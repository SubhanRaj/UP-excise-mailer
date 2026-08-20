# UP Excise Mailer

Mail-merge and bulk emailer for the UP Excise Department HQ — sends the same or
per-recipient files to the department's 5 zones, 18 divisions, and 75 districts,
through each HQ section's own Gmail SMTP (app password), without mailing them
one at a time by hand.

See [CLAUDE.md](./CLAUDE.md) for architecture, [plan.md](./plan.md) for the
original build plan, [summary.md](./summary.md) for build progress,
[SECURITY.md](./SECURITY.md) for the security model, and
[DEPLOY.md](./DEPLOY.md) for the vhost/tunnel deploy steps once written.

## Stack

PHP 8.5, Laravel 13, MariaDB, Livewire 4, Tailwind (Play CDN) + Tabler Icons,
Quill (CDN, template/campaign rich-text editing), Fortify (routes disabled,
password+OTP auth hand-rolled), Resend (system/auth mail from
`noreply@mail.exciseup.in`), dynamic Gmail SMTP per HQ section (campaign
mail), `openspout`/`smalot/pdfparser` (CSV/XLSX/PDF recipient import),
Apache + Cloudflare Tunnel (`cloudflared`) at `mailer.exciseup.in`.

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

Live at `https://mailer.exciseup.in`. Auth, admin CRUD (sections, mail
accounts, designations, users, activity log), the recipient directory,
mail templates, recipient-list import, and the campaign builder are all
built and wired up — see [summary.md](./summary.md) for exactly what's
built, what's next, and the build history.
