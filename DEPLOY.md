# Running & Deploying UP-excise-mailer

This is an **on-premise, no-cloud** Laravel app living at `~/Sites/UP-excise-mailer`
on the same Ubuntu AIO box as `~/Sites/excise-budget-tracker` and
`~/Sites/pdf-markdown-pipeline`. There is no CI/CD and no cloud provider — this doc is the
source of truth for what's actually running in production right now.

## Known issue right now (2026-08-20)

`redacted-account@example.gov.in` (Task Force section's mGovCloud/Zoho mail account) is
**hard-blocked by Zoho** — `554 5.1.8 Sender Address Blocked` — after a burst of
sends near the end of a 75-recipient campaign tripped their anti-abuse system
(`550 5.4.6 Unusual sending activity detected` on the last 7 recipients, then
escalated to the account-level block on any further attempt, confirmed via
bounce notices in mGovCloud Workspace). This is entirely Zoho/NIC-side, not an
app bug — see `summary.md`'s 2026-08-20 "Live incident" entry. Don't retry
sends from this account until the block is confirmed lifted (user is following
up with the DA admin + NIC directly). Once lifted, and once NIC confirms a
higher-volume allowance, raise that account's `throttle_seconds` /
`daily_send_cap` in Admin → Mail Accounts to match.

## Current production state (2026-08-20)

| Component | State |
|---|---|
| Web server | **`php artisan serve` (port 8000), not Apache** — this is the one sibling app still on this pattern; see "Not yet on Apache" below for why and what changes once it's fixed |
| PHP | 8.5.4 |
| Database | MySQL/MariaDB, `up_excise_mailer_local` (despite the name, this is the live production database — not renamed since initial scaffolding) |
| Queue | `QUEUE_CONNECTION=database`, two parallel workers |
| Mail (system) | Resend (`MAIL_MAILER=resend`, `noreply@mail.exciseup.in`) — invites, login OTP, and (SuperAdmin only) test sends |
| Mail (campaigns) | Per-section `mail_accounts` rows — Gmail, NIC/mGovCloud, or custom SMTP, built into a dynamic mailer at send time (see CLAUDE.md's "Sending mail") |
| Cloudflare Tunnel | `mailer.exciseup.in` → `http://127.0.0.1:8000` |
| Process supervision | **systemd `--user` units**, all three (see below) — nothing runs as an unsupervised manual/nohup process |

## systemd `--user` units (`~/.config/systemd/user/`)

Four units, all owned by `subhan`, all `enabled` (`systemctl --user is-enabled <unit>`):

- **`up-excise-mailer-app.service`** — `php artisan serve --host=127.0.0.1 --port=8000`. This
  app is the *only* one of the three sibling apps that still needs an app-serving unit at all —
  `excise-budget-tracker` and `pdf-markdown-pipeline` are both on real Apache vhosts, where
  Apache itself (a system service, always running) serves the app and no `artisan serve` unit
  is needed. This one exists specifically because this app has **no Apache vhost yet** (see
  below) — once that's done, retire this unit, matching what `pdf-pipeline-app.service` already
  went through on 2026-08-13 (stopped, disabled, unit file removed once Apache took over).
- **`up-excise-mailer-queue.service`** / **`up-excise-mailer-queue2.service`** — two parallel
  `php artisan queue:work --tries=3 --timeout=1900` workers. `--timeout=1900` (~31 min) is
  deliberately generous — a too-short queue timeout can kill a job's PHP process *after* the
  mail has already been accepted by a slow relay but *before* the campaign_recipients row gets
  marked `sent`, leaving the app thinking a delivered email failed. Confirmed live 2026-08-20:
  NIC's mGovCloud (Zoho-based) pauses to scan and rename an attachment before accepting the
  message, which is slow enough that this actually happened once with a bare-default (60s)
  worker — see `MailAccount::mailerConfig()`'s `'timeout' => 180` (the SMTP transport's own
  socket timeout, a separate setting from the queue worker's `--timeout`) and `summary.md`'s
  2026-08-20 "stuck on queued" entry for the full incident.
- **`up-excise-mailer-tunnel.service`** — `cloudflared tunnel --config
  ~/.cloudflared/mailer-config.yml run up-excise-mailer`.

All four `WantedBy=default.target`, all survive logout/reboot via `loginctl enable-linger
subhan` (already set, shared across all three sibling apps' users — `Linger=yes`, confirmed via
`loginctl show-user subhan`).

**Checking status** — always with `--user`, plain `systemctl status <unit>` (no `--user`) will
report "could not be found" even though the unit is running:
```bash
systemctl --user status up-excise-mailer-app.service up-excise-mailer-queue.service \
  up-excise-mailer-queue2.service up-excise-mailer-tunnel.service
```

**Restarting after a code change** (this app is served directly from this working directory —
`git pull`/`git checkout` on it is not a no-op for the live site):
```bash
systemctl --user restart up-excise-mailer-app.service   # picks up new PHP code
systemctl --user restart up-excise-mailer-queue.service up-excise-mailer-queue2.service
```
`php artisan queue:restart` (the Laravel-native way) is **not safe here on its own** — it just
sets a cache flag the worker checks *before its next job pickup* and exits once it sees it, and
without something supervising it, nothing would relaunch it. It only became safe again for this
app once the systemd units above existed (systemd relaunches on exit via `Restart=always`); if
you use it, verify with `systemctl --user status` afterward that the units actually came back.

**Never leave a manually-started `php artisan serve` or `queue:work` running alongside the
systemd-managed ones** — this happened once already this session (an extra ad-hoc `queue:work
--tries=1` with the PHP-default 60s timeout got started by hand, which is exactly the
short-timeout risk described above) and caused real confusion diagnosing a "stuck" send. If you
ever need to run one manually for debugging, stop the matching systemd unit first
(`systemctl --user stop up-excise-mailer-queue.service`) so there's only ever one worker
per role.

## Not yet on Apache

Blocked on `sudo`, which this session doesn't have — either the user runs the vhost-creation
commands themselves, or grants `sudo` for a session that can. The pattern to follow is
`pdf-markdown-pipeline`'s (see its own `DEPLOY.md`, "Production deployment" section) applied to
this app's paths:

1. Vhost on an unused port (e.g. `127.0.0.1:8082` — budget-tracker already has `8081`,
   pdf-pipeline has `8080`), `DocumentRoot` at `public/`, `Require all granted`.
   `Listen 8082` added to `/etc/apache2/ports.conf`, enabled via `a2ensite`.
2. **Permissions** — `chmod o+x ~` if not already done (it will be, from the sibling
   apps' setup), `chown -R subhan:www-data storage bootstrap/cache && chmod -R 775 storage
   bootstrap/cache`.
3. **`ProtectHome` override** — Ubuntu's `apache2.service` ships `ProtectHome=read-only`, which
   blocks Apache from writing anywhere under `/home` regardless of Unix permissions. A drop-in
   at `/etc/systemd/system/apache2.service.d/override.conf` is *already in place* on this box
   (shared across all three apps under `~`) — just add this app's paths to its
   existing `ReadWritePaths=` line, **don't overwrite the file** (it also carries `ProcSubset=all`
   for the budget-tracker's health dashboard — pdf-pipeline's `DEPLOY.md` has the exact incident
   from blindly overwriting this file once already). After editing:
   `sudo systemctl daemon-reload && sudo systemctl restart apache2`.
4. Switch `~/.cloudflared/mailer-config.yml`'s ingress from `http://127.0.0.1:8000` to
   `http://127.0.0.1:8082`, then `systemctl --user restart up-excise-mailer-tunnel.service`.
5. Stop, disable, and remove `up-excise-mailer-app.service` (Apache replaces it entirely) —
   `up-excise-mailer-queue.service`/`queue2.service`/`tunnel.service` are unaffected.
6. `php artisan view:clear` after the cutover and after any future branch switch on the live
   checkout — Blade's compiled-view cache mtime-touch can throw `Utime failed: Operation not
   permitted` across a `subhan`/`www-data` ownership split, exactly like pdf-pipeline hit.

## Mail

Two independent mail paths, don't confuse them:

- **System mail** (invites, login OTP, SuperAdmin test sends) — `MAIL_MAILER=resend`,
  `RESEND_API_KEY` in `.env`, always `noreply@mail.exciseup.in`. Fixed, not admin-configurable
  in the UI.
- **Campaign mail** — per-section `mail_accounts` rows (Admin → Mail Accounts), built into a
  dynamic Laravel mailer config at send time (`MailAccount::mailerConfig()`), never written to
  `.env` or any long-lived config array. Provider presets: Gmail (`smtp.gmail.com`), NIC/
  mGovCloud (`smtp.mgovcloud.in`), or Custom SMTP. `CampaignMail` sets its `From` address to
  match whichever account is actually authenticating — required, not cosmetic: several relays
  (confirmed with NIC's mGovCloud) reject the send outright if the From header doesn't match the
  authenticated mailbox.

## Verifying a deployment

```bash
systemctl --user status up-excise-mailer-app up-excise-mailer-queue up-excise-mailer-queue2 up-excise-mailer-tunnel
curl -sS -o /dev/null -w '%{http_code}\n' https://mailer.exciseup.in/login   # expect 200
php artisan queue:failed                                                     # expect "No failed jobs found"
```
