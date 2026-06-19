<?php

namespace App\Filters;

use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\Filters\FilterInterface;

class DecryptAndProxy implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        $path = $request->uri->getPath();

        // استبدال "ui/" بـ "api/" (أو "task/" أو غيره حسب الحاجة)
        $target = preg_replace('#^ui/#', 'api/', $path);

        $apiUrl = site_url($target);
        $encrypted = @file_get_contents($apiUrl);

        if (! $encrypted) {
            return service('response')->setJSON([
                'error' => true,
                'message' => '🔴 لا يمكن تحميل الاستجابة المشفّرة من ' . $apiUrl,
            ])->setStatusCode(500);
        }

        $cipher = json_decode($encrypted, true);

        try {
            $crypto = service('crypto');
            $decrypted = $crypto->decrypt($cipher);

            return service('response')->setJSON($decrypted);
        } catch (\Throwable $e) {
            return service('response')->setJSON([
                'error' => true,
                'message' => '❌ فشل في فك التشفير: ' . $e->getMessage(),
            ])->setStatusCode(500);
        }
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        // لا شيء هنا
    }
}
