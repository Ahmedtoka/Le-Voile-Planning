<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * @deprecated اندمج في App\Services\DemoDataService.
 * سايبينه فاضي عشان أي استدعاء قديم ما يكسرش.
 */
class DemoFlowSeeder extends Seeder
{
    public function run(): void
    {
        $this->command?->warn('DemoFlowSeeder اتنقل لـ DemoDataService — استخدم DemoSeeder أو php artisan lv:demo');
    }
}
