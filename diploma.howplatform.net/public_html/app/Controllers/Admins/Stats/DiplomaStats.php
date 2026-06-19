<?php

declare(strict_types=1);

namespace App\Controllers\Admins\Stats;

use App\Controllers\BaseController;
use App\Services\DiplomaStatsService;
use CodeIgniter\HTTP\ResponseInterface;

class DiplomaStats extends BaseController
{
    private DiplomaStatsService $svc;

    public function __construct()
    {
        $this->svc = new DiplomaStatsService();
    }

    /** GET /admins/api/stats/diploma/active-users */
    public function activeUsers(): ResponseInterface
    {
        $data = $this->svc->getActiveUsersSummary();

        return $this->response->setJSON([
            'ok'   => true,
            'data' => $data,
        ]);
    }

    /** GET /admins/api/stats/diploma/active-by-university?window=day|week|month&limit=10 */
    public function activeByUniversity(): ResponseInterface
    {
        $window = (string) ($this->request->getGet('window') ?? 'day');
        $limit  = (int) ($this->request->getGet('limit') ?? 10);

        $data = $this->svc->getActiveByUniversity($window, $limit);

        return $this->response->setJSON([
            'ok'   => true,
            'data' => $data,
        ]);
    }

    /** GET /admins/api/stats/diploma/registration-completion */
    public function registrationCompletion(): ResponseInterface
    {
        $data = $this->svc->getRegistrationCompletion();

        return $this->response->setJSON([
            'ok'   => true,
            'data' => $data,
        ]);
    }

    /** GET /admins/api/stats/diploma/plan-distribution */
    public function planDistribution(): ResponseInterface
    {
        $data = $this->svc->getPlanDistribution();

        return $this->response->setJSON([
            'ok'   => true,
            'data' => $data,
        ]);
    }

    /** GET /admins/api/stats/diploma/universities-distribution?limit=100 */
    public function universitiesDistribution(): ResponseInterface
    {
        $limit = (int) ($this->request->getGet('limit') ?? 100);
        $data  = $this->svc->getUniversitiesDistribution($limit);

        return $this->response->setJSON([
            'ok'   => true,
            'data' => $data,
        ]);
    }

    /** GET /admins/api/stats/diploma/fateen-usage?bucket=day|month|year */
    public function fateenUsage(): ResponseInterface
    {
        $bucket = (string) ($this->request->getGet('bucket') ?? 'day');
        $data   = $this->svc->getFateenUsage($bucket);

        return $this->response->setJSON([
            'ok'   => true,
            'data' => $data,
        ]);
    }

    /** GET /admins/api/stats/diploma/active-breakdown?window=day|week|month */
    public function activeBreakdown(): ResponseInterface
    {
        $window = (string) ($this->request->getGet('window') ?? 'day');
        $data   = $this->svc->getActiveBreakdown($window);

        return $this->response->setJSON([
            'ok'   => true,
            'data' => $data,
        ]);
    }

