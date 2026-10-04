# معماری افزونه MD Custom Comments — راهنمای توسعه و نگهداری

## نمای کلی

```
Namespace:    MDCustomComments\
Prefix:       md_comments_ / md_
DB Version:   1.0.0
Text Domain:  md-custom-comments
```

---

## ساختار دایرکتوری

```
md-custom-comments/
├── md-custom-comments.php          # Bootstrap (ورودی اصلی)
├── package.json                     # وابستگی‌های Node (Tailwind CLI)
├── tailwind.config.js               # تنظیمات Tailwind
├── src/
│   ├── input.css                    # سورس Tailwind (برای بیلد)
│   ├── Core/
│   │   ├── Plugin.php               # هسته مرکزی + DI container
│   │   ├── Activator.php            # هوک فعال‌سازی
│   │   ├── Installer.php            # ایجاد جدول دیتابیس + تنظیمات پیش‌فرض
│   │   └── Logger.php               # سیستم لاگ فایلی
│   ├── Front/
│   │   ├── Shortcode.php            # ثبت شورت‌کد [md_comments]
│   │   ├── View.php                 # رندر HTML فرم + لیست نظرات
│   │   ├── Controller.php           # هندلر AJAX ثبت نظر
│   │   └── Assets.php               # لود شرطی CSS/JS فرانت
│   ├── Admin/
│   │   ├── Assets.php               # لود شرطی CSS/JS ادمین
│   │   ├── Controllers/
│   │   │   └── SettingsController.php  # منطق AJAX + آماده‌سازی متغیرها (بدون HTML)
│   │   └── Views/
│   │       └── settings-page.php    # قالب HTML/Alpine.js صفحه تنظیمات (include شده)
│   ├── Database/
│   │   └── CommentRepository.php    # تمام کوئری‌های دیتابیس
│   ├── Security/
│   │   └── Validator.php            # اعتبارسنجی ورودی (Zero-Trust)
│   ├── Support/
│   │   ├── AvatarRenderer.php       # منطق مشترک رندر آواتار (فرانت + ادمین)
│   │   └── PersianFormatter.php     # اعداد فارسی + تاریخ نسبی فارسی (فرانت + ادمین)
│   └── Events/
│       └── Listeners/
│           ├── SendTelegram.php     # ارسال وب‌هوک تلگرام
│           ├── SendBale.php         # ارسال پیام به بله
│           └── SendSlack.php        # ارسال وب‌هوک اسلک
├── config/
│   └── settings.php                 # تنظیمات منوی ادمین
├── assets/
│   ├── front/
│   │   ├── front.css                # استایل کامپایل شده فرانت
│   │   └── front.js                 # اسکریپت فرانت (ستاره‌ها، ارسال AJAX)
│   └── admin/
│       ├── admin.js                 # آپلودر مدیا ادمین
│       ├── alpine.min.js            # Alpine.js (محلی)
│       └── tailwind.min.js           # Tailwind (محلی)
└── docs/
    ├── dev_guide.md                 # راهنمای توسعه
    ├── styling-guide.md             # راهنمای استایل
    └── architecture.md              # ← این فایل
```

---

## جریان اجرا (Execution Flow)

### 1. بارگذاری افزونه
```
wp-admin/plugins.php → فعالسازی
       ↓
register_activation_hook → Activator::activate()
       ↓
Installer::install() → ساخت جدول wp_md_comments + تنظیمات پیش‌فرض
```

### 2. بوت‌استرپ در هر صفحه
```
WordPress → plugins_loaded hook
       ↓
Plugin::instance()  (الگوی Singleton)
       ↓
bootstrap():
  ├── بررسی نسخه دیتابیس (آپدیت خودکار)
  ├── new Front\Assets()        → ثبت hooks برای لود CSS/JS فرانت
  ├── new Front\Controller()    → ثبت hooks برای AJAX
  ├── new Front\Shortcode()     → ثبت شورت‌کد [md_comments]
  ├── if (is_admin):
  │   ├── new Admin\Assets()          → ثبت hooks لود دارایی‌های ادمین
  │   └── new Admin\SettingsController() → منوی ادمین + AJAX مدیریت
  └── register_events()         → ثبت EDA hooks
```

