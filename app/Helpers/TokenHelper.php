<?php

namespace App\Helpers;

use App\Models\User;
use Illuminate\Support\Facades\Redis;
use Tymon\JWTAuth\Facades\JWTAuth;

class TokenHelper
{
  public static function GenerateJwtToken(User $user, int $expiredTimestamp, array $customClaims = []): string
  {
    $claims = array_merge([
      'exp' => $expiredTimestamp,
    ], $customClaims);

    return JWTAuth::customClaims($claims)->fromUser($user);
  }
  public static function StoreJwtTokenInRedis(User $user, string $token, int $ttlSeconds, string $keyPrefix): void
  {
    $key = "{$keyPrefix}:{$user->id}";
    Redis::setex($key, $ttlSeconds, $token);
  }
}
