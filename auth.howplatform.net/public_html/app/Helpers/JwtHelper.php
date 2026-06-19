<?php

namespace App\Helpers;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

class JwtHelper
{
    /**
     * Read a JWT setting from config/jwt.php first (survives config:cache),
     * then fall back to the raw environment ($_ENV / $_SERVER / getenv).
     */
    private static function cfg(string $key, string $envKey, $default)
    {
        if (function_exists('config')) {
            $v = config("jwt.$key");
            if ($v !== null && $v !== '') {
                return $v;
            }
        }
        $v = $_ENV[$envKey] ?? $_SERVER[$envKey] ?? getenv($envKey);
        return ($v === false || $v === null || $v === '') ? $default : $v;
    }

    private static function secret(): string
    {
        $s = (string) self::cfg('secret', 'JWT_SECRET', '');
        if ($s === '') {
            throw new \RuntimeException('Missing JWT_SECRET');
        }
        return $s;
    }

    private static function algo(): string
    {
        return (string) self::cfg('algo', 'JWT_ALGO', 'HS256');
    }

    public static function generateToken($user, array $extraClaims = []): string
    {
        $iat = time();
        $ttl = (int) self::cfg('ttl', 'JWT_TTL_SECONDS', 2592000);

        $payload = array_merge([
            'iss'      => (string) self::cfg('iss', 'JWT_ISS', 'auth.howplatform.net'),
            'aud'      => (string) self::cfg('aud', 'JWT_AUD', 'howplatform.net'),
            'sub'      => (int)   ($user->id        ?? 0),
            'name'     => (string)($user->name      ?? ''),
            'email'    => (string)($user->email     ?? ''),
            'is_sales' => (int)   ($user->is_sales  ?? 0),
            'is_admin' => (int)   ($user->is_admin  ?? 0),
            'avatar'   => (string)($user->avatar    ?? ''),
            'gid'      => (string)($user->google_id ?? ''),
            'iat'      => $iat,
            'nbf'      => $iat,
            'exp'      => $iat + $ttl,
        ], $extraClaims);

        return JWT::encode($payload, self::secret(), self::algo());
    }

    public static function verifyToken(string $token)
    {
        return JWT::decode($token, new Key(self::secret(), self::algo()));
    }
}
