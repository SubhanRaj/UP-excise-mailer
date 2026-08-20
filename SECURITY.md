# Security — UP Excise Mailer

**Stack:** Laravel 13, PHP 8.5, MariaDB, Cloudflare Tunnel (HTTPS), on-premise
deployment, public internet-facing at `mailer.exciseup.in`.

**Scope:** Schema + model layer only as of this writing — see
[summary.md](./summary.md) for what's actually built. This document states
the intended security model so it can be audited against the real
implementation as each piece lands.

## Account creation & auth

- No public registration route. Admin-invite only: `URL::temporarySignedRoute`
  (default Laravel signing — HMAC'd, expiring, single-purpose) emailed via
  Resend. Treat the invite link like a password — it grants account setup to
  whoever holds it.
- Password + 6-digit email OTP (two factors) on every login, not just first
  login. OTP is cache-backed with a short TTL, never persisted to a DB
  column, never logged.
- Rate limiting required on **both** the login-attempt endpoint and the
  OTP-verify endpoint, keyed by email+IP server-side. A client-side resend
  cooldown is not a substitute — see
  `~/Projects/excise-revenue-recovery-portal/SECURITY.md` (finding H-01: a
  missing server-side rate limit shrank an OTP-style secret's effective
  brute-force space by 5 orders of magnitude).

## Secrets

- Gmail app passwords: `mail_accounts.app_password` uses Laravel's
  `encrypted` Eloquent cast (APP_KEY-derived, AES-256-CBC) — never stored or
  logged in plaintext, never included in API/JSON responses (`#[Hidden]`
  attribute on the model).
- `RESEND_API_KEY` and `APP_KEY` live only in `.env` (gitignored, confirmed
  not tracked in this repo).
- No secret is ever written into `config()` at boot — the dynamic mailer
  config built from a `mail_accounts` row exists only for the duration of a
  single send.

## Authorization

- Flat `role` (SuperAdmin bypasses all checks) + `privileges` JSON array,
  checked via `User::hasPrivilege()`.
- `User::canUseMailAccount()` additionally restricts campaign sending to a
  user's own section's Gmail account, unless SuperAdmin — prevents one
  section's Google One seat being used by another section's users.
- No zone/division/district-level recipient scoping yet (see CLAUDE.md
  "Known ceilings") — anyone with `campaigns.send` can target any recipient.
  Acceptable for a small trusted HQ user base; revisit if the user base grows
  past "everyone with an account is already vetted."

## Audit

- `activity_logs`: every non-GET authenticated request + login/logout events,
  written via `ActivityLog::record()` which never throws (a logging failure
  must never break the request it's logging). No retention policy yet.

## File uploads

- CSV/XLSX/PDF recipient imports and zip attachment uploads are
  user-supplied files handled server-side (openspout, smalot/pdfparser,
  PHP's ZipArchive) — validate file type/size at the controller boundary
  before parsing, and store uploads outside the public webroot
  (`storage/app/`, not `public/`).
- Uploaded attachments are only ever sent as email attachments, never served
  back over HTTP by path — no risk of arbitrary file disclosure via a public
  URL.

## Transport

- Cloudflare Tunnel terminates TLS at Cloudflare's edge; Apache origin is
  plain HTTP on a loopback-bound port, matching both sibling apps' deploy
  pattern — the origin is never directly internet-reachable.
