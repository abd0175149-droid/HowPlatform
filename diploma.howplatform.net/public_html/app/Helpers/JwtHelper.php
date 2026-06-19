<?php

namespace App\Helpers;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

class JwtHelper
{
    private static $secret;
    private static $algo = 'HS256';

    public static function init()
    {
        self::$secret = getenv('JWT_SECRET'); // نفس المفتاح من .env
    }

    public static function decodeToken(string $token)
    {
        self::init();
        try {
            return JWT::decode($token, new Key(self::$secret, self::$algo));
        } catch (\Exception $e) {
            log_message('error', 'JWT Decode error: ' . $e->getMessage());
            return null;
        }
    }
}
