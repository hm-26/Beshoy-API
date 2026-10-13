<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ReportAttachment;
use App\Support\FileVault;
use App\Support\PdfGuard;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Streams an attachment. Protected by the `signed:relative` middleware (see routes/api.php), so only links that the
 * API itself produced work. Files are stored encrypted (App\Support\FileVault) and decrypted on the fly, chunk by chunk;
 * HTTP Range requests are supported, which makes video seeking work. Older plaintext files are still served as before.
 */
class AttachmentController extends Controller
{
    public function file(Request $request, ReportAttachment $attachment): Response
    {
        $disk = Storage::disk(ReportAttachment::DISK);
        abort_unless($disk->exists($attachment->path), 404);
        $path = $disk->path($attachment->path);

        $type = ReportAttachment::TYPES[$attachment->extension][1] ?? 'application/octet-stream';
        if (str_starts_with($type, 'text/')) {
            $type .= '; charset=utf-8';
        }

        // A PDF that carries active content (e.g. uploaded before the upload check existed) is never shown in the
        // browser. A visitor who insists can still save it to their own device (`download=1`, a signed parameter that
        // cannot be added by hand): it is then sent as an opaque binary attachment, under a ".unsafe" file name so that
        // a double-click does not open it in a PDF reader. The site shows two warnings before it offers that link.
        $unsafePdf = $attachment->extension === 'pdf' && ! PdfGuard::isSafeStored($path, $attachment->id);
        abort_if($unsafePdf && ! $request->boolean('download'), 403, 'This PDF contains active content and was blocked.');
        if ($unsafePdf) {
            $type = 'application/octet-stream';
        }

        $name = $attachment->original_name.($unsafePdf ? '.unsafe' : '');
        $fallback = str_replace(['%', '/', '\\'], '_', Str::ascii($name)) ?: 'file';
        $headers = [
            'Content-Type' => $type,
            'X-Content-Type-Options' => 'nosniff',
            'Accept-Ranges' => 'bytes',
            // PDFs are never cached: a copy stored by the browser before a file was found unsafe would keep opening inline.
            'Cache-Control' => $attachment->extension === 'pdf' ? 'private, no-store' : 'private, max-age=3600',
            'Content-Disposition' => HeaderUtils::makeDisposition(
                $request->boolean('download') ? HeaderUtils::DISPOSITION_ATTACHMENT : HeaderUtils::DISPOSITION_INLINE,
                $name,
                $fallback,
            ),
            // Nothing we serve needs to run scripts. (Clean PDFs are left alone: the browser's viewer needs more room.)
            ...($attachment->extension === 'pdf' && ! $unsafePdf ? [] : ['Content-Security-Policy' => "default-src 'none'; sandbox"]),
        ];

        if (! FileVault::isEncrypted($path)) {
            return response()->file($path, $headers); // legacy plaintext file (BinaryFileResponse handles Range itself)
        }

        $size = FileVault::plainSize($path);
        if ($size === null || ! FileVault::verify($path)) {
            abort(500, 'The file could not be read.');
        }

        [$from, $to, $partial] = $this->range((string) $request->header('Range'), $size);
        if ($from === null) {
            return response('', 416, ['Content-Range' => "bytes */{$size}"] + $headers);
        }
        $headers['Content-Length'] = (string) ($size === 0 ? 0 : $to - $from + 1);
        if ($partial) {
            $headers['Content-Range'] = "bytes {$from}-{$to}/{$size}";
        }

        return new StreamedResponse(function () use ($path, $from, $to, $size) {
            if ($size === 0) {
                return;
            }
            foreach (FileVault::read($path, $from, $to) as $chunk) {
                echo $chunk;
                if (connection_aborted()) {
                    return;
                }
                flush();
            }
        }, $partial ? 206 : 200, $headers);
    }

    /**
     * Parses a single "bytes=a-b" range. Anything else (several ranges, other units, nonsense) means "send everything".
     *
     * @return array{0:?int,1:int,2:bool} from (null = unsatisfiable), to, is-partial
     */
    private function range(string $header, int $size): array
    {
        if (! preg_match('/^bytes=(\d*)-(\d*)$/', trim($header), $m) || ($m[1] === '' && $m[2] === '')) {
            return [0, max(0, $size - 1), false];
        }
        if ($size === 0) {
            return [null, 0, true];
        }

        if ($m[1] === '') {                       // "-N": the last N bytes
            $length = (int) $m[2];
            if ($length === 0) {
                return [null, 0, true];
            }
            return [max(0, $size - $length), $size - 1, true];
        }

        $from = (int) $m[1];
        $to = $m[2] === '' ? $size - 1 : min((int) $m[2], $size - 1);
        if ($from >= $size || $from > $to) {
            return [null, 0, true];
        }

        return [$from, $to, true];
    }
}
