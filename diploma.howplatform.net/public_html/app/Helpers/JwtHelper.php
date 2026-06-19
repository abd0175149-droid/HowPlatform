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
            $decoded = JWT::decode($token, new Key(self::$secret, self::$algo));

            // تحقّق اختياري من iss/aud عند ضبطهما في البيئة (يرفض فقط عند وجود مطالبة غير مطابقة)
            $iss = getenv('JWT_ISS');
            $aud = getenv('JWT_AUD');
            if ($iss && isset($decoded->iss) && $decoded->iss !== $iss) {
                return null;
            }
            if ($aud && isset($decoded->aud) && $decoded->aud !== $aud) {
                return null;
            }

            return $decoded;
        } catch (\Exception $e) {
            log_message('error', 'JWT Decode error: ' . $e->getMessage());
            return null;
        }
    }
}
