# 13 — Context Recovery, CodeGraph & Tooling Rules

## 1. Purpose

AI coding agents must not guess architecture, dependencies, call paths or existing behavior when repository evidence can be inspected.

The repository and its current implementation are the source of truth.

AI memory/chat context is supplementary only.

## 2. Required Context Recovery Order

When context is incomplete, ambiguous or possibly stale, use this order:

```text
1. Read README.md
2. Read relevant spec files
3. Inspect Git status / branch / recent commits
4. Search repository
5. Query CodeGraph / GraphRAG if available
6. Inspect affected source files directly
7. Inspect tests
8. Inspect configuration / migrations / routes
9. Use external documentation/tools only when needed
10. State assumptions explicitly only if evidence is still missing
```

Never invent current implementation details.

## 3. CodeGraph Is an Approved Primary Context Tool

The VS Code development environment may contain CodeGraph / GraphRAG tooling that indexes:

- files
- symbols
- functions
- classes
- imports
- callers
- callees
- dependency relationships
- module relationships
- impact paths

When CodeGraph is available, agents SHOULD use it before broad manual scanning when the task requires understanding existing code relationships.

## 4. When CodeGraph MUST Be Used

Use CodeGraph or equivalent repository graph tooling when any of these are true:

### Cross-module change

Examples:

- Auth → Applications → Deployment
- Quota → Deployment → Admin
- MCP → Laravel service → Provider
- Domain → Provider → Traefik

### Impact is unclear

Before modifying a shared:

- service
- interface
- model
- type
- event
- API contract
- database entity
- provider adapter

query callers/dependencies first.

### Existing implementation is unfamiliar

If the agent does not understand how a module works:

```text
DO NOT GUESS
→ Query graph
→ Inspect key nodes
→ Inspect source
```

### Refactoring

Before moving/renaming/removing a symbol:

- find callers
- find callees
- find references
- inspect dependency impact

### Unexpected behavior

When a bug seems to span multiple layers:

```text
UI
→ API
→ Service
→ Queue
→ Provider
```

trace the code path before editing.

## 5. Preferred CodeGraph Questions

Examples:

```text
Which modules depend on DeploymentService?
```

```text
Show callers of deploy().
```

```text
Trace the path from POST /apps/{id}/deployments to DokployProvider.
```

```text
Which code checks application ownership?
```

```text
Where is quota enforced before deployment?
```

```text
What code writes Deployment status?
```

```text
Which modules depend on the Application model?
```

```text
What will be affected if DeploymentProvider.deploy() changes?
```

## 6. CodeGraph Does Not Replace Source Inspection

Graph results provide context.

For any code change, inspect the actual relevant source files before modifying them.

Correct workflow:

```text
CodeGraph
   ↓
Identify relevant symbols/files
   ↓
Read actual implementation
   ↓
Read tests
   ↓
Make change
```

Do not implement solely from graph summaries.

## 7. Keep CodeGraph Fresh

If CodeGraph supports incremental indexing/watch mode, use it.

After significant changes involving:

- new modules
- renamed services
- removed classes
- changed dependency boundaries
- new provider adapters
- large refactors

update/rebuild the graph before final impact verification.

Do not rely on a stale graph.

## 8. Git Is Also Required Context

Before a new implementation task:

```text
git status
git branch --show-current
git log --oneline -n <reasonable number>
```

or equivalent Git tooling.

Check for:

- uncommitted changes
- unexpected branch
- recent architectural changes
- conflicting work
- files modified by another task

Do not overwrite unrelated user changes.

## 9. Spec vs Code Conflicts

If:

```text
Specification says A
Current implementation does B
```

the agent must:

1. identify the conflict;
2. determine whether B is intentional from Git/history/tests if possible;
3. avoid silently changing behavior;
4. update implementation/spec together when the new requirement clearly supersedes the old one;
5. report the decision.

## 10. Tool Selection Rule

Use tools to reduce uncertainty.

Preferred tools when available:

```text
CodeGraph / GraphRAG
Git
repository search
filesystem/source reader
test runner
type checker
linter
database/migration inspector
API client
browser/devtools for UI verification
Dokploy documentation/API inspection
```

Do not use external web search to infer behavior already present in the local repository.

## 11. Context Budget Rule

Do not read the entire repository blindly.

Prefer:

```text
Graph/search
→ identify relevant files
→ inspect focused implementation
```

This reduces noise and prevents important relationships from being missed.

## 12. Before-Coding Protocol

Before modifying code:

- [ ] Read relevant specification.
- [ ] Check Git state.
- [ ] Identify affected module(s).
- [ ] Search existing implementation.
- [ ] Query CodeGraph when dependency/context is non-trivial.
- [ ] Inspect relevant tests.
- [ ] Identify security/quota/provider impact.
- [ ] Identify API/schema impact.
- [ ] Identify UI rules if frontend is involved.

## 13. After-Coding Protocol

After implementation:

- [ ] Run focused tests.
- [ ] Run relevant full test suite.
- [ ] Run lint/typecheck/build.
- [ ] Verify acceptance criteria.
- [ ] Re-query CodeGraph impact if shared code changed.
- [ ] Update CodeGraph/index if required.
- [ ] Check Git diff.
- [ ] Verify no unrelated changes.
- [ ] Update specification if behavior/contracts changed.
- [ ] Record known limitations.

## 14. No Memory-as-Truth Rule

Never use statements like:

> "I remember this module works like..."

as implementation evidence.

Use:

```text
Repo
Git
CodeGraph
Tests
Specs
```

as evidence.

## 15. Failure to Obtain Context

If tools fail or graph data is unavailable:

1. fall back to repository search/source inspection;
2. state which context could not be verified;
3. choose the smallest safe implementation;
4. do not make broad architectural changes based on assumptions.

## 16. Critical Rule

> If enough context exists locally to verify a claim, verify it before coding.
