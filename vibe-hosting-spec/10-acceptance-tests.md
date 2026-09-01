# 10 — MVP Acceptance Tests

## Authentication

- [ ] User can register.
- [ ] User can login/logout.
- [ ] Suspended user cannot perform operational actions.
- [ ] Admin role is protected.

## Applications

- [ ] User can create one app within quota.
- [ ] Second app is rejected when beta quota is 1.
- [ ] User A cannot access User B app by ID.
- [ ] Admin can view all apps.

## Deployment

- [ ] App can deploy from valid GitHub repo.
- [ ] Deployment returns immediately as queued/accepted.
- [ ] Queue worker executes deployment.
- [ ] Only one beta build executes concurrently.
- [ ] Deployment status updates correctly.
- [ ] Build failure is recorded.
- [ ] User sees normalized useful error.
- [ ] Successful deployment receives HTTPS URL.

## Logs

- [ ] User can view logs for own app.
- [ ] User cannot view logs for another app.
- [ ] Secret values are not printed by platform code.
- [ ] Admin can view logs for support.

## Environment Variables

- [ ] User can add env variable.
- [ ] Secret is encrypted at rest.
- [ ] Secret plaintext is not returned after save.
- [ ] Env update can trigger/recommend redeploy.

## Quota

- [ ] CPU limit applied.
- [ ] RAM limit applied.
- [ ] Default quota applies automatically.
- [ ] Admin override works.
- [ ] User cannot remove resource limits.

## Admin

- [ ] Search/list users.
- [ ] Suspend user.
- [ ] Activate user.
- [ ] Override quota.
- [ ] List all apps.
- [ ] Restart app.
- [ ] Stop app.
- [ ] Delete app.

## Audit

- [ ] Deploy action logged.
- [ ] Restart action logged.
- [ ] Env change logged with secret redaction.
- [ ] Admin suspension logged.
- [ ] MCP action logged.
- [ ] Request ID included.

## MCP

- [ ] MCP lists only current user's apps.
- [ ] MCP deploy checks quota.
- [ ] MCP cannot access raw Dokploy API.
- [ ] MCP cannot retrieve provider secrets.
- [ ] MCP restart appears in audit log.

## Security

- [ ] Customer container does not mount Docker socket.
- [ ] Customer container is not privileged.
- [ ] Dokploy token never reaches frontend bundle/network response.
- [ ] Redis/Postgres control-plane ports are not public.
- [ ] Secrets are redacted from application audit logs.
- [ ] Direct resource ID manipulation does not bypass ownership.

## UI/UX

- [ ] Dashboard page titles <= 24 px desktop.
- [ ] Default body text 13–14 px.
- [ ] Application list is dense table/list, not huge card grid.
- [ ] No more than 3–4 summary KPIs in overview.
- [ ] Normal dashboard buttons 32–36 px height.
- [ ] No excessive shadows/gradients/glass effects.
- [ ] Mobile supports status/deploy/logs/env.
- [ ] Empty states are compact.
- [ ] Errors are actionable.
- [ ] Logs use monospace compact viewer.
