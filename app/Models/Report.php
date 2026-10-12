<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

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

    public function scopePublished($query)
    {
        return $query->where('published', true);
    }
}
