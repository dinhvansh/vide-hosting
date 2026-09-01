# 09 — UI/UX Design Rules

## 1. Design Goal

The UI must feel like a polished modern deployment product, not:

- cPanel
- an enterprise admin template
- a CRUD generator
- a dashboard filled with oversized cards
- a page with giant headings and lots of empty space
- a generic AI-generated SaaS template

Primary references in spirit:

- Vercel: clarity
- Linear: density and hierarchy
- GitHub: functional information
- Stripe Dashboard: operational clarity

Do not copy visual identity.

## 2. Core Visual Principle

> Compact, calm, information-dense, deliberate.

Use space to separate groups, not to make every element huge.

## 3. Typography Rules

### Page title

Desktop:

- 20–24 px
- weight 600–700
- never 36–48 px inside application dashboards

Mobile:

- 20–22 px

### Section title

- 14–16 px
- weight 600

### Body

- 13–14 px default dashboard text
- 15–16 px only for long-form onboarding/help text

### Secondary/meta text

- 12–13 px

### Buttons

- 13–14 px
- avoid large marketing-style button text in dashboard

### Prohibited

Do not create dashboard pages with:

- 48 px hero titles
- 24 px card labels
- huge KPI numbers without reason
- excessive bold text

## 4. Layout Density

Desktop content width:

- full-width operational layouts are allowed
- preferred max content width ~1440 px
- avoid narrow 800 px centered dashboard layouts

Main shell:

```text
Top bar: 48–56 px
Sidebar: 220–240 px
Content padding: 20–28 px desktop
Mobile padding: 16 px
```

Do not use 40–64 px padding around every panel.

## 5. Card Rules

Cards are NOT the default container for everything.

Use cards only when the content is logically grouped.

Prefer:

- tables
- rows
- bordered sections
- split panels
- compact stat strips

over 10 separate cards.

Card style:

- radius: 8–12 px
- subtle 1 px border
- little or no shadow
- padding: 16–20 px

Do not use:

- giant 24–32 px radius
- floating cards everywhere
- strong box shadows
- glassmorphism
- gradient borders
- every stat in a separate large card

## 6. Dashboard KPI Rules

At most 3–4 high-level metrics in the main overview.

Example:

```text
Apps  4     Running  3     Failed  1     Builds queued  0
```

Prefer one compact horizontal stat block.

Avoid:

```text
[ HUGE CARD ] [ HUGE CARD ] [ HUGE CARD ] [ HUGE CARD ]
```

## 7. Color

Use a neutral product UI.

Guideline:

- one primary accent
- neutral gray scale
- green for healthy/success
- amber for warning
- red for destructive/error
- blue or accent for active/interactive

Do not over-color sections.

Status colors must be readable with text labels, never color-only.

## 8. Buttons

Button sizes:

- height ~32–36 px normal dashboard action
- 40 px only for primary onboarding action

Variants:

- Primary
- Secondary
- Ghost
- Destructive

Do not put a big primary button in every card.

Avoid excessive pill buttons.

## 9. Icons

Use a consistent icon set such as Lucide.

Typical size:

- 14–18 px

Do not use oversized 32–48 px icons inside operational dashboard cards.

Icons supplement labels; they should not replace important action names unless universally understood.

## 10. User Dashboard Navigation

Recommended:

- Overview
- Applications
- Deployments
- Usage
- Account

Inside application:

- Overview
- Deployments
- Logs
- Environment
- Domains
- Database
- Settings

Do not show infrastructure internals such as:

- Traefik
- Docker network
- provider IDs
- container hashes

unless an advanced support/admin view needs them.

## 11. Application List

Preferred desktop design:

A dense table or structured list.

Columns:

- App
- Status
- Domain
- Last deploy
- Usage
- Actions

Do not display each application as a giant 300 px-high card.

Mobile may convert rows into compact stacked items.

## 12. Application Overview

Recommended structure:

```text
App name                      Running
domain.example.com

[Deploy] [Restart] [...]

────────────────────────────────────

Deployment
Latest commit / status / timestamp

Resources
RAM 230 / 512 MB
CPU 0.08 / 0.5
Disk 420 MB / 2 GB

Recent Activity
...
```

Keep the first viewport operational.

