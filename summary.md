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
  logout/reboot. **Update 2026-08-20: `php artisan serve` and both queue
  workers are also now systemd `--user` units** (see the dated section
  below) — nothing in this app runs as an unsupervised manual process
  anymore.
- End-to-end verified: invite email sent to redacted-personal-email@example.com via
  Resend, onboarding link works through the real domain, login page loads
  at `https://mailer.exciseup.in/login`.

## M4 — Every sidebar nav item wired to a real page (2026-08-20, done)

All nine placeholder `href="#"` links now route to real controllers/views —
no more dead nav.

- `/admin/sections` — plain CRUD; `destroy()` refuses to delete a section
  that still has users or mail accounts attached (mail_accounts cascades on
  delete at the DB level, so this guard avoids a silent mass-delete).
- `/admin/mail-accounts` — CRUD scoped by section; `app_password` is
  write-only (`update()` unsets it from the validated array when the edit
  form field is left blank, never echoed back into `value=`). Added a
  client-side-only "Provider" select (Gmail / Custom SMTP) that just
  prefills `smtp_host`/`smtp_port` and swaps field labels — no schema
  change, credentials stay DB-encrypted per `MailAccount::mailerConfig()`,
  matching CLAUDE.md's "never in .env" design (user confirmed this scope
  explicitly after asking about it mid-session).
