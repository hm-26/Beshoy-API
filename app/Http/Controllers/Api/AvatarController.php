<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ProfileAvatar;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;

class AvatarController extends Controller
{
    private const MAX_KB = 3072;       // the browser already shrinks the picture; this is only a safety net
    private const MAX_PIXELS = 4000;   // per side (guards against decompression bombs)

    /** GET /profile/avatar — public. The URL carries ?v=<version>, so the answer can be cached for a long time. */
    public function show(): Response
    {
        $avatar = ProfileAvatar::query()->first();
        abort_unless($avatar, 404);

        $bytes = base64_decode($avatar->data, true);
        abort_if($bytes === false, 404);

        return response($bytes, 200, [
            'Content-Type' => $avatar->mime,
            'Content-Length' => (string) strlen($bytes),
            'Cache-Control' => 'public, max-age=31536000, immutable',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /** POST /admin/profile/avatar — multipart: avatar (JPG / PNG / WebP). Replaces the current picture. */
    public function store(Request $request): JsonResponse
    {
        $request->validate(['avatar' => ['required', 'file', 'max:'.self::MAX_KB]], [
            'avatar.required' => 'No picture was received.',
            'avatar.max' => 'The picture may be at most '.(self::MAX_KB / 1024).' MB.',
            'avatar.uploaded' => 'The upload failed. The picture may be larger than the server limit.',
        ]);

        // Trust the content, not the file name or the browser-supplied type: it must really be a JPG, PNG or WebP.
        $path = $request->file('avatar')->getRealPath();
        $info = @getimagesize($path);
        $types = [IMAGETYPE_JPEG => 'image/jpeg', IMAGETYPE_PNG => 'image/png', IMAGETYPE_WEBP => 'image/webp'];

        if (! $info || ! isset($types[$info[2]]) || $info[0] > self::MAX_PIXELS || $info[1] > self::MAX_PIXELS) {
            throw ValidationException::withMessages(['avatar' => ['Use a JPG, PNG or WebP picture (up to '.self::MAX_PIXELS.' px).']]);
        }

        $bytes = (string) file_get_contents($path);
        $avatar = ProfileAvatar::query()->first() ?? new ProfileAvatar();
        $avatar->fill([
            'mime' => $types[$info[2]],
            'version' => substr(sha1($bytes), 0, 12),
            'data' => base64_encode($bytes),
        ])->save();

        return response()->json(['data' => ['avatarVersion' => $avatar->version]]);
    }

    /** DELETE /admin/profile/avatar — back to the default picture. */
    public function destroy(): Response
    {
        ProfileAvatar::query()->delete();

        return response()->noContent();
    }
}