Do not waste the first screen on a hero introduction.

## 13. Logs UI

Logs should look technical but clean.

Requirements:

- monospace 12–13 px
- dark log surface acceptable even in light theme
- timestamp optional
- search/filter later
- pause auto-scroll
- copy
- tail indicator

Do not wrap every log line in cards.

## 14. Environment Variables

Use compact table/list.

Columns:

- Key
- Value state
- Secret
- Last updated
- Actions

Secret:

```text
OPENAI_API_KEY    ••••••••••••    Secret
```

Never reveal secret automatically.

Editing should happen in drawer/modal, not a huge page.

## 15. Admin Dashboard

Admin UI can be denser than user UI.

User table:

- Name/email
- Status
- Apps
- Resource usage
- Joined
- Actions

Application table:

- App
- Owner
- Node
- Status
- RAM
- CPU
- Last deploy
- Actions

Use filters and search.

Do not use large profile cards for every user.

## 16. Forms

Label:

- 13–14 px
- concise

Input:

- 36–40 px high

Forms should not span the entire desktop width.

Typical form width:

- 480–640 px

Long configuration may use two columns where useful.

Use helper text only when needed.

## 17. Modals and Drawers

Use modal for:

- confirmations
- small edits

Use right drawer/sheet for:

- environment variable editing
- deployment detail
- user quota editing

Avoid navigating to a full new page for a tiny operation.

## 18. Empty States

Empty state should be compact.

Good:

```text
No applications yet
Deploy a GitHub repository to get started.

[New application]
```

Bad:

- huge illustration
- huge 40 px title
- three paragraphs
- giant button
- full viewport empty state

## 19. Loading

Prefer:

- skeleton rows
- inline spinners
- button progress

Avoid blocking the whole app with a fullscreen loader.

Deployment continues asynchronously.

## 20. Error Messages

Errors must be actionable.

Bad:

> Deployment failed.

Better:

> Build failed while running `npm run build`. Open build logs for details.

If known:

> The container exceeded the 512 MB memory limit.

Provide:

- reason
- next action
- logs link

## 21. Responsive Rules

Mobile must support essential operations:

- check status
- deploy
- restart
- view logs
- edit env
- see domain

Do not force desktop tables on mobile.

Convert tables into:

- compact rows
- stacked key/value sections

## 22. Motion

Use minimal motion.

- 120–200 ms transitions
- no excessive bouncing
- no large page animations
- no animated gradient background

Operational UI should feel fast.

## 23. Marketing vs Product UI

Marketing landing page may use larger typography.

Dashboard may not inherit landing-page typography.

Never use a landing-page hero inside authenticated dashboard routes.

## 24. Copywriting Style

Use concise labels:

Good:

- Deploy
- Restart
- View logs
- Add domain
- Environment
- Usage

Avoid:

- "Launch your incredible application"
- "Experience seamless deployment"
- long AI-sounding helper paragraphs

Vietnamese copy should be natural and direct.

## 25. Component Reuse

Build reusable components:

- StatusBadge
- ResourceMeter
- AppRow
- DeployButton
- EmptyState
- ConfirmDialog
- DataTable
- PageHeader
- SectionHeader
- LogViewer
- SecretField
- ActivityRow

But do not create excessive generic abstractions before repeated usage exists.

## 26. Required Visual QA Before Merge

For each major page verify:

- no giant typography
- no unnecessary card grid
- spacing is consistent
- primary action is obvious
- no more than one strong primary CTA per local context
- table density is readable
- mobile works
- empty/loading/error states exist
- user can understand status without reading documentation

## 27. Explicit Anti-Patterns

Reject UI implementation if it has:

- giant heading + subtitle + 4 huge KPI cards on every page
- 24 px+ card labels
- excessive gradients
- glass cards
- heavy shadows
- rounded-everything design
- 20+ px body copy in dashboard
- excessive vertical whitespace
- every section inside independent card
- icons used as decoration rather than function
- duplicated action buttons
- raw infrastructure jargon in user-facing screens

## 28. Desired Overall Feel

The final product should feel:

- compact
- trustworthy
- developer-friendly
- beginner-friendly
- modern
- calm
- fast

Not:

- flashy
- toy-like
- corporate-heavy
- overly spacious
- template-generated
