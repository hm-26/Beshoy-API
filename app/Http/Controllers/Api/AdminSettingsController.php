<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\AdminSessions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminSettingsController extends Controller
{
    /** GET /admin/settings */
    public function show(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->payload($request->user())]);
    }

    /** PUT /admin/settings  { maxDevices: 1..5 } */
    public function update(Request $request): JsonResponse
    {
        $data = $request->validate([
            'maxDevices' => ['required', 'integer', 'between:'.AdminSessions::MIN_DEVICES.','.AdminSessions::MAX_DEVICES],
        ]);

        $user = $request->user();
        $user->forceFill(['max_devices' => $data['maxDevices']])->save();

        return response()->json(['data' => $this->payload($user)]);
    }

    private function payload(User $user): array
    {
        return [
            'maxDevices' => AdminSessions::limit($user),
            'activeDevices' => AdminSessions::active($user)->count(),
            'maxAllowed' => AdminSessions::MAX_DEVICES,
        ];
    }
}
