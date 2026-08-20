<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['section_id', 'gmail_address', 'app_password', 'smtp_host', 'smtp_port', 'throttle_seconds', 'daily_send_cap', 'is_active'])]
#[Hidden(['app_password'])]
class MailAccount extends Model
{
    use SoftDeletes;

    protected function casts(): array
    {
        return [
            'app_password' => 'encrypted',
            'is_active' => 'boolean',
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
