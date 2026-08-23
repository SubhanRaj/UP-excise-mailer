# Security Posture — UP Excise Mailer

**Stack:** Laravel 13, PHP 8.5, MariaDB, Cloudflare Tunnel (HTTPS), on-premise deployment
(`php artisan serve` behind `cloudflared`, no Apache vhost yet — see `DEPLOY.md`), public
internet-facing at `mailer.exciseup.in`.
**Scope:** Full application — auth/OTP, RBAC/privileges, campaign send pipeline (dynamic
per-account mailer), recipient import (CSV/XLSX/PDF, Livewire file uploads), zip-per-recipient
attachment matching, mail template editor, admin CRUD, activity log.

This document is the current, authoritative record of the app's security posture. Re-run its
checklist whenever a new write path or upload surface is added, and update the relevant section
in place rather than appending a new dated entry.

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
| L-02 | Three Livewire components had no in-component privilege re-check | LOW (defense-in-depth) | **FIXED** |
| L-03 | Two write paths made related multi-step writes without a shared transaction | LOW (atomicity) | **FIXED** |

All other areas audited (below) pass with no remediation required.

---

### C-01 · Production `.env` Debug Mode

**Severity:** CRITICAL — **Status:** FIXED

With `APP_DEBUG=true`, any unhandled exception (a bad query, a missing route parameter, a
third-party SMTP failure that escapes its `catch`, a 404 on a malformed URL) renders Laravel's
full Whoops debug page instead of the app's own error views: complete stack trace with file
paths and surrounding source code, the request's full headers/session/cookies, and **all
resolved `config()` values — including `APP_KEY`, `DB_PASSWORD`, and `RESEND_API_KEY`**. Anyone
who can trigger *any* exception on a public-facing site could read the database password and
mail API key directly out of the error page.

**Current state:** `.env` sets `APP_ENV=production`, `APP_DEBUG=false`. `.env.example` carries
a `# PRODUCTION: must be 'false' — true leaks stack traces, .env values, SQL` comment so a
future `cp .env.example .env` on a new environment doesn't silently regress this. The app's own
branded error pages (`resources/views/errors/*.blade.php`) render correctly with debug mode off.

**Files:** `.env`, `.env.example`.

---

### M-01 · Security Response Headers / Content-Security-Policy

**Severity:** MEDIUM — **Status:** FIXED

**Current state:** `App\Http\Middleware\SecurityHeaders` is registered globally via
`$middleware->append(...)` in `bootstrap/app.php` and sets, on every response:

| Header | Value |
|--------|-------|
| `X-Frame-Options` | `SAMEORIGIN` |
| `X-Content-Type-Options` | `nosniff` |
| `Referrer-Policy` | `strict-origin-when-cross-origin` |
| `Permissions-Policy` | `camera=(), microphone=(), geolocation=(), payment=(), usb=()` |
| `X-Robots-Tag` | `noindex, nofollow, noarchive` (in addition to `public/robots.txt`'s `Disallow: /`) |
| `Content-Security-Policy` | `default-src 'self'` plus the exact CDNs this app loads — `cdn.tailwindcss.com` (Tailwind Play), `cdn.jsdelivr.net` (Quill editor + SweetAlert2), `fonts.googleapis.com`/`fonts.gstatic.com` (Google Fonts) |
| `Strict-Transport-Security` | `max-age=31536000; includeSubDomains` (HTTPS-only — not sent over plain HTTP, so local `php artisan serve` dev is unaffected) |

`unsafe-inline` is required for Tailwind Play CDN and this app's inline `<script>` blocks
(theme anti-flash in `x-head`, Alpine directives). `unsafe-eval` is required because
Livewire 4 bundles Alpine.js, whose `x-data`/`x-init` expressions evaluate via `new Function()`.
Both are an accepted trade-off tied to the Play-CDN architecture (see `CLAUDE.md`'s UI
conventions) — narrowing further requires moving off the CDN entirely. If a new CDN is ever
added to a Blade view, add its host to this policy's `script-src`/`style-src` too, or the
browser silently blocks it.

**Verify:** `curl -sSI https://mailer.exciseup.in/login` — all six headers should be present.

**Files:** `app/Http/Middleware/SecurityHeaders.php`, `bootstrap/app.php`.

---

### M-02 · Session Payload Encryption

**Severity:** MEDIUM — **Status:** FIXED

`SESSION_DRIVER=database` means every session's payload — including the in-progress 6-digit
login OTP (`otp.code`), its expiry, and `login.id`/`login.remember` during the password→OTP
window — is stored in the `sessions` table. Without encryption, direct DB read access (a leaked
DB credential, a backup file, a DBA) exposes a live, still-valid OTP code without needing the
victim's password or inbox.

**Current state:** `SESSION_ENCRYPT=true` in `.env` — Laravel encrypts session payloads with
`APP_KEY` before writing to the DB. `.env.example` carries a matching
`# PRODUCTION: must be 'true' — session table stores the login OTP` comment.

**Files:** `.env`, `.env.example`.

---

### M-03 · Secure Cookie Flag

**Severity:** MEDIUM — **Status:** FIXED

This app is served over HTTPS in production (Cloudflare Tunnel terminates TLS at the edge). The
session cookie must be marked `Secure` so it's never accepted over a plain HTTP connection (e.g.
hitting the `cloudflared` origin directly, or a future misconfiguration).

