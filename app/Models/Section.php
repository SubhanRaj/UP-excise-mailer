<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['name', 'slug', 'email', 'head_name'])]
class Section extends Model
{
    use SoftDeletes;

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function mailAccounts(): HasMany
    {
        return $this->hasMany(MailAccount::class);
    }
}
