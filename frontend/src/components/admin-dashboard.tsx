"use client";

import Link from "next/link";
import { FormEvent, useCallback, useEffect, useState } from "react";
import { api, ApiError, Application, NodeRecord, Quota, User } from "@/lib/api";
import { LanguageSwitcher } from "./language-switcher";
import { StatusBadge } from "./status-badge";
import { useI18n } from "@/lib/i18n";
import { useSectionNavigation } from "./use-section-navigation";

const ADMIN_SECTIONS = [
  "admin-overview",
  "admin-nodes",
  "admin-users",
  "admin-applications",
  "admin-build-queue",
] as const;

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

export function AdminDashboard() {
  const { t, href, dateLocale } = useI18n();
  const [overview, setOverview] = useState<Overview | null>(null);
  const [users, setUsers] = useState<User[]>([]);
  const [apps, setApps] = useState<Application[]>([]);
  const [queue, setQueue] = useState<QueueItem[]>([]);
  const [nodes, setNodes] = useState<NodeRecord[]>([]);
  const [showCreateNode, setShowCreateNode] = useState(false);
  const [error, setError] = useState("");
  const [search, setSearch] = useState("");
  const [submittedSearch, setSubmittedSearch] = useState("");
  const [mobileNavOpen, setMobileNavOpen] = useState(false);
  const { activeSection, navigateToSection } =
    useSectionNavigation(ADMIN_SECTIONS);
  const [quotaEditor, setQuotaEditor] = useState<{
    user: User;
    quota: Quota;
  } | null>(null);
  const token =
    typeof window === "undefined" ? null : localStorage.getItem("vive_token");

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
      const [stats, userList, appList, queueList, nodeList] = await Promise.all([
        api<Overview>("/admin/system/overview", {}, token),
        api<User[]>(`/admin/users${query}`, {}, token),
        api<Application[]>(`/admin/apps${query}`, {}, token),
        api<QueueItem[]>("/admin/system/build-queue", {}, token),
        api<NodeRecord[]>("/admin/nodes", {}, token),
      ]);
      setOverview(stats);
      setUsers(userList);
      setApps(appList);
      setQueue(queueList);
      setNodes(nodeList);
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
      if (detail.user.quota) setQuotaEditor({ user, quota: detail.user.quota });
    } catch (caught) {
      setError(
        caught instanceof ApiError
          ? t(caught.message)
          : t("Không thể tải quota."),
      );
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
            {t("Hạ tầng → Nodes")} <em>{nodes.length}</em>
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
          <small>{t("Control plane")}</small>
          <p>{t("Quản trị hệ thống")}</p>
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
          <div id="admin-overview" className="page-header dashboard-section-anchor">
            <div>
              <h1>{t("Quản trị hệ thống")}</h1>
              <p>
                {t("Người dùng, ứng dụng và trạng thái vận hành tập trung.")}
              </p>
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
          {overview && (
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
          <section id="admin-nodes" className="section admin-section dashboard-section-anchor node-section">
            <div className="section-title">
              <div>
                <h2>{t("Infrastructure → Nodes")}</h2>
                <span>{t("Phân bổ ứng dụng theo năng lực dự phòng của node")}</span>
              </div>
              <button className="button secondary small" onClick={() => setShowCreateNode(true)}>
                {t("+ Thêm node")}
              </button>
            </div>
            <div className="table-wrap">
              <table className="node-table">
                <thead>
                  <tr>
                    <th>{t("Tên node")}</th>
                    <th>{t("Trạng thái")}</th>
                    <th>{t("Apps")}</th>
                    <th>CPU</th>
                    <th>RAM</th>
                    <th>Disk</th>
                    <th>{t("Heartbeat cuối")}</th>
                    <th />
                  </tr>
                </thead>
                <tbody>
                  {nodes.map((node) => (
                    <tr key={node.id}>
                      <td>
                        <b>{node.name}</b>
                        <small>{node.code} · {node.region ?? t("Không xác định")}</small>
                      </td>
                      <td><StatusBadge status={node.status} /></td>
                      <td>{node.applications_count}</td>
                      <td>{formatCapacity(node.capacity.cpu.reserved, node.capacity.cpu.total, "CPU", dateLocale)}</td>
                      <td>{formatCapacity(node.capacity.memory_mb.reserved, node.capacity.memory_mb.total, "MB", dateLocale)}</td>
                      <td>{formatCapacity(node.capacity.disk_mb.reserved, node.capacity.disk_mb.total, "MB", dateLocale)}</td>
                      <td>{node.last_heartbeat_at ? new Date(node.last_heartbeat_at).toLocaleString(dateLocale) : "—"}</td>
                      <td>
                        <div className="row-actions node-actions">
                          {node.status !== "ACTIVE" && <button onClick={() => changeNodeStatus(node, "activate")}>{t("Kích hoạt")}</button>}
                          {node.status === "ACTIVE" && <button onClick={() => changeNodeStatus(node, "drain")}>{t("Drain")}</button>}
                          <button onClick={() => changeNodeStatus(node, "maintenance")}>{t("Bảo trì")}</button>
                          <button className="danger" onClick={() => changeNodeStatus(node, "disable")}>{t("Vô hiệu hóa")}</button>
                        </div>
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          </section>
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
          <section id="admin-users" className="section admin-section dashboard-section-anchor">
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
                    <th>{t("Apps")}</th>
                    <th>{t("RAM cấp phát")}</th>
                    <th>{t("Tham gia")}</th>
                    <th />
                  </tr>
                </thead>
                <tbody>
                  {users.map((user) => (
                    <tr key={user.id}>
                      <td>
                        <b>{user.name}</b>
                        <small>
                          {user.email} · {t(user.role)}
                        </small>
                      </td>
                      <td>
                        <StatusBadge status={user.status} />
                      </td>
                      <td>{user.applications_count ?? 0}</td>
                      <td>{user.applications_memory_limit_mb ?? 0} MB</td>
                      <td>
                        {user.created_at
                          ? new Date(user.created_at).toLocaleDateString(
                              dateLocale,
                            )
                          : "—"}
                      </td>
                      <td>
                        <div className="row-actions">
                          <button onClick={() => editQuota(user)}>
                            {t("Quota")}
                          </button>
                          <button onClick={() => changeStatus(user)}>
                            {user.status === "SUSPENDED"
                              ? t("Kích hoạt")
                              : t("Tạm ngưng")}
                          </button>
                        </div>
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          </section>
          <section id="admin-applications" className="section admin-section dashboard-section-anchor">
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
                      <td>{app.owner?.email ?? "—"}</td>
                      <td>
                        <StatusBadge status={app.status} />
                      </td>
                      <td>{app.domain ?? t("Đang cấp phát")}</td>
                      <td>
                        {app.resources.memory_mb} MB · {app.resources.cpu} CPU
                      </td>
                      <td>
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
                        <div className="row-actions">
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
                        </div>
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          </section>
          <section id="admin-build-queue" className="section admin-section dashboard-section-anchor">
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
        </div>
      </main>
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
                <label>{t("Provider")}<select name="provider" defaultValue="DOKPLOY"><option value="DOKPLOY">Dokploy</option><option value="FAKE">Fake / Local</option></select></label>
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
