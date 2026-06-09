# گزارش امنیتی افزونه MD Custom Comments

**تاریخ بررسی:** ۲۰۲۶-۰۵-۲۰  
**نسخه:** ۱.۰.۰  

---

## خلاصه

| سطح | تعداد |
|-----|-------|
| بحرانی (Critical) | ۲ |
| بالا (High) | ۲ |
| متوسط (Medium) | ۳ |
| پایین (Low) | ۴ |

---

## ⛔ بحرانی (Critical)

### C-1: عدم بررسی اعتبار SSL در درخواست‌های خروجی

**فایل‌ها:**
- `src/Events/Listeners/SendBale.php:56`
- `src/Admin/Controllers/SettingsController.php:170`
- `src/Admin/Controllers/SettingsController.php:215`

**توضیح:** در تمام درخواست‌های خروجی به API بله و تلگرام، پارامتر `sslverify` برابر `false` تنظیم شده است. این یعنی اتصال SSL/TLS تأیید اعتبار نمی‌شود.

**خطر:** مهاجم در شبکه محلی (مثلاً شبکه وای‌فای عمومی یا هاست اشتراکی) می‌تواند حمله Man-in-the-Middle انجام داده و درخواست‌های حاوی توکن ربات را شنود یا تغییر دهد.

**راه‌حل:** در محیط تولید، `sslverify` را به `true` تغییر دهید:
```php
'sslverify' => true,
```

---

### C-2: جعل IP از طریق هدرهای HTTP

**فایل:** `src/Security/Validator.php:77-83`

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

### H-1: عدم بررسی Nonce در دریافت لیست نظرات

**فایل:** `src/Admin/Controllers/SettingsController.php:119-134`

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

### H-2: عدم محدودیت طول ورودی

**فایل:** `src/Security/Validator.php:23-39`

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

### M-1: تابع کمکی در فضای نام عمومی

**فایل:** `src/Events/Listeners/SendTelegram.php:60-62`

**توضیح:** تابع `function_class_exists_as_scheduled()` در فضای نام عمومی (Global Namespace) تعریف شده است.

**خطر:** احتمال تداخل با نام تابع مشابه در افزونه یا قالب دیگر.

**راه‌حل:** نام تابع را با پیشوند `md_` شروع کنید یا تابع را به یک متد استاتیک در یک کلاس تبدیل کنید:

```php
if ( function_exists( 'as_enqueue_async_action' ) ) { ... }
```

(کافی است از `function_exists` مستقیماً استفاده شود، نیازی به تابع کمکی نیست)

---

### M-2: افشای توکن ربات در URL

**فایل:** `src/Events/Listeners/SendBale.php:45`

**توضیح:** توکن ربات مستقیماً در URL قرار می‌گیرد:
```php
$url = "https://tapi.bale.ai/bot" . $bot_token . "/sendMessage";
```

**خطر:** توکن در سرور لاگ‌های Apache/Nginx ثبت می‌شود. اگر سرور لاگ‌ها به بیرون نشت کنند، توکن ربات فاش می‌شود.

**راه‌حل:** این روش استاندارد API بله/تلگرام است و نمی‌توان از هدر Bearer استفاده کرد. برای کاهش ریسک:
- لاگ‌های سرور را پاکسازی (sanitize) کنید
- از متغیرهای محیطی (Environment Variables) برای ذخیره توکن استفاده کنید

---

### M-3: استفاده از Action Scheduler وابستگی خارجی

**فایل:** `src/Events/Listeners/SendBale.php:32`

**توضیح:** کد فرض می‌کند `as_enqueue_async_action()` ممکن است در دسترس باشد یا نباشد. این تابع تنها در صورت نصب WooCommerce یا افزونه Action Scheduler وجود دارد.

**خطر:** اگر Action Scheduler نصب نباشد، ارسال پیام به صورت همزمان (Synchronous) انجام می‌شود که ممکن است باعث کندی در ثبت نظر شود.

**راه‌حل:** در مستندات ذکر شود که نصب Action Scheduler توصیه می‌شود، یا از `wp_schedule_single_event()` وردپرس استفاده کنید.

---

## 🔍 پایین (Low)

### L-1: نام فیلد Honeypot قابل حدس

**فایل:** `src/Security/Validator.php:17`, `assets/front/front.js:117`

**توضیح:** نام فیلد مخفی `md_hp_website` ثابت و قابل پیش‌بینی است.

**خطر:** ربات‌های پیشرفته می‌توانند این فیلد را شناسایی کرده و از آن عبور کنند.

**راه‌حل:** نام فیلد را پویا کنید (مثلاً یک هش از آیدی پست یا تایم‌استمپ).

---

### L-2: `error_log()` بدون مدیریت

**فایل:** `src/Events/Listeners/SendBale.php:60`

**توضیح:** خطاهای API مستقیماً با `error_log()` نوشته می‌شوند.

**خطر:** اطلاعات API و توکن‌ها ممکن است در لاگ‌های خطا ثبت شوند.

**راه‌حل:** از کلاس `Logger` موجود در افزونه استفاده کنید و توکن را از پیام خطا حذف کنید.

---

### L-3: پاکسازی کش بدون تأیید کاربر

**فایل:** `src/Admin/Controllers/SettingsController.php:84-96`

**توضیح:** با هر بار ذخیره تنظیمات، کش چهار افزونه مختلف پاک می‌شود (W3TC, WP Super Cache, WP Rocket, LiteSpeed).

**خطر:** افت عملکرد لحظه‌ای سایت در زمان بازدید بالا.

**راه‌حل:** پاک‌سازی کش را فقط در صورت تغییر تنظیمات مرتبط با ظاهر انجام دهید.

---

### L-4: عدم بررسی CSRF در متد GET نظرات (تکمیلی H-1)

**فایل:** `src/Admin/Controllers/SettingsController.php:119`

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

## اولویت‌بندی رفع مشکلات

### فوری (باید رفع شود)
1. **C-1** — غیرفعال کردن `sslverify => false` در محیط تولید
2. **C-2** — اصلاح متد `get_ip_address()` با `FILTER_VALIDATE_IP`

### مهم (توصیه می‌شود رفع شود)
3. **H-1** — افزودن nonce به `get_admin_comments_ajax()`
4. **H-2** — افزودن محدودیت طول به `name` و `comment`

### فرعی (رفع در نسخه‌های بعدی)
5. **M-1** — حذف تابع `function_class_exists_as_scheduled`
6. **M-2** — مستندسازی درباره لاگ‌های سرور
7. **M-3** — استفاده از Cron وردپرس به جای Action Scheduler
8. **L-1** — پویا کردن نام فیلد Honeypot
9. **L-2** — استفاده از Logger به جای `error_log()`
10. **L-3** — پاک‌سازی هوشمند کش
11. **L-4** — تغییر GET به POST برای نظرات
