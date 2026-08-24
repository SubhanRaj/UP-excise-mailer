# Running & Deploying UP-excise-mailer

This is an on-premise Laravel app — no cloud provider, no CI/CD. It runs behind Apache (a vhost
on a private loopback port) and reaches the public internet through a Cloudflare Tunnel, with
systemd `--user` units supervising the queue workers and the tunnel process.

Operational specifics for the box this app currently runs on — real ports, tunnel config, server
paths, systemd unit names — live in a private infrastructure repo. If you're setting this app up
on your own server, the pattern is:

1. `composer install`, copy `.env.example` to `.env`, fill in your database and mail
   credentials, `php artisan key:generate`, `php artisan migrate --seed`.
2. Point an Apache (or nginx) vhost's document root at `public/`.
3. Run `php artisan queue:work` under something that supervises it (systemd, supervisord) —
   campaign sends are queued jobs, and nothing gets delivered without a worker running.
4. `RESEND_API_KEY` in `.env` for system mail (invites, login OTP). Campaign mail is configured
   per-section from inside the app (Admin → Mail Accounts) and never touches `.env`.
5. Every account is created by invite from an existing user, so a fresh install needs one
   SuperAdmin bootstrapped directly. `password` has a `hashed` cast, so assign the plain value
   directly rather than pre-hashing it, and set attributes one at a time (not via `create()`) so
   `email_verified_at` isn't dropped by the model's `#[Fillable]` guard:
   ```bash
   php artisan tinker --execute="
   \$u = new App\Models\User();
   \$u->name = 'Your Name';
   \$u->username = 'your_username';
   \$u->email = 'you@example.com';
   \$u->password = 'a-real-password';
   \$u->role = 'SuperAdmin';
   \$u->email_verified_at = now();
   \$u->save();
   "
   ```
   Every other user goes through Admin → Users, which sends a real invite email.

If Apache's systemd unit sets `MemoryDenyWriteExecute=yes` (Ubuntu's stock `apache2.service`
does), PCRE's JIT compiler can't map executable memory, and any `preg_match()` on a pattern
that hasn't been JIT-compiled yet throws a warning that Laravel turns into a fatal error —
including inside Blade compilation, which affects any page whose view isn't already compiled.
Set `pcre.jit=0` in the Apache PHP `php.ini` (not the CLI one) and reload Apache.

See [CLAUDE.md](./CLAUDE.md) for the application architecture and [SECURITY.md](./SECURITY.md)
for the security posture.
