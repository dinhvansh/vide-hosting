# Vive Host

Vive Host is a Vietnamese-first application deployment control plane. It connects GitHub repositories to an asynchronous provider-backed deployment flow and exposes the same authorization, quota, secret, and audit rules to the dashboard, admin interface, and MCP tools.

## Local development

Requirements: Docker Desktop with Linux containers.

```bash
docker compose up --build
```

Open `http://localhost:3000` (or set `FRONTEND_PORT` when that port is occupied). The API is available at `http://localhost:8000/api/v1`. Local Docker uses the safe fake deployment provider. The production stack uses the Dokploy adapter and requires a Dokploy URL plus `x-api-key` credential.

For an admin account, run in the backend container:

```bash
docker compose exec backend php artisan db:seed
```

Default local admin credentials are declared in `backend/.env.example` and must be changed outside local development.

## Services

- `frontend`: Next.js 16 dashboard and admin UI
- `backend`: Laravel 13 REST API and modular service layer
- `worker`: single-concurrency beta deployment queue
- `notification-worker`: independent email queue so account mail is never blocked by a build
- `scheduler`: deployment reconciliation and stale-job recovery (production)
- `postgres`, `redis`: control-plane persistence and queue
- `mcp`: stdio MCP gateway authenticated with `VIVE_API_TOKEN`

Deployment jobs use a 35-minute Redis visibility timeout, longer than the 30-minute worker timeout. Email uses a separate short-visibility Redis connection so mail retries promptly without making long-running provider operations eligible for duplicate execution.

## Verification

```bash
cd backend && php artisan test
cd frontend && npm run lint && npm run build
cd mcp && npm run typecheck && npm run build
docker compose config --quiet
```

## Production deployment

Copy `.env.production.example` to `.env.production`, supply every required secret/domain and a working mail transport (`MAIL_MAILER`, `MAIL_FROM_ADDRESS`, and SMTP/provider settings), then validate and start the stack:

```bash
docker compose --env-file .env.production -f docker-compose.production.yml config --quiet
docker compose --env-file .env.production -f docker-compose.production.yml run --rm migrate php artisan production:preflight
docker compose --env-file .env.production -f docker-compose.production.yml up -d --build
docker compose --env-file .env.production -f docker-compose.production.yml ps
```

Install Dokploy on the host first so its attachable `dokploy-network` and Traefik edge proxy exist. DNS for `PUBLIC_DOMAIN` must point to this host and ports 80/443 must reach Dokploy Traefik. `PLATFORM_DOMAIN` must be delegated to the same ingress. The Vive gateway joins `dokploy-network` without publishing a host port; Traefik terminates TLS and forwards to it on port 8080. PostgreSQL and Redis remain on the private control network. Check `/api/v1/health/live` for process health and `/api/v1/health/ready` for database, Redis, and Dokploy readiness.

Create the initial production administrator once after the stack is healthy. The seeder is idempotent and reads `ADMIN_NAME`, `ADMIN_EMAIL`, and `ADMIN_PASSWORD` from the production environment:

```bash
docker compose --env-file .env.production -f docker-compose.production.yml exec backend php artisan db:seed --force
```

Create a private PostgreSQL backup artifact on the host (protect the `backups` directory with filesystem encryption and copy it to encrypted off-site storage):

```bash
docker compose --env-file .env.production -f docker-compose.production.yml --profile maintenance run --rm backup-postgres
```

Test each backup by restoring it into an isolated PostgreSQL instance before rotating older copies. PostgreSQL is the control-plane source of truth. Redis contains disposable cache, sessions, and queued work; after Redis loss users sign in again and the scheduler re-dispatches/reconciles deployments from PostgreSQL and Dokploy. Do not restore stale Redis queue snapshots because they can replay provider operations.

For upgrades, take a backup, pull/build the new images, and run `up -d --build`; the one-shot `migrate` service must finish successfully before API, worker, and scheduler start. Roll back application images only when the database migration is backward-compatible; otherwise restore the matching PostgreSQL backup first.

The original product and engineering specifications remain under `vibe-hosting-spec/`.
