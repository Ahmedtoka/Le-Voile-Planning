<?php

namespace App\Console\Commands;

use App\Services\DemoDataService;
use Illuminate\Console\Command;

class DemoDataCommand extends Command
{
    protected $signature = 'lv:demo';

    protected $description = 'مسح بيانات الشغل وتوليد داتا ديمو كاملة تمشي الفلو من أوله لآخره';

    public function handle(): int
    {
        $this->info('بمسح وبولّد الداتا الديمو…');
        $stats = (new DemoDataService(fn ($m) => $this->line('  ' . $m)))->generate();

        $this->newLine();
        $this->table(['العنصر', 'العدد'], collect($stats)->map(fn ($v, $k) => [$k, $v])->values()->all());
        $this->info('تمام. ادخل بـ planner / 123456');

        return self::SUCCESS;
    }
}
