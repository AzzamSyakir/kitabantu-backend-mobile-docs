<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Helpers\ApiResponseHelper;
use Illuminate\Support\Facades\Redis;

class CheckRefreshToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $authHeader = $request->header('Authorization');

        if (!$authHeader || !str_starts_with($authHeader, 'Bearer ')) {
            return ApiResponseHelper::respond(null, 'Refresh token not found', 401);
        }

        $token = substr($authHeader, 7);

        try {
            $parts = explode('.', $token);
            if (count($parts) !== 3) {
                return ApiResponseHelper::respond(null, 'Refresh token is invalid or expired', 401);
            }

            $payload = json_decode(base64_decode(strtr($parts[1], '-_', '+/')), true);
            $userId = $payload['sub'] ?? null;
        } catch (\Exception $e) {
            return ApiResponseHelper::respond(null, 'Refresh token is invalid or expired', 401);
        }

        if (!$userId) {
            return ApiResponseHelper::respond(null, 'Invalid refresh token payload', 401);
        }

        $refreshTokenKey = "refresh_token:{$userId}";
        $storedToken = Redis::get($refreshTokenKey);

        if (!$storedToken || $storedToken !== $token) {
            return ApiResponseHelper::respond(null, 'Refresh token is invalid or expired', 401);
        }

        return $next($request);
    }
}