- `/admin/designations`, `/admin/users`, `/admin/activity-logs` — ported
  near-verbatim from `~/Sites/excise-budget-tracker/app/Http/Controllers/Admin/*`,
  adapted for this app's 3-way `role` (SuperAdmin/Admin/User vs.
  budget-tracker's SuperAdmin/Executive) and added `section_id` to the user
  form. User creation reuses the existing onboarding-link flow (mail-failure
  doesn't fail account creation, same try/catch as the reference).
- `resources/views/components/breadcrumb.blade.php` and
  `.field-err-msg` (in `head.blade.php`) copied over — both were used by
  every reference view but didn't exist here yet.
- Registered `is_admin` / `privilege:{x}` middleware aliases
  (`app/Http/Middleware/{IsAdmin,HasPrivilege}.php`, aliased in
  `bootstrap/app.php`) and a `mutations` rate limiter — none of this existed
  since M2 only wired login/logout activity logging, not the admin-CRUD
  gates.
- `/recipients` — read-only tabbed browse of zones/divisions/districts,
  flags districts with a missing AEC (DEO) email (CLAUDE.md's documented
  data-quality ceiling). **Also added edit forms** (SuperAdmin-only) for
  zone/division/district contact info — name + officer name/email/CUG,
  plain UTF-8 text inputs (MySQL is utf8mb4 by default in Laravel 13, so
  Hindi input needs no extra handling). Officer title labels corrected
  from JC/DC/DEO to **JEC/DEC/AEC (DEO)** per user correction — display
  labels only, DB columns stay `jc_*`/`dc_*`/`deo_*` (renaming working
  columns for a label change isn't worth the migration).
- `/templates` — CRUD for `mail_templates`, gated by `templates.manage`.
  Auto-extracts `{{variable}}` names from subject+body into the `variables`
  json column on save (`MailTemplateController::extractVariables()`) so the
  future campaign builder can list them without re-parsing.
  **Hit and fixed a real bug**: writing `{{ '{{'.$var.'}}' }}` inside a Blade
  `{{ }}` echo breaks the compiler — Blade's tokenizer finds the *first*
  `}}` it sees, which lands inside the string literal itself, truncating the
  expression mid-string and producing a PHP `ParseError` ("Unclosed '('")
  at runtime. Fixed by using Blade's `@{{ ... }}` literal-brace escape for
  static example text, and a `@php()`-computed variable for the dynamic
  case in the index table. Body field is now a **Quill** WYSIWYG editor
  (`resources/views/templates/_editor.blade.php`) instead of a raw HTML
  textarea — MIT-licensed, CDN-loaded (`cdn.jsdelivr.net/npm/quill@2`), no
  new composer/npm dependency, matches this app's no-build-step convention
  (Tailwind Play CDN). A hidden `textarea[name=body]` is synced from Quill's
  `innerHTML` on `text-change` and on form submit; `{{variable}}` is typed
  as plain text and passes through untouched into the stored HTML. **If you
  ever need to display a literal `{{...}}` placeholder in a Blade template,
  use `@{{ }}` for static text.** (An earlier version of this note said
  "or precompute the string outside the echo via `@php()`" — that's wrong,
  see the follow-up fix below; corrected here so this note doesn't mislead
  again.)
- `/recipient-lists` — the import wizard, Livewire-shaped as planned:
  `app/Services/RecipientImportParser.php` (CSV/XLSX via
  `openspout/openspout`, PDF via `smalot/pdfparser` + regex extraction of
  `name <email>` pairs) feeds `App\Livewire\RecipientListImportWizard`
  (3 steps: upload+name → column mapping → preview & save). PDF skips the
  mapping step since extraction already yields name/email pairs directly.
  List browsing (`recipient-lists.index`/`.show`) is plain Blade.
- Real HQ section names seeded: `database/seeders/SectionSeeder.php` copies
  the 13-section list (11 HQ sections + 2 Secretariat wings) from
  `~/Sites/pdf-markdown-pipeline/database/seeders/SectionSeeder.php` — that
  app models department/wing structure this one doesn't need, so just the
  names carried over. Wired into `DatabaseSeeder` and run against the live DB.
- Fixed two more copy-paste-from-budget-tracker leftovers, both reported
  directly by the user: the favicon set (`public/favicon*`, `icon-*.png`,
  `apple-touch-icon.png`) was still budget-tracker's rupee icon — regenerated
  as a plain indigo/white envelope glyph via GD (`imagecreatetruecolor` +
  `imagerectangle`/`imageline`, no ImageMagick available on this box);
  `site.webmanifest`'s `name`/`short_name` still said "Budget Tracker".
  Also fixed icon/placeholder overlap and added password show/hide toggles
  on `auth/login.blade.php` and `auth/onboarding.blade.php` — same bug and
  same fix already applied in both sibling apps: the shared `.field-input`
  CSS class's `px-3` conflicts with a `pl-9`/`pr-10` override added via a
  plain HTML class list (Tailwind Play CDN's generated-CSS order doesn't
  respect HTML class order), so icon-adorned auth fields need their utility
  classes written out in full inline instead of layering onto `.field-input`.

## M5 — Campaign builder, end to end (2026-08-20, done)

The last "coming soon" placeholder is gone — `/campaigns/create` is a real
4-step Livewire wizard (`App\Livewire\CampaignBuilder` +
`resources/views/livewire/campaign-builder.blade.php`) that actually queues
mail:

1. **Who** — campaign name, mail account (scoped to the user's section
   unless SuperAdmin, reusing `User::canUseMailAccount()`), and a recipient
   picker: Zones / Divisions / Districts (checkbox lists, "Select All"
   button) or "A List I Imported" (an existing `recipient_list`). Only
   recipients with a valid email on file are counted/sent to — the same
   data-quality ceiling as `/recipients`.
2. **What to Say** — pick a saved template (autofills subject/body) or
   write one from scratch; "+ New Template" opens an inline quick-create
   panel (plain textarea, not the full Quill editor — deliberately lighter
   than `/templates`, which stays the place for polished authoring) so the
   flow never dead-ends into a separate CRUD page. A row of variable chips
   (**plain words, no underscores** — `district`, `division`, `zone`,
   `officer`, `cug`, or the recipient list's own column names for
   `recipient_list` scope — swapped in from `jc_/dc_/deo_name` etc. at
   render time) both appends to Subject (Livewire round-trip) and inserts
   at the cursor in the Quill body editor (`window.bodyQuill.insertText()`,
   exposed by the new `resources/views/livewire/_quill-editor.blade.php`
   partial). Body syncs to a `wire:model`-bound hidden textarea on
   `text-change`; the whole Quill container is `wire:ignore`d so unrelated
   Livewire re-renders (e.g. typing in Subject) don't clobber the editor's
   live DOM.
3. **Files** — "Do you want to attach any files?" Yes/No, then "One file
   for everyone" (`single_file`) or "A different file per recipient"
   (`zip_per_recipient`, upload a zip → `ZipArchive::extractTo()` →
   `App\Services\ZipRecipientMatcher` auto-matches by normalized filename
   with a Levenshtein fallback, each result editable via a per-row
   `<select>` before continuing — auto-match is a convenience, never a
   silent default, per plan.md).
4. **Review & Send** — plain-language summary (recipient count, sender,
   attachment choice, rendered subject/body preview), "Confirm & Send"
   creates the `Campaign` + one `CampaignRecipient` row per recipient
   (subject/body rendered per-recipient via `MailTemplate::render()` before
   dispatch) and queues `App\Jobs\SendCampaignRecipientMail` per row,
   staggered by `mail_account.throttle_seconds * index` exactly as
   plan.md's sending design specifies. `App\Mail\CampaignMail` builds the
   actual message (HTML body + optional attachment) through the dynamic
   `mail.mailers.dynamic` config built fresh from `MailAccount::mailerConfig()`
   — credentials still never touch `.env` or a long-lived config array.
   `/campaigns/{campaign}` is a new read-only status page (waiting/sent/
   failed counts + a per-recipient table with error messages).
- `ZipRecipientMatcher` (normalize → exact → Levenshtein-fallback within a
  length-proportional threshold, each filename used once) is a plain
  testable class per plan.md, not inline in the Livewire component —
  `tests/Unit/ZipRecipientMatcherTest.php` covers exact/fuzzy/no-match/
  filename-reuse. A full-flow Feature test (zone scope → template →
  attachments → review → queue, asserting the `campaigns`/
  `campaign_recipients` rows land correctly) was run manually to verify
  the wizard end to end and then removed — worth re-adding as a permanent
  test once Pest/PHPUnit conventions for this app are settled (see item 3
  below).
- Removed the last two "internal/technical" leaks a layman user would
  trip on, both flagged directly by the user: the dashboard's empty-state
  copy referencing `summary.md` and "campaign builder... roadmap" (now
  just "No campaigns sent yet"), and the Recipients/Campaigns tables'
  `snake_case`/raw-enum values (`recipient_list`, `sending`, `queued`) —
  now mapped to plain labels ("Imported List", "Sending", "Draft") in the
  view layer. Template variables were similarly renamed from
  `jc_name`/`aec_email` style to single plain words (`officer`, `cug`)
  for the same reason — the underscore/scope-prefix convention was for
  developers, not the officers actually writing these emails.

### Campaign builder follow-ups (2026-08-20, done)

- Divisions and Districts checkbox lists were missing the "Select All"
  toggle that Zones already had — `selectAllDivisions()`/
  `selectAllDistricts()` existed on `CampaignBuilder` since M5 but were
  never wired into the view. Fixed.
- Added a 5th scope: **Everyone** (`scope = 'all'`) — every district's
  officer, department-wide, no picking required. Maps to the `all` value
  plan.md's `recipient_scope` enum always listed but nothing implemented
  until now.
- Renamed the recipient-list scope button from "A List I Imported" to
  **"Imported List"** — the original wrapped awkwardly in the 2/4-column
  grid on narrow screens and read as "A List | Imported", which is what
  prompted the question. `campaigns/index.blade.php`'s scope-label map
  updated to match ("Everyone" instead of the old default "All Districts").
- Confirmed with the user that `/admin/sections` already supports adding
  any section by name (Distillery, Warehouse, etc.) with zero extra code —
  no "Type" field needed. If the list grows large enough to need
  grouping/filtering later, that's a real schema change (migration + form +
  list grouping), not a default to build speculatively.
- **Fixed a real data bug, caught by the user**: `SectionSeeder` had wrongly
  included "Joint Secretary Wing" / "Deputy Secretary Wing" — in
  `~/Sites/pdf-markdown-pipeline`'s own DB these belong to a *separate*
  `Department` row (`slug=excise`, `level=secretariat_level`, i.e. "Excise
  Secretariat") from the actual Excise Department
  (`level=department_level`) whose HQ sections these are — the two rows
  just happen to share a slug there. Removed both from the seeder and
  deleted the already-seeded rows from the live DB (verified zero
  users/mail_accounts referenced them first). 11 correct HQ sections remain.
- Added a `post` column to `users` (migration
  `2026_08_20_000113_add_post_to_users_table.php`, nullable
  `string(100)`) — a free-text specific posting/charge (e.g. "Deputy
  Excise Commissioner (Prevention & Enforcement)"), distinct from
  `designation_id`'s standard rank. Same split as
  `~/Sites/pdf-markdown-pipeline`'s `users.post` column, whose own
  `DesignationSeeder` comment explicitly documents this exact decision
  ("the specific posting... belongs on the user's own `post` field, not
  as a separate Designation per posting"). Wired into
  `Store/UpdateUserRequest`, the create/edit forms (now a 3-column
  Designation/Post/Role row), and the users index listing.

### Real fix for the `{{ }}`-in-Blade footgun, this time verified (2026-08-20, done)

The M4 fix for the Templates "Variables" column ParseError was **itself
still broken** — the user caught it live, seeing literal
`<?php echo e(district); ?>` text rendered on the page instead of
`{{district}}`. Root cause, confirmed by compiling the view directly
(`app('blade.compiler')->compileString(...)`): Blade's `{{ }}` → echo
compiler scans the **raw `.blade.php` file text** for `{{ ... }}` pairs in
one pass, *before* it has any concept of `@php()` blocks, PHP string
literals, or anything else — so `@php($varLabel = '{{'.$var.'}}')` still
has a literal `{{` and `}}` sitting in the raw file text, and Blade mangles
it into `($varLabel = '<?php echo e('.$var.'); ?>')` at compile time. The
`@php()` wrapper does nothing to protect against this; that part of the M4
note was wrong.

**The actual fix**: move the token-building into a plain PHP method —
`MailTemplate::variableToken(string $name): string` in
`app/Models/MailTemplate.php` (returns `'{{'.$name.'}}'`) — a regular
`.php` file, which Blade's compiler never touches at all, so the
concatenation is just normal PHP with no compilation hazard. Both broken
call sites fixed to use it: `resources/views/templates/index.blade.php`'s
Variables badges, and `resources/views/livewire/campaign-builder.blade.php`'s
variable-picker chips (this one was silently inserting the same garbled
`<?php echo e(...); ?>` string into the Quill body editor on click —
worse than the visible table bug, since nothing would have errored, the
campaign body would have just silently contained broken text). Verified
both by compiling the views directly and diffing the rendered HTML
before/after, not just by eyeballing the source.

**Rule going forward**: a `.blade.php` file's raw text must never contain
both `{{` and `}}` characters together, in any order, for any reason —
not in a comment, not in a string literal, not inside `@php()`. If you need
the two-character sequence `{{` to appear in rendered output, build it in
a plain `.php` class method (or a Blade `@{{ }}`-escaped literal for
static text) and reference that from the view — never type it inline.

**Also came from this same conversation**: another Claude session (working
in this same repo, coordinating separately) created two real
`mail_templates` rows via `MailTemplate::updateOrCreate()` — "Shops
Workbook — District Data Request" (the real per-district data-collection
distribution email) and "Test Email — Do Not Action" (a permanent,
deliberately-unedited template for sanity-check sends). Both use the
`district`/`division`/`zone`/`officer` variable set. Worth knowing these
exist next time this app is touched — they weren't part of this session's
own work but now live in the same `mail_templates` table.

### "Everyone" scope, security audit, officer-name cleanup, XLSX bulk import (2026-08-20, done)

- **"Everyone" scope fix**: `scope = 'all'` previously meant "every district"
  only — wrong, per the user: it should mean every zone, division, *and*
  district officer combined (98 = 5 + 18 + 75 in the seeded data). Fixed in
  `CampaignBuilder::candidateRecipients()` (concatenates all three levels)
  and the info-box copy no longer says "district's officer."
- **Security audit, prompted by a direct question ("is the app secure?")**
  — not just reassurance, two real fixes:
  - `/templates/create` and `/templates/{id}/edit` (GET) had **no privilege
    check** — any authenticated user could view them; only the POST/PUT
    save was blocked (via `StoreMailTemplateRequest::authorize()`). Fixed
    by moving `create`/`store`/`edit`/`update`/`destroy` under
    `privilege:templates.manage` route middleware, same as every other
    admin-CRUD route group — `index` alone stays open to all authenticated
    users (needed for the campaign builder's template picker).
  - `CampaignBuilder`'s zip-attachment match-override `<select>` is
    `wire:model`-bound, and Livewire does **not** validate that a submitted
    value matches one of the rendered `<option>`s — a tampered payload
    could set an arbitrary string that gets concatenated into a
    `Storage::disk('local')` path. Flysystem's local adapter already
    rejects `..` traversal by default, so this likely wasn't exploitable
    end-to-end, but added an explicit server-side check
    (`in_array($override, $this->zipExtractedFiles, true)`) in
    `confirmAndQueue()` as defense-in-depth anyway — the value should never
    have been trusted regardless of what Flysystem does.
  - **Soft deletes added** to `Section`, `MailAccount`, `MailTemplate`,
    `Campaign`, `RecipientList` (migration
    `2026_08_20_000114_add_soft_deletes_to_reference_tables.php` +
    `use SoftDeletes;` on each model) — these are the "reference/record
    data" tier in `~/Sites/excise-budget-tracker`'s own convention
    (BudgetHead, Scheme, Letter, Designation, User all soft-delete there;
    Zone/Division/District and pure join/detail/log tables don't). Only
    `Designation`/`User` had it here before; the other five were a real
    gap against that established pattern. `destroy()` methods needed zero
    code changes — Eloquent's `->delete()` becomes a soft delete
    transparently once the trait is present. Verified via tinker
    (soft-deleted row invisible to normal queries, recoverable via
    `withTrashed()`).
- **Officer-name cleanup + terminology fix**, both flagged directly by the
  user:
  - The seeded `jc_name`/`dc_name`/`deo_name` values (from
    excise-budget-tracker's JSON export) are stale — officers rotate
    postings — and were in Hindi, which doesn't round-trip cleanly through
    `{{officer}}`. Migration
    `2026_08_20_000115_clear_stale_officer_names.php` nulls all three
    columns (email/CUG untouched — only the name was flagged as
    unreliable).
  - Corrected terminology: AEC (Assistant Excise Commissioner) is the
    *cadre*; DEO (District Excise Officer) is the *post* held at a
    district posting. Every "AEC (DEO)" label became plain **DEO**
    (`/recipients` table headers, the district edit form). Zone stays JEC,
    Division stays DEC — unchanged, already correct.
  - Added `Zone::officerDisplayName()` / `Division::officerDisplayName()`
    / `District::officerDisplayName()` — returns the real name if set,
    else a placeholder like `"DEO - Agra"` / `"JEC - Lucknow Zone"`. Used
    everywhere an officer name is shown or merged into a campaign
    (`/recipients` table — shown in italic grey when it's a placeholder —
    and `CampaignBuilder::candidateRecipients()`'s `name`/`officer`
    fields), so a blank name never sends as a literal blank in an email
    and the UI never claims a name is on file when it isn't.
- **XLSX bulk import for the officer directory** (Zone/Division/District
  JEC/DEC/DEO name/email/CUG — separate from the ad-hoc `recipient_lists`
  import wizard, which creates new campaign-only lists rather than
  updating the fixed 5/18/75 org directory):
  - `RecipientController::downloadTemplate($level)` streams a pre-filled
    XLSX (`response()->streamDownload()` + `openspout`'s `Writer::openToFile('php://output')`
    — `Writer::openToBrowser()` sets its own headers, which would have
    doubled up with `streamDownload()`'s, so this uses the header-agnostic
    `openToFile()` form instead) — Name column pre-filled for every
    zone/division/district at that level, officer columns pre-filled with
    whatever's currently on file, so re-uploading only requires editing
    what actually changed.
  - `App\Livewire\OfficerDirectoryImportWizard` (2 steps: upload → preview
    table with a per-row "Will update" / "No match — skipped" status →
    confirm) reuses `RecipientImportParser` (already installed for the
    recipient-list wizard; the fixed template column order means no
    column-mapping step is needed here, unlike that wizard). **Never
    creates new zones/divisions/districts** — matches existing rows by
    name only, and a blank cell in the upload leaves the existing value
    alone rather than blanking it out, so a sheet only needs to carry
    what changed. `openspout` already supports XLSX read *and* write
    (confirmed via its `Writer\XLSX\Writer` — matches the ladder: reuse an
    already-installed dependency rather than adding
    `phpoffice/phpspreadsheet`, which is what the user's other reference
    project actually uses, but in Node/`exceljs`, not PHP).
  - Verified with a real round-trip Feature test: hit the template-download
    route for real bytes, built a matching upload file with those exact
    columns via `openspout`'s own writer, ran it through the Livewire
    component, and asserted the target `District` row's `deo_name`/
    `deo_email`/`deo_cug` actually changed in the DB — not just that the
    component didn't throw.
- `README.md`/`CLAUDE.md` updated (`post` field, Quill, current build
  status) and this repo is now public:
  **https://github.com/SubhanRaj/UP-excise-mailer** — confirmed `.env`
  was gitignored and never tracked before pushing, no other secret files
  staged.

### Test-send, daily send cap, retry-failed, and a global Sent Mail log (2026-08-20, done)

- **Send Test Email** (`/campaigns/test-send`, SuperAdmin-only via `is_admin`
  middleware) — `App\Livewire\TestEmailSender`. Deliberately **not** a
  `Campaign` (no `campaigns`/`campaign_recipients` rows) — sends through
  `config('mail.default')` (Resend, the same trusted system sender as
  invites/OTP), not a per-section Gmail `mail_account`, so "is the software
  working" can be answered without one being configured yet. Reuses
  `App\Mail\CampaignMail` with an unsaved (never persisted)
  `CampaignRecipient` instance — no new Mailable needed. Recipient is
  either an existing app user (dropdown) or any manually-typed address;
  template defaults to "Test Email — Do Not Action" but any template can
  be picked. Logged via `ActivityLog::record('test-email.send', ...)` —
  already visible at `/admin/activity-logs` with zero extra work, since
  that page auto-derives its action filter dropdown from distinct
  `activity_logs.action` values. Verified with `Mail::fake()` +
  `Mail::assertSent()`, not just "it didn't throw" — **and clarified for
  the user that this fake-mail verification never actually sent anything
  to a real inbox**, since they asked where the test email went.
- **`daily_send_cap` enforcement** — this `mail_accounts` column existed
  since M1 but nothing ever read it (confirmed by grepping the whole
  `app/` tree for the column name outside migrations/validation — a real
  "looks configured, does nothing" gap, caught during the security
  self-audit). `CampaignBuilder::sendDelaySeconds()` now buckets
  recipients past the day's remaining cap into following days (`day *
  86400 + positionInDay * throttle_seconds`), accounting for how many
  this account already sent *today* across any campaign before this one
  queues. Marked with a `ponytail:` comment: the already-sent-today count
  isn't locked against a second campaign queuing concurrently on the same
  account — fine for how this app is actually used (occasional manual
  campaigns), would need a real reservation/lock if that ever changes.
  Verified with `Queue::fake()` + inspecting each dispatched job's actual
  delay: 5 recipients at cap=2 landed in day buckets `[0,0,1,1,2]` exactly
  as intended.
- **Resend-failed / retry-recipient action**
  (`CampaignController::retryRecipient()`, a "Retry" button next to any
  `failed` row on `/campaigns/{campaign}`) — re-derives the recipient's
  `{{variable}}` values from the *current* zone/division/district/
  `recipient_list_item` row via the new `CampaignRecipient::resolveVars()`
  (not a stale snapshot from queue time), so retrying after fixing a bad
  email address in `/recipients` also picks up any other corrections made
  since. Resets `status` to `pending`, clears `error_message`, dispatches
  immediately (no delay — it's a single manual retry, not part of a
  throttled batch). Verified end-to-end: seeded a district with a
  corrected officer name different from the stale `campaign_recipients`
  row, hit the retry route, asserted the dispatched job's rendered
  subject/body contain the *current* data, not what was originally queued.
- **Global Sent Mail log** (`/campaigns/sent-mail`) — the user asked "can
  we add an option to show the mail sent from this software"; per-campaign
  status already existed but there was no single place to see everything
  actually delivered across every campaign. Plain paginated
  `CampaignRecipient::where('status', 'sent')` list, most recent first,
  linking back to each row's campaign. Points to Activity Log for test
  sends rather than duplicating that list.

### Send Test Email: pick a mail account too, not just Resend (2026-08-20, done)

User pushback on the first version: it explained *why* it only sent via
Resend instead of just letting you test what you actually want to test — a
section's Gmail account, the same one campaigns really send through. Fixed
by adding a **Send Via** toggle to `TestEmailSender`: `system` (Resend,
still the default) or `mail_account` (reuses `MailAccount::mailerConfig()`
+ `config(['mail.mailers.dynamic' => ...])` — the exact same dynamic-mailer
pattern `SendCampaignRecipientMail` uses for real campaigns), with the
account dropdown scoped by `User::canUseMailAccount()` just like the
campaign builder's own picker. Dropped the explanatory blurb entirely —
the toggle makes the choice self-evident, no prose needed. Verified with a
Feature test: SuperAdmin sends via a mail_account, asserts `CampaignMail`
actually dispatched through the fake.

### Section contact fields + "Task Force" → "Task Force Section" (2026-08-20, done)

- Added nullable `sections.email` and `sections.head_name` columns
  (`2026_08_20_000116_add_contact_fields_to_sections_table.php`) —
  `email` is for *receiving* mail addressed to that section (e.g. a task
  force asking other sections for data), distinct from a `mail_accounts`
  row which is for *sending* through Gmail SMTP; the create/edit forms
  say so explicitly since the two are easy to conflate. Wired into
  `Section`'s `#[Fillable]`, both `StoreSectionRequest`/
  `UpdateSectionRequest`, the create/edit forms, and two new columns on
  the sections index table.
- Renamed the seeded "Task Force" section to "Task Force Section" (user:
  it should read as a section name like the others, not a standalone
  label) — `SectionSeeder` updated for fresh installs, plus a one-off
  data migration (`2026_08_20_000117_rename_task_force_section.php`)
  updating the row already seeded on the live DB (`slug` `task-force` →
  `task-force-section`), reversible via `down()`.
- Dropped two more layman-facing technical asides the user flagged as
  meaningless jargon: "Just fills in the SMTP fields below — everything
  is stored as plain SMTP settings" (Add Mail Account) and its edit-page
  twin — the Provider dropdown visibly filling in the fields below it
  needed no explanation.

### Full jargon sweep across the UI (2026-08-20, done)

User asked to fix "such jargon" everywhere, not just where already caught —
ran an Explore-agent audit of every `resources/views/**/*.blade.php` for
developer terminology or implementation-detail explanations a layman
wouldn't need. Fixed everything the audit flagged as real:
- **Templates create/edit** — the variable-placeholder hint still showed
  raw `{{ district_name }}` (underscore_case) even though the campaign
  builder's own variable picker was already fixed to plain words back in
  an earlier round; now reads "Type a word in double curly braces... e.g.
  `{{ district }}`" in both the Subject and Body hints, both pages.
- **Mail Accounts create/edit/index** — "Throttle (seconds between
  sends)" → "Delay Between Sends (seconds)" (label, and the index table
  header). SMTP Host/Port field *labels* were kept — they sit directly
  above a concrete input, unlike a prose hint, and an admin configuring a
  literal mail server needs to know which value goes where.
- **Recipient list import wizard** — "text-layer PDF ... no OCR" →
  "a PDF you can select text in (a scanned or photographed PDF won't
  work)"; "Parse File" button → "Upload File"; "Parsing file…" loading
  text → "Reading file…" (matches the officer-directory import wizard's
  existing wording).
- Reviewed and left as-is: Sections' email/head hint (plain and useful,
  not an implementation detail), Designations/Users/Recipients hints
  (already plain), Activity Log's IP/Path/Status columns (legitimate
  SuperAdmin audit-tool columns, not layman-facing), and the officer
  directory wizard's JEC/DEC/DEO labels (department terms, not dev jargon).

### Zip-match hardened against real government filenames (2026-08-20, done)

User has a real 75-file batch — one Excel workbook per district
(`5_Shops_<DISTRICT>.xlsx`, from the department's actual data-collection
folder) — and asked whether `zip_per_recipient` can handle it as-is.
Checked against the real filenames and the DB's 75 district names:
`ZipRecipientMatcher`'s old exact+Levenshtein-only matching would have
failed on **every single file** — the shared `5_Shops_` decorative
prefix alone pushed every Levenshtein distance past threshold, before
even accounting for name-format mismatches (`Bhadohi` in the DB vs. the
file's official long name `SANT_RAVIDAS_NAGAR_BHADOHI`; `Lakhimpur
Kheri` in the DB vs. the file's short colloquial `KHERI`; `Barabanki`
vs. the file's spaced-out `BARA_BANKI`). Added a middle tier —
**substring containment against prefix-stripped candidates**
(`findContains()` + `prefixStrippedCandidates()`, tried before the
existing Levenshtein fallback) — that strips up to 3 leading
hyphen-separated segments off the filename and checks containment in
both directions, catching all three patterns above without hardcoding
`5_Shops_` or any other specific prefix. Verified against the actual
75-file folder end to end: **all 75 auto-matched correctly**, zero
misses, zero wrong guesses (spot-checked the full mapping by eye).
Added 3 new permanent regression cases to `ZipRecipientMatcherTest`
(decorative prefix, official-long-name-in-file, short-file-long-
recipient) — 7/7 passing, existing exact/fuzzy/no-match/dedup cases
still hold.

Answer for the user: **yes, zip it — no manual work needed.** Zip all 75
`.xlsx` files as-is (original names, no renaming), pick `zip_per_recipient`
in the campaign builder with recipient scope "Districts" → select all,
and the confirmation table should now show all 75 auto-matched before
Confirm & Queue. No need to bulk-upload 75 separate one-off campaigns.

This isn't a one-batch fix — it generalizes to any future filename
pattern that's textually related to the district name: decorative
prefixes/suffixes (`Report_AGRA_2027.xlsx`), long-official-vs-short-
colloquial in either direction, spelling variants/typos, and any mix of
underscores/hyphens/spaces/capitalization. A filename with no textual
relation to the district at all (a bare numeric code, an unrelated
abbreviation scheme) still shows "no match" for manual override — never
silently wrong, always caught in the confirmation table before send.

### NIC email (mGovCloud) as a mail account provider (2026-08-20, done)

Added a third **Provider** preset next to Gmail and Custom SMTP on
Add/Edit Mail Account: **NIC Email (mGovCloud)**, using the config from
mgovcloud.in's own NIC SMTP docs — host `smtp.mgovcloud.in`, port 587
with TLS (the docs also list port 465 with SSL as an alternative;
either works, since the encryption fix below picks it automatically
from the port). No schema/backend change needed — `mail_accounts` was
already provider-agnostic (`gmail_address` is really just a username
column, `smtp_host`/`smtp_port` already free-text); this is purely a
third labeled preset in the same provider-switch JS, refactored from an
`isGmail` ternary into a small lookup object so adding this one didn't
mean duplicating the whole handler a third time.
- **Real bug fix alongside it**: `MailAccount::mailerConfig()` hardcoded
  `'encryption' => 'tls'` regardless of port — harmless for Gmail
  (always TLS/587 in this app) but would have silently broken NIC's
  port-465-SSL option, and any other future provider using SSL. Now
  derives it: `$this->smtp_port === 465 ? 'ssl' : 'tls'`. Verified both
  branches directly against `mailerConfig()`'s output.

### Fixed: the privileges checkboxes for Sections/Mail Accounts/Designations/Users did nothing (2026-08-20, done)

User granted a Task Force (IT/tech section) user the "Manage Sections"
privilege and found no Sections nav item or access after login —
correctly suspected the checkbox wasn't actually wired to anything,
rather than reaching for "just make them SuperAdmin" as a workaround.
Confirmed: `sections.manage`, `mail-accounts.manage`, and `users.manage`
already existed as checkable privileges (`User::PRIVILEGES`,
`admin/_privilege_checkboxes.blade.php`) and even had a working
`HasPrivilege` middleware — but the actual routes for all four admin
resources (`routes/web.php`'s sections/mail-accounts/designations/users
group) were hardcoded to the blanket `is_admin` (SuperAdmin-only)
middleware, and all 8 of their `Store*/Update*Request::authorize()`
methods hardcoded `isAdmin()` too — so the privilege checkbox was
checked, saved to the DB, and then ignored by every layer that mattered.
The sidebar had the same bug (`@if(isAdmin())` wrapping all four nav
links). Fixed root-to-leaf for all four resources:
- Routes: swapped `is_admin` for `privilege:sections.manage` /
  `mail-accounts.manage` / `designations.manage` / `users.manage`
  (`privilege:` middleware already treats SuperAdmin as holding every
  privilege via `User::hasPrivilege()`, so this is a strict widening,
  not a behavior change for existing SuperAdmins).
- All 8 FormRequests' `authorize()`: `isAdmin()` → `hasPrivilege('...')`.
- Sidebar: each of the four "Manage" nav links now checks its own
  `hasPrivilege()` individually instead of one shared admin-only block.
- Added `designations.manage` as a privilege (it existed as an admin
  resource but had no corresponding privilege key at all before this).
- **Privilege-escalation guard**, since `users.manage` alone is now
  reachable by a non-SuperAdmin: `Store/UpdateUserRequest` now restrict
  the `role` dropdown to Admin/User only (never SuperAdmin) and
  `privileges.*` to a subset of the *actor's own* privileges — a
  `users.manage` holder can't grant a privilege they don't have, and
  can't promote anyone to SuperAdmin. `UpdateUserRequest::authorize()`
  and `UserManagementController::edit/update/destroy/resendActivation`
  also now refuse to touch an existing SuperAdmin account unless the
  actor is one too — otherwise a users.manage grant would be a path to
  demoting/locking out the actual SuperAdmins. Forms hide the
  SuperAdmin role option and ungrantable privilege checkboxes for
  non-SuperAdmin actors, and the Users list hides Edit/Deactivate for
  SuperAdmin rows to a non-SuperAdmin viewer, so the UI never offers an
  action that would just 403.
- **Found and fixed a second, unrelated pre-existing bug while writing
  the end-to-end test for this**: `Store/UpdateSectionRequest` and
  `Store/UpdateDesignationRequest` compute `slug` in
  `prepareForValidation()` but never declared it in `rules()` — so
  `$request->validated()` silently dropped it, and
  `Section::create()`/`Designation::create()` crashed with a `NOT NULL
  constraint failed: sections.slug` on every real creation through the
  web form (the seeder's direct `Section::firstOrCreate()` calls never
  hit this path, which is why it went unnoticed). Added `'slug' =>
  ['required', 'string', 'max:160']` to all four requests' `rules()`.
- Verified end-to-end with a throwaway Feature test (written, run,
  deleted per this project's convention): a `sections.manage`-only User
  can create a section; a `designations.manage`-only User can create a
  designation; a privilege-less User gets 403 on the same routes; a
  `users.manage`-only User gets a validation error trying to grant
  SuperAdmin role or a privilege they don't hold; and gets 403 trying to
  edit an existing SuperAdmin. 6/6 passed.

**For the live Task Force account**: their user row currently has
`role = SuperAdmin` (looks like a manual workaround was already
applied) — worth reverting to `role = User` (or `Admin`, which behaves
identically except cosmetically — only the literal `SuperAdmin` string
bypasses privilege checks) now that the privilege checkboxes actually
work, so they get exactly Sections access rather than blanket admin
access to every section's mail accounts, all campaigns, and user
management.

### Fixed: mail-account Provider switch broke on a repeat page visit (2026-08-20, done)

User picked "NIC Email (mGovCloud)" and the address label kept saying
"Gmail Address" and the SMTP Host/Port fields didn't update. Root cause
was **not** what it looked like at first (Livewire's `wire:navigate`
page swap actually does re-run inline `<script>` tags on every visit —
verified directly against `vendor/livewire/livewire/dist/livewire.js`'s
`prepNewBodyScriptTagsToRun()`, which clones and re-inserts every body
`<script>` tag on each swap unless marked `data-navigate-once`). The
real bug: `const mailProviderPresets = {...}` was declared at the
script's top level in both Add/Edit Mail Account — fine on the *first*
visit, but a `SyntaxError: Identifier has already been declared` on any
*second* visit to the same page in one browser session (re-running the
same top-level `const` in the same global scope), which silently kills
the entire script block, including the `change` listener — so the
symptom only appears after you've already been on the page once before,
which is exactly the normal flow (visit once during setup, come back to
add another account). Fixed by moving the `const` inside the
`change`-listener setup (a fresh function scope every execution, no
redeclaration). Also added the SSL/TLS port hint the NIC docs describe
("587 for TLS, or 465 for SSL") next to the SMTP Port field on both
pages. **Found and fixed the identical bug pattern** in
`admin/users/create.blade.php`'s and `edit.blade.php`'s designation→
privilege-autofill script (same top-level-redeclaration risk, same
"only breaks on a repeat visit" symptom) and defensively guarded
`templates/_editor.blade.php`'s Quill init (already IIFE-scoped so not
actually at risk, but wrapped for consistency and a null-check on the
editor element).

### Task Force can now send test emails through their own mail account (2026-08-20, done)

New `test-email.send` privilege (added to `User::PRIVILEGES` and the
checkboxes) — previously Send Test Email was hardcoded SuperAdmin-only.
A privilege holder can reach `/campaigns/test-send`, but the page never
shows them the "System (Resend)" option at all — that's reserved for
SuperAdmin, since Resend is the same shared sender used for login
OTP/invites, not something a section should be probing. A non-SuperAdmin
always sends through a real `mail_account`, already scoped to their own
section by the existing `canUseMailAccount()`/`mailAccounts` query (no
change needed there). Enforced server-side too, not just hidden in the
UI: `TestEmailSender::send()` aborts 403 if a non-SuperAdmin's `sendVia`
is somehow `system`. Verified: a `test-email.send`-only User can send via
their section's mail account and gets 403 attempting `sendVia=system`; a
privilege-less User gets 403 on the route entirely.

### Fixed: real sends via a mail_account were rejected as "not allowed to relay" (2026-08-20, done)

Caught live via the user's own test send — auth succeeded (after
setting an app-specific password) but the send itself then failed with
`553 Sender is not allowed to relay emails`. Root cause: `CampaignMail`
never set a `from()` address at all, so every send — through Resend
*and* through a real `mail_account` — used the fixed system
`MAIL_FROM_ADDRESS` (`noreply@mail.exciseup.in`) regardless of which
mailbox actually authenticated over SMTP. Most relays (Gmail, and NIC's
mGovCloud in particular, apparently strictly) refuse to relay a message
whose From header doesn't match the authenticated account. This wasn't
test-send-specific — it would have silently broken **every real
campaign send** through any non-Resend mail account the moment one
actually ran, since `SendCampaignRecipientMail` has the exact same gap
and no live campaign had exercised this path yet. Fixed by giving
`CampaignMail` an optional `?MailAccount $account` constructor param;
when present, `build()` calls `->from($account->gmail_address,
$account->section?->name)`. Both call sites (`SendCampaignRecipientMail`
and `TestEmailSender`) now pass their resolved `$account` through.
Sending via Resend (`$account` null) is unaffected — falls through to
the existing `MAIL_FROM_ADDRESS` default, as before. Verified directly
against `CampaignMail::build()`'s resulting `from` property with and
without an account.

### Connection Security (TLS/SSL) selector on Mail Accounts (2026-08-20, done)

User: raw port numbers ("587 vs 465") are exactly the kind of thing a
layman shouldn't have to know. Added a **Connection Security** dropdown
next to SMTP Host on Add/Edit Mail Account — "TLS (port 587)" / "SSL
(port 465)" — that fills in the SMTP Port field automatically; the port
field itself stays editable underneath for the rare non-standard-port
case, now captioned "filled in automatically... only change it if your
provider uses a different port" instead of asking the admin to
remember which number means what. Wired into the same provider-preset
JS as the Provider dropdown (picking Gmail/NIC/Custom also sets
Connection Security to match), and the Edit page pre-selects the right
option from the account's already-stored port. `MailAccount::
mailerConfig()` already derived `encryption` from the port number (see
the NIC-provider fix earlier this session) — this is a UI-only
addition, the port stays the single source of truth.

Also confirmed, in response to the user's question: app passwords are
stored via Laravel's `encrypted` cast and only edge whitespace is
trimmed by the framework's default `TrimStrings` middleware — mixed-
case alphanumeric passwords pass through byte-for-byte, no bug there.

### Mail Account form: hide SMTP details for known providers, TLS default (2026-08-20, done)

User: raw SMTP host/port/security fields don't make sense to ask about
for Gmail/NIC — those are fully documented and already filled in. The
SMTP Host / Connection Security / SMTP Port block on Add/Edit Mail
Account is now hidden entirely unless Provider is "Custom SMTP" (the
Provider-switch JS toggles a `.hidden` class); values are still set
behind the scenes for Gmail/NIC via the existing preset objects, so
nothing about form submission changed, only what's shown. Guarded
against a real edge case: a validation-error round-trip on a Custom
SMTP submission needed the block to *stay* visible (so the error message
and the admin's typed values aren't hidden) — computed server-side via
`$errors->has('smtp_host') || $errors->has('smtp_port') ||` (create)
`old('smtp_host')` not matching a known host, or (edit) the account's
already-stored host not being Gmail/NIC. Also switched the one existing
live mail account (`redacted-account@example.gov.in`) from SSL/465 to TLS/587
per the user's explicit preference.

### Real audit-trail gap found: only 2 of the documented actions were ever logged (2026-08-20, done)

User: "why does the audit log only have two actions?" — checked the
live DB and found exactly `auth.login` and `test-email.send` across all
12 rows, despite CLAUDE.md documenting "Full audit trail — every
non-GET authenticated request... `ActivityLog::record()`". The
generic auto-logging middleware this describes was **never actually
built** — only two manual call sites existed (the `Login`/`Logout`
event listeners in `AppServiceProvider`, and `TestEmailSender`). Every
section/user/mail-account/template/campaign create-update-delete this
whole session went completely unlogged. Fixed by porting
`~/Sites/excise-budget-tracker/app/Http/Middleware/LogMutation.php`
verbatim (per CLAUDE.md's own "don't diverge from these patterns
without a reason" — that app already has the real implementation this
one's docs describe): appended globally in `bootstrap/app.php`, logs
every authenticated non-GET/HEAD/OPTIONS request using the route name
as the action (e.g. `admin.sections.store`), skipping the four
login/OTP/logout route names that already get a dedicated, more
detailed entry from the event listeners. Verified end-to-end: a POST to
`/admin/sections` now creates an `activity_logs` row with action
`admin.sections.store`; a GET does not.

### Test-send failures now show up as failures, not a blank crash (2026-08-20, done)

User: add a Status column so troubleshooting is possible. Real gap this
exposed: `TestEmailSender::send()` had no try/catch at all — a failed
send (like the two live SMTP errors hit this session) threw straight
through to Laravel's raw error page and left **no record anywhere** of
the attempt, success or failure. Now wrapped: on success, logs
`status: sent` (unchanged behavior); on failure, logs `status: failed`
+ a truncated error message and shows a friendly inline `flash()->error()`
instead of crashing the page — matching this app's established
try/catch-and-flash convention used elsewhere (e.g.
`UserManagementController::store()`). The Sent Mail page's Test Sends
table has a new **Status** column (green "sent" / red "failed", with
the error message shown under a failed row) — older rows logged before
this fix have no `status` key and default to displaying "sent" (correct,
since the old code path only ever logged after a successful send).

### Sent Mail promoted to a first-class page, merged with test sends (2026-08-20, done)

User: "why is sent mail not populated... I've received a test email" —
by original design, test sends only went to Activity Log, not Sent
Mail, which only tracked `campaign_recipients` rows; confusing since
both are "mail this app sent." Fixed two ways: (1) `sentMail()` now
also fetches the 50 most recent `test-email.send` activity-log rows and
the page shows two sections, **Campaign Sends** (existing, paginated)
and **Test Sends** (new, with the Status column above); (2) per "put
sent mail as a separate dashboard entity," **Sent Mail is now its own
top-level sidebar link** (icon `ti-mail-check`, right under Campaigns)
instead of a button buried in the Campaigns page header — removed that
now-redundant header button. Breadcrumb updated to not nest under
Campaigns either.

### Nicer confirm dialogs, matching ~/Sites/pla (2026-08-20, done)

User pointed at `~/Sites/pla`'s nicer delete confirmations. Chose the
smaller of two options presented (kept this app's existing form-POST +
redirect + flash pattern rather than pla's full AJAX+jQuery rewrite) —
replaced the plain browser `confirm()` popup on all 6 delete/deactivate
forms (Sections, Mail Accounts, Users, Designations, Recipient Lists,
Templates) with a SweetAlert2 dialog. Implementation is a single
delegated `submit` listener registered once in the shared layout
(inside the existing `window.__layoutScriptsInitialized` guard, so it
survives `wire:navigate` correctly without the redeclaration bug fixed
earlier this session) — any current or future form just needs
`data-confirm="message"` instead of the old inline `onsubmit="return
confirm(...)"`, no per-page JS required.

### "Send Test" shortcut on the Mail Accounts list (2026-08-20, done)

Added a flask-icon "Send Test Email" link per row on
`/admin/mail-accounts` (next to Edit/Delete, gated by the
`test-email.send` privilege) — links to `campaigns.test-send` with
`?mailAccountId=<id>`, which `TestEmailSender::mount()` now reads to
pre-select that exact account (`sendVia=mail_account`) instead of
making the admin pick it again from a dropdown — test the account
you're already looking at, right from where you manage it. Verified via
Livewire test with a query-string-driven mount.

**Also found and fixed the actual reason Task Force couldn't see Send
Test Email at all**: their live account has every other privilege
granted (users/sections/mail-accounts/designations/templates.manage,
campaigns.send, recipients.import, activity-logs.view) but
`test-email.send` was simply never added to the list — not a bug,
just never granted. Added it directly to their `privileges` column.

### Toast on Livewire actions, loading spinner, mail account ID out of the URL (2026-08-20, done)

Three real issues from one round of user testing:

1. **No toast after Send Test Email** — nothing to do with the CDN.
   This app only has `php-flasher/flasher-laravel` (confirmed: the
   dedicated Livewire bridge package, `php-flasher/flasher-livewire`,
   is abandoned upstream with no real replacement shipped into
   `flasher-laravel`). `flash()->success()`/`error()` render via a
   server-side Blade directive (`@flasher_render`) on the *next full
   page load* — a `wire:click` action that doesn't redirect anywhere
   never gets one, so the flashed message just sits unused in the
   session. Fixed by dispatching a Livewire browser event instead
   (`$this->dispatch('toast', type: ..., message: ...)`) and rendering
   it as a SweetAlert2 toast via one listener registered on
   `livewire:init` in the shared layout (reuses the SweetAlert2 CDN
   already loaded for the confirm-dialog fix earlier this session) —
   generic and reusable by any future Livewire component, not
   test-email-specific.
2. **No loading feedback on Send Test Email** — the button already had
   `wire:loading.attr="disabled"` but only a subtle opacity change, easy
   to miss and double-click. Added a spinner icon + "Sending…" text
   swap via `wire:loading`/`wire:loading.remove`.
3. **`?mailAccountId=1` in the URL** — user has mitigated this pattern
   in every sibling app; exposing a database ID in a GET query string
   (URL bar, browser history, referrer headers, server access logs) for
   what's really a one-off action isn't good practice even though it
   was already authorization-checked server-side
   (`canUseMailAccount()`), so not an actual IDOR — still fixed to match
   the user's standing convention. The "Send Test" button on Mail
   Accounts is now a POST form (`CampaignController::prefillTestSend()`,
   new route `campaigns.test-send.prefill`) that validates + authorizes
   the account, stashes its ID in `session()` (one-time —
   `TestEmailSender::mount()` reads it via `session()->pull(...)`, so
   it's consumed immediately and never lingers), then redirects to the
   plain `/campaigns/test-send` with no query string at all. Verified:
   the redirect target carries zero query params, and a cross-section
   account ID gets a 403 rather than being silently accepted.
   Pagination's `?page=N` links are unaffected/unchanged — those aren't
   sensitive/exploitable IDs, just a page number, which is the
   distinction the user actually drew.

### Fixed two self-inflicted script bugs, and found php-flasher's real Livewire support (2026-08-20, done)

**The `livewire:navigated`-wrapping "fix" from earlier this session was
itself the bug** — user reported the Templates editor kept stacking a
new Quill instance on every "Add Template" visit, with none at all on
the very first visit. Root cause, confirmed directly against
`vendor/livewire/livewire/dist/livewire.js`: `livewire:navigated` (an
alias of Alpine's `alpine:navigated`) fires **only on SPA-style
`wire:navigate` transitions, never on a genuine first/hard page load**
— so wrapping a page-specific init script in
`document.addEventListener('livewire:navigated', fn)` meant it never
ran on first arrival, and because the wrapping script itself
re-executes on every visit (Livewire clones and re-inserts body
`<script>` tags on each navigation — confirmed via
`prepNewBodyScriptTagsToRun()`), each return visit registered *another*
listener, so the Nth visit fired N-1 stacked copies of the init logic.
The actual fix needed was never the event wrapper — it was avoiding a
top-level `const`/`let` redeclaration crash (the real, narrower bug
from earlier), which only needs a plain IIFE. Reverted the
`livewire:navigated` wrapper back to a direct IIFE (or a plain
top-level call, where there was no `const` at all) in all 5 places it
had been wrongly applied: `templates/_editor.blade.php` (Quill),
`admin/mail-accounts/create.blade.php` + `edit.blade.php`
(provider-switch), `admin/users/create.blade.php` + `edit.blade.php`
(designation-privilege autofill).

**Also removed the custom SweetAlert2-toast workaround** built earlier
this session for "no toast after Send Test Email" — user pointed at
php-flasher's own Livewire docs (php-flasher.io/livewire/), which
turned out to be completely accurate: `flasher-laravel` already ships a
real `LivewireListener` hooked into Livewire's `dehydrate` cycle
(`vendor/php-flasher/flasher-laravel/EventListener/LivewireListener.php`)
that automatically dispatches queued `flash()` notifications as a
`flasher:render` Livewire browser event — genuinely zero-config, no
custom JS needed. The actual gap was operational, not architectural:
**`php artisan flasher:install` had never been run**, so
`public/vendor/flasher/` (JS/CSS/themes) never existed. Ran it now
(committed the published assets, same pattern as
`public/vendor/tabler-icons`). Reverted `TestEmailSender::send()` back
to plain `flash()->success()`/`flash()->error()` and deleted the custom
`Livewire.on('toast', ...)` SweetAlert2 listener from the layout — the
ad-hoc toast was very likely also the source of the "looks bad, has a
horizontal scroll" complaint, since it's now gone entirely in favor of
php-flasher's own styled/themed toast. SweetAlert2 itself is kept for
the delete/deactivate **confirm dialogs** only (a genuinely different
UX need — a modal, not a toast), which is unaffected by any of this.

### Template editor upgrade: real toolbar, variable dropdown, preview (2026-08-20, done)

Quill was previously initialized with zero toolbar config. Added: a
configured toolbar (headers, bold/italic/underline/strike, text/bg
color, lists, align, link, blockquote, clean); an "Insert a variable…"
dropdown next to the editor that inserts the token at the cursor
(reuses `MailTemplate::variableToken()`, same brace-safety pattern as
the campaign builder's chip picker); and a "Preview with sample data"
button rendering the current (unsaved) Subject + Body with placeholder
values substituted client-side, in a SweetAlert2 modal (regex mirrors
`MailTemplate::render()`'s server-side pattern exactly). Deliberately
left out image embedding — base64-embedded images bloat every
recipient's email and hurt deliverability on Gmail/NIC relays; if
wanted later it should upload to storage and reference a URL, not
embed.

### Confirmed real sends never use the logged-in user's name; fixed campaign template selection (2026-08-20, done)

User was alarmed seeing `{{officer}}` render as "Joint Task Force" (the
logged-in admin) and asked to check whether a real send could do that
— genuinely worth stopping to verify rather than just asserting no.
Checked `CampaignBuilder.php` directly: `officer` is *always* built
from `Zone/Division/District::officerDisplayName()` per the actual
recipient (lines 326-350), with zero code path anywhere touching
`auth()->user()`. The alarming value came from the Template editor's
new **Preview with sample data** button (added this session) — its
placeholder data used the logged-in admin's own name for `officer`,
which was misleading (looked like real send behavior when it wasn't).
Fixed: sample values are now obviously-fake placeholders ("Sample
Officer Name", "Sample Recipient Name") instead.

**Also fixed a real error hit picking a template in the campaign
builder**: `Unable to call lifecycle method [updatedTemplateId]
directly` — the template `<select>` had both `wire:model="templateId"`
*and* `wire:change="updatedTemplateId"`, directly invoking a Livewire
magic lifecycle-hook method by name, which Livewire 4 explicitly
disallows (`updated{Property}()` is meant to fire automatically when
the bound property changes, never be called directly). The `wire:change`
was almost certainly a workaround for the property not updating live
in the first place — `wire:model` alone is deferred by default. Correct
fix: `wire:model.live="templateId"`, `wire:change` removed entirely.
Verified with a Livewire test: selecting a template now correctly
fills in Subject/Body without erroring.

### Fixed: campaign builder's Body editor stayed blank after picking a template (2026-08-20, done)

User: Subject fills in correctly after selecting a template, but the
Body/Message editor stays blank. Root cause: the Quill editor
(`livewire/_quill-editor.blade.php`) sits inside a `wire:ignore` div —
required so Livewire's own re-rendering never fights with Quill for
control of that DOM — which means Livewire literally never touches
that subtree again after it first mounts. `updatedTemplateId()` (and
`saveNewTemplate()`) set `$this->body` on the server correctly, but
nothing ever pushed that new HTML into the *already-running* Quill
instance sitting behind the wire:ignore wall. Subject is a plain
`wire:model` text input, so it re-rendered fine — Body couldn't, by
design of wire:ignore. Fixed by having both places `$this->dispatch(
'quill-set-content', model: 'body', html: $this->body)` after setting
`$body`, with a matching `Livewire.on('quill-set-content', ...)`
listener in the editor partial that sets `quill.root.innerHTML`
directly. Deliberately **not** wrapped in a `livewire:init` (or
`livewire:navigated`) listener — same lesson as the earlier stacking-
editor bug this session: this script re-executes fresh each time the
step-2 block re-enters the DOM, but `livewire:init` fires exactly once
per page load, so wrapping it there would mean the listener never
(re-)registers for any Quill instance after the first one. Verified
with a Livewire test asserting the `quill-set-content` event dispatches
with the template's actual body HTML.

### Fixed: campaign status never left "queued" even after every recipient sent (2026-08-20, done)

Same live Lucknow incident, a second real bug in it: user asked why
Sent Mail/logs still didn't show the delivery even after the SMTP-
timeout fix — checked the DB directly and the `campaign_recipients`
row was already correctly `status = 'sent'` (Sent Mail's own query
already finds it fine). The actual problem was one level up: **nothing
anywhere in this app ever transitions `Campaign::status` past
`'queued'`** — grepped the whole `app/` tree for any write to it, found
none. So the Campaigns list/show pages keep showing "Sending" forever,
even once every single recipient has a terminal status, which reads as
"nothing happened" even though delivery genuinely succeeded. Fixed:
`SendCampaignRecipientMail::handle()` now checks, after updating its
own recipient's row, whether any `pending`/`queued` recipients remain
for that campaign — if none, flips the campaign to `'completed'`.
`CampaignController::retryRecipient()` correspondingly resets the
campaign back to `'queued'` when retrying a failed recipient, so it
doesn't keep reading "Sent" while one row is mid-retry underneath it.
Applied retroactively to the live "Test" campaign (id 1) so it stopped
showing stale. Verified with a Feature test: a recipient's job
completing correctly flips its solo-recipient campaign to `completed`.

### Manual "type email addresses" recipient scope (2026-08-20, done)

User: adding one or two ad-hoc recipients shouldn't require importing
a whole Recipient List first. Added **Type Emails** as a sixth
`recipient_scope` option in the campaign builder — a plain textarea,
comma- or newline-separated, parsed/deduped/validated the same way
every other scope already is (`candidateRecipients()`'s existing
`FILTER_VALIDATE_EMAIL` filter at the end catches anything malformed).
Recipients get `recipient_type = 'manual'`, `recipient_ref_id = null`
— there's no backing zone/division/district/list row to point at, so
`CampaignRecipient::resolveVars()` (used by retry) just returns the
static `name`/`email` already stored on the row for that type, since
there's nothing external to re-fetch from. Available merge variables
for this scope are `name`/`email`, matching the Imported List scope's
un-mapped case. Verified with a Feature test parsing a mixed comma/
newline/duplicate-containing input down to the correct 3 unique
addresses.

### Idempotent campaign sending + inline send progress (2026-08-20, done)

User: mid-campaign, asked that sending be made idempotent (a second
"Confirm & Send" — double-click, stale back-nav — shouldn't re-queue
the same batch) and that a real resend require an explicit SweetAlert2
confirm, not fire silently. Added a `queued` bool on `CampaignBuilder`
checked/set at the top of `confirmAndQueue()` (no-op past the first
call for that component instance), and wrapped the "Confirm & Send"
button in a SweetAlert2 confirm (Alpine `x-on:click`, ships bundled
with Livewire — no new CDN) that only calls `$wire.confirmAndQueue()`
on confirm. The per-recipient "Retry" button on a failed row got the
same `data-confirm="..."` treatment the app's delete/deactivate forms
already use (delegated listener in `components/layout.blade.php`).
Also: the Campaigns list badge just said "Sending" with no indication
of progress on a large batch — `CampaignController::index()` now pulls
a `sent_count` alongside the existing `recipients_count` via a second
`withCount()` closure, and the list shows "Sent 41 of 75" instead of a
static label while `status` is `sending`/`queued`.

### Live incident: NIC/mGovCloud (Zoho) blocked the Task Force sending mailbox (2026-08-20, ongoing — not a code bug)

The 7 recipients that failed at the tail of the 2026-08-20 75-district
campaign (see the "campaign status never left queued" entry above)
were initially assumed to be `throttle_seconds` (4s) tripping Zoho's
per-message rate limit — bumped `redacted-account@example.gov.in`'s
`throttle_seconds` to 20 and re-queued all 7 with a matching 20s stagger
via `SendCampaignRecipientMail::dispatch(...)->delay(...)` (one-off,
run directly in tinker — no new UI for this). All 7 failed again with
the *identical* `550 5.4.6 Unusual sending activity detected` error
despite proper spacing, which ruled out a simple rate-gap problem. A
follow-up test send confirmed the real cause: Zoho had escalated to a
hard account-level block — bounces now show `554 5.1.8 Sender Address
Blocked` (confirmed by the user directly in mGovCloud Workspace's
mailer-daemon bounce notices). This is entirely Zoho/NIC-side; nothing
in this app caused or can fix it. **Left the 7 recipients as `failed`
and stopped sending anything further from this account** — repeated
attempts while flagged risk extending the block rather than clearing
it. User is following up directly with the department's DA admin and
NIC (mGovCloud usage-policy page: `mgovcloud.in/mail/help/usage-
policy.html`) to get the block lifted and ask about a standing
higher-volume allowance, since this section needs to send to many
recipients regularly. No code change needed once that's granted — just
raise `throttle_seconds` / set `daily_send_cap` on that Mail Account
row (Admin → Mail Accounts) to whatever NIC approves.

### Bullet-list templates rendering as numbered lists in real inboxes (2026-08-21, done)

User reported mail templates with bullet lists sent as "1. 2. 3." in real
inboxes. Root cause: Quill (the template editor's rich-text component)
fakes bullet rendering with CSS on `<li data-list="bullet">` inside an
`<ol>`, plus an empty `<span class="ql-ui">` marker — only looks like a
bullet inside Quill's own editor stylesheet; sent as raw HTML with no
Quill CSS in the recipient's inbox, the `<ol>` renders as a plain numbered
list. Added a `body` set-mutator on `MailTemplate`
(`sanitizeQuillBody()`, DOMDocument/DOMXPath-based) that swaps a bullet
`<ol>` for a real `<ul>` with inline spacing styles (email clients ignore
`<style>` blocks), strips the `ql-ui` marker spans, and leaves genuine
`<ol data-list="ordered">` numbered lists alone. Runs on every write
(`store()`/`update()` both funnel through `MailTemplate::create()`/
`update()`), not just the create form.

### Campaign detail page: sort/filter/search, slug URLs, failed_at (2026-08-21, done)

User asked for delivered/failed counts and a per-recipient retry on the
per-campaign page — most of this already existed
(`CampaignController::show()`, `resources/views/campaigns/show.blade.php`)
from an earlier session, just wasn't linked/known about. Added on top:
status-filter stat cards (click Waiting/Sent/Failed/Total to filter),
sortable column headers (name/email/status/sent_at), and a name/email
search box, all via query-string params so links/pagination stay
shareable. Separately, user flagged campaign URLs exposing sequential ids
(`/campaigns/2`) — added `campaigns.slug` (random-suffixed on create, not
the row id), `Campaign::getRouteKeyName() === 'slug'`, backfilled the two
existing rows; `route('campaigns.show', $campaign)` calls elsewhere picked
this up automatically, no other view changes needed. Also added
`campaign_recipients.failed_at` (stamped in `SendCampaignRecipientMail`'s
catch block, cleared on retry) since a failed row's "Sent At" column was
just a blank dash with no indication of *when* it failed — now shows
"Failed <timestamp>" instead. Rebuilt `campaigns/sent-mail.blade.php` the
same way (it wasn't actually mislabeling failed sends as sent — the query
already filtered `status='sent'` correctly — but silently omitting failed
rows with zero indication read as "broken"; now shows every status with
the same stat-card/sort/filter/search treatment as the per-campaign page).

### Custom branded error pages (2026-08-21, done)

`php artisan vendor:publish --tag=laravel-errors` as the base, then
replaced the stock views with an `<x-error-page>` component matching
`excise-budget-tracker`'s and `pdf-markdown-pipeline`'s pattern exactly —
same icon/heading/message/single-CTA shape, reusing this app's own
`x-head` for favicon/title/dark-mode-flash handling.
401/402/403/404/419/429/500/503 all covered.

### Full security audit + DB-transaction atomicity pass (2026-08-21, done)

User asked for a full security audit against the same checklist already
run on `excise-budget-tracker`/`pdf-markdown-pipeline`, plus a check of
how `mail_accounts.app_password` is stored and whether the app is
exploitable via URL params/SQL injection. Full findings and fixes in
`SECURITY.md` — most consequential: **production `.env` had
`APP_ENV=local`/`APP_DEBUG=true`**, caught by the user mid-session — any
unhandled exception on this public-facing app would have rendered
Laravel's Whoops debug page, dumping `APP_KEY`/`DB_PASSWORD`/
`RESEND_API_KEY` straight into the response. Fixed, along with: no
security response headers/CSP anywhere (added `SecurityHeaders`
middleware, same pattern as both sibling apps); `SESSION_ENCRYPT`/
`SESSION_SECURE_COOKIE`/`SESSION_SAME_SITE` all unset or insecure (the
login OTP was sitting in plaintext in the `sessions` table); Livewire's
global `upload-file` endpoint had no `auth` middleware at all (3
components in this app use `WithFileUploads`); three Livewire components
had no in-component privilege re-check (not independently exploitable —
Livewire signs snapshots with a checksum — but inconsistent with this
app's own convention elsewhere); `routes/web.php` had repeated per-route
middleware arrays instead of proper `Route::middleware()->group()`
blocks. Also added the 7-day rolling session + remember-me both sibling
apps run (`SESSION_LIFETIME=10080`) — the checkbox/controller
wiring/`remember_token` column already existed, purely a missing `.env`
setting. Confirmed **PASS** on: CSRF, SQL injection (Eloquent/Query
Builder throughout, one static-string `selectRaw()`), mass assignment
(`#[Fillable]` on every model), `mail_accounts.app_password` encryption
(`encrypted` cast + `#[Hidden]`), zip-slip (PHP's `ZipArchive::extractTo()`
has built-in protection), upload validation, and `.env`'s git-ignore
status. Same-day follow-up: audited every write path for the
`DB::transaction()`+`try`/`catch` atomicity convention both sibling apps
use for multi-step writes — found and fixed two spots making two related
writes without tying them together (`SendCampaignRecipientMail::handle()`,
`CampaignController::retryRecipient()`); re-confirmed simple single-model
CRUD controllers correctly don't need it, matching the sibling apps' own
convention.

### Manual recipient entry on Recipient Lists (2026-08-21, done)

User asked for a way to add recipients directly (form/table input) instead
of always needing an Excel upload — same idea as the manual comma/newline
email textarea already in `CampaignBuilder`/`TestEmailSender`. Considered
putting this on `/recipients` (the Zone/Division/District directory) but
that page is deliberately the fixed 5/18/75 org hierarchy (CLAUDE.md), not
an addable list — confirmed with the user and built it into
`/recipient-lists/create` instead, alongside the existing file-upload
wizard. `RecipientListImportWizard` gained a `mode` toggle ("Upload File" /
"Type Manually") and a `saveManual()` action: a textarea, one recipient per
line as "Name, email" or a bare email, parsed the same trim/filter/dedupe
way as `CampaignBuilder::parsedManualEmails()`, saved via the same
privilege-check + `DB::transaction()` convention as the rest of this
session's write-path audit. `recipient_lists.source_type`'s migration
comment already anticipated a `'manual'` value — no schema change needed.

### Route-order regression: /campaigns/test-send and /campaigns/create 404ing (2026-08-21, fixed)

User reported SuperAdmin getting a 404 on `/campaigns/test-send`. Traced
to the earlier same-day route-groups cleanup: `campaigns/{campaign}` (the
slug wildcard show route) got registered *before* the literal
`campaigns/create` and `campaigns/test-send` routes. Laravel matches
routes in registration order, so both literal paths were being captured
by the wildcard first — "test-send"/"create" got treated as a campaign
slug, 404ing inside `CampaignController::show()` before privilege
middleware (even SuperAdmin's bypass) ever got a chance to run. Reordered
`routes/web.php` so both literal-path groups register before the
wildcard — `php artisan route:list` and a live `curl` both confirm
`302` (auth redirect) instead of `404` now.

### Resend to a different email address, from a 'sent' row too (2026-08-21, done)

User flagged that some district/division/zone officer mailboxes are dead
— their relay accepts the message with a `250` and the recipient never
reads it, which looks identical to a real delivery in this app (no
bounce, `status = sent`). The existing per-recipient "Retry" only worked
on `failed` rows and always resent to the same (bad) address. Added
`CampaignController::resendToEmail()` + a per-row "Resend to different
email…" toggle (Alpine `x-show`, no new JS dependency) available on both
`sent` and `failed` rows: type a corrected address, optionally check
"Also save as the on-file email" to write it back onto the underlying
zone/division/district/recipient-list-item
(`CampaignRecipient::saveEmailToDirectory()`, maps `recipient_type` to
the right model + column — `jc_email`/`dc_email`/`deo_email`/
`RecipientListItem.email`) so future campaigns pick up the fix too, not
just this one resend. Checkbox hidden for `manual` recipients (no backing
directory row). Wrapped in the same `DB::transaction()`+`try`/`catch`
convention as `retryRecipient()`.

### recipients.manage privilege, auto-submit filters, auto-refresh (2026-08-21, done)

User (an `Admin`-role account, not `SuperAdmin`) reported no edit option
on `/recipients` at all. Root cause: the zone/division/district Edit
links and the Officer Directory import wizard were gated on `isAdmin()`
(`role === 'SuperAdmin'` only) — the one spot in this app not using the
granular `privilege:X` convention everything else uses (campaigns.send,
templates.manage, etc.). Added `recipients.manage` to `User::PRIVILEGES`,
switched the `recipients` route group + Blade guards +
`OfficerDirectoryImportWizard`'s in-component checks to it, granted it to
the existing Admin-role account. Also: search boxes and status-filter
dropdowns on the campaign detail and Sent Mail pages now auto-submit
(Alpine debounce) instead of needing a Filter click, and both pages
auto-refresh every 6s while a recipient is still pending/queued — user
reported a resend showing "Waiting" until manual reload even though Zoho
had already accepted it; queue workers were fine (no failed jobs), it was
purely that a static Blade page has nothing telling it the job finished.

### IST timestamps, per-recipient toast, manual mark-as-sent (2026-08-21, done)

Three follow-ups from the auto-refresh work above. (1) Every displayed
timestamp was raw UTC — kept storage in UTC (correct for
sorting/DST-safety, and changing `config('app.timezone')` outright would
have silently mislabeled every already-stored row by -5:30, since MySQL
datetime columns carry no tz info and Laravel doesn't auto-convert on
read) and instead added `Carbon::macro('ist')` in `AppServiceProvider`,
converting all 10 `->format('d M Y...')` call sites across 7 views to
display-only `->ist()->format(...)`. (2) The auto-refresh toast only
compared aggregate counts, so it just said "1 sent" with no way to tell
which recipient — rebuilt to snapshot a per-recipient id→status map in
sessionStorage instead and name the actual email(s) that changed:
"Sent: a@x.com — Failed: b@y.com". (3) Added "Mark as sent" on failed
rows (`CampaignController::markAsSent()`) for a recipient the section
actually emailed manually from their own Zoho/Gmail inbox instead of
through this app — clears the failed state and stamps `sent_at` without
dispatching another automated send.

### Resend with the corrected attachment — Shravasti/Bhadohi (2026-08-21, done + live-fixed)

User reported Shravasti and Bhadohi never got the attached file, while
everyone else did. Root cause: `ZipRecipientMatcher`'s fuzzy match
missed "DEO - Shravasti" against `5_Shops_SHRAWASTI.xlsx` (spelling) and
"DEO - Bhadohi" against `5_Shops_SANT_RAVIDAS_NAGAR_BHADOHI.xlsx`
(official district name) at original queue time — both sent with
`attachment_path=null`, `matched_via='none'`, no error, `status='sent'`
(a silent miss, not a failure). Neither existing resend action gave any
way to fix it, so retrying just repeated the same no-attachment send.
The extracted zip directory is never cleaned up after a campaign
completes, so the actual files were still on disk. Added a "No
attachment" badge on any `zip_per_recipient` row missing one, and
extended the resend form with an attachment `<select>` listing every
file the campaign's zip actually extracted —
`CampaignController::resendToEmail()` validates the choice server-side
against the campaign's own extracted files only. Verified live against
the real campaign (not just tested): both recipients now show the
correct `attachment_path` and `status=sent`; confirmed 0 of 76
recipients in that campaign are left without an attachment.

### Search + sort on /recipients (2026-08-21, done)

Zones/Divisions/Districts tables had no search or sort — just three
plain, alphabetically-fixed tables (5/18/75 rows each). Added an
auto-submitting search box (matches name/officer name/officer email for
the active tab) and sortable Name/Officer Name/Officer Email column
headers, matching the query-string-driven pattern already used on the
campaign detail and Sent Mail pages. Per-tab column mapping
(`jc_name`/`dc_name`/`deo_name` etc.) lives in one place in
`RecipientController::index()` so search and sort can't drift out of
sync with each other.

### Route-order regression: /recipient-lists/create 404ing (2026-08-21, fixed)

Same bug class as the earlier `/campaigns/{campaign}` regression (M-session
2026-08-21): `recipient-lists/{recipientList}` (the `show` route) was
registered before the literal `recipient-lists/create` route, so Laravel
matched "create" as a `{recipientList}` route-model-binding id first, failed
to find a row, and 404'd — for every user, not just the reported
account. `recipients.import` already correctly gates both the "Add
Recipient" link and every write action in `RecipientListImportWizard`
(single manual entry, pasted list, and file upload all go through the same
privilege check), and the affected user already had that privilege — the
404 was the actual root cause, not a missing privilege. Fixed by moving the
`create`/`destroy` group before the `{recipientList}` show route.

### Quill editor not mounting on first wire:navigate visit to templates/create (2026-08-21, fixed)

Reported as "the campaign creator body bug is still there... only on page
reload" despite earlier attempts. Root cause, found by reading Livewire's own
`navigate.js` (`prepNewBodyScriptTagsToRun`): every `<script>` tag in a page
swapped in via `wire:navigate` gets cloned and re-inserted via
`element.replaceWith()` so it actually executes (no dedup unless
`data-navigate-once` is set) — but a `<script src>` tag inserted this way
loads **asynchronously**, unlike the same tag parsed synchronously by the
browser on a real/hard page load. `templates/_editor.blade.php` loaded
`quill.js` and then immediately ran `new Quill(...)` in the very next
`<script>` tag, which raced the async load and silently threw
`Quill is not defined` on a from-SPA-navigation visit — while a hard reload
(synchronous parser-driven script execution) always happened to win the
race, which is why every previous "fix" attempt (that never touched this
file) appeared to work once reloaded and regressed again from a fresh
`wire:navigate` visit. Fixed by loading `quill.js` on demand with an
`onload` callback instead of assuming it's already loaded.

### Recipient Lists / Recipients nav cleanup + resend directory checkbox (2026-08-21, done)

Investigated "recipient list not updated" report (e.g. Bahraich) by tracing
`resendToEmail()`/`CampaignRecipient::saveEmailToDirectory()` end to end and
testing it directly in a rolled-back transaction — the write path itself was
already correct (confirmed working for 4 of that day's 10 resends; Bahraich
specifically had never actually been resent, only originally sent). The
likely real issue: the "Also save as the on-file email" checkbox is easy to
miss/leave unchecked, so a typed correction only fixes that one send instead
of the directory. Now defaults to checked (opt-out, not opt-in). Separately,
`/recipient-lists`'s single "Import List" button hid manual entry inside a
tab toggle on the next page — split into two explicit buttons ("Import
File" / "Add Recipients Manually", the latter deep-linking to the wizard's
manual tab via `RecipientListImportWizard::mount(string $mode)`), and added
cross-linking Zones/Divisions/Districts/Ad-hoc-Lists tabs to both this page
and `/recipients` so the fixed directory and ad-hoc lists no longer feel
like two unrelated sections.

### IMAP reply fetching for campaigns (2026-08-21, done)

Added an opt-in IMAP integration so a section can see replies to a campaign
without leaving the app. Each `mail_accounts` row gets an optional
`imap_host`/`imap_port` (Gmail preset: `imap.gmail.com:993`; NIC's mGovCloud
preset: `imap.mgovcloud.in:993`, per NIC's own published IMAP settings at
mgovcloud.in/mail/help/imap-access.html — same SSL-on-993 shape as Gmail,
same login as its SMTP credentials) — `webklex/php-imap` v6 (pure-PHP, no
`ext-imap` needed; this host doesn't have it enabled) is what talks IMAP.
`SendCampaignRecipientMail` now captures the
outgoing `Message-ID` (`Illuminate\Mail\SentMessage::getMessageId()`) onto
`campaign_recipients.message_id`. A "Check for replies" button on
`/campaigns/{campaign}` (manual only — this host has no cron/scheduler wired
up, so automatic polling was deliberately left out of this first cut) runs
`ImapReplyFetcher`: one IMAP `SINCE` query against INBOX (bounded to since
the account's last fetch, or 90 days back on a first run), then in-memory
matches each message's `In-Reply-To`/`References` headers against this
account's known outgoing `message_id`s — a reply only surfaces if it's
actually threaded to something this app sent; nothing else in the inbox is
touched, read, or marked seen (`leaveUnread()`). Matches save to a new
`campaign_replies` table and show inline as an expandable thread under the
matching recipient row.

### Dashboard: clickable stats/campaigns, more stat cards, Chart.js send-volume chart (2026-08-21, done)

Recent-campaign rows now link to `/campaigns/{campaign}` (same
name-cell-links-out pattern as `campaigns/index.blade.php`, for
consistency), and the four org-hierarchy stat cards (Zones/Divisions/
Districts/Recipient Lists) link to their respective list pages. Added four
more stat cards (Total Sent, Failed Sends, Mail Templates, kept Active Mail
Accounts) and a "last 14 days" send-volume line chart via Chart.js
(`cdn.jsdelivr.net`, already CSP-whitelisted for Quill/SweetAlert2 — no
`SecurityHeaders` change needed). `Dashboard::sendVolumeByDay()` zero-fills
every day in the window so a quiet day shows as a real gap, not a missing
point. A quick-actions row (New Campaign / Import Recipients / New
Template, each privilege-gated the same as their own page's button) sits
above the stats. The Chart.js `<script src>` load hits the exact same
`wire:navigate`-async-load race as the Quill editor bug — fixed with the
same load-then-init pattern (`templates/_editor.blade.php`), plus a
`Chart.getChart(canvas)?.destroy()` guard since re-running this script on
every SPA visit would otherwise throw "Canvas is already in use" on the
second visit to `/dashboard`.

### Flash messages: richer text, toasts moved off SweetAlert2 (2026-08-21, done)

Two separate things, both from the same report ("why doesn't a district
resend get as good a notification as the test-email send?"): first, the
retry/resend/fetch-replies flash messages didn't name which mail account
they went through, unlike `TestEmailSender`'s "Test email sent to X via Y" —
now they do (`CampaignController::retryRecipient/resendToEmail/
fetchReplies`). Second, the two client-side "Sent: X — Failed: Y" toasts
that detect an in-flight send finishing while the page is open
(`campaigns/show.blade.php`, `campaigns/sent-mail.blade.php`) used
SweetAlert2 — switched to `window.flasher.success()/.error()` so every
notification in the app (server flash and this client-only one) renders
through the same self-hosted `php-flasher` theme instead of two different
notification libraries. `@flasher_render` (`layout.blade.php`) loads
`window.flasher` asynchronously on every page regardless of whether a flash
is queued, so calling it from a `DOMContentLoaded` handler hits the same
CDN/async-load race as the Quill and Chart.js fixes above — handled here
with a short capped poll instead of a duplicate `<script>` tag, since
`@flasher_render`'s own script already guarantees the load. SweetAlert2
stays for the two things flasher structurally can't do — pre-submit
confirmation modals (`data-confirm`, `layout.blade.php`) and the template
preview modal (`templates/_editor.blade.php`) — flasher is a toast library
only, with no confirm/dialog concept.

### Manual "responded" tracking + XLSX/PDF export on the campaign page (2026-08-23, done)

IMAP reply fetching needs NIC approval that hasn't landed yet, so until then the only way to
know a district actually mailed a file back is to look in the section's own inbox by hand — this
adds a manual tick per recipient so that doesn't also require a side spreadsheet. New
`campaign_recipients.responded_at` (nullable timestamp) — deliberately independent of the
IMAP-derived `campaign_replies` rows, not a replacement for them; both signals coexist once IMAP
is unblocked. `/campaigns/{campaign}` gets: a tick-button per row (submits a one-row form,
toggles `responded_at` between `now()`/`null`, `campaigns.mark-responded`), "mark all responded /
not responded" for the current page in one click, a Responded stat card + filter dropdown
alongside the existing status filter, and an Export dropdown (Excel via the already-installed
`openspout` writer, reusing `RecipientController::downloadTemplate()`'s
`streamDownload`+`Writer::openToFile('php://output')` pattern; PDF via newly-added
`barryvdh/laravel-dompdf`) that both respect whatever status/responded/search filter is currently
applied. All of it gated behind the existing `campaigns.send` privilege, same as retry/resend/
mark-sent; the bulk/per-row updates scope through `$campaign->recipients()->whereIn(...)`, so a
crafted recipient id from a different campaign can't be touched.

Also audited this codebase against `pdf-markdown-pipeline`'s M87–M89 incidents (nested `<form>`
mangling a Save into a DELETE, `@flasher_render` never echoing, `Collection::groupBy()` dropping
privilege keys) since they'd just been fixed there. Found and fixed one live instance of the M89
class here: `admin/users/edit.blade.php` nested a "Resend activation link" `<form>` inside the
"Save Changes" `<form>` — invalid HTML that made the browser close the outer form early at the
inner form's `</form>`, silently dropping Designation/Post/Role/Privileges out of the actual
submit for any unactivated user. Fixed with the same standalone-sibling-form + `form="id"` button
pattern used in the sibling repo. The other two bug classes were checked and don't apply here: a
real end-to-end test (queue a flash, render the next page, assert the text appears) passed both
before and after touching `@flasher_render`, confirming this app's flasher rendering already
works correctly (unlike the sibling app) — no change made; and the privilege-checkbox partial
here (`admin/_privilege_checkboxes.blade.php`) uses a plain associative array, never
`Collection::groupBy()`, so it was never exposed to that bug either.

### Sidebar-collapse flash on hard reload; hide resend once responded (2026-08-23, done)

Reported while auto-searching on `/campaigns/{campaign}` (a plain GET form, debounced
auto-submit — a genuine hard reload, not a `wire:navigate` SPA swap): the sidebar visibly
collapsed a moment *after* the page painted expanded. Root cause, confirmed against
`vendor/livewire/livewire/dist/livewire.js`: the `navigate` Alpine plugin fires
`alpine:navigated` (forwarded as `livewire:navigated`) via an unconditional `setTimeout` on
*every* page load, hard reloads included — not only SPA transitions as an earlier note in this
file assumed. The sidebar's collapse-state restore only ran inside that listener, and the server
always rendered `sidebar-expanded` by default, so every hard reload painted expanded first, then
animated shut via the `#sidebar` width transition once the listener fired. Fixed the same way
`color_scheme` avoids the equivalent dark-mode flash: `toggleSidebar()` now also mirrors
`sidebar_collapsed` into a cookie, and `sidebar.blade.php` reads that cookie server-side to
render the correct class (and toggle icon/tooltip) on the very first paint — the client-side
listener still runs afterward but finds nothing to change, so no more visible animation.

Also, once a recipient is manually marked "responded", the "Resend / fix attachment" button and
its form are hidden for that row (a small "Responded — resend hidden" note takes its place) —
no reason to offer a resend once the section has confirmed the file arrived. Retry/"Mark as
sent" for a genuinely `failed` send stay available either way, since responding doesn't change
whether the send itself succeeded.

### Campaign detail page converted to a real Livewire component; sidebar-cookie fix that actually works (2026-08-23, done)

Two things landed together, and the second explains why the previous sidebar-flash fix didn't
actually hold up under a real hard reload:

**Root cause of "the flash still happens even after hard refresh":** `sidebar_collapsed` (and
`color_scheme`) are set via raw `document.cookie = ...` in JS — plaintext, unencrypted. Laravel's
`EncryptCookies` middleware tries to **decrypt every incoming cookie by default**, and on failure
(a plaintext cookie can't decrypt) it silently nulls the value out server-side
(`Illuminate\Cookie\Middleware\EncryptCookies::decrypt()` — confirmed by reading the vendor
source directly, not assumed). So `request()->cookie('sidebar_collapsed')` was *always* null no
matter what the real cookie said, meaning the previous fix's server-rendered class never actually
applied — dark mode only ever worked because `head.blade.php`'s synchronous inline script reads
`localStorage` directly and never depended on the cookie being readable server-side at all. Fixed
properly this time: `AppServiceProvider::boot()` now calls `EncryptCookies::except(['color_scheme',
'sidebar_collapsed'])` — both are plain UI preferences, never session/auth state, so there's no
security downside to leaving them unencrypted — and `request()->cookie('sidebar_collapsed')`
genuinely reflects the real value now (verified end-to-end via a raw kernel request with the
cookie set).

**The bigger fix — `/campaigns/{campaign}` is now a full Livewire component
(`App\Livewire\CampaignShow`), not a controller + plain Blade page.** This was flagged mid-session:
this app is Livewire-first by design (CLAUDE.md's "UI conventions" previously said the opposite —
corrected), and the page's plain `<form method="POST">`/GET-auto-submit actions (search,
retry/resend/mark-sent, the new responded tick, fetch-replies) were each doing a full browser
round-trip — which is *why* the sidebar flash was visible there specifically (a hard reload,
not a `wire:navigate` SPA swap), and separately why the debounced search auto-submit dropped
input focus every time it fired. Converted the whole page: `CampaignController::show()`,
`retryRecipient()`, `markAsSent()`, `resendToEmail()`, `fetchReplies()`, and `markResponded()` are
gone — their logic now lives as `CampaignShow` methods (`retry`, `markSent`, `resend`,
`toggleResponded`, `bulkMarkResponded`, `fetchReplies`), each independently privilege-checked per
the existing L-02 convention (SECURITY.md), each recipient resolved through
`$this->campaign->recipients()->findOrFail($id)` so a cross-campaign id 403s. Search/status/
responded-filter/sort are `#[Url]`-bound public properties (`wire:model.live.debounce` on
search) — filtering now happens via Livewire's AJAX diffing, no navigation, no lost focus, no
sidebar re-render at all. The old sessionStorage-diff + `setInterval(reload)` auto-refresh hack
is gone too, replaced by `wire:poll.6s="$refresh"` gated on `$hasInFlight` — Livewire morphs just
the changed rows in place instead of reloading the whole page. The reply-thread expand/collapse
toggle deliberately stayed plain Alpine `x-data` (no server round-trip needed for showing
already-loaded data — Livewire and Alpine coexist by design, not everything needs to be a wire
call). `campaigns/show.blade.php` deleted; `resources/views/livewire/campaign-show.blade.php` is
the new view. `POST /campaigns/{campaign}/recipients/.../retry|resend|mark-sent`,
`/fetch-replies`, and `/recipients/mark-responded` routes are gone — those actions go through
Livewire's own update endpoint now. `GET /campaigns/{campaign}/export/{format}` stays a plain
controller route (a file download always breaks out of SPA flow, in any framework — converting
it would be pointless). Added `tests/Feature/CampaignShowSmokeTest.php` (full page render +
`toggleResponded`/search exercised via `Livewire::test()`) since this component now carries real
privilege-gated mutation logic that had zero test coverage as a controller either.

**Not yet migrated, flagged for a future pass, not done silently in this one:** admin CRUD
(sections/mail accounts/designations/users) and the auth flow (login/OTP/onboarding) are still
plain Blade + controllers. CLAUDE.md's UI conventions section now says admin CRUD should move to
Livewire "as those pages are next touched" rather than staying plain by default — auth intentionally
stays as-is (no real interactivity to gain). Whether to proactively migrate admin CRUD now, in one
pass, versus opportunistically as each page is touched, is an open question for the user to weigh
in on — it's a much larger, multi-page undertaking than this session's scope.

### Admin CRUD (sections/mail accounts/designations/users) converted to Livewire too (2026-08-23, done)

Follow-up to the above, same session — the user clarified the app was meant to be Livewire-first
end to end from the start, not something to weigh opportunistically, so all four admin CRUD
resources moved over now rather than "as next touched." Also clarified a misconception worth
recording: converting `<form method="POST">` actions to `wire:click`/`wire:submit` does **not**
move anything into the browser URL or widen the attack surface — every Livewire action still goes
over POST to Livewire's own CSRF-protected endpoint, carrying a signed+encrypted component
snapshot, not query-string data; only the `#[Url]`-bound filter properties (already used on
`CampaignShow`) touch the URL at all, and those are the same plain GET filters the old pages
already used.

Each resource got two full-page Livewire components under `app/Livewire/Admin/` —
`{Resource}Index` (list + delete via `wire:click`+`wire:confirm`) and `{Resource}Form` (handles
both create and edit: `mount(?Model $model = null)`, same pattern CampaignShow already
established). `SectionController`, `MailAccountController`, `DesignationController`,
`UserManagementController`, and all 8 of their `Store*Request`/`Update*Request` FormRequest
classes are deleted — their validation logic (including the security-relevant bits: a
`users.manage`-only actor can't self-escalate to SuperAdmin or grant a privilege they don't
themselves hold, blank `app_password` on Mail Account edit means "keep the existing one") moved
into each `*Form`'s own `save()` method, verified by a dedicated escalation-guard test rather than
just re-typed and trusted. `admin/_privilege_checkboxes.blade.php` (shared by Designations and
Users) now binds via `wire:model` instead of a plain `name="x[]"` array — no other caller was left
on the old pattern. Designation-privilege autofill (picking a designation fills in its default
privileges) moved from client-side JS reading a `data-privileges` attribute to a real Livewire
`updatedDesignationId()` hook — one less place client and server state could drift.

Route names are unchanged (`admin.sections.index` etc. — the sidebar links needed zero changes)
but `store`/`update`/`destroy`/`resend-activation` routes are gone entirely, same shape as
`CampaignShow`'s conversion: only `index`/`create`/`edit` remain as GET routes mounting a
component, each still gated by the existing `privilege:X` route middleware for the page load
itself, with every mutating method additionally `abort_unless`-checking its own privilege inside
the component (L-02 pattern, SECURITY.md) since Livewire's own update endpoint doesn't run route
middleware. Mail Accounts kept its provider-preset (Gmail/NIC/custom) JS entirely client-side —
setting `.value` via JS doesn't fire the native `input` event `wire:model` listens for, so the
preset-switch handler now explicitly dispatches one after every programmatic value change
(`setAndSync()` helper in the view) or the visible field and the component's actual state would
silently disagree.

**Two real bugs found and fixed while building this, not present in the final commit:** (1)
`MailAccountForm::mount()` used `$mailAccount->prop !== null ? ... : ...` (no null-safe `?->`) on
two fields — harmless-looking but threw a "read property on null" warning in create mode, since
unlike the `??`-based lines beside it, a bare ternary doesn't get PHP's null-property-access
suppression; (2) the provider-preset hint strings were interpolated via `@js()` *inside* a `{{ }}`
Blade echo, which doesn't compile — `@js()` and `Js::from()` need to be called as a plain
expression, not nested inside another directive's output. Both caught by a real `Livewire::test()`
round-trip before commit, not just a syntax check — the second one in particular would have passed
`php -l` cleanly and only broken at runtime.

Added `tests/Feature/AdminCrudSmokeTest.php`: full create→edit→delete round-trip for Sections,
a privilege-checkbox round-trip for Designations, a create-then-blank-password-edit round-trip for
Mail Accounts (confirms the password truly isn't overwritten), a User create + the exact
privilege-escalation-guard scenario (a non-admin `users.manage` holder attempting to grant
SuperAdmin or an unheld privilege, both must fail validation), and a real-HTTP render check of all
8 index/create pages for an authenticated SuperAdmin.

**Not yet done — pick up here, in order:**

1. Live-updating campaign status (currently `/campaigns/{campaign}` is a
   plain paginated table you have to manually refresh) — `wire:poll` on a
   small Livewire status component would match plan.md's "status page
   polls for progress" note.
2. Proper Pest tests per plan.md's Verification section (invite→OTP flow,
   RBAC cross-section denial, CSV/XLSX/PDF import round-trip) — only
   `ZipRecipientMatcherTest` exists so far; the app is actually wired for
   plain PHPUnit (`tests/Unit/ExampleTest.php` is PHPUnit-style, not
   Pest, despite `pestphp/pest-plugin` being in composer.json) — worth
   deciding one way or the other rather than mixing. Also worth a test for
   `RecipientImportParser` and for the Blade `{{ }}`-in-`{{ }}` footgun
   (see M4) now that it's bitten twice in this codebase.
3. **Apache vhost on port 8082** — needs `sudo` (this session didn't have
   it; ask the user for it directly, or have them run the vhost-creation
   commands themselves). Once done: switch `~/.cloudflared/mailer-config.yml`
   ingress from `http://127.0.0.1:8000` to `http://127.0.0.1:8082`, add
   `storage`/`bootstrap/cache` to the Apache sandbox override's
   `ReadWritePaths=` (same gotcha budget-tracker hit), retire
   `up-excise-mailer-app.service` (Apache/php-fpm replaces `artisan
   serve` at that point, matching how the two sibling apps run — see the
   2026-08-20 systemd section above for why this app currently still
   needs an app-serving unit that they don't), write `DEPLOY.md`.
