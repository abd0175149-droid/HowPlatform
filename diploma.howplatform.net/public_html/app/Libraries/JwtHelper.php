<?php

declare(strict_types=1);

namespace App\Libraries;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

/**
 * Helper لفك/التحقق من JWT القادم من ساب-دومين التوثيق.
 * يدعم HS256 مع سرّ بصيغة نصّ عادي أو base64:... وكذلك RS256 عبر مفتاح عام.
 */
class JwtHelper
{
    public static function decode(?string $token): ?object
    {
        if (!$token) {
            return null;
        }

        $algo = getenv('JWT_ALGO') ?: 'HS256';
        $iss  = getenv('JWT_ISS') ?: null;
        $aud  = getenv('JWT_AUD') ?: null;

        try {
            if (strtoupper($algo) === 'RS256') {
                $publicKey = self::getPublicKey();
                if (!$publicKey) {
                    throw new \RuntimeException('Missing JWT public key');
                }
                $decoded = JWT::decode($token, new Key($publicKey, 'RS256'));
            } else {
                // HS256 (افتراضي)
                $secret = self::resolveHsSecret();
                if ($secret === null) {
                    throw new \RuntimeException('Missing JWT secret');
                }
                $decoded = JWT::decode($token, new Key($secret, 'HS256'));
            }
        } catch (\Throwable $e) {
            log_message('warning', 'JWT decode failed: ' . $e->getMessage());
            return null;
        }

        // تحقق اختياري من iss/aud
        if ($iss !== null && property_exists($decoded, 'iss') && $decoded->iss !== $iss) {
            return null;
        }
        if ($aud !== null && property_exists($decoded, 'aud') && $decoded->aud !== $aud) {
            return null;
        }

        return $decoded;
    }

    /**
     * يقرأ التوكن من السيشن ثم من الكوكي ثم من Authorization: Bearer
     */
    public static function readFromCookieOrSession(): ?object
    {
        $session = session();

        // 1) Session
        $tok = $session->get('howplatform_token');
        if (is_string($tok) && $tok !== '') {
            $decoded = self::decode($tok);
            if ($decoded) {
                return $decoded;
            }
        }

        // 2) Cookie
        $cookieName = getenv('JWT_COOKIE_NAME') ?: 'howplatform_token';
        $tok = $_COOKIE[$cookieName] ?? null;
        if (is_string($tok) && $tok !== '') {
            $decoded = self::decode($tok);
            if ($decoded) {
                $session->set('howplatform_token', $tok);
                return $decoded;
            }
        }

        // 3) Authorization: Bearer
        $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['Authorization'] ?? '';
        if (is_string($authHeader) && stripos($authHeader, 'Bearer ') === 0) {
            $tok = trim(substr($authHeader, 7));
            if ($tok !== '') {
                $decoded = self::decode($tok);
                if ($decoded) {
                    $session->set('howplatform_token', $tok);
                    return $decoded;
                }
            }
        }

        return null;
    }

    /** يدعم سرّ HS256 بصيغة نصّ عادي أو base64:.... */
    private static function resolveHsSecret(): ?string
    {
        // نقرأ من JWT_SECRET (المعتاد) أو متغيّر بديل إن رغبت لاحقًا
        $raw = getenv('JWT_SECRET') ?: '';
        $raw = trim($raw);

        if ($raw === '') {
            return null;
        }

        if (stripos($raw, 'base64:') === 0) {
            $b64 = substr($raw, 7);
            $bin = base64_decode($b64, true);
            return $bin === false ? null : $bin;
        }

        return $raw;
    }

    private static function getPublicKey(): ?string
    {
        $key = getenv('JWT_PUBLIC_KEY') ?: '';
        $key = trim($key);
        return $key !== '' ? $key : null;
    }

    /** يُرجع عنوان API للتوثيق من AUTH_API_URL أو الاسم القديم auth.apiUrl */
    public static function getAuthApiUrl(): ?string
    {
        $url = getenv('AUTH_API_URL') ?: (getenv('auth.apiUrl') ?: '');
        $url = trim($url);
        return $url !== '' ? $url : null;
    }
    
    
    public static function currentUserId(): ?int
    {
        $session = session();

        // ⚡️ أولاً: سيشن سريع
        $sid = $session->get('user_id');
        if (is_numeric($sid)) {
            return (int) $sid;
        }

        // 🧩 ثانياً: من التوكن (سيشن/كوكي/Authorization) عبر الدالة الجاهزة لديك
        $decoded = self::readFromCookieOrSession();
        if ($decoded) {
            // بعض الإصدارات تضعه في sub، وأخرى في id — ندعم الحالتين
            if (isset($decoded->sub) && is_numeric($decoded->sub)) {
                return (int) $decoded->sub;
            }
            if (isset($decoded->id) && is_numeric($decoded->id)) {
                return (int) $decoded->id;
            }
            if (isset($decoded->uid) && is_numeric($decoded->uid)) {
                return (int) $decoded->uid;
            }
        }

        // ♻️ ثالثاً: auth_user داخل السيشن (fallback آمن)
        $auth = $session->get('auth_user');
        if (is_array($auth) && isset($auth['id']) && is_numeric($auth['id'])) {
            return (int) $auth['id'];
        }

        return null;
    }
}
