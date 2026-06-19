<?php

declare(strict_types=1);

namespace App\Controllers\Ui;

use App\Controllers\BaseController;
use App\Models\UserModel;
use Config\Upgrade as UpgradeConfig;

class Fragments extends BaseController
{
    /**
     * يُعيد HTML المودال فقط. نتحكم بمن يراها أثناء التطوير.
     */
    public function upgradeModal()
    {
        $userId = (int) session('user_id');
        if (! $userId) {
            return $this->response->setStatusCode(401)->setBody('');
        }

        // حارس وضع التطوير: يسمح فقط للمستخدمين المحددين في Config\Upgrade
        $cfg = config(UpgradeConfig::class);
        if ($cfg && $cfg->devOnly) {
            $user  = model(UserModel::class)->find($userId);
            $email = $user['email'] ?? null;

            $allowed = false;
            foreach ($cfg->allowedUsers as $needle) {
                if ((is_numeric($needle) && (int) $needle === $userId) || (is_string($needle) && $needle === $email)) {
                    $allowed = true;
                    break;
                }
            }
            if (! $allowed) {
                return $this->response->setStatusCode(403)->setBody('');
            }
        }

        // إعادة الـ View كـ HTML خام
        return $this->response->setContentType('text/html')->setBody(
            view('student/partials/upgrade_modal', ['meta' => []])
        );
    }
}
