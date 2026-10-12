<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ContactMessageResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $m = $this->resource;

        return [
            'id' => $m->id,
            'name' => $m->name,
            'email' => $m->email,
            'phone' => $m->phone,
            'subject' => $m->subject,
            'message' => $m->message,
            'read' => $m->read_at !== null,
            'readAt' => $m->read_at?->toIso8601String(),
            'createdAt' => $m->created_at?->toIso8601String(),
        ];
    }
}
