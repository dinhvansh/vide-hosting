# 04 — Data Model

Use UUIDs unless there is a strong implementation reason not to.

## users

- id
- name
- email
- password_hash
- role: SUPER_ADMIN | ADMIN | USER
- status: ACTIVE | SUSPENDED | BETA | DISABLED
- email_verified_at
- created_at
- updated_at

Indexes:

- unique(email)
- status
- role

## applications

- id
- user_id
- name
- slug
- repository_url
- branch
- framework
- status
- provider
- provider_application_id
- cpu_limit
- memory_limit_mb
- disk_limit_mb
- created_at
- updated_at
- deleted_at

Indexes:

- user_id
- status
- unique(user_id, slug)

## deployments

- id
- application_id
- status
- branch
- commit_sha
- provider_deployment_id
- build_started_at
- deploy_started_at
- finished_at
- error_code
- error_message
- created_by
- created_at
- updated_at

Indexes:

- application_id
- status
- created_at

## environment_variables

- id
- application_id
- key
- encrypted_value
- is_secret
- created_at
- updated_at

Constraints:

- unique(application_id, key)

Rules:

- encrypted_value must be encrypted at rest.
- secret values must not be returned plaintext after creation.

## domains

- id
- application_id
- domain
- type: PLATFORM_SUBDOMAIN | CUSTOM
- status
- ssl_status
- created_at
- updated_at

Constraints:

- unique(domain)

## databases

- id
- application_id
- type
- database_name
- database_user
- encrypted_password
- provider_database_id
- status
- created_at
- updated_at

## quotas

Prefer default plan quota plus optional user override.

Fields:

- id
- user_id
- max_apps
- max_memory_mb_per_app
- max_cpu_per_app
- max_disk_mb_per_app
- max_build_concurrency
- created_at
- updated_at

## audit_logs

- id
- actor_type: USER | ADMIN | MCP | SYSTEM
- actor_id
- action
- resource_type
- resource_id
- request_id
- ip_address
- metadata_json
- created_at

Audit logs should be append-only.

## provider_resources

Optional generic provider mapping table if Dokploy IDs become more complex.

- id
- resource_type
- internal_resource_id
- provider
- provider_resource_id
- metadata_json
