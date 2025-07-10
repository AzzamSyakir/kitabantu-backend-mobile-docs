<?php
namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Tymon\JWTAuth\Facades\JWTAuth;
use Tymon\JWTAuth\Exceptions\JWTException;
use App\Helpers\ApiResponseHelper;
use Symfony\Component\HttpFoundation\Response;

class CheckAccessToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $authHeader = $request->header('Authorization');
        if (!$authHeader || !str_starts_with($authHeader, 'Bearer ')) {
            return ApiResponseHelper::respond(null, 'Access token not found', 401);
        }

        $token = str_replace('Bearer ', '', $authHeader);

        try {
            $user = JWTAuth::setToken($token)->authenticate();
            if (!$user) {
                return ApiResponseHelper::respond(null, "Access token is invalid or expired", 401);
            }
        } catch (JWTException $e) {
            return ApiResponseHelper::respond(null, 'Access token is invalid or expired', 401);
        }

        return $next($request);
    }
}