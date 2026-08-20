<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['name', 'division_id', 'deo_name', 'deo_email', 'deo_cug', 'latitude', 'longitude'])]
class District extends Model
{
    protected function casts(): array
    {
        return [
            'latitude' => 'decimal:6',
            'longitude' => 'decimal:6',
        ];
    }

    public function division(): BelongsTo
    {
        return $this->belongsTo(Division::class);
    }
}
