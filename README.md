<div align="center">

# ✉️ UP Excise Mailer

**Mail-merge & bulk emailer for the UP Excise Department HQ**

Sends the same file — or a distinct file per recipient — to the department's
5 zones, 18 divisions, and 75 districts, through each HQ section's own Gmail
SMTP, without anyone mailing them one at a time by hand.

[![PHP](https://img.shields.io/badge/PHP-8.5-777BB4?logo=php&logoColor=white)](https://php.net)
[![Laravel](https://img.shields.io/badge/Laravel-13-FF2D20?logo=laravel&logoColor=white)](https://laravel.com)
[![Livewire](https://img.shields.io/badge/Livewire-4-4E56A6?logo=livewire&logoColor=white)](https://livewire.laravel.com)
[![MariaDB](https://img.shields.io/badge/MariaDB-DB-003545?logo=mariadb&logoColor=white)](https://mariadb.org)
[![Tailwind](https://img.shields.io/badge/Tailwind-Play%20CDN-06B6D4?logo=tailwindcss&logoColor=white)](https://tailwindcss.com)
[![Status](https://img.shields.io/badge/status-live-success)](https://mailer.exciseup.in)

</div>

---

## What it does

- Holds the fixed **zone / division / district** recipient directory (JC/DC/DEO
  contacts), plus ad-hoc **imported recipient lists** (CSV/XLSX/PDF).
- Drafts **mail-merge templates** with `{{variable}}` placeholders.
- Sends a campaign as either **one merged file to everyone**, or a **zip of
  distinct per-recipient files**, auto-matched by filename with manual override.
- Sends through each HQ section's own **Gmail SMTP app password** — never a
  shared account — with per-account throttling and daily caps.
- Optionally fetches **IMAP replies** threaded back to the exact message a
  campaign sent, without touching the rest of the section's inbox.
- Full **activity log** of every authenticated write, and password + OTP auth
  with signed-URL invites.

Built to replace the department's actual prior workflow: one email, one
recipient, one attachment, by hand — repeated up to 75 times per file.

## Docs

| File | Covers |
|---|---|
| [plan.md](./plan.md) | Full approved build plan — context, domain model, auth flow, sending design, UI, deploy |
| [CLAUDE.md](./CLAUDE.md) | Living architecture reference |
| [summary.md](./summary.md) | What's actually built vs. still pending |
| [APP_FLOW.md](./APP_FLOW.md) | Mermaid diagrams — auth, recipient-scope resolution, send lifecycle, authorization |
| [SECURITY.md](./SECURITY.md) | Full audited security posture and checklist |

## Stack

PHP 8.5 · Laravel 13 · MariaDB · Livewire 4 · Tailwind (Play CDN) + Tabler
Icons · Quill (rich-text editing) · Fortify (hand-rolled password+OTP auth) ·
Resend (system/auth mail) · dynamic Gmail SMTP per HQ section (campaign
mail) · `webklex/php-imap` (reply fetching) · `openspout` / `smalot/pdfparser`
(CSV/XLSX/PDF recipient import) · Apache + Cloudflare Tunnel.

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
Per-section Gmail SMTP app passwords are entered through the app itself
(`/admin/mail-accounts`), not `.env` — see [SECURITY.md](./SECURITY.md).

## Status

Live at **[mailer.exciseup.in](https://mailer.exciseup.in)**. Auth, admin CRUD
(sections, mail accounts, designations, users, activity log), the recipient
directory, mail templates, recipient-list import, the campaign builder, and
IMAP reply fetching are all built and wired up — see
[summary.md](./summary.md) for exactly what's built, what's next, and the
build history.
