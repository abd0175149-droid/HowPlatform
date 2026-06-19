<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Controllers\BaseController;
use App\Models\PaymentMethodModel;

class PaymentsMeta extends BaseController
{
    public function methods()
    {
        $methods = (new PaymentMethodModel())->orderBy('name', 'ASC')->findAll();
        return $this->response->setJSON(['data' => $methods]);
    }
}
