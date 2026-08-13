<?php

namespace Database\Seeders;

use App\Services\DemoDataService;
use Illuminate\Database\Seeder;

/**
 * الداتا الديمو — نفس اللي بيعمله زرار «ولّد الداتا» في الإعدادات،
 * وبيتنادى كمان من `php artisan lv:demo`.
 * مصدر واحد للحقيقة، عشان الاتنين ما يفرقوش.
 */
class DemoSeeder extends Seeder
{
    public function run(): void
    {
        $svc = new DemoDataService(fn ($m) => $this->command?->line('    ' . $m));
        $stats = $svc->generate();

        $this->command?->newLine();
        $this->command?->table(
            ['العنصر', 'العدد'],
            collect($stats)->map(fn ($v, $k) => [$k, $v])->values()->all()
        );
    }
}
