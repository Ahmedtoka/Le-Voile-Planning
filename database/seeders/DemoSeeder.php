<?php

namespace Database\Seeders;

use App\Services\PaperSeeder;
use Illuminate\Database\Seeder;

/**
 * الافتراضي بعد migrate --seed = **الورق الحقيقي**.
 * نفس اللي بيعمله `php artisan lv:paper` وزرار «الورق الحقيقي» في الإعدادات.
 *
 * عايز ديمو كبير للتجربة؟  php artisan lv:demo
 * عايز بيانات أساسية بس؟   php artisan lv:master
 */
class DemoSeeder extends Seeder
{
    public function run(): void
    {
        $stats = (new PaperSeeder(fn ($m) => $this->command?->line('    ' . $m)))->run();

        $this->command?->newLine();
        $this->command?->table(
            ['العنصر', 'العدد'],
            collect($stats)->map(fn ($v, $k) => [$k, $v])->values()->all()
        );
    }
}
