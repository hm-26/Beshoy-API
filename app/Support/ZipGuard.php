<?php

namespace App\Support;

use ZipArchive;

/**
 * Checks on ZIP-based uploads (.zip itself, and ZIP-based containers: .docx .xlsx .pptx .odt .ods .jar .apk .epub).
 * These are always served as `application/octet-stream` (forced download, never opened by the browser — see
 * ReportAttachment::TYPES), so this is not about script execution in the browser. It guards two other things:
 *
 *   1. Zip bombs: a tiny file that expands to gigabytes and can exhaust disk/CPU for anyone who opens it
 *      (including the admin, locally, when they open an attachment they just reviewed).
 *   2. Office macros / external relationships: a .docx/.xlsx/.pptx can carry a VBA macro (vbaProject.bin) or a
 *      relationship that loads remote content (TargetMode="External", e.g. a "remote template" injection). Those
 *      run with the *admin's* privileges the moment the file is opened in Word/Excel/PowerPoint, so they are
 *      rejected rather than merely downloaded.
 */
final class ZipGuard
{
    private const MAX_ENTRIES = 5000;
    private const MAX_RATIO = 200;                  // uncompressed / compressed, per entry
    private const MAX_UNCOMPRESSED = 512 * 1024 * 1024;

    private const OFFICE_EXTENSIONS = ['docx', 'xlsx', 'pptx', 'odt', 'ods'];

    private const MACRO_MARKERS = [
        'vbaProject.bin', 'vbaProject.bin.rels', 'macros/', // OOXML / ODF macro containers
    ];

    /** Returns null when the archive looks safe to store, otherwise a short human-readable reason. */
    public static function problem(string $path, string $ext): ?string
    {
        $zip = new ZipArchive();
        if ($zip->open($path) !== true) {
            return 'it is not a valid ZIP-based file';
        }

        try {
            $count = $zip->numFiles;
            if ($count > self::MAX_ENTRIES) {
                return 'the archive has too many entries to be checked safely';
            }

            $totalUncompressed = 0;
            for ($i = 0; $i < $count; $i++) {
                $stat = $zip->statIndex($i);
                if ($stat === false) {
                    return 'the archive index could not be read';
                }
                $name = $stat['name'];
                if (str_contains($name, '..') || str_starts_with($name, '/')) {
                    return 'an entry inside the archive has an unsafe path';
                }

                $totalUncompressed += $stat['size'];
                if ($totalUncompressed > self::MAX_UNCOMPRESSED) {
                    return 'the archive expands to an unreasonably large size (possible zip bomb)';
                }
                if ($stat['comp_size'] > 0 && ($stat['size'] / max(1, $stat['comp_size'])) > self::MAX_RATIO) {
                    return 'an entry\'s compression ratio is suspiciously high (possible zip bomb)';
                }

                if (in_array($ext, self::OFFICE_EXTENSIONS, true)) {
                    foreach (self::MACRO_MARKERS as $marker) {
                        if (stripos($name, $marker) !== false) {
                            return 'the document contains a macro, which is not allowed';
                        }
                    }
                }
            }

            if (in_array($ext, self::OFFICE_EXTENSIONS, true) && ($hit = self::externalRelationship($zip))) {
                return $hit;
            }
        } finally {
            $zip->close();
        }

        return null;
    }

    /** True if any .rels part points outside the document (TargetMode="External") — a remote-template / remote-image injection. */
    private static function externalRelationship(ZipArchive $zip): ?string
    {
        $count = $zip->numFiles;
        for ($i = 0; $i < $count; $i++) {
            $name = $zip->statIndex($i)['name'] ?? '';
            if (! str_ends_with($name, '.rels')) {
                continue;
            }
            $xml = $zip->getFromIndex($i, 2 * 1024 * 1024);
            if (is_string($xml) && stripos($xml, 'TargetMode="External"') !== false) {
                return 'the document links to external content, which is not allowed';
            }
        }

        return null;
    }
}
