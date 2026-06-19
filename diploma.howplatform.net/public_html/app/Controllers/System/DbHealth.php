<?php

declare(strict_types=1);

namespace App\Controllers\System;

use App\Controllers\BaseController;
use CodeIgniter\HTTP\ResponseInterface;
use Throwable;

class DbHealth extends BaseController
{
    /**
     * ✅ فحص اتصال default فقط (للتوافق الخلفي)
     */
    public function default(): ResponseInterface
    {
        return $this->probe('default', true);
    }

    /**
     * ✅ فحص اتصال remote فقط (للتوافق الخلفي)
     */
    public function remote(): ResponseInterface
    {
        return $this->probe('remote', true);
    }

    /**
     * ✅ فحص مجموعة مُحدّدة بالاسم (عبر الراوت: /system/db-health/group/{name})
     */
    public function group(string $name): ResponseInterface
    {
        // تبسيط الاسم وتمييزه
        $group = trim($name);
        return $this->probe($group, true);
    }

    /**
     * ✅ فحص شامل لكل المجموعات المُعرّفة في Config\Database
     */
    public function all(): ResponseInterface
    {
        $groups = $this->listConfiguredGroups();
        $out    = [];

        foreach ($groups as $g) {
            $out[$g] = $this->tryProbe($g, true);
        }

        return $this->response->setJSON([
            'detected_groups' => $groups,
            'results'         => $out,
        ]);
    }

    /**
     * 🧩 دالة مساعدة: تُعيد JSON موحّد لفحص مجموعة معيّنة
     *
     * @param string $group اسم مجموعة الاتصال في Config\Database
     * @param bool   $includeEnv هل نضمّن قراءة env للمجموعة للمساعدة في التشخيص
     */
    private function probe(string $group, bool $includeEnv = false): ResponseInterface
    {
        try {
            $res = $this->doProbe($group, $includeEnv);
            return $this->response->setJSON($res);
        } catch (Throwable $e) {
            log_message('error', 'DB probe failed ({group}): {msg}', [
                'group' => $group,
                'msg'   => $e->getMessage(),
            ]);
            return $this->response->setStatusCode(500)->setJSON([
                'group' => $group,
                'ok'    => false,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * 🧩 مثل probe لكن لا يرمي استثناءً، مناسب للتجميع داخل all()
     */
    private function tryProbe(string $group, bool $includeEnv = false): array
    {
        try {
            return $this->doProbe($group, $includeEnv);
        } catch (Throwable $e) {
            return [
                'group' => $group,
                'ok'    => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * 🧠 تنفيذ الفحص الفعلي لاتصال قاعدة بيانات
     */
    private function doProbe(string $group, bool $includeEnv): array
    {
        // === تشخيص وجود المجموعة في الإعدادات ===
        $cfg         = config('Database');
        $groupExists = property_exists($cfg, $group);
        $configArr   = $groupExists ? $cfg->{$group} : null;
        $groupEmpty  = $groupExists ? (empty($configArr) || !is_array($configArr)) : true;

        // === قراءة قيم env للمجموعة (بدون كلمة المرور) ===
        $envRead = $includeEnv ? $this->envReadForGroup($group) : null;

        // إن لم تكن المجموعة موجودة في Config أو كانت فارغة، نُعيد تشخيصاً واضحاً
        if ($groupEmpty) {
            return [
                'group'       => $group,
                'ok'          => false,
                'error'       => 'DB group is missing or empty in Config\\Database.',
                'groupExists' => $groupExists,
                'envRead'     => $envRead,
            ];
        }

        // === محاولة الاتصال ===
        $db   = \Config\Database::connect($group);
        $row1 = $db->query('SELECT 1 AS ok')->getRowArray();

        // معلومات عامة من السيرفر/الاتصال
        $meta = $db->query('SELECT DATABASE() AS db, @@hostname AS host, @@version AS version')->getRowArray();

        // عدّ الجداول (اختياري) — يعمل مع MySQL/MariaDB
        $schemaName = $meta['db'] ?? '';
        $tablesCnt  = null;
        if ($schemaName !== '') {
            $tablesCnt = $db->query(
                'SELECT COUNT(*) AS c FROM information_schema.TABLES WHERE TABLE_SCHEMA = ?',
                [$schemaName]
            )->getRowArray();
        }

        // معلومات الاتصال من الكائن (لا تتضمن كلمة المرور)
        $connInfo = [
            'database' => $db->database ?? null,
            'hostname' => $db->hostname ?? null,
            'port'     => $db->port ?? null,
            'driver'   => $db->DBDriver ?? null,
        ];

        return [
            'group'        => $group,
            'ok'           => (int) ($row1['ok'] ?? 0) === 1,
            'meta'         => $meta,
            'tables_count' => $tablesCnt['c'] ?? null,
            'conn_info'    => $connInfo,
            'groupExists'  => $groupExists,
            'envRead'      => $envRead,
        ];
    }

    /**
     * 🔎 إحضار قائمة المجموعات المُعرّفة في Config\Database بشكل ديناميكي
     * - نلتقط كل خاصية من نوع مصفوفة (اتصال) ونستثني الحقول غير ذات الصلة.
     */
    private function listConfiguredGroups(): array
    {
        $cfg   = config('Database');
        $vars  = get_object_vars($cfg);
        $out   = [];

        foreach ($vars as $key => $val) {
            // نستثني خصائص ليست مجموعات اتصال
            if (in_array($key, ['filesPath', 'defaultGroup', 'tests'], true)) {
                continue;
            }
            if (is_array($val)) {
                $out[] = $key;
            }
        }

        // ترتيب ثابت لسهولة القراءة
        sort($out);
        return $out;
    }

    /**
     * 🔐 قراءة آمنة لقيم env لمجموعة معينة (بدون كلمة المرور)
     * - يفيد في تشخيص اختلاف الكونفق عن .env
     */
    private function envReadForGroup(string $group): array
    {
        $prefix = 'database.' . $group . '.';

        // ملاحظة: نقرأ فقط القيم التشخيصية ونمتنع عن كشف كلمة المرور
        return [
            $prefix . 'hostname' => (string) env($prefix . 'hostname'),
            $prefix . 'database' => (string) env($prefix . 'database'),
            $prefix . 'username' => (string) env($prefix . 'username'),
            $prefix . 'port'     => (string) env($prefix . 'port'),
            $prefix . 'DBDriver' => (string) env($prefix . 'DBDriver'),
        ];
    }
}
