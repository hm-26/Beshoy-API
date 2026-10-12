<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\URL;

class AttachmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $a = $this->resource;

        // Round to the hour so the URL stays identical for a while and the browser can cache the file.
        $expires = now()->addHours(config('uploads.url_ttl_hours'))->startOfHour();
        // Relative links (/api/attachments/..) resolve against whatever origin the API is served from.
        $link = fn (array $extra = []) => URL::temporarySignedRoute(
            'attachments.file', $expires, ['attachment' => $a->id] + $extra, absolute: false
        );

        return [
            'id' => $a->id,
            'kind' => $a->kind,            // image | video | file
            'name' => $a->original_name,
            'extension' => $a->extension,
            'mime' => $a->mime,
            'size' => $a->size,
            'caption' => $a->caption,
            'ar' => ['caption' => $a->caption_ar],
            'url' => $link(),                       // inline (view in the site)
            'downloadUrl' => $link(['download' => 1]),
        ];
    }
}
