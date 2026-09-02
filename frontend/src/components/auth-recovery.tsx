"use client";

import Link from "next/link";
import { FormEvent, useEffect, useState } from "react";
import { api, ApiError } from "@/lib/api";
import { clearAuthToken } from "@/lib/auth-token";
import { LanguageSwitcher } from "./language-switcher";
import { useI18n } from "@/lib/i18n";

function AuthFrame({ title, description, children }: { title: string; description: string; children: React.ReactNode }) {
  const { t, href } = useI18n();
  return <main className="auth-page"><Link className="brand" href={href("/")}><span>V</span> Vive Host</Link><div className="auth-language"><LanguageSwitcher /></div><section className="auth-card"><div><small>{t("VIVE HOST · BẢO MẬT TÀI KHOẢN")}</small><h1>{title}</h1><p>{description}</p></div>{children}</section></main>;
}

export function ForgotPassword() {
  const { t, href } = useI18n();
  const [message, setMessage] = useState("");
  const [error, setError] = useState("");
  const [busy, setBusy] = useState(false);
  const submit = async (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault(); setBusy(true); setError("");
    const email = String(new FormData(event.currentTarget).get("email"));
    try { await api("/auth/forgot-password", { method: "POST", body: JSON.stringify({ email }) }); setMessage(t("Nếu email tồn tại, Vive Host đã xếp hàng thư đặt lại mật khẩu.")); }
    catch (caught) { setError(caught instanceof ApiError ? t(caught.message) : t("Không thể gửi yêu cầu lúc này.")); }
    finally { setBusy(false); }
  };
  return <AuthFrame title={t("Quên mật khẩu")} description={t("Nhập email tài khoản để nhận liên kết dùng một lần.")}>{message ? <div className="success-message">{message}</div> : <form onSubmit={submit}>{error && <div className="form-error">{error}</div>}<label>Email<input name="email" type="email" autoComplete="email" required /></label><button className="button primary auth-submit" disabled={busy}>{busy ? t("Đang gửi…") : t("Gửi liên kết đặt lại")}</button></form>}<Link className="text-button" href={href("/dashboard")}>{t("Quay lại đăng nhập")}</Link></AuthFrame>;
}

export function ResetPassword({ token, email }: { token: string; email: string }) {
  const { t, href } = useI18n();
  const [message, setMessage] = useState("");
  const [error, setError] = useState("");
  const [busy, setBusy] = useState(false);
  const submit = async (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault(); setBusy(true); setError("");
    const form = new FormData(event.currentTarget); const password = String(form.get("password"));
    try { await api("/auth/reset-password", { method: "POST", body: JSON.stringify({ token, email, password, password_confirmation: form.get("password_confirmation") }) }); clearAuthToken(); setMessage(t("Mật khẩu đã được đổi. Các phiên cũ đã bị thu hồi.")); }
    catch (caught) { setError(caught instanceof ApiError ? t(caught.message) : t("Không thể đặt lại mật khẩu.")); }
    finally { setBusy(false); }
  };
  return <AuthFrame title={t("Đặt lại mật khẩu")} description={email ? t("Tạo mật khẩu mới cho {email}.", { email }) : t("Liên kết đặt lại không hợp lệ.")}>{message ? <><div className="success-message">{message}</div><Link className="button primary auth-submit" href={href("/dashboard")}>{t("Đăng nhập")}</Link></> : <form onSubmit={submit}>{error && <div className="form-error">{error}</div>}<label>{t("Mật khẩu mới")}<input name="password" type="password" autoComplete="new-password" minLength={8} required /></label><label>{t("Nhập lại mật khẩu")}<input name="password_confirmation" type="password" autoComplete="new-password" minLength={8} required /></label><button className="button primary auth-submit" disabled={busy || !token || !email}>{busy ? t("Đang cập nhật…") : t("Đổi mật khẩu")}</button></form>}</AuthFrame>;
}

export function VerifyEmail({ verificationUrl }: { verificationUrl: string }) {
  const { t, href } = useI18n();
  const [state, setState] = useState<"loading" | "success" | "error">(verificationUrl ? "loading" : "error");
  useEffect(() => { if (!verificationUrl) return; const controller = new AbortController(); fetch(verificationUrl, { headers: { Accept: "application/json" }, signal: controller.signal }).then((response) => { if (!response.ok) throw new Error(); setState("success"); }).catch((error: unknown) => { if (error instanceof Error && error.name !== "AbortError") setState("error"); }); return () => controller.abort(); }, [verificationUrl]);
  return <AuthFrame title={t("Xác minh email")} description={t("Vive Host đang kiểm tra liên kết xác minh của bạn.")}>{state === "loading" && <div className="success-message">{t("Đang xác minh…")}</div>}{state === "success" && <><div className="success-message">{t("Email đã được xác minh thành công.")}</div><Link className="button primary auth-submit" href={href("/dashboard")}>{t("Vào dashboard")}</Link></>}{state === "error" && <><div className="form-error">{t("Liên kết không hợp lệ hoặc đã hết hạn.")}</div><Link className="text-button" href={href("/account")}>{t("Gửi lại email xác minh")}</Link></>}</AuthFrame>;
}
