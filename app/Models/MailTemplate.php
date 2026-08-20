<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['name', 'subject', 'body', 'variables', 'created_by'])]
class MailTemplate extends Model
{
    protected function casts(): array
    {
        return [
            'variables' => 'array',
        ];
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Replaces {{variable}} placeholders with values from an associative array.
     * Unknown placeholders are left as-is so a typo is visible rather than silently blanked.
     */
    public static function render(string $text, array $values): string
    {
        return preg_replace_callback('/\{\{\s*([a-zA-Z0-9_]+)\s*\}\}/', function (array $matches) use ($values) {
            return array_key_exists($matches[1], $values) ? (string) $values[$matches[1]] : $matches[0];
        }, $text);
    }
}
