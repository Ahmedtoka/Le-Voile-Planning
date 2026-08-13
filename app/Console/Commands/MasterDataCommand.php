<?php

namespace App\Console\Commands;

use App\Services\DemoDataService;
use Illuminate\Console\Command;

/**
 * البيانات الأساسية بس — نقطة البداية للشغل الحقيقي.
 * موردين ومصانع وخامات وألوان وموديلات وإكسسوارات، من غير أي مستندات.
 */
class MasterDataCommand extends Command
{
    protected $signature = 'lv:master';
    protected $description = 'مسح بيانات الشغل وتجهيز البيانات الأساسية بس (بدون طلبات ولا أحواض ولا أوامر)';

    public function handle(): int
    {
        $this->info('بجهّز البيانات الأساسية…');

        $stats = (new DemoDataService(fn ($m) => $this->line('  ' . $m)))->generateMasterOnly();

        $this->newLine();
        $this->table(['العنصر', 'العدد'], collect($stats)->map(fn ($v, $k) => [$k, $v])->values()->all());
        $this->info('جاهز. ادخل بـ planner / 123456 وابدأ من طلبات الشراء.');

        return self::SUCCESS;
    }
}
