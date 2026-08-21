<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['campaign_id', 'recipient_type', 'recipient_ref_id', 'name', 'email', 'attachment_path', 'matched_via', 'status', 'error_message', 'sent_at', 'failed_at'])]
class CampaignRecipient extends Model
{
    protected function casts(): array
    {
        return [
            'sent_at' => 'datetime',
            'failed_at' => 'datetime',
        ];
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
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
}
