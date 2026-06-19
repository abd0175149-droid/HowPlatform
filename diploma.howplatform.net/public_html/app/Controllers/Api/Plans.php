<?php
declare(strict_types=1);

namespace App\Controllers\Api;

use App\Models\PlanModel;
use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\RESTful\ResourceController;

class Plans extends ResourceController
{
    protected $format = 'json';

    public function index(): ResponseInterface
    {
        try {
            $model = new PlanModel();
            $plans = $model->getActivePlansWithFeatures();

            return $this->respond([
                'status' => 'ok',
                'data'   => $plans,
            ]);
        } catch (\Throwable $e) {
            log_message('error', 'Plans::index error: {msg}', ['msg' => $e->getMessage()]);
            return $this->respond([
                'status'  => 'error',
                'message' => 'Failed to load plans.',
            ], 500);
        }
    }
}
