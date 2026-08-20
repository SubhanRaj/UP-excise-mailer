<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['recipient_list_id', 'name', 'email', 'extra'])]
class RecipientListItem extends Model
{
    protected function casts(): array
    {
        return [
            'extra' => 'array',
        ];
    }

    public function recipientList(): BelongsTo
    {
        return $this->belongsTo(RecipientList::class);
    }
}
