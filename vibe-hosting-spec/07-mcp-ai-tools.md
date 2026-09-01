# 07 — MCP / AI Tool Specification

## Purpose

Allow AI agents to operate applications for a user while preserving normal authorization, quota and audit rules.

AI must never call Dokploy directly.

```text
AI Agent
→ MCP Tool
→ Laravel Business API/Service
→ Authorization
→ Quota
→ Audit
→ DeploymentProvider
→ Dokploy
```

## Authentication Model

Each MCP session/action must resolve to:

- authenticated user
- tenant/account context if added later
- permissions
- request ID

MCP token must not imply unlimited infrastructure access.

## Initial Tools

### projects.list

Returns user applications.

### projects.get

Input:

- app_id

### projects.create

Input:

- name
- repository_url
- branch

Must enforce quota.

### deployments.create

Input:

- app_id
- optional branch

Returns deployment ID/status.

### deployments.status

Input:

- deployment_id

### deployments.logs

Input:

- deployment_id
- optional tail limit

### apps.restart

Input:

- app_id

### apps.stop

Input:

- app_id

### env.list

Input:

- app_id

Never returns secret plaintext.

### env.set

Input:

- app_id
- key
- value
- is_secret

### domains.add

Later if custom domain is enabled.

### databases.create

Later when database creation is stable.

## Tools Explicitly Forbidden

Do not expose:

- arbitrary shell execution
- SSH
- Docker CLI
- raw Dokploy API
- privileged container creation
- raw filesystem read/write on host
- Docker socket access
- unrestricted network scanning
- provider token retrieval

## Confirmation Rules

Destructive/high-impact AI actions should require confirmation in the product UX when practical:

- delete app
- restore backup
- remove custom domain
- delete database
- resource changes affecting billing

Restart and normal deploy can be lower friction.

## Audit

Every MCP action logs:

- actor_type = MCP
- effective user_id
- tool name
- inputs with secrets redacted
- resource
- result
- request_id
- timestamp

## AI Troubleshooting Flow

Example:

```text
User: "Why is my app down?"

AI:
1. projects.get
2. deployments.status
3. deployments.logs
4. usage.get
5. Explain likely cause
6. Offer safe action
```

AI may diagnose OOM, build failure, missing env or crash loops based on available telemetry.

AI must not fabricate deployment state.
