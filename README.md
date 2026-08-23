<div align="center">

<img src="public/favicon.svg" width="64" height="64" alt="UP Excise Mailer logo">

# UP Excise Mailer

**Mail-merge & bulk emailer for the UP Excise Department HQ**

A recipient directory (zones/divisions/districts) plus ad-hoc imported lists,
`{{variable}}` mail-merge templates, and a campaign sender that dispatches
either one merged file to every recipient or a zip of distinct per-recipient
files — queued and throttled per sending account.

[![PHP](https://img.shields.io/badge/PHP-8.5-777BB4?logo=php&logoColor=white)](https://php.net)
[![Laravel](https://img.shields.io/badge/Laravel-13-FF2D20?logo=laravel&logoColor=white)](https://laravel.com)
[![Livewire](https://img.shields.io/badge/Livewire-4-4E56A6?logo=livewire&logoColor=white)](https://livewire.laravel.com)
[![MariaDB](https://img.shields.io/badge/MariaDB-DB-003545?logo=mariadb&logoColor=white)](https://mariadb.org)
[![Tailwind](https://img.shields.io/badge/Tailwind-Play%20CDN-06B6D4?logo=tailwindcss&logoColor=white)](https://tailwindcss.com)
[![Status](https://img.shields.io/badge/status-live-success)](https://mailer.exciseup.in)

</div>

---

## What it does

- **Recipient directory**: a fixed zone/division/district org hierarchy
  (JC/DC/DEO contacts), plus ad-hoc **imported recipient lists** (CSV/XLSX/PDF).
- **Mail-merge templates** — subject + HTML body with `{{variable}}`
  placeholders, rendered per recipient at send time.
- **Campaign sender** — one merged file to every recipient, or a zip of
  distinct per-recipient files auto-matched to recipients by filename (with
  manual override before send). Each recipient is a separately queued,
  throttled job, so a large campaign trickles out instead of bursting.
- **Per-section SMTP accounts** — every HQ section owns its own outbound
  mail account (Gmail, NIC/mGovCloud, or any custom SMTP), configured through
  the app, never a shared credential and never sitting in `.env`.
- **IMAP reply fetching** (opt-in per account) — pulls only the replies to
  mail this app actually sent, threaded back via `Message-ID`, without
  touching or marking read anything else in the section's inbox.
- **Manual response tracking** — a per-recipient tick (independent of IMAP,
  which needs a one-time NIC approval this deployment is still waiting on)
  plus a status/responded filter, bulk mark, and Excel (with a real header
  AutoFilter) / PDF export — including one-click "responded only" /
  "not responded only" exports.
- **Auth & audit** — password + OTP login, signed-URL invites, flat
  role+privileges RBAC, and a full activity log of every authenticated write.

**Livewire-first, not core Laravel with Livewire bolted on.** Every page with
filtering, search, or per-row actions — the campaign detail page, all of
admin CRUD — is a full-page Livewire component: actions are `wire:click`/
`wire:submit` (AJAX, no page reload), not `<form method="POST">` round-trips.
Auth (login/OTP/onboarding) is the one deliberate exception — a handful of
one-shot form submits with nothing to update in place, kept as plain
controllers so its rate-limit guarantees stay simple, auditable route
middleware instead of re-derived in-component checks.

## Docs

| File | Covers |
|---|---|
| [plan.md](./plan.md) | Full approved build plan — context, domain model, auth flow, sending design, UI, deploy |
| [CLAUDE.md](./CLAUDE.md) | Living architecture reference |
| [summary.md](./summary.md) | What's actually built vs. still pending |
| [APP_FLOW.md](./APP_FLOW.md) | Mermaid diagrams — auth, recipient-scope resolution, send lifecycle, authorization |
| [SECURITY.md](./SECURITY.md) | Full audited security posture and checklist |

## Stack

PHP 8.5 · Laravel 13 · MariaDB · Livewire 4 (the primary UI layer, not an
add-on) · Tailwind (Play CDN) + Tabler Icons · Quill (rich-text editing) ·
Fortify (hand-rolled password+OTP auth) · Resend (system/auth mail) ·
dynamic per-account SMTP built at send time (campaign mail) ·
`webklex/php-imap` (reply fetching) · `openspout` (CSV/XLSX recipient import
+ Excel export, incl. AutoFilter) · `smalot/pdfparser` (PDF recipient import)
· `barryvdh/laravel-dompdf` (PDF export) · Apache + Cloudflare Tunnel.

## Local setup

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan db:provision   # creates local MariaDB DB + user, writes .env
php artisan migrate --seed
npm install && npm run dev # not required for the app itself — Tailwind is Play CDN,
                            # only needed if/when a real build step is added
```

Three things run together for local dev:

```bash
php artisan serve                  # http://127.0.0.1:8000
php artisan queue:work             # required — campaign sends are queued/throttled
php artisan pail                   # optional — live log tailing
```

Fill in `.env` before sending real mail: `RESEND_API_KEY` (system/auth mail).
Per-section SMTP credentials are entered through the app itself
(`/admin/mail-accounts`), not `.env` — see [SECURITY.md](./SECURITY.md).

## Status

Live at **[mailer.exciseup.in](https://mailer.exciseup.in)**. Auth, admin CRUD
(sections, mail accounts, designations, users, activity log), the recipient
directory, mail templates, recipient-list import, the campaign builder, and
IMAP reply fetching are all built and wired up — see
[summary.md](./summary.md) for exactly what's built, what's next, and the
build history.
