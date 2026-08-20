# UP-excise-mailer — mail-merge & bulk emailer for UP Excise Dept

## Context

HQ currently mails the same or per-recipient files to 5 zones / 18 divisions / 75
districts by hand, one email at a time, whenever the file differs per recipient. This
app gives HQ sections a single place to: hold the zone/division/district recipient
directory, import ad-hoc recipient lists (CSV/XLSX/PDF), draft mail-merge templates
with variables, and send either one merged file to everyone or a batch of distinct
per-recipient files (auto-matched from a zip, with manual override) — through each
section's own Gmail SMTP (Google One paid seat, app password), while auth/OTP/admin
notification mail goes through the already-provisioned Resend sender
`noreply@mail.exciseup.in`.

Reuses proven patterns from two sibling Laravel apps rather than inventing new ones:
`~/Sites/excise-budget-tracker` (Livewire, Fortify-routes-ignored + hand-rolled
password+OTP login, flat role/privileges RBAC, `activity_logs` audit table, and —
critically — the **already-seeded** zones/divisions/districts JSON with JC/DC/DEO
name+email+CUG, sourced from `~/Projects/excise-revenue-recovery-portal`) and
`~/Sites/pdf-markdown-pipeline` (designation/org-scope model, magic-link-style signed
invite pattern via `URL::temporarySignedRoute`, Tailwind Play CDN + self-hosted Tabler
Icons UI shell, Apache-vhost + named `cloudflared` tunnel deploy pattern).

Confirmed decisions from user: **cloudflared** (not wrangler — not installed, doesn't
do tunnels) for the `mailer.exciseup.in` tunnel; Gmail creds are **per HQ section**
(shared, not per-user); per-recipient file matching supports **both** zip
auto-match-by-filename and manual override in one confirmation table; account creation
is **admin-invite only** (magic link to set password, then password+OTP for every
login after); sends are **queued and throttled** per mail account.

## Domain model (new migrations)

Reuse budget-tracker's exact `zones`/`divisions`/`districts` tables + its seeded
`database/seeders/data/{zones,divisions,districts}.json` verbatim (copy the JSON,
adapt the seeder) — this is already the cleaned join of JC/DC/DEO name/email/CUG for
5/18/75, no need to re-derive from the raw CSVs.

New tables:
- `sections` — id, name, slug, timestamps. (HQ sections, e.g. "Enforcement", "Admin")
- `mail_accounts` — id, section_id FK, gmail_address, app_password (encrypted cast),
  smtp_host default `smtp.gmail.com`, smtp_port default `587`, throttle_seconds
  (default e.g. 4), daily_send_cap nullable, is_active, timestamps.
- `designations` — id, name, slug, sort_order, timestamps (same shape as both
  reference repos).
- `users` — id, name, email(unique), username(unique), mobile, password nullable
  (null until invite accepted), role (SuperAdmin/Admin/User), designation_id FK
  null-on-delete, section_id FK null-on-delete, remember_token, timestamps, soft
  deletes. Login OTP stored ephemerally (cache, keyed by user id, TTL) exactly like
  budget-tracker's `LoginOtp` — no DB column needed.
- `activity_logs` — copy budget-tracker's schema verbatim (user_id, action,
  ip_address, user_agent, metadata json, created_at) + global `LogMutation`
  middleware + auth event listeners, same as reference.
- `recipient_lists` — id, name, source_type (csv/xlsx/pdf/manual), uploaded_by FK,
  original_filename, timestamps. (ad-hoc imported groups, distinct from the fixed
  zone/division/district directory)
- `recipient_list_items` — id, recipient_list_id FK cascade, name, email,
  extra json (any other parsed columns/placeholders), timestamps.
- `mail_templates` — id, name, subject, body (HTML w/ `{{variable}}` placeholders),
  variables json, created_by FK, timestamps.