    /**
     * GET /admins/api/stats/diploma/active-users-list?window=day|week|month&page=1&per_page=5&debug=1
     * يعيد الأسماء + الجامعة + التخصص مع باجينيشن ولوجينغ.
     */
    public function activeUsersList(): ResponseInterface
    {
        $logger    = service('logger');

        $window    = (string) ($this->request->getGet('window') ?? 'day');
        $page      = max(1, (int) ($this->request->getGet('page') ?? 1));
        $perPage   = max(1, min(50, (int) ($this->request->getGet('per_page') ?? 5)));
        $wantDebug = (int) ($this->request->getGet('debug') ?? 0) === 1;

        $logger->debug('ActiveUsersList request params', [
            'window'   => $window,
            'page'     => $page,
            'per_page' => $perPage,
            'query'    => $this->request->getGet(),
            'ip'       => $this->request->getIPAddress(),
            'ua'       => $this->request->getUserAgent()->getAgentString(),
        ]);

        try {
            $data = $this->svc->getActiveUsersList($window, $page, $perPage);

            $logger->debug('ActiveUsersList service result', [
                'window'        => $data['window'] ?? null,
                'start_at'      => $data['start_at'] ?? null,
                'end_at'        => $data['end_at'] ?? null,
                'page'          => $data['page'] ?? null,
                'per_page'      => $data['per_page'] ?? null,
                'total'         => $data['total'] ?? null,
                'items_count'   => isset($data['items']) && is_array($data['items']) ? count($data['items']) : 0,
                'items_preview' => array_map(static fn($r) => $r['display_name'] ?? null, array_slice($data['items'] ?? [], 0, 3)),
            ]);

            $hasPrev = ($data['page'] ?? 1) > 1;
            $hasNext = (($data['page'] ?? 1) * ($data['per_page'] ?? $perPage)) < ($data['total'] ?? 0);

            // ✅ نمرّر الجامعة والتخصص أيضًا
$items = array_map(
    static function (array $r): array {
        return [
            'id'                 => (int)   ($r['id'] ?? 0),
            'display_name'       => (string)($r['display_name'] ?? 'User'),
            'university_name'    => isset($r['university_name']) ? (string)$r['university_name'] : null,
            'specialty_name'     => isset($r['specialty_name'])  ? (string)$r['specialty_name']  : null,
            'last_login_at'      => (string)($r['last_login_at'] ?? ''),     // UTC
            'last_login_local'   => $r['last_login_local']   ?? null,        // Asia/Amman
            'last_login_display' => $r['last_login_display'] ?? null,        // 12h + شهر عربي
        ];
    },
    $data['items'] ?? []
);


            $debugHeader = json_encode([
                'window'   => $data['window'] ?? $window,
                'start_at' => $data['start_at'] ?? null,
                'end_at'   => $data['end_at'] ?? null,
                'page'     => $data['page'] ?? $page,
                'per_page' => $data['per_page'] ?? $perPage,
                'total'    => $data['total'] ?? 0,
                'items'    => count($items),
            ], JSON_UNESCAPED_UNICODE);

            $this->response->setHeader('X-Debug-ActiveUsers', (string) $debugHeader);

            $payload = [
                'ok'       => true,
                'window'   => $data['window'] ?? $window,
                'start_at' => $data['start_at'] ?? null,
                'end_at'   => $data['end_at'] ?? null,
                'page'     => $data['page'] ?? $page,
                'per_page' => $data['per_page'] ?? $perPage,
                'total'    => $data['total'] ?? 0,
                'has_prev' => $hasPrev,
                'has_next' => $hasNext,
                'items'    => $items,
            ];

            if ($wantDebug) {
                $payload['debug'] = [
                    'request' => [
                        'window'   => $window,
                        'page'     => $page,
                        'per_page' => $perPage,
                    ],
                    'service' => [
                        'window'   => $data['window'] ?? null,
                        'start_at' => $data['start_at'] ?? null,
                        'end_at'   => $data['end_at'] ?? null,
                        'totals'   => [
                            'total'       => $data['total'] ?? 0,
                            'items_count' => count($items),
                        ],
                    ],
                ];
            }

            return $this->response->setStatusCode(200)->setJSON($payload);
        } catch (\Throwable $e) {
            $logger->error('ActiveUsersList exception', [
                'window'   => $window,
                'page'     => $page,
                'per_page' => $perPage,
                'error'    => $e->getMessage(),
                'file'     => $e->getFile(),
                'line'     => $e->getLine(),
                'trace'    => $e->getTraceAsString(),
            ]);

            $this->response->setHeader('X-Debug-ActiveUsers', 'ERROR');

            return $this->response->setStatusCode(500)->setJSON([
                'ok'    => false,
                'error' => 'ActiveUsersList failed',
                'debug' => $wantDebug ? [
                    'message' => $e->getMessage(),
                    'file'    => $e->getFile(),
                    'line'    => $e->getLine(),
                ] : null,
            ]);
        }
    }

    /** GET /admins/api/stats/diploma/ai-job-stats?type_key= */
    public function aiJobStats(): ResponseInterface
    {
        $typeKey = (string) ($this->request->getGet('type_key') ?? '');
        $typeKey = $typeKey !== '' ? $typeKey : null;

        $data = $this->svc->getAiJobStats($typeKey);

        return $this->response->setJSON([
            'ok'   => true,
            'data' => $data,
        ]);
    }
}
