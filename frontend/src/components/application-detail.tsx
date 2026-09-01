"use client";

import Link from "next/link";
import { useRouter } from "next/navigation";
import { FormEvent, useCallback, useEffect, useState } from "react";
import {
  api,
  apiEnvelope,
  ApiError,
  Application,
  Deployment,
  Domain,
  EnvironmentVariable,
  ManagedDatabase,
  Usage,
} from "@/lib/api";
import { SelectField } from "./select-field";
import { LanguageSwitcher } from "./language-switcher";
import { StatusBadge } from "./status-badge";
import { useI18n } from "@/lib/i18n";

const tabs = [
  "Tổng quan",
  "Deployments",
  "Logs",
  "Environment",
  "Domains",
  "Database",
  "Settings",
] as const;
type Tab = (typeof tabs)[number];

export function ApplicationDetail({ appId }: { appId: string }) {
  const { t, href, dateLocale } = useI18n();
  const router = useRouter();
  const [token, setToken] = useState<string | null>(null);
  const [app, setApp] = useState<Application | null>(null);
  const [deployments, setDeployments] = useState<Deployment[]>([]);
  const [variables, setVariables] = useState<EnvironmentVariable[]>([]);
  const [domains, setDomains] = useState<Domain[]>([]);
  const [databases, setDatabases] = useState<ManagedDatabase[]>([]);
  const [usage, setUsage] = useState<Usage | null>(null);
  const [customDomainsEnabled, setCustomDomainsEnabled] = useState(false);
  const [activeTab, setActiveTab] = useState<Tab>("Tổng quan");
  const [runtimeLogs, setRuntimeLogs] = useState("");
  const [buildLogs, setBuildLogs] = useState("");
  const [databasePassword, setDatabasePassword] = useState("");
  const [environmentNotice, setEnvironmentNotice] = useState("");
  const [error, setError] = useState("");
  const [busy, setBusy] = useState(false);

  const load = useCallback(
    async (authToken: string) => {
      const [
        application,
        deploymentList,
        envList,
        domainEnvelope,
        databaseList,
        currentUsage,
      ] = await Promise.all([
        api<Application>(`/apps/${appId}`, {}, authToken),
        api<Deployment[]>(`/apps/${appId}/deployments`, {}, authToken),
        api<EnvironmentVariable[]>(`/apps/${appId}/env`, {}, authToken),
        apiEnvelope<Domain[]>(`/apps/${appId}/domains`, {}, authToken),
        api<ManagedDatabase[]>(`/apps/${appId}/databases`, {}, authToken),
        api<Usage>(`/apps/${appId}/usage`, {}, authToken),
      ]);
      setApp(application);
      setDeployments(deploymentList);
      setVariables(envList);
      setDomains(domainEnvelope.data);
      setCustomDomainsEnabled(
        Boolean(domainEnvelope.meta.custom_domains_enabled),
      );
      setDatabases(databaseList);
      setUsage(currentUsage);
    },
    [appId],
  );

  useEffect(() => {
    Promise.resolve().then(async () => {
      const saved = localStorage.getItem("vive_token");
      if (!saved) {
        setError(t("Bạn cần đăng nhập để xem ứng dụng."));
        return;
      }
      setToken(saved);
      try {
        await load(saved);
      } catch (caught) {
        setError(
          caught instanceof ApiError
            ? t(caught.message)
            : t("Không thể tải ứng dụng."),
        );
      }
    });
  }, [load, t]);
  useEffect(() => {
    if (!token) return;
    const hasActiveDeployment = deployments.some((item) =>
      ["QUEUED", "BUILDING", "DEPLOYING"].includes(item.status),
    );
    if (!hasActiveDeployment) return;
    const timer = window.setInterval(() => {
      load(token).catch(() => undefined);
    }, 4000);
    return () => window.clearInterval(timer);
  }, [deployments, load, token]);
  useEffect(() => {
    if (!token || activeTab !== "Logs") return;
    const refreshLogs = async () => {
      const runtime = await api<{ logs: string }>(
        `/apps/${appId}/logs/runtime`,
        {},
        token,
      );
      setRuntimeLogs(runtime.logs);
      if (deployments[0]) {
        const build = await api<{ logs: string }>(
          `/apps/${appId}/deployments/${deployments[0].id}/logs`,
          {},
          token,
        );
        setBuildLogs(build.logs);
      }
    };
    const timer = window.setInterval(() => {
      refreshLogs().catch(() => undefined);
    }, 5000);
    return () => window.clearInterval(timer);
  }, [activeTab, appId, deployments, token]);

  const run = async (action: () => Promise<void>) => {
    setBusy(true);
    setError("");
    try {
      await action();
      if (token) await load(token);
    } catch (caught) {
      setError(
        caught instanceof ApiError
          ? t(caught.message)
          : t("Không thể hoàn tất thao tác."),
      );
    } finally {
      setBusy(false);
    }
  };
  const deploy = () =>
    run(async () => {
      if (token)
        await api(
          `/apps/${appId}/deployments`,
          {
            method: "POST",
            headers: { "Idempotency-Key": crypto.randomUUID() },
            body: "{}",
          },
          token,
        );
    });
  const operate = (action: "restart" | "stop") =>
    run(async () => {
      if (token)
        await api(
          `/apps/${appId}/${action}`,
          { method: "POST", body: "{}" },
          token,
        );
    });
  const openLogs = () =>
    run(async () => {
      if (!token) return;
      const runtime = await api<{ logs: string }>(
        `/apps/${appId}/logs/runtime`,
        {},
        token,
      );
      setRuntimeLogs(runtime.logs);
      if (deployments[0]) {
        const build = await api<{ logs: string }>(
          `/apps/${appId}/deployments/${deployments[0].id}/logs`,
          {},
          token,
        );
        setBuildLogs(build.logs);
      }
    });

  const saveEnv = (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault();
    const formElement = event.currentTarget;
    const form = new FormData(formElement);
    run(async () => {
      if (!token) return;
      await api(
        `/apps/${appId}/env`,
        {
          method: "POST",
          body: JSON.stringify({
            key: form.get("key"),
            value: form.get("value"),
            is_secret: form.get("is_secret") === "on",
          }),
        },
        token,
      );
      formElement.reset();
      setEnvironmentNotice(
        t(
          "Đã lưu biến môi trường. Hãy redeploy để bản chạy mới nhận thay đổi.",
        ),
      );
    });
  };
  const deleteEnv = (key: string) =>
    run(async () => {
      if (token) {
        await api(
          `/apps/${appId}/env/${encodeURIComponent(key)}`,
          { method: "DELETE" },
          token,
        );
        setEnvironmentNotice(
          t("Đã xóa biến môi trường. Hãy redeploy để áp dụng thay đổi."),
        );
      }
    });
  const addDomain = (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault();
    const form = new FormData(event.currentTarget);
    run(async () => {
      if (token)
        await api(
          `/apps/${appId}/domains`,
          {
            method: "POST",
            body: JSON.stringify({ domain: form.get("domain") }),
          },
          token,
        );
      event.currentTarget.reset();
    });
  };
  const deleteDomain = (id: string) =>
    run(async () => {
      if (token)
        await api(`/apps/${appId}/domains/${id}`, { method: "DELETE" }, token);
    });
  const createDatabase = () =>
    run(async () => {
      if (!token) return;
      const result = await api<{ database: ManagedDatabase; password: string }>(
        `/apps/${appId}/databases`,
        { method: "POST", body: JSON.stringify({ type: "POSTGRESQL" }) },
        token,
      );
      setDatabasePassword(result.password);
    });
  const saveSettings = (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault();
    const form = new FormData(event.currentTarget);
    run(async () => {
      if (!token) return;
      const updated = await api<Application>(
        `/apps/${appId}`,
        {
          method: "PATCH",
          body: JSON.stringify({
            name: form.get("name"),
            branch: form.get("branch"),
            framework: form.get("framework"),
          }),
        },
        token,
      );
      setApp(updated);
    });
  };
  const deleteApp = async () => {
    if (
      !token ||
      !window.confirm(t("Xóa vĩnh viễn ứng dụng và tài nguyên liên quan?"))
    )
      return;
    setBusy(true);
    setError("");
    try {
      await api(`/apps/${appId}`, { method: "DELETE" }, token);
      router.push(href("/dashboard"));
    } catch (caught) {
      setError(
        caught instanceof ApiError
          ? t(caught.message)
          : t("Không thể xóa ứng dụng."),
      );
      setBusy(false);
    }
  };

  if (!app)
    return (
      <div className="detail-loading">
        <Link href={href("/dashboard")}>← Dashboard</Link>
        <p>{error || t("Đang tải ứng dụng…")}</p>
      </div>
    );

  return (
    <div className="app-page">
      <header className="app-topbar">
        <Link className="brand" href={href("/dashboard")}>
          <span>V</span> Vive Host
        </Link>
        <Link href={href("/dashboard")}>{t("Tổng quan")}</Link>
        <span>/</span>
        <b>{app.name}</b>
        <LanguageSwitcher compact />
      </header>
      <main className="app-content">
        <div className="app-heading">
          <div>
            <div className="app-title-line">
              <h1>{app.name}</h1>
              <StatusBadge status={app.status} />
            </div>
            {app.domain ? (
              <a
                href={`https://${app.domain}`}
                target="_blank"
                rel="noreferrer"
              >
                {app.domain}
              </a>
            ) : (
              <span className="app-location">{app.repository_url}</span>
            )}
          </div>
          <div className="app-actions">
            <button className="button primary" disabled={busy} onClick={deploy}>
              {t("Deploy")}
            </button>
            <button
              className="button secondary"
              disabled={busy}
              onClick={() => operate("restart")}
            >
              {t("Restart")}
            </button>
            <button
              className="button secondary"
              disabled={busy}
              onClick={() => operate("stop")}
            >
              {t("Stop")}
            </button>
          </div>
        </div>
        {error && (
          <div className="alert">
            <b>{t("Cần xử lý")}</b>
            <span>{error}</span>
            <button onClick={() => setError("")}>×</button>
          </div>
        )}
        <nav className="app-tabs">
          {tabs.map((tab) => (
            <button
              key={tab}
              className={activeTab === tab ? "active" : ""}
              onClick={() => {
                setActiveTab(tab);
                if (tab === "Logs") openLogs();
              }}
            >
              {t(tab)}
            </button>
          ))}
        </nav>
        {activeTab === "Tổng quan" && (
          <div className="app-grid">
            <section className="section detail-section">
              <div className="section-title">
                <h2>{t("Deployment gần nhất")}</h2>
              </div>
              {deployments[0] ? (
                <dl className="detail-list">
                  <div>
                    <dt>{t("Trạng thái")}</dt>
                    <dd>
                      <StatusBadge status={deployments[0].status} />
                    </dd>
                  </div>
                  <div>
                    <dt>{t("Branch")}</dt>
                    <dd>{deployments[0].branch}</dd>
                  </div>
                  <div>
                    <dt>{t("Commit")}</dt>
                    <dd className="mono">
                      {deployments[0].commit_sha ?? t("Đang xác định")}
                    </dd>
                  </div>
                  <div>
                    <dt>{t("Thời gian")}</dt>
                    <dd>
                      {new Date(deployments[0].created_at).toLocaleString(
                        dateLocale,
                      )}
                    </dd>
                  </div>
                </dl>
              ) : (
                <Empty text={t("Chưa có deployment nào.")} />
              )}
            </section>
            <section className="section detail-section">
              <div className="section-title">
                <h2>{t("Tài nguyên")}</h2>
              </div>
              {usage && (
                <div className="resource-list">
                  <Resource
                    label="RAM"
                    value={usage.memory_mb}
                    limit={usage.limits.memory_mb}
                    unit="MB"
                  />
                  <Resource
                    label="CPU"
                    value={usage.cpu}
                    limit={usage.limits.cpu}
                    unit="vCPU"
                  />
                  <Resource
                    label={t("Disk")}
                    value={usage.disk_mb}
                    limit={usage.limits.disk_mb}
                    unit="MB"
                  />
                </div>
              )}
            </section>
          </div>
        )}
        {activeTab === "Deployments" && (
          <section className="section">
            <div className="section-title">
              <h2>{t("Lịch sử deployment")}</h2>
              <span>{t("{count} lần", { count: deployments.length })}</span>
            </div>
            {deployments.length ? (
              <div className="deployment-list">
                {deployments.map((item) => (
                  <div key={item.id}>
                    <StatusBadge status={item.status} />
                    <b>{item.branch}</b>
                    <code>{item.commit_sha ?? "pending"}</code>
                    <time>
                      {new Date(item.created_at).toLocaleString(dateLocale)}
                    </time>
                  </div>
                ))}
              </div>
            ) : (
              <Empty text={t("Chưa có deployment nào.")} />
            )}
          </section>
        )}
        {activeTab === "Logs" && (
          <div className="logs-grid">
            <LogPanel
              title={t("Build logs")}
              content={buildLogs || t("Chưa có build logs.")}
            />
            <LogPanel
              title={t("Runtime logs")}
              content={runtimeLogs || t("Đang tải runtime logs…")}
            />
          </div>
        )}
        {activeTab === "Environment" && (
          <section className="section">
            <div className="section-title">
              <h2>{t("Environment variables")}</h2>
              <span>{t("Secret không thể xem lại")}</span>
            </div>
            <div className="settings-body">
              {environmentNotice && (
                <div className="success-message env-notice">
                  {environmentNotice}
                  <button onClick={deploy}>{t("Redeploy ngay")}</button>
                </div>
              )}
              <form className="inline-form" onSubmit={saveEnv}>
                <input
                  name="key"
                  required
                  pattern="[A-Z_][A-Z0-9_]*"
                  placeholder="VARIABLE_KEY"
                  autoComplete="off"
                />
                <input
                  name="value"
                  required
                  type="password"
                  placeholder={t("Giá trị")}
                  autoComplete="new-password"
                />
                <label className="check">
                  <input name="is_secret" type="checkbox" defaultChecked />{" "}
                  {t("Secret")}
                </label>
                <button className="button primary" disabled={busy}>
                  {t("Lưu")}
                </button>
              </form>
              {variables.length ? (
                <div className="key-list">
                  {variables.map((item) => (
                    <div key={item.key}>
                      <code>{item.key}</code>
                      <span>
                        {item.has_value ? "••••••••••••" : t("Chưa có giá trị")}
                      </span>
                      <StatusBadge
                        status={item.is_secret ? "SECRET" : "PLAIN"}
                      />
                      <button onClick={() => deleteEnv(item.key)}>
                        {t("Xóa")}
                      </button>
                    </div>
                  ))}
                </div>
              ) : (
                <Empty text={t("Chưa có environment variable.")} />
              )}
            </div>
          </section>
        )}
        {activeTab === "Domains" && (
          <section className="section">
            <div className="section-title">
              <h2>{t("Domains & HTTPS")}</h2>
              <span>
                {customDomainsEnabled
                  ? t("Custom domain đã bật")
                  : t("Beta chỉ dùng platform domain")}
              </span>
            </div>
            <div className="settings-body">
              {customDomainsEnabled && (
                <form className="inline-form" onSubmit={addDomain}>
                  <input name="domain" required placeholder="app.example.com" />
                  <button className="button primary">{t("Thêm domain")}</button>
                </form>
              )}
              <div className="key-list">
                {domains.map((domain) => (
                  <div key={domain.id}>
                    <div>
                      <code>{domain.domain}</code>
                      <small>{domain.type}</small>
                    </div>
                    <StatusBadge status={domain.status} />
                    <StatusBadge status={`SSL_${domain.ssl_status}`} />
                    {domain.type === "CUSTOM" && (
                      <button onClick={() => deleteDomain(domain.id)}>
                        {t("Xóa")}
                      </button>
                    )}
                  </div>
                ))}
              </div>
            </div>
          </section>
        )}
        {activeTab === "Database" && (
          <section className="section">
            <div className="section-title">
              <h2>{t("Managed database")}</h2>
              <span>{t("Tối đa 1 database trong beta")}</span>
            </div>
            <div className="settings-body">
              {databasePassword && (
                <div className="credential-once">
                  <b>{t("Lưu mật khẩu ngay — chỉ hiển thị một lần")}</b>
                  <code>{databasePassword}</code>
                </div>
              )}
              {databases.length === 0 ? (
                <div className="empty">
                  <h3>{t("Chưa có database")}</h3>
                  <p>
                    {t(
                      "Tạo PostgreSQL database với credentials được quản lý an toàn.",
                    )}
                  </p>
                  <button
                    className="button primary"
                    disabled={busy}
                    onClick={createDatabase}
                  >
                    {t("Tạo PostgreSQL")}
                  </button>
                </div>
              ) : (
                <div className="key-list">
                  {databases.map((database) => (
                    <div key={database.id}>
                      <div>
                        <b>{database.database_name}</b>
                        <small>
                          {database.database_user}@{database.host}:
                          {database.port}
                        </small>
                      </div>
                      <StatusBadge status={database.status} />
                      <span>{database.type}</span>
                    </div>
                  ))}
                </div>
              )}
            </div>
          </section>
        )}
        {activeTab === "Settings" && (
          <section className="section app-settings">
            <div className="section-title">
              <h2>{t("Cài đặt ứng dụng")}</h2>
              <span>{t("Branch và framework được đồng bộ sang provider")}</span>
            </div>
            <form className="settings-form" onSubmit={saveSettings}>
              <label>
                {t("Tên ứng dụng")}
                <input
                  name="name"
                  defaultValue={app.name}
                  required
                  maxLength={100}
                />
              </label>
              <label>
                Branch
                <input
                  name="branch"
                  defaultValue={app.branch}
                  required
                  maxLength={100}
                  pattern="(?:[A-Za-z0-9._/]|-)+"
                />
              </label>
              <div className="form-field">
                <span className="form-label">{t("Framework")}</span>
                <SelectField
                  name="framework"
                  defaultValue={app.framework}
                  ariaLabel={t("Chọn framework")}
                  options={[
                    { value: "auto", label: t("Tự phát hiện") },
                    { value: "nextjs", label: "Next.js" },
                    { value: "node", label: "Node.js" },
                    { value: "laravel", label: "Laravel" },
                    { value: "python", label: "Python" },
                    { value: "static", label: "Static" },
                  ]}
                />
              </div>
              <button className="button primary" disabled={busy}>
                {t("Lưu cài đặt")}
              </button>
            </form>
            <div className="danger-zone">
              <h3>{t("Xóa ứng dụng")}</h3>
              <p>
                {t(
                  "Thao tác này xóa ứng dụng và yêu cầu provider dọn tài nguyên liên quan.",
                )}
              </p>
              <button className="button destructive" onClick={deleteApp}>
                {t("Xóa ứng dụng")}
              </button>
            </div>
          </section>
        )}
      </main>
    </div>
  );
}

function Empty({ text }: { text: string }) {
  return <div className="small-empty">{text}</div>;
}
function LogPanel({ title, content }: { title: string; content: string }) {
  const { t } = useI18n();
  return (
    <section className="log-panel">
      <header>
        <b>{title}</b>
        <button onClick={() => navigator.clipboard.writeText(content)}>
          {t("Copy")}
        </button>
      </header>
      <pre>{content}</pre>
    </section>
  );
}
function Resource({
  label,
  value,
  limit,
  unit,
}: {
  label: string;
  value: number;
  limit: number;
  unit: string;
}) {
  const percent = Math.min(100, limit ? (value / limit) * 100 : 0);
  return (
    <div>
      <span>{label}</span>
      <b>
        {value} / {limit} {unit}
      </b>
      <i>
        <em style={{ width: `${percent}%` }} />
      </i>
    </div>
  );
}
