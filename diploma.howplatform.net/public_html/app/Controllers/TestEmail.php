<?php

namespace App\Controllers;

use CodeIgniter\Controller;

class TestEmail extends Controller
{
    public function index()
    {
        // 1) استدعاء خدمة الإيميل
        $email = \Config\Services::email();

        // 2) إعداد الرسالة
        $email->setTo('aboodafaneh13@gmail.com');          // ← غيّر إلى بريدك
        $email->setSubject('اختبار SMTP من CodeIgniter');
        $email->setMessage('<h3 style="color:#0a7">🎉 تم الإرسال بنجاح!</h3><p>إذا وصلتك هذه الرسالة فالإعداد صحيح.</p>');
        $email->setAltMessage('Test message (plain)'); // نصيّة لعملاء لا يدعمون HTML

        // 3) الإرسال
        if ($email->send()) {
            return '✔️ تم الإرسال — افحص صندوق الوارد.';
        }

        // 4) في حال الفشل اطبع معلومات التصحيح
        echo '<pre>';
        print_r($email->printDebugger(['headers', 'subject', 'body']));
        echo '</pre>';

        return '❌ فشل الإرسال — راجع معلومات التصحيح أعلاه.';
    }
}