**Current state:** `SESSION_SECURE_COOKIE=true` in `.env`; `.env.example` carries a matching
`# PRODUCTION (HTTPS): must be 'true' — prevents cookie over plain HTTP` comment.

**Files:** `.env`, `.env.example`.

---

### M-04 · Livewire Upload-File Endpoint Auth

**Severity:** MEDIUM — **Status:** FIXED

Livewire registers `POST livewire-{hash}/upload-file` globally at boot, independent of any
page-level route middleware. Its default config
(`temporary_file_upload.middleware => null`) falls back to Livewire's own default of
`'throttle:60,1'` — **no `auth` at all**. Three Livewire components use `WithFileUploads`:
`OfficerDirectoryImportWizard`, `RecipientListImportWizard`, and `CampaignBuilder`
(zip-per-recipient attachments) — a real, exercised surface, not a theoretical one. Without this
fix, any visitor, logged in or not, could POST up to 12MB (Livewire's own cap) to this endpoint,
60 times a minute per IP, landing in `storage/app/private/livewire-tmp/`, regardless of whether
they could reach any page that actually uses a file upload.

**Current state:** `AppServiceProvider::boot()` sets
`config(['livewire.temporary_file_upload.middleware' => ['auth', 'throttle:60,1']])` before
route registration — closes the endpoint to authenticated users while preserving the original
rate limit (a bare `'auth'` string would silently *drop* the throttle, since Livewire's own
default only applies when the config key is unset).

**Files:** `app/Providers/AppServiceProvider.php`.

---

### L-01 · `SameSite` Cookie Policy

**Severity:** LOW — **Status:** FIXED

Leaving `SESSION_SAME_SITE` unset falls back to Laravel's default `lax`, which still attaches
the session cookie to top-level cross-site GET navigations. This is a closed internal HQ tool
with no OAuth redirect flows or legitimate cross-site entry points, so `strict` is safe here.

**Current state:** `SESSION_SAME_SITE=strict` in `.env`; `.env.example` carries a matching
comment. CSRF tokens already protect all mutating requests regardless — this closes an
additional cookie-attachment vector.

**Files:** `.env`, `.env.example`.

---

### L-02 · In-Component Privilege Checks (Livewire)

**Severity:** LOW (defense-in-depth — not independently exploitable, see below) — **Status:** FIXED

Livewire's `livewire/update` AJAX endpoint (where a mounted component's public methods actually
run) is registered with only `['web', RequireLivewireHeaders::class]` middleware — **not** the
mounting page route's own middleware. `mount()` only runs on the initial page load; it does not
re-run on the subsequent AJAX calls that invoke a component's action methods. Every
`WithFileUploads` component in this app must independently `abort_unless(hasPrivilege(...))`
inside every action method, not just rely on the mounting route's middleware.

**Why this is LOW, not HIGH:** Livewire signs every component snapshot with a keyed checksum
(derived from `APP_KEY`) — a client cannot forge a snapshot for a component from nothing, or
tamper with its properties, without the server having issued that exact snapshot first. The
*only* way to obtain a valid snapshot for any of these components is to have successfully loaded
the mounting page's initial `GET`, which is itself correctly gated. So a missing in-component
check on its own can't be exploited by a user who couldn't already reach the page — but it's a
fragile posture: a future refactor of the route grouping, or a privilege revoked mid-session,
has no independent backstop without it.

