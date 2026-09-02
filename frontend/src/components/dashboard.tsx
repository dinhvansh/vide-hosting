"use client";

import Link from "next/link";
import { FormEvent, useCallback, useEffect, useMemo, useRef, useState } from "react";
import { api, ApiError, Application, BillingCatalog, CheckoutResult, PaymentOrder, User } from "@/lib/api";
import { clearAuthToken, getAuthToken, storeAuthToken } from "@/lib/auth-token";
import { LanguageSwitcher } from "./language-switcher";
import { SelectField } from "./select-field";
import { StatusBadge } from "./status-badge";
import { useI18n } from "@/lib/i18n";
import { useSectionNavigation } from "./use-section-navigation";

const DASHBOARD_SECTIONS = [
  "dashboard-overview",
  "dashboard-usage",
  "dashboard-billing",
  "dashboard-applications",
  "dashboard-deployments",
] as const;

type AuthResult = { user: User; token: string };

function formatSubscriptionTerm(user: User, locale: string, t: (source: string, variables?: Record<string, string | number>) => string): string {
  if (!user.subscription?.ends_at) return t("Không giới hạn");
  const end = new Date(user.subscription.ends_at);
  const days = Math.ceil((end.getTime() - Date.now()) / 86400000);
  if (days < 0 || user.subscription.status === "EXPIRED") return t("Đã hết hạn {date}", { date: end.toLocaleDateString(locale) });
  return t("Còn {count} ngày · đến {date}", { count: days, date: end.toLocaleDateString(locale) });
}

