"use client";

import Link from "next/link";
import { FormEvent, ReactNode, useCallback, useEffect, useMemo, useRef, useState } from "react";
import { createPortal } from "react-dom";
import { api, ApiError, Application, NodeRecord, Plan, Quota, User } from "@/lib/api";
import { clearAuthToken, getAuthToken } from "@/lib/auth-token";
import { LanguageSwitcher } from "./language-switcher";
import { SelectField } from "./select-field";
import { StatusBadge } from "./status-badge";
import { useI18n } from "@/lib/i18n";

const ADMIN_SECTIONS = [
  "admin-overview",
  "admin-nodes",
  "admin-users",
  "admin-plans",
  "admin-applications",
  "admin-build-queue",
] as const;

type AdminSection = (typeof ADMIN_SECTIONS)[number];

type HostMetrics = {
  available: boolean;
  cpu_percent: number | null;
  memory_used_gb: number | null;
  memory_total_gb: number | null;
  disk_used_percent: number | null;
  disk_total_gb: number | null;
  uptime_seconds: number | null;
  message: string;
};
type Failure = {
  id: string;
  error_code: string | null;
  finished_at: string;
  application: { id: string; name: string; owner: string };
};
type Consumer = {
  application: { id: string; name: string; owner: string };
  usage: { cpu_percent: number; memory_mb: number; disk_mb: number };
  highest_utilization_percent: number;
};
type ProductMetrics = {
  registrations: number;
  verified_users: number;
  application_creations: number;
  successful_first_deployments: number;
  deployment_success_rate_percent: number;
  median_time_to_first_live_seconds: number;
  repeat_deployment_rate_percent: number;
  active_apps_after_7_days: number;
  restart_actions: number;
};
type Overview = {
  users: number;
  apps: number;
  running: number;
  failed: number;
  queued: number;
  provider: { connected: boolean; message: string };
  host: HostMetrics;
  recent_failures: Failure[];
  top_consumers: Consumer[];
  product_metrics: ProductMetrics;
};
type QueueItem = {
  id: string;
  status: string;
  created_at: string;
  application: { id: string; name: string; owner: string };
};
type UserDetail = { user: User; applications: Application[] };
type NodeFilter = "ALL" | "READY" | "EMPTY" | "ATTENTION";

