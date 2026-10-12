<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ProfileRequest;
use App\Http\Resources\ProfileResource;
use App\Models\Profile;

class ProfileController extends Controller
{
    public function show(): ProfileResource
    {
        return new ProfileResource(Profile::current());
    }

    public function update(ProfileRequest $request): ProfileResource
    {
        $profile = Profile::current();
        $profile->update($request->validated());

        return new ProfileResource($profile->fresh());
    }
}
