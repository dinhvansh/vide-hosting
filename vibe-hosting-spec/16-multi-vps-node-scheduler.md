# 16 — Multi-VPS, Node Pool & Scheduler Strategy

## 1. Goal

The platform must grow from one home server to multiple VPS/deployment nodes without changing the main user experience, MCP contract, or core application APIs.

Do not introduce Kubernetes, Swarm, distributed storage, or automatic live migration for this requirement.

## 2. Core Principle

> Design for multiple nodes, operate one node until demand requires more.

Normal users should not know which VPS hosts an application.

User sees:

```text
Application
Status
Domain
Usage
Deployments
```

Platform internally tracks:

```text
Application
→ Node
→ Deployment Provider resource
```

## 3. Initial Single-Node Mode

Initial node:

```text
HOME-01
```

Every active application is assigned:

```text
node_id = HOME-01
```

The platform must work correctly with exactly one node.

Scheduler implementation may initially return the only eligible ACTIVE node.

## 4. Future Multi-Node Mode

Example:

```text
HOME-01
VPS-01
VPS-02
VPS-03
```

New applications are assigned by `NodeScheduler`.

Existing applications stay on their assigned node unless an explicit move/migration is performed.

## 5. Data Model

### nodes

Fields:

- id
- name
- code
- provider
- provider_server_id
- host
- region
- status
- cpu_total
- memory_total_mb
- disk_total_mb
- cpu_reserved
- memory_reserved_mb
- disk_reserved_mb
- cpu_usage_percent
- memory_usage_mb
- disk_usage_mb
- last_heartbeat_at
- metadata_json
- created_at
- updated_at

Recommended status:

```text
ACTIVE
DRAINING
MAINTENANCE
FULL
OFFLINE
DISABLED
```

### applications

Add:

- node_id

Rules:

- `node_id` may be nullable only during migration/backfill.
- New apps must receive a node before provider resource creation.
- Existing apps must be backfilled to the default node.

## 6. Node Assignment Flow

```text
Create Application
      ↓
Validate user
      ↓
Check quota
      ↓
Resolve node
      ↓
Save application.node_id
      ↓
Create provider resource on assigned node
      ↓
Deploy
```

Node assignment must happen before Dokploy application creation.

## 7. NodeScheduler Service

Create:

```text
NodeScheduler
```

Suggested methods:

```text
selectNodeForApplication()
canAcceptApplication()
getAvailableNodes()
markNodeDraining()
markNodeMaintenance()
```

Do not put scheduling logic inside controllers, React, MCP tools, or DokployProvider.

## 8. Scheduler V1

Keep V1 simple and deterministic.

Eligible node:

```text
status = ACTIVE
```

and sufficient resources for requested app limits plus platform reserve.

Consider RAM, CPU, and disk.

## 9. Reserved vs Actual Capacity

Track both reserved and actual usage.

Reserved capacity is based on configured app limits. Actual usage is observed runtime consumption.

V1 capacity protection should primarily use reserved capacity. Actual usage is used for node health and pressure decisions.

## 10. Platform Reserve

Do not schedule 100% of physical resources.

Example:

```text
Node RAM = 8 GB
System reserve = 2 GB
Schedulable RAM = 6 GB
```

## 11. Oversubscription

Do not hard-code aggressive oversubscription in MVP.

After real beta metrics exist, support configurable ratios such as:

```text
memory_overcommit_ratio = 1.3
cpu_overcommit_ratio = 2.0
```

Admin-controlled only.

## 12. Node Thresholds

A node should stop receiving new applications before reaching 100%.

Example starting thresholds:

```text
RAM actual > 75%
OR
Disk actual > 75%
OR
manual drain
```

Thresholds should be configuration.

## 13. DRAINING

Admin can set:

```text
ACTIVE → DRAINING
```

Meaning:

- no new apps assigned
- existing apps keep running
- existing app redeploy is allowed unless separately blocked
- admin may later move apps manually

## 14. MAINTENANCE

No new applications. Admin is performing maintenance. MVP may treat this similarly to DRAINING for placement.

## 15. FULL

May be set automatically when capacity checks fail.

- no new apps
- existing apps remain
- admin retains visibility and safe controls

## 16. OFFLINE

When heartbeat/provider connectivity fails beyond threshold:

```text
mark OFFLINE
alert/show admin
mark affected infrastructure unavailable
do not automatically recreate apps elsewhere
```

Automatic failover is not required.

## 17. Node Health Collection

Periodically collect:

- provider connectivity
- CPU usage
- memory usage
- disk usage
- app count
- last heartbeat
- recent deployment failures

A scheduled Laravel job is enough initially.