export function Dashboard({
  initialRegister = false,
}: {
  initialRegister?: boolean;
}) {
  const { t, href, dateLocale } = useI18n();
  const [token, setToken] = useState<string | null>(null);
  const [user, setUser] = useState<User | null>(null);
  const [apps, setApps] = useState<Application[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState("");
  const [notice, setNotice] = useState("");
  const [showCreate, setShowCreate] = useState(false);
  const [selected, setSelected] = useState<Application | null>(null);
  const [mobileNavOpen, setMobileNavOpen] = useState(false);
  const [billing, setBilling] = useState<BillingCatalog | null>(null);
  const [showBilling, setShowBilling] = useState(false);
  const [billingLoading, setBillingLoading] = useState(false);
  const paymentReturnHandled = useRef(false);
  const { activeSection, navigateToSection } =
    useSectionNavigation(DASHBOARD_SECTIONS);

  const loadApps = useCallback(async (authToken: string) => {
    const [profile, applications, catalog] = await Promise.all([
      api<User>("/me", {}, authToken),
      api<Application[]>("/apps", {}, authToken),
      api<BillingCatalog>("/billing/catalog", {}, authToken),
    ]);
    setUser(profile);
    setApps(applications);
    setBilling(catalog);
    setSelected((current) =>
      current
        ? (applications.find((application) => application.id === current.id) ??
          null)
        : null,
    );
  }, []);

  useEffect(() => {
    Promise.resolve().then(async () => {
      const saved = getAuthToken();
      if (!saved) {
        setLoading(false);
        return;
      }
      setToken(saved);
      try {
        await loadApps(saved);
      } catch {
        clearAuthToken();
        setToken(null);
      } finally {
        setLoading(false);
      }
    });
  }, [loadApps]);
  useEffect(() => {
    if (!token) return;
    const timer = window.setInterval(() => {
      loadApps(token).catch(() => undefined);
    }, 10000);
    return () => window.clearInterval(timer);
  }, [loadApps, token]);
  useEffect(() => {
    if (!token || paymentReturnHandled.current) return;
    const orderId = new URLSearchParams(window.location.search).get("payment_order");
    if (!orderId) return;
    paymentReturnHandled.current = true;
    api<PaymentOrder>(`/billing/orders/${orderId}/reconcile`, { method: "POST", body: "{}" }, token)
      .then(async (order) => {
        setNotice(order.status === "APPROVED" ? t("Thanh toán thành công. Quyền lợi đã được cập nhật.") : t("Đang chờ SePay xác nhận thanh toán."));
        await loadApps(token);
      })
      .catch(() => setError(t("Không thể kiểm tra trạng thái thanh toán.")))
      .finally(() => window.history.replaceState({}, "", window.location.pathname));
  }, [loadApps, t, token]);

  const authenticate = async (
    event: FormEvent<HTMLFormElement>,
    register: boolean,
  ) => {
    event.preventDefault();
    setError("");
    setLoading(true);
    const form = new FormData(event.currentTarget);
    const password = String(form.get("password"));
    try {
      const result = await api<AuthResult>(
        register ? "/auth/register" : "/auth/login",
        {
          method: "POST",
          body: JSON.stringify({
            name: form.get("name"),
            email: form.get("email"),
            password,
            password_confirmation: password,
          }),
        },
      );
      storeAuthToken(
        result.token,
        register || form.get("remember_login") === "on",
      );
      setToken(result.token);
      await loadApps(result.token);
    } catch (caught) {
      setError(
        caught instanceof ApiError
          ? t(caught.message)
          : t("Không thể kết nối API."),
      );
    } finally {
      setLoading(false);
    }
  };

  const createApp = async (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault();
    if (!token) return;
    setError("");
    setLoading(true);
    const form = new FormData(event.currentTarget);
    try {
      const app = await api<Application>(
        "/apps",
        {
          method: "POST",
          body: JSON.stringify({
            name: form.get("name"),
            repository_url: form.get("repository_url"),
            branch: form.get("branch"),
            framework: form.get("framework"),
          }),
        },
        token,
      );
      setApps([app]);
      setShowCreate(false);
      setSelected(app);
      try {
        await api(
          `/apps/${app.id}/deployments`,
          {
            method: "POST",
            headers: { "Idempotency-Key": crypto.randomUUID() },
            body: "{}",
          },
          token,
        );
        await loadApps(token);
      } catch (caught) {
        setError(
          caught instanceof ApiError
            ? t("Ứng dụng đã được tạo nhưng chưa thể deploy: {message}", {
                message: caught.message,
              })
            : t("Ứng dụng đã được tạo nhưng chưa thể deploy."),
        );
      }
    } catch (caught) {
      setError(
        caught instanceof ApiError
          ? t(caught.message)
          : t("Không thể tạo ứng dụng."),
      );
    } finally {
      setLoading(false);
    }
  };

  const checkout = async (event: FormEvent<HTMLFormElement>, type: "PLAN" | "APP_SLOT") => {
    event.preventDefault();
    if (!token || !billing?.payment_available) return;
    setBillingLoading(true);
    setError("");
    const data = new FormData(event.currentTarget);
    try {
      const result = await api<CheckoutResult>("/billing/orders", {
        method: "POST",
        body: JSON.stringify(type === "PLAN" ? {
          type,
          plan_id: data.get("plan_id"),
          duration_months: Number(data.get("duration_months")),
        } : { type, quantity: Number(data.get("quantity")) }),
      }, token);
      const form = document.createElement("form");
      form.method = "POST";
      form.action = result.checkout.url;
      Object.entries(result.checkout.fields).forEach(([name, value]) => {
        const input = document.createElement("input");
        input.type = "hidden"; input.name = name; input.value = value; form.appendChild(input);
      });
      document.body.appendChild(form);
      form.submit();
    } catch (caught) {
      setError(caught instanceof ApiError ? t(caught.message) : t("Không thể tạo đơn thanh toán."));
    } finally {
      setBillingLoading(false);
    }
  };

  const deploy = async (app: Application) => {
    if (!token) return;
    setError("");
    try {
      await api(
        `/apps/${app.id}/deployments`,
        {
          method: "POST",
          headers: { "Idempotency-Key": crypto.randomUUID() },
          body: "{}",
        },
        token,
      );
      await loadApps(token);
    } catch (caught) {
      setError(
        caught instanceof ApiError ? t(caught.message) : t("Không thể deploy."),
      );
    }
  };
  const restart = async (app: Application) => {
    if (!token) return;
    try {
      await api(
        `/apps/${app.id}/restart`,
        { method: "POST", body: "{}" },
        token,
      );
      await loadApps(token);
    } catch (caught) {
      setError(
        caught instanceof ApiError
          ? t(caught.message)
          : t("Không thể restart."),
      );
    }
  };
  const stats = useMemo(
    () => ({
      total: apps.length,
      running: apps.filter((item) => item.status === "RUNNING").length,
      failed: apps.filter((item) => item.status === "FAILED").length,
      queued: apps.filter((item) => item.latest_deployment?.status === "QUEUED")
        .length,
    }),
    [apps],
  );

  if (loading && !token)
    return <div className="center-loader">{t("Đang tải Vive Host…")}</div>;
  if (!token || !user)
    return (
      <AuthScreen
        onSubmit={authenticate}
        error={error}
        loading={loading}
        initialRegister={initialRegister}
      />
    );

  return (
    <div className="shell">
      <aside className={`sidebar${mobileNavOpen ? " mobile-open" : ""}`}>
        <Link
          className="brand"
          href={href("/")}
          onClick={() => setMobileNavOpen(false)}
        >
          <span>V</span> Vive Host
        </Link>
        <nav aria-label={t("Điều hướng dashboard")}>
          <a
            href="#dashboard-overview"
            className={activeSection === "dashboard-overview" ? "active" : ""}
            aria-current={activeSection === "dashboard-overview" ? "location" : undefined}
            onClick={(event) => {
              event.preventDefault();
              navigateToSection("dashboard-overview");
              setMobileNavOpen(false);
            }}
          >
            {t("Tổng quan")}
          </a>
          <a
            href="#dashboard-applications"
            className={activeSection === "dashboard-applications" ? "active" : ""}
            aria-current={activeSection === "dashboard-applications" ? "location" : undefined}
            onClick={(event) => {
              event.preventDefault();
              navigateToSection("dashboard-applications");
              setMobileNavOpen(false);
            }}
          >
            {t("Ứng dụng")} <em>{apps.length}</em>
          </a>
          <a
            href="#dashboard-deployments"
            className={activeSection === "dashboard-deployments" ? "active" : ""}
            aria-current={activeSection === "dashboard-deployments" ? "location" : undefined}
            onClick={(event) => {
              event.preventDefault();
              navigateToSection("dashboard-deployments");
              setMobileNavOpen(false);
            }}
          >
            {t("Deployments")}
          </a>
          <a
            href="#dashboard-usage"
            className={activeSection === "dashboard-usage" ? "active" : ""}
            aria-current={activeSection === "dashboard-usage" ? "location" : undefined}
            onClick={(event) => {
              event.preventDefault();
              navigateToSection("dashboard-usage");
              setMobileNavOpen(false);
            }}
          >
            {t("Usage")}
          </a>
          <a
            href="#dashboard-billing"
            className={activeSection === "dashboard-billing" ? "active" : ""}
            onClick={(event) => {
              event.preventDefault();
              navigateToSection("dashboard-billing");
              setMobileNavOpen(false);
            }}
          >
            {t("Gói & thanh toán")}
          </a>
          <Link href={href("/account")} onClick={() => setMobileNavOpen(false)}>
            {t("Tài khoản & MCP")}
          </Link>
          {["ADMIN", "SUPER_ADMIN"].includes(user.role) && (
            <Link href={href("/admin")} onClick={() => setMobileNavOpen(false)}>
              {t("Quản trị hệ thống")}
            </Link>
          )}
        </nav>
        <div className="sidebar-bottom">
          <small>{t("Open Beta")}</small>
          <p>{user.name}</p>
          <span>{user.email}</span>
          <button
            onClick={() => {
              clearAuthToken();
              setToken(null);
              setUser(null);
              setMobileNavOpen(false);
            }}
          >
            {t("Đăng xuất")}
          </button>
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
          <span>{t("Workspace cá nhân")}</span>
          <div className="topbar-actions">
            <LanguageSwitcher compact />
            <StatusBadge status={user.status} />
          </div>
        </header>
        <div className="content">
          <div id="dashboard-overview" className="page-header dashboard-section-anchor">
            <div>
              <h1>{t("Tổng quan")}</h1>
              <p>{t("Quản lý ứng dụng và các lần triển khai gần đây.")}</p>
            </div>
            <button
              className="button primary"
              onClick={() => setShowCreate(true)}
            >
              {t("+ Ứng dụng mới")}
            </button>
          </div>
          {error && (
            <div className="alert">
              <b>{t("Cần xử lý")}</b>
              <span>{error}</span>
              <button onClick={() => setError("")}>×</button>
            </div>
          )}
          {notice && <div className="alert success-alert"><b>{t("Thanh toán")}</b><span>{notice}</span><button onClick={() => setNotice("")}>×</button></div>}
          <section id="dashboard-billing" className="customer-plan-card dashboard-section-anchor">
            <div>
              <span>{t("Gói hiện tại")}</span>
              <b>{user.subscription?.plan.name ?? t("Open Beta")}</b>
              <small>{t(user.subscription?.status ?? "TRIALING")}</small>
            </div>
            <div>
              <span>{t("Thời hạn gói")}</span>
              <b>{formatSubscriptionTerm(user, dateLocale, t)}</b>
              <small>{t("Không tự động gia hạn hoặc trừ tiền")}</small>
            </div>
            <div>
              <span>{t("Hạn mức")}</span>
              <b>{t("{apps} app · {ram} MB RAM · {cpu} CPU", { apps: user.quota?.max_apps ?? 1, ram: user.quota?.max_memory_mb_per_app ?? 512, cpu: user.quota?.max_cpu_per_app ?? 0.5 })}</b>
              <small>{t("{count} slot mua thêm", { count: user.subscription?.extra_app_slots ?? 0 })}</small>
              <button className="button primary plan-action" onClick={() => setShowBilling(true)}>
                {t("Gia hạn / mua thêm app")}
              </button>
            </div>
          </section>
          <section id="dashboard-usage" className="stat-strip dashboard-section-anchor">
            <div>
              <span>{t("Ứng dụng")}</span>
              <b>{stats.total}</b>
            </div>
            <div>
              <span>{t("Đang chạy")}</span>
              <b className="healthy">{stats.running}</b>
            </div>
            <div>
              <span>{t("Thất bại")}</span>
              <b className="danger">{stats.failed}</b>
            </div>
            <div>
              <span>{t("Đang chờ build")}</span>
              <b>{stats.queued}</b>
            </div>
          </section>
          <section id="dashboard-applications" className="section dashboard-section-anchor">
            <div className="section-title">
              <h2>{t("Ứng dụng gần đây")}</h2>
              <span>{t("Giới hạn: {count} ứng dụng", { count: user.quota?.max_apps ?? 1 })}</span>
            </div>
            {apps.length === 0 ? (
              <div className="empty">
                <h3>{t("Chưa có ứng dụng")}</h3>
                <p>{t("Deploy một GitHub repository để bắt đầu.")}</p>
                <button
                  className="button primary"
                  onClick={() => setShowCreate(true)}
                >
                  {t("Ứng dụng mới")}
                </button>
              </div>
            ) : (
              <div className="table-wrap">
                <table>
                  <thead>
                    <tr>
                      <th>{t("Ứng dụng")}</th>
                      <th>{t("Trạng thái")}</th>
                      <th>{t("Domain")}</th>
                      <th>{t("Deploy cuối")}</th>
                      <th>{t("Tài nguyên")}</th>
                      <th />
                    </tr>
                  </thead>
                  <tbody>
                    {apps.map((app) => (
                      <tr key={app.id} onClick={() => setSelected(app)}>
                        <td>
                          <Link
                            href={href(`/dashboard/apps/${app.id}`)}
                            onClick={(event) => event.stopPropagation()}
                          >
                            <b>{app.name}</b>
                            <small>
                              {app.repository_url.replace(
                                "https://github.com/",
                                "",
                              )}
                            </small>
                          </Link>
                        </td>
                        <td>
                          <StatusBadge status={app.status} />
                        </td>
                        <td>{app.domain ?? t("Đang cấp phát")}</td>
                        <td>
                          {app.latest_deployment ? (
                            <>
                              <StatusBadge
                                status={app.latest_deployment.status}
                              />
                              <small>{app.latest_deployment.branch}</small>
                            </>
                          ) : (
                            t("Chưa deploy")
                          )}
                        </td>
                        <td>
                          <span className="usage">
                            {app.resources.memory_mb} MB · {app.resources.cpu}{" "}
                            CPU
                          </span>
                        </td>
                        <td>
                          <button
                            className="button secondary small"
                            onClick={(event) => {
                              event.stopPropagation();
                              deploy(app);
                            }}
                          >
                            {t("Deploy")}
                          </button>
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            )}
          </section>
          <section id="dashboard-deployments" className="section dashboard-activity dashboard-section-anchor">
            <div className="section-title">
              <h2>{t("Hoạt động deployment gần đây")}</h2>
              <span>{t("Cập nhật tự động")}</span>
            </div>
            {apps.some((app) => app.latest_deployment) ? (
              <div className="compact-list">
                {apps
                  .filter((app) => app.latest_deployment)
                  .map((app) => (
                    <Link
                      href={href(`/dashboard/apps/${app.id}`)}
                      key={app.latest_deployment!.id}
                    >
                      <StatusBadge status={app.latest_deployment!.status} />
                      <div>
                        <b>{app.name}</b>
                        <small>
                          {t("Branch {branch}", {
                            branch: app.latest_deployment!.branch,
                          })}{" "}
                          ·{" "}
                          {new Date(
                            app.latest_deployment!.created_at,
                          ).toLocaleString(dateLocale)}
                        </small>
                      </div>
                      <span>{t("Xem chi tiết")}</span>
                    </Link>
                  ))}
              </div>
            ) : (
              <div className="small-empty">
                {t("Chưa có hoạt động deployment.")}
              </div>
            )}
          </section>
        </div>
      </main>
      {showCreate && (
        <CreateDrawer
          onClose={() => setShowCreate(false)}
          onSubmit={createApp}
          loading={loading}
        />
      )}
      {showBilling && billing && (
        <BillingDrawer
          catalog={billing}
          currentPlanId={user.subscription?.plan.id}
          loading={billingLoading}
          onClose={() => setShowBilling(false)}
          onCheckout={checkout}
        />
      )}
      {selected && token && (
        <AppDrawer
          app={selected}
          token={token}
          onClose={() => setSelected(null)}
          onDeploy={() => deploy(selected)}
          onRestart={() => restart(selected)}
        />
      )}
    </div>
  );
}

function BillingDrawer({ catalog, currentPlanId, loading, onClose, onCheckout }: {
  catalog: BillingCatalog;
  currentPlanId?: string;
  loading: boolean;
  onClose: () => void;
  onCheckout: (event: FormEvent<HTMLFormElement>, type: "PLAN" | "APP_SLOT") => void;
}) {
  const { t, dateLocale } = useI18n();
  const money = (value: number) => new Intl.NumberFormat(dateLocale, { style: "currency", currency: "VND", maximumFractionDigits: 0 }).format(value);
  const defaultPlan = catalog.plans.find((plan) => plan.id === currentPlanId) ?? catalog.plans[0];
  return (
    <div className="overlay" onMouseDown={onClose}>
      <aside className="drawer billing-drawer" onMouseDown={(event) => event.stopPropagation()}>
        <div className="drawer-head">
          <div><span className="drawer-kicker">{t("BILLING")}</span><h2>{t("Gói & thanh toán")}</h2><p>{t("Tự gia hạn gói hoặc tăng số ứng dụng được phép tạo.")}</p></div>
          <button aria-label={t("Đóng")} onClick={onClose}>×</button>
        </div>
        {!catalog.payment_available && <div className="billing-notice"><b>{t("Thanh toán chưa sẵn sàng")}</b><span>{t("Hệ thống chưa được cấu hình SePay. Liên hệ hỗ trợ để kích hoạt thanh toán.")}</span></div>}
        <form className="billing-option" onSubmit={(event) => onCheckout(event, "PLAN")}>
          <div className="billing-option-head"><div><span>{t("GIA HẠN / NÂNG GÓI")}</span><h3>{t("Chọn gói dịch vụ")}</h3></div></div>
          {defaultPlan ? <>
            <label>{t("Gói")}
              <SelectField name="plan_id" defaultValue={defaultPlan.id} ariaLabel={t("Gói dịch vụ")} options={catalog.plans.map((plan) => ({ value: plan.id, label: `${plan.name} · ${money(plan.monthly_price_vnd)}/${t("tháng")}`, description: t("{count} ứng dụng · {ram} MB RAM mỗi app", { count: plan.max_apps, ram: plan.max_memory_mb_per_app }) }))} />
            </label>
            <label>{t("Thời hạn")}
              <SelectField name="duration_months" defaultValue="1" ariaLabel={t("Thời hạn")} options={catalog.terms.map((term) => ({ value: String(term), label: t("{count} tháng", { count: term }), description: t("Thanh toán một lần, không tự động trừ tiền") }))} />
            </label>
            <button className="button primary billing-submit" disabled={!catalog.payment_available || loading}>{loading ? t("Đang xử lý…") : t("Tiếp tục thanh toán")}</button>
          </> : <p className="muted">{t("Chưa có gói trả phí được công bố.")}</p>}
        </form>
        <form className="billing-option" onSubmit={(event) => onCheckout(event, "APP_SLOT")}>
          <div className="billing-option-head"><div><span>{t("MUA THÊM ỨNG DỤNG")}</span><h3>{t("Tăng hạn mức ứng dụng")}</h3></div><b>{money(catalog.app_slot_monthly_price_vnd)}/{t("slot/tháng")}</b></div>
          <p>{t("Slot mua thêm đi cùng thời hạn gói hiện tại và được tính lại khi gia hạn.")}</p>
          <label>{t("Số slot cần thêm")}
            <SelectField name="quantity" defaultValue="1" ariaLabel={t("Số slot cần thêm")} options={[1, 2, 3, 5, 10].map((quantity) => ({ value: String(quantity), label: t("+{count} ứng dụng", { count: quantity }), description: money(catalog.app_slot_monthly_price_vnd * quantity) + "/" + t("tháng") }))} />
          </label>
          <button className="button secondary billing-submit" disabled={!catalog.payment_available || loading}>{loading ? t("Đang xử lý…") : t("Mua thêm slot")}</button>
        </form>
        <small className="billing-policy">{t("Quyền lợi chỉ được cộng sau khi SePay xác nhận thanh toán. IPN lặp lại không cộng trùng.")}</small>
      </aside>
    </div>
  );
}

function AuthScreen({
  onSubmit,
  error,
  loading,
  initialRegister,
}: {
  onSubmit: (event: FormEvent<HTMLFormElement>, register: boolean) => void;
  error: string;
  loading: boolean;
  initialRegister: boolean;
}) {
  const { t, href } = useI18n();
  const [register, setRegister] = useState(initialRegister);
  return (
    <main className="auth-page">
      <Link className="brand" href={href("/")}>
        <span>V</span> Vive Host
      </Link>
      <div className="auth-language">
        <LanguageSwitcher />
      </div>
      <form
        className="auth-card"
        onSubmit={(event) => onSubmit(event, register)}
      >
        <div>
          <small>{t("VIVE HOST · OPEN BETA")}</small>
          <h1>{register ? t("Tạo tài khoản") : t("Đăng nhập")}</h1>
          <p>
            {register
              ? t("Deploy miễn phí một ứng dụng trong giai đoạn beta.")
              : t("Tiếp tục quản lý các ứng dụng của bạn.")}
          </p>
        </div>
        {error && <div className="form-error">{error}</div>}
        {register && (
          <label>
            {t("Họ và tên")}
            <input
              name="name"
              autoComplete="name"
              required
              placeholder={t("Nguyễn Văn An")}
            />
          </label>
        )}
        <label>
          Email
          <input
            name="email"
            type="email"
            autoComplete="email"
            required
            placeholder="you@example.com"
          />
        </label>
        <label>
          {t("Mật khẩu")}
          <input
            name="password"
            type="password"
            autoComplete={register ? "new-password" : "current-password"}
            minLength={8}
            required
            placeholder={t("Tối thiểu 8 ký tự")}
          />
        </label>
        {register && (
          <label className="auth-legal">
            <input name="legal_acceptance" type="checkbox" required />
            <span>
              {t("Tôi đồng ý với")}{" "}
              <Link href={href("/policies/terms")}>
                {t("Điều khoản sử dụng")}
              </Link>{" "}
              {t("và")}{" "}
              <Link href={href("/policies/privacy")}>
                {t("Chính sách bảo mật")}
              </Link>
              .
            </span>
          </label>
        )}
        {!register && (
          <div className="auth-login-options">
            <label className="auth-remember">
              <input name="remember_login" type="checkbox" defaultChecked />
              <span>{t("Ghi nhớ đăng nhập")}</span>
            </label>
            <Link className="auth-inline-link" href={href("/forgot-password")}>
              {t("Quên mật khẩu?")}
            </Link>
          </div>
        )}
        <button className="button primary auth-submit" disabled={loading}>
          {loading
            ? t("Đang xử lý…")
            : register
              ? t("Tạo tài khoản")
              : t("Đăng nhập")}
        </button>
        <button
          type="button"
          className="text-button"
          onClick={() => setRegister(!register)}
        >
          {register
            ? t("Đã có tài khoản? Đăng nhập")
            : t("Chưa có tài khoản? Đăng ký beta")}
        </button>
      </form>
    </main>
  );
}

function CreateDrawer({
  onClose,
  onSubmit,
  loading,
}: {
  onClose: () => void;
  onSubmit: (event: FormEvent<HTMLFormElement>) => void;
  loading: boolean;
}) {
  const { t } = useI18n();
  return (
    <div className="overlay" onMouseDown={onClose}>
      <aside
        className="drawer"
        onMouseDown={(event) => event.stopPropagation()}
      >
        <div className="drawer-head">
          <div>
            <span className="drawer-kicker">{t("NEW APPLICATION")}</span>
            <h2>{t("Ứng dụng mới")}</h2>
            <p>{t("Kết nối GitHub repository để deploy.")}</p>
          </div>
          <button aria-label={t("Đóng")} onClick={onClose}>
            ×
          </button>
        </div>
        <form onSubmit={onSubmit}>
          <label>
            {t("Tên ứng dụng")}
            <input name="name" required placeholder="Internal CRM" />
          </label>
          <label>
            {t("GitHub repository URL")}
            <input
              name="repository_url"
              type="url"
              required
              placeholder="https://github.com/you/app"
            />
          </label>
          <div className="form-row">
            <label>
              Branch
              <input name="branch" defaultValue="main" required />
            </label>
            <div className="form-field">
              <span className="form-label">{t("Framework")}</span>
              <SelectField
                name="framework"
                defaultValue="auto"
                ariaLabel={t("Chọn framework")}
                options={[
                  {
                    value: "auto",
                    label: t("Tự phát hiện"),
                    description: t("Khuyến nghị"),
                  },
                  {
                    value: "nextjs",
                    label: "Next.js",
                    description: t("React full-stack"),
                  },
                  {
                    value: "laravel",
                    label: "Laravel",
                    description: t("PHP application"),
                  },
                  {
                    value: "python",
                    label: "Python",
                    description: t("Python service"),
                  },
                  {
                    value: "static",
                    label: "Static",
                    description: "HTML, CSS, JS",
                  },
                ]}
              />
            </div>
          </div>
          <p className="quota-note">
            <b>{t("Open Beta quota")}</b>
            <span>512 MB RAM · 0.5 CPU · 2 GB disk</span>
          </p>
          <div className="drawer-actions">
            <button
              type="button"
              className="button secondary"
              onClick={onClose}
            >
              {t("Hủy")}
            </button>
            <button className="button primary" disabled={loading}>
              {loading ? t("Đang tạo…") : t("Tạo và deploy")}
            </button>
          </div>
        </form>
      </aside>
    </div>
  );
}

function AppDrawer({
  app,
  token,
  onClose,
  onDeploy,
  onRestart,
}: {
  app: Application;
  token: string;
  onClose: () => void;
  onDeploy: () => void;
  onRestart: () => void;
}) {
  const { t } = useI18n();
  const [logs, setLogs] = useState("");
  const loadLogs = async () => {
    const result = await api<{ logs: string }>(
      `/apps/${app.id}/logs/runtime`,
      {},
      token,
    );
    setLogs(result.logs);
  };
  return (
    <div className="overlay" onMouseDown={onClose}>
      <aside
        className="drawer app-detail"
        onMouseDown={(event) => event.stopPropagation()}
      >
        <div className="drawer-head">
          <div>
            <StatusBadge status={app.status} />
            <h2>{app.name}</h2>
            <p>{app.domain ?? app.repository_url}</p>
          </div>
          <button aria-label={t("Đóng")} onClick={onClose}>
            ×
          </button>
        </div>
        <div className="detail-actions">
          <button className="button primary" onClick={onDeploy}>
            {t("Deploy")}
          </button>
          <button className="button secondary" onClick={onRestart}>
            {t("Restart")}
          </button>
          <button className="button secondary" onClick={loadLogs}>
            {t("Xem logs")}
          </button>
        </div>
        <section>
          <h3>{t("Deployment gần nhất")}</h3>
          {app.latest_deployment ? (
            <dl>
              <div>
                <dt>{t("Trạng thái")}</dt>
                <dd>
                  <StatusBadge status={app.latest_deployment.status} />
                </dd>
              </div>
              <div>
                <dt>{t("Branch")}</dt>
                <dd>{app.latest_deployment.branch}</dd>
              </div>
              <div>
                <dt>{t("Commit")}</dt>
                <dd className="mono">
                  {app.latest_deployment.commit_sha ?? t("Đang xác định")}
                </dd>
              </div>
            </dl>
          ) : (
            <p className="muted">{t("Ứng dụng chưa được deploy.")}</p>
          )}
        </section>
        {logs && (
          <section>
            <h3>{t("Runtime logs")}</h3>
            <pre className="log-viewer">{logs}</pre>
          </section>
        )}
        <section>
          <h3>{t("Tài nguyên")}</h3>
          <div className="meters">
            <label>
              <span>RAM</span>
              <b>0 / {app.resources.memory_mb} MB</b>
              <i>
                <em style={{ width: "2%" }} />
              </i>
            </label>
            <label>
              <span>CPU</span>
              <b>0 / {app.resources.cpu}</b>
              <i>
                <em style={{ width: "2%" }} />
              </i>
            </label>
            <label>
              <span>{t("Disk")}</span>
              <b>0 / {app.resources.disk_mb} MB</b>
              <i>
                <em style={{ width: "2%" }} />
              </i>
            </label>
          </div>
        </section>
      </aside>
    </div>
  );
}
