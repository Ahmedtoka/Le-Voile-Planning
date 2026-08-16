<?php

namespace App\Console\Commands;

use App\Services\PaperSeeder;
use Illuminate\Console\Command;

/**
 * إدخال المستندات الحقيقية بأرقامها الفعلية —
 * طلب شراء 107 · استلام 1000885 · فحص 04379 · KB106/KB107 · صرف 1303774
 */
class PaperCommand extends Command
{
    protected $signature = 'lv:paper';
    protected $description = 'تفريغ السيستم وإدخال المستندات الورقية الحقيقية بأرقامها';

    public function handle(): int
    {
        $this->info('بفرّغ السيستم وأدخّل الورق الحقيقي…');

        $stats = (new PaperSeeder(fn ($m) => $this->line('  ' . $m)))->run();

        $this->newLine();
        $this->table(['العنصر', 'العدد'], collect($stats)->map(fn ($v, $k) => [$k, $v])->values()->all());
        $this->info('تمام. ادخل بـ planner / 123456 وقارن كل شاشة بالورقة اللي في إيدك.');

        return self::SUCCESS;
    }
}
