<?php

namespace App\Controllers;

use CodeIgniter\Controller;

class DecryptProxy extends Controller
{
public function fetch(string $encodedTarget = null)
{
    try {
        $logFile = WRITEPATH . 'logs/decrypt_debug.txt';
        file_put_contents($logFile, "🚀 بدأ التنفيذ\n");

        // نفك المسار URL-decoded (لأن CodeIgniter لا يسمح بـ / مباشرة في (:any))
        $target = urldecode($encodedTarget);

        file_put_contents($logFile, "📦 المسار: {$target}\n", FILE_APPEND);

        if (! $target || str_contains($target, '..') || str_starts_with($target, 'http')) {
            file_put_contents($logFile, "❌ مسار مرفوض: {$target}\n", FILE_APPEND);
            return $this->fail('Invalid or missing path');
        }

        $apiUrl = site_url($target);
        file_put_contents($logFile, "🔗 الاتصال بـ: {$apiUrl}\n", FILE_APPEND);

        $cipher = $this->fetchEncrypted($apiUrl);
        file_put_contents($logFile, "🔑 Received Cipher: " . print_r($cipher, true) . "\n", FILE_APPEND);

        $crypto = service('crypto');
        $decrypted = $crypto->decrypt($cipher);

        file_put_contents($logFile, "✅ Decrypted Successfully\n", FILE_APPEND);

        return $this->response->setJSON($decrypted);

    } catch (\Throwable $e) {
        file_put_contents($logFile, "❌ Exception: " . $e->getMessage() . "\n", FILE_APPEND);
        return $this->response->setJSON([
            'error' => true,
            'message' => $e->getMessage()
        ])->setStatusCode(500);
    }
}




    private function fetchEncrypted(string $url): array
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false
        ]);

        $response = curl_exec($ch);
        curl_close($ch);

        $decoded = json_decode($response, true);

        if (! is_array($decoded) || ! isset($decoded['ciphertext'])) {
            throw new \RuntimeException('Response not encrypted or invalid.');
        }

        return $decoded;
    }

    private function fail(string $message)
    {
        return $this->response->setJSON([
            'error' => true,
            'message' => $message
        ])->setStatusCode(400);
    }
}
