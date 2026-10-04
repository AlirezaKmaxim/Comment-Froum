# گزارش امنیتی افزونه MD Custom Comments

**تاریخ بررسی:** ۲۰۲۶-۰۵-۲۰
**نسخه:** ۱.۰.۰
**آخرین بازبینی وضعیت:** ۱۴۰۵/۰۶/۱۱ (۲۰۲۶-۰۹-۰۲) — به‌همراه `docs/improve.md`

> ⚠️ **این گزارش نسبت به کد فعلی قدیمی بود** (نسخه بررسی‌شده ۱.۰.۰ بود؛ نسخه فعلی ۱.۱.۱). در بازبینی اخیر مشخص شد اکثر موارد **از قبل در کد رفع شده بودند** ولی این سند به‌روز نشده بود. وضعیت هر مورد به‌صورت جداگانه در همان بخش با یک نقل‌قول (`> **وضعیت:** ...`) علامت‌گذاری شده. برای جزئیات بیشتر و سایر یافته‌های غیرامنیتی (باگ/کارایی/بدهی فنی) به `docs/improve.md` مراجعه کنید.

---

## خلاصه

| سطح | تعداد کل | باز مانده | رفع شده |
|-----|---------|-----------|---------|
| بحرانی (Critical) | ۲ | ۰ | ۲ |
| بالا (High) | ۲ | ۰ | ۲ |
| متوسط (Medium) | ۳ | ۱ (M-2 ذاتی API است) | ۲ |
| پایین (Low) | ۴ | ۱ (L-3) | ۳ |

---

## ⛔ بحرانی (Critical)

### ✅ C-1 (رفع شده): عدم بررسی اعتبار SSL در درخواست‌های خروجی

> **وضعیت:** **کاملاً رفع شده.** درخواست‌های تلگرام، اسلک و اکنون بله هم دیگر `sslverify => false` ندارند (پیش‌فرض `wp_remote_post` یعنی `true` در همه‌جا رعایت می‌شود). دو نقطه‌ی باقی‌مانده (`SendBale::send_request` و `test_bale_connection_ajax`) هم اصلاح شدند — این مورد به‌عنوان **B-7** هم در `docs/improve.md` ثبت شده بود.

**فایل‌ها:**
- `src/Events/Listeners/SendBale.php` (متد `send_request`)
- `src/Admin/Controllers/SettingsController.php` (متد `test_bale_connection_ajax`)

**توضیح:** در درخواست‌های خروجی به API بله، پارامتر `sslverify` برابر `false` تنظیم شده است. این یعنی اتصال SSL/TLS تأیید اعتبار نمی‌شود.

**خطر:** مهاجم در شبکه محلی (مثلاً شبکه وای‌فای عمومی یا هاست اشتراکی) می‌تواند حمله Man-in-the-Middle انجام داده و درخواست‌های حاوی توکن ربات را شنود یا تغییر دهد.

**راه‌حل:** در محیط تولید، `sslverify` را به `true` تغییر دهید:
```php
'sslverify' => true,
```

---

### ✅ C-2 (رفع شده): جعل IP از طریق هدرهای HTTP

> **وضعیت:** رفع شده. `Validator::get_ip_address()` دقیقاً همان راه‌حل پیشنهادی زیر را پیاده‌سازی کرده: `HTTP_X_FORWARDED_FOR` را با `explode(',')` می‌شکند و اولین مقدار را می‌گیرد، و نتیجه نهایی را با `filter_var($ip, FILTER_VALIDATE_IP)` اعتبارسنجی می‌کند (در غیر این صورت به `REMOTE_ADDR` برمی‌گردد).

**فایل:** `src/Security/Validator.php`

**توضیح:** متد `get_ip_address()` مقادیر `HTTP_CLIENT_IP` و `HTTP_X_FORWARDED_FOR` را بدون هیچ اعتبارسنجی می‌پذیرد. این هدرها به راحتی توسط مهاجم جعل می‌شوند.

**خطر:** مهاجم می‌تواند IP دلخواه خود را در هدر HTTP قرار دهد و IP واقعی خود را مخفی کند. `HTTP_X_FORWARDED_FOR` می‌تواند چندین IP به صورت کاما-مجزا داشته باشد.

**راه‌حل:** از متد `WP_Http::get_ip()` وردپرس یا کتابخانه `wc_clean()` ووکامرس استفاده کنید. یا حداقل `HTTP_X_FORWARDED_FOR` را با `explode` پردازش کرده و اولین IP معتبر را استخراج کنید:

