"use client";

import Link from "next/link";
import { FormEvent, useCallback, useEffect, useState } from "react";
import { api, ApiError, ApiTokenRecord, User } from "@/lib/api";
import { LanguageSwitcher } from "./language-switcher";
import { StatusBadge } from "./status-badge";
import { useI18n } from "@/lib/i18n";

export function AccountSettings() {
  const { t, href, dateLocale } = useI18n();
  const [token, setToken] = useState<string | null>(null);
  const [user, setUser] = useState<User | null>(null);
  const [tokens, setTokens] = useState<ApiTokenRecord[]>([]);
  const [newToken, setNewToken] = useState("");
  const [error, setError] = useState("");
  const [notice, setNotice] = useState("");
  const [busy, setBusy] = useState(false);

  const load = useCallback(async (authToken: string) => {
    const [profile, records] = await Promise.all([
      api<User>("/me", {}, authToken),
      api<ApiTokenRecord[]>("/tokens", {}, authToken),
    ]);
    setUser(profile);
    setTokens(records);
  }, []);

  useEffect(() => {
    Promise.resolve().then(async () => {
      const saved = localStorage.getItem("vive_token");
      if (!saved) {
        setError(t("Bạn cần đăng nhập trước."));
        return;
      }
      setToken(saved);
      try {
        await load(saved);
      } catch (caught) {
        setError(
          caught instanceof ApiError
            ? t(caught.message)
            : t("Không thể tải tài khoản."),
        );
      }
    });
  }, [load, t]);

  const createMcpToken = async (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault();
    if (!token) return;
    setBusy(true);
    setError("");
    setNotice("");
    setNewToken("");
    const formElement = event.currentTarget;
    const name = String(new FormData(formElement).get("name"));
    try {
      const created = await api<{ token: string }>(
        "/tokens/mcp",
        { method: "POST", body: JSON.stringify({ name }) },
        token,
      );
      setNewToken(created.token);
      formElement.reset();
      await load(token);
    } catch (caught) {
      setError(
        caught instanceof ApiError
          ? t(caught.message)
          : t("Không thể tạo MCP token."),
      );
    } finally {
      setBusy(false);
    }
  };

  const revoke = async (id: string) => {
    if (
      !token ||
      !window.confirm(
        t(
          "Thu hồi token này? MCP client dùng token sẽ mất quyền truy cập ngay.",
        ),
      )
    )
      return;
    setBusy(true);
    setError("");
    setNotice("");
    try {
      await api(`/tokens/${id}`, { method: "DELETE" }, token);
      await load(token);
    } catch (caught) {
      setError(
        caught instanceof ApiError
          ? t(caught.message)
          : t("Không thể thu hồi token."),
      );
    } finally {
      setBusy(false);
    }
  };

  const resendVerification = async () => {
    if (!token) return;
    setBusy(true);
    setError("");
    setNotice("");
    try {
      await api(
        "/auth/email/verification-notification",
        { method: "POST", body: "{}" },
        token,
      );
      setNotice(t("Email xác minh đã được xếp hàng gửi."));
    } catch (caught) {
      setError(
        caught instanceof ApiError
          ? t(caught.message)
          : t("Không thể gửi email xác minh."),
      );
    } finally {
      setBusy(false);
    }
  };

  return (
    <div className="app-page">
      <header className="app-topbar">
        <Link className="brand" href={href("/dashboard")}>
          <span>V</span> Vive Host
        </Link>
        <Link href={href("/dashboard")}>{t("Tổng quan")}</Link>
        <span>/</span>
        <b>{t("Tài khoản & MCP")}</b>
        <LanguageSwitcher compact />
      </header>
      <main className="account-content">
        <div className="page-header">
          <div>
            <h1>{t("Tài khoản & MCP")}</h1>
            <p>
              {t("Quản lý phiên đăng nhập và quyền truy cập cho trợ lý AI.")}
            </p>
          </div>
        </div>
        {error && (
          <div className="alert">
            <b>{t("Cần xử lý")}</b>
            <span>{error}</span>
            <button onClick={() => setError("")}>×</button>
          </div>
        )}
        {notice && (
          <div className="success-message account-notice">{notice}</div>
        )}
        {user && (
          <section className="section account-profile">
            <div className="section-title">
              <h2>{t("Thông tin tài khoản")}</h2>
              <StatusBadge status={user.status} />
            </div>
            <dl className="detail-list">
              <div>
                <dt>{t("Họ tên")}</dt>
                <dd>{user.name}</dd>
              </div>
              <div>
                <dt>Email</dt>
                <dd>{user.email}</dd>
              </div>
              <div>
                <dt>{t("Xác minh email")}</dt>
                <dd>
                  {user.email_verified_at ? (
                    t("Đã xác minh")
                  ) : (
                    <button
                      className="verify-link"
                      disabled={busy}
                      onClick={resendVerification}
                    >
                      {t("Gửi email xác minh")}
                    </button>
                  )}
                </dd>
              </div>
              <div>
                <dt>{t("Vai trò")}</dt>
                <dd>{t(user.role)}</dd>
              </div>
            </dl>
          </section>
        )}
        <section className="section account-tokens">
          <div className="section-title">
            <div>
              <h2>{t("MCP access tokens")}</h2>
              <p>
                {t(
                  "Chỉ cấp các quyền app, deployment, env và usage cần thiết.",
                )}
              </p>
            </div>
          </div>
          <div className="settings-body">
            <form className="inline-form" onSubmit={createMcpToken}>
              <input
                name="name"
                required
                maxLength={100}
                placeholder={t("Ví dụ: Claude Desktop")}
              />
              <button className="button primary" disabled={busy}>
                {t("Tạo MCP token")}
              </button>
            </form>
            {newToken && (
              <div className="credential-once">
                <b>{t("Sao chép ngay — token chỉ hiển thị một lần")}</b>
                <code>{newToken}</code>
                <button
                  className="button secondary small"
                  onClick={() => navigator.clipboard.writeText(newToken)}
                >
                  {t("Sao chép")}
                </button>
              </div>
            )}
            <div className="token-list">
              {tokens.map((item) => (
                <div key={item.id}>
                  <div>
                    <b>{item.name}</b>
                    <small>
                      {t(item.actor_type)} ·{" "}
                      {t("hết hạn {date}", {
                        date: new Date(item.expires_at).toLocaleDateString(
                          dateLocale,
                        ),
                      })}
                    </small>
                  </div>
                  <StatusBadge
                    status={item.revoked_at ? "REVOKED" : "ACTIVE"}
                  />
                  <span>
                    {item.last_used_at
                      ? t("Dùng {date}", {
                          date: new Date(item.last_used_at).toLocaleString(
                            dateLocale,
                          ),
                        })
                      : t("Chưa sử dụng")}
                  </span>
                  {!item.revoked_at && (
                    <button disabled={busy} onClick={() => revoke(item.id)}>
                      {t("Thu hồi")}
                    </button>
                  )}
                </div>
              ))}
            </div>
          </div>
        </section>
      </main>
    </div>
  );
}
