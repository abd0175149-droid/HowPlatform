<?php

namespace App\Filters;

use CodeIgniter\Config\Services;
use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

class SubdomainDbSelector implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        // التقاط الهوست وتحديد السب-دومين
        $host = strtolower($request->getServer('HTTP_HOST') ?? '');
        $parts = array_values(array_filter(explode('.', $host)));

        // إن كان النطاق root بدون سب-دومين
        $sub = (count($parts) >= 3) ? $parts[0] : 'root';

        // خريطة السب-دومين -> مجموعة الاتصال
        $map = [
            // root أو www تروح لواجهة howpaltform
            'root'    => 'frontend',
            'www'     => 'frontend',

            // سب-دومينات إضافية
            'tools'   => 'tools',
            'library' => 'library',
            'auth'    => 'remote',   // إن وجد auth.domain.com
        ];

        $group = $map[$sub] ?? 'default';

        // تبديل المجموعة الافتراضية قبل أي اتصال DB
        $dbConfig = config('Database');
        $dbConfig->defaultGroup = $group;

        // (اختياري) حضّر اتصالاً مبكراً للتأكد من الصحة
        // \Config\Database::connect($group, false);

        // خزن المجموعة الحالية في الحاوية للاستعلام لاحقاً
        Services::session()->set('db_group', $group);
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
    }
}
