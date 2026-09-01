# 03 — System Architecture

## 1. High-Level Architecture

```text
                ┌────────────────────┐
                │ User Dashboard     │
                │ Next.js / React    │
                └─────────┬──────────┘
                          │
                          │ HTTPS REST
                          ▼
                ┌────────────────────┐
                │ Laravel API        │
                │ Modular Monolith   │
                └─────────┬──────────┘
                          │
        ┌─────────────────┼─────────────────┐
        │                 │                 │
        ▼                 ▼                 ▼
 PostgreSQL            Redis           Queue Workers
                                              │
                                              ▼
                                  DeploymentService
                                              │
                                              ▼
                                  DeploymentProvider
                                              │
                                      DokployProvider
                                              │
                                              ▼
                                           Dokploy
                                              │
                                              ▼
                                        Docker Apps
```

Additional entry point:

```text
AI Agent
   │
   ▼
MCP Server / Tool Gateway
   │
   ▼
Laravel API / Services
```

Admin Dashboard uses the same Laravel API.

## 2. Architectural Rules

### Rule A — One business backend

Dashboard, Admin and MCP must use the same business rules.

Do not duplicate deployment logic in:

- frontend
- MCP server
- cron scripts
- Dokploy-specific controllers

### Rule B — Modular monolith

Initial backend modules:

- Auth
- Users
- Applications
- Deployments
- Domains
- EnvironmentVariables
- Databases
- Quotas
- Usage
- Plans
- Admin
- Audit
- MCP

### Rule C — Service layer

Core services:

- UserService
- ApplicationService
- DeploymentService
- DomainService
- EnvironmentVariableService
- DatabaseService
- QuotaService
- AuditService

Controllers should be thin.

### Rule D — Async deployment

Deploy is not a blocking HTTP process.

Pattern:

```text
POST /deploy
→ validate
→ create deployment row
→ enqueue job
→ return 202
→ worker executes
→ status updates
```

## 3. Repository Structure

Recommended:

```text
/
├── frontend/
│   ├── app/
│   ├── components/
│   ├── features/
│   ├── lib/
│   ├── hooks/
│   └── types/
│
├── backend/
│   ├── app/
│   │   ├── Modules/
│   │   ├── Services/
│   │   ├── Providers/
│   │   ├── Jobs/
│   │   └── Policies/
│   ├── database/
│   ├── routes/
│   └── tests/
│
├── mcp/
│   ├── tools/
│   ├── auth/
│   └── tests/
│
├── infrastructure/
│   ├── docker/
│   ├── scripts/
│   └── docs/
│
└── docs/
```

## 4. Status Model

Application:

- CREATED
- RUNNING
- STOPPED
- FAILED
- SUSPENDED
- DELETED

Deployment:

- QUEUED
- BUILDING
- DEPLOYING
- RUNNING
- FAILED
- CANCELLED

Do not overload application status with deployment status.

## 5. Scaling Model

Initial:

```text
One Ubuntu host
├── frontend
├── backend
├── redis
├── postgres
├── worker
├── dokploy
└── customer containers
```

Future:

```text
Frontend
   │
API × N
   │
Redis / PostgreSQL
   │
Workers × N
   │
Deployment Provider
   │
Worker Nodes × N
```

Do not redesign application APIs when moving to multiple nodes.
