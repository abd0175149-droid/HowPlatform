<?php
declare(strict_types=1);

namespace App\Controllers;

use CodeIgniter\Controller;

class VoucherLanding extends Controller
{
    public function verify()
    {
        $request = $this->request;
        $session = session();

        // 🧹 طبيع الكود: إزالة مسافات وشرطات وتحويل لحروف كبيرة
        $raw  = (string) $request->getGet('code');
        $code = strtoupper(preg_replace('/[\s\-_]+/', '', trim($raw)));

        if ($code === '') {
            // إن لم يوجد كود نوجّه للترقية بشكل عادي
            return redirect()->to('/students#upgrade');
        }

        // هل المستخدم مسجّل دخول على هذا الساب دومين؟
        $loggedIn = $session->has('user_id') || $session->has('auth_user');

        if (!$loggedIn) {
            // أرسل المستخدم لتسجيل الدخول ثم عُد لنفس الرابط (return)
            $currentUrl = current_url() . '?code=' . urlencode($code);
            $loginUrl   = site_url('auth/login') . '?return=' . urlencode($currentUrl);
            return redirect()->to($loginUrl);
        }

        // ✅ مسجّل دخول: وجّهه مباشرة إلى واجهة الطالب مع تمرير الكود والخيارات عبر الـhash
        // نستخدم hash لأن الـstudent_router يعتمد الأقسام من الـhash ويحمّل الصفحة ديناميكيًا
        $hash = '#upgrade?code=' . urlencode($code) . '&autoverify=1&open=activate';
        return redirect()->to('/students' . $hash);
    }
}
