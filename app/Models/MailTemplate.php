<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['name', 'subject', 'body', 'variables', 'created_by'])]
class MailTemplate extends Model
{
    use SoftDeletes;

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

    /**
     * Builds a literal "{{name}}" placeholder string. Exists so Blade views never write the
     * two literal brace-pairs themselves — Blade's echo compiler scans the raw .blade.php file
     * text for `{{ ... }}` before it understands PHP string literals or @php blocks, so writing
     * '{{'.$var.'}}' directly in a view (even inside @php()) gets mangled into the compiled PHP
     * source itself. Keeping the concatenation here, in a plain .php file Blade never compiles,
     * sidesteps that entirely.
     */
    public static function variableToken(string $name): string
    {
        return '{{'.$name.'}}';
    }
}
