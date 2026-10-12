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
     * SVG / HTML / JS / PHP are deliberately absent — they can carry scripts.
     */
    public const TYPES = [
        'jpg' => ['image', 'image/jpeg'], 'jpeg' => ['image', 'image/jpeg'],
        'png' => ['image', 'image/png'], 'gif' => ['image', 'image/gif'], 'webp' => ['image', 'image/webp'],
        'mp4' => ['video', 'video/mp4'], 'm4v' => ['video', 'video/mp4'],
        'webm' => ['video', 'video/webm'], 'mov' => ['video', 'video/quicktime'],
        'pdf' => ['file', 'application/pdf'],
        'txt' => ['file', 'text/plain'], 'log' => ['file', 'text/plain'], 'md' => ['file', 'text/plain'],
        'csv' => ['file', 'text/csv'], 'json' => ['file', 'application/json'], 'har' => ['file', 'application/json'],
        'zip' => ['file', 'application/zip'],
    ];

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
