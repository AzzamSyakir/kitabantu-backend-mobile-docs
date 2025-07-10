<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateProfileRequest;
use App\Helpers\ApiResponseHelper;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;

class ProfileController
{
    
 public function UpdateProfile(UpdateProfileRequest $request): JsonResponse
{
    DB::beginTransaction();

    try {
        $user = $request->user();
        $profile = $user->profile;

        $user->update([
            'email' => $request->input('email', $user->email),
            'password' => $request->filled('password') ? Hash::make($request->input('password')) : $user->password,
        ]);

        $profileData = $request->only([
            'full_name',
            'phone_number',
            'nick_name',
            'gender',
            'domicile',
            'preferred_service',
        ]);

        if ($request->hasFile('picture_url') && $request->file('picture_url')->isValid()) {
            $extension = $request->file('picture_url')->getClientOriginalExtension();
            $filename = $user->id . '_profile_photo.' . $extension;
            $path = $request->file('picture_url')->storeAs('profile_photos', $filename, 'public');
            $profileData['picture_url'] = asset('storage/' . $path);
        }

        $profile->update($profileData);

        DB::commit();

        return ApiResponseHelper::respond(
            [
                'user' => $user->fresh('profile')
            ],
            'Profile update succeeded.',
            200
        );
    } catch (\Throwable $e) {
        DB::rollBack();

        return ApiResponseHelper::respond(
            null,
            'Profile update failed: ' . $e->getMessage(),
            500
        );
    }
}

}