## 18. DeploymentProvider Node Context

Provider operations must accept node context.

Conceptually:

```text
createApplication(node, application)
deploy(node, application, deployment)
restart(node, application)
stop(node, application)
getLogs(node, application)
```

`DokployProvider` maps the internal node to the correct Dokploy deployment server.

## 19. Dokploy Mapping

Store explicit provider mapping:

```text
node.provider = DOKPLOY
node.provider_server_id = <remote/deployment server id>
```

Even the first/local Dokploy server should have an explicit node row.

## 20. Admin UI

Add:

```text
Admin → Infrastructure → Nodes
```

Node list columns:

- Name
- Status
- Region
- Apps
- CPU
- RAM
- Disk
- Last heartbeat
- Actions

Actions:

- Activate
- Drain
- Maintenance
- Disable
- View Apps

Normal users must not see this page.

## 21. Manual App Move — Future

Do not block MVP on migration.

Future workflow:

```text
Check destination capacity
→ Prepare destination
→ Copy persistent data
→ Restore database if needed
→ Deploy
→ Health check
→ Switch routing/domain
→ Retire old workload
```

Scheduling and migration are separate features.

## 22. Database Placement

Do not assume managed application database must live on the same node as the application.

App node placement and database placement must remain logically separate.

## 23. Build Server Evolution

Initial:

```text
build + runtime on same node
```

Later only when metrics justify it:

```text
Dedicated Build Node
→ Container Registry
→ Deployment Nodes
```

## 24. Selection Algorithm V1

Recommended:

1. Fetch ACTIVE nodes.
2. Exclude nodes beyond hard pressure thresholds.
3. Exclude nodes without enough reserved capacity.
4. Sort by available schedulable RAM descending.
5. Use disk availability as secondary criterion.
6. Select first eligible node.
7. Persist assignment transactionally.

If no node is available:

```text
NO_CAPACITY
```

Do not create provider resources.

## 25. Concurrency Safety

Protect node reservation with a database transaction plus row/advisory lock or equivalent atomic mechanism.

Do not use an unsafe read-capacity-now / assign-later flow.

## 26. Node Removal

A node cannot be deleted while applications remain assigned.

Allowed:

```text
DRAIN
DISABLE
```

Delete only when assigned app count is zero.

## 27. Audit Events

Add:

```text
NODE_CREATE
NODE_UPDATE
NODE_DRAIN
NODE_ACTIVATE
NODE_MAINTENANCE
NODE_DISABLE
APP_NODE_ASSIGN
APP_NODE_MOVE
```

## 28. MCP Rules

Normal MCP tools do not expose physical placement.

User says:

```text
deploy my app
```

Platform chooses node.

Do not expose normal-user tools such as `deploy_to_vps_2`.

## 29. API Rules

Normal user APIs must not expose:

- VPS IP
- provider token
- Dokploy server ID
- internal infrastructure secrets

Suggested admin endpoints:

```text
GET  /api/v1/admin/nodes
GET  /api/v1/admin/nodes/{node}
POST /api/v1/admin/nodes/{node}/drain
POST /api/v1/admin/nodes/{node}/activate
POST /api/v1/admin/nodes/{node}/maintenance
```

## 30. Retrofit Path for Nearly-Finished Single-Node Code

If current implementation is already near completion:

1. Create `nodes` table.
2. Seed default node `HOME-01`.
3. Add nullable `applications.node_id`.
4. Backfill existing apps.
5. Make new apps assign the default/selected node.
6. Pass node context into provider operations.
7. Implement `NodeScheduler` initially as "return eligible default node".
8. Add capacity-aware selection later.

Do not rewrite working deployment logic unnecessarily.

## 31. Acceptance Criteria

- [ ] `nodes` table exists.
- [ ] Default node exists.
- [ ] Every active app has `node_id`.
- [ ] New app receives node assignment before provider creation.
- [ ] Provider operations use assigned node.
- [ ] User-facing app API is independent of physical VPS.
- [ ] Admin can list nodes.
- [ ] Admin can mark node DRAINING.
- [ ] DRAINING node receives no new app.
- [ ] Existing apps on DRAINING node still operate.
- [ ] No capacity returns stable `NO_CAPACITY`.
- [ ] Concurrent app creation cannot over-allocate node capacity.
- [ ] Normal users do not receive internal provider/server identifiers.

## 32. Explicit Non-Goals

Not required now:

- live migration
- automatic failover
- Kubernetes
- Docker Swarm
- multi-region
- cross-node replicas
- distributed persistent storage
- automatic database migration
- zero-downtime node moves
- AI predictive scheduling
