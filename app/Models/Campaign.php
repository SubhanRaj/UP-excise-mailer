<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

#[Fillable(['name', 'slug', 'mail_account_id', 'template_id', 'subject', 'body', 'recipient_scope', 'attachment_mode', 'status', 'created_by', 'sent_at'])]
class Campaign extends Model
{
    use SoftDeletes;

    protected static function booted(): void
    {
        static::creating(function (Campaign $campaign) {
            $campaign->slug ??= self::uniqueSlugFor($campaign->name);
        });
    }

    protected function casts(): array
    {
        return [
            'sent_at' => 'datetime',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /**
     * Random suffix (not the row's id) so a campaign URL never leaks how many campaigns
     * exist or lets someone walk /campaigns/1, /campaigns/2, ... to enumerate them.
     */
    public static function uniqueSlugFor(string $name): string
    {
        $base = Str::slug($name) ?: 'campaign';

        do {
            $slug = $base.'-'.Str::lower(Str::random(6));
        } while (self::withTrashed()->where('slug', $slug)->exists());

        return $slug;
    }

    public function mailAccount(): BelongsTo
    {
        return $this->belongsTo(MailAccount::class);
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(MailTemplate::class, 'template_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function recipients(): HasMany
    {
        return $this->hasMany(CampaignRecipient::class);
    }
}
