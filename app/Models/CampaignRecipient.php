<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['campaign_id', 'recipient_type', 'recipient_ref_id', 'name', 'email', 'attachment_path', 'matched_via', 'status', 'error_message', 'sent_at', 'failed_at', 'message_id', 'responded_at'])]
class CampaignRecipient extends Model
{
    protected function casts(): array
    {
        return [
            'sent_at' => 'datetime',
            'failed_at' => 'datetime',
            'responded_at' => 'datetime',
        ];
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    public function replies(): HasMany
    {
        return $this->hasMany(CampaignReply::class);
    }

    /**
     * Re-derives the {{variable}} values for this recipient from the current
     * zone/division/district/list_item row (not a snapshot taken at queue time), so a retry
     * after fixing a bad email address also picks up the corrected data. Mirrors
     * CampaignBuilder::candidateRecipients()'s per-type vars shape.
     */
    public function resolveVars(): array
    {
        return match ($this->recipient_type) {
            'zone' => ($zone = Zone::find($this->recipient_ref_id))
                ? ['zone' => $zone->name, 'officer' => $zone->officerDisplayName(), 'cug' => $zone->jc_cug]
                : [],
            'division' => ($division = Division::with('zone')->find($this->recipient_ref_id))
                ? ['division' => $division->name, 'zone' => $division->zone?->name, 'officer' => $division->officerDisplayName(), 'cug' => $division->dc_cug]
                : [],
            'district' => ($district = District::with('division.zone')->find($this->recipient_ref_id))
                ? [
                    'district' => $district->name, 'division' => $district->division?->name, 'zone' => $district->division?->zone?->name,
                    'officer' => $district->officerDisplayName(), 'cug' => $district->deo_cug,
                ]
                : [],
            'list_item' => ($item = RecipientListItem::find($this->recipient_ref_id))
                ? array_merge(['name' => $item->name, 'email' => $item->email], $item->extra ?? [])
                : [],
            // No backing row to re-fetch from — a typed email's "vars" are inherently static.
            'manual' => ['name' => $this->name, 'email' => $this->email],
            default => [],
        };
    }

    /**
     * Writes a corrected address back onto the zone/division/district/list-item this recipient
     * was resolved from, so future campaigns targeting the same scope pick it up too — not just
     * this one resend. Returns false (no-op) for a 'manual' recipient, which has no backing row.
     */
    public function saveEmailToDirectory(string $email): bool
    {
        [$modelClass, $column] = match ($this->recipient_type) {
            'zone' => [Zone::class, 'jc_email'],
            'division' => [Division::class, 'dc_email'],
            'district' => [District::class, 'deo_email'],
            'list_item' => [RecipientListItem::class, 'email'],
            default => [null, null],
        };

        if (! $modelClass || ! ($model = $modelClass::find($this->recipient_ref_id))) {
            return false;
        }

        $model->update([$column => $email]);

        return true;
    }
}
