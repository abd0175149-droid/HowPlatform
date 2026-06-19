<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Controllers\BaseController;
use App\Models\UserModel;
use App\Models\AiUsageLogModel;
use App\Models\SurveyResponseModel;
use CodeIgniter\HTTP\ResponseInterface;
use Throwable;

class DashboardStatsController extends BaseController
{
    /**
     * GET /admin/stats/users-activity?range=day|week|month
     */
    public function usersActivity(): ResponseInterface
    {
        try {
            $range  = (string) ($this->request->getGet('range') ?? 'day');
            $users  = new UserModel();
            $total  = $users->countAll();
            $active = $users->countActiveByRange($range);

            return $this->response->setJSON([
                'success' => true,
                'range'   => $range,
                'data'    => [
                    'total'  => (int) $total,
                    'active' => (int) $active,
                    'other'  => max(0, (int) $total - (int) $active),
                ],
            ]);
        } catch (Throwable $e) {
            return $this->response->setStatusCode(500)
                ->setJSON(['success' => false, 'message' => 'usersActivity failed', 'error' => $e->getMessage()]);
        }
    }

    /**
     * GET /admin/stats/ai-usage?range=day|month|year
     */
    public function aiUsage(): ResponseInterface
    {
        try {
            $range = (string) ($this->request->getGet('range') ?? 'day');
            $model  = new AiUsageLogModel();
            $result = $model->aggregateByRange($range);

            return $this->response->setJSON([
                'success' => true,
                'range'   => $range,
                'data'    => $result,
            ]);
        } catch (Throwable $e) {
            return $this->response->setStatusCode(500)
                ->setJSON(['success' => false, 'message' => 'aiUsage failed', 'error' => $e->getMessage()]);
        }
    }

    /**
     * GET /admin/stats/satisfaction-level?range=day|week|month
     */
    public function satisfactionLevel(): ResponseInterface
    {
        try {
            $range = (string) ($this->request->getGet('range') ?? 'day');
            if (!in_array($range, ['day', 'week', 'month'], true)) {
                $range = 'day';
            }

            $model = new SurveyResponseModel();
            $data  = $model->countSatisfactionByRange($range);

            return $this->response->setJSON([
                'success' => true,
                'range'   => $range,
                'data'    => $data,
            ]);
        } catch (Throwable $e) {
            return $this->response->setStatusCode(500)
                ->setJSON(['success' => false, 'message' => 'satisfactionLevel failed', 'error' => $e->getMessage()]);
        }
    }

    /**
     * GET /admin/stats/survey-devices?range=day|week|month
     */
    public function surveyDevices(): ResponseInterface
    {
        try {
            $range = (string) ($this->request->getGet('range') ?? 'month');
            if (!in_array($range, ['day','week','month'], true)) {
                $range = 'month';
            }

            $m    = new SurveyResponseModel();
            $data = $m->devicesBreakdown($range);

            return $this->response->setJSON([
                'success' => true,
                'range'   => $range,
                'data'    => $data,
            ]);
        } catch (Throwable $e) {
            return $this->response->setStatusCode(500)
                ->setJSON(['success' => false, 'message' => 'surveyDevices failed', 'error' => $e->getMessage()]);
        }
    }

    /**
     * GET /admin/stats/survey-sources?range=day|week|month
     */
    public function surveySources(): ResponseInterface
    {
        try {
            $range = (string) ($this->request->getGet('range') ?? 'month');
            if (!in_array($range, ['day','week','month'], true)) {
                $range = 'month';
            }

            $m    = new SurveyResponseModel();
            $data = $m->awarenessBreakdown($range);

            return $this->response->setJSON([
                'success' => true,
                'range'   => $range,
                'data'    => $data,
            ]);
        } catch (Throwable $e) {
            return $this->response->setStatusCode(500)
                ->setJSON(['success' => false, 'message' => 'surveySources failed', 'error' => $e->getMessage()]);
        }
    }

    /**
     * GET /admin/stats/registration-completion
     * يعيد {completed, incomplete, total} اعتمادًا على users.is_complete
     */
    public function registrationCompletion(): ResponseInterface
    {
        try {
            $users = new UserModel();
            $data  = $users->getRegistrationCompletionCounts();

            return $this->response->setJSON([
                'success' => true,
                'data'    => $data,
            ]);
        } catch (Throwable $e) {
            return $this->response->setStatusCode(500)
                ->setJSON(['success' => false, 'message' => 'registrationCompletion failed', 'error' => $e->getMessage()]);
        }
    }
}
