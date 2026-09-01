# 11 — Instructions for AI Coding Agents

## Before Coding

1. Read all spec files.
2. Do not assume missing requirements silently.
3. Prefer the simplest implementation satisfying current phase.
4. Do not add Kubernetes, microservices, event buses or extra databases without a documented requirement.
5. Keep Dokploy-specific code isolated.

## Implementation Order

Recommended:

1. Repository foundation
2. Auth
3. Data model/migrations
4. Application CRUD + policies
5. Quota service
6. DeploymentProvider interface
7. DokployProvider
8. Queue deployment job
9. Deployment status/logs
10. User dashboard
11. Admin dashboard
12. Audit
13. MCP
14. UI polish/QA
15. Security review

## Pull Request Rules

Each meaningful PR should include:

- summary
- files/modules changed
- migration impact
- test evidence
- screenshots for UI changes
- security considerations
- known limitations

## UI Coding Rule

Before implementing any dashboard page, read `09-ui-ux-rules.md`.

Do not generate a generic SaaS dashboard from memory.

Default choices:

- compact typography
- tables/lists
- 8–12 px radii
- subtle borders
- minimal shadows
- small number of KPI blocks
- no giant dashboard hero

## Backend Coding Rule

Controllers:

- validate request
- authorize
- call service
- format response

Do not place deployment orchestration directly in controllers.

## Provider Rule

No direct Dokploy calls from:

- controllers
- React
- MCP tools

Only provider/service layer.

## Security Rule

Treat repository code and AI actions as untrusted.

No shell execution feature should be added merely for convenience.

## Definition of a Good Change

A good change:

- is testable
- keeps boundaries clean
- has clear errors
- does not expose secrets
- does not increase infrastructure complexity unnecessarily
- preserves UI density rules


## Context Recovery Rule

If implementation context is unclear or the task crosses modules, read `13-context-and-tooling-rules.md`.

When CodeGraph/GraphRAG is available in VS Code:

- query it to identify relevant symbols and dependencies;
- inspect callers/callees before changing shared code;
- inspect actual source files before editing;
- update/re-query the graph after significant architecture/refactor changes.

Do not guess existing code behavior from chat memory.