- `campaigns` — id, name, mail_account_id FK, template_id nullable FK,
  recipient_scope enum(all/zones/divisions/districts/recipient_list),
  attachment_mode enum(single_file/zip_per_recipient/none), status
  enum(draft/queued/sending/completed/failed), created_by FK, sent_at nullable,
  timestamps.
- `campaign_recipients` — id, campaign_id FK cascade, recipient_type
  enum(zone/division/district/list_item), recipient_ref_id nullable (FK-less, points
  at zones/divisions/districts/recipient_list_items by type), name, email,
  attachment_path nullable, matched_via enum(filename_auto/manual/none) nullable,
  status enum(pending/queued/sent/failed), error_message nullable, sent_at nullable,
  timestamps.

## Auth flow

- Admin creates a user (`POST /admin/users`, name/email/mobile/designation/section/role)
  → `URL::temporarySignedRoute('invite.accept', now()->addDays(3), ['user' => $id])`
  emailed via Resend (`noreply@mail.exciseup.in`) — this is the "magic link" account
  creation step (Fortify's own routes stay `ignoreRoutes()`'d, same as budget-tracker;
  the invite is a hand-rolled signed-URL controller, matching pdf-markdown-pipeline's
  signed-URL usage elsewhere).
- Invite-accept page: user sets password (validated), account activated.
- Every login thereafter: email+password (`Auth::guard('web')->validate()`) → 6-digit
  OTP generated, cached, emailed via Resend → `/login/otp` verify page → `Auth::login()`
  only on success. Copy budget-tracker's `Auth\LoginController` + `App\Mail\LoginOtp` +
  rate limiters (`login`, `two-factor`) near-verbatim.
- RBAC: reuse budget-tracker's flat `role` + `privileges` JSON + `HasPrivilege`
  middleware — SuperAdmin bypass, `privilege:{x}` gate checks. Add a `section`-scoped
  check where relevant (e.g. a User can only pick mail_accounts belonging to their own
  section, unless SuperAdmin).

## Gmail SMTP sending (dynamic, per section)

At send time, build a runtime mailer config from the selected `mail_accounts` row:
```php
config(['mail.mailers.dynamic' => [
    'transport' => 'smtp', 'host' => $account->smtp_host, 'port' => $account->smtp_port,
    'encryption' => 'tls', 'username' => $account->gmail_address,
    'password' => decrypt($account->app_password),
]]);
Mail::mailer('dynamic')->to($recipientEmail)->send(new CampaignMail($campaign, $recipient));
```
`app_password` uses Laravel's `encrypted` cast (APP_KEY-based, no new secret to manage).

Sending is queued (`database` queue driver, matching both reference apps) via a
`SendCampaignRecipientMail` job per `campaign_recipients` row, dispatched with a
staggered `delay()` computed from `mail_account.throttle_seconds * index` so a
75-recipient campaign trickles out instead of bursting — avoids Gmail's daily/burst
caps. Job updates `campaign_recipients.status`/`error_message`/`sent_at`; a campaign
"status" page polls/Livewire-polls for progress.

## Recipient scope + file matching

