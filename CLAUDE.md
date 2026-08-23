# CLAUDE.md — UP Excise Mailer

Read [plan.md](./plan.md) first — it's the full approved build plan (context,
domain model, auth flow, sending design, UI, deploy). This file is the living
architecture reference; [summary.md](./summary.md) tracks what's actually
built vs. still pending. See [APP_FLOW.md](./APP_FLOW.md) for Mermaid
diagrams of auth, recipient-scope resolution, campaign send lifecycle,
authorization, and the component map. See [SECURITY.md](./SECURITY.md) for
the full audited security posture (production `.env` hardening, security
response headers/CSP, session config, Livewire upload auth, DB-transaction
atomicity convention) — re-run its checklist whenever a new write path or
upload surface is added. Keep all four updated as work progresses.

## What this app is

HQ currently emails files to the department's 5 zones / 18 divisions / 75
districts by hand — one email at a time whenever the file differs per
recipient. This app: holds the zone/division/district recipient directory,
imports ad-hoc recipient lists (CSV/XLSX/PDF), drafts mail-merge templates
with `{{variable}}` placeholders, and sends either one merged file to
everyone or a batch of distinct per-recipient files (zip, auto-matched by
filename with manual override) — through each HQ section's own Gmail SMTP
app password. System/auth mail (invites, login OTP) goes through Resend
(`noreply@mail.exciseup.in`) on an already-verified sending domain.

The auth pattern (password + OTP, flat role+privileges RBAC, signed-URL
invites), the zone/division/district schema, and the Tailwind Play CDN +
Tabler Icons UI shell follow conventions shared with this project's other
Laravel apps in the same deployment — worth knowing if a decision here looks
arbitrary, though every pattern used is documented here in full, not just
referenced.

## Domain model

| Table | Purpose |
|---|---|
| `zones` / `divisions` / `districts` | Fixed 5/18/75 org hierarchy, JC/DC/DEO name+email+CUG. Seeded via `GeoOrgSeeder` from `database/seeders/data/*.json`, sourced from the department's own contact lists. **Do not treat this as more authoritative than it is** — some districts may lack email/CUG; verify before a real send. |
| `sections` | HQ sections (e.g. Enforcement, Admin) — each holds users and one or more `mail_accounts`. |
| `mail_accounts` | Gmail address + `app_password` (encrypted cast) per section. `throttle_seconds` / `daily_send_cap` bound how fast a campaign sends. Optional `imap_host`/`imap_port` opt an account into reply fetching (see Sending mail below). |
| `designations` | Job title + `default_privileges` preset applied to new users on that designation. |
| `users` | `role` (SuperAdmin/Admin/User) + `privileges` JSON, `designation_id` (standard rank), `post` (free-text specific posting/charge, e.g. "Prevention & Enforcement" — distinct from `designation_id`, which is the standard rank), `section_id`. `password` nullable until invite accepted. |
| `activity_logs` | Full audit trail — every non-GET authenticated request + login/logout, `ActivityLog::record()` (never throws). |
| `recipient_lists` / `recipient_list_items` | Ad-hoc imported recipient groups (CSV/XLSX/PDF), separate from the fixed zone/division/district directory. |
| `mail_templates` | Subject + HTML body with `{{variable}}` placeholders, rendered via `MailTemplate::render()`. |
| `campaigns` / `campaign_recipients` | One campaign = one send job. `recipient_scope` picks zones/divisions/districts/all/a recipient_list; `attachment_mode` is `single_file`, `zip_per_recipient`, or `none`. Each `campaign_recipients` row tracks its own send status independently (`sent_at`/`failed_at` timestamps recorded separately, not inferred from `status` alone), plus the outgoing `message_id` used to match replies. `campaigns.slug` (random-suffixed, not the row id) is the route-binding key — `/campaigns/{campaign}` URLs never expose or let you enumerate the id. |
| `campaign_replies` | Inbound replies matched to a `campaign_recipients` row via IMAP header threading — see Sending mail below. |

## Auth flow

1. Admin creates a user → `URL::temporarySignedRoute('invite.accept', ...)`
   emailed via Resend — this is the "magic link" account-creation step.
   Fortify's own routes stay `ignoreRoutes()`'d, called from
   `FortifyServiceProvider::register()` — calling it from `boot()` instead
   would leave `/register`, `/reset-password`, `/passkeys/*` reachable
   unauthenticated, since routes registered before `register()` runs can't be
   suppressed by a call made in `boot()`.
2. Invite-accept page sets password, activates the account.
3. Every login: email+password → 6-digit OTP (cached, not a DB column) →
   emailed via Resend → `/login/otp` verify → `Auth::login()` only on
   success. Rate-limit **both** the login attempt and the OTP-verify
   endpoint server-side (per email+IP) — a client-side resend cooldown alone
   is a UX nicety, not a security boundary.
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

