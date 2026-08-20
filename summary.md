# Build summary — UP Excise Mailer

Running log of what's actually built, in build order. See [plan.md](./plan.md)
for the full approved plan and [CLAUDE.md](./CLAUDE.md) for architecture.

## M1 — Scaffold, schema, models, seed data (2026-08-20, done)

- `composer create-project laravel/laravel` (Laravel 13, PHP 8.5).
- Installed: `laravel/fortify`, `livewire/livewire`, `resend/resend-php`,
  `php-flasher/flasher-laravel`, `subhanraj/laravel-db-provisioner` (dev),
  `openspout/openspout`, `smalot/pdfparser`.
- MariaDB provisioned manually (the package's interactive admin-credential
  prompt needed a real root-equivalent login — `admin`/[provided by user],
  not blank-password root) as `up_excise_mailer_local` /
  `up_excise_mailer_local`. Credentials are in `.env` (gitignored, not
  committed).
- All 13 migrations written and verified (`php artisan migrate:fresh --seed`
  ran clean): `users` (extended: username, mobile, role, designation_id,
  section_id, privileges, soft deletes, nullable password),
  `designations`, `sections`, `activity_logs`, `zones`, `divisions`,
  `districts`, `mail_accounts`, `recipient_lists`, `recipient_list_items`,
  `mail_templates`, `campaigns`, `campaign_recipients`.
- All 12 Eloquent models written (`app/Models/`), matching migrations 1:1.
  `User` has `hasPrivilege()`, `canUseMailAccount()`, `uniqueUsername()`.
  `MailAccount` has `mailerConfig()` (builds a dynamic per-account SMTP
  config array, `app_password` via `encrypted` cast + `#[Hidden]`).
  `MailTemplate` has `render()` (`{{variable}}` placeholder substitution).
- `database/seeders/data/{zones,divisions,districts}.json` copied verbatim
  from `~/Sites/excise-budget-tracker` (already-cleaned 5/18/75 org
  hierarchy with JC/DC/DEO name+email+CUG). `GeoOrgSeeder` ported unchanged.
  `DesignationSeeder` rewritten with this app's own designation ladder +
  `User::PRIVILEGES` set (`users.manage`, `sections.manage`,
  `mail-accounts.manage`, `templates.manage`, `campaigns.send`,
  `recipients.import`, `activity-logs.view`). Verified seed counts: 5 zones,
  18 divisions, 75 districts, 9 designations, 1 SuperAdmin user.
- `git init` done, `.env` confirmed excluded, `plan.md`/`CLAUDE.md`/
  `README.md`/`SECURITY.md`/this file written and committed
  (`929aeab`).

## M2 — Auth flow + real UI shell (2026-08-20, done)

- `FortifyServiceProvider::register()` calls `Fortify::ignoreRoutes()`
  (confirmed via `route:list` — no `/register`, `/passkeys/*`, etc. leaked).
- Admin-invite flow: `Auth\OnboardingController` (signed URL, 72h, single-use
  — gated on `email_verified_at`), `App\Mail\AccountOnboarding`.
- Login flow: `Auth\LoginController` (email+password → 6-digit OTP cached in
  session, not DB → `/login/otp` verify → `Auth::login()`), `App\Mail\LoginOtp`.
  Both ported near-verbatim from excise-budget-tracker.
- Rate limiters (`login`, `two-factor`, both email+IP AND IP-alone) and
  activity-log `Login`/`Logout` listeners wired in `AppServiceProvider`.
- UI shell copied from excise-budget-tracker/pdf-markdown-pipeline and
  re-themed: `public/vendor/tabler-icons` (4.2M, self-hosted),
  `resources/views/components/{head,layout,sidebar,header,footer}.blade.php`
  (dark mode, collapsible sidebar, live clock — money-unit-switcher/
  chart.js/exceljs pieces dropped, budget-tracker-specific). Auth pages
  (`login`, `otp`, `onboarding`) use the same Tabler-icon card style, not
  the full sidebar shell (matches sibling apps — auth is deliberately
  chromeless).
- Dashboard is a real Livewire component (`App\Livewire\Dashboard` +
  `resources/views/livewire/dashboard.blade.php`), per the requested stack —
  stat cards (zone/division/district/mail-account counts) + recent
  campaigns table (empty state for now).
- Committed (`3d8983d`).

**Sidebar nav items beyond Dashboard are still `href="#"` placeholders** —
Campaigns, Templates, Recipients, Recipient Lists, Mail Accounts, Sections,
Users, Designations, Activity Log. Nothing 404s, they just don't go
anywhere yet. This is the very next thing to fix — see M4 below.

## M3 — Live deploy to mailer.exciseup.in (2026-08-20, done)

