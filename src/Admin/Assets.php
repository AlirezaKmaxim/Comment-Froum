<?php
namespace MDCustomComments\Admin;

defined( 'ABSPATH' ) || exit;

class Assets {
    public function __construct() {
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_assets' ] );
    }

    public function enqueue_admin_assets( $hook ) {
        // لود شرطی استایل‌ها و اسکریپت‌ها فقط در صفحه تنظیمات افزونه
        if ( $hook !== 'toplevel_page_md-custom-comments' ) {
            return;
        }

        // فعال‌سازی کتابخانه رسانه وردپرس
        wp_enqueue_media();

        // استایل از پیش کامپایل‌شده Tailwind برای پنل ادمین (به‌جای کامپایلر Runtime حجیم
        // که کلاس‌ها را در لحظه‌ی بارگذاری صفحه در مرورگر تولید می‌کرد). برای بازسازی این فایل
        // پس از تغییر کلاس‌های Tailwind در src/Admin/Views/settings-page.php دستور
        // `npm run build:admin` (یا `npm run build`) را اجرا کنید.
        wp_enqueue_style(
            'md-comments-admin-tailwind',
            plugins_url( 'assets/admin/admin-tailwind.css', MD_CUSTOM_COMMENTS_FILE ),
            [],
            MD_CUSTOM_COMMENTS_VERSION
        );

        // Alpine.js به صورت محلی برای تعاملات پنل مدیریت
        wp_enqueue_script(
            'md-comments-admin-alpine',
            plugins_url( 'assets/admin/alpine.min.js', MD_CUSTOM_COMMENTS_FILE ),
            [],
            MD_CUSTOM_COMMENTS_VERSION,
            true
        );

        // لود اسکریپت اختصاصی مدیریت مدیا
        wp_enqueue_script(
            'md-comments-admin-js',
            plugins_url( 'assets/admin/admin.js', MD_CUSTOM_COMMENTS_FILE ),
            [ 'jquery' ],
            MD_CUSTOM_COMMENTS_VERSION,
            true
        );
    }
}
