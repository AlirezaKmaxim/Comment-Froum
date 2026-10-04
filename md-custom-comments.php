<?php
/**
 * Plugin Name: MD Custom Comments (مدیریت نظرات سفارشی)
 * Plugin URI:  https://github.com/hosein/md-custom-comments
 * Description: سیستم هوشمند و زیبای ثبت نظرات با امتیازدهی ستاره‌ای، انتخاب آواتار سفارشی و اطلاع‌رسانی پیام‌رسان‌ها
 * Version:     1.1.1
 * Author:      Hosein
 * License:     GPL-2.0+
 * Text Domain: md-custom-comments
 * Domain Path: /languages
 */

defined( 'ABSPATH' ) || exit;

// تعریف ثوابت اصلی افزونه
define( 'MD_CUSTOM_COMMENTS_VERSION', '1.1.1' );
define( 'MD_CUSTOM_COMMENTS_FILE', __FILE__ );
define( 'MD_CUSTOM_COMMENTS_PATH', plugin_dir_path( __FILE__ ) );
define( 'MD_CUSTOM_COMMENTS_URL', plugin_dir_url( __FILE__ ) );

// بارگذاری Autoloader کامپوزر
if ( file_exists( MD_CUSTOM_COMMENTS_PATH . 'vendor/autoload.php' ) ) {
    require_once MD_CUSTOM_COMMENTS_PATH . 'vendor/autoload.php';
} else {
    // Autoloader دستی در صورت عدم نصب کامپوزر
    spl_autoload_register( function ( $class ) {
        $prefix = 'MDCustomComments\\';
        $base_dir = MD_CUSTOM_COMMENTS_PATH . 'src/';
        
        $len = strlen( $prefix );
        if ( strncmp( $prefix, $class, $len ) !== 0 ) {
            return;
        }
        
        $relative_class = substr( $class, $len );
        $file = $base_dir . str_replace( '\\', '/', $relative_class ) . '.php';
        
        if ( file_exists( $file ) ) {
            require_once $file;
        }
    } );
}

// کدهای مربوط به فعال‌سازی و غیرفعال‌سازی افزونه
register_activation_hook( __FILE__, [ 'MDCustomComments\\Core\\Activator', 'activate' ] );

// راه‌اندازی و شروع اجرای افزونه
add_action( 'plugins_loaded', function() {
    \MDCustomComments\Core\Plugin::instance();
} );
