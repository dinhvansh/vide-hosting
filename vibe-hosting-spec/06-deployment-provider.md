# 06 — Deployment Provider Abstraction

## Goal

Dokploy is infrastructure implementation, not business architecture.

All Dokploy-specific operations must be isolated behind an adapter.

## Interface

Conceptual interface:

```text
DeploymentProvider

createApplication()
updateApplication()
deleteApplication()

deploy()
restart()
stop()

getApplicationStatus()
getDeploymentStatus()

getBuildLogs()
getRuntimeLogs()

getHostMetrics()

setEnvironmentVariables()

addDomain()
removeDomain()

createDatabase()
deleteDatabase()

setResourceLimits()
```

## DokployProvider

`DokployProvider` translates internal business objects into Dokploy API calls.

It must:

- normalize Dokploy status into platform status
- normalize Dokploy errors into internal error codes
- hide provider tokens
- implement retry rules where safe
- support request correlation
- avoid leaking provider response objects into controllers
- normalize optional Dokploy host monitoring into CPU, RAM, disk and uptime values
- return an unavailable state instead of leaking monitoring errors or tokens

## Internal Error Codes

Examples:

- PROVIDER_UNAVAILABLE
- PROVIDER_AUTH_FAILED
- BUILD_FAILED
- DEPLOY_FAILED
- INVALID_REPOSITORY
- DOMAIN_CONFIGURATION_FAILED
- RESOURCE_LIMIT_FAILED
- APP_NOT_FOUND
- PROVIDER_TIMEOUT

Frontend should receive stable internal errors, not provider-specific text.

## Framework Detection

Initial hints:

### Next.js / Node

- `package.json`

### Laravel

- `composer.json`
- `artisan`

### Python

- `requirements.txt`
- `pyproject.toml`

### Static

- `index.html`

If uncertain, request user configuration:

- build command
- start command
- exposed port

Do not attempt magical detection that produces unsafe shell commands.

## Resource Limits

Every app creation must enforce defaults.

Beta:

- CPU 0.5
- RAM 512 MB
- disk target 1–2 GB

No customer action may remove limits.

## Build Queue

Global beta concurrency:

```text
1
```

Deploy jobs beyond capacity remain QUEUED.
