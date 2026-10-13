<?php

namespace App\Http\Resources;

use App\Models\Profile;
use App\Models\ProfileAvatar;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProfileResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $p = $this->resource;

        // Only the known keys, only non-empty strings; always a JSON object ({}), never [].
        $contact = [];
        foreach (Profile::CONTACT_KEYS as $key) {
            $value = $p->contact[$key] ?? null;
            if (is_string($value) && trim($value) !== '') {
                $contact[$key] = trim($value);
            }
        }

        return [
            'name' => $p->name,
            'role' => $p->role,
            'bio' => $p->bio,
            'location' => $p->location,
            'skills' => $p->skills ?? [],
            'contact' => (object) $contact,
            'ar' => $p->ar,
            // null = no custom picture (the site shows its default one); otherwise GET /profile/avatar?v=<this value>
            'avatarVersion' => ProfileAvatar::currentVersion(),
        ];
    }
}
