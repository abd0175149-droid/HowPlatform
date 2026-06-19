<?php
// app/Controllers/Api/SubscriptionController.php
declare(strict_types=1);

namespace App\Controllers\Api;

use App\Controllers\BaseController;
use App\Services\SubscriptionService;
use CodeIgniter\API\ResponseTrait;
use Config\Database;

final class SubscriptionController extends BaseController
{
    use ResponseTrait;

    protected function userId(): ?int
    {
        $uid = session('user_id');
        if (!$uid && function_exists('auth') && auth()?->user()) {
            $uid = auth()->user()->id ?? null;
        }
        return $uid ? (int)$uid : null;
    }

    public function plan()
    {
        $uid = $this->userId();
        if (!$uid) {
            return $this->failUnauthorized('Not signed in.');
        }

        $svc  = new SubscriptionService(Database::connect());
        $plan = $svc->getActivePlanForUser($uid);

        if (!$plan) {
            return $this->respond([
                'ok'     => true,
                'active' => false,
                'level'  => 0,
                'plan'   => null,
            ]);
        }

        return $this->respond([
            'ok'     => true,
            'active' => true,
            'level'  => (int)$plan['level'],
            'plan'   => $plan,
        ]);
    }
}
