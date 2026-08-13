<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // الأساس: أدوار وصلاحيات ومستخدمين ودورات اعتماد — ده مش داتا ديمو
        $this->call([
            RolePermissionSeeder::class,
            UserSeeder::class,
            ApprovalFlowSeeder::class,
        ]);

        // الداتا الديمو — تقدر تمسحها وتولّدها من الإعدادات ← أدوات الداتا
        $this->call(DemoSeeder::class);
    }
}
