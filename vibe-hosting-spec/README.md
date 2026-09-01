# AI-Native Vibe Hosting — Master Development Spec

## Purpose

This repository is the source of truth for developing an AI-native hosting platform focused on Vietnamese users who build websites and web apps with tools such as ChatGPT, Claude, Cursor, Lovable, Windsurf and similar AI coding tools.

The product is not positioned as a traditional VPS/cPanel hosting product.

Core promise:

> Connect code → Deploy → Get a working HTTPS URL.

Users should not need to understand Docker, Linux, reverse proxies, TLS certificates, ports or infrastructure administration.

The platform must provide:

- Lightweight user dashboard.
- Strong admin dashboard.
- GitHub-based deployment.
- Environment variables.
- Deployment status and logs.
- Resource quotas.
- Automatic HTTPS/subdomain routing.
- Backend abstraction over Dokploy.
- MCP/tool layer so AI agents can deploy and operate applications for users.
- Security boundaries so AI and customer workloads cannot bypass platform permissions.

## Technology Decision

- Frontend: Next.js + React + TypeScript
- Backend: Laravel REST API
- Database: PostgreSQL
- Cache / Queue: Redis
- Deployment engine: Dokploy
- Runtime: Docker
- Reverse proxy: Traefik
- AI integration: MCP server/tool gateway
- Initial infrastructure: Ubuntu home server for free open beta
- Architecture: Modular monolith + provider adapters

## Source of Truth Order

When specs conflict, use this order:

1. `01-product-business.md`
2. `02-scope-roadmap.md`
3. `03-system-architecture.md`
4. `04-data-model.md`
5. `05-api-contract.md`
6. `06-deployment-provider.md`
7. `07-mcp-ai-tools.md`
8. `08-security-rules.md`
9. `09-ui-ux-rules.md`
10. `10-acceptance-tests.md`
11. `11-agent-development-instructions.md`
12. `12-ui-page-specs.md`
13. `13-context-and-tooling-rules.md`
14. `14-git-workflow-and-change-control.md`
15. `15-agent-task-template.md`

## Core Product Principles

1. Do not expose infrastructure complexity to users unless required.
2. Do not let frontend, MCP, or admin routes call Dokploy directly.
3. All operational actions go through backend services and authorization.
4. AI is never trusted implicitly.
5. Every app has resource limits.
6. Customer code must never receive host-level privileges.
7. Do not introduce Kubernetes or microservices before real scaling need.
8. Favor simple, maintainable code over premature abstraction.
9. Do not build billing before beta usage proves demand.
10. UX must feel intentional and productized, not like a generic admin template.

## AI Context Rule

When implementation context is unclear, agents must use repository evidence first. If CodeGraph/GraphRAG is available in the VS Code workspace, use it to understand symbols, dependencies and impact before broad edits. See `13-context-and-tooling-rules.md`.
