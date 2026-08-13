<?php

namespace App\Console\Commands;

use App\Services\DemoDataService;
use Illuminate\Console\Command;

class ResetDataCommand extends Command
{
    protected $signature = 'lv:reset {--force : من غير تأكيد}';
    protected $description = 'مسح كل بيانات الشغل مع الإبقاء على المستخدمين والأدوار ودورات الاعتماد';

    public function handle(): int
    {
        if (!$this->option('force') && !$this->confirm('هيتمسح كل بيانات الشغل. المستخدمون والأدوار هيفضلوا. متأكد؟')) {
            $this->warn('اتلغى.');
            return self::SUCCESS;
        }

        (new DemoDataService(fn ($m) => $this->line('  ' . $m)))->reset();
        $this->info('تم المسح.');

        return self::SUCCESS;
    }
}
