<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'jc_name', 'jc_email', 'jc_cug'])]
class Zone extends Model
{
    public function divisions(): HasMany
    {
        return $this->hasMany(Division::class);
    }
}
