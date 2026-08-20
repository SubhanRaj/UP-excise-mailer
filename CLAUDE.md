# CLAUDE.md — UP Excise Mailer

Read [plan.md](./plan.md) first — it's the full approved build plan (context,
domain model, auth flow, sending design, UI, deploy). This file is the living
architecture reference; [summary.md](./summary.md) tracks what's actually
built vs. still pending. Keep both updated as work progresses.

## What this app is

HQ currently emails files to the department's 5 zones / 18 divisions / 75
districts by hand — one email at a time whenever the file differs per
recipient. This app: holds the zone/division/district recipient directory,
imports ad-hoc recipient lists (CSV/XLSX/PDF), drafts mail-merge templates
with `{{variable}}` placeholders, and sends either one merged file to
everyone or a batch of distinct per-recipient files (zip, auto-matched by
filename with manual override) — through each HQ section's own Gmail SMTP
app password. System/auth mail (invites, login OTP) goes through Resend
(`noreply@mail.exciseup.in`), reusing the already-verified sending domain
from `~/Sites/pdf-markdown-pipeline` / `~/Sites/excise-budget-tracker`.

## Sibling apps this was built from

- `~/Sites/excise-budget-tracker` — source of the zones/divisions/districts
  schema + seed JSON (copied verbatim into `database/seeders/data/`), the
  password+OTP auth pattern, flat role+privileges RBAC, `activity_logs` audit
  table, Tailwind Play CDN + Tabler Icons UI shell.
- `~/Sites/pdf-markdown-pipeline` — source of the designation/scoping model,
  signed-URL invite pattern, same UI shell (non-Livewire reference), Apache
  vhost + `cloudflared` tunnel deploy pattern.

Don't diverge from these patterns without a reason — if a decision here looks
arbitrary, it's probably copied from one of these two; check there first.

## Domain model

| Table | Purpose |
|---|---|
| `zones` / `divisions` / `districts` | Fixed 5/18/75 org hierarchy, JC/DC/DEO name+email+CUG. Seeded via `GeoOrgSeeder` from `database/seeders/data/*.json` (copied from excise-budget-tracker, ultimately sourced from `~/Projects/excise-revenue-recovery-portal`'s contact CSVs). **Do not treat this as more authoritative than it is** — some districts may lack email/CUG; verify before a real send. |
| `sections` | HQ sections (e.g. Enforcement, Admin) — each holds users and one or more `mail_accounts`. |
| `mail_accounts` | Gmail address + `app_password` (encrypted cast) per section. `throttle_seconds` / `daily_send_cap` bound how fast a campaign sends. |
| `designations` | Job title + `default_privileges` preset, same shape as both sibling apps. |
| `users` | `role` (SuperAdmin/Admin/User) + `privileges` JSON, `designation_id`, `section_id`. `password` nullable until invite accepted. |
| `activity_logs` | Full audit trail — every non-GET authenticated request + login/logout, `ActivityLog::record()` (never throws). |
| `recipient_lists` / `recipient_list_items` | Ad-hoc imported recipient groups (CSV/XLSX/PDF), separate from the fixed zone/division/district directory. |
| `mail_templates` | Subject + HTML body with `{{variable}}` placeholders, rendered via `MailTemplate::render()`. |
| `campaigns` / `campaign_recipients` | One campaign = one send job. `recipient_scope` picks zones/divisions/districts/all/a recipient_list; `attachment_mode` is `single_file`, `zip_per_recipient`, or `none`. Each `campaign_recipients` row tracks its own send status independently. |

## Auth flow

1. Admin creates a user → `URL::temporarySignedRoute('invite.accept', ...)`
   emailed via Resend — this is the "magic link" account-creation step.
   Fortify's own routes stay `ignoreRoutes()`'d (must be called from
   `FortifyServiceProvider::register()`, not `boot()` — see
   excise-budget-tracker's `summary.md` M9 for the exploitable bug this
   avoids: `/register`, `/reset-password`, `/passkeys/*` reachable if done in
   `boot()`).
2. Invite-accept page sets password, activates the account.
3. Every login: email+password → 6-digit OTP (cached, not a DB column) →
   emailed via Resend → `/login/otp` verify → `Auth::login()` only on
   success. Rate-limit **both** the login attempt and the OTP-verify
   endpoint server-side (per email+IP) — a client-side resend cooldown alone
   is a UX nicety, not a security boundary (see
   `~/Projects/excise-revenue-recovery-portal/SECURITY.md` finding H-01).
4. RBAC: `User::hasPrivilege()` — SuperAdmin bypass, else check `privileges`
   JSON. `User::canUseMailAccount()` additionally restricts sending to a
   user's own section's mail accounts, unless SuperAdmin.

## Sending mail

Campaign mail uses a **dynamic per-account mailer**, built at send time from
the selected `mail_accounts` row (see `MailAccount::mailerConfig()`) —
credentials never sit in `.env` or a long-lived `config()` array outside the
request that's actually sending. Each `campaign_recipients` row becomes one
queued job (`database` queue), dispatched with a `delay()` staggered by
`mail_account.throttle_seconds * index` so a 75-recipient campaign trickles
out instead of bursting past Gmail's caps.

`zip_per_recipient` attachment matching: normalize both filenames and
recipient names (strip extension, slugify), try exact match then a
Levenshtein/fuzzy fallback, and always show a confirmation table before
allowing "Confirm & Queue" — auto-match is a convenience, not a silent
default.

## UI conventions

Tailwind Play CDN + Inter font + self-hosted Tabler Icons
(`public/vendor/tabler-icons`, copy from pdf-markdown-pipeline verbatim).
Blade layout shell at `resources/views/components/{head,sidebar,header,footer}.blade.php`.
Livewire 4 only for genuinely interactive flows (import wizard, campaign
builder, campaign status). Auth pages and admin CRUD stay plain Blade +
controllers — don't reach for Livewire where a controller redirect is enough.

## Known ceilings (deliberate, not oversights)

- PDF recipient import assumes a text layer (no OCR) — a scanned PDF won't parse.
- No per-user zone/division/district access scoping yet — any user with
  `campaigns.send` can target any recipient. If a role ever needs to be
  restricted to e.g. "only their own zone," look at
  `~/Sites/pdf-markdown-pipeline/MULTI_DEPARTMENT_SCOPE_PLAN.md`'s
  `hasDepartmentAccess()` pivot-table pattern before inventing a new one.
- `activity_logs` has no retention/pruning policy yet (pdf-markdown-pipeline's
  ROADMAP.md notes a 2-year default with CSV export before hard delete —
  worth the same here once the table has real volume).
