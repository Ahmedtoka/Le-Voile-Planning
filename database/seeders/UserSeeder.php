<?php

namespace Database\Seeders;

use App\Models\Factory;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * مستخدمو الديمو — واحد لكل دور بأسماء واقعية، وكلمة المرور للجميع 123456.
 *
 * idempotent: بيعمل updateOrCreate على اسم الدخول، فتقدر تشغّله كل ما تحب
 * من غير ما يكرّر حد. مستخدمو المصانع بيتربطوا بالمصنع لو موجود
 * (users.factory_id) — ولو البيانات الأساسية لسه ما اتولّدتش، linkFactories()
 * بتتنادى تاني من مولّد الديمو بعد ما المصانع تتعمل.
 */
class UserSeeder extends Seeder
{
    public const PASSWORD = '123456';

    /**
     * [اسم الدخول, الاسم, المسمى الوظيفي, الأدوار, الإدارة (للتجميع في شاشة الدخول), كود المصنع]
     */
    public const USERS = [
        ['mostafa', 'مصطفى عبد الرحمن',     'مخطط إنتاج',               ['planner'],          'التخطيط',            null],
        ['yasmin',  'ياسمين حمدي',          'باترونست',                 ['patternist'],       'التخطيط',            null],

        ['ahmed',   'أحمد سمير',            'مسؤول مشتريات',            ['purchasing'],       'المشتريات والمالية', null],
        ['hesham',  'هشام الدسوقي',         'مدير المشتريات',           ['purchasing_mgr'],   'المشتريات والمالية', null],
        ['dina',    'دينا مجدي',            'محاسبة — الإدارة المالية', ['finance'],          'المشتريات والمالية', null],

        ['hazem',   'حازم فتحي',            'أمين مخزن العبور',         ['storekeeper'],      'المخازن',            null],
        ['mona',    'منى عبد العزيز',       'مراقب مخزون',              ['stock_controller'], 'المخازن',            null],

        ['ibrahim', 'إبراهيم رزق',          'فاحص قماش',                ['inspector'],        'الجودة',             null],
        ['nourhan', 'نورهان سعيد',          'فني معمل',                 ['lab_tech'],         'الجودة',             null],

        ['karim',   'كريم عادل',            'متابعة مصانع',             ['factory_follow'],   'الإنتاج',            null],
        ['mahmoud', 'محمود رجب',            'مسؤول إنتاج — مصنع سيني',  ['factory'],          'الإنتاج',            'SIN'],
        ['samir',   'سمير عبد الله',        'مسؤول إنتاج — مصنع النصر', ['factory'],          'الإنتاج',            'NSR'],

        ['tarek',   'طارق الشناوي',         'المدير العام',             ['gm'],               'الإدارة',            null],
        ['admin',   'مدير النظام',          'مدير النظام',              ['admin'],            'الإدارة',            null],
    ];

    public function run(): void
    {
        foreach (self::USERS as [$username, $name, $job, $roleKeys, $dept, $factoryCode]) {
            $user = User::updateOrCreate(['username' => $username], [
                'name'       => $name,
                'job_title'  => $job,
                'password'   => self::PASSWORD,
                'is_active'  => true,
                'factory_id' => $factoryCode ? self::factoryId($factoryCode) : null,
            ]);

            $user->roles()->sync(Role::whereIn('key', $roleKeys)->pluck('id'));
        }

        // حسابات الديمو القديمة (planner/moaz/store…) اتبدلت بالأسماء الجديدة — بتتشال
        $old = ['planner', 'moaz', 'hshmt', 'store', 'control', 'lab', 'qc', 'pattern', 'follow', 'finance', 'gm'];
        foreach (User::whereIn('username', $old)->whereNotIn('username', array_column(self::USERS, 0))->get() as $u) {
            $u->roles()->detach();
            $u->delete();
        }
    }

    /** أول مستخدم نشط بالدور ده — المولّدات بتستخدمه بدل اسم الدخول */
    public static function idByRole(string $roleKey): ?int
    {
        return User::where('is_active', true)
            ->whereHas('roles', fn ($q) => $q->where('key', $roleKey))
            ->orderBy('id')->value('id');
    }

    /** ربط مستخدمي المصانع بمصانعهم — بتتنادى بعد توليد البيانات الأساسية كمان */
    public static function linkFactories(): void
    {
        foreach (self::USERS as [$username, , , , , $factoryCode]) {
            if (!$factoryCode) continue;
            if ($id = self::factoryId($factoryCode)) {
                User::where('username', $username)->update(['factory_id' => $id]);
            }
        }
    }

    private static function factoryId(string $code): ?int
    {
        return Factory::where('code', $code)->value('id') ?? Factory::orderBy('id')->value('id');
    }

    /** مستخدمو الديمو مجمّعين بالإدارة — لشاشة الدخول السريع */
    public static function demoUsers(): \Illuminate\Support\Collection
    {
        $order = array_column(self::USERS, 0);
        $dept  = array_combine($order, array_column(self::USERS, 4));

        $users = User::with('roles')->whereIn('username', $order)->where('is_active', true)->get();

        // المصنع بيتحمّل بالـ factory_id مباشرة (علاقة employerFactory في الموديل بتدوّر على employer_factory_id)
        $factories = Factory::whereIn('id', $users->pluck('factory_id')->filter())->get()->keyBy('id');
        foreach ($users as $u) {
            $u->setRelation('employerFactory', $u->factory_id ? ($factories[$u->factory_id] ?? null) : null);
        }

        return $users
            ->sortBy(fn ($u) => array_search($u->username, $order, true))
            ->groupBy(fn ($u) => $dept[$u->username] ?? 'أخرى');
    }
}
