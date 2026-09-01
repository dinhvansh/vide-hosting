import { McpServer } from "@modelcontextprotocol/sdk/server/mcp.js";
import { StdioServerTransport } from "@modelcontextprotocol/sdk/server/stdio.js";
import { z } from "zod";

const baseUrl = process.env.VIVE_API_URL ?? "http://localhost:8000/api/v1";
const token = process.env.VIVE_API_TOKEN;
if (!token) throw new Error("VIVE_API_TOKEN is required");

async function request(path: string, options: RequestInit = {}) {
  const response = await fetch(`${baseUrl}${path}`, { ...options, headers: { Authorization: `Bearer ${token}`, Accept: "application/json", "Content-Type": "application/json", "X-Vive-Actor": "MCP", ...options.headers } });
  const body = await response.json();
  if (!response.ok) throw new Error(body.error?.message ?? "Vive API request failed");
  return body.data;
}

const output = (data: unknown) => ({ content: [{ type: "text" as const, text: JSON.stringify(data, null, 2) }] });
const server = new McpServer({ name: "vive-host", version: "0.1.0" });
server.tool("projects.list", "List applications owned by the authenticated Vive user", {}, async () => output(await request("/apps")));
server.tool("projects.get", "Get one owned application", { app_id: z.string().uuid() }, async ({ app_id }) => output(await request(`/apps/${app_id}`)));
server.tool("projects.create", "Create an application while enforcing account quota", { name: z.string().min(1), repository_url: z.string().url(), branch: z.string().default("main") }, async (input) => output(await request("/apps", { method: "POST", body: JSON.stringify(input) })));
server.tool("deployments.create", "Queue a deployment", { app_id: z.string().uuid(), branch: z.string().optional(), idempotency_key: z.string().max(100).optional() }, async ({ app_id, branch, idempotency_key }) => output(await request(`/apps/${app_id}/deployments`, { method: "POST", headers: { "Idempotency-Key": idempotency_key ?? crypto.randomUUID() }, body: JSON.stringify({ branch }) })));
server.tool("deployments.status", "Read deployment state", { deployment_id: z.string().uuid() }, async ({ deployment_id }) => output(await request(`/deployments/${deployment_id}`)));
server.tool("deployments.logs", "Read normalized build logs", { deployment_id: z.string().uuid(), tail: z.number().int().min(1).max(500).optional() }, async ({ deployment_id, tail }) => output(await request(`/deployments/${deployment_id}/logs${tail === undefined ? "" : `?tail=${tail}`}`)));
server.tool("apps.restart", "Restart an owned application", { app_id: z.string().uuid() }, async ({ app_id }) => output(await request(`/apps/${app_id}/restart`, { method: "POST", body: "{}" })));
server.tool("apps.stop", "Stop an owned application", { app_id: z.string().uuid() }, async ({ app_id }) => output(await request(`/apps/${app_id}/stop`, { method: "POST", body: "{}" })));
server.tool("env.list", "List environment variable metadata without values", { app_id: z.string().uuid() }, async ({ app_id }) => output(await request(`/apps/${app_id}/env`)));
server.tool("env.set", "Set an environment variable; secret values are never returned", { app_id: z.string().uuid(), key: z.string().regex(/^[A-Z_][A-Z0-9_]*$/), value: z.string(), is_secret: z.boolean().default(true) }, async ({ app_id, ...input }) => output(await request(`/apps/${app_id}/env`, { method: "POST", body: JSON.stringify(input) })));
server.tool("usage.get", "Read application CPU, memory, disk usage and configured limits", { app_id: z.string().uuid() }, async ({ app_id }) => output(await request(`/apps/${app_id}/usage`)));
await server.connect(new StdioServerTransport());