**Current state:** every action method on `CampaignBuilder`, `OfficerDirectoryImportWizard`, and
`RecipientListImportWizard` re-checks its own privilege via `abort_unless(auth()->user()->
hasPrivilege(...), 403)`, independent of the mounting route's middleware. This is the pattern to
follow for any new `WithFileUploads` component added to the app.

**Files:** `app/Livewire/OfficerDirectoryImportWizard.php`,
`app/Livewire/RecipientListImportWizard.php`, `app/Livewire/CampaignBuilder.php`.

---

### L-03 · Multi-Step Write Atomicity

**Severity:** LOW (atomicity, not a security exploit — a crash mid-sequence produces an
inconsistent-but-recoverable state, not a privilege or data-disclosure issue) — **Status:** FIXED

**Convention:** `DB::transaction()` + `try`/`catch` wraps **multi-step** writes that must
succeed or fail together — not every single-statement `Model::create()`/`update()`/`delete()`,
which is already atomic at the database level on its own.

Two write paths made two related writes without tying them together:

1. `SendCampaignRecipientMail::handle()` — on success, `$recipient->update(['status' =>
   'sent', ...])` followed by `markCampaignCompletedIfDone()` (a separate `Campaign::update()`
   if no recipient is left pending); same shape in the `catch` branch for a failed send. If the
   process dies between the two calls (worker killed, OOM, deploy mid-job), a recipient could be
   marked `sent`/`failed` while the campaign stays `queued` forever even though it was actually
   the last recipient.
2. `CampaignController::retryRecipient()` — `$recipient->update([...])` followed by
   `$campaign->update(['status' => 'queued'])`, with no `try`/`catch` at all — an exception
   between the two calls (or either one failing) left no way to tell the user anything went
   wrong.

**Current state:** both call sites wrap just their two related writes in `DB::transaction()`
(so either both land or neither does), with `Mail::send()` in the job kept deliberately
*outside* the transaction — a DB transaction (and its row locks) should never stay open across a
slow network call to an external SMTP relay. `retryRecipient()` has a `try`/`catch` that logs the
failure and flashes an error instead of a raw 500, with nothing partially changed since the
transaction rolls back.

**Files:** `app/Jobs/SendCampaignRecipientMail.php`, `app/Http/Controllers/CampaignController.php`.

---

## Passing Checks — Confirmed Correct, No Remediation Required

