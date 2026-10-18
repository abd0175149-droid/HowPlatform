<?php

declare(strict_types=1);

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use App\Libraries\FirebaseAdmin;

/**
 * تشخيص إعداد Firebase: custom token + access token + كتابة/حذف Firestore.
 * تشغيل: php spark firebase:test
 * (أمر تشخيصي مؤقت — يُحذف بعد التأكد.)
 */
class FirebaseTest extends BaseCommand
{
    protected $group       = 'Diagnostics';
    protected $name        = 'firebase:test';
    protected $description = 'Verify Firebase Admin credentials (custom token, access token, Firestore write).';

    public function run(array $params)
    {
        try {
            $fb = new FirebaseAdmin();
            CLI::write('project_id = ' . $fb->projectId(), 'yellow');

            // 1) custom token
            $ct = $fb->createCustomToken('diag_uid_1', ['role' => 'student']);
            CLI::write('CUSTOM_TOKEN_OK len=' . strlen($ct), 'green');

            // 2) access token (network → Google)
            $at = $fb->getAccessToken();
            CLI::write('ACCESS_TOKEN_OK len=' . strlen($at), 'green');

            // 3) Firestore write + delete (يثبت تفعيل Firestore + صلاحية الكتابة)
            $id = $fb->firestoreCreate('_diagnostics', [
                'ok'        => true,
                'note'      => 'firebase:test',
                'createdAt' => FirebaseAdmin::nowTimestamp(),
            ]);
            CLI::write('FIRESTORE_WRITE_OK id=' . $id, 'green');

            $fb->firestoreDelete('_diagnostics/' . $id);
            CLI::write('FIRESTORE_DELETE_OK', 'green');

            CLI::write('ALL_FIREBASE_CHECKS_PASSED', 'green');
        } catch (\Throwable $e) {
            CLI::error('FIREBASE_TEST_FAILED: ' . $e->getMessage());
        }
    }
}
