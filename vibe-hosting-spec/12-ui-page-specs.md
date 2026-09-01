# 12 — Core Page Specifications

## User — Overview

Header:

- title: Overview
- optional subtle beta badge
- one primary action: New application

Content:

1. Compact stat strip:
   - Applications
   - Running
   - Failed
   - Queued builds
2. Recent applications table/list
3. Recent deployment activity

Do not use a hero block.

## User — Applications

Desktop:

Table/list with:

- Name
- Status
- Domain
- Last deploy
- RAM
- Actions

Primary action:

- New application

Row click opens app detail.

## User — New Application

Form width ~560–640 px.

Fields:

- Name
- GitHub repository URL
- Branch
- optional framework override
- environment variables section

Primary:

- Deploy application

Show quota note discreetly, not as huge alert.

## User — Application Detail

Top row:

- app name
- domain
- status badge
- Deploy button
- overflow actions

Tabs:

- Overview
- Deployments
- Logs
- Environment
- Domains
- Database
- Settings

Overview:

- latest deployment
- resource meter
- recent activity

## User — Deployment Detail

- status
- commit
- branch
- timestamps
- build log viewer
- retry/redeploy action

Avoid large status illustrations.

## User — Logs

- compact toolbar
- runtime/build switch
- auto-refresh
- copy
- monospace viewer

## User — Environment

Compact key/value table.

Add/edit through drawer or modal.

## Admin — Overview

Compact metrics:

- users
- apps
- running
- failed
- queued
- host RAM/CPU/disk

Then:

- recent failures
- top resource consumers
- recent users

Do not create 12 KPI cards.

## Admin — Users

Dense data table:

- Name/email
- Status
- Apps
- RAM usage
- Joined
- Actions

Detail drawer/page:

- account status
- quota
- apps
- audit activity

## Admin — Applications

Dense table:

- App
- Owner
- Status
- Domain
- RAM
- CPU
- Last deploy
- Actions

## Admin — System

Show:

- CPU
- RAM
- Disk
- build queue
- deployment failures
- Dokploy/provider connectivity

Technical information belongs here, not user dashboard.
