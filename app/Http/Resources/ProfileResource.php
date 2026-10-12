<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProfileResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $p = $this->resource;

        return [
            'name' => $p->name,
            'role' => $p->role,
            'bio' => $p->bio,
            'location' => $p->location,
            'skills' => $p->skills ?? [],
            'ar' => $p->ar,
        ];
    }
}
