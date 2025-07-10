<?php

namespace App\Http\Controllers;

use App\Http\Requests\ForgotPasswordRequest;
use App\Http\Requests\SignUpRequest;
use App\Models\Profile;
use App\Models\User;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Hash;
use Illuminate\Http\JsonResponse;
use App\Jobs\SendVerificationLinkJob;
use Illuminate\Http\Request;
use App\Helpers\ApiResponseHelper;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use App\Http\Requests\SignInRequest;
use Tymon\JWTAuth\Facades\JWTAuth;
use Laravel\Socialite\Facades\Socialite;
use App\Helpers\TokenHelper;
use Illuminate\Support\Arr;
class AuthController
{
    public function SignUp(SignUpRequest $request): JsonResponse
    {
        $data = $request->validated();
        DB::beginTransaction();

        try {
            $regionController = new RegionController();
            $domicileData = $regionController->getDomicileByVillageId($data['villageId']);

            if ($domicileData->getStatusCode() !== 200) {
                return ApiResponseHelper::respond(
                    null,
                    "Failed to resolve domicile from village ID",
                    400
                );
            }

            $domicileString = Arr::get($domicileData->getData(true), 'data.full_address', '');
            $userId = Str::uuid();
            $profileId = Str::uuid();
            $filename = null;
            $path = null;

            if ($request->hasFile('profile_photo') && $request->file('profile_photo')->isValid()) {
                $extension = $request->file('profile_photo')->getClientOriginalExtension();
                $filename = $userId . '_profile_photo.' . $extension;
                $path = $request->file('profile_photo')->storeAs('profile_photos', $filename, 'public');
            }
            $pictureUrl = asset('storage/' . $path);

            $user = User::create([
                'id' => $userId,
                'email' => $data['email'],
                'password' => Hash::make($data['password']),
            ]);

            $profile = Profile::create([
                'id' => $profileId,
                'user_id' => $userId,
                'full_name' => $data['full_name'],
                'phone_number' => $data['phone_number'],
                'nick_name' => $data['nick_name'],
                'gender' => $data['gender'],
                'domicile' => $domicileString,
                'preferred_service' => $data['preferred_service'],
                'picture_url' => $pictureUrl,
            ]);

            SendVerificationLinkJob::dispatch($user);
            DB::commit();

            return ApiResponseHelper::respond(
                [
                    'user' => $user,
                    'profile' => $profile
                ],
                'SignUp succeeded.',
                201
            );
        } catch (\Throwable $e) {
            DB::rollBack();

            if (isset($path) && Storage::disk('public')->exists($path)) {
                Storage::disk('public')->delete($path);
            }

            return ApiResponseHelper::respond(
                null,
                'SignUp Failed: ' . $e->getMessage(),
                500
            );
        }
    }

