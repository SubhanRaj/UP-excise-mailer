<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
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

    /**
     * Quill's bullet lists are an <ol> whose <li>s carry data-list="bullet" plus an empty
     * <span class="ql-ui"> marker — that only renders as a bullet inside Quill's own editor CSS.
     * Sent as raw HTML (CampaignMail has no Quill stylesheet), it shows up as a numbered list.
     * Normalize on write so every stored body is plain HTML that renders correctly anywhere.
     */
    protected function body(): Attribute
    {
        return Attribute::make(
            set: fn (?string $value) => $value === null ? null : self::sanitizeQuillBody($value),
        );
    }

    public static function sanitizeQuillBody(string $html): string
    {
        if (! str_contains($html, 'data-list') && ! str_contains($html, 'ql-ui')) {
            return $html;
        }

        $dom = new \DOMDocument();
        libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="utf-8"?><div id="__root">'.$html.'</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();

        $xpath = new \DOMXPath($dom);

        foreach (iterator_to_array($xpath->query('//span[contains(concat(" ", normalize-space(@class), " "), " ql-ui ")]')) as $span) {
            $span->parentNode?->removeChild($span);
        }

        foreach (iterator_to_array($xpath->query('//li[@data-list="bullet"]')) as $li) {
            $li->removeAttribute('data-list');
            $li->setAttribute('style', 'margin-bottom:6px;');

            $ol = $li->parentNode;
            if ($ol instanceof \DOMElement && strtolower($ol->nodeName) === 'ol') {
                $ul = $dom->createElement('ul');
                $ul->setAttribute('style', 'margin:0 0 16px 0; padding-left:24px; line-height:1.7;');
                foreach (iterator_to_array($ol->childNodes) as $child) {
                    $ul->appendChild($child);
                }
                $ol->parentNode->replaceChild($ul, $ol);
            }
        }

        foreach (iterator_to_array($xpath->query('//li[@data-list="ordered"]')) as $li) {
            $li->removeAttribute('data-list');
        }

        $root = $xpath->query('//div[@id="__root"]')->item(0);
        $result = '';
        foreach ($root->childNodes as $child) {
            $result .= $dom->saveHTML($child);
        }

        return $result;
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
