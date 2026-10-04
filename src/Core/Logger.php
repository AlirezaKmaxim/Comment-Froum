<?php
namespace MDCustomComments\Core;

defined( 'ABSPATH' ) || exit;

class Logger {
    /**
     * حداکثر حجم مجاز فایل لاگ قبل از چرخش (Rotation) — پیش‌فرض ۵ مگابایت
     */
    const MAX_LOG_SIZE = 5242880;

    /**
     * ثبت لاگ در فایل اختصاصی افزونه
     */
    public static function log( $level, $message, $context = [] ) {
        $upload_dir = wp_upload_dir();
        $log_dir = $upload_dir['basedir'] . '/md-custom-comments';

        if ( ! file_exists( $log_dir ) ) {
            wp_mkdir_p( $log_dir );
        }

        // محافظت از دسترسی مستقیم HTTP به پوشه لاگ (که ممکن است حاوی جزئیات خطاهای وب‌هوک باشد)
        self::protect_log_directory( $log_dir );

        $log_file = $log_dir . '/debug.log';

        // چرخش ساده فایل لاگ: اگر از حد مجاز بزرگ‌تر شد، یک نسخه پشتیبان نگه داشته و از نو شروع می‌کنیم
        if ( file_exists( $log_file ) && filesize( $log_file ) > self::MAX_LOG_SIZE ) {
            @rename( $log_file, $log_dir . '/debug.log.old' );
        }

        $timestamp = current_time( 'mysql' );

        // در صورتی که پیام آرایه یا شیء باشد
        if ( is_array( $message ) || is_object( $message ) ) {
            $message = print_r( $message, true );
        }

        $context_str = '';
        if ( ! empty( $context ) ) {
            $context_str = ' | Context: ' . json_encode( $context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
        }

        $formatted = sprintf( "[%s] [%s] %s%s\n", $timestamp, strtoupper( $level ), $message, $context_str );

        @file_put_contents( $log_file, $formatted, FILE_APPEND );
    }

    /**
     * جلوگیری از دسترسی مستقیم مرورگر به فایل‌های پوشه لاگ (index.php خالی + .htaccess)
     * فقط در صورتی که این فایل‌ها هنوز وجود نداشته باشند نوشته می‌شوند.
     */
    private static function protect_log_directory( $log_dir ) {
        $index_file = $log_dir . '/index.php';
        if ( ! file_exists( $index_file ) ) {
            @file_put_contents( $index_file, "<?php\n// Silence is golden.\n" );
        }

        $htaccess_file = $log_dir . '/.htaccess';
        if ( ! file_exists( $htaccess_file ) ) {
            @file_put_contents( $htaccess_file, "Require all denied\nDeny from all\n" );
        }
    }

    public static function info( $message, $context = [] ) {
        self::log( 'info', $message, $context );
    }

    public static function error( $message, $context = [] ) {
        self::log( 'error', $message, $context );
    }

    public static function debug( $message, $context = [] ) {
        self::log( 'debug', $message, $context );
    }
}
