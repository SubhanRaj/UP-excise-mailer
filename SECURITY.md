# Security Audit Report — UP Excise Mailer

**Audit date:** 2026-08-21
**Remediation date:** 2026-08-21
**Auditor:** Senior Web Application Security Architect (Claude Code)
**Stack:** Laravel 13, PHP 8.5, MariaDB, Cloudflare Tunnel (HTTPS), on-premise deployment
(`php artisan serve` behind `cloudflared`, no Apache vhost yet — see DEPLOY.md), public
internet-facing at `mailer.exciseup.in`.
**Scope:** Full application — auth/OTP, RBAC/privileges, campaign send pipeline (dynamic
per-account mailer), recipient import (CSV/XLSX/PDF, Livewire file uploads), zip-per-recipient
attachment matching, mail template editor, admin CRUD, activity log.

---

## Status Summary

| ID | Finding | Severity | Status |
|----|---------|----------|--------|
| C-01 | `APP_ENV=local` / `APP_DEBUG=true` on the live production `.env` | **CRITICAL** | **FIXED** |
| M-01 | No security response headers / Content-Security-Policy | MEDIUM | **FIXED** |
| M-02 | `SESSION_ENCRYPT=false` — OTP/login state stored in plaintext in the `sessions` table | MEDIUM | **FIXED** |
| M-03 | `SESSION_SECURE_COOKIE` unset despite production HTTPS deployment | MEDIUM | **FIXED** |
| M-04 | Livewire's global `upload-file` route had no `auth` middleware | MEDIUM | **FIXED** |
| L-01 | `SESSION_SAME_SITE` left at framework default (`lax`) | LOW | **FIXED** |
| L-02 | Three Livewire components (`OfficerDirectoryImportWizard`, `RecipientListImportWizard`, `CampaignBuilder::confirmAndQueue()`) had no in-component privilege re-check | LOW (defense-in-depth) | **FIXED** |
| L-03 | `SendCampaignRecipientMail::handle()` and `CampaignController::retryRecipient()` each made two related writes (recipient status + campaign status) without a shared transaction | LOW (atomicity) | **FIXED** |
| — | 7-day rolling session + remember-me was not configured (`SESSION_LIFETIME=120`, no sliding-session guidance) | — | **FIXED (hardening)** |
| — | `routes/web.php` repeated `['auth', 'privilege:X', 'throttle:mutations']` on individual routes instead of a shared group | — | **CLEANED UP (not a vuln)** |

All other areas audited (below) passed with no remediation required.

---

### C-01 · Production `.env` Had Debug Mode On

**Severity:** CRITICAL
**Status:** FIXED

**Finding:** the live `.env` for `mailer.exciseup.in` — a public internet-facing app — had
`APP_ENV=local` and `APP_DEBUG=true`. With `APP_DEBUG=true`, any unhandled exception (a bad
query, a missing route parameter, a third-party SMTP failure that escapes its `catch`, a 404 on
a malformed URL) renders Laravel's full Whoops debug page instead of the app's own error views:
complete stack trace with file paths and surrounding source code, the request's full
headers/session/cookies, and **all resolved `config()` values — including `APP_KEY`,
`DB_PASSWORD`, and `RESEND_API_KEY`** (config values are dumped as part of the debug page's
environment tab). Anyone who could trigger *any* exception on this app — trivial on a
public-facing site — could read the database password and mail API key directly out of the
error page.

**Fix applied:** `.env`: `APP_ENV=production`, `APP_DEBUG=false`. `.env.example` annotated
(`# PRODUCTION: must be 'false' — true leaks stack traces, .env values, SQL`) so a future
`cp .env.example .env` on a new environment doesn't silently regress this. Confirmed the custom
error pages added earlier this session (`resources/views/errors/*.blade.php`) now actually
render in production instead of being masked by the debug page.

**Files changed:** `.env`, `.env.example`.

---

### M-01 · No Security Response Headers / Content-Security-Policy

**Severity:** MEDIUM
**Status:** FIXED

