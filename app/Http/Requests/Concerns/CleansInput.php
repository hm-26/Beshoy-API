<?php

namespace App\Http\Requests\Concerns;

/**
 * Normalises every string a form request receives BEFORE validation:
 *  - invalid UTF-8 is scrubbed,
 *  - NUL and other C0/DEL control characters are removed (tab / newline / CR stay) — they crash PostgreSQL and
 *    can hide text in logs and terminals,
 *  - Unicode bidirectional OVERRIDE/ISOLATE characters are removed from prose fields, so text pasted from a hostile
 *    target cannot visually reorder a title ("Trojan Source"). They are kept inside the request/response code blocks,
 *    because a report may need to show them verbatim.
 */
trait CleansInput
{
    /** Dot-paths whose bidi characters must be preserved. */
    protected array $keepBidi = ['request.code', 'response.code'];

    public static function cleanString(string $value, bool $keepBidi = false): string
    {
        $value = mb_scrub($value, 'UTF-8');
        $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $value) ?? '';

        if (! $keepBidi) {
            $value = preg_replace('/[\x{202A}-\x{202E}\x{2066}-\x{2069}]/u', '', $value) ?? '';
        }

        return $value;
    }

    protected function cleanTree(mixed $value, string $path = ''): mixed
    {
        if (is_string($value)) {
            return self::cleanString($value, in_array($path, $this->keepBidi, true));
        }
        if (is_array($value)) {
            $out = [];
            foreach ($value as $key => $item) {
                $out[$key] = $this->cleanTree($item, $path === '' ? (string) $key : $path.'.'.$key);
            }

            return $out;
        }

        return $value;
    }

    protected function prepareForValidation(): void
    {
        $this->replace($this->cleanTree($this->all()));
    }
}
