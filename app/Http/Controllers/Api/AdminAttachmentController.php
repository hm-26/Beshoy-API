<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\AttachmentResource;
use App\Models\Report;
use App\Models\ReportAttachment;
use App\Support\PdfGuard;
use App\Support\ZipGuard;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AdminAttachmentController extends Controller
{
    /** POST /admin/reports/{report}/attachments — multipart: files[] (+ optional caption / captionAr). */
    public function store(Request $request, Report $report): JsonResponse
    {
        $maxMb = (int) config('uploads.max_mb');
        $data = $request->validate([
            'files' => ['required', 'array', 'min:1', 'max:'.config('uploads.max_files_per_request')],
            'files.*' => ['file', 'max:'.($maxMb * 1024)],
            'caption' => ['nullable', 'string', 'max:255'],
            'captionAr' => ['nullable', 'string', 'max:255'],
        ], [
            'files.required' => 'No file was received. It may be larger than the server limit (PHP upload_max_filesize / post_max_size).',
            'files.*.max' => "Each file may be at most {$maxMb} MB.",
            'files.*.uploaded' => 'The upload failed. The file may be larger than the server limit (PHP upload_max_filesize).',
        ]);

        // Validate every file first so a bad one does not leave half the batch stored.
        $checked = [];
        foreach ($request->file('files') as $i => $file) {
            $checked[] = [$file, $this->classify($file, $i)];
        }

        $order = (int) $report->attachments()->reorder()->max('sort_order');
        $created = [];
        foreach ($checked as [$file, [$ext, $kind, $mime]]) {
            $created[] = $report->attachments()->create([
                'path' => $file->storeAs("report-attachments/{$report->id}", Str::uuid().'.'.$ext, ReportAttachment::DISK),
                'original_name' => Str::limit($this->cleanName($file->getClientOriginalName()), 200, ''),
                'extension' => $ext,
                'mime' => $mime,
                'kind' => $kind,
                'size' => $file->getSize(),
                'caption' => $data['caption'] ?? null,
                'caption_ar' => $data['captionAr'] ?? null,
                'sort_order' => ++$order,
            ]);
        }

        return AttachmentResource::collection(collect($created))->response()->setStatusCode(201);
    }

    /** PATCH /admin/attachments/{attachment} — captions only. */
    public function update(Request $request, ReportAttachment $attachment): AttachmentResource
    {
        $data = $request->validate([
            'caption' => ['nullable', 'string', 'max:255'],
            'captionAr' => ['nullable', 'string', 'max:255'],
        ]);

        if (array_key_exists('caption', $data)) {
            $attachment->caption = $data['caption'];
        }
        if (array_key_exists('captionAr', $data)) {
            $attachment->caption_ar = $data['captionAr'];
        }
        $attachment->save();

        return new AttachmentResource($attachment);
    }

    public function destroy(ReportAttachment $attachment): Response
    {
        $attachment->delete(); // the model event removes the stored file

        return response()->noContent();
    }

    /**
     * Decide what an upload really is. The extension must be whitelisted AND the content (sniffed by PHP,
     * not the browser-supplied type) must match it, so "shell.php renamed to a.png" is rejected.
     *
     * @return array{0:string,1:string,2:string} extension, kind, mime
     */
    private function classify(UploadedFile $file, int $index): array
    {
        $ext = strtolower($file->getClientOriginalExtension());
        $fail = fn (string $msg) => throw ValidationException::withMessages(["files.$index" => $msg]);

        if (! isset(ReportAttachment::TYPES[$ext])) {
            $fail('File type ".'.$ext.'" is not allowed. Allowed: '.implode(', ', array_keys(ReportAttachment::TYPES)).'.');
        }
        [$kind, $mime] = ReportAttachment::TYPES[$ext];
        $detected = (string) $file->getMimeType();

        $ok = match (true) {
            $kind === 'image' => str_starts_with($detected, 'image/') && $detected !== 'image/svg+xml',
            $kind === 'video' => str_starts_with($detected, 'video/') || $detected === 'application/mp4',
            $ext === 'pdf' => $detected === 'application/pdf',
            $ext === 'zip' => in_array($detected, ['application/zip', 'application/x-zip-compressed'], true),
            in_array($ext, ReportAttachment::ZIP_LIKE, true) => in_array($detected, ['application/zip', 'application/x-zip-compressed', 'application/octet-stream', 'application/java-archive', 'application/epub+zip', 'application/vnd.android.package-archive'], true)
                || str_starts_with($detected, 'application/vnd.'),
            in_array($ext, ['mp3', 'wav', 'ogg', 'm4a'], true) => str_starts_with($detected, 'audio/') || $detected === 'application/ogg' || $detected === 'video/ogg' || $detected === 'video/mp4',
            default => str_starts_with($detected, 'text/') || in_array($detected, ['application/json', 'application/csv', 'application/x-ndjson', 'inode/x-empty', 'application/javascript', 'application/x-javascript', 'application/xml', 'application/x-httpd-php', 'application/x-sh', 'application/x-shellscript', 'application/sql', 'application/x-yaml', 'application/x-empty'], true),
        };
        if (! $ok) {
            $fail("The content of \"{$file->getClientOriginalName()}\" does not look like a .$ext file.");
        }

        // PDFs are shown inline from the site's own origin, so one that can run JavaScript / auto-actions is refused.
        if ($ext === 'pdf' && ($why = PdfGuard::problem((string) $file->getRealPath()))) {
            $fail("\"{$file->getClientOriginalName()}\" was rejected: {$why}. Remove JavaScript/actions from the PDF (or print it to a fresh PDF) and upload again.");
        }

        // ZIP-based files (zip itself, and Office/ODF containers): guard against zip bombs, and against macros /
        // remote-template links in Office documents, which run with the admin's privileges once opened.
        if (($ext === 'zip' || in_array($ext, ReportAttachment::ZIP_LIKE, true)) && ($why = ZipGuard::problem((string) $file->getRealPath(), $ext))) {
            $fail("\"{$file->getClientOriginalName()}\" was rejected: {$why}.");
        }

        return [$ext, $kind, $mime];
    }

    private function cleanName(string $name): string
    {
        return trim(preg_replace('/[\x00-\x1F\x7F\/\\\\]+/', '_', $name)) ?: 'file';
    }
}
