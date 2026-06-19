<?php

declare(strict_types=1);

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

class FeatureAccessFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        // اسم الميزة يُمرّر من الراوتر: feature:upgrade_page
        $feature = $arguments[0] ?? null;
        if (!$feature) {
            return; // لا نمنع إن لم يُمرر اسم
        }

        // ⚠️ عدّل طريقة جلب الجلسة حسب مشروعك
        $uid   = (int) (session('user_id') ?? 0);
        $email = (string) (session('email')   ?? '');

        $gate = service('featureGate');
        if (!$gate->isEnabled($feature, $uid, $email)) {
            return redirect()->to('/')->with('error', 'هذه الميزة غير متاحة حالياً.');
        }
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        // لا شيء
    }
}
