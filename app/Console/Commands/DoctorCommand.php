<?php

namespace App\Console\Commands;

use App\Models\ApprovalFlow;
use App\Models\Role;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * فحص شامل قبل ما تفتح السيستم — خصوصًا بعد الرفع على السيرفر.
 *
 * بيقولك بالظبط إيه اللي ناقص وإيه الأمر اللي يصلّحه، بدل ما تكتشف
 * المشكلة وأنت بتجرب قدام حد.
 *
 *   php artisan lv:doctor
 */
class DoctorCommand extends Command
{
    protected $signature = 'lv:doctor';
    protected $description = 'فحص شامل للسيستم: البيئة والداتابيز والصلاحيات والداتا';

    private array $problems = [];
    private array $warnings = [];

    public function handle(): int
    {
        $this->line('');
        $this->line('  <fg=magenta;options=bold>Le Voile — فحص السيستم</>');
        $this->line('  ' . str_repeat('─', 60));

        $this->section('البيئة',      fn () => $this->env());
        $this->section('الداتابيز',   fn () => $this->database());
        $this->section('السكيما',     fn () => $this->schema());
        $this->section('الملفات',     fn () => $this->storage());
        $this->section('الدخول',      fn () => $this->auth());
        $this->section('الداتا',      fn () => $this->data());

        $this->line('');
        if ($this->problems) {
            $this->line('  <fg=red;options=bold>مشاكل لازم تتحل (' . count($this->problems) . ')</>');
            foreach ($this->problems as $i => [$what, $fix]) {
                $this->line('   <fg=red>' . ($i + 1) . '. ' . $what . '</>');
                if ($fix) $this->line('      <fg=gray>الحل: ' . $fix . '</>');
            }
        }

        if ($this->warnings) {
            $this->line('');
            $this->line('  <fg=yellow;options=bold>تحذيرات (' . count($this->warnings) . ')</>');
            foreach ($this->warnings as $i => [$what, $fix]) {
                $this->line('   <fg=yellow>' . ($i + 1) . '. ' . $what . '</>');
                if ($fix) $this->line('      <fg=gray>' . $fix . '</>');
            }
        }

        $this->line('');
        if (!$this->problems) {
            $this->line('  <fg=green;options=bold>✔ السيستم جاهز.</>');
            $this->line('  <fg=gray>افتح ' . config('app.url') . ' وادخل بـ admin / 123456</>');
        }
        $this->line('');

        return $this->problems ? self::FAILURE : self::SUCCESS;
    }

    // ── الأقسام ──────────────────────────────────────────────────

    /** الأمر ده شغلته يشخّص التركيبات المكسورة — فممنوع يقع هو نفسه */
    private function section(string $name, callable $fn): void
    {
        $this->line('');
        $this->line('  <options=bold>' . $name . '</>');

        try {
            $fn();
        } catch (\Throwable $e) {
            $this->bad($name . ' — ' . $e->getMessage(), 'php artisan migrate --force');
        }
    }

    private function ok(string $m): void   { $this->line('   <fg=green>✔</> ' . $m); }
    private function bad(string $m, string $fix = ''): void
    {
        $this->line('   <fg=red>✘</> ' . $m);
        $this->problems[] = [$m, $fix];
    }
    /**
     * ملاحظة صفراء.
     * ⚠ الاسم مش warn() عن قصد — دي ميثود موجودة في Illuminate\Console\Command
     * وإعادة تعريفها بتوقيع مختلف بترمي خطأ ترجمة بيوقف كل أوامر artisan.
     */
    private function note(string $m, string $fix = ''): void
    {
        $this->line('   <fg=yellow>!</> ' . $m);
        $this->warnings[] = [$m, $fix];
    }

    // ── البيئة ───────────────────────────────────────────────────

