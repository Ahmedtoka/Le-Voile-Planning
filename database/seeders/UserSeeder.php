<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    /** [اسم الدخول, الاسم, المسمى, الأدوار] — كلمة المرور للجميع: 123456 */
    public const USERS = [
        ['admin',    'مدير النظام',       'IT',              ['admin']],
        ['planner',  'أحمد التخطيط',      'مخطط إنتاج',      ['planner']],
        ['moaz',     'محمد مسعد',         'مشتريات',         ['purchasing']],
        ['hshmt',    'حشمت',              'مدير المشتريات',  ['purchasing_mgr']],
        ['store',    'حازم أمين المخزن',  'أمين مخزن',       ['storekeeper']],
        ['control',  'مراقب المخزون',     'مراقب مخزون',     ['stock_controller']],
        ['lab',      'إبراهيم المعمل',    'فني معمل',        ['lab_tech']],
        ['qc',       'فاحص القماش',       'فاحص',            ['inspector']],
        ['pattern',  'الباترونست',        'باترونست',        ['patternist']],
        ['follow',   'متابع المصانع',     'متابعة مصانع',    ['factory_follow']],
        ['finance',  'الإدارة المالية',   'مالية',           ['finance']],
        ['gm',       'المدير العام',      'مدير عام',        ['gm']],
    ];

    public function run(): void
    {
        foreach (self::USERS as [$username, $name, $job, $roleKeys]) {
            $user = User::updateOrCreate(['username' => $username], [
                'name'      => $name,
                'job_title' => $job,
                'password'  => '123456',
                'is_active' => true,
            ]);

            $user->roles()->sync(Role::whereIn('key', $roleKeys)->pluck('id'));
        }
    }
}
