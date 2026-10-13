<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ReportAttachment;
use App\Support\PdfGuard;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;

/**
 * Streams an attachment. Protected by the `signed:relative` middleware (see routes/api.php), so only links that the
 * API itself produced work. BinaryFileResponse handles HTTP Range requests, which makes video seeking work.
 */
class AttachmentController extends Controller
{
    public function file(Request $request, ReportAttachment $attachment): BinaryFileResponse
    {
        $disk = Storage::disk(ReportAttachment::DISK);
        abort_unless($disk->exists($attachment->path), 404);

        $type = ReportAttachment::TYPES[$attachment->extension][1] ?? 'application/octet-stream';
        if (str_starts_with($type, 'text/')) {
            $type .= '; charset=utf-8';
        }

        // A PDF that carries active content (e.g. uploaded before the upload check existed) is never rendered by the
        // browser: it is forced to a download as an opaque binary, so its scripts cannot run on this origin.
        $unsafePdf = $attachment->extension === 'pdf'
            && ! PdfGuard::isSafeStored($disk->path($attachment->path), $attachment->id);
        if ($unsafePdf) {
            $type = 'application/octet-stream';
        }

        $response = response()->file($disk->path($attachment->path), [
            'Content-Type' => $type,
            'X-Content-Type-Options' => 'nosniff',
            // PDFs are never cached: a copy stored by the browser before a file was found unsafe would keep opening inline.
            'Cache-Control' => $attachment->extension === 'pdf' ? 'private, no-store' : 'private, max-age=3600',
            // Nothing we serve needs to run scripts. (Clean PDFs are left alone: the browser's viewer needs more room.)
            ...($attachment->extension === 'pdf' && ! $unsafePdf ? [] : ['Content-Security-Policy' => "default-src 'none'; sandbox"]),
        ]);

        $fallback = str_replace(['%', '/', '\\'], '_', Str::ascii($attachment->original_name)) ?: 'file';
        $response->setContentDisposition(
            ($request->boolean('download') || $unsafePdf) ? ResponseHeaderBag::DISPOSITION_ATTACHMENT : ResponseHeaderBag::DISPOSITION_INLINE,
            $attachment->original_name,
            $fallback,
        );

        return $response;
    }
}
