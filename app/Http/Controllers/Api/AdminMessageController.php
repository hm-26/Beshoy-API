<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ContactMessageResource;
use App\Models\ContactMessage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/** Admin inbox for the messages visitors sent through the site. */
class AdminMessageController extends Controller
{
    public function index(): JsonResponse
    {
        $messages = ContactMessage::orderByDesc('id')->limit(500)->get();

        return response()->json([
            'data' => ContactMessageResource::collection($messages)->resolve(),
            'meta' => ['unread' => ContactMessage::whereNull('read_at')->count()],
        ]);
    }

    public function unreadCount(): JsonResponse
    {
        return response()->json(['data' => ['unread' => ContactMessage::whereNull('read_at')->count()]]);
    }

    /** PATCH { read: true|false } */
    public function update(Request $request, ContactMessage $message): ContactMessageResource
    {
        $data = $request->validate(['read' => ['required', 'boolean']]);
        $message->read_at = $data['read'] ? ($message->read_at ?? now()) : null;
        $message->save();

        return new ContactMessageResource($message);
    }

    public function destroy(ContactMessage $message): Response
    {
        $message->delete();

        return response()->noContent();
    }
}
