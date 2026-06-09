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

        // لود Tailwind CSS و Alpine.js به صورت محلی برای پنل مدیریت مدرن
        wp_enqueue_script(
            'md-comments-admin-tailwind',
            plugins_url( 'assets/admin/tailwind.min.js', MD_CUSTOM_COMMENTS_FILE ),
            [],
            MD_CUSTOM_COMMENTS_VERSION
        );
        
        // تنظیمات دلخواه Tailwind در ادمین
        wp_add_inline_script( 'md-comments-admin-tailwind', "
            tailwind.config = {
                theme: {
                    extend: {
                        colors: {
                            primary: '#0c2d28',
                            gold: '#c39854',
                            teal: '#009c8f',
                            neutral: '#606060',
                            border: '#d9d9d9',
                            subtle: '#e0e0e0',
                        }
                    }
                }
            }
        " );

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