```php
private static function get_ip_address() {
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    
    if ( ! empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
        $ips = explode( ',', $_SERVER['HTTP_X_FORWARDED_FOR'] );
        $ip = trim( $ips[0] );
    } elseif ( ! empty( $_SERVER['HTTP_CLIENT_IP'] ) ) {
        $ip = $_SERVER['HTTP_CLIENT_IP'];
    }
    
    return filter_var( $ip, FILTER_VALIDATE_IP ) ? $ip : $_SERVER['REMOTE_ADDR'];
}
```

---

## ⚠️ بالا (High)

### ✅ H-1 (رفع شده): عدم بررسی Nonce در دریافت لیست نظرات

> **وضعیت:** رفع شده. `get_admin_comments_ajax()` هم `current_user_can('manage_options')` و هم `wp_verify_nonce($_POST['md_nonce'], 'md_comments_settings_action')` را چک می‌کند، و سمت جاوااسکریپت (`fetchComments()` در `render_settings_page`) با `fetch(ajaxurl, {method:'POST', body: formData})` و `md_nonce` در body درخواست می‌فرستد (نه GET). همین رفع، L-4 را هم پوشش می‌دهد.

**فایل:** `src/Admin/Controllers/SettingsController.php`

**توضیح:** متد `get_admin_comments_ajax()` تنها `current_user_can('manage_options')` را بررسی می‌کند اما توکن امنیتی (nonce) را تأیید نمی‌کند. همچنین این endpoint از متد GET استفاده می‌کند.

**خطر:** اگر ادمین سایت به لینک مخربی هدایت شود که آدرس `admin-ajax.php?action=md_get_admin_comments` را بارگذاری کند، اطلاعات نظرات (شامل نام، شماره تماس، IP کاربران) فاش می‌شود (CSRF).

**راه‌حل:** از POST به جای GET استفاده کرده و nonce را بررسی کنید:

```php
public function get_admin_comments_ajax() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( 'دسترسی غیرمجاز.' );
    }
    if ( ! isset( $_REQUEST['md_nonce'] ) || ! wp_verify_nonce( $_REQUEST['md_nonce'], 'md_comments_settings_action' ) ) {
        wp_send_json_error( 'توکن امنیتی نامعتبر است.' );
    }
    // ...
}
```

---

### ✅ H-2 (رفع شده): عدم محدودیت طول ورودی

> **وضعیت:** رفع شده. `Validator::validate_comment_submission()` روی `name` با `mb_substr($name, 0, 100)` و روی `comment` با `mb_substr($comment, 0, 5000)` محدودیت طول اعمال می‌کند — دقیقاً راه‌حل پیشنهادی زیر.

**فایل:** `src/Security/Validator.php`

**توضیح:** فیلدهای `name` و `comment` محدودیت طول ندارند. مهاجم می‌تواند رشته‌های بسیار طولانی (مثلاً ۱ میلیون کاراکتر) ارسال کند.

**خطر:** حمله DoS. اشغال حافظه و پردازش سرور، پر شدن دیتابیس با داده‌های حجیم.

**راه‌حل:** محدودیت طول اضافه کنید:

```php
$name = isset( $_POST['name'] ) ? sanitize_text_field( $_POST['name'] ) : '';
$name = mb_substr( $name, 0, 100 ); // حداکثر ۱۰۰ کاراکتر
if ( empty( $name ) ) {
    wp_send_json_error( 'لطفاً نام و نام خانوادگی خود را وارد کنید.' );
}
```

```php
$comment = isset( $_POST['comment'] ) ? sanitize_textarea_field( $_POST['comment'] ) : '';
$comment = mb_substr( $comment, 0, 5000 ); // حداکثر ۵۰۰۰ کاراکتر
```

---

## ⚡ متوسط (Medium)

### ✅ M-1 (رفع شده): تابع کمکی در فضای نام عمومی

> **وضعیت:** رفع شده. در کد فعلی `SendTelegram.php` مستقیماً از `function_exists('as_enqueue_async_action')` استفاده می‌شود (بدون تابع کمکی جداگانه در فضای نام عمومی).

**فایل:** `src/Events/Listeners/SendTelegram.php`

**توضیح:** تابع `function_class_exists_as_scheduled()` در فضای نام عمومی (Global Namespace) تعریف شده است.

**خطر:** احتمال تداخل با نام تابع مشابه در افزونه یا قالب دیگر.

**راه‌حل:** نام تابع را با پیشوند `md_` شروع کنید یا تابع را به یک متد استاتیک در یک کلاس تبدیل کنید:

