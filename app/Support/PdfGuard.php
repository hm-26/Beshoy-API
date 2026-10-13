<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;

/**
 * Detects PDFs that carry active content (JavaScript, auto-run actions, launch actions, embedded files ...).
 *
 * Why: attachments are served inline from the site's own origin, so a PDF whose JavaScript runs in the viewer
 * (e.g. an `app.alert()` payload) executes in the context of the site. The scanner is used twice:
 *   1. on upload  -> such a file is rejected,
 *   2. on serving -> a file that is already stored (uploaded before this check existed) is never shown inline.
 *
 * It is deliberately strict and fails closed: anything it cannot read with confidence counts as unsafe.
 * Detection is done on a *normalised* view of the file:
 *   - `#xx` escapes inside names are decoded (`/J#61vaScript` == `/JavaScript`),
 *   - object streams (where PDF producers can hide objects) are inflated and scanned too.
 */
final class PdfGuard
{
    /** PDF names that mean "something can run / launch / leave the document". */
    private const BAD_NAMES = [
        'JS', 'JavaScript', 'OpenAction', 'AA', 'Launch', 'SubmitForm', 'ImportData', 'RichMedia',
        'XFA', 'EmbeddedFile', 'EmbeddedFiles', 'GoToR', 'GoToE', 'Rendition', 'Encrypt',
    ];

    /** Filters whose output is image data only; the stream is skipped, never decoded. */
    private const IMAGE_FILTERS = ['DCTDecode', 'DCT', 'JPXDecode', 'CCITTFaxDecode', 'CCF', 'JBIG2Decode'];

    /** Largest file scanned in memory. Bigger PDFs are treated as unsafe for inline viewing. */
    public const MAX_BYTES = 40 * 1024 * 1024;

    /** Largest amount of inflated data accepted for one stream / for the whole file (decompression-bomb guard). */
    private const MAX_STREAM = 64 * 1024 * 1024;

    private const MAX_TOTAL = 128 * 1024 * 1024;

    private const DELIM = '\s()<>\[\]{}\/%';

    /** Returns null when the PDF looks clean, otherwise a short human-readable reason. */
    public static function problem(string $path): ?string
    {
        $size = @filesize($path);
        if ($size === false || $size < 8) {
            return 'the file is empty or unreadable';
        }
        if ($size > self::MAX_BYTES) {
            return 'the PDF is too large to be checked for active content';
        }
        $raw = @file_get_contents($path);
        if ($raw === false || ! str_contains(substr($raw, 0, 1024), '%PDF-')) {
            return 'it is not a valid PDF';
        }

        return self::inspect($raw);
    }

    /** Cached verdict for a stored attachment (key includes size + mtime, so a replaced file is re-checked). */
    public static function isSafeStored(string $path, int|string $id): bool
    {
        $key = 'pdfguard:'.$id.':'.@filesize($path).':'.@filemtime($path);

        return (bool) Cache::remember($key, now()->addDay(), fn () => self::problem($path) === null);
    }

    public static function inspect(string $raw): ?string
    {
        if ($hit = self::scanText($raw)) {
            return $hit;
        }

        $total = 0;
        $offset = 0;
        while (($s = strpos($raw, 'stream', $offset)) !== false) {
            $offset = $s + 6;
            if ($s >= 3 && substr($raw, $s - 3, 3) === 'end') {
                continue; // that was "endstream"
            }
            $start = $s + 6;
            if (($raw[$start] ?? '') === "\r") {
                $start++;
            }
            if (($raw[$start] ?? '') === "\n") {
                $start++;
            }
            $end = strpos($raw, 'endstream', $start);
            if ($end === false) {
                return 'a stream is not terminated';
            }
            $offset = $end + 9;

            // The dictionary sits in the ~2 KB before the keyword, after the last "obj".
            $head = substr($raw, max(0, $s - 2048), min(2048, $s));
            $objAt = strrpos($head, 'obj');
            $dict = self::normaliseNames($objAt === false ? $head : substr($head, $objAt));

            $filters = self::filtersOf($dict);
            $isObjStm = (bool) preg_match('~/ObjStm(?=['.self::DELIM.']|$)~', $dict);
            $data = substr($raw, $start, $end - $start);

            if ($filters === []) {
                if ($isObjStm && ($hit = self::scanText($data))) {
                    return $hit;
                }

                continue;
            }

            $decoded = self::decode($data, $filters, $total);
            if ($decoded === false) {
                return 'a stream could not be decoded, so it cannot be checked';
            }
            if ($decoded === null) {
                continue; // image-only stream
            }
            if ($isObjStm && ($hit = self::scanText($decoded))) {
                return $hit;
            }
        }

        return null;
    }

