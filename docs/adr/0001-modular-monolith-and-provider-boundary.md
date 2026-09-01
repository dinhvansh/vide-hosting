# ADR 0001: Modular monolith and deployment provider boundary

## Status

Accepted.

## Decision

The Laravel API owns authentication, authorization, quotas, audit, and operational workflows. Dashboard, admin routes, queue jobs, and MCP tools call this business boundary. Infrastructure operations are available only through `DeploymentProvider`; local development binds a deterministic fake provider and production may bind Dokploy.

Deployments are persisted before dispatch and executed asynchronously by a single beta worker. Customer workloads receive fixed CPU, memory, and disk limits from quota configuration.

## Consequences

Provider credentials never enter browser or MCP responses. Replacing or scaling Dokploy does not change public API contracts. A concrete Dokploy installation still requires its version-specific endpoint mapping and credentials before production deployment.
