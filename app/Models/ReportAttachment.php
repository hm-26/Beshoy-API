<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class ReportAttachment extends Model
{
    /** Files live on the private "local" disk (storage/app/private) and are only served through the API. */
    public const DISK = 'local';

    /**
     * Allowed uploads, keyed by file extension: [kind, served mime type].
     * Source code / markup is accepted too, but is ALWAYS served as text/plain (plus nosniff and a sandbox CSP), so the
     * browser can never run it; the site only displays it in its code viewer. Files live on a private disk.
     */
    public const TYPES = [
        'jpg' => ['image', 'image/jpeg'], 'jpeg' => ['image', 'image/jpeg'],
        'png' => ['image', 'image/png'], 'gif' => ['image', 'image/gif'], 'webp' => ['image', 'image/webp'],
        'mp4' => ['video', 'video/mp4'], 'm4v' => ['video', 'video/mp4'],
        'webm' => ['video', 'video/webm'], 'mov' => ['video', 'video/quicktime'],
        'mp3' => ['file', 'audio/mpeg'], 'wav' => ['file', 'audio/wav'], 'ogg' => ['file', 'audio/ogg'], 'm4a' => ['file', 'audio/mp4'],
        'pdf' => ['file', 'application/pdf'],
        'csv' => ['file', 'text/csv'], 'json' => ['file', 'application/json'], 'har' => ['file', 'application/json'],
        'zip' => ['file', 'application/zip'],
        // ZIP-based containers (the viewer opens them like a folder)
        'docx' => ['file', 'application/octet-stream'], 'xlsx' => ['file', 'application/octet-stream'],
        'pptx' => ['file', 'application/octet-stream'], 'odt' => ['file', 'application/octet-stream'],
        'ods' => ['file', 'application/octet-stream'], 'jar' => ['file', 'application/octet-stream'],
        'apk' => ['file', 'application/octet-stream'], 'epub' => ['file', 'application/octet-stream'],
        // text & source code (served as text/plain)
        'txt' => ['file', 'text/plain'], 'log' => ['file', 'text/plain'], 'md' => ['file', 'text/plain'], 'markdown' => ['file', 'text/plain'],
        'tsv' => ['file', 'text/plain'], 'xml' => ['file', 'text/plain'], 'html' => ['file', 'text/plain'], 'htm' => ['file', 'text/plain'],
        'css' => ['file', 'text/plain'], 'scss' => ['file', 'text/plain'], 'less' => ['file', 'text/plain'],
        'js' => ['file', 'text/plain'], 'mjs' => ['file', 'text/plain'], 'cjs' => ['file', 'text/plain'], 'jsx' => ['file', 'text/plain'],
        'ts' => ['file', 'text/plain'], 'tsx' => ['file', 'text/plain'], 'vue' => ['file', 'text/plain'], 'php' => ['file', 'text/plain'],
        'py' => ['file', 'text/plain'], 'rb' => ['file', 'text/plain'], 'go' => ['file', 'text/plain'], 'java' => ['file', 'text/plain'],
        'kt' => ['file', 'text/plain'], 'c' => ['file', 'text/plain'], 'h' => ['file', 'text/plain'], 'cpp' => ['file', 'text/plain'],
        'hpp' => ['file', 'text/plain'], 'cs' => ['file', 'text/plain'], 'rs' => ['file', 'text/plain'], 'swift' => ['file', 'text/plain'],
        'sh' => ['file', 'text/plain'], 'bash' => ['file', 'text/plain'], 'bat' => ['file', 'text/plain'], 'ps1' => ['file', 'text/plain'],
        'sql' => ['file', 'text/plain'], 'yml' => ['file', 'text/plain'], 'yaml' => ['file', 'text/plain'], 'toml' => ['file', 'text/plain'],
        'ini' => ['file', 'text/plain'], 'conf' => ['file', 'text/plain'], 'cfg' => ['file', 'text/plain'], 'env' => ['file', 'text/plain'],
        'http' => ['file', 'text/plain'], 'diff' => ['file', 'text/plain'], 'patch' => ['file', 'text/plain'],
    ];

    /** Extensions that are ZIP containers under the hood. */
    public const ZIP_LIKE = ['zip', 'docx', 'xlsx', 'pptx', 'odt', 'ods', 'jar', 'apk', 'epub'];

    protected $fillable = [
        'report_id', 'path', 'original_name', 'extension', 'mime', 'kind', 'size',
        'caption', 'caption_ar', 'sort_order',
    ];

    protected function casts(): array
    {
        return ['size' => 'integer', 'sort_order' => 'integer'];
    }

    protected static function booted(): void
    {
        // Remove the stored file whenever the row goes away (single delete or via the report).
        static::deleting(fn (self $a) => Storage::disk(self::DISK)->delete($a->path));
    }

    public function report(): BelongsTo
    {
        return $this->belongsTo(Report::class);
    }
}
