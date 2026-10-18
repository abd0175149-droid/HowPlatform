<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            PointRuleSeeder::class,
            FeatureCatalogSeeder::class,
            RealPlansSeeder::class,
        ]);
    }
}
