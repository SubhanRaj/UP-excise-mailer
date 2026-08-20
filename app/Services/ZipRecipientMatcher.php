<?php

namespace App\Services;

use Illuminate\Support\Str;

/**
 * Matches recipient names to filenames extracted from an uploaded zip — exact match on the
 * normalized (slugified, extension-stripped) name first, then substring containment (handles
 * real-world exports like "5_Shops_AGRA.xlsx" or an official long name "SANT_RAVIDAS_NAGAR_
 * BHADOHI" file for a recipient just called "Bhadohi"), then a Levenshtein fallback within a
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
                ?? $this->findContains($normName, $normalizedFiles, $used)
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

    /**
     * Catches a decorative prefix on the filename ("5_Shops_AGRA" for recipient "Agra") or an
     * official long name embedded in a short colloquial recipient name and vice versa
     * ("SANT_RAVIDAS_NAGAR_BHADOHI" for recipient "Bhadohi"; "KHERI" for recipient "Lakhimpur
     * Kheri"). Requires the shorter side to be at least 3 characters to avoid coincidental
     * short-string matches; picks the closest length match when several files qualify.
     */
    private function findContains(string $normName, array $normalizedFiles, array $used): ?string
    {
        if (strlen($normName) < 3) {
            return null;
        }

        $best = null;
        $bestLengthDiff = null;

        foreach ($normalizedFiles as $file => $normFile) {
            if (isset($used[$file])) {
                continue;
            }

            foreach ($this->prefixStrippedCandidates($normFile) as $candidate) {
                if (strlen($candidate) < 3) {
                    continue;
                }

                if (! str_contains($candidate, $normName) && ! str_contains($normName, $candidate)) {
                    continue;
                }

                $lengthDiff = abs(strlen($candidate) - strlen($normName));

                if ($bestLengthDiff === null || $lengthDiff < $bestLengthDiff) {
                    $bestLengthDiff = $lengthDiff;
                    $best = $file;
                }
            }
        }

        return $best;
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

            foreach ($this->prefixStrippedCandidates($normFile) as $candidate) {
                $distance = levenshtein($normName, $candidate);

                if ($distance <= $threshold && ($bestDistance === null || $distance < $bestDistance)) {
                    $bestDistance = $distance;
                    $best = $file;
                }
            }
        }

        return $best;
    }

    /**
     * @return string[] the normalized string itself, plus progressively-stripped versions
     *                   with leading hyphen-separated segments removed (up to 3), so a
     *                   decorative prefix like "5-shops-" doesn't block a real match.
     */
    private function prefixStrippedCandidates(string $normalized): array
    {
        $candidates = [$normalized];
        $segments = explode('-', $normalized);

        for ($i = 1; $i < count($segments) && $i <= 3; $i++) {
            $candidates[] = implode('-', array_slice($segments, $i));
        }

        return array_unique(array_filter($candidates, fn ($c) => $c !== ''));
    }
}
