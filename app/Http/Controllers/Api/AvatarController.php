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

    /**
     * GET /profile/avatar — public, but only a SMALL copy (128 px) for what cannot send the seal handshake: the browser-tab
     * icon, link previews (og:image) and crawlers. The full-size picture is only served sealed (see sealed()).
     * Without the GD extension, or with no picture uploaded, it redirects to the site's default logo.
     */
    public function show(Request $request): Response
    {
        $avatar = ProfileAvatar::query()->first();
        $bytes = $avatar ? base64_decode($avatar->data, true) : false;
        $thumb = $bytes !== false ? $this->thumbnail($bytes) : null;

        if ($thumb === null) {
            return response('', 302, ['Location' => '/images/logo.png', 'Cache-Control' => 'public, max-age=300']);
        }

        return response($thumb, 200, [
            'Content-Type' => 'image/png',
            'Content-Length' => (string) strlen($thumb),
            'Cache-Control' => 'public, max-age=300, must-revalidate',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /** GET /profile/avatar/sealed — the full picture as { mime, image: base64 }; the seal middleware encrypts it for the asking browser. */
    public function sealed(): JsonResponse
    {
        $avatar = ProfileAvatar::query()->first();
        abort_if(! $avatar, 404);

        return response()->json(['data' => ['mime' => $avatar->mime, 'image' => $avatar->data]]);
    }

    private function thumbnail(string $bytes): ?string
    {
        if (! function_exists('imagecreatefromstring')) {
            return null;
        }
        $src = @imagecreatefromstring($bytes);
        if ($src === false) {
            return null;
        }
        $size = min(imagesx($src), imagesy($src), 128);
        $dst = imagecreatetruecolor($size, $size);
        imagealphablending($dst, false);
        imagesavealpha($dst, true);
        imagecopyresampled($dst, $src, 0, 0, 0, 0, $size, $size, min(imagesx($src), imagesy($src)), min(imagesx($src), imagesy($src)));
        ob_start();
        imagepng($dst);

        return (string) ob_get_clean();
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