### 3. رندر فرانت با شورت‌کد
```
[md_comments] در محتوای پست
       ↓
Shortcode::render()
       ↓
Assets::enqueue_assets()  → لود front.css + front.js
       ↓
View::render()  → HTML کامل (فرم + لیست نظرات)
```

### 4. ثبت نظر با AJAX
```
کاربر → کلیک ارسال
       ↓
front.js → fetch() به admin-ajax.php
       ↓
Controller::handle_submit()
       ↓
Validator::validate_comment_submission()
  ├── بررسی Nonce
  ├── بررسی Honeypot
  ├── sanitize_text_field / sanitize_textarea_field (+ wp_unslash) + محدودیت طول
  ├── Regex شماره موبایل ایران
  └── تعیین وضعیت (approved/hold)
       ↓
CommentRepository::insert()
  └── $wpdb->insert با prepare
       ↓
do_action('md_comment_inserted', $comment_id, $data)
       ↓
Plugin::dispatch_comment_events()
  ├── SendTelegram::dispatch()
  ├── SendBale::dispatch()
  └── SendSlack::dispatch()
       ↓
wp_send_json_success() → front.js آپند کامنت به DOM
```

---

## ارتباط فایل‌ها (File Relationships)

```
md-custom-comments.php
  └── وابسته به: تمام کلاس‌های src/ از طریق autoloader
  └── نکته: فقط لودر است، هیچ لاجیکی ندارد

Core/Plugin.php
  └── وابسته به: Front/* , Admin/* , Events/* (مقداردهی در bootstrap)
  └── وابسته به: Core/Installer (آپدیت دیتابیس)
  └── رویدادها: md_comment_inserted → dispatch_comment_events
  └── الگو: Singleton — دسترسی سراسری با Plugin::instance()

Core/Installer.php
  └── فراخوانی توسط: Core/Activator , Core/Plugin
  └── ایجاد: جدول wp_md_comments + تنظیمات پیش‌فرض wp_options

Front/Shortcode.php
  └── وابسته به: Front/Assets , Front/View
  └── ثبت: add_shortcode('md_comments')

Front/View.php
  └── وابسته به: Database/CommentRepository
  └── وابسته به: get_option('md_comments_settings')
  └── متدهای کمکی: get_avatar_html, to_persian_num, human_time_diff_fa

Front/Controller.php
  └── وابسته به: Security/Validator , Database/CommentRepository
  └── اکشن‌های AJAX: wp_ajax_md_submit_comment

Front/Assets.php
  └── وابسته به: assets/front/front.css , assets/front/front.js
  └── هوک: wp_enqueue_scripts

Admin/Assets.php
  └── وابسته به: assets/admin/{admin.js, alpine.min.js, tailwind.min.js}
  └── هوک: admin_enqueue_scripts (شرطی: فقط صفحه تنظیمات)

Admin/SettingsController.php
  └── وابسته به: config/settings.php , Database/CommentRepository
  └── AJAX: md_save_settings, md_get_admin_comments, md_change_comment_status,
  │         md_delete_comment, md_reply_comment, md_edit_comment, md_test_bale
  └── هوک: admin_menu, admin_init, admin_bar_menu
  └── رندر: render_settings_page() → include Admin/Views/settings-page.php (HTML با Alpine.js + Tailwind)

Database/CommentRepository.php
  └── وابسته به: $wpdb (تنها کلاسی که با دیتابیس صحبت می‌کند)
  └── متدها: insert, get_approved_comments, get_all_comments_for_admin,
  │          get_comments_count, get_unique_users_count, update, update_status, delete
  └── رویداد: do_action('md_comment_inserted') بعد از insert

Security/Validator.php
  └── استاتیک: validate_comment_submission()
  └── وابسته به: wp_verify_nonce, sanitize_text_field, preg_match

Events/Listeners/SendTelegram.php
  └── وابسته به: get_option('md_comments_settings')
  └── استفاده از: Action Scheduler (as_enqueue_async_action)
  └── API: wp_remote_post به webhook_url

Events/Listeners/SendBale.php
  └── وابسته به: get_option('md_comments_settings')
  └── API: wp_remote_post به https://tapi.bale.ai/bot...

Events/Listeners/SendSlack.php
  └── وابسته به: get_option('md_comments_settings')
  └── API: wp_remote_post با payload شامل attachments
```