    private function env(): void
    {
        PHP_VERSION_ID >= 80200
            ? $this->ok('PHP ' . PHP_VERSION)
            : $this->bad('PHP ' . PHP_VERSION . ' — المطلوب 8.2 على الأقل', 'غيّر نسخة PHP من لوحة الاستضافة');

        foreach (['pdo_mysql', 'mbstring', 'openssl', 'fileinfo', 'zip', 'gd', 'ctype', 'json'] as $ext) {
            extension_loaded($ext)
                ? $this->ok('امتداد ' . $ext)
                : $this->bad('امتداد ' . $ext . ' مش مفعّل', 'فعّله من لوحة الاستضافة (PHP Extensions)');
        }

        config('app.key')
            ? $this->ok('APP_KEY موجود')
            : $this->bad('APP_KEY فاضي — كل صفحة هترمي 500', 'php artisan key:generate');

        if (config('app.debug') && config('app.env') === 'production') {
            $this->note('APP_DEBUG=true على بيئة production',
                'خليه false — بيعرض تفاصيل السيرفر لأي زائر');
        }

        $url = (string) config('app.url');
        if (!$url || str_contains($url, 'localhost')) {
            $this->note('APP_URL = ' . ($url ?: 'فاضي'),
                'لازم يبقى العنوان الحقيقي، وإلا الصور والروابط هتتكسر');
        } else {
            $this->ok('APP_URL = ' . $url);
        }
    }

    // ── الداتابيز ────────────────────────────────────────────────

    private function database(): void
    {
        try {
            DB::connection()->getPdo();
            $this->ok('الاتصال بالداتابيز: ' . config('database.connections.mysql.database'));
        } catch (\Throwable $e) {
            $this->bad('مفيش اتصال بالداتابيز — ' . $e->getMessage(),
                'راجع DB_HOST و DB_DATABASE و DB_USERNAME و DB_PASSWORD في .env');
            return;
        }

        try {
            $pending = collect(app('migrator')->getMigrationFiles(database_path('migrations')))
                ->keys()
                ->diff(app('migrator')->getRepository()->getRan())
                ->count();

            $pending === 0
                ? $this->ok('كل الميجريشنات اتنفّذت')
                : $this->bad("فيه {$pending} ميجريشن لسه ما اتنفّذش", 'php artisan migrate --force');
        } catch (\Throwable $e) {
            $this->note('مقدرتش أتأكد من الميجريشنات: ' . $e->getMessage());
        }
    }

    // ── السكيما ──────────────────────────────────────────────────

    private function schema(): void
    {
        $required = [
            'users', 'roles', 'permissions', 'approval_flows', 'approvals',
            'suppliers', 'factories', 'warehouses', 'fabric_types', 'colors',
            'product_models', 'accessories', 'model_boms',
            'purchase_orders', 'purchase_order_lines',
            'consignments', 'fabric_rolls', 'stock_additions', 'stock_addition_lines',
            'goods_receipts', 'goods_receipt_lines', 'stock_movements',
            'fabric_inspections', 'inspection_rolls', 'lab_reports', 'lab_gsm_readings',
            'markers', 'marker_lines', 'marker_requests',
            'work_orders', 'work_order_lines', 'cut_declarations', 'production_receipts',
            'sales_snapshots', 'stock_snapshots', 'forecasts', 'document_comments',
        ];

        $missing = array_values(array_filter($required, fn ($t) => !Schema::hasTable($t)));
        $missing
            ? $this->bad('جداول ناقصة: ' . implode('، ', $missing), 'php artisan migrate --force')
            : $this->ok(count($required) . ' جدول موجودين');

        // الأعمدة اللي اتضافت بعد التطوير — أكتر مصدر للأخطاء
        $cols = [
            'consignments'       => ['hold_kg', 'released_kg', 'status'],
            'purchase_orders'    => ['stage', 'requested_by', 'sourced_by', 'finance_by', 'planning_note'],
            'stock_additions'    => ['purchase_order_id', 'total_rolls'],
            'stock_addition_lines' => ['rolls_count'],
            'goods_receipts'     => ['stock_addition_id', 'fabric_inspection_id'],
            'fabric_inspections' => ['declared_rolls', 'counted_rolls', 'rolls_variance', 'counted_kg'],
            'stock_movements'    => ['quality_state'],
        ];

        $bad = [];
        foreach ($cols as $table => $list) {
            if (!Schema::hasTable($table)) continue;
            foreach ($list as $c) {
                if (!Schema::hasColumn($table, $c)) $bad[] = "{$table}.{$c}";
            }
        }

        $bad
            ? $this->bad('أعمدة ناقصة: ' . implode('، ', $bad),
                'php artisan migrate --force  ← فيه ميجريشن ترقيعي بيضيفهم من غير ما يمسح داتا')
            : $this->ok('كل الأعمدة الجديدة موجودة');
    }

    // ── الملفات ──────────────────────────────────────────────────

