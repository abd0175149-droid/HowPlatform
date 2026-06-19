<?php

declare(strict_types=1);

namespace App\Controllers\Admins\Stats;

use App\Controllers\BaseController;
use App\Services\ToolsStatsService;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Stats\ToolsStats
 * REST endpoints لإحصاءات منصة الأدوات
 */
class ToolsStats extends BaseController
{
    /**
     * GET /admins/api/stats/tools/cards?only_active=1&limit=12
     * يعيد بطاقات الأدوات: uses_count, rating_avg, last_used_at (محلي ومختصر)
     */
  public function cards(): ResponseInterface
{
    try {
        // only_active: true افتراضيًا إذا لم يُرسل
        $onlyActiveParam = $this->request->getGet('only_active');
        $onlyActive = $onlyActiveParam === null ? true : ((int) $onlyActiveParam !== 0);

        // limit: 1..100 أو null
        $limitParam = $this->request->getGet('limit');
        $limit = null;
        if ($limitParam !== null && $limitParam !== '') {
            $limit = max(1, min(100, (int) $limitParam));
        }

        $svc   = new \App\Services\ToolsStatsService();
        $items = $svc->getToolCards($onlyActive, $limit);

        return $this->response->setStatusCode(200)->setJSON([
            'ok'    => true,
            'count' => count($items),
            'items' => $items,
        ]);
    } catch (\Throwable $e) {
        // 🔎 لوج واضح في ملفات السجل
        log_message('error', 'ToolsStats::cards failed: {msg} in {file}:{line}', [
            'msg'  => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
        ]);

        // ⚠️ مؤقتًا: أظهر رسالة الخطأ للمساعدة في التشخيص
        return $this->response->setStatusCode(500)->setJSON([
            'ok'      => false,
            'message' => 'تعذّر جلب بطاقات الأدوات.',
            'error'   => $e->getMessage(), // أزِلها لاحقًا بعد الإصلاح
        ]);
    }
}

}
