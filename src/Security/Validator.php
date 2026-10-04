<?php
namespace MDCustomComments\Security;

defined( 'ABSPATH' ) || exit;

class Validator {
    /**
     * اعتبارسنجی داده‌های ورودی ثبت نظر
     */
    public static function validate_comment_submission() {
        // ۱. بررسی CSRF Nonce Token
        if ( ! isset( $_POST['_ajax_nonce'] ) || ! wp_verify_nonce( $_POST['_ajax_nonce'], 'md_submit_comment_nonce' ) ) {
            wp_send_json_error( 'توکن امنیتی نامعتبر است. لطفاً صفحه را بارگذاری مجدد کنید.' );
        }

        // دریافت شناسه نوشته جاری برای ساخت نام فیلد تله به صورت پویا
        $post_id = isset( $_POST['post_id'] ) ? intval( $_POST['post_id'] ) : 0;

        // ۲. بررسی فیلد تله (Honeypot) جهت مسدودسازی ربات‌ها
        $hp_field_name = 'md_hp_' . substr( md5( $post_id . ( defined( 'NONCE_KEY' ) ? NONCE_KEY : 'md_fallback_salt' ) ), 0, 10 );
        if ( ! empty( $_POST[ $hp_field_name ] ) ) {
            // تظاهر به موفقیت‌آمیز بودن جهت سردرگم کردن بات
            wp_send_json_success( 'دیدگاه شما با موفقیت ثبت شد ✓' );
        }

        // ۳. بررسی و فیلتر کردن فیلد نام
        $name = isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '';
        $name = mb_substr( $name, 0, 100 ); // حداکثر ۱۰۰ کاراکتر
        if ( empty( $name ) ) {
            wp_send_json_error( 'لطفاً نام و نام خانوادگی خود را وارد کنید.' );
        }

        // ۴. اعتبارسنجی و فیلتر شماره موبایل ایران (Regex)
        $phone = isset( $_POST['phone'] ) ? sanitize_text_field( wp_unslash( $_POST['phone'] ) ) : '';
        if ( ! empty( $phone ) ) {
            if ( ! preg_match( '/^09[0-9]{9}$/', $phone ) ) {
                wp_send_json_error( 'شماره همراه وارد شده نامعتبر است. نمونه صحیح: 09123456789' );
            }
        }

        // ۵. فیلتر کردن متن نظر
        $comment = isset( $_POST['comment'] ) ? sanitize_textarea_field( wp_unslash( $_POST['comment'] ) ) : '';
        $comment = mb_substr( $comment, 0, 5000 ); // حداکثر ۵۰۰۰ کاراکتر
        if ( empty( $comment ) ) {
            wp_send_json_error( 'لطفاً متن دیدگاه خود را وارد کنید.' );
        }

        // ۶. اعتبارسنجی امتیاز ستاره‌ای (از ۱ تا ۵)
        $rating = isset( $_POST['rating'] ) ? intval( $_POST['rating'] ) : 0;
        if ( $rating < 1 || $rating > 5 ) {
            wp_send_json_error( 'لطفاً امتیاز خود را به این محتوا ثبت کنید.' );
        }

        // ۷. اعتبارسنجی شناسه آواتار (از ۱ تا ۴)
        $avatar = isset( $_POST['avatar'] ) ? intval( $_POST['avatar'] ) : 1;
        if ( $avatar < 1 || $avatar > 4 ) {
            $avatar = 1;
        }

        // ۸. دریافت شناسه نوشته جاری (قبلاً برای هانی‌پات استخراج شده است)
        if ( $post_id <= 0 ) {
            wp_send_json_error( 'شناسه نوشته نامعتبر است.' );
        }

        // ۹. دریافت شناسه والد (برای پاسخ‌ها)
        $parent_id = isset( $_POST['parent_id'] ) ? intval( $_POST['parent_id'] ) : 0;

        return [
            'post_id'    => $post_id,
            'user_id'    => get_current_user_id(),
            'name'       => $name,
            'phone'      => $phone,
            'avatar'     => $avatar,
            'rating'     => $rating,
            'comment'    => $comment,
            'parent_id'  => $parent_id,
            'ip_address' => self::get_ip_address(),
            'status'     => self::determine_comment_status(),
        ];
    }

    public static function get_ip_address() {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        
        if ( ! empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
            $ips = explode( ',', $_SERVER['HTTP_X_FORWARDED_FOR'] );
            $ip = trim( $ips[0] );
        } elseif ( ! empty( $_SERVER['HTTP_CLIENT_IP'] ) ) {
            $ip = $_SERVER['HTTP_CLIENT_IP'];
        }
        
        return filter_var( $ip, FILTER_VALIDATE_IP ) ? $ip : $_SERVER['REMOTE_ADDR'];
    }

    private static function determine_comment_status() {
        $settings = get_option( 'md_comments_settings', [] );
        $auto_approve = isset( $settings['auto_approve'] ) ? $settings['auto_approve'] : 'no';
        return ( $auto_approve === 'yes' ) ? 'approved' : 'hold';
    }
}
