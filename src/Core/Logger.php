<?php
namespace MDCustomComments\Core;

defined( 'ABSPATH' ) || exit;

class Logger {
    /**
     * ثبت لاگ در فایل اختصاصی افزونه
     */
    public static function log( $level, $message, $context = [] ) {
        $upload_dir = wp_upload_dir();
        $log_dir = $upload_dir['basedir'] . '/md-custom-comments';
        
        if ( ! file_exists( $log_dir ) ) {
            wp_mkdir_p( $log_dir );
        }
        
        $log_file = $log_dir . '/debug.log';
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
