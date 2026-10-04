<?php
namespace MDCustomComments\Support;

defined( 'ABSPATH' ) || exit;

/**
 * توابع کمکی مشترک برای نمایش اعداد فارسی و تاریخ نسبی (فارسی)،
 * که قبلاً به‌صورت کپی-پیست‌شده هم در Front\View و هم در Admin\Controllers\SettingsController وجود داشت.
 */
class PersianFormatter {
    /**
     * تبدیل اعداد انگلیسی به فارسی
     */
    public static function to_persian_num( $num ) {
        $persian_digits = [ '۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹' ];
        return str_replace( range( 0, 9 ), $persian_digits, $num );
    }

    /**
     * نمایش تاریخ به صورت زمان گذشته (مانند "۲ ساعت پیش")
     */
    public static function human_time_diff_fa( $datetime ) {
        $diff = time() - strtotime( $datetime );
        if ( $diff < 60 ) {
            return 'لحظاتی پیش';
        }
        $diff_minutes = round( $diff / 60 );
        if ( $diff_minutes < 60 ) {
            return self::to_persian_num( $diff_minutes ) . ' دقیقه پیش';
        }
        $diff_hours = round( $diff / 3600 );
        if ( $diff_hours < 24 ) {
            return self::to_persian_num( $diff_hours ) . ' ساعت پیش';
        }
        $diff_days = round( $diff / 86400 );
        return self::to_persian_num( $diff_days ) . ' روز پیش';
    }
}
