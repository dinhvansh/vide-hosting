export type User = { id: string; name: string; email: string; email_verified_at?: string | null; role: string; status: string; applications_count?: number; applications_memory_limit_mb?: number; quota?: Quota; created_at?: string };
export type Quota = { max_apps: number; max_memory_mb_per_app: number; max_cpu_per_app: number; max_disk_mb_per_app: number; max_build_concurrency: number };
export type Deployment = { id: string; status: string; branch: string; commit_sha: string | null; created_at: string; error?: { code: string; message: string } | null };
export type Application = { id: string; name: string; slug: string; repository_url: string; branch: string; framework: string; status: string; domain?: string | null; owner?: User; resources: { cpu: number; memory_mb: number; disk_mb: number }; latest_deployment?: Deployment | null; created_at: string };
export type EnvironmentVariable = { key: string; is_secret: boolean; has_value: boolean; updated_at: string };
export type Domain = { id: string; domain: string; type: string; status: string; ssl_status: string; created_at: string };
export type ManagedDatabase = { id: string; type: string; database_name: string; database_user: string; host: string; port: number; status: string; has_password: boolean; created_at: string };
export type Usage = { cpu: number; memory_mb: number; disk_mb: number; limits: { cpu: number; memory_mb: number; disk_mb: number } };
export type NodeRecord = { id: string; name: string; code: string; provider: string; region: string | null; status: string; applications_count: number; capacity: { cpu: { total: number; reserved: number; usage_percent: number | null }; memory_mb: { total: number; reserved: number; usage: number | null }; disk_mb: { total: number; reserved: number; usage: number | null } }; last_heartbeat_at: string | null; created_at: string; updated_at: string };
export type ApiTokenRecord = { id: string; name: string; actor_type: string; abilities: string[]; last_used_at: string | null; expires_at: string; revoked_at: string | null };
const API_URL = process.env.NEXT_PUBLIC_API_URL ?? "http://localhost:8000/api/v1";
export class ApiError extends Error { constructor(message: string, public code = "REQUEST_FAILED", public details: Record<string, string[]> = {}) { super(message); } }
function requestHeaders(options: RequestInit, token?: string): HeadersInit {
  const locale = typeof document === "undefined" ? "vi" : document.documentElement.lang;
  return { "Content-Type": "application/json", Accept: "application/json", "Accept-Language": locale, ...(token ? { Authorization: `Bearer ${token}` } : {}), ...options.headers };
}
export async function api<T>(path: string, options: RequestInit = {}, token?: string): Promise<T> { const response = await fetch(`${API_URL}${path}`, { ...options, headers: requestHeaders(options, token) }); const payload = await response.json(); if (!response.ok) throw new ApiError(payload.error?.message ?? "Không thể hoàn tất yêu cầu.", payload.error?.code, payload.error?.details); return payload.data as T; }
export async function apiEnvelope<T>(path: string, options: RequestInit = {}, token?: string): Promise<{ data: T; meta: Record<string, unknown> }> { const response = await fetch(`${API_URL}${path}`, { ...options, headers: requestHeaders(options, token) }); const payload = await response.json(); if (!response.ok) throw new ApiError(payload.error?.message ?? "Không thể hoàn tất yêu cầu.", payload.error?.code, payload.error?.details); return { data: payload.data as T, meta: payload.meta ?? {} }; }
