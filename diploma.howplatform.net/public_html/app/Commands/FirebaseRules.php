<?php

declare(strict_types=1);

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use App\Libraries\FirebaseAdmin;

/** نشر قواعد أمان Firestore لإشارات المحادثات. php spark firebase:rules */
class FirebaseRules extends BaseCommand
{
    protected $group       = 'Diagnostics';
    protected $name        = 'firebase:rules';
    protected $description = 'Publish Firestore security rules for conversation signal docs.';

    public function run(array $params)
    {
        $rules = <<<'RULES'
rules_version = '2';
service cloud.firestore {
  match /databases/{database}/documents {
    // إشارات المحادثات: لا تحوي محتوى حسّاساً (مؤشرات فقط)
    match /conversations/{reqId} {
      allow read: if request.auth != null && (
        request.auth.token.role == 'admin' ||
        request.auth.uid == resource.data.studentUid ||
        request.auth.uid == resource.data.assignedAdminUid
      );
      allow write: if false; // الكتابة عبر Admin SDK فقط (يتجاوز القواعد)
    }
    match /{document=**} {
      allow read, write: if false;
    }
  }
}
RULES;

        try {
            $fb   = new FirebaseAdmin();
            $name = $fb->publishFirestoreRules($rules);
            CLI::write('FIRESTORE_RULES_PUBLISHED ✓', 'green');
            CLI::write('ruleset = ' . $name, 'yellow');
        } catch (\Throwable $e) {
            CLI::error('PUBLISH_FAILED: ' . $e->getMessage());
        }
    }
}
