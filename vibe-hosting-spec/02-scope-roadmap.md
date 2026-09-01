# 02 — Scope & Development Roadmap

## Phase 0 — Repository & Engineering Foundation

Deliver:

- Monorepo or clearly separated frontend/backend folders.
- Environment templates.
- Local Docker development.
- CI for lint/test/build.
- Architecture decision records.
- Seed admin account mechanism.
- Basic API error format.
- Request ID support.

Acceptance:

- Frontend starts locally.
- Laravel API starts locally.
- PostgreSQL works.
- Redis works.
- Tests run in CI.

---

## Phase 1 — Internal Alpha

Goal:

Deploy team-owned applications end to end.

Features:

### Authentication

- login
- logout
- current user
- role handling

### Applications

- create app
- name/slug
- GitHub repository URL
- branch
- framework field
- resource limit defaults

### Deployment

- queue deployment
- provider adapter
- Dokploy integration
- status tracking
- build logs
- runtime logs
- restart
- stop
- delete

### Networking

- platform subdomain
- HTTPS

### Environment variables

- add
- edit
- delete
- secret masking

Framework validation projects:

- Next.js
- Laravel
- Python
- Static HTML

Exit criteria:

- Create app → deploy → HTTPS URL works.
- User can see failure logs.
- Dokploy credentials never appear in browser.

---

## Phase 2 — Closed Beta

Goal:

5–10 real users.

Add:

- self registration
- quota service
- 1 app per beta user
- user application ownership checks
- admin users list
- suspend/activate user
- admin app management
- admin quota override
- audit logs
- build concurrency = 1
- platform resource overview

Track:

- deploy success
- build duration
- RAM
- CPU
- disk
- user support issues

Exit criteria:

- No user can access another user's app.
- Admin can suspend a user and block new operational actions.
- Quota prevents additional app creation.

---

## Phase 3 — Open Beta

Goal:

Allow public free registration.

Add:

- onboarding
- clearer deployment error messages
- basic framework detection
- basic MCP server
- MCP app list
- MCP app create
- MCP deploy
- MCP status/logs
- MCP restart

Open beta notice:

- best effort
- no SLA
- users responsible for retaining important copies

Exit criteria:

- AI can deploy through MCP without direct Dokploy access.
- All MCP actions appear in audit logs.

---

## Phase 4 — Paid MVP

Only start after clear demand.

Add:

- plans
- subscriptions
- SePay/payment integration
- auto activation
- auto suspension
- custom domains
- usage reporting
- backup tiers
- restore UI
- plan upgrade

Do not make payment logic part of deployment provider.

---

## Phase 5 — Infrastructure Scale

Trigger examples:

- sustained CPU > 60%
- RAM > 70%
- disk > 70%
- unacceptable build wait
- too many user apps for one node

Evolution:

1. Scale home server vertically if easy.
2. Move control plane to VPS when justified.
3. Add worker nodes.
4. Separate build workloads if necessary.
5. Separate PostgreSQL/Redis only when needed.

Avoid Kubernetes until there is an actual operational reason.