    private function storage(): void
    {
        foreach ([
            storage_path('framework/sessions') => 'مجلد الجلسات',
            storage_path('framework/views')    => 'مجلد الفيوز المترجمة',
            storage_path('framework/cache')    => 'مجلد الكاش',
            storage_path('logs')               => 'مجلد اللوجات',
            storage_path('app/public')         => 'مجلد الملفات المرفوعة',
            base_path('bootstrap/cache')       => 'كاش الإقلاع',
        ] as $path => $label) {
            if (!is_dir($path)) {
                @mkdir($path, 0775, true);
            }
            is_writable($path)
                ? $this->ok($label . ' قابل للكتابة')
                : $this->bad($label . ' مش قابل للكتابة — ' . $path,
                    'chmod -R 775 storage bootstrap/cache');
        }

        is_link(public_path('storage')) || is_dir(public_path('storage'))
            ? $this->ok('رابط الملفات العامة موجود')
            : $this->bad('رابط public/storage مش موجود — الصور والمرفقات مش هتظهر',
                'php artisan storage:link');
    }

    // ── الدخول ───────────────────────────────────────────────────

    private function auth(): void
    {
        $secure = (bool) config('session.secure');
        $https  = str_starts_with((string) config('app.url'), 'https://');

        if ($secure && !$https) {
            $this->bad('SESSION_SECURE_COOKIE=true بس APP_URL مش https — الكوكي مش هيتبعت والدخول هيفشل بصمت',
                'خلي SESSION_SECURE_COOKIE=false في .env لحد ما الـ SSL يشتغل');
        } else {
            $this->ok('إعداد كوكي الجلسة متوافق مع البروتوكول');
        }

        $domain = config('session.domain');
        if ($domain) {
            $host = parse_url((string) config('app.url'), PHP_URL_HOST) ?: '';
            str_ends_with($host, ltrim((string) $domain, '.'))
                ? $this->ok('SESSION_DOMAIN متوافق مع APP_URL')
                : $this->bad("SESSION_DOMAIN={$domain} مش متوافق مع الدومين {$host} — الدخول هيفشل بصمت",
                    'شيل SESSION_DOMAIN من .env أو خليه مطابق للدومين');
        } else {
            $this->ok('SESSION_DOMAIN مش محدد (الافتراضي — أأمن)');
        }

        if (config('session.driver') === 'database' && !Schema::hasTable('sessions')) {
            $this->bad('SESSION_DRIVER=database بس جدول sessions مش موجود', 'php artisan migrate --force');
        }
    }

    // ── الداتا ───────────────────────────────────────────────────

    private function data(): void
    {
        if (!Schema::hasTable('users')) return;

        $users = User::count();
        $users > 0
            ? $this->ok($users . ' مستخدم')
            : $this->bad('مفيش مستخدمين — مش هتقدر تدخل',
                'php artisan db:seed --class=Database\\\\Seeders\\\\UserSeeder --force');

        $admin = User::whereHas('roles', fn ($q) => $q->where('key', 'admin'))->where('is_active', true)->first();
        $admin
            ? $this->ok('مدير نظام نشط: ' . $admin->username)
            : $this->bad('مفيش مدير نظام نشط',
                'php artisan db:seed --class=Database\\\\Seeders\\\\RolePermissionSeeder --force');

        Role::count() > 0
            ? $this->ok(Role::count() . ' دور معرّف')
            : $this->bad('مفيش أدوار', 'php artisan db:seed --class=Database\\\\Seeders\\\\RolePermissionSeeder --force');

        ApprovalFlow::count() > 0
            ? $this->ok(ApprovalFlow::count() . ' دورة اعتماد')
            : $this->note('مفيش دورات اعتماد — المستندات هتتعمد مباشرة',
                'php artisan db:seed --class=Database\\\\Seeders\\\\ApprovalFlowSeeder --force');

        if (Schema::hasTable('consignments')) {
            $n = DB::table('consignments')->count();
            $n > 0
                ? $this->ok($n . ' حوض في السيستم')
                : $this->note('مفيش داتا شغل', 'php artisan lv:demo  ← يولّد داتا ديمو كاملة');
        }

        // حالات مش معروفة — مؤشر على داتابيز قديمة
        if (Schema::hasTable('consignments')) {
            $valid = array_keys(\App\Models\Consignment::STATUSES);
            $odd = DB::table('consignments')->whereNotIn('status', $valid)->count();
            $odd > 0
                ? $this->bad("{$odd} حوض بحالة قديمة مش معروفة", 'php artisan migrate --force')
                : $this->ok('حالات الأحواض سليمة');
        }
    }
}
