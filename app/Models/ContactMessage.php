<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ContactMessage extends Model
{
    protected $fillable = ['name', 'email', 'phone', 'subject', 'message', 'ip_hash'];

    protected function casts(): array
    {
        return ['read_at' => 'datetime'];
    }
}
