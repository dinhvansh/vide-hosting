# 08 — Security & Isolation Rules

These requirements are mandatory, not optional polish.

## 1. Trust Boundaries

Untrusted:

- customer repositories
- customer runtime code
- customer-provided environment variables
- AI-generated code
- MCP requests until authorized

Trusted:

- Laravel business backend
- provider secrets
- internal admin actions after authorization

## 2. Container Rules

Customer workloads must never:

- run privileged
- mount `/`
- mount `/var/run/docker.sock`
- access host secrets
- access deployment provider tokens
- access backend env files
- share writable volumes with another customer
- receive host networking unless explicitly justified

## 3. Resource Control

Every app must have limits:

- memory
- CPU
- disk where enforceable
- process/PID limit if supported

No unlimited beta apps.

## 4. Network Isolation

Where technically possible:

- isolate customer apps
- prevent direct access to internal control-plane services
- restrict access to PostgreSQL/Redis control-plane instances
- do not expose database admin ports publicly

## 5. Secrets

Platform secrets include:

- Dokploy API token
- DB passwords
- Laravel APP_KEY
- JWT/session secrets
- MCP signing/auth secrets

Rules:

- never send to frontend
- never print to logs
- redact from audit metadata
- encrypt customer secret env values at rest

## 6. Authorization

Every app operation checks:

1. authenticated actor
2. user active status
3. resource ownership
4. role/admin override
5. quota
6. operation-specific permission

Never rely only on app IDs being difficult to guess.

## 7. Admin Security

Admin actions require:

- dedicated role
- audit logging
- CSRF/session protections as applicable
- rate limiting where relevant

High-risk admin actions should record reason metadata later.

## 8. Home Beta Infrastructure

Do not expose:

- Docker API
- Dokploy admin ports
- PostgreSQL
- Redis
- SSH without secure configuration

Prefer:

- Cloudflare Tunnel for public web routes
- firewall deny-by-default
- SSH keys
- separate Ubuntu host/VM

## 9. Abuse Protection

Beta should support:

- suspend user
- stop app
- delete app
- rate-limit deploy
- rate-limit restarts
- detect obvious resource abuse
- terminate containers exceeding hard resource limits

## 10. Logging

Do not log:

- passwords
- full API keys
- env secret values
- authorization headers
- Dokploy token

## 11. Security Definition of Done

A release fails security acceptance if:

- user A can see user B app/log/env
- customer container can access Docker socket
- provider credentials reach browser
- secret env is returned plaintext
- suspended user can deploy/restart
