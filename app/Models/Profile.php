<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Profile extends Model
{
    protected $fillable = ['name', 'role', 'bio', 'location', 'skills', 'ar'];

    protected function casts(): array
    {
        return ['skills' => 'array', 'ar' => 'array'];
    }

    /** The site has a single profile row. */
    public static function current(): self
    {
        return static::query()->firstOrCreate([], ['name' => 'Your Name', 'skills' => []]);
    }
}