**Finding:** zero security response headers were set anywhere in the stack — no
`X-Frame-Options`, `X-Content-Type-Options`, `Referrer-Policy`, `Permissions-Policy`, or
`Content-Security-Policy` — despite being reachable over the public internet via Cloudflare
Tunnel. Same gap independently found and fixed in both sibling apps
(`~/Sites/excise-budget-tracker` M-01, `~/Sites/pdf-markdown-pipeline` M-01).

**Fix applied:** `App\Http\Middleware\SecurityHeaders` (new), registered globally via
`$middleware->append(...)` in `bootstrap/app.php`, matching both siblings' exact pattern:

| Header | Value |
|--------|-------|
| `X-Frame-Options` | `SAMEORIGIN` |
| `X-Content-Type-Options` | `nosniff` |
| `Referrer-Policy` | `strict-origin-when-cross-origin` |
| `Permissions-Policy` | `camera=(), microphone=(), geolocation=(), payment=(), usb=()` |
| `X-Robots-Tag` | `noindex, nofollow, noarchive` (belt-and-braces on top of `public/robots.txt`'s existing `Disallow: /`) |
| `Content-Security-Policy` | `default-src 'self'` plus the exact CDNs this app loads — `cdn.tailwindcss.com` (Tailwind Play), `cdn.jsdelivr.net` (Quill editor + SweetAlert2), `fonts.googleapis.com`/`fonts.gstatic.com` (Google Fonts) |
| `Strict-Transport-Security` | `max-age=31536000; includeSubDomains` (HTTPS-only — not sent over plain HTTP so local `php artisan serve` dev is unaffected) |

`unsafe-inline` is required for Tailwind Play CDN and this app's inline `<script>` blocks
(theme anti-flash in `x-head`, Alpine directives). `unsafe-eval` is required because
Livewire 4 bundles Alpine.js, whose `x-data`/`x-init` expressions evaluate via `new Function()`.
Both are an accepted trade-off tied to the Play-CDN architecture (see `CLAUDE.md`'s UI
conventions) — narrowing further requires moving off the CDN entirely.

**Verified live:** `curl -sSI https://mailer.exciseup.in/login` — all six headers present.

**Files changed:** `app/Http/Middleware/SecurityHeaders.php` (new), `bootstrap/app.php`.

---

### M-02 · `SESSION_ENCRYPT=false` — OTP/Login State in Plaintext

**Severity:** MEDIUM
**Status:** FIXED

**Finding:** `SESSION_DRIVER=database` with `SESSION_ENCRYPT=false` meant every session's
payload — including the in-progress 6-digit login OTP (`otp.code`), its expiry, and
`login.id`/`login.remember` during the password→OTP window — was stored as plaintext
(base64-encoded, not encrypted) in the `sessions` table. Direct DB read access (a leaked DB
credential — see C-01 above, a backup file, a DBA) would expose a live, still-valid OTP code
without needing the victim's password or inbox. Same class of finding as
`excise-budget-tracker`'s M-03.

**Fix applied:** `SESSION_ENCRYPT=true` in `.env` (Laravel encrypts session payloads with
`APP_KEY` before writing to the DB); `.env.example` annotated
(`# PRODUCTION: must be 'true' — session table stores the login OTP`).

**Files changed:** `.env`, `.env.example`.

---

### M-03 · `SESSION_SECURE_COOKIE` Not Set

**Severity:** MEDIUM
**Status:** FIXED

**Finding:** `config/session.php`'s `'secure'` reads `env('SESSION_SECURE_COOKIE')`, absent
from `.env` and therefore `null` (falsy). This app is served over HTTPS in production
(Cloudflare Tunnel terminates TLS at the edge), but the session cookie wasn't marked `Secure` —
it would still be accepted over a plain HTTP connection if one were ever exposed (e.g. hitting
the `cloudflared` origin directly, or a future misconfiguration).

**Fix applied:** `SESSION_SECURE_COOKIE=true` in `.env`; `.env.example` annotated
(`# PRODUCTION (HTTPS): must be 'true' — prevents cookie over plain HTTP`).

**Files changed:** `.env`, `.env.example`.

---

### M-04 · Livewire's Upload-File Endpoint Had No Auth Middleware

**Severity:** MEDIUM
**Status:** FIXED

**Finding:** Livewire registers `POST livewire-{hash}/upload-file` globally at boot,
independent of any page-level route middleware. Its default config
(`temporary_file_upload.middleware => null`) falls back to Livewire's own default of
`'throttle:60,1'` — **no `auth` at all**. Three Livewire components in this app use
`WithFileUploads`: `OfficerDirectoryImportWizard`, `RecipientListImportWizard`, and
`CampaignBuilder` (zip-per-recipient attachments) — a real, exercised surface, not a
theoretical one. Until this fix, *any* visitor, logged in or not, could POST up to 12MB
(Livewire's own cap) to this endpoint, 60 times a minute per IP, landing in
`storage/app/private/livewire-tmp/`, regardless of whether they could reach any page that
actually uses a file upload. Identical finding, independently caught and fixed in
`excise-budget-tracker` (M-04) the same way.

**Fix applied:** `AppServiceProvider::boot()` now sets
`config(['livewire.temporary_file_upload.middleware' => ['auth', 'throttle:60,1']])` before
route registration — closes the endpoint to authenticated users while preserving the original
rate limit (setting a bare `'auth'` string would have silently *dropped* the throttle, since
Livewire's own default only applies when the config key is unset).

**Files changed:** `app/Providers/AppServiceProvider.php`.

---

### L-01 · `SESSION_SAME_SITE` Left at Framework Default (`lax`)

**Severity:** LOW
**Status:** FIXED

**Finding:** neither `.env` nor `.env.example` set `SESSION_SAME_SITE`, falling back to
Laravel's default `lax`, which still attaches the session cookie to top-level cross-site GET
navigations. This is a closed internal HQ tool with no OAuth redirect flows or legitimate
cross-site entry points.

**Fix applied:** `SESSION_SAME_SITE=strict` in `.env`; `.env.example` annotated. CSRF tokens
already protect all mutating requests regardless — this closes an additional
cookie-attachment vector.

**Files changed:** `.env`, `.env.example`.

---

### L-02 · Three Livewire Components Had No In-Component Privilege Re-Check

**Severity:** LOW (defense-in-depth — not independently exploitable, see below)
**Status:** FIXED

**Finding:** Livewire's `livewire/update` AJAX endpoint (where a mounted component's public
methods actually run) is registered with only `['web', RequireLivewireHeaders::class]`
middleware — **not** the mounting page route's own middleware. `mount()` only runs on the
initial page load; it does not re-run on the subsequent AJAX calls that invoke a component's
action methods. `CampaignBuilder::confirmAndQueue()` and `TestEmailSender` already guard against
this correctly (`abort_unless(auth()->user()->hasPrivilege(...))` inside the action method
itself, not just relying on the mounting route's middleware) — but `OfficerDirectoryImportWizard`
(`upload()`, `apply()`) and `RecipientListImportWizard` (`upload()`, `confirmMapping()`,
`save()`) had no such check anywhere, and `CampaignBuilder::confirmAndQueue()` itself checked
`canUseMailAccount()` (section ownership) but never the base `campaigns.send` privilege.

**Why this is LOW, not HIGH:** Livewire signs every component snapshot with a keyed checksum
(derived from `APP_KEY`) — a client cannot forge a snapshot for a component from nothing, or
tamper with its properties, without the server having issued that exact snapshot first. The
*only* way to obtain a valid snapshot for any of these three components is to have successfully
loaded the mounting page's initial `GET`, which **is** correctly gated (`is_admin` for the
officer directory import, `privilege:recipients.import` for the recipient-list wizard,
`privilege:campaigns.send` for the campaign builder). So this gap could not be exploited by a
user who couldn't already reach the page — it's an inconsistency with this codebase's own
established convention (`CampaignBuilder`'s mail-account check, `TestEmailSender`'s explicit
`mount()` + action-method checks), not a live bypass. Fixed anyway because relying solely on the
mounting route is fragile — a future refactor of the route grouping, or a privilege revoked
mid-session, has no independent backstop without this.

**Fix applied:** added the same `abort_unless(...)` pattern already used elsewhere in this
codebase to all five previously-unguarded methods.

**Files changed:** `app/Livewire/OfficerDirectoryImportWizard.php`,
`app/Livewire/RecipientListImportWizard.php`, `app/Livewire/CampaignBuilder.php`.

---

### L-03 · Two Related Writes Made Without a Shared Transaction

**Severity:** LOW (atomicity, not a security exploit — a crash mid-sequence produces an
inconsistent-but-recoverable state, not a privilege or data-disclosure issue)
**Status:** FIXED

**Finding:** audited every write path in the app for the same `DB::transaction()` + `try`/`catch`
convention already used correctly in `CampaignBuilder::confirmAndQueue()` (campaign +
recipients + job dispatch), `OfficerDirectoryImportWizard::apply()`, `RecipientListImportWizard::save()`,
and `Admin\UserManagementController::store()`/`update()` (matching `excise-budget-tracker`'s and
`pdf-markdown-pipeline`'s own convention: wrap **multi-step** writes that must succeed or fail
together, not every single-statement CRUD call — a lone `Model::create()`/`update()`/`delete()`
is already atomic at the database level, and both sibling apps' own simple resource controllers
(`DesignationController`, `SectionController`, equivalents) confirm this — they don't wrap
single-model CRUD either).

Two genuine gaps found, both making **two related writes** without tying them together:

1. `SendCampaignRecipientMail::handle()` — on success, `$recipient->update(['status' =>
   'sent', ...])` followed by `markCampaignCompletedIfDone()` (a separate `Campaign::update()`
   if no recipient is left pending); same shape in the `catch` branch for a failed send. If the
   process died between the two calls (worker killed, OOM, deploy mid-job), a recipient could be
   marked `sent`/`failed` while the campaign stayed `queued` forever even though it was actually
   the last recipient — the exact "stuck on queued" class of bug `DEPLOY.md` already documents a
   prior live incident of, just from a different cause.
2. `CampaignController::retryRecipient()` — `$recipient->update([...])` followed by
   `$campaign->update(['status' => 'queued'])`, with no `try`/`catch` at all — an exception
   between the two calls (or either one failing) left no way to tell the user anything went
   wrong; the request would 500 with the recipient possibly reset but the campaign not flipped
   back to `queued`, or vice versa.

**Fix applied:** both call sites now wrap just their two related writes in `DB::transaction()`
(so either both land or neither does), with `Mail::send()` in the job kept deliberately *outside*
the transaction — never hold a DB transaction (and its row locks) open across a slow network
call to an external SMTP relay, the same principle `pdf-markdown-pipeline`'s `SECURITY.md`
documents for its own file-move-after-transaction pattern. `retryRecipient()` gained a
`try`/`catch` matching `UserManagementController`'s convention — logs the failure and flashes an
error instead of a raw 500, with nothing partially changed since the transaction rolls back.

**Files changed:** `app/Jobs/SendCampaignRecipientMail.php`, `app/Http/Controllers/CampaignController.php`.

---

### Hardening — 7-Day Rolling Session + Remember-Me

**Status:** APPLIED

`SESSION_LIFETIME` was `120` (2 hours) with no sliding-session or remember-me configuration
documented, unlike both sibling apps (`excise-budget-tracker`, `pdf-markdown-pipeline`), which
deliberately run a 7-day sliding session for their actual deployment model — one government-PC
per officer, not a shared kiosk — so OTP fires on a genuine fresh login, not every visit.
`LoginController` already correctly threads `$request->boolean('remember')` through to
`Auth::login($user, $remember)`, the login form already has a `remember` checkbox, and
`users.remember_token` already exists in the base migration — this was purely a missing `.env`
setting, no code change needed. Set `SESSION_LIFETIME=10080` (7 days, resets on every
authenticated request, expires only after 7 days idle) and `SESSION_EXPIRE_ON_CLOSE=false`
(sessions survive browser restart) in `.env`, with matching guidance comments in `.env.example`.

**Files changed:** `.env`, `.env.example`.

---

### Cleanup — Route Groups (Not a Vulnerability)

`routes/web.php` repeated `->middleware(['auth', 'privilege:X', 'throttle:mutations'])` on
several individual routes (`campaigns.create`, `campaigns.test-send`,
`campaigns.test-send.prefill`, `campaigns.retry-recipient`) instead of grouping them the same
way the `recipients.*`, `recipient-lists.*`, and `admin.*` routes already do. Purely a
readability/DRY issue — every route already had the correct middleware, just spelled out
individually — but the repetition is exactly the shape that lets a route silently drift out of
sync with its siblings the next time someone adds a route nearby and forgets one item in the
array. Consolidated into `Route::middleware([...])->group(...)` blocks; `php artisan
route:list` confirms all 7 `campaigns.*` and 14 `recipients.*`/`recipient-lists.*` routes
resolve identically before and after.

**Files changed:** `routes/web.php`.

---

## Passing Checks — Confirmed Correct, No Remediation Required

| Area | Verdict |
|------|---------|
| Secrets at rest — `mail_accounts.app_password` uses Laravel's `encrypted` Eloquent cast (`APP_KEY`-derived AES-256-CBC), plus `#[Hidden(['app_password'])]` so it's never serialized into JSON/array output | ✓ PASS |
| Dynamic mailer config — `MailAccount::mailerConfig()` is built fresh per send, never written to a long-lived `config()` array or `.env` | ✓ PASS |
| CSRF protection | ✓ PASS — standard `web` middleware group throughout, no route excludes it, no `VerifyCsrfToken::except()` entries found |
| SQL injection — every query goes through Eloquent/Query Builder parameter binding; the one `selectRaw()` (`CampaignController::show()`'s status-count aggregate) uses a static string, no interpolated user input; `grep` for `DB::raw`/`whereRaw`/`DB::statement`/`DB::unprepared` codebase-wide found nothing else | ✓ PASS |
| Mass assignment — every model uses an explicit `#[Fillable([...])]` attribute; `grep -rn 'guarded'` across `app/` found zero matches (no model uses `$guarded = []`) | ✓ PASS |
| Login/OTP rate limiting — dual-keyed (email+IP 5/min, IP-only 10/min) on `login`; `two-factor` limiter (5/min, keyed by pending-login session ID + IP) on OTP verify/resend, plus a 45s server-side resend cooldown independent of the limiter | ✓ PASS |
| Auth flow — `Auth::guard('web')->validate()` never calls `Auth::login()`; a session is only ever granted in `verifyOtp()` after `hash_equals()` + expiry check, so there is no "authenticated but unverified" window for `middleware('auth')` to accidentally admit | ✓ PASS |
| Fortify route suppression — not applicable, this app hand-rolls its own auth controllers rather than using Fortify (unlike both sibling apps) — confirmed no `laravel/fortify` dependency, so there's no `ignoreRoutes()`-in-`boot()` class of bug to check for | ✓ PASS (N/A) |
| RBAC / privilege enforcement — every mutating route is gated by `auth` + `privilege:X`/`is_admin` at the route-group level (not solely inside a `FormRequest`), which structurally avoids the classic "unguarded `create`/`edit`/`destroy`" gap found and fixed repeatedly in `pdf-markdown-pipeline`'s H-04/H-05 | ✓ PASS |
| `User::canUseMailAccount()` — additionally restricts campaign sending to a user's own section's mail account, unless SuperAdmin | ✓ PASS |
| Zip attachment extraction (`CampaignBuilder::uploadZip()`) — PHP's `ZipArchive::extractTo()` has built-in zip-slip protection since PHP 7 (rejects `../`-traversing entry names); extraction target is a server-generated `campaign-imports/{uniqid()}` directory under the private `local` disk, never a user-controlled path | ✓ PASS |
| File upload validation — all three upload surfaces (`OfficerDirectoryImportWizard`, `RecipientListImportWizard`, `CampaignBuilder::uploadZip()`) validate `mimes:`/extension and a size cap (`max:5120`/`10240`/`51200`) before parsing; all land on the private `local` disk (`storage/app/private/`), never the public disk — no `storage:link` in this app, so there is no public URL to any uploaded file at all | ✓ PASS |
| Uploaded attachments never served back over HTTP by path — only ever read server-side and attached to an outbound email (`CampaignMail`) | ✓ PASS |
| `.env` not committed to git — confirmed via `git check-ignore -v .env` and `git log --all -- .env` (no history) | ✓ PASS |
| XSS — all user-controlled Blade output goes through `{{ }}` auto-escaping; the one `{!! !!}` usage is `MailTemplate`'s rendered body inside `CampaignMail`, which is HTML **by design** (the mail-merge template itself, authored by a privileged `templates.manage` user through the Quill editor, not arbitrary end-user input) — same trust boundary as an admin-authored email template in any mail-merge tool | ✓ PASS (by design) |
| Activity log / audit trail — `activity_logs` records every non-GET authenticated request + login/logout via `ActivityLog::record()`, which never throws (a logging failure can't break the request it's logging) | ✓ PASS |
| Crawler/indexing exposure | ✓ PASS — `public/robots.txt` already disallowed all crawlers site-wide (`Disallow: /`) before this audit; `SecurityHeaders` now adds `X-Robots-Tag: noindex, nofollow, noarchive` as defense-in-depth |
| IDOR on campaign URLs — campaign show/retry routes are slug-bound (`Campaign::getRouteKeyName() === 'slug'`, random-suffixed, not the row id — see this session's earlier fix), so a campaign can't be enumerated by walking `/campaigns/1`, `/campaigns/2`, ...; access itself is `auth`-only by design (any authenticated HQ user can view any campaign's send status — same "internal single-department tool, RBAC gates writes not reads" model both sibling apps use) | ✓ PASS |
| Onboarding link — `URL::temporarySignedRoute`, single-use (gated by `email_verified_at`), 72h expiry, rate-limited (`throttle:login` on `onboarding.store`) | ✓ PASS |
| Password policy — `Password::defaults()` requires min 8, mixed case, numbers, symbols, applied globally via `AppServiceProvider::boot()` | ✓ PASS |
| Simple single-model CRUD (`Admin\{Designation,Section,MailAccount}Controller`, `MailTemplateController`, `RecipientListController::destroy()`, `RecipientController`'s zone/division/district edits, `OnboardingController::store()`) — each is exactly one `Model::create()`/`update()`/`delete()` call, already atomic at the database level; deliberately **not** wrapped in `DB::transaction()`, matching `excise-budget-tracker`'s own `DesignationController`/equivalents, which don't wrap single-statement CRUD either | ✓ PASS (by design, re-confirmed this pass) |

---

*Audit and remediation completed 2026-08-21. The single most consequential finding (C-01,
`APP_DEBUG=true` in production) was raised directly by the site owner mid-session; the rest were
found by a full-codebase pass modeled on the same checklist already applied to
`excise-budget-tracker` and `pdf-markdown-pipeline` — session hardening, security headers,
Livewire's global upload-file endpoint, mass-assignment/SQL-injection/CSRF sweep, and a
component-by-component recheck of every `WithFileUploads` Livewire component's own
authorization (not just its mounting route's).*

**Follow-up pass, same day:** audited every write path in the app for the `DB::transaction()` +
`try`/`catch` atomicity convention both sibling apps use for multi-step writes. Found and fixed
L-03 (two spots making two related writes without tying them together —
`SendCampaignRecipientMail::handle()` and `CampaignController::retryRecipient()`); re-confirmed
every simple single-model CRUD controller correctly does **not** need it, matching the sibling
apps' own convention of reserving transactions for genuinely multi-step operations.
