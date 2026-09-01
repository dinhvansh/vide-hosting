# 15 — AI Agent Task Template

Use this template for implementation tasks.

## Task

Describe requested outcome.

## Relevant Specs

List files that govern the change.

Example:

```text
03-system-architecture.md
05-api-contract.md
08-security-rules.md
09-ui-ux-rules.md
```

## Preflight

- [ ] Git status checked
- [ ] Current branch checked
- [ ] Recent commits inspected
- [ ] Relevant repo code searched
- [ ] CodeGraph queried if needed
- [ ] Existing tests inspected

## Current Behavior

Describe verified current behavior from repository evidence.

## Desired Behavior

Describe target behavior.

## Impact

### Backend

- modules:
- services:
- models:
- jobs:
- provider:

### Frontend

- pages:
- components:
- API client/types:

### Database

- migration required: YES / NO

### MCP

- affected tools:

### Security

- ownership:
- quota:
- secrets:
- audit:

## Implementation

Describe concise implementation approach.

## Acceptance Criteria

List testable criteria.

## Verification

Run:

```text
focused tests
full relevant tests
lint
typecheck
build
```

For UI:

- desktop check
- mobile check
- loading state
- empty state
- error state
- UI/UX rule compliance

## Post-Change

- [ ] Git diff reviewed
- [ ] No unrelated changes
- [ ] CodeGraph updated/rechecked if needed
- [ ] Docs/spec updated if contract changed
- [ ] Known limitations documented