    /** @return string|null reason when $text contains an active-content marker */
    private static function scanText(string $text): ?string
    {
        $text = self::normaliseNames($text);

        if (preg_match('~/('.implode('|', self::BAD_NAMES).')(?=['.self::DELIM.']|$)~', $text, $m)) {
            return 'it contains active content (/'.$m[1].')';
        }
        if (stripos($text, 'javascript:') !== false) {
            return 'it contains a javascript: link';
        }

        return null;
    }

    /** Decode `#xx` escapes inside PDF names so obfuscated names cannot dodge the checks. */
    private static function normaliseNames(string $text): string
    {
        if (! str_contains($text, '#')) {
            return $text;
        }

        return preg_replace_callback(
            '~/[^'.self::DELIM.']*#[0-9A-Fa-f]{2}[^'.self::DELIM.']*~',
            fn (array $m) => preg_replace_callback('~#([0-9A-Fa-f]{2})~', fn ($h) => chr(hexdec($h[1])), $m[0]),
            $text,
        ) ?? $text;
    }

    /** @return list<string> filter names from a (normalised) stream dictionary, in order */
    private static function filtersOf(string $dict): array
    {
        if (! preg_match('~/Filter\s*(\[[^\]]*\]|/[^'.self::DELIM.']+)~', $dict, $m)) {
            return [];
        }
        preg_match_all('~/([^'.self::DELIM.']+)~', $m[1], $names);

        return $names[1];
    }

    /**
     * Run a stream through its filter chain.
     *
     * @return string|false|null decoded bytes · false = cannot be decoded (unsafe) · null = image data, skipped
     */
    private static function decode(string $data, array $filters, int &$total): string|false|null
    {
        foreach ($filters as $filter) {
            if (in_array($filter, self::IMAGE_FILTERS, true)) {
                return null;
            }
            $data = match ($filter) {
                'FlateDecode', 'Fl' => self::inflate($data),
                'ASCIIHexDecode', 'AHx' => self::asciiHex($data),
                'ASCII85Decode', 'A85' => self::ascii85($data),
                default => false, // LZW, RunLength, Crypt, unknown: cannot be inspected
            };
            if ($data === false) {
                return false;
            }
            $total += strlen($data);
            if ($total > self::MAX_TOTAL) {
                return false;
            }
        }

        return $data;
    }

    private static function inflate(string $data): string|false
    {
        $data = ltrim($data, "\r\n");

        // zlib raises a PHP warning on bad data. The "@" operator is not honoured everywhere (PHPUnit turns the
        // warning into an exception), so swallow warnings with our own handler while decoding.
        set_error_handler(static fn (): bool => true);
        try {
            foreach ([
                fn () => gzuncompress($data, self::MAX_STREAM),
                fn () => zlib_decode($data, self::MAX_STREAM),
                fn () => strlen($data) > 2 ? gzinflate(substr($data, 2), self::MAX_STREAM) : false,
            ] as $try) {
                $out = $try();
                if (is_string($out)) {
                    return $out;
                }
            }
        } finally {
            restore_error_handler();
        }

        return false;
    }

    private static function asciiHex(string $data): string|false
    {
        $data = preg_replace('~\s+~', '', $data) ?? '';
        $data = rtrim($data, '>');
        if ($data === '' || preg_match('~[^0-9A-Fa-f]~', $data)) {
            return $data === '' ? '' : false;
        }
        if (strlen($data) % 2) {
            $data .= '0';
        }

        return hex2bin($data) ?: false;
    }

    private static function ascii85(string $data): string|false
    {
        $data = preg_replace('~\s+~', '', $data) ?? '';
        $data = preg_replace('#^<~#', '', $data);
        $data = preg_replace('#~>\s*$#', '', $data);
        $out = '';
        $group = [];
        foreach (str_split($data) as $ch) {
            if ($ch === 'z' && $group === []) {
                $out .= "\0\0\0\0";

                continue;
            }
            $v = ord($ch) - 33;
            if ($v < 0 || $v > 84) {
                return false;
            }
            $group[] = $v;
            if (count($group) === 5) {
                $n = 0;
                foreach ($group as $g) {
                    $n = $n * 85 + $g;
                }
                $out .= pack('N', $n & 0xFFFFFFFF);
                $group = [];
            }
        }
        if ($group !== []) {
            $pad = 5 - count($group);
            $n = 0;
            foreach (array_pad($group, 5, 84) as $g) {
                $n = $n * 85 + $g;
            }
            $out .= substr(pack('N', $n & 0xFFFFFFFF), 0, 4 - $pad);
        }

        return $out;
    }
}
