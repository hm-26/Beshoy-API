<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ReportAttachment;
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

        $response = response()->file($disk->path($attachment->path), [
            'Content-Type' => $type,
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, max-age=3600',
            // Nothing we serve needs to run scripts. (PDFs are left alone: the browser's viewer needs more room.)
            ...($attachment->extension === 'pdf' ? [] : ['Content-Security-Policy' => "default-src 'none'; sandbox"]),
        ]);

        $fallback = str_replace(['%', '/', '\\'], '_', Str::ascii($attachment->original_name)) ?: 'file';
        $response->setContentDisposition(
            $request->boolean('download') ? ResponseHeaderBag::DISPOSITION_ATTACHMENT : ResponseHeaderBag::DISPOSITION_INLINE,
            $attachment->original_name,
            $fallback,
        );

        return $response;
    }
}
