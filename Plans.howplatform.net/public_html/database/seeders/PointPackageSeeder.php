<?php

namespace Database\Seeders;

use App\Models\PointPackage;
use Illuminate\Database\Seeder;

/**
 * سلّم تسعير النقاط (idempotent: يُحدِّث السعر إن تغيّر، ولا يُكرّر الحزمة).
 * القيم المعتمَدة من المؤسّس (2026-06-29):
 *   200 نقطة = 2 دينار، 500 = 4، 1000 = 7، 2000 = 10.
 */
class PointPackageSeeder extends Seeder
{
    public function run(): void
    {
        $packages = [
            ['points' => 200,  'price_amount' => 2,  'sort_order' => 1],
            ['points' => 500,  'price_amount' => 4,  'sort_order' => 2],
            ['points' => 1000, 'price_amount' => 7,  'sort_order' => 3],
            ['points' => 2000, 'price_amount' => 10, 'sort_order' => 4],
        ];

        foreach ($packages as $p) {
            PointPackage::updateOrCreate(
                ['points' => $p['points']],
                [
                    'price_amount'   => $p['price_amount'],
                    'price_currency' => 'JOD',
                    'sort_order'     => $p['sort_order'],
                    'is_active'      => true,
                ]
            );
        }
    }
}
