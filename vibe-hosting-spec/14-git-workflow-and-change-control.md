# 14 — Git Workflow & Change Control

## 1. Before Every Task

Check:

```text
git status
current branch
recent commits
```

Never assume the workspace is clean.

## 2. Preserve Existing Work

Do not:

- reset unrelated changes
- force checkout over user files
- delete unknown files
- rewrite history
- force push

unless explicitly requested.

## 3. Scope Changes

Keep each change focused.

Before editing, identify:

- modules
- APIs
- migrations
- frontend routes
- tests
- documentation

that should change.

Avoid opportunistic unrelated refactors.

## 4. Schema Changes

Every database schema change must include:

- migration
- model update
- validation/API changes
- tests
- backward/rollout consideration where relevant

## 5. API Changes

When API contract changes:

update:

- backend
- frontend client/types
- MCP consumers if affected
- tests
- `05-api-contract.md`

## 6. Architecture Changes

When a durable architecture decision changes:

update the relevant specification and optionally add an ADR.

Examples:

- replacing Dokploy
- changing auth model
- changing provider interface
- introducing new worker architecture
- changing tenant model

## 7. Completion Summary

At task completion report:

- what changed
- why
- affected modules
- migrations
- API changes
- tests run
- UI verification
- security implications
- known limitations
