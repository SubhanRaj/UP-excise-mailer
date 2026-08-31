<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Cache;

#[Fillable(['section_id', 'gmail_address', 'app_password', 'smtp_host', 'smtp_port', 'throttle_seconds', 'daily_send_cap', 'is_active', 'imap_host', 'imap_port', 'imap_last_fetched_at'])]
#[Hidden(['app_password'])]
class MailAccount extends Model
{
    use SoftDeletes;

    /**
     * Hard floor under any account's own `throttle_seconds` — after this account got flagged by
     * its provider once already, this is the minimum gap enforced between *any* two sends from
     * it, regardless of which path sent them (initial campaign dispatch, a retry, a resend).
     * `throttle_seconds` alone only staggers a campaign's own initial queue dispatch; retry and
     * resend fire immediately with no delay, so this floor is enforced separately in
     * `reserveSendSlot()` rather than relying on `throttle_seconds` being set high enough.
     */
    public const SEND_COOLDOWN_SECONDS = 60;

    protected function casts(): array
    {
        return [
            'app_password' => 'encrypted',
            'is_active' => 'boolean',
            'imap_last_fetched_at' => 'datetime',
        ];
    }

    public function repliesEnabled(): bool
    {
        return filled($this->imap_host);
    }

    /**
     * Same login as SMTP (gmail_address/app_password) — Gmail app passwords and NIC's
     * mGovCloud credentials both authorize IMAP with the identical username/password used
     * for sending, so there's no separate credential to store.
     */
    public function imapConfig(): array
    {
        return [
            'host' => $this->imap_host,
            'port' => $this->imap_port,
            'encryption' => $this->imap_port === 993 ? 'ssl' : false,
            'validate_cert' => true,
            'username' => $this->gmail_address,
            'password' => $this->app_password,
            'protocol' => 'imap',
        ];
    }

    public function section(): BelongsTo
    {
        return $this->belongsTo(Section::class);
    }

    public function campaigns(): HasMany
    {
        return $this->hasMany(Campaign::class);
    }

    /**
     * Atomically claims this account's next send slot and returns how many seconds the caller
     * must wait before actually sending (0 if the cooldown has already elapsed). Reserves the
     * slot at claim time, not at send time, so two queue workers racing to send from the same
     * account get serialized SEND_COOLDOWN_SECONDS apart instead of both computing the same
     * "0 seconds to wait" against a stale timestamp. The lock is held only long enough to read
     * and write the reservation, not for the wait itself — callers are expected to `sleep()` the
     * returned duration themselves, outside the lock.
     */
    public function reserveSendSlot(): int
    {
        $key = "mail-account:{$this->id}:next-send-slot";

        return Cache::lock("{$key}:lock", 10)->block(5, function () use ($key) {
            $reservedAt = Cache::get($key);
            $wait = $reservedAt ? max(0, self::SEND_COOLDOWN_SECONDS - now()->diffInSeconds($reservedAt)) : 0;
            Cache::put($key, now()->addSeconds($wait), now()->addMinutes(5));

            return $wait;
        });
    }

    /**
     * Runtime SMTP mailer config for this account — never written to config files,
     * built fresh per send so credentials never sit in a long-lived config array.
     */
    public function mailerConfig(): array
    {
        return [
            'transport' => 'smtp',
            'host' => $this->smtp_host,
            'port' => $this->smtp_port,
            'encryption' => $this->smtp_port === 465 ? 'ssl' : 'tls',
            'username' => $this->gmail_address,
            'password' => $this->app_password,
            // PHP's socket default (60s) isn't enough for some relays (confirmed live: NIC's
            // mGovCloud/Zoho pauses to scan and rename an attachment before accepting the
            // message, well past 60s) — the send still completes on the relay's side, but a
            // too-short timeout here would sever the connection first and leave this app
            // thinking it failed when the recipient already has the email.
            'timeout' => 180,
        ];
    }
}
