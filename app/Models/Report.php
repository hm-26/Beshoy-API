<?php

namespace App\Models;

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
        return [
            'request' => 'array',
            'response' => 'array',
            'ar' => 'array',
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