    public function SignIn(SignInRequest $request)
    {
        $credentials = $request->validated();

        if (!$accessToken = JWTAuth::attempt($credentials)) {
            return ApiResponseHelper::respond(
                null,
                'Unauthorized',
                401
            );
        }

        try {
            Redis::ping();
        } catch (\Exception $e) {
            return ApiResponseHelper::respond(
                null,
                'Connection to Redis not failed',
                500
            );
        }
        $user = auth()->user();
        $accessTokenTTL = 5;
        $refreshTokenTTL = 60 * 24;

        $accessTokenExp = now()->addMinutes($accessTokenTTL)->timestamp;
        $refreshTokenExp = now()->addMinutes($refreshTokenTTL)->timestamp;

        $accessToken = TokenHelper::generateJwtToken($user, $accessTokenExp);
        $refreshToken = TokenHelper::generateJwtToken($user, $refreshTokenExp);
        $ttlRefreshTokenSeconds = $refreshTokenExp * 60;
        TokenHelper::storeJwtTokenInRedis($user, $refreshToken, $ttlRefreshTokenSeconds, "refresh_token");

        $data = [
            'access_token' => $accessToken,
            'refresh_token' => $refreshToken,
            'token_type' => 'bearer',
            'expires_in' => auth('api')->factory()->getTTL() * 60,
        ];
        return ApiResponseHelper::respond(
            $data,
            'SignIn succeeded',
            200
        );
    }
    public function GenerateRefreshToken(Request $request)
    {
        $refreshToken = $request->bearerToken();

        try {
            $parts = explode('.', $refreshToken);
            $payload = json_decode(base64_decode(strtr($parts[1], '-_', '+/')), true);
            $userId = $payload['sub'] ?? null;
        } catch (\Exception $e) {
            return ApiResponseHelper::respond(null, 'Invalid token structure', 400);
        }

        if (!$userId) {
            return ApiResponseHelper::respond(null, 'Invalid user ID in token', 400);
        }

        $user = User::where('id', $userId)->first();
        if (!$user) {
            return ApiResponseHelper::respond(null, 'User not found', 404);
        }

        $key = "refresh_token:{$user->id}";
        $storedRefreshToken = Redis::get($key);

        if (!$storedRefreshToken || $storedRefreshToken !== $refreshToken) {
            return ApiResponseHelper::respond(null, 'Refresh token invalid or expired', 401);
        }

        $accessTokenTTL = 5;
        $accessTokenExp = now()->addMinutes($accessTokenTTL)->timestamp;
        $accessToken = TokenHelper::generateJwtToken($user, $accessTokenExp);

        $refreshTokenTTL = 60 * 24;
        $refreshTokenExp = now()->addMinutes($refreshTokenTTL)->timestamp;
        $newRefreshToken = TokenHelper::generateJwtToken($user, $refreshTokenExp);
        $ttlInSeconds = $refreshTokenTTL * 60;

        TokenHelper::storeJwtTokenInRedis($user, $newRefreshToken, $ttlInSeconds, 'refresh_token');

        return ApiResponseHelper::respond([
            'access_token' => $accessToken,
            'refresh_token' => $newRefreshToken,
            'token_type' => 'bearer',
            'expires_in' => $accessTokenTTL * 60,
        ], 'Token refreshed successfully', 200);
    }
    public function GenerateAccessToken(Request $request): JsonResponse
    {
        $refreshToken = $request->bearerToken();

        try {
            $parts = explode('.', $refreshToken);
            $payload = json_decode(base64_decode(strtr($parts[1], '-_', '+/')), true);
            $userId = $payload['sub'] ?? null;
        } catch (\Exception $e) {
            return ApiResponseHelper::respond(null, 'Invalid token structure', 400);
        }

        if (!$userId) {
            return ApiResponseHelper::respond(null, 'Invalid user ID in token', 400);
        }
        $user = User::where('id', $userId)->first();

        $accessTokenTTL = 5;
        $accessTokenExp = now()->addMinutes($accessTokenTTL)->timestamp;

        $accessToken = TokenHelper::generateJwtToken($user, $accessTokenExp);

        return ApiResponseHelper::respond([
            'access_token' => $accessToken,
            'token_type' => 'bearer',
            'expires_in' => $accessTokenTTL * 60,
        ], 'Access token generated successfully', 200);
    }
    public function ForgotPassword(ForgotPasswordRequest $request)
    {
        $request->validated();
        DB::beginTransaction();
        $user = User::where('email', $request->email)->first();

        if (!$user) {
            DB::rollBack();
            return ApiResponseHelper::respond(null, "User not found", 404);
        }

        if (!Hash::check($request->old_password, $user->password)) {
            DB::rollBack();
            return ApiResponseHelper::respond(null, 'Old password is incorrect', 401);
        }
        $user->password = Hash::make($request->new_password);
        $user->save();
        DB::commit();
        return ApiResponseHelper::respond(null, 'Password updated successfully', 200);
    }
    public function SignOut(Request $request)
    {
        $authHeader = $request->header('Authorization');

        $token = str_replace('Bearer ', '', $authHeader);

        DB::beginTransaction();
        try {
            $user = JWTAuth::setToken($token)->authenticate();

            if (!$user) {
                DB::rollBack();
                return ApiResponseHelper::respond(null, 'User not found', 404);
            }

            $refreshTokenKey = 'refresh_token:' . $user->id;
            Redis::del($refreshTokenKey);
            JWTAuth::invalidate($token);

            DB::commit();

            return ApiResponseHelper::respond(null, "SignOut successful. Token invalidated and refresh token removed", 200);

        } catch (\Tymon\JWTAuth\Exceptions\TokenInvalidException $e) {
            DB::rollBack();
            return ApiResponseHelper::respond(null, 'Invalid token', 400);

        } catch (\Exception $e) {
            DB::rollBack();
            return ApiResponseHelper::respond(null, "Could not log out: {$e->getMessage()}", 500);
        }
    }
    public function CheckEmail(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
        ]);

        $isTaken = User::where('email', $request->email)->exists();

        return ApiResponseHelper::respond(
            ['is_taken' => $isTaken],
            'Email availability checked.',
            200
        );
    }
    // auth with google
    public function RedirectGoogle()
    {
        return Socialite::driver('google')->redirect();
    }
    public function CallbackGoogle()
    {
        $googleUser = Socialite::driver('google')->stateless()->user();
        $existingUser = User::where('email', $googleUser->getEmail())->first();

        if ($existingUser) {
            return $this->signInWithGoogle($existingUser);
        }

        return $this->SignUpWithGoogle($googleUser);
    }
    protected function SignUpWithGoogle($googleUser)
    {
        if (is_null($googleUser)) {
            return ApiResponseHelper::respond(
                null,
                'Google SignUp Error: Invalid Google user data.',
                400
            );
        }

        DB::beginTransaction();

        try {
            $newUser = User::create([
                'id' => Str::uuid(),
                'email' => $googleUser->getEmail(),
                'full_name' => $googleUser->getName(),
                'picture_url' => $googleUser->getAvatar(),
                'auth_method' => 'google',
            ]);

            $tokenTtl = 2;
            $tokenExp = now()->addMinutes($tokenTtl)->timestamp;
            $temporaryToken = TokenHelper::generateJwtToken($newUser, $tokenExp);

            DB::commit();

            return ApiResponseHelper::respond(
                ['temporary_access_token' => $temporaryToken],
                'Google SignUp succeeded.',
                201
            );
        } catch (\Throwable $e) {
            DB::rollBack();

            return ApiResponseHelper::respond(
                null,
                'Google SignUp Failed: ' . $e->getMessage(),
                500
            );
        }
    }
    protected function SignInWithGoogle(User $user)
    {
        try {
            Redis::ping();
        } catch (\Exception $e) {
            return ApiResponseHelper::respond(
                null,
                'Connection to Redis failed',
                500
            );
        }

        $accessToken = JWTAuth::customClaims([
            'exp' => now()->addMinutes(5)->timestamp,
        ])->fromUser($user);

        $accessTokenTTLMinutes = 5;
        $refreshTokenTTLMinutes = 60 * 24;

        $accessTokenExp = now()->addMinutes($accessTokenTTLMinutes)->timestamp;
        $refreshTokenExp = now()->addMinutes($refreshTokenTTLMinutes)->timestamp;

        $accessToken = TokenHelper::generateJwtToken($user, $accessTokenExp);
        $refreshToken = TokenHelper::generateJwtToken($user, $refreshTokenExp);

        $ttlRefreshTokenSeconds = $refreshTokenTTLMinutes * 60;
        TokenHelper::storeJwtTokenInRedis($user, $refreshToken, $ttlRefreshTokenSeconds, "refresh_token");


        $data = [
            'access_token' => $accessToken,
            'refresh_token' => $refreshToken,
            'token_type' => 'bearer',
            'expires_in' => 5 * 60,
        ];

        return ApiResponseHelper::respond(
            $data,
            'Google SignIn succeeded.',
            200
        );
    }
    public function CompleteProfile(Request $request): JsonResponse
    {
        $data = $request->validate([
            'preferred_service' => 'string|max:255',
        ]);

        DB::beginTransaction();

        try {
            $user = $request->user();

            $user->update([
                'preferred_service' => $data['preferred_service'] ?? null,
            ]);

            DB::commit();

            return ApiResponseHelper::respond(
                ['user' => $user],
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