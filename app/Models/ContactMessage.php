<?php

namespace App\Models;

use App\Casts\SafeEncrypted;
use Illuminate\Database\Eloquent\Model;

class ContactMessage extends Model
{
    protected $fillable = ['name', 'email', 'phone', 'subject', 'message', 'ip_hash'];

    protected function casts(): array
    {
        // Everything a visitor typed is personal data: stored encrypted, decrypted only when the admin opens the inbox.
        return [
            'name' => SafeEncrypted::class,
            'email' => SafeEncrypted::class,
            'phone' => SafeEncrypted::class,
            'subject' => SafeEncrypted::class,
            'message' => SafeEncrypted::class,
            'read_at' => 'datetime',
        ];
    }
}
