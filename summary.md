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
  `README.md`/`SECURITY.md`/this file written. **Not yet committed** — see
  "Next" below, first commit is the very next step.

**Not yet done — pick up here:**

1. `git add -A && git commit` for this scaffold (nothing committed yet).
2. Fortify config: `Fortify::ignoreRoutes()` in `FortifyServiceProvider::register()`
   (not `boot()` — see CLAUDE.md's auth-flow note on why).
3. Auth: invite-create controller (signed URL), invite-accept page (set
   password), `Auth\LoginController` (email+password → OTP → verify),
   `App\Mail\{Invite,LoginOtp}` mailables, rate limiters (login + OTP-verify,
   both server-side, keyed by email+IP) — copy the shape from
   `~/Sites/excise-budget-tracker/app/Http/Controllers/Auth/LoginController.php`
   and `app/Mail/LoginOtp.php`, adapt for the invite step which
   budget-tracker doesn't have (pdf-markdown-pipeline's signed-URL usage is
   the closer reference for that part).
4. `LogMutation` middleware + `Login`/`Logout` listeners writing
   `ActivityLog::record()` — copy from excise-budget-tracker's
   `AppServiceProvider` almost verbatim.
5. UI shell: copy `public/vendor/tabler-icons` and
   `resources/views/components/{head,sidebar,header,footer}.blade.php` from
   `~/Sites/pdf-markdown-pipeline`, re-theme/re-title for this app.
6. Admin CRUD (plain Blade + controllers, not Livewire): `/admin/users`,
   `/admin/designations`, `/admin/sections`, `/admin/mail-accounts`,
   `/admin/activity-logs`.
7. Livewire components: recipient-list import wizard (CSV/XLSX/PDF → column
   map → preview → save), campaign builder (scope picker → template →
   attachment mode → zip match-confirmation table → queue), campaign status
   page (live send progress).
8. `SendCampaignRecipientMail` job (dynamic mailer via
   `MailAccount::mailerConfig()`, staggered `delay()`), `CampaignMail`
   mailable.
9. Zip filename-matching logic (normalize + exact + fuzzy match) as a
   testable class, not inline in the Livewire component.
10. Pest tests per plan.md's Verification section.
11. Deploy: Apache vhost (port 8082), `cloudflared tunnel create
    up-excise-mailer`, `~/.cloudflared/mailer-config.yml`, systemd --user
    tunnel unit, `ReadWritePaths=` addition for the Apache sandbox override —
    write `DEPLOY.md` once this is actually done, following
    `~/Sites/pdf-markdown-pipeline/DEPLOY.md`'s structure.