export function AdminDashboard() {
  const { t, href, dateLocale } = useI18n();
  const [overview, setOverview] = useState<Overview | null>(null);
  const [users, setUsers] = useState<User[]>([]);
  const [apps, setApps] = useState<Application[]>([]);
  const [queue, setQueue] = useState<QueueItem[]>([]);
  const [nodes, setNodes] = useState<NodeRecord[]>([]);
  const [plans, setPlans] = useState<Plan[]>([]);
  const [currentUser, setCurrentUser] = useState<User | null>(null);
  const [showCreateNode, setShowCreateNode] = useState(false);
  const [selectedNode, setSelectedNode] = useState<NodeRecord | null>(null);
  const [selectedUser, setSelectedUser] = useState<UserDetail | null>(null);
  const [userDetailLoading, setUserDetailLoading] = useState(false);
  const [nodeFilter, setNodeFilter] = useState<NodeFilter>("ALL");
  const [error, setError] = useState("");
  const [notice, setNotice] = useState("");
  const [search, setSearch] = useState("");
  const [submittedSearch, setSubmittedSearch] = useState("");
  const [mobileNavOpen, setMobileNavOpen] = useState(false);
  const [activeSection, setActiveSection] =
    useState<AdminSection>("admin-overview");
  const [quotaEditor, setQuotaEditor] = useState<{
    user: User;
    quota: Quota;
  } | null>(null);
  const [subscriptionEditor, setSubscriptionEditor] = useState<User | null>(null);
  const [planEditor, setPlanEditor] = useState<Plan | "NEW" | null>(null);
  const token = getAuthToken();

  const nodeSummary = useMemo(() => ({
    total: nodes.length,
    ready: nodes.filter(isNodeReady).length,
    empty: nodes.filter((node) => node.applications_count === 0).length,
    attention: nodes.filter(nodeNeedsAttention).length,
    applications: nodes.reduce((total, node) => total + node.applications_count, 0),
  }), [nodes]);
  const visibleNodes = useMemo(() => nodes.filter((node) => {
    if (nodeFilter === "READY") return isNodeReady(node);
    if (nodeFilter === "EMPTY") return node.applications_count === 0;
    if (nodeFilter === "ATTENTION") return nodeNeedsAttention(node);
    return true;
  }), [nodeFilter, nodes]);

  const load = useCallback(async () => {
    if (!token) {
      setError(t("Hãy đăng nhập bằng tài khoản quản trị."));
      return;
    }
    setError("");
    try {
      const query = submittedSearch
        ? `?search=${encodeURIComponent(submittedSearch)}`
        : "";
      const [stats, userList, appList, queueList, nodeList, planList, profile] = await Promise.all([
        api<Overview>("/admin/system/overview", {}, token),
        api<User[]>(`/admin/users${query}`, {}, token),
        api<Application[]>(`/admin/apps${query}`, {}, token),
        api<QueueItem[]>("/admin/system/build-queue", {}, token),
        api<NodeRecord[]>("/admin/nodes", {}, token),
        api<Plan[]>("/admin/plans", {}, token),
        api<User>("/me", {}, token),
      ]);
      setOverview(stats);
      setUsers(userList);
      setApps(appList);
      setQueue(queueList);
      setNodes(nodeList);
      setPlans(planList);
      setCurrentUser(profile);
    } catch (caught) {
      setError(
        caught instanceof ApiError
          ? t(caught.message)
          : t("Tài khoản không có quyền quản trị hoặc API chưa sẵn sàng."),
      );
    }
  }, [submittedSearch, t, token]);

  useEffect(() => {
    Promise.resolve().then(load);
  }, [load]);

  useEffect(() => {
    const syncSectionFromHash = () => {
      const section = window.location.hash.slice(1) as AdminSection;
      if (ADMIN_SECTIONS.includes(section)) setActiveSection(section);
    };

    syncSectionFromHash();
    window.addEventListener("hashchange", syncSectionFromHash);
    window.addEventListener("popstate", syncSectionFromHash);
    return () => {
      window.removeEventListener("hashchange", syncSectionFromHash);
      window.removeEventListener("popstate", syncSectionFromHash);
    };
  }, []);

  const navigateToSection = (section: AdminSection) => {
    setActiveSection(section);
    setSelectedNode(null);
    setSelectedUser(null);
    if (window.location.hash !== `#${section}`) {
      window.history.pushState(
        window.history.state,
        "",
        `${window.location.pathname}${window.location.search}#${section}`,
      );
    }
    window.scrollTo({ top: 0, behavior: "smooth" });
  };

  const sectionCopy: Record<AdminSection, { title: string; description: string }> = {
    "admin-overview": {
      title: t("Quản trị hệ thống"),
      description: t("Theo dõi sức khỏe và các tín hiệu quan trọng của nền tảng."),
    },
    "admin-nodes": {
      title: t("Hạ tầng Nodes"),
      description: t("Quản lý sức chứa, trạng thái và phân bổ ứng dụng trên từng node."),
    },
    "admin-users": {
      title: t("Quản lý người dùng"),
      description: t("Tìm kiếm tài khoản, điều chỉnh hạn mức và trạng thái truy cập."),
    },
    "admin-plans": {
      title: t("Gói dịch vụ"),
      description: t("Thiết lập giá, tài nguyên và trạng thái bán của từng gói."),
    },
    "admin-applications": {
      title: t("Quản lý ứng dụng"),
      description: t("Theo dõi workload và thực hiện các thao tác vận hành an toàn."),
    },
    "admin-build-queue": {
      title: t("Hàng đợi build"),
      description: t("Theo dõi các deployment đang chờ được xử lý."),
    },
  };

  const runAction = async (action: () => Promise<unknown>) => {
    setError("");
    try {
      await action();
      await load();
    } catch (caught) {
      setError(
        caught instanceof ApiError
          ? t(caught.message)
          : t("Không thể hoàn tất thao tác quản trị."),
      );
    }
  };

  const savePlan = async (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault();
    if (!token || !planEditor) return;
    const form = new FormData(event.currentTarget);
    const payload = {
      code: String(form.get("code")), name: String(form.get("name")),
      monthly_price_vnd: Number(form.get("monthly_price_vnd")), max_apps: Number(form.get("max_apps")),
      max_memory_mb_per_app: Number(form.get("max_memory_mb_per_app")), max_cpu_per_app: Number(form.get("max_cpu_per_app")),
      max_disk_mb_per_app: Number(form.get("max_disk_mb_per_app")), max_build_concurrency: Number(form.get("max_build_concurrency")),
      is_default: form.get("is_default") === "true", is_published: form.get("is_published") === "true",
    };
    try {
      await api(planEditor === "NEW" ? "/admin/plans" : `/admin/plans/${planEditor.id}`, {
        method: planEditor === "NEW" ? "POST" : "PATCH", body: JSON.stringify(payload),
      }, token);
      setPlanEditor(null);
      setNotice(t(planEditor === "NEW" ? "Đã tạo gói {name}." : "Đã cập nhật gói {name}.", { name: payload.name }));
      await load();
    } catch (caught) {
      setError(caught instanceof ApiError ? t(caught.message) : t("Không thể lưu gói dịch vụ."));
    }
  };
  const changeStatus = async (user: User) => {
    if (!token) return;
    const action = user.status === "SUSPENDED" ? "activate" : "suspend";
    await runAction(() =>
      api(
        `/admin/users/${user.id}/${action}`,
        { method: "POST", body: "{}" },
        token,
      ),
    );
  };
  const operateApp = async (
    app: Application,
    action: "restart" | "stop" | "redeploy",
  ) => {
    if (!token) return;
    await runAction(() =>
      api(
        `/admin/apps/${app.id}/${action}`,
        { method: "POST", body: "{}" },
        token,
      ),
    );
  };
  const editQuota = async (user: User) => {
    if (!token) return;
    try {
      const detail = await api<{ user: User }>(
        `/admin/users/${user.id}`,
        {},
        token,
      );
      if (!detail.user.quota) {
        setError(t("Không thể khởi tạo hạn mức cho người dùng này."));
        return;
      }
      setQuotaEditor({ user, quota: detail.user.quota });
    } catch (caught) {
      setError(
        caught instanceof ApiError
          ? t(caught.message)
          : t("Không thể tải quota."),
      );
    }
  };
  const openUserDetail = async (user: User) => {
    if (!token) return;
    setUserDetailLoading(true);
    setSelectedUser({ user, applications: [] });
    try {
      setSelectedUser(await api<UserDetail>(`/admin/users/${user.id}`, {}, token));
    } catch (caught) {
      setSelectedUser(null);
      setError(caught instanceof ApiError ? t(caught.message) : t("Không thể tải chi tiết người dùng."));
    } finally {
      setUserDetailLoading(false);
    }
  };
  const saveQuota = async (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault();
    if (!token || !quotaEditor) return;
    const form = new FormData(event.currentTarget);
    await runAction(() =>
      api(
        `/admin/users/${quotaEditor.user.id}/quota`,
        {
          method: "PATCH",
          body: JSON.stringify({
            max_apps: Number(form.get("max_apps")),
            max_memory_mb_per_app: Number(form.get("memory")),
            max_cpu_per_app: Number(form.get("cpu")),
            max_disk_mb_per_app: Number(form.get("disk")),
            max_build_concurrency: Number(form.get("builds")),
          }),
        },
        token,
      ),
    );
    setQuotaEditor(null);
    setNotice(t("Đã lưu hạn mức cho {name}.", { name: quotaEditor.user.name }));
  };
  const saveSubscription = async (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault();
    if (!token || !subscriptionEditor) return;
    const form = new FormData(event.currentTarget);
    await runAction(() => api<User>(
      `/admin/users/${subscriptionEditor.id}/subscription`,
      {
        method: "PATCH",
        body: JSON.stringify({
          plan_id: form.get("plan_id"),
          status: form.get("status"),
          duration_months: Number(form.get("duration_months")),
        }),
      },
      token,
    ));
    setSubscriptionEditor(null);
    setNotice(t("Đã cập nhật gói cho {name}.", { name: subscriptionEditor.name }));
  };
  const deleteApp = async (app: Application) => {
    if (
      !token ||
      !window.confirm(
        t("Xóa ứng dụng {name} và toàn bộ tài nguyên provider?", {
          name: app.name,
        }),
      )
    )
      return;
    await runAction(() =>
      api(`/admin/apps/${app.id}`, { method: "DELETE" }, token),
    );
  };

  const changeNodeStatus = async (
    node: NodeRecord,
    action: "activate" | "drain" | "maintenance" | "disable",
  ) => {
    if (!token) return;
    await runAction(() =>
      api(`/admin/nodes/${node.id}/${action}`, { method: "POST", body: "{}" }, token),
    );
  };

  const createNode = async (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault();
    if (!token) return;
    const form = new FormData(event.currentTarget);
    await runAction(() =>
      api(
        "/admin/nodes",
        {
          method: "POST",
          body: JSON.stringify({
            name: form.get("name"),
            code: String(form.get("code")).toUpperCase(),
            provider: form.get("provider"),
            provider_server_id: form.get("provider_server_id") || null,
            host: form.get("host") || null,
            region: form.get("region") || null,
            cpu_total: Number(form.get("cpu_total")),
            memory_total_mb: Number(form.get("memory_total_mb")),
            disk_total_mb: Number(form.get("disk_total_mb")),
          }),
        },
        token,
      ),
    );
    setShowCreateNode(false);
  };

  const logout = async () => {
    if (token) {
      try {
        await api("/auth/logout", { method: "POST", body: "{}" }, token);
      } catch {
        // Local sign-out must still succeed if the API is temporarily unavailable.
      }
    }
    clearAuthToken();
    window.location.assign(href("/dashboard"));
  };

  return (
    <div className="shell admin-shell">
      <aside className={`sidebar${mobileNavOpen ? " mobile-open" : ""}`}>
        <Link
          className="brand"
          href={href("/")}
          onClick={() => setMobileNavOpen(false)}
        >
          <span>V</span> {t("Vive Admin")}
        </Link>
        <nav aria-label={t("Điều hướng dashboard")}>
          <span className="admin-nav-label">{t("Vận hành")}</span>
          <a
            href="#admin-overview"
            className={activeSection === "admin-overview" ? "active" : ""}
            aria-current={activeSection === "admin-overview" ? "location" : undefined}
            onClick={(event) => {
              event.preventDefault();
              navigateToSection("admin-overview");
              setMobileNavOpen(false);
            }}
          >
            {t("Tổng quan")}
          </a>
          <span className="admin-nav-label">{t("Quản lý")}</span>
          <a
            href="#admin-nodes"
            className={activeSection === "admin-nodes" ? "active" : ""}
            aria-current={activeSection === "admin-nodes" ? "location" : undefined}
            onClick={(event) => {
              event.preventDefault();
              navigateToSection("admin-nodes");
              setMobileNavOpen(false);
            }}
          >
            {t("Hạ tầng Nodes")} <em>{nodes.length}</em>
          </a>
          <a
            href="#admin-users"
            className={activeSection === "admin-users" ? "active" : ""}
            aria-current={activeSection === "admin-users" ? "location" : undefined}
            onClick={(event) => {
              event.preventDefault();
              navigateToSection("admin-users");
              setMobileNavOpen(false);
            }}
          >
            {t("Người dùng")} <em>{users.length}</em>
          </a>
          <a
            href="#admin-applications"
            className={activeSection === "admin-applications" ? "active" : ""}
            aria-current={activeSection === "admin-applications" ? "location" : undefined}
            onClick={(event) => {
              event.preventDefault();
              navigateToSection("admin-applications");
              setMobileNavOpen(false);
            }}
          >
            {t("Ứng dụng")} <em>{apps.length}</em>
          </a>
          <a
            href="#admin-plans"
            className={activeSection === "admin-plans" ? "active" : ""}
            aria-current={activeSection === "admin-plans" ? "location" : undefined}
            onClick={(event) => {
              event.preventDefault();
              navigateToSection("admin-plans");
              setMobileNavOpen(false);
            }}
          >
            {t("Gói dịch vụ")} <em>{plans.length}</em>
          </a>
          <a
            href="#admin-build-queue"
            className={activeSection === "admin-build-queue" ? "active" : ""}
            aria-current={activeSection === "admin-build-queue" ? "location" : undefined}
            onClick={(event) => {
              event.preventDefault();
              navigateToSection("admin-build-queue");
              setMobileNavOpen(false);
            }}
          >
            {t("Build queue")} <em>{queue.length}</em>
          </a>
          <Link
            href={href("/dashboard")}
            onClick={() => setMobileNavOpen(false)}
          >
            ← {t("Dashboard người dùng")}
          </Link>
        </nav>
        <div className="sidebar-bottom">
          <small>{t("Đang đăng nhập")}</small>
          <p>{currentUser?.name ?? t("Quản trị hệ thống")}</p>
          {currentUser?.email && <span>{currentUser.email}</span>}
          <button onClick={logout}>{t("Đăng xuất")}</button>
        </div>
      </aside>
      {mobileNavOpen && (
        <button
          className="mobile-backdrop"
          aria-label={t("Đóng")}
          onClick={() => setMobileNavOpen(false)}
        />
      )}
      <main className="dashboard-main">
        <header className="topbar">
          <button
            className="mobile-brand"
            aria-label={t("Menu")}
            aria-expanded={mobileNavOpen}
            onClick={() => setMobileNavOpen(true)}
          >
            V
          </button>
          <span>{t("Admin workspace")}</span>
          <div className="topbar-actions">
            <LanguageSwitcher compact />
            <StatusBadge status="ACTIVE" />
          </div>
        </header>
        <div className="content">
          <div className="page-header admin-page-header">
            <div>
              <span className="admin-page-kicker">{t("Không gian quản trị")}</span>
              <h1>{sectionCopy[activeSection].title}</h1>
              <p>{sectionCopy[activeSection].description}</p>
            </div>
            <button className="button secondary" onClick={load}>
              {t("Làm mới")}
            </button>
          </div>
          {error && (
            <div className="alert">
              <b>{t("Không thể hoàn tất")}</b>
              <span>{error}</span>
              <button onClick={() => setError("")}>×</button>
            </div>
          )}
          {notice && (
            <div className="alert success-alert" role="status">
              <b>{t("Đã cập nhật")}</b>
              <span>{notice}</span>
              <button onClick={() => setNotice("")}>×</button>
            </div>
          )}
          {activeSection === "admin-overview" && overview && (
            <>
              <section className="stat-strip admin-stats">
                <div>
                  <span>{t("Người dùng")}</span>
                  <b>{overview.users}</b>
                </div>
                <div>
                  <span>{t("Ứng dụng")}</span>
                  <b>{overview.apps}</b>
                </div>
                <div>
                  <span>{t("Đang chạy")}</span>
                  <b className="healthy">{overview.running}</b>
                </div>
                <div>
                  <span>{t("Thất bại / Queue")}</span>
                  <b className={overview.failed ? "danger" : ""}>
                    {overview.failed} / {overview.queued}
                  </b>
                </div>
              </section>
              <div
                className={`provider-health ${overview.provider.connected ? "connected" : "disconnected"}`}
              >
                <StatusBadge
                  status={
                    overview.provider.connected ? "CONNECTED" : "DISCONNECTED"
                  }
                />
                <span>{t(overview.provider.message)}</span>
              </div>
              <section className="section host-overview">
                <div className="section-title">
                  <h2>{t("Tài nguyên host")}</h2>
                  <span>{t(overview.host.message)}</span>
                </div>
                {overview.host.available ? (
                  <div className="host-metrics">
                    <Metric
                      label="CPU"
                      value={`${formatNumber(overview.host.cpu_percent, dateLocale)}%`}
                    />
                    <Metric
                      label="RAM"
                      value={`${formatNumber(overview.host.memory_used_gb, dateLocale)} / ${formatNumber(overview.host.memory_total_gb, dateLocale)} GB`}
                    />
                    <Metric
                      label={t("Disk")}
                      value={`${formatNumber(overview.host.disk_used_percent, dateLocale)}% · ${formatNumber(overview.host.disk_total_gb, dateLocale)} GB`}
                    />
                    <Metric
                      label={t("Uptime")}
                      value={formatUptime(overview.host.uptime_seconds, t)}
                    />
                  </div>
                ) : (
                  <div className="small-empty">{t(overview.host.message)}</div>
                )}
              </section>
              <section className="section host-overview">
                <div className="section-title">
                  <h2>{t("Product signals")}</h2>
                  <span>{t("Open Beta")}</span>
                </div>
                <div className="compact-list">
                  <ProductSignal
                    label={t("Tài khoản xác minh")}
                    value={`${overview.product_metrics.verified_users} / ${overview.product_metrics.registrations}`}
                  />
                  <ProductSignal
                    label={t("App được tạo")}
                    value={String(
                      overview.product_metrics.application_creations,
                    )}
                  />
                  <ProductSignal
                    label={t("First deploy thành công")}
                    value={String(
                      overview.product_metrics.successful_first_deployments,
                    )}
                  />
                  <ProductSignal
                    label={t("Tỷ lệ deploy thành công")}
                    value={`${overview.product_metrics.deployment_success_rate_percent}%`}
                  />
                  <ProductSignal
                    label={t("Median time-to-live")}
                    value={formatDuration(
                      overview.product_metrics
                        .median_time_to_first_live_seconds,
                      t,
                    )}
                  />
                  <ProductSignal
                    label={t("App quay lại deploy")}
                    value={`${overview.product_metrics.repeat_deployment_rate_percent}%`}
                  />
                  <ProductSignal
                    label={t("App active sau 7 ngày")}
                    value={String(
                      overview.product_metrics.active_apps_after_7_days,
                    )}
                  />
                  <ProductSignal
                    label={t("Lần restart")}
                    value={String(overview.product_metrics.restart_actions)}
                  />
                </div>
              </section>
              <div className="admin-observability">
                <section className="section admin-section">
                  <div className="section-title">
                    <h2>{t("Deployment thất bại gần đây")}</h2>
                    <span>
                      {t("{count} sự kiện", {
                        count: overview.recent_failures.length,
                      })}
                    </span>
                  </div>
                  {overview.recent_failures.length ? (
                    <div className="compact-list">
                      {overview.recent_failures.map((failure) => (
                        <div key={failure.id}>
                          <StatusBadge status="FAILED" />
                          <div>
                            <b>{failure.application.name}</b>
                            <small>
                              {failure.error_code ?? "DEPLOY_FAILED"} ·{" "}
                              {failure.application.owner}
                            </small>
                          </div>
                          <time>
                            {new Date(failure.finished_at).toLocaleString(
                              dateLocale,
                            )}
                          </time>
                        </div>
                      ))}
                    </div>
                  ) : (
                    <div className="small-empty">
                      {t("Chưa có deployment thất bại.")}
                    </div>
                  )}
                </section>
                <section className="section admin-section">
                  <div className="section-title">
                    <h2>{t("Ứng dụng dùng nhiều tài nguyên")}</h2>
                    <span>Top {overview.top_consumers.length}</span>
                  </div>
                  {overview.top_consumers.length ? (
                    <div className="compact-list">
                      {overview.top_consumers.map((consumer) => (
                        <div key={consumer.application.id}>
                          <b className="utilization">
                            {consumer.highest_utilization_percent}%
                          </b>
                          <div>
                            <b>{consumer.application.name}</b>
                            <small>
                              {consumer.usage.memory_mb} MB RAM ·{" "}
                              {consumer.usage.cpu_percent}% CPU ·{" "}
                              {consumer.usage.disk_mb} MB disk
                            </small>
                          </div>
                          <span>{consumer.application.owner}</span>
                        </div>
                      ))}
                    </div>
                  ) : (
                    <div className="small-empty">
                      {t("Chưa có ứng dụng đang chạy.")}
                    </div>
                  )}
                </section>
              </div>
            </>
          )}
          {activeSection === "admin-nodes" && (
          <div id="admin-nodes" className="admin-workspace node-workspace">
            <section className="node-summary" aria-label={t("Tổng quan node")}>
              <SummaryCard label={t("Tổng số node")} value={nodeSummary.total} tone="neutral" />
              <SummaryCard label={t("Sẵn sàng nhận app")} value={nodeSummary.ready} tone="success" />
              <SummaryCard label={t("Node đang trống")} value={nodeSummary.empty} tone="info" />
              <SummaryCard label={t("Cần chú ý")} value={nodeSummary.attention} tone={nodeSummary.attention ? "danger" : "neutral"} />
              <SummaryCard label={t("App đã phân bổ")} value={nodeSummary.applications} tone="neutral" />
            </section>
            <section className="section node-section">
              <div className="section-title node-section-head">
                <div>
                  <h2>{t("Danh sách node triển khai")}</h2>
                  <span>{t("Nhấn vào một node để xem app, owner và tài nguyên đã cấp phát.")}</span>
                </div>
                <button className="button primary small" onClick={() => setShowCreateNode(true)}>
                  {t("+ Thêm node")}
                </button>
              </div>
              <div className="admin-filterbar" role="group" aria-label={t("Lọc node")}>
                {([
                  ["ALL", t("Tất cả"), nodeSummary.total],
                  ["READY", t("Sẵn sàng"), nodeSummary.ready],
                  ["EMPTY", t("Đang trống"), nodeSummary.empty],
                  ["ATTENTION", t("Có vấn đề"), nodeSummary.attention],
                ] as [NodeFilter, string, number][]).map(([value, label, count]) => (
                  <button key={value} className={nodeFilter === value ? "active" : ""} onClick={() => setNodeFilter(value)}>
                    {label}<span>{count}</span>
                  </button>
                ))}
              </div>
              {visibleNodes.length ? (
                <div className="table-wrap">
                  <table className="node-table operational-table">
                    <thead>
                      <tr>
                        <th>{t("Node / khu vực")}</th>
                        <th>{t("Sức khỏe")}</th>
                        <th>{t("Workload")}</th>
                        <th>{t("Tài nguyên đã đặt trước")}</th>
                        <th>{t("Heartbeat cuối")}</th>
                        <th />
                      </tr>
                    </thead>
                    <tbody>
                      {visibleNodes.map((node) => (
                        <tr key={node.id} onClick={() => setSelectedNode(node)}>
                          <td>
                            <div className="node-identity">
                              <span className={`node-health-dot ${nodeNeedsAttention(node) ? "attention" : "healthy"}`} />
                              <div><b>{node.name}</b><small>{node.code} · {node.region ?? t("Không xác định")}</small></div>
                            </div>
                          </td>
                          <td data-label={t("Sức khỏe")}>
                            <StatusBadge status={node.status} />
                            <small className={nodeNeedsAttention(node) ? "warning-text" : "healthy-text"}>{nodeHealthLabel(node, t)}</small>
                          </td>
                          <td data-label={t("Workload")}>
                            <b>{t("{count} app", { count: node.applications_count })}</b>
                            <small>{node.applications_count ? t("Đang giữ tài nguyên") : t("Chưa có workload")}</small>
                          </td>
                          <td data-label={t("Tài nguyên đã đặt trước")}>
                            <div className="node-resource-stack">
                              <MiniCapacity label="CPU" reserved={node.capacity.cpu.reserved} total={node.capacity.cpu.total} unit="" locale={dateLocale} />
                              <MiniCapacity label="RAM" reserved={node.capacity.memory_mb.reserved} total={node.capacity.memory_mb.total} unit="MB" locale={dateLocale} />
                              <MiniCapacity label="Disk" reserved={node.capacity.disk_mb.reserved} total={node.capacity.disk_mb.total} unit="MB" locale={dateLocale} />
                            </div>
                          </td>
                          <td data-label={t("Heartbeat cuối")}>
                            <b>{formatRelativeHeartbeat(node.last_heartbeat_at, t)}</b>
                            <small>{node.last_heartbeat_at ? new Date(node.last_heartbeat_at).toLocaleString(dateLocale) : t("Chưa nhận heartbeat")}</small>
                          </td>
                          <td onClick={(event) => event.stopPropagation()}>
                            <ActionMenu label={t("Thao tác")}>
                              {node.status !== "ACTIVE" && <button onClick={() => changeNodeStatus(node, "activate")}>{t("Kích hoạt")}</button>}
                              {node.status === "ACTIVE" && <button onClick={() => changeNodeStatus(node, "drain")}>{t("Drain node")}</button>}
                              <button onClick={() => changeNodeStatus(node, "maintenance")}>{t("Chuyển sang bảo trì")}</button>
                              <button className="danger" onClick={() => changeNodeStatus(node, "disable")}>{t("Vô hiệu hóa node")}</button>
                            </ActionMenu>
                          </td>
                        </tr>
                      ))}
                    </tbody>
                  </table>
                </div>
              ) : <div className="small-empty">{t("Không có node phù hợp bộ lọc.")}</div>}
            </section>
          </div>
          )}
          {activeSection === "admin-users" && (
          <form
            className="admin-search"
            onSubmit={(event) => {
              event.preventDefault();
              setSubmittedSearch(search.trim());
            }}
          >
            <input
              value={search}
              onChange={(event) => setSearch(event.target.value)}
              placeholder={t("Tìm theo tên, email hoặc repository…")}
            />
            <button className="button secondary">{t("Tìm kiếm")}</button>
          </form>
          )}
          {activeSection === "admin-users" && (
          <section id="admin-users" className="section admin-section admin-workspace">
            <div className="section-title">
              <h2>{t("Người dùng gần đây")}</h2>
              <span>{t("{count} tài khoản", { count: users.length })}</span>
            </div>
            <div className="table-wrap">
              <table>
                <thead>
                  <tr>
                    <th>{t("Người dùng")}</th>
                    <th>{t("Trạng thái")}</th>
                    <th>{t("Gói / thời hạn")}</th>
                    <th>{t("Apps")}</th>
                    <th>{t("RAM cấp phát")}</th>
                    <th>{t("Tham gia")}</th>
                    <th />
                  </tr>
                </thead>
                <tbody>
                  {users.map((user) => (
                    <tr key={user.id} onClick={() => openUserDetail(user)}>
                      <td>
                        <b>{user.name}</b>
                        <small>
                          {user.email} · {t(user.role)}
                        </small>
                      </td>
                      <td data-label={t("Trạng thái")}>
                        <StatusBadge status={user.status} />
                      </td>
                      <td data-label={t("Gói / thời hạn")}>
                        <b>{user.subscription?.plan.name ?? t("Chưa có gói")}</b>
                        <small>{subscriptionTimeLabel(user, dateLocale, t)}</small>
                      </td>
                      <td data-label={t("Apps")}>{user.applications_count ?? 0}</td>
                      <td data-label={t("RAM cấp phát")}>{user.applications_memory_limit_mb ?? 0} MB</td>
                      <td data-label={t("Tham gia")}>
                        {user.created_at
                          ? new Date(user.created_at).toLocaleDateString(
                              dateLocale,
                            )
                          : "—"}
                      </td>
                      <td onClick={(event) => event.stopPropagation()}>
                        <div className="row-actions">
                          <ActionMenu label={t("Thao tác")}>
                            <button onClick={() => editQuota(user)}>{t("Chỉnh hạn mức")}</button>
                            <button onClick={() => setSubscriptionEditor(user)}>{t("Quản lý gói")}</button>
                            <button onClick={() => changeStatus(user)}>
                              {user.status === "SUSPENDED"
                                ? t("Kích hoạt")
                                : t("Tạm ngưng tài khoản")}
                            </button>
                          </ActionMenu>
                        </div>
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          </section>
          )}
          {activeSection === "admin-plans" && (
          <section id="admin-plans" className="section admin-section admin-workspace plan-workspace">
            <div className="section-title">
              <div><h2>{t("Danh sách gói dịch vụ")}</h2><span>{t("Giá và quyền lợi áp dụng ngay cho người đăng ký gói.")}</span></div>
              <button className="button primary small" onClick={() => setPlanEditor("NEW")}>{t("+ Thêm gói")}</button>
            </div>
            <div className="table-wrap">
              <table className="plan-table">
                <thead><tr><th>{t("Gói")}</th><th>{t("Giá tháng")}</th><th>{t("Ứng dụng")}</th><th>CPU / RAM</th><th>{t("Disk")}</th><th>{t("Build đồng thời")}</th><th>{t("Trạng thái bán")}</th><th /></tr></thead>
                <tbody>{plans.map((plan) => (
                  <tr key={plan.id}>
                    <td><b>{plan.name}</b><small>{plan.code}{plan.is_default ? ` · ${t("Mặc định")}` : ""}</small></td>
                    <td data-label={t("Giá tháng")}><b>{formatPlanPrice(plan, dateLocale, t)}</b></td>
                    <td data-label={t("Ứng dụng")}><b>{plan.max_apps}</b></td>
                    <td data-label="CPU / RAM"><b>{plan.max_cpu_per_app} CPU · {plan.max_memory_mb_per_app} MB</b><small>{t("mỗi ứng dụng")}</small></td>
                    <td data-label={t("Disk")}><b>{formatDisk(plan.max_disk_mb_per_app, dateLocale)}</b></td>
                    <td data-label={t("Build đồng thời")}><b>{plan.max_build_concurrency}</b></td>
                    <td data-label={t("Trạng thái bán")}><span className={`status ${plan.is_published ? "status-active" : "status-disabled"}`}><i />{t(plan.is_published ? "Đang công bố" : "Đang ẩn")}</span></td>
                    <td><button className="icon-action" aria-label={t("Chỉnh sửa gói {name}", { name: plan.name })} onClick={() => setPlanEditor(plan)}>•••</button></td>
                  </tr>
                ))}</tbody>
              </table>
            </div>
          </section>
          )}
          {activeSection === "admin-applications" && (
          <form
            className="admin-search"
            onSubmit={(event) => {
              event.preventDefault();
              setSubmittedSearch(search.trim());
            }}
          >
            <input
              value={search}
              onChange={(event) => setSearch(event.target.value)}
              placeholder={t("Tìm theo tên, email hoặc repository…")}
            />
            <button className="button secondary">{t("Tìm kiếm")}</button>
          </form>
          )}
          {activeSection === "admin-applications" && (
          <section id="admin-applications" className="section admin-section admin-workspace">
            <div className="section-title">
              <h2>{t("Tất cả ứng dụng")}</h2>
              <span>{t("{count} ứng dụng", { count: apps.length })}</span>
            </div>
            <div className="table-wrap">
              <table>
                <thead>
                  <tr>
                    <th>{t("Ứng dụng")}</th>
                    <th>{t("Owner")}</th>
                    <th>{t("Node")}</th>
                    <th>{t("Trạng thái")}</th>
                    <th>{t("Domain")}</th>
                    <th>{t("RAM / CPU")}</th>
                    <th>{t("Deploy cuối")}</th>
                    <th />
                  </tr>
                </thead>
                <tbody>
                  {apps.map((app) => (
                    <tr key={app.id}>
                      <td>
                        <b>{app.name}</b>
                        <small>
                          {app.repository_url.replace(
                            "https://github.com/",
                            "",
                          )}
                        </small>
                      </td>
                      <td data-label={t("Owner")}>{app.owner?.email ?? "—"}</td>
                      <td data-label={t("Node")}>
                        {app.node ? <><b>{app.node.name}</b><small><StatusBadge status={app.node.status} /></small></> : "—"}
                      </td>
                      <td data-label={t("Trạng thái")}>
                        <StatusBadge status={app.status} />
                      </td>
                      <td data-label={t("Domain")}>{app.domain ?? t("Đang cấp phát")}</td>
                      <td data-label={t("RAM / CPU")}>
                        <b>{app.resources.memory_mb} MB · {app.resources.cpu} CPU</b>
                        <small>{formatStorage(app.resources.disk_mb, dateLocale)} Disk</small>
                      </td>
                      <td data-label={t("Deploy cuối")}>
                        {app.latest_deployment ? (
                          <>
                            <StatusBadge
                              status={app.latest_deployment.status}
                            />
                            <small>
                              {new Date(
                                app.latest_deployment.created_at,
                              ).toLocaleString(dateLocale)}
                            </small>
                          </>
                        ) : (
                          t("Chưa deploy")
                        )}
                      </td>
                      <td>
                        <ActionMenu label={t("Thao tác")}>
                          <button onClick={() => operateApp(app, "redeploy")}>
                            {t("Redeploy")}
                          </button>
                          <button onClick={() => operateApp(app, "restart")}>
                            {t("Restart")}
                          </button>
                          <button onClick={() => operateApp(app, "stop")}>
                            {t("Stop")}
                          </button>
                          <button
                            className="danger"
                            onClick={() => deleteApp(app)}
                          >
                            {t("Xóa")}
                          </button>
                        </ActionMenu>
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          </section>
          )}
          {activeSection === "admin-build-queue" && (
          <section id="admin-build-queue" className="section admin-section admin-workspace">
            <div className="section-title">
              <h2>{t("Build queue")}</h2>
              <span>{t("{count} jobs", { count: queue.length })}</span>
            </div>
            {queue.length ? (
              <div className="key-list settings-body">
                {queue.map((item) => (
                  <div key={item.id}>
                    <StatusBadge status={item.status} />
                    <b>{item.application.name}</b>
                    <span>{item.application.owner}</span>
                    <time>
                      {new Date(item.created_at).toLocaleString(dateLocale)}
                    </time>
                  </div>
                ))}
              </div>
            ) : (
              <div className="small-empty">{t("Build queue đang trống.")}</div>
            )}
          </section>
          )}
        </div>
      </main>
      {selectedNode && (
        <div className="overlay" onMouseDown={() => setSelectedNode(null)}>
          <aside className="drawer infrastructure-drawer" onMouseDown={(event) => event.stopPropagation()}>
            <div className="drawer-head">
              <div>
                <span className="drawer-kicker">{t("NODE DETAIL")}</span>
                <h2>{selectedNode.name}</h2>
                <p>{selectedNode.code} · {selectedNode.region ?? t("Không xác định")}</p>
              </div>
              <button aria-label={t("Đóng")} onClick={() => setSelectedNode(null)}>×</button>
            </div>
            <div className="drawer-status-line">
              <StatusBadge status={selectedNode.status} />
              <span className={nodeNeedsAttention(selectedNode) ? "warning-text" : "healthy-text"}>{nodeHealthLabel(selectedNode, t)}</span>
            </div>
            <section className="drawer-capacity">
              <div className="section-title"><h3>{t("Tài nguyên đã đặt trước")}</h3><span>{t("Đã cấp phát / Tổng")}</span></div>
              <CapacityDetail label="CPU" reserved={selectedNode.capacity.cpu.reserved} total={selectedNode.capacity.cpu.total} unit="CPU" locale={dateLocale} t={t} />
              <CapacityDetail label="RAM" reserved={selectedNode.capacity.memory_mb.reserved} total={selectedNode.capacity.memory_mb.total} unit="MB" locale={dateLocale} t={t} />
              <CapacityDetail label="Disk" reserved={selectedNode.capacity.disk_mb.reserved} total={selectedNode.capacity.disk_mb.total} unit="MB" locale={dateLocale} t={t} />
            </section>
            <section className="drawer-workloads">
              <div className="section-title"><h3>{t("Ứng dụng trên node")}</h3><span>{t("{count} app", { count: apps.filter((app) => app.node?.id === selectedNode.id).length })}</span></div>
              {apps.filter((app) => app.node?.id === selectedNode.id).length ? (
                <div className="workload-list">
                  {apps.filter((app) => app.node?.id === selectedNode.id).map((app) => (
                    <div key={app.id}>
                      <div><b>{app.name}</b><small>{app.owner?.email ?? "—"}</small></div>
                      <StatusBadge status={app.status} />
                      <span>{app.resources.cpu} CPU · {formatStorage(app.resources.memory_mb, dateLocale)} RAM · {formatStorage(app.resources.disk_mb, dateLocale)} Disk</span>
                    </div>
                  ))}
                </div>
              ) : <div className="small-empty">{t("Node này chưa chạy ứng dụng nào.")}</div>}
            </section>
          </aside>
        </div>
      )}
      {selectedUser && (
        <div className="overlay" onMouseDown={() => setSelectedUser(null)}>
          <aside className="drawer infrastructure-drawer" onMouseDown={(event) => event.stopPropagation()}>
            <div className="drawer-head">
              <div>
                <span className="drawer-kicker">{t("USER DETAIL")}</span>
                <h2>{selectedUser.user.name}</h2>
                <p>{selectedUser.user.email}</p>
              </div>
              <button aria-label={t("Đóng")} onClick={() => setSelectedUser(null)}>×</button>
            </div>
            <div className="drawer-status-line"><StatusBadge status={selectedUser.user.status} /><span>{t(selectedUser.user.role)}</span></div>
            <section className="subscription-summary">
              <div><span>{t("Gói hiện tại")}</span><b>{selectedUser.user.subscription?.plan.name ?? t("Chưa có gói")}</b></div>
              <div><span>{t("Trạng thái gói")}</span><StatusBadge status={selectedUser.user.subscription?.status ?? "UNKNOWN"} /></div>
              <div><span>{t("Thời hạn")}</span><b>{subscriptionTimeLabel(selectedUser.user, dateLocale, t)}</b></div>
            </section>
            <section className="user-allocation-summary">
              <div><span>{t("Ứng dụng")}</span><b>{selectedUser.applications.length}</b></div>
              <div><span>CPU</span><b>{formatNumber(selectedUser.applications.reduce((sum, app) => sum + app.resources.cpu, 0), dateLocale)}</b></div>
              <div><span>RAM</span><b>{formatStorage(selectedUser.applications.reduce((sum, app) => sum + app.resources.memory_mb, 0), dateLocale)}</b></div>
              <div><span>Disk</span><b>{formatStorage(selectedUser.applications.reduce((sum, app) => sum + app.resources.disk_mb, 0), dateLocale)}</b></div>
            </section>
            <section className="drawer-workloads">
              <div className="section-title"><h3>{t("Ứng dụng của người dùng")}</h3><span>{t("Phân bổ hiện tại")}</span></div>
              {userDetailLoading ? <div className="small-empty">{t("Đang tải…")}</div> : selectedUser.applications.length ? (
                <div className="workload-list">
                  {selectedUser.applications.map((app) => (
                    <div key={app.id}>
                      <div><b>{app.name}</b><small>{app.node ? `${app.node.name} · ${app.node.code}` : t("Chưa gán node")}</small></div>
                      <StatusBadge status={app.status} />
                      <span>{app.resources.cpu} CPU · {formatStorage(app.resources.memory_mb, dateLocale)} RAM · {formatStorage(app.resources.disk_mb, dateLocale)} Disk</span>
                    </div>
                  ))}
                </div>
              ) : <div className="small-empty">{t("Người dùng này chưa có ứng dụng.")}</div>}
            </section>
          </aside>
        </div>
      )}
      {quotaEditor && (
        <div className="overlay" onMouseDown={() => setQuotaEditor(null)}>
          <aside
            className="drawer"
            onMouseDown={(event) => event.stopPropagation()}
          >
            <div className="drawer-head">
              <div>
                <h2>
                  {t("Quota")} · {quotaEditor.user.name}
                </h2>
                <p>{quotaEditor.user.email}</p>
              </div>
              <button
                aria-label={t("Đóng")}
                onClick={() => setQuotaEditor(null)}
              >
                ×
              </button>
            </div>
            <form onSubmit={saveQuota}>
              <label>
                {t("Số ứng dụng")}
                <input
                  name="max_apps"
                  type="number"
                  min="1"
                  max="20"
                  defaultValue={quotaEditor.quota.max_apps}
                  required
                />
              </label>
              <label>
                {t("RAM mỗi app (MB)")}
                <input
                  name="memory"
                  type="number"
                  min="128"
                  max="8192"
                  defaultValue={quotaEditor.quota.max_memory_mb_per_app}
                  required
                />
              </label>
              <label>
                {t("CPU mỗi app")}
                <input
                  name="cpu"
                  type="number"
                  min="0.1"
                  max="8"
                  step="0.1"
                  defaultValue={quotaEditor.quota.max_cpu_per_app}
                  required
                />
              </label>
              <label>
                {t("Disk mỗi app (MB)")}
                <input
                  name="disk"
                  type="number"
                  min="512"
                  max="51200"
                  defaultValue={quotaEditor.quota.max_disk_mb_per_app}
                  required
                />
              </label>
              <label>
                {t("Build đồng thời")}
                <input
                  name="builds"
                  type="number"
                  min="1"
                  max="5"
                  defaultValue={quotaEditor.quota.max_build_concurrency}
                  required
                />
              </label>
              <div className="drawer-actions">
                <button
                  type="button"
                  className="button secondary"
                  onClick={() => setQuotaEditor(null)}
                >
                  {t("Hủy")}
                </button>
                <button className="button primary">{t("Lưu quota")}</button>
              </div>
            </form>
          </aside>
        </div>
      )}
      {planEditor && (
        <div className="overlay" onMouseDown={() => setPlanEditor(null)}>
          <aside className="drawer plan-editor" onMouseDown={(event) => event.stopPropagation()}>
            <div className="drawer-head">
              <div><span className="drawer-kicker">{t("PLAN SETUP")}</span><h2>{planEditor === "NEW" ? t("Tạo gói dịch vụ") : t("Chỉnh sửa {name}", { name: planEditor.name })}</h2><p>{t("Thay đổi quyền lợi sẽ đồng bộ hạn mức của người đang dùng gói.")}</p></div>
              <button aria-label={t("Đóng")} onClick={() => setPlanEditor(null)}>×</button>
            </div>
            <form onSubmit={savePlan}>
              <div className="form-row">
                <label>{t("Mã gói")}<input name="code" required maxLength={40} pattern="[A-Za-z][A-Za-z0-9_]*" defaultValue={planEditor === "NEW" ? "" : planEditor.code} placeholder="CREATOR_PLUS" /></label>
                <label>{t("Tên hiển thị")}<input name="name" required maxLength={100} defaultValue={planEditor === "NEW" ? "" : planEditor.name} placeholder="Creator Plus" /></label>
              </div>
              <label>{t("Giá mỗi tháng (VND)")}<input name="monthly_price_vnd" required type="number" min="0" max="100000000" step="1000" defaultValue={planEditor === "NEW" ? 0 : planEditor.monthly_price_vnd} /></label>
              <div className="plan-entitlement-grid">
                <label>{t("Số ứng dụng")}<input name="max_apps" required type="number" min="1" max="1000" defaultValue={planEditor === "NEW" ? 1 : planEditor.max_apps} /></label>
                <label>{t("RAM mỗi app (MB)")}<input name="max_memory_mb_per_app" required type="number" min="128" max="131072" step="128" defaultValue={planEditor === "NEW" ? 512 : planEditor.max_memory_mb_per_app} /></label>
                <label>{t("CPU mỗi app")}<input name="max_cpu_per_app" required type="number" min="0.1" max="64" step="0.1" defaultValue={planEditor === "NEW" ? 0.5 : planEditor.max_cpu_per_app} /></label>
                <label>{t("Disk mỗi app (MB)")}<input name="max_disk_mb_per_app" required type="number" min="512" max="1048576" step="512" defaultValue={planEditor === "NEW" ? 2048 : planEditor.max_disk_mb_per_app} /></label>
                <label>{t("Build đồng thời")}<input name="max_build_concurrency" required type="number" min="1" max="100" defaultValue={planEditor === "NEW" ? 1 : planEditor.max_build_concurrency} /></label>
              </div>
              <div className="form-row">
                <label>{t("Trạng thái bán")}<SelectField name="is_published" ariaLabel={t("Trạng thái bán")} defaultValue={planEditor !== "NEW" && planEditor.is_published ? "true" : "false"} options={[{ value: "true", label: t("Đang công bố"), description: t("User có thể chọn mua gói") }, { value: "false", label: t("Đang ẩn"), description: t("Không nhận đăng ký mới") }]} /></label>
                <label>{t("Gói mặc định")}<SelectField name="is_default" ariaLabel={t("Gói mặc định")} defaultValue={planEditor !== "NEW" && planEditor.is_default ? "true" : "false"} options={[{ value: "false", label: t("Không") }, { value: "true", label: t("Có"), description: t("Áp dụng cho tài khoản mới") }]} /></label>
              </div>
              <div className="drawer-actions"><button type="button" className="button secondary" onClick={() => setPlanEditor(null)}>{t("Hủy")}</button><button className="button primary">{t("Lưu gói")}</button></div>
            </form>
          </aside>
        </div>
      )}
      {subscriptionEditor && (
        <div className="overlay" onMouseDown={() => setSubscriptionEditor(null)}>
          <aside className="drawer" onMouseDown={(event) => event.stopPropagation()}>
            <div className="drawer-head">
              <div>
                <span className="drawer-kicker">{t("SUBSCRIPTION")}</span>
                <h2>{t("Quản lý gói")} · {subscriptionEditor.name}</h2>
                <p>{t("Giá trả phí đang ở trạng thái dự kiến; hệ thống chưa tự động thu tiền.")}</p>
              </div>
              <button aria-label={t("Đóng")} onClick={() => setSubscriptionEditor(null)}>×</button>
            </div>
            <form onSubmit={saveSubscription}>
              <label>{t("Gói dịch vụ")}
                <SelectField name="plan_id" ariaLabel={t("Gói dịch vụ")} defaultValue={subscriptionEditor.subscription?.plan.id ?? plans[0]?.id ?? ""} options={plans.map((plan) => ({ value: plan.id, label: plan.name, description: formatPlanPrice(plan, dateLocale, t) }))} />
              </label>
              <label>{t("Trạng thái gói")}
                <SelectField name="status" ariaLabel={t("Trạng thái gói")} defaultValue={subscriptionEditor.subscription?.status ?? "TRIALING"} options={['TRIALING', 'ACTIVE', 'PAST_DUE', 'EXPIRED', 'CANCELED'].map((status) => ({ value: status, label: t(status) }))} />
              </label>
              <label>{t("Gia hạn")}
                <SelectField name="duration_months" ariaLabel={t("Gia hạn")} defaultValue="1" options={[1, 3, 6, 12].map((months) => ({ value: String(months), label: t("{count} tháng", { count: months }) }))} />
              </label>
              <div className="subscription-policy-note">
                <b>{t("Chính sách hết hạn")}</b>
                <span>{t("Nhắc mail trước 7, 3, 1 ngày; grace 3 ngày; không tự xóa ứng dụng.")}</span>
              </div>
              <div className="drawer-actions">
                <button type="button" className="button secondary" onClick={() => setSubscriptionEditor(null)}>{t("Hủy")}</button>
                <button className="button primary">{t("Lưu gói")}</button>
              </div>
            </form>
          </aside>
        </div>
      )}
      {showCreateNode && (
        <div className="overlay" onMouseDown={() => setShowCreateNode(false)}>
          <aside className="drawer" onMouseDown={(event) => event.stopPropagation()}>
            <div className="drawer-head">
              <div>
                <span className="drawer-kicker">{t("INFRASTRUCTURE")}</span>
                <h2>{t("Thêm deployment node")}</h2>
                <p>{t("Node mới bắt đầu ở trạng thái ACTIVE.")}</p>
              </div>
              <button aria-label={t("Đóng")} onClick={() => setShowCreateNode(false)}>×</button>
            </div>
            <form onSubmit={createNode}>
              <div className="form-row">
                <label>{t("Tên node")}<input name="name" placeholder="VPS Singapore" required /></label>
                <label>{t("Mã node")}<input name="code" placeholder="VPS-SG-01" pattern="[A-Za-z0-9-]+" required /></label>
              </div>
              <div className="form-row">
                <label>{t("Provider")}<SelectField name="provider" ariaLabel={t("Provider")} defaultValue="DOKPLOY" options={[{ value: "DOKPLOY", label: "Dokploy", description: t("Hạ tầng production") }, { value: "FAKE", label: "Fake / Local", description: t("Mô phỏng để kiểm thử") }]} /></label>
                <label>{t("Khu vực")}<input name="region" placeholder="sg" /></label>
              </div>
              <label>{t("Dokploy server ID")}<input name="provider_server_id" type="password" autoComplete="off" /></label>
              <label>{t("Host nội bộ")}<input name="host" type="password" autoComplete="off" /></label>
              <div className="form-row node-capacity-fields">
                <label>CPU<input name="cpu_total" type="number" min="0.1" step="0.1" defaultValue="8" required /></label>
                <label>RAM (MB)<input name="memory_total_mb" type="number" min="128" defaultValue="16384" required /></label>
                <label>Disk (MB)<input name="disk_total_mb" type="number" min="512" defaultValue="102400" required /></label>
              </div>
              <div className="drawer-actions">
                <button type="button" className="button secondary" onClick={() => setShowCreateNode(false)}>{t("Hủy")}</button>
                <button className="button primary">{t("Tạo node")}</button>
              </div>
            </form>
          </aside>
        </div>
      )}
    </div>
  );
}

type Translate = (source: string, variables?: Record<string, string | number>) => string;
type SummaryTone = "neutral" | "success" | "info" | "danger";

function subscriptionTimeLabel(user: User, locale: string, t: Translate): string {
  const subscription = user.subscription;
  if (!subscription?.ends_at) return t("Không giới hạn");
  const end = new Date(subscription.ends_at);
  const days = Math.ceil((end.getTime() - Date.now()) / 86400000);
  if (subscription.status === "EXPIRED" || days < 0) return t("Đã hết hạn {date}", { date: end.toLocaleDateString(locale) });
  return t("Còn {count} ngày · đến {date}", { count: days, date: end.toLocaleDateString(locale) });
}

function formatPlanPrice(plan: Plan, locale: string, t: Translate): string {
  if (plan.monthly_price_vnd === 0) return t("Miễn phí");
  const price = new Intl.NumberFormat(locale, { style: "currency", currency: "VND", maximumFractionDigits: 0 }).format(plan.monthly_price_vnd);
  return plan.is_published ? t("{price}/tháng", { price }) : t("Dự kiến {price}/tháng", { price });
}

function SummaryCard({ label, value, tone }: { label: string; value: number; tone: SummaryTone }) {
  return <div className={`node-summary-card ${tone}`}><span>{label}</span><b>{value}</b></div>;
}

function MiniCapacity({ label, reserved, total, unit, locale }: { label: string; reserved: number; total: number; unit: string; locale: string }) {
  const percent = capacityPercent(reserved, total);
  return (
    <div className="mini-capacity">
      <span>{label}</span>
      <i><em className={percent >= 80 ? "pressure" : ""} style={{ width: `${percent}%` }} /></i>
      <b>{formatCapacity(reserved, total, unit, locale)}</b>
    </div>
  );
}

function CapacityDetail({ label, reserved, total, unit, locale, t }: { label: string; reserved: number; total: number; unit: string; locale: string; t: Translate }) {
  const percent = capacityPercent(reserved, total);
  const available = Math.max(0, total - reserved);
  return (
    <div className="capacity-detail">
      <div><b>{label}</b><span>{formatCapacity(reserved, total, unit, locale)} · {percent}%</span></div>
      <i><em className={percent >= 80 ? "pressure" : ""} style={{ width: `${percent}%` }} /></i>
      <small>{t("Còn trống {value} {unit}", { value: new Intl.NumberFormat(locale, { maximumFractionDigits: 1 }).format(available), unit })}</small>
    </div>
  );
}

function capacityPercent(reserved: number, total: number): number {
  return total > 0 ? Math.min(100, Math.round((reserved / total) * 100)) : 100;
}

function nodeHasPressure(node: NodeRecord): boolean {
  const reservedPressure = [
    capacityPercent(node.capacity.cpu.reserved, node.capacity.cpu.total),
    capacityPercent(node.capacity.memory_mb.reserved, node.capacity.memory_mb.total),
    capacityPercent(node.capacity.disk_mb.reserved, node.capacity.disk_mb.total),
  ].some((percent) => percent >= 80);
  const runtimePressure = [
    node.capacity.cpu.usage_percent,
    node.capacity.memory_mb.usage === null || node.capacity.memory_mb.total <= 0 ? null : (node.capacity.memory_mb.usage / node.capacity.memory_mb.total) * 100,
    node.capacity.disk_mb.usage === null || node.capacity.disk_mb.total <= 0 ? null : (node.capacity.disk_mb.usage / node.capacity.disk_mb.total) * 100,
  ].some((percent) => percent !== null && percent >= 75);
  return reservedPressure || runtimePressure;
}

function heartbeatIsStale(value: string | null): boolean {
  return value !== null && Date.now() - new Date(value).getTime() > 5 * 60 * 1000;
}

function isNodeReady(node: NodeRecord): boolean {
  return node.status === "ACTIVE" && !nodeHasPressure(node);
}

function nodeNeedsAttention(node: NodeRecord): boolean {
  return !isNodeReady(node) || heartbeatIsStale(node.last_heartbeat_at);
}

function nodeHealthLabel(node: NodeRecord, t: Translate): string {
  if (node.status !== "ACTIVE") return t("Không nhận app mới");
  if (nodeHasPressure(node)) return t("Tài nguyên gần đầy");
  if (heartbeatIsStale(node.last_heartbeat_at)) return t("Heartbeat bị trễ");
  return node.applications_count === 0 ? t("Sẵn sàng · đang trống") : t("Đang vận hành tốt");
}

function formatRelativeHeartbeat(value: string | null, t: Translate): string {
  if (!value) return t("Chưa có dữ liệu");
  const seconds = Math.max(0, Math.floor((Date.now() - new Date(value).getTime()) / 1000));
  if (seconds < 60) return t("Vừa cập nhật");
  if (seconds < 3600) return t("{count} phút trước", { count: Math.floor(seconds / 60) });
  return t("{count} giờ trước", { count: Math.floor(seconds / 3600) });
}

function formatStorage(valueMb: number, locale: string): string {
  if (valueMb >= 1024) return `${formatNumber(valueMb / 1024, locale)} GB`;
  return `${formatNumber(valueMb, locale)} MB`;
}

function ProductSignal({ label, value }: { label: string; value: string }) {
  return (
    <div>
      <span />
      <div>
        <b>{label}</b>
      </div>
      <strong>{value}</strong>
    </div>
  );
}
function ActionMenu({ label, children }: { label: string; children: ReactNode }) {
  const [open, setOpen] = useState(false);
  const [position, setPosition] = useState({ top: 0, right: 0 });
  const trigger = useRef<HTMLButtonElement>(null);

  useEffect(() => {
    if (!open) return;
    const close = (event: KeyboardEvent) => {
      if (event.key === "Escape") setOpen(false);
    };
    const closeOnResize = () => setOpen(false);
    window.addEventListener("keydown", close);
    window.addEventListener("resize", closeOnResize);
    return () => {
      window.removeEventListener("keydown", close);
      window.removeEventListener("resize", closeOnResize);
    };
  }, [open]);

  const toggle = () => {
    if (!open && trigger.current) {
      const rect = trigger.current.getBoundingClientRect();
      setPosition({
        top: Math.min(rect.bottom + 7, window.innerHeight - 180),
        right: Math.max(14, window.innerWidth - rect.right),
      });
    }
    setOpen((current) => !current);
  };

  return (
    <div className="action-menu">
      <button
        ref={trigger}
        type="button"
        className="action-menu-trigger"
        aria-label={label}
        aria-haspopup="menu"
        aria-expanded={open}
        title={label}
        onClick={(event) => { event.stopPropagation(); toggle(); }}
      >
        <span /><span /><span />
      </button>
      {open && createPortal(
        <>
          <button
            type="button"
            className="action-menu-backdrop"
            aria-label={label}
            onClick={() => setOpen(false)}
          />
          <div
            className="action-menu-popover"
            role="menu"
            style={{ top: position.top, right: position.right }}
            onClick={() => setOpen(false)}
          >
            {children}
          </div>
        </>,
        document.body,
      )}
    </div>
  );
}
function Metric({ label, value }: { label: string; value: string }) {
  return (
    <div>
      <span>{label}</span>
      <b>{value}</b>
    </div>
  );
}
function formatNumber(value: number | null, locale: string): string {
  return value === null
    ? "—"
    : new Intl.NumberFormat(locale, { maximumFractionDigits: 1 }).format(value);
}
function formatCapacity(value: number, total: number, unit: string, locale: string): string {
  const formatter = new Intl.NumberFormat(locale, { maximumFractionDigits: 1 });
  return `${formatter.format(value)} / ${formatter.format(total)} ${unit}`;
}
function formatDisk(value: number, locale: string): string {
  const formatter = new Intl.NumberFormat(locale, { maximumFractionDigits: 1 });
  return value >= 1024 ? `${formatter.format(value / 1024)} GB` : `${formatter.format(value)} MB`;
}
function formatUptime(
  value: number | null,
  t: (source: string, variables?: Record<string, string | number>) => string,
): string {
  if (value === null) return "—";
  const days = Math.floor(value / 86400);
  const hours = Math.floor((value % 86400) / 3600);
  return t("{days} ngày {hours} giờ", { days, hours });
}
function formatDuration(
  value: number,
  t: (source: string, variables?: Record<string, string | number>) => string,
): string {
  if (value < 60) return t("{value} giây", { value });
  const minutes = Math.floor(value / 60);
  const seconds = value % 60;
  return seconds
    ? t("{minutes} phút {seconds} giây", { minutes, seconds })
    : t("{minutes} phút", { minutes });
}
