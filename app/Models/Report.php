<?php

namespace App\Models;

use App\Casts\SafeEncrypted;
use App\Casts\SafeEncryptedJson;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Report extends Model
{
    protected $fillable = [
        'platform', 'program_type', 'bounty', 'asset', 'weakness', 'title', 'summary', 'severity',
        'steps_to_reproduce', 'request', 'response', 'supporting_references', 'endpoint',
        'impact', 'recommendation', 'ar', 'published',
    ];

    protected function casts(): array
    {
        // Everything written in a report is stored encrypted. Only what the database must filter or sort by
        // (`severity`, `published`) stays readable.
        return [
            'platform' => SafeEncrypted::class,
            'program_type' => SafeEncrypted::class,
            'bounty' => SafeEncrypted::class,
            'asset' => SafeEncrypted::class,
            'weakness' => SafeEncrypted::class,
            'title' => SafeEncrypted::class,
            'summary' => SafeEncrypted::class,
            'steps_to_reproduce' => SafeEncrypted::class,
            'supporting_references' => SafeEncrypted::class,
            'endpoint' => SafeEncrypted::class,
            'impact' => SafeEncrypted::class,
            'recommendation' => SafeEncrypted::class,
            'request' => SafeEncryptedJson::class,
            'response' => SafeEncryptedJson::class,
            'ar' => SafeEncryptedJson::class,
            'published' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        // Row-level cascade does not fire model events, so delete attachments one by one to remove their files.
        static::deleting(fn (self $report) => $report->attachments->each->delete());
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(ReportAttachment::class)->orderBy('sort_order')->orderBy('id');
    }

    public function scopePublished($query)
    {
        return $query->where('published', true);
    }
}
