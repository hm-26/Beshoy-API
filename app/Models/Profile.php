<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Profile extends Model
{
    /** Contact fields the admin can publish. Anything else is dropped by the form request and the resource. */
    public const CONTACT_KEYS = [
        'email', 'phone', 'whatsapp', 'telegram',
        'linkedin', 'github', 'x', 'facebook', 'instagram', 'youtube', 'website',
    ];

    protected $fillable = ['name', 'role', 'bio', 'location', 'skills', 'contact', 'ar'];

    protected function casts(): array
    {
        return ['skills' => 'array', 'contact' => 'array', 'ar' => 'array'];
    }

    /** The site has a single profile row. */
    public static function current(): self
    {
        return static::query()->firstOrCreate([], ['name' => 'Your Name', 'skills' => []]);
    }
}
