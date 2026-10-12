<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ContactMessageRequest;
use App\Models\ContactMessage;
use Illuminate\Http\JsonResponse;

/** Public: visitors send a message through the website. The admin reads it in the panel. */
class ContactController extends Controller
{
    /** Stop the table from being flooded even if somebody gets past the per-IP throttle. */
    private const MAX_UNREAD = 500;

    public function store(ContactMessageRequest $request): JsonResponse
    {
        // A filled honeypot means a bot: answer "ok" so it learns nothing, store nothing.
        if (filled($request->input('hp'))) {
            return response()->json(['data' => ['sent' => true]], 201);
        }

        if (ContactMessage::whereNull('read_at')->count() >= self::MAX_UNREAD) {
            abort(503, 'The inbox is full right now. Please try another contact method.');
        }

        ContactMessage::create([
            ...$request->safe()->only(['name', 'email', 'phone', 'subject', 'message']),
            'ip_hash' => hash_hmac('sha256', (string) $request->ip(), (string) config('app.key')),
        ]);

        return response()->json(['data' => ['sent' => true]], 201);
    }
}