### Reply fetching (IMAP)

An account with `mail_accounts.imap_host` set opts into reply fetching —
`MailAccount::imapConfig()` builds a `webklex/php-imap` client from the same
credentials already used for SMTP (Gmail app passwords and NIC's mGovCloud
password both authorize IMAP too). `SendCampaignRecipientMail` captures the
outgoing `Message-ID` onto `campaign_recipients.message_id` at send time.
The "Check for replies" button on `/campaigns/{campaign}` runs
`ImapReplyFetcher::fetch()`: one `SINCE`-bounded IMAP query against INBOX,
then an in-memory match of each fetched message's `In-Reply-To`/`References`
headers against this account's known outgoing `message_id`s. Only threaded
matches are saved, as `campaign_replies` rows linked to the matching
recipient — the rest of the inbox is read-only to this app (`leaveUnread()`
never marks anything seen) and never touched. This is manual-trigger only;
there's no scheduler wired up on this host to poll automatically.

## Security

Full audited posture lives in [SECURITY.md](./SECURITY.md) — don't duplicate it here beyond the
load-bearing rules:

- Production `.env` must stay `APP_ENV=production`/`APP_DEBUG=false` — `true` dumps `APP_KEY`/
  `DB_PASSWORD`/`RESEND_API_KEY` on any unhandled exception. `SESSION_ENCRYPT`/
  `SESSION_SECURE_COOKIE`/`SESSION_SAME_SITE=strict` must all stay on for the same reason
  (`SESSION_DRIVER=database` means the login OTP itself sits in the `sessions` table).
- `App\Http\Middleware\SecurityHeaders` (global, `bootstrap/app.php`) sets CSP/X-Frame-Options/
  HSTS/etc. on every response — if a new CDN is ever added to a Blade view, add its host to the
  CSP's `script-src`/`style-src` there too, or the browser silently blocks it.
- Any new Livewire component using `WithFileUploads` gets its own `abort_unless(hasPrivilege(...))`
  inside every action method, not just relied on via its mounting route's middleware — Livewire's
  `livewire/update` endpoint isn't covered by the page route's own middleware (see `CampaignBuilder`,
  `OfficerDirectoryImportWizard`, `RecipientListImportWizard` for the existing pattern).
- `DB::transaction()` + `try`/`catch` is for **multi-step** writes that must succeed/fail
  together (e.g. a campaign + its recipient rows, a recipient's status flip + the campaign's
  completion check) — not every single-statement `Model::create()`/`update()`/`delete()`, which
  is already atomic on its own. Don't wrap trivial CRUD in a transaction just for the sake of it.

## UI conventions

Tailwind Play CDN + Inter font + self-hosted Tabler Icons
(`public/vendor/tabler-icons`). Blade layout shell at
`resources/views/components/{head,sidebar,header,footer}.blade.php`.

**This app is Livewire-first by design, not core Laravel with Livewire bolted on** — any page
with per-row actions, filters, search, or anything that would otherwise reload the page belongs
in a full-page Livewire component (`#[Layout(...)]` or `->layout(...)` on the return of
`render()`, mounted directly off a route — see `CampaignBuilder`, `TestEmailSender`,
`CampaignShow`), not a controller + plain Blade form. A plain `<form method="POST">` always does
a real browser round-trip regardless of anything else on the page (including `wire:navigate`,
which only intercepts GET link clicks) — every one of those round-trips on an otherwise-Livewire
page is a bug to fix, not an acceptable default. Plain controllers remain fine for the auth flow
(login/OTP/onboarding — no interactivity to speak of) and for a handful of simple one-shot
redirects (`prefillTestSend`, file exports); admin CRUD (sections/mail accounts/designations/
users) should move to Livewire components as those pages are next touched, following the pattern
above, rather than staying plain Blade by default.

## Known ceilings (deliberate, not oversights)

- PDF recipient import assumes a text layer (no OCR) — a scanned PDF won't parse.
- No per-user zone/division/district access scoping yet — any user with
  `campaigns.send` can target any recipient. If a role ever needs to be
  restricted to e.g. "only their own zone," a `user_id`/`scope_type`/
  `scope_id` pivot table checked via a `hasDepartmentAccess()`-style method
  on `User` is the natural shape, added alongside the existing privilege
  checks rather than replacing them.
- `activity_logs` has no retention/pruning policy yet — worth a scheduled
  command that exports rows past some age (e.g. 2 years) to CSV before hard
  deleting them, once the table has real volume.
