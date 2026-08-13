# الرفع والتشغيل — Le Voile

## الوضع الحالي (اقرأ ده الأول)

الإيرور اللي ظهر لك:

```
Unknown column 'hold_kg' in 'field list'
Database\Seeders\MasterDataSeeder ... DemoFlowSeeder ...
```

سببه حاجتين مع بعض:

1. **ملفات قديمة على السيرفر** — `DatabaseSeeder` لسه بينده `MasterDataSeeder`
   و `DemoFlowSeeder`، وهما اتشالوا واندمجوا في `DemoDataService`.
2. **الداتابيز اتعملها migrate قبل تعديل السكيما** — Laravel مش بيعيد تشغيل
   ميجريشن اتسجّل قبل كده، فالأعمدة الجديدة (`hold_kg`, `stage`, `rolls_count`…)
   مش موجودة.

الاتنين اتحلوا: فيه دلوقتي **ميجريشن ترقيعي** بيضيف أي عمود ناقص من غير ما
يمسح داتا، وأمر **`lv:doctor`** بيقولك إيه اللي لسه ناقص.

---

## الخطوات على السيرفر

### ① ارفع الملفات

ارفع المشروع كله **ما عدا**: `.env` · `vendor/` · `node_modules/` · `storage/logs/*`

> مهم: اتأكد إن `database/seeders/DatabaseSeeder.php` اترفع بالنسخة الجديدة.
> لو لسه بينده `MasterDataSeeder` يبقى الرفع ما اكتملش.

### ② الإعدادات

```bash
cp .envlive .env
php artisan key:generate
```

### ③ الحزم

```bash
composer install --no-dev --optimize-autoloader
```

### ④ الداتابيز

**لو الداتا اللي عندك مش مهمة (الحالة دي):**
```bash
php artisan migrate:fresh --force

# اختار واحدة:
php artisan lv:demo      # ديمو كامل — للتجربة والفهم
php artisan lv:master    # بيانات أساسية بس — للبدء بشغل حقيقي
```
> `migrate:fresh --seed` بيعمل الأدوار والمستخدمين + **الديمو الكامل**.
> لو عايز تبدأ نضيف، شغّل `migrate:fresh --seed --force` وبعدها `php artisan lv:master`.

**لو عايز تحافظ على الداتا:**
```bash
php artisan migrate --force
```
الميجريشن الترقيعي هيضيف الأعمدة الناقصة ويحوّل الحالات القديمة للجديدة.

### ⑤ الملفات والصلاحيات

```bash
php artisan storage:link
chmod -R 775 storage bootstrap/cache
```

### ⑥ الفحص — الخطوة اللي بتمنع المفاجآت

```bash
php artisan lv:doctor
```

بيفحص: نسخة PHP والامتدادات، الـ APP_KEY، الاتصال بالداتابيز، الميجريشنات
المعلّقة، الجداول والأعمدة الناقصة، صلاحيات المجلدات، رابط `public/storage`،
**إعدادات كوكي الجلسة** (أشهر سبب لفشل الدخول)، والمستخدمين والأدوار.

كل سطر أحمر معاه الأمر اللي بيصلّحه. متكملش قبل ما كله يبقى أخضر.

### ⑦ الكاش (آخر خطوة)

```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

> لو عدّلت `.env` بعد كده، لازم `php artisan config:clear` وإلا التعديل مش هيتقرأ.

---

## صفحة خطأ متكررة: `Call to undefined function highlight_file()`

الاستضافة (Cloudways من ضمنها) قافلة دالة `highlight_file` في `disable_functions`.
صفحة الخطأ التفصيلية بتاعة Symfony بتستدعيها، فبتقع هي كمان وبتعمل تكرار لا نهائي —
**والخطأ الحقيقي بيتخبّى وراها**.

### الخطوة الأولى: شوف الخطأ الحقيقي

```bash
cd /home/1216096.cloudwaysapps.com/sjqdrzbznj/public_html
tail -60 storage/logs/laravel.log
```

### الخطوة التانية: أوقف الصفحة دي

```bash
# اتأكد إن APP_DEBUG=false
grep APP_DEBUG .env

# لو كانت true غيّرها لـ false، وبعدين — مهم جدًا:
php artisan config:clear
php artisan cache:clear
php artisan view:clear
```

> لو عملت `config:cache` قبل كده وإنت حاطط `APP_DEBUG=true`، تغيير `.env` لوحده
> **مش هيعمل حاجة**. لازم `config:clear`.

السيستم دلوقتي فيه صفحة خطأ بديلة بتشتغل تلقائيًا في الحالة دي وبتعرض الخطأ
الحقيقي ومكانه بدل التكرار.

### دوال تانية بتتقفل على Cloudways

| الدالة | التأثير | الحل |
|---|---|---|
| `highlight_file` | صفحة الخطأ التفصيلية بتقع | اتحل تلقائيًا — والأفضل `APP_DEBUG=false` |
| `symlink` | `php artisan storage:link` بيفشل | `cp -r storage/app/public public/storage` |
| `set_time_limit` | توليد الداتا ممكن يقطع | استخدم `php artisan lv:demo` من التيرمنال بدل الزرار |

`php artisan lv:doctor` بيفحص الدوال دي كلها ويقولك أي واحدة مقفولة.

---

## «الدخول مش راضي يشتغل»

الترتيب ده بيحل 95% من الحالات:

1. **`SESSION_SECURE_COOKIE=true` والموقع بيفتح بـ http**
   الكوكي مش بيتبعت أصلًا، فالصفحة بترجعك للوجين من غير رسالة.
   → خليها `false` في `.env`، وبعدين `php artisan config:clear`.

2. **`SESSION_DOMAIN` بدومين مختلف عن اللي بتفتح بيه**
   نفس النتيجة بالظبط. → علّق السطر.

3. **`storage/framework/sessions` مش قابل للكتابة**
   → `chmod -R 775 storage`

4. **`APP_KEY` فاضي** → `php artisan key:generate`

5. **الكاش قديم بعد تعديل .env** → `php artisan config:clear`

`php artisan lv:doctor` بيفحص الخمسة دول كلهم.

---

## Document Root

لازم يشاور على مجلد **`public`** — مش على جذر المشروع.
لو مش قادر تغيّره من لوحة الاستضافة، حط في جذر المشروع `.htaccess`:

```apache
<IfModule mod_rewrite.c>
    RewriteEngine On
    RewriteRule ^(.*)$ public/$1 [L]
</IfModule>
```

---

## بعد ما يشتغل

| الأمر | بيعمل إيه |
|---|---|
| `php artisan lv:doctor` | فحص شامل — شغّله بعد أي تعديل |
| `php artisan lv:demo` | ديمو كامل — السيستم وهو شغّال بكل مستنداته |
| `php artisan lv:master` | بيانات أساسية بس (بدون مستندات) — للبدء بشغل حقيقي |
| `php artisan lv:reset` | يمسح كل بيانات الشغل ويسيب المستخدمين والأدوار |

نفس الزرارين موجودين في **الإعدادات ← أدوات الداتا** (للأدمن بس).

**الدخول:** `admin / 123456` — غيّر الباسورد فورًا من الإعدادات ← المستخدمين.

---

## تحديث لاحق

```bash
# ارفع الملفات المعدّلة، وبعدين:
php artisan migrate --force
php artisan config:clear && php artisan route:clear && php artisan view:clear
php artisan config:cache && php artisan route:cache && php artisan view:cache
php artisan lv:doctor
```
