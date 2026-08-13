<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

class RolePermissionSeeder extends Seeder
{
    /** الأدوار — الطقم الكامل */
    public const ROLES = [
        'admin'            => ['مدير النظام', 'صلاحية كاملة على كل حاجة'],
        'planner'          => ['مخطط الإنتاج', 'قلب السيستم — الأحواض، الماركرات، أوامر الشغل، الفوركاست'],
        'purchasing'       => ['مشتريات', 'إنشاء طلبات الشراء ومتابعة الموردين'],
        'purchasing_mgr'   => ['مدير المشتريات', 'اعتماد طلبات الشراء'],
        'storekeeper'      => ['أمين مخزن', 'أذون الاستلام والإضافة واستلام الإنتاج'],
        'stock_controller' => ['مراقب مخزون', 'مراجعة الأذون والأرصدة والجرد'],
        'lab_tech'         => ['فني معمل', 'تقارير الانكماش ومطابقة الألوان'],
        'inspector'        => ['فاحص قماش', 'تقارير فحص القماش'],
        'patternist'       => ['باترونست', 'رفع الماركرات وبياناتها'],
        'factory_follow'   => ['متابعة مصانع', 'بيانات القص واستلامات الإنتاج'],
        'finance'          => ['مالية', 'اعتماد طلبات الشراء ماليًا'],
        'gm'               => ['مدير عام', 'عرض شامل واعتمادات عليا'],
    ];

    /** الصلاحيات مجمّعة */
    public const PERMISSIONS = [
        'البيانات الأساسية' => [
            'master.view'   => 'عرض البيانات الأساسية',
            'master.manage' => 'إدارة البيانات الأساسية',
            'colors.merge'  => 'دمج أكواد الألوان',
        ],
        'دورة الشراء' => [
            'po.view'    => 'عرض طلبات الشراء',
            'po.request' => 'إنشاء طلب شراء (التخطيط)',
            'po.source'  => 'تحديد المورد والأسعار (المشتريات)',
            'po.finance' => 'علم الحسابات والمستحقات',
            'po.manage'  => 'تعديل إداري على الطلبات',
            'po.approve' => 'اعتماد طلبات الشراء',
        ],
        'المخازن' => [
            'receipt.view'   => 'عرض أذون الاستلام',
            'receipt.manage' => 'إنشاء أذون الاستلام والإضافة',
            'receipt.approve'=> 'اعتماد أذون المخازن',
        ],
        'الجودة' => [
            'qc.view'    => 'عرض تقارير الفحص والمعمل',
            'qc.manage'  => 'إنشاء تقارير الفحص والمعمل',
            'qc.approve' => 'اعتماد تقارير الجودة',
        ],
        'التخطيط' => [
            'marker.view'    => 'عرض الماركرات',
            'marker.manage'  => 'إنشاء ورفع الماركرات',
            'marker.approve' => 'اعتماد الماركرات',
            'wo.view'        => 'عرض أوامر الشغل',
            'wo.manage'      => 'إنشاء أوامر الشغل',
            'wo.approve'     => 'اعتماد أوامر الشغل',
            'wo.close'       => 'قفل أوامر الشغل',
        ],
        'الإنتاج' => [
            'cut.view'    => 'عرض بيانات القص',
            'cut.manage'  => 'إدخال بيانات القص',
            'cut.approve' => 'اعتماد بيانات القص',
            'prod.manage' => 'استلام الإنتاج',
            'prod.approve'=> 'اعتماد استلامات الإنتاج',
        ],
        'الفوركاست' => [
            'forecast.view'   => 'عرض الفوركاست والتغطية',
            'forecast.manage' => 'تعديل النسب وتوليد الفوركاست',
        ],
        'النظام' => [
            'import.manage'   => 'استيراد وتصدير البيانات',
            'settings.users'  => 'إدارة المستخدمين',
            'settings.roles'  => 'إدارة الأدوار والصلاحيات',
            'settings.flows'  => 'إدارة دورات الاعتماد',
            'settings.audit'  => 'عرض سجل الحركة',
        ],
    ];

    /** الصلاحيات الافتراضية لكل دور */
    public const ROLE_PERMISSIONS = [
        // التخطيط هو اللي بيبدأ طلب الشراء
        'planner' => ['master.view','po.view','po.request','receipt.view','qc.view','marker.view','marker.manage',
                      'wo.view','wo.manage','wo.close','cut.view','forecast.view','forecast.manage','import.manage'],
        // المشتريات بتسعّر وتحدد المورد — مش بتنشئ الطلب
        'purchasing' => ['master.view','po.view','po.source','receipt.view'],
        'purchasing_mgr' => ['master.view','po.view','po.source','po.manage','po.approve','receipt.view','receipt.approve'],
        'storekeeper' => ['master.view','receipt.view','receipt.manage','prod.manage','qc.view'],
        'stock_controller' => ['master.view','receipt.view','receipt.approve','prod.approve','qc.view'],
        'lab_tech' => ['qc.view','qc.manage','master.view'],
        'inspector' => ['qc.view','qc.manage','master.view'],
        'patternist' => ['marker.view','marker.manage','master.view','wo.view'],
        'factory_follow' => ['wo.view','cut.view','cut.manage','prod.manage','master.view'],
        'finance' => ['po.view','po.finance','po.approve','forecast.view'],
        'gm' => ['master.view','po.view','po.approve','po.finance','receipt.view','qc.view','marker.view',
                 'wo.view','wo.approve','cut.view','cut.approve','forecast.view','settings.audit'],
    ];

    public function run(): void
    {
        foreach (self::ROLES as $key => [$name, $desc]) {
            Role::updateOrCreate(['key' => $key], [
                'name' => $name, 'description' => $desc, 'is_system' => true,
            ]);
        }

        foreach (self::PERMISSIONS as $group => $perms) {
            foreach ($perms as $key => $name) {
                Permission::updateOrCreate(['key' => $key], ['name' => $name, 'group' => $group]);
            }
        }

        foreach (self::ROLE_PERMISSIONS as $roleKey => $permKeys) {
            $role = Role::where('key', $roleKey)->first();
            if (!$role) continue;
            $role->permissions()->sync(Permission::whereIn('key', $permKeys)->pluck('id'));
        }
    }
}