| Area | Verdict |
|------|---------|
| Secrets at rest — `mail_accounts.app_password` uses Laravel's `encrypted` Eloquent cast (`APP_KEY`-derived AES-256-CBC), plus `#[Hidden(['app_password'])]` so it's never serialized into JSON/array output | ✓ PASS |
| Dynamic mailer config — `MailAccount::mailerConfig()` is built fresh per send, never written to a long-lived `config()` array or `.env` | ✓ PASS |
| CSRF protection — standard `web` middleware group throughout, no route excludes it, no `VerifyCsrfToken::except()` entries | ✓ PASS |
| SQL injection — every query goes through Eloquent/Query Builder parameter binding; the one `selectRaw()` (`CampaignController::show()`'s status-count aggregate) uses a static string, no interpolated user input; no other `DB::raw`/`whereRaw`/`DB::statement`/`DB::unprepared` calls anywhere in the codebase | ✓ PASS |
| Mass assignment — every model uses an explicit `#[Fillable([...])]` attribute; no model uses `$guarded = []` | ✓ PASS |
| Login/OTP rate limiting — dual-keyed (email+IP 5/min, IP-only 10/min) on `login`; `two-factor` limiter (5/min, keyed by pending-login session ID + IP) on OTP verify/resend, plus a 45s server-side resend cooldown independent of the limiter | ✓ PASS |
| Auth flow — `Auth::guard('web')->validate()` never calls `Auth::login()`; a session is only ever granted in `verifyOtp()` after `hash_equals()` + expiry check, so there is no "authenticated but unverified" window for `middleware('auth')` to accidentally admit | ✓ PASS |
| Fortify route suppression — not applicable; this app hand-rolls its own auth controllers rather than using Fortify, so there is no `ignoreRoutes()`-in-`boot()` class of bug to check for | ✓ PASS (N/A) |
| RBAC / privilege enforcement — every mutating route is gated by `auth` + `privilege:X`/`is_admin` at the route-group level (not solely inside a `FormRequest`), which structurally avoids an unguarded `create`/`edit`/`destroy` gap | ✓ PASS |
| `User::canUseMailAccount()` — additionally restricts campaign sending to a user's own section's mail account, unless SuperAdmin | ✓ PASS |
| Zip attachment extraction (`CampaignBuilder::uploadZip()`) — PHP's `ZipArchive::extractTo()` has built-in zip-slip protection since PHP 7 (rejects `../`-traversing entry names); extraction target is a server-generated `campaign-imports/{uniqid()}` directory under the private `local` disk, never a user-controlled path | ✓ PASS |
| File upload validation — all three upload surfaces (`OfficerDirectoryImportWizard`, `RecipientListImportWizard`, `CampaignBuilder::uploadZip()`) validate `mimes:`/extension and a size cap (`max:5120`/`10240`/`51200`) before parsing; all land on the private `local` disk (`storage/app/private/`), never the public disk — no `storage:link` in this app, so there is no public URL to any uploaded file at all | ✓ PASS |
| Uploaded attachments never served back over HTTP by path — only ever read server-side and attached to an outbound email (`CampaignMail`) | ✓ PASS |
| `.env` not committed to git — confirmed via `git check-ignore -v .env` and `git log --all -- .env` (no history) | ✓ PASS |
| XSS — all user-controlled Blade output goes through `{{ }}` auto-escaping; the one `{!! !!}` usage is `MailTemplate`'s rendered body inside `CampaignMail`, which is HTML **by design** (the mail-merge template itself, authored by a privileged `templates.manage` user through the Quill editor, not arbitrary end-user input) — same trust boundary as an admin-authored email template in any mail-merge tool | ✓ PASS (by design) |
| Activity log / audit trail — `activity_logs` records every non-GET authenticated request + login/logout via `ActivityLog::record()`, which never throws (a logging failure can't break the request it's logging) | ✓ PASS |
| Crawler/indexing exposure — `public/robots.txt` disallows all crawlers site-wide (`Disallow: /`); `SecurityHeaders` adds `X-Robots-Tag: noindex, nofollow, noarchive` as defense-in-depth | ✓ PASS |
| IDOR on campaign URLs — campaign show/retry routes are slug-bound (`Campaign::getRouteKeyName() === 'slug'`, random-suffixed, not the row id), so a campaign can't be enumerated by walking `/campaigns/1`, `/campaigns/2`, ...; access itself is `auth`-only by design (any authenticated HQ user can view any campaign's send status — RBAC gates writes, not reads) | ✓ PASS |
| Onboarding link — `URL::temporarySignedRoute`, single-use (gated by `email_verified_at`), 72h expiry, rate-limited (`throttle:login` on `onboarding.store`) | ✓ PASS |
| Password policy — `Password::defaults()` requires min 8, mixed case, numbers, symbols, applied globally via `AppServiceProvider::boot()` | ✓ PASS |
| Simple single-model CRUD (`Admin\{Designation,Section,MailAccount}Controller`, `MailTemplateController`, `RecipientListController::destroy()`, `RecipientController`'s zone/division/district edits, `OnboardingController::store()`) — each is exactly one `Model::create()`/`update()`/`delete()` call, already atomic at the database level; deliberately **not** wrapped in `DB::transaction()` | ✓ PASS (by design) |
| IMAP reply fetching (`ImapReplyFetcher`) — the fetch route is `privilege:campaigns.send`-gated same as every other campaign write; a reply only saves if its `In-Reply-To`/`References` header matches a `message_id` this app itself generated, so an attacker can't inject an arbitrary `campaign_replies` row by emailing a section's mailbox directly, only by replying to a thread the app already started; the stored `body_text`/`subject`/`from_name` are untrusted third-party content, rendered in `campaigns/show.blade.php` exclusively through `{{ }}` auto-escaping — no `{!! !!}`, so a crafted reply body can't inject script | ✓ PASS |
| Manual "responded" tick (`CampaignController::markResponded()`) — `privilege:campaigns.send` + `throttle:mutations` gated, single `whereIn('id', $ids)->update()` scoped through `$campaign->recipients()` (the relationship, not a bare `CampaignRecipient::whereIn`), so a crafted recipient id from a different campaign can't be touched by a user who only has access to this one; XLSX/PDF export (`CampaignController::export()`) is `auth`-only same as `show()` (reads, not writes — same access model as the rest of the campaign detail page), `abort_unless` on the format param, and the PDF Blade view renders every field through `{{ }}` | ✓ PASS |
