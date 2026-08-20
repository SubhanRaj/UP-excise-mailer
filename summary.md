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
  `0b93ba71-3f28-4fb7-926f-3672d47ea8ad`), `~/.cloudflared/mailer-config.yml`
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
- End-to-end verified: invite email sent to shubhanraj2002@gmail.com via
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

**Not yet done — pick up here, in order:**

1. Live-updating campaign status (currently `/campaigns/{campaign}` is a
   plain paginated table you have to manually refresh) — `wire:poll` on a
   small Livewire status component would match plan.md's "status page
   polls for progress" note.
2. Resend-failed / retry-recipient action on the campaign status page —
   right now a failed row just sits there with its error message.
3. Proper Pest tests per plan.md's Verification section (invite→OTP flow,
   RBAC cross-section denial, CSV/XLSX/PDF import round-trip) — only
   `ZipRecipientMatcherTest` exists so far; the app is actually wired for
   plain PHPUnit (`tests/Unit/ExampleTest.php` is PHPUnit-style, not
   Pest, despite `pestphp/pest-plugin` being in composer.json) — worth
   deciding one way or the other rather than mixing. Also worth a test for
   `RecipientImportParser` and for the Blade `{{ }}`-in-`{{ }}` footgun
   (see M4) now that it's bitten twice in this codebase.
4. **Apache vhost on port 8082** — needs `sudo` (this session didn't have
   it; ask the user for it directly, or have them run the vhost-creation
   commands themselves). Once done: switch `~/.cloudflared/mailer-config.yml`
   ingress from `http://127.0.0.1:8000` to `http://127.0.0.1:8082`, add
   `storage`/`bootstrap/cache` to the Apache sandbox override's
   `ReadWritePaths=` (same gotcha budget-tracker hit), convert
   `php artisan serve`/`queue:work` from manual nohup processes to systemd
   `--user` units (see `pdf-pipeline-queue.service`/`pdf-pipeline-queue2.service`
   for the pattern), write `DEPLOY.md`.