```php
if ( function_exists( 'as_enqueue_async_action' ) ) { ... }
```

(کافی است از `function_exists` مستقیماً استفاده شود، نیازی به تابع کمکی نیست)

---

### M-2 (بدون تغییر — ذاتی طراحی API بله): افشای توکن ربات در URL

> **وضعیت:** این مورد به‌خودی‌خود همان‌طور که خود گزارش هم اشاره کرده «روش استاندارد API بله» است و راه‌حل جایگزینی (هدر Bearer) وجود ندارد؛ عملاً باز می‌ماند. اما ریسک جانبی آن — افشای توکن در لاگ خطا (L-2) — رفع شده: در `SendBale::send_request()` قبل از لاگ کردن خطا با `str_replace($bot_token, '[REDACTED_BOT_TOKEN]', $err_msg)` توکن حذف می‌شود.

**فایل:** `src/Events/Listeners/SendBale.php`

**توضیح:** توکن ربات مستقیماً در URL قرار می‌گیرد:
```php
$url = "https://tapi.bale.ai/bot" . $bot_token . "/sendMessage";
```

**خطر:** توکن در سرور لاگ‌های Apache/Nginx ثبت می‌شود. اگر سرور لاگ‌ها به بیرون نشت کنند، توکن ربات فاش می‌شود.

**راه‌حل:** این روش استاندارد API بله/تلگرام است و نمی‌توان از هدر Bearer استفاده کرد. برای کاهش ریسک:
- لاگ‌های سرور را پاکسازی (sanitize) کنید
- از متغیرهای محیطی (Environment Variables) برای ذخیره توکن استفاده کنید

---

### ✅ M-3 (رفع شده): استفاده از Action Scheduler وابستگی خارجی

> **وضعیت:** رفع شده. هر سه Listener (`SendTelegram`, `SendBale`, `SendSlack`) اگر `as_enqueue_async_action` در دسترس نباشد، با `wp_schedule_single_event()` وردپرس (بدون نیاز به Action Scheduler/ووکامرس) به‌صورت غیرهمزمان fallback می‌کنند.

**فایل:** `src/Events/Listeners/SendBale.php`

**توضیح:** کد فرض می‌کند `as_enqueue_async_action()` ممکن است در دسترس باشد یا نباشد. این تابع تنها در صورت نصب WooCommerce یا افزونه Action Scheduler وجود دارد.

**خطر:** اگر Action Scheduler نصب نباشد، ارسال پیام به صورت همزمان (Synchronous) انجام می‌شود که ممکن است باعث کندی در ثبت نظر شود.

**راه‌حل:** در مستندات ذکر شود که نصب Action Scheduler توصیه می‌شود، یا از `wp_schedule_single_event()` وردپرس استفاده کنید.

---

## 🔍 پایین (Low)

### ✅ L-1 (رفع شده): نام فیلد Honeypot قابل حدس

> **وضعیت:** رفع شده. نام فیلد اکنون پویاست: `'md_hp_' . substr(md5($post_id . NONCE_KEY), 0, 10)` — هم در `Validator::validate_comment_submission()` و هم در `View::render()` (که مقدار را برای فرم رندر می‌کند)؛ `front.js` هم نام واقعی فیلد را از DOM می‌خواند، نه یک مقدار ثابت.

**فایل:** `src/Security/Validator.php`, `assets/front/front.js`

**توضیح:** نام فیلد مخفی `md_hp_website` ثابت و قابل پیش‌بینی است.

**خطر:** ربات‌های پیشرفته می‌توانند این فیلد را شناسایی کرده و از آن عبور کنند.

**راه‌حل:** نام فیلد را پویا کنید (مثلاً یک هش از آیدی پست یا تایم‌استمپ).

---

### ✅ L-2 (رفع شده): `error_log()` بدون مدیریت

> **وضعیت:** رفع شده. هر سه Listener از `\MDCustomComments\Core\Logger::error()` استفاده می‌کنند (نه `error_log()` مستقیم)، و پیام خطا قبل از لاگ شدن از توکن/URL وب‌هوک پاک‌سازی (redact) می‌شود.

**فایل:** `src/Events/Listeners/SendBale.php`

**توضیح:** خطاهای API مستقیماً با `error_log()` نوشته می‌شوند.

**خطر:** اطلاعات API و توکن‌ها ممکن است در لاگ‌های خطا ثبت شوند.

**راه‌حل:** از کلاس `Logger` موجود در افزونه استفاده کنید و توکن را از پیام خطا حذف کنید.

