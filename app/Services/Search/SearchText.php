<?php

declare(strict_types=1);

namespace App\Services\Search;

use Transliterator;

final class SearchText
{
    // Letters and digits that stand on their own. The full-text parser drops combining marks, modifier letters
    // such as the halfwidth voiced mark and variation selectors, so a word of them alone would match every row.
    private const BASE_CHARACTERS = '\p{Ll}\p{Lu}\p{Lt}\p{Lo}\p{Nd}';

    private const SEPARATORS = '[\s\p{Pd}\'’ʼ`]+';

    // InnoDB's default full-text stopwords that are long enough to be indexed.
    private const STOPWORDS = ['about', 'are', 'com', 'for', 'from', 'how', 'that', 'the', 'this', 'was', 'what', 'when', 'where', 'who', 'will', 'with', 'und', 'www'];

    private const FULL_TEXT_WORDS = 4;

    private static ?Transliterator $folding = null;

    public static function baseLength(string $value): int
    {
        return (int) preg_match_all('/['.self::BASE_CHARACTERS.']/u', $value);
    }

    /** A boolean-mode query that requires every indexed word as a prefix, or null when no word is long enough. */
    public static function fullTextQuery(string $term): ?string
    {
        $words = self::indexedWords($term);

        return $words === [] ? null : implode(' ', array_map(fn (string $word): string => '+'.$word.'*', $words));
    }

    /**
     * Query words the full-text query leaves out: too short for the index, stopwords, or beyond its first
     * words ("st" in "st spring", "св" in "св ключ"). Results must still contain them.
     *
     * @return list<string>
     */
    public static function unindexedWords(string $term): array
    {
        return array_values(array_diff(self::words($term), self::indexedWords($term)));
    }

    /**
     * The term as a LIKE pattern without wildcards around it. Any run of separators matches one character, so
     * "rostov na" finds "Rostov-na-Donu" and "xi'an" finds "Xi’an". That character may also be a letter
     * ("st pe" fetches "Stapel"); the name matcher drops those. A leading apostrophe ("'s-Hertogenbosch")
     * stays literal, as a leading wildcard would scan the whole name index.
     */
    public static function likePattern(string $term): string
    {
        return preg_replace('/(?<=.)'.self::SEPARATORS.'/u', '_', str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $term)) ?? $term;
    }

    /**
     * Folds the query and names alike, at least as far as the database collations do ("lodz" and "Łódź",
     * "орел" and "Орёл"), and turns every run of spaces, hyphens, apostrophes and punctuation into one space.
     */
    public static function fold(string $value): string
    {
        self::$folding ??= Transliterator::create('Any-Latin; Latin-ASCII; Lower()');
        $value = self::$folding?->transliterate($value) ?: mb_strtolower($value);

        return mb_trim(preg_replace(['/\p{M}+/u', '/[^\p{L}\p{N}]+/u'], ['', ' '], $value) ?? $value);
    }

    public static function script(string $term): ?string
    {
        return match (true) {
            preg_match('/\p{Cyrillic}/u', $term) === 1 => 'Cyrillic',
            preg_match('/\p{Latin}/u', $term) === 1 => 'Latin',
            default => null,
        };
    }

    // Some GeoNames Russian names carry stress marks ("Ю́жная Гео́ргия").
    public static function display(?string $name): ?string
    {
        return $name === null ? null : (preg_replace('/(?<=\p{Cyrillic})[\x{0300}\x{0301}]/u', '', $name) ?? $name);
    }

    /** @param  array<int, ?string>  $parts */
    public static function join(array $parts, string $separator = ', '): ?string
    {
        $parts = array_filter($parts, fn (?string $part): bool => $part !== null && $part !== '');

        return $parts === [] ? null : implode($separator, $parts);
    }

    /** @return list<string> */
    private static function indexedWords(string $term): array
    {
        $words = array_filter(self::words($term), fn (string $word): bool => mb_strlen($word) >= 3 && ! in_array($word, self::STOPWORDS, true));

        // Every term adds a scan of its index entries, so long queries keep only their first words.
        return array_slice(array_values($words), 0, self::FULL_TEXT_WORDS);
    }

    /** @return list<string> */
    private static function words(string $term): array
    {
        // Apostrophes separate words as in the index ("Xi’an"); the modifier letter "ʼ" is not punctuation.
        $words = preg_split('/(?:[^\p{L}\p{M}\p{N}]|ʼ)+/u', mb_strtolower($term), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $words = array_map(fn (string $word): string => preg_replace('/[^'.self::BASE_CHARACTERS.']+/u', '', $word) ?? '', $words);

        return array_values(array_filter(array_unique($words), fn (string $word): bool => $word !== ''));
    }
}