- `cloudflared tunnel create up-excise-mailer` (id
  `REDACTED-TUNNEL-ID`), `~/.cloudflared/mailer-config.yml`
  (ingress → `http://127.0.0.1:8000`, i.e. straight at `artisan serve` —
  **no Apache vhost yet**, see "Not yet done" #11 below, that needs `sudo`
  which this session didn't have).
- **Caught and fixed a real bug**: `cloudflared tunnel route dns
  up-excise-mailer mailer.exciseup.in` initially created the CNAME pointing
  at pdf-markdown-pipeline's tunnel ID instead (confirmed via a live 404
  before our tunnel was even running). Fixed with
  `cloudflared tunnel route dns --overwrite-dns <correct-uuid> mailer.exciseup.in`.
  **If this domain ever misbehaves, check the CNAME target first** — this
  class of bug can recur if the tunnel is ever recreated.
- `APP_URL=https://mailer.exciseup.in` in `.env`.
- Hit and fixed the standard Cloudflare Tunnel gotcha: signed URLs (the
  invite link) were generating/validating with `http://` instead of
  `https://` behind the tunnel's plain-HTTP loopback hop. Fixed with
  `$middleware->trustProxies(at: ['127.0.0.1'])` in `bootstrap/app.php` —
  same fix both sibling apps already carry. Committed separately after
  `3d8983d`.
- `~/.config/systemd/user/up-excise-mailer-tunnel.service` created,
  enabled, and running (`systemctl --user is-active` → `active`) — survives
  logout/reboot. `php artisan serve` and `php artisan queue:work` are
  **still manually started (nohup), not yet systemd units** — see "Not yet
  done" #11.
- End-to-end verified: invite email sent to redacted-personal-email@example.com via
  Resend, onboarding link works through the real domain, login page loads
  at `https://mailer.exciseup.in/login`.

**Not yet done — pick up here, in order:**

1. **Wire up every sidebar nav item** (currently `#`) to a real route +
   controller/view or Livewire component, even a minimal one — this was
   flagged directly by the user as broken/confusing. Suggested order,
   cheapest-and-most-blocking first:
   - `/admin/sections` — plain CRUD (name/slug only, ~3 fields).
   - `/admin/mail-accounts` — CRUD scoped by section
     (`User::canUseMailAccount()` already exists on the model), form fields
     match the `mail_accounts` migration; **never echo `app_password` back
     into a form value** — write-only field, same pattern as any password
     field.
   - `/admin/designations` — CRUD, mirror
     `~/Sites/excise-budget-tracker/app/Http/Controllers/Admin/*`
     (`DesignationController` there, if it exists — check; otherwise
     `DesignationSeeder`'s shape is already known).
   - `/admin/users` — index/create/edit; **create should call the existing
     `Auth\OnboardingController`'s invite pattern** (create user with
     `password = null`, `email_verified_at = null`, send
     `AccountOnboarding` mail via signed route) — this logic currently only
     exists inline in the tinker one-off used to invite the SuperAdmin
     during M3, pull it into a real
     `Admin\UserManagementController@store` (see
     `~/Sites/excise-budget-tracker/app/Http/Controllers/Admin/UserManagementController.php`
     for the exact shape to copy, including the "mail failure shouldn't
     fail account creation" try/catch).
   - `/admin/activity-logs` — read-only paginated index, `ActivityLog`
     model already has everything needed.
   - `/recipients` — read-only browse of zones/divisions/districts
     (tabs or a combined table), no write actions needed — this is just
     surfacing `GeoOrgSeeder`'s data.
   - `/templates` — CRUD for `mail_templates` (`MailTemplate::render()`
     already implemented — a live preview using it would be a nice-to-have,
     not required for first cut).
   - `/recipient-lists` — CSV/XLSX/PDF import wizard, **the first genuinely
     Livewire-shaped piece** (multi-step: upload → column-map → preview →
     save). `openspout/openspout` and `smalot/pdfparser` are already
     installed, nothing built against them yet.
   - `/campaigns` — index first (list `campaigns` table, empty state is
     fine initially), then the actual builder (scope picker → template →
     attachment mode → zip match-confirmation table → queue) — this is the
     biggest remaining piece, do it last once everything it depends on
     (mail accounts, templates, recipient lists) exists.
2. `SendCampaignRecipientMail` job (dynamic mailer via
   `MailAccount::mailerConfig()`, staggered `delay()`), `CampaignMail`
   mailable — needed once the campaign builder can actually queue a send.
3. Zip filename-matching logic (normalize + exact + fuzzy match) as a
   testable class, not inline in a Livewire component.
4. Pest tests per plan.md's Verification section — none written yet.
5. **Apache vhost on port 8082** — needs `sudo` (this session didn't have
   it; ask the user for it directly, or have them run the vhost-creation
   commands themselves). Once done: switch `~/.cloudflared/mailer-config.yml`
   ingress from `http://127.0.0.1:8000` to `http://127.0.0.1:8082`, add
   `storage`/`bootstrap/cache` to the Apache sandbox override's
   `ReadWritePaths=` (same gotcha budget-tracker hit), convert
   `php artisan serve`/`queue:work` from manual nohup processes to systemd
   `--user` units (see `pdf-pipeline-queue.service`/`pdf-pipeline-queue2.service`
   for the pattern), write `DEPLOY.md`.