---

## جدول دیتابیس

### `wp_md_comments`
| فیلد | نوع | توضیح |
|------|-----|--------|
| id | bigint(20) PK | شناسه یکتا |
| post_id | bigint(20) | پست مرتبط |
| user_id | bigint(20) | کاربر وردپرسی (0 = مهمان) |
| user_name | varchar(100) | نام کاربر |
| user_phone | varchar(20) | شماره همراه |
| avatar_id | int(11) | شناسه آواتار انتخابی (1-4) |
| rating | tinyint(4) | امتیاز (1-5) |
| comment_text | text | متن دیدگاه |
| status | varchar(20) | hold / approved |
| parent_id | bigint(20) | پاسخ به کدام کامنت (0 = کامنت اصلی) |
| ip_address | varchar(45) | آی‌پی فرستنده |
| created_at | datetime | تاریخ ثبت |

---

## Event-Driven Architecture (EDA)

رویدادهای سفارشی:

| رویداد | فرستنده | گیرنده | توضیح |
|--------|---------|--------|-------|
| `md_comment_inserted` | `CommentRepository::insert()` | `Plugin::dispatch_comment_events()` | پس از ثبت نظر در دیتابیس |
| `md_send_telegram_webhook_async` | `SendTelegram::dispatch()` | `Plugin::handle_telegram_async()` | ارسال ناهمزمان وب‌هوک تلگرام |
| `md_send_bale_async` | `SendBale::dispatch()` | `Plugin::handle_bale_async()` | ارسال ناهمزمان پیام بله |
| `md_send_slack_webhook_async` | `SendSlack::dispatch()` | `Plugin::handle_slack_async()` | ارسال ناهمزمان وب‌هوک اسلک |

برای افزودن پیام‌رسان جدید:
1. فایل listener جدید در `src/Events/Listeners/` بسازید
2. متد `dispatch()` پیاده‌سازی کنید
3. در `Plugin::dispatch_comment_events()` نمونه‌سازی و فراخوانی کنید
4. فیلدهای تنظیمات را در `SettingsController` اضافه کنید

---

## نکات مهم برای نگهداری

### افزودن فیلد جدید به دیتابیس
1. به `Installer::install()` دستور `ALTER TABLE` اضافه کنید
2. به `CommentRepository` متدهای مربوطه اضافه کنید
3. فیلد را در `Validator::validate_comment_submission()` validate کنید
4. فیلد را در `View::render()` و `SettingsController` نمایش دهید

### افزودن تنظیم جدید به ادمین
1. مقدار پیش‌فرض را در `Installer::set_default_options()` اضافه کنید
2. فیلد HTML را در `src/Admin/Views/settings-page.php` اضافه کنید (متغیر لازم را قبلش در `SettingsController::render_settings_page()` تعریف کنید)
3. ذخیره‌سازی را در `SettingsController::save_settings_ajax()` اضافه کنید
4. مقدار را در View.php با `get_option('md_comments_settings')` بخوانید

### نکات امنیتی
- **همه** ورودی‌ها از `Validator` عبور می‌کنند (Nonce + Honeypot + Sanitize)
- **تنها** `CommentRepository` با `$wpdb->prepare()` با دیتابیس کار می‌کند
- فایل‌های PHP با `defined('ABSPATH') || exit;` از دسترسی مستقیم محافظت می‌شوند
