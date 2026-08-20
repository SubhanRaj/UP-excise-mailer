<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'zone_id', 'dc_name', 'dc_email', 'dc_cug'])]
class Division extends Model
{
    public function zone(): BelongsTo
    {
        return $this->belongsTo(Zone::class);
    }

    public function districts(): HasMany
    {
        return $this->hasMany(District::class);
    }
}