Campaign builder (Livewire component) lets the user pick a scope (all zones/divisions/
districts, a subset, or an imported `recipient_list`), then an attachment mode:
- **single_file**: one file (or the template's merge fields alone), same content/
  attachment to every recipient — variables like `{{district_name}}`, `{{deo_name}}`
  interpolated per recipient into the template body/subject.
- **zip_per_recipient**: upload a zip; server extracts, normalizes each filename
  (strip extension, slugify) and each recipient name the same way, auto-matches
  (exact then fuzzy/Levenshtein fallback), and renders a confirmation table
  (recipient | matched file | status) where any unmatched or wrong match can be
  manually reassigned via a dropdown before "Confirm & Queue" is enabled.

## Import (CSV/XLSX/PDF)

- CSV/XLSX: `openspout/openspout` (lightweight, no extra native deps beyond what
  composer pulls) reading rows → column-mapping step (map columns to name/email/extra)
  → preview → save as a new `recipient_list` + `recipient_list_items`.
- PDF: `smalot/pdfparser` to extract text, regex-extract `name <email>`-shaped or
  tabular rows; same column-mapping/preview/save flow. (No OCR — these are expected to
  be text-layer PDFs, e.g. exported contact sheets; note this as a known ceiling.)

## UI

Tailwind Play CDN + Inter font + self-hosted Tabler Icons (`public/vendor/tabler-icons`,
copy from pdf-markdown-pipeline), `resources/views/components/{head,sidebar,header,
footer}.blade.php` layout shell copied and re-themed (same nav-link/active classes).
Livewire 4 (per user's requested stack) for the genuinely-interactive flows: import
wizard (upload → column-map → preview), campaign builder (scope picker → template
variable insertion → attachment mode → zip match-confirmation table → queue), campaign
status/progress view. Everything else (auth pages, admin CRUD for users/sections/mail
accounts/designations) stays plain Blade + controllers, matching how budget-tracker
itself kept auth non-Livewire.

## Deploy / infra

- `git init` in `~/Sites/UP-excise-mailer`, initial commit after scaffold.
- `subhanraj/laravel-db-provisioner` (`composer require --dev`) → `php artisan
  db:provision` for a fresh MariaDB DB+user, then `migrate` + seed zones/divisions/
  districts/designations.
- Apache vhost `up-excise-mailer.conf`, port **8082** (8080/8081 taken), copied from
  `pdf-markdown-pipeline.conf` pattern (`ServerName mailer.exciseup.in`, DocumentRoot
  `.../public`); add its `storage`/`bootstrap/cache` paths to the systemd
  `apache2.service.d/override.conf` `ReadWritePaths=` (documented gotcha from
  budget-tracker's own deploy notes).
- `cloudflared tunnel create up-excise-mailer` → `~/.cloudflared/mailer-config.yml`
  (ingress `mailer.exciseup.in → http://127.0.0.1:8082`, 404 catchall) → DNS route →
  systemd --user unit `mailer-tunnel.service` (copy budget-tracker-tunnel's unit,
  swap paths), `systemctl --user enable --now`.
- `.env`: `MAIL_MAILER=resend` default (auth/system mail), `RESEND_API_KEY`,
  `RESEND_FROM=noreply@mail.exciseup.in`; per-section Gmail creds live in DB
  (`mail_accounts`), not `.env`.

## Plan file

First file committed to the new repo is `plan.md` at its root — a copy of this plan,
so the build record travels with the code instead of staying only in
`~/.claude/plans/`.

## Docs to write

`README.md` (setup/run, matching pdf-markdown-pipeline's structure), `CLAUDE.md`
(architecture, tech stack, domain model — matching both repos' CLAUDE.md depth),
`DEPLOY.md` (vhost/tunnel/systemd steps as above), `SECURITY.md` (encrypted app
passwords, OTP/rate-limiting, audit log, signed-URL invite expiry, RBAC — matching
both repos' SECURITY.md), `summary.md` (running build log, same convention as both
repos).

## Verification

- `php artisan test` (Pest, matching pdf-markdown-pipeline) covering: invite→
  set-password→login→OTP flow; RBAC middleware denies cross-section mail_account use;
  zip filename-matching (exact + fuzzy + unmatched) logic; queued-send throttling
  produces correctly staggered delays; CSV/XLSX/PDF import → preview → save round-trip.
- Manual: `php artisan serve` locally, walk through admin-invite → login → create a
  test mail_account (a real Gmail app password) → build a campaign against a handful
  of test districts → confirm delivered mail and `campaign_recipients` status updates.
- Post-deploy: hit `https://mailer.exciseup.in` through the tunnel, confirm vhost +
  tunnel + `ReadWritePaths` are all correctly wired (matches the exact failure modes
  documented in budget-tracker's own deploy history).
