<?php

namespace App\Services;

use Illuminate\Support\Str;

/**
 * Matches recipient names to filenames extracted from an uploaded zip — exact match on the
 * normalized (slugified, extension-stripped) name first, then a Levenshtein fallback within a
 * length-proportional threshold. Each filename is used at most once. Never silent: callers
 * always get a per-recipient result they can override in a confirmation table before sending.
 */
class ZipRecipientMatcher
{
    public static function normalize(string $value): string
    {
        return Str::slug(pathinfo($value, PATHINFO_FILENAME));
    }

    /**
     * @param  array<int|string, string>  $recipientNames  keyed by recipient id/key
     * @param  string[]  $filenames
     * @return array<int|string, array{filename: ?string, matched_via: string}>
     */
    public function match(array $recipientNames, array $filenames): array
    {
        $normalizedFiles = [];
        foreach ($filenames as $file) {
            $normalizedFiles[$file] = self::normalize($file);
        }

        $used = [];
        $results = [];

        foreach ($recipientNames as $key => $name) {
            $normName = self::normalize((string) $name);
            $match = $this->findExact($normName, $normalizedFiles, $used)
                ?? $this->findFuzzy($normName, $normalizedFiles, $used);

            if ($match !== null) {
                $used[$match] = true;
            }

            $results[$key] = [
                'filename' => $match,
                'matched_via' => $match !== null ? 'filename_auto' : 'none',
            ];
        }

        return $results;
    }

    private function findExact(string $normName, array $normalizedFiles, array $used): ?string
    {
        foreach ($normalizedFiles as $file => $normFile) {
            if (! isset($used[$file]) && $normFile === $normName) {
                return $file;
            }
        }

        return null;
    }

    private function findFuzzy(string $normName, array $normalizedFiles, array $used): ?string
    {
        if ($normName === '') {
            return null;
        }

        $threshold = max(2, (int) round(strlen($normName) * 0.3));
        $best = null;
        $bestDistance = null;

        foreach ($normalizedFiles as $file => $normFile) {
            if (isset($used[$file])) {
                continue;
            }

            $distance = levenshtein($normName, $normFile);

            if ($distance <= $threshold && ($bestDistance === null || $distance < $bestDistance)) {
                $bestDistance = $distance;
                $best = $file;
            }
        }

        return $best;
    }
}
