<?php

declare(strict_types=1);

namespace App\Controllers\Admins\Stats;

use App\Controllers\BaseController;
use App\Services\LibraryStatsService;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * LibraryStats
 * واجهات API لإحصائيات المكتبة تحت /admins/api/stats/library/*
 */
class LibraryStats extends BaseController
{
    private LibraryStatsService $svc;

    public function __construct()
    {
        $this->svc = new LibraryStatsService();
    }

    /** GET /admins/api/stats/library/downloads-summary?window=month&filter_bots=1 */
    public function downloadsSummary(): ResponseInterface
    {
        $window     = (string) ($this->request->getGet('window') ?? '');
        $filterBots = (bool) ((int) ($this->request->getGet('filter_bots') ?? 1));

        $data = $this->svc->getDownloadsSummary($window ?: null, $filterBots);
        return $this->response->setJSON(['ok' => true, 'data' => $data]);
    }

    /** GET /admins/api/stats/library/top-grades?window=month&limit=10&normalized=1&filter_bots=1 */
    public function topGrades(): ResponseInterface
    {
        $window     = (string) ($this->request->getGet('window') ?? 'month');
        $limit      = (int) ($this->request->getGet('limit') ?? 10);
        $normalized = (bool) ((int) ($this->request->getGet('normalized') ?? 0));
        $filterBots = (bool) ((int) ($this->request->getGet('filter_bots') ?? 1));

        $data = $this->svc->getTopGradesByDownloads($limit, $window, $normalized, $filterBots);
        return $this->response->setJSON(['ok' => true, 'data' => $data]);
    }

    /** GET /admins/api/stats/library/downloads-auth-vs-anon?window=month&filter_bots=1 */
    public function downloadsAuthVsAnon(): ResponseInterface
    {
        $window     = (string) ($this->request->getGet('window') ?? 'month');
        $filterBots = (bool) ((int) ($this->request->getGet('filter_bots') ?? 1));

        $data = $this->svc->getDownloadsAuthVsAnon($window, $filterBots);
        return $this->response->setJSON(['ok' => true, 'data' => $data]);
    }

    /** GET /admins/api/stats/library/visits-summary?window=month&filter_bots=1 */
    public function visitsSummary(): ResponseInterface
    {
        $window     = (string) ($this->request->getGet('window') ?? 'month');
        $filterBots = (bool) ((int) ($this->request->getGet('filter_bots') ?? 1));

        $data = $this->svc->getVisitsSummary($window, $filterBots);
        return $this->response->setJSON(['ok' => true, 'data' => $data]);
    }
}
