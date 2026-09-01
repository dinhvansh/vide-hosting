# 05 — REST API Contract

Base:

```text
/api/v1
```

Standard success:

```json
{
  "data": {},
  "meta": {},
  "request_id": "..."
}
```

Standard error:

```json
{
  "error": {
    "code": "QUOTA_EXCEEDED",
    "message": "Application limit reached.",
    "details": {}
  },
  "request_id": "..."
}
```

Never return raw Dokploy errors directly to the frontend.

## Authentication

- POST `/auth/register`
- POST `/auth/login`
- POST `/auth/logout`
- GET `/me`

## Applications

- GET `/apps`
- POST `/apps`
- GET `/apps/{app}`
- PATCH `/apps/{app}`
- DELETE `/apps/{app}`

Create input:

```json
{
  "name": "CRM",
  "repository_url": "https://github.com/example/crm",
  "branch": "main"
}
```

## Deployments

- GET `/apps/{app}/deployments`
- POST `/apps/{app}/deployments`
- GET `/apps/{app}/deployments/{deployment}`
- POST `/apps/{app}/restart`
- POST `/apps/{app}/stop`

Deploy returns HTTP 202.

## Logs

- GET `/apps/{app}/logs/runtime`
- GET `/apps/{app}/deployments/{deployment}/logs`

Both endpoints accept an optional integer `tail` query parameter from 1 to 500 and return only the last requested lines.

## Environment Variables

- GET `/apps/{app}/env`
- POST `/apps/{app}/env`
- PATCH `/apps/{app}/env/{key}`
- DELETE `/apps/{app}/env/{key}`

Secret response example:

```json
{
  "key": "OPENAI_API_KEY",
  "is_secret": true,
  "has_value": true
}
```

Do not return secret value.

## Domains

- GET `/apps/{app}/domains`
- POST `/apps/{app}/domains`
- DELETE `/apps/{app}/domains/{domain}`

MVP may only allow platform subdomain.

## Databases

- POST `/apps/{app}/databases`
- GET `/apps/{app}/databases`

## Usage

- GET `/apps/{app}/usage`
- GET `/usage`

## Admin Users

- GET `/admin/users`
- GET `/admin/users/{user}`
- POST `/admin/users/{user}/suspend`
- POST `/admin/users/{user}/activate`
- PATCH `/admin/users/{user}/quota`

## Admin Applications

- GET `/admin/apps`
- GET `/admin/apps/{app}`
- POST `/admin/apps/{app}/restart`
- POST `/admin/apps/{app}/stop`
- POST `/admin/apps/{app}/redeploy`
- DELETE `/admin/apps/{app}`

## Admin System

- GET `/admin/system/overview`
- GET `/admin/system/build-queue`

The overview includes account/application counts, provider connectivity, normalized host CPU/RAM/disk/uptime metrics, recent deployment failures, and the top five running application resource consumers. Host metrics return `available: false` with a stable message when Dokploy monitoring is not configured; provider credentials and monitoring tokens are never returned.

The overview also includes `product_metrics` for Open Beta validation:

- registrations and verified users
- total application creations, including deleted applications
- successful first deployments
- terminal deployment success rate
- median time to first live deployment
- repeat deployment rate
- applications still running after seven days
- restart action count

## API Rules

1. Every resource route checks ownership or admin authorization.
2. All operational mutations generate audit records.
3. Rate-limit deploy/restart/log-heavy endpoints.
4. Use idempotency key support for deploy creation if practical.
5. Long-running operations are async.
