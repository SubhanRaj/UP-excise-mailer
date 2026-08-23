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

## Current production state (2026-08-23)

| Component | State |
|---|---|
| Web server | Apache vhost, `127.0.0.1:8082`, `DocumentRoot` at `public/` — same pattern as the two sibling apps |
| PHP | 8.5.4 |
| Database | MySQL/MariaDB, `up_excise_mailer_local` (despite the name, this is the live production database — not renamed since initial scaffolding) |
| Queue | `QUEUE_CONNECTION=database`, two parallel workers |
| Mail (system) | Resend (`MAIL_MAILER=resend`, `noreply@mail.exciseup.in`) — invites, login OTP, and (SuperAdmin only) test sends |
| Mail (campaigns) | Per-section `mail_accounts` rows — Gmail, NIC/mGovCloud, or custom SMTP, built into a dynamic mailer at send time (see CLAUDE.md's "Sending mail") |
| Cloudflare Tunnel | `mailer.exciseup.in` → `http://127.0.0.1:8082` |
| Process supervision | systemd `--user` units for the queue workers and the tunnel (see below) — Apache itself is a system service |

Apache vhost config: `/etc/apache2/sites-available/up-excise-mailer.conf`. Port `8082` is
`Listen`-ed in `/etc/apache2/ports.conf`, alongside budget-tracker's `8081` and
pdf-pipeline's `8080`.

## systemd `--user` units (`~/.config/systemd/user/`)

Three units, all owned by `subhan`, all `enabled` (`systemctl --user is-enabled <unit>`):

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

`up-excise-mailer-app.service` (`php artisan serve`) is gone — stopped, disabled, and removed
from `default.target.wants` on 2026-08-23 once the Apache vhost took over. The two queue units'
and the tunnel unit's `After=` lines still list it; it's a soft dependency name reference with no
effect once the unit doesn't exist, harmless to leave and not worth editing three files to
remove.

All three `WantedBy=default.target`, all survive logout/reboot via `loginctl enable-linger
subhan` (already set, shared across all three sibling apps' users — `Linger=yes`, confirmed via
`loginctl show-user subhan`).

**Checking status** — always with `--user`, plain `systemctl status <unit>` (no `--user`) will
report "could not be found" even though the unit is running:
```bash
systemctl --user status up-excise-mailer-queue.service up-excise-mailer-queue2.service \
  up-excise-mailer-tunnel.service
```

**Restarting after a code change** (this app is served directly from this working directory —
`git pull`/`git checkout` on it is not a no-op for the live site):
```bash
sudo systemctl reload apache2                            # picks up new PHP code
systemctl --user restart up-excise-mailer-queue.service up-excise-mailer-queue2.service
```
`php artisan queue:restart` (the Laravel-native way) is **not safe here on its own** — it just
sets a cache flag the worker checks *before its next job pickup* and exits once it sees it, and
without something supervising it, nothing would relaunch it. It's safe to use because the systemd
units above supervise the workers (`Restart=always` relaunches on exit); if you use it, verify
with `systemctl --user status` afterward that the units actually came back.

**Never leave a manually-started `queue:work` running alongside the systemd-managed ones** — this
happened once already (an extra ad-hoc `queue:work --tries=1` with the PHP-default 60s timeout
got started by hand, which is exactly the short-timeout risk described above) and caused real
confusion diagnosing a "stuck" send. If you need to run one manually for debugging, stop the
matching systemd unit first (`systemctl --user stop up-excise-mailer-queue.service`) so there's
only ever one worker per role.

## Apache

Same setup as `pdf-markdown-pipeline` and `excise-budget-tracker` (see either app's own
`DEPLOY.md`), applied to this app's paths:

- Vhost: `/etc/apache2/sites-available/up-excise-mailer.conf`, `ServerName mailer.exciseup.in`,
  `DocumentRoot ~/Sites/UP-excise-mailer/public`, `Require all granted`, listening on
  `127.0.0.1:8082`.
- **Permissions** — `chown -R subhan:www-data storage bootstrap/cache && chmod -R 775 storage
  bootstrap/cache`.
- **`ProtectHome` override** — Ubuntu's `apache2.service` ships `ProtectHome=read-only`, which
  blocks Apache from writing anywhere under `/home` regardless of Unix permissions. The drop-in at
  `/etc/systemd/system/apache2.service.d/override.conf` (shared across all three apps under
  `~`) has this app's `storage`/`bootstrap/cache` paths added to its `ReadWritePaths=`
  line, alongside the other two apps' paths and the budget-tracker's `ProcSubset=all` — the file
  is edited in place, never overwritten wholesale. After editing:
  `sudo systemctl daemon-reload && sudo systemctl restart apache2`.
- Cloudflare Tunnel ingress in `~/.cloudflared/mailer-config.yml` points at
  `http://127.0.0.1:8082`.
- `php artisan view:clear` after any future branch switch on the live checkout — Blade's
  compiled-view cache mtime-touch can throw `Utime failed: Operation not permitted` across a
  `subhan`/`www-data` ownership split, exactly like pdf-pipeline hit once.

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
systemctl --user status up-excise-mailer-queue up-excise-mailer-queue2 up-excise-mailer-tunnel
curl -sS -o /dev/null -w '%{http_code}\n' http://127.0.0.1:8082/login       # expect 200, confirms Apache
curl -sS -o /dev/null -w '%{http_code}\n' https://mailer.exciseup.in/login  # expect 200, confirms the tunnel
php artisan queue:failed                                                    # expect "No failed jobs found"
```
