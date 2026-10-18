<?php

declare(strict_types=1);

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use App\Libraries\FirebaseAdmin;

/** فحص مسار إرسال FCM v1. php spark fcm:test [token] (تشخيصي مؤقت) */
class FcmTest extends BaseCommand
{
    protected $group       = 'Diagnostics';
    protected $name        = 'fcm:test';
    protected $description = 'Send a test FCM push to a token (default: dummy) to verify the send path.';

    public function run(array $params)
    {
        $token = $params[0] ?? 'dummy_invalid_token_for_path_check';
        try {
            $fb  = new FirebaseAdmin();
            $res = $fb->fcmSendToTokens([$token], 'اختبار الدفع', 'رسالة اختبار FCM', ['link' => '#/service']);
            CLI::write('FCM result: success=' . $res['success'] . ' failure=' . $res['failure']
                . ' invalid=' . count($res['invalid']), $res['success'] > 0 ? 'green' : 'yellow');
            CLI::write('(فشل مع توكن وهمي = طبيعي؛ المهم ألا يكون خطأ مصادقة 401/403)');
        } catch (\Throwable $e) {
            CLI::error('FCM_TEST_FAILED: ' . $e->getMessage());
        }
    }
}
