<?php

declare(strict_types=1);

namespace App\Services\Search;

/**
 * Checks names against the typed query with the same folding for every kind of result,
 * so places, water sources and maps rank their exact, prefix and word matches alike.
 */
final class NameMatcher
{
    public const EXACT = 1.5;

    public const PREFIX = 0.5;

    // Every query word starts a word of the name.
    public const WORDS = 0.0;

    private readonly string $needle;

    private readonly ?string $script;

    public function __construct(string $term)
    {
        $this->needle = SearchText::fold($term);
        $this->script = SearchText::script($term);
    }

    /** The ranking bonus, or null when the name does not match the query. */
    public function bonus(?string $name): ?float
    {
        // Folding transliterates, so "мос" would also match "Moscow"; only compare names in the query's script.
        if ($name === null || $this->needle === '' || ($this->script !== null && preg_match('/\p{'.$this->script.'}/u', $name) !== 1)) {
            return null;
        }

        $name = SearchText::fold($name);

        return match (true) {
            $name === $this->needle => self::EXACT,
            str_starts_with($name, $this->needle) => self::PREFIX,
            str_contains(' '.$name, ' '.$this->needle), $this->startsWords($name) => self::WORDS,
            default => null,
        };
    }

    // Every query word starts a different word of the name, in any order: "novgorod ve" finds "Velikiy Novgorod".
    private function startsWords(string $name): bool
    {
        $words = explode(' ', $name);
        $prefixes = explode(' ', $this->needle);
        // Longest first: a shorter prefix of the same word can still take another one.
        usort($prefixes, fn (string $a, string $b): int => mb_strlen($b) <=> mb_strlen($a));

        foreach ($prefixes as $prefix) {
            $index = array_find_key($words, fn (string $word): bool => str_starts_with($word, $prefix));

            if ($index === null) {
                return false;
            }

            unset($words[$index]);
        }

        return true;
    }
}
