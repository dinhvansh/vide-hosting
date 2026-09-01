# 01 — Product & Business Requirements

## 1. Product Vision

Build a Vietnamese-friendly deployment platform for people who create software with AI coding tools but do not want to operate servers.

The platform should feel closer to:

- "Deploy my app"
- "Put this repo online"
- "Fix why this deployment failed"

and not:

- "Manage my VPS"
- "Configure Nginx"
- "Configure Docker networking"

## 2. Primary Value Proposition

### User-facing promise

> Deploy your AI-built app to the internet in minutes.

### Product differentiators

- Vietnamese-first UX.
- Simple pricing in VND later.
- Local payment options later.
- Lightweight dashboard.
- AI-assisted deployment through MCP.
- Admin can manage user resources centrally.
- Avoid Vercel-style complex metering in the early product.

## 3. Target Users

### AI coding users

Users building with:

- Cursor
- Claude
- ChatGPT
- Lovable
- Windsurf
- Bolt
- GitHub Copilot

### Independent developers

Need quick deployment without maintaining a VPS.

### Freelancers

Need reliable deployment for:

- landing pages
- customer websites
- MVP apps
- internal tools
- CRM-like apps
- prototypes

### SMEs

Need simple deployment for internal or customer-facing apps without dedicated DevOps.

## 4. Open Beta Business Model

Initial stage:

- FREE
- Best effort
- No SLA
- One app per account
- Resource limits
- No billing system required
- No invoice system required

Beta goal is not revenue.

Beta goal is to answer:

1. Will people actually deploy?
2. Will they redeploy?
3. What frameworks do they use?
4. What breaks most often?
5. How much support is required?
6. What CPU/RAM/disk is actually consumed?
7. Which features do users request before paying?

## 5. Initial Beta Quotas

Default:

- 1 account = 1 app
- RAM max = 512 MB
- CPU max = 0.5 vCPU
- Disk target = 1–2 GB
- Concurrent build per platform = 1
- No guaranteed uptime

Admin may override quota per user.

## 6. Future Pricing Direction

Do not implement in MVP, but keep plan/quota architecture compatible.

Possible direction:

- FREE
- STARTER ~79,000 VND/month
- PRO ~149,000 VND/month
- BUSINESS ~299,000 VND/month

Potential differentiators:

- number of apps
- RAM
- CPU
- disk
- custom domain
- databases
- backups
- retention
- build concurrency
- support level
- MCP/AI operating features

## 7. Success Metrics

Track:

- registrations
- activated users
- app creation count
- successful first deployments
- deployment success rate
- median time to first live URL
- repeat deployment rate
- active apps after 7 days
- app restart frequency
- support requests
- average RAM per app
- average CPU per app
- average disk per app

Most important early signal:

> Does a user return and deploy again after the first successful deployment?

## 8. Product Non-Goals for MVP

Do not build:

- Kubernetes
- autoscaling
- multi-region
- enterprise SLA
- complex RBAC
- organization/team management
- mobile app
- S3 backup
- invoice
- payment gateway
- advanced observability stack
- custom orchestration engine
