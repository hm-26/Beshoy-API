<?php

namespace App\Models;

use App\Casts\SafeEncrypted;
use Illuminate\Database\Eloquent\Model;

class ProfileAvatar extends Model
{
    protected $fillable = ['mime', 'version', 'data'];

    protected $hidden = ['data'];

    protected function casts(): array
    {
        return ['data' => SafeEncrypted::class];
    }

    /** Current picture version for the public profile, or null if there is none (or the migration has not run yet). */
    public static function currentVersion(): ?string
    {
        try {
            return static::query()->value('version');
        } catch (\Throwable) {
            return null;
        }
    }
}