---

### 🔸 L-3 (هنوز باز): پاکسازی کش بدون تأیید کاربر

> **وضعیت:** بررسی شد و هنوز باز است. کد فعلی از قبل شرط "فقط اگر کلیدهای مرتبط با ظاهر تغییر کرده باشند" را دارد (`$flush_cache` بر اساس مقایسه `$appearance_keys`) که ریسک را کمی کاهش می‌دهد، ولی همچنان بدون تایید صریح کاربر، پاک‌سازی کش چهار افزونه مختلف را انجام می‌دهد.

**فایل:** `src/Admin/Controllers/SettingsController.php` (متد `save_settings_ajax`)

**توضیح:** با هر بار ذخیره تنظیمات، کش چهار افزونه مختلف پاک می‌شود (W3TC, WP Super Cache, WP Rocket, LiteSpeed).

**خطر:** افت عملکرد لحظه‌ای سایت در زمان بازدید بالا.

**راه‌حل:** پاک‌سازی کش را فقط در صورت تغییر تنظیمات مرتبط با ظاهر انجام دهید.

---

### ✅ L-4 (رفع شده): عدم بررسی CSRF در متد GET نظرات (تکمیلی H-1)

> **وضعیت:** رفع شده — همراه با H-1 (بالا). درخواست اکنون از `fetch(ajaxurl, {method:'POST', ...})` استفاده می‌کند، نه `?action=...` روی GET.

**فایل:** `src/Admin/Controllers/SettingsController.php`

**توضیح:** درخواست با `fetch(ajaxurl + '?action=md_get_admin_comments')` از متد GET استفاده می‌کند.

**خطر:** لاگ‌های سرور شامل شناسه و نام کاربران می‌شوند.

**راه‌حل:** به POST تغییر دهید.

---

## ✅ وضعیت مطلوب (بدون مشکل)

| بخش | وضعیت | توضیح |
|-----|-------|--------|
| SQL Injection | ✅ ایمن | تمام کوئری‌ها با `$wpdb->prepare()` |
| XSS | ✅ ایمن | تمام خروجی‌ها با `esc_html` / `esc_attr` / `esc_url` |
| دسترسی مستقیم به فایل | ✅ ایمن | تمام فایل‌ها `defined('ABSPATH') || exit;` |
| CSRF در ذخیره نظرات | ✅ ایمن | nonce در ثبت نظر |
| CSRF در تنظیمات | ✅ ایمن | nonce در ذخیره تنظیمات |
| دسترسی ادمین | ✅ ایمن | `manage_options` در همه endpoint‌های ادمین |
| تزریق HTML در آواتار | ✅ ایمن | `esc_url()` برای src و `esc_attr()` برای کلاس |
| رمز عبور | ✅ N/A | افزونه رمز عبور ذخیره نمی‌کند |

---

## اولویت‌بندی رفع مشکلات (به‌روزشده — ۱۴۰۵/۰۶/۱۱)

### هنوز باز — باید رفع شود
1. **L-3** — پاک‌سازی هوشمندتر کش (فقط با تایید صریح کاربر یا محدودتر کردن شرط فعلی)

### رفع شده (تایید شده در بازبینی فعلی)
- ~~C-1 (کامل — تلگرام/اسلک/بله)~~ — `sslverify` دیگر هیچ‌جا `false` ست نمی‌شود، پیش‌فرض `true` رعایت می‌شود
- ~~C-2~~ — `get_ip_address()` با `FILTER_VALIDATE_IP`
- ~~H-1~~ — nonce در `get_admin_comments_ajax()`
- ~~H-2~~ — محدودیت طول `name`/`comment` با `mb_substr`
- ~~M-1~~ — حذف تابع کمکی global، استفاده مستقیم از `function_exists`
- ~~M-3~~ — fallback با `wp_schedule_single_event()`
- ~~L-1~~ — نام پویای فیلد Honeypot
- ~~L-2~~ — استفاده از `Logger::error()` + redact کردن توکن
- ~~L-4~~ — تغییر GET به POST (همراه با H-1)

### باقی‌مانده ذاتی (غیرقابل‌رفع کامل)
- **M-2** — افشای توکن در URL بله؛ محدودیت API خود بله است (ریسک جانبی لاگ سرور با L-2 کاهش یافته)

> برای باگ‌های فانکشنال، گلوگاه‌های کارایی و بدهی فنی (خارج از حوزه امنیت) به `docs/improve.md` مراجعه کنید.
