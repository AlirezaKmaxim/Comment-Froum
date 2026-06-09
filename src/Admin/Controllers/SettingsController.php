<?php
namespace MDCustomComments\Admin\Controllers;

defined( 'ABSPATH' ) || exit;

class SettingsController {
    public function __construct() {
        add_action( 'admin_menu', [ $this, 'add_menu_page' ] );
        add_action( 'admin_init', [ $this, 'register_settings' ] );
        add_action( 'wp_ajax_md_save_settings', [ $this, 'save_settings_ajax' ] );
        add_action( 'admin_bar_menu', [ $this, 'add_admin_bar_link' ], 999 );
        add_action( 'wp_ajax_md_get_admin_comments', [ $this, 'get_admin_comments_ajax' ] );
        add_action( 'wp_ajax_md_change_comment_status', [ $this, 'change_comment_status_ajax' ] );
        add_action( 'wp_ajax_md_delete_comment', [ $this, 'delete_comment_ajax' ] );
        add_action( 'wp_ajax_md_reply_comment', [ $this, 'reply_comment_ajax' ] );
        add_action( 'wp_ajax_md_edit_comment', [ $this, 'edit_comment_ajax' ] );
        add_action( 'wp_ajax_md_test_bale', [ $this, 'test_bale_connection_ajax' ] );
    }

    public function add_menu_page() {
        $config = require MD_CUSTOM_COMMENTS_PATH . 'config/settings.php';
        
        add_menu_page(
            'تنظیمات نظرات سفارشی MD',
            $config['menu_title'],
            $config['capability'],
            $config['menu_slug'],
            [ $this, 'render_settings_page' ],
            $config['icon_url'],
            $config['position']
        );
    }

    public function register_settings() {
        register_setting( 'md_comments_settings_group', 'md_comments_settings' );
    }

    /**
     * ذخیره تنظیمات به صورت AJAX
     */
    public function save_settings_ajax() {
        // ۱. بررسی مجوز دسترسی کاربر
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'شما دسترسی کافی برای این کار را ندارید.' );
        }

        // ۲. بررسی توکن امنیتی
        if ( ! isset( $_POST['md_nonce'] ) || ! wp_verify_nonce( $_POST['md_nonce'], 'md_comments_settings_action' ) ) {
            wp_send_json_error( 'توکن امنیتی نامعتبر است. لطفاً صفحه را بارگذاری مجدد کنید.' );
        }

        // ۳. دریافت و پاک‌سازی داده‌ها
        $input = isset( $_POST['md_comments_settings'] ) ? $_POST['md_comments_settings'] : [];
        $old_settings = get_option( 'md_comments_settings', [] );
        $settings = $old_settings;

        // عمومی
        $settings['auto_approve'] = isset( $input['auto_approve'] ) && $input['auto_approve'] === 'yes' ? 'yes' : 'no';
        $settings['title_name'] = isset( $input['title_name'] ) ? sanitize_text_field( $input['title_name'] ) : 'نام و نام خانوادگی';
        $settings['title_phone'] = isset( $input['title_phone'] ) ? sanitize_text_field( $input['title_phone'] ) : 'شماره همراه (برای اطلاع‌رسانی)';
        $settings['label_admin'] = isset( $input['label_admin'] ) ? sanitize_text_field( $input['label_admin'] ) : 'کارشناس پشتیبانی';
        $settings['badge_admin'] = isset( $input['badge_admin'] ) ? sanitize_text_field( $input['badge_admin'] ) : 'ادمین';
        $settings['badge_member'] = isset( $input['badge_member'] ) ? sanitize_text_field( $input['badge_member'] ) : 'عضو سایت';

        // وب‌هوک‌ها
        $settings['telegram_enabled'] = isset( $input['telegram_enabled'] ) && $input['telegram_enabled'] === 'yes' ? 'yes' : 'no';
        $settings['telegram_webhook'] = isset( $input['telegram_webhook'] ) ? esc_url_raw( $input['telegram_webhook'] ) : '';
        $settings['slack_enabled'] = isset( $input['slack_enabled'] ) && $input['slack_enabled'] === 'yes' ? 'yes' : 'no';
        $settings['slack_webhook'] = isset( $input['slack_webhook'] ) ? esc_url_raw( $input['slack_webhook'] ) : '';
        $settings['bale_enabled'] = isset( $input['bale_enabled'] ) && $input['bale_enabled'] === 'yes' ? 'yes' : 'no';
        $settings['bale_bot_token'] = isset( $input['bale_bot_token'] ) ? sanitize_text_field( $input['bale_bot_token'] ) : '';
        $settings['bale_chat_id'] = isset( $input['bale_chat_id'] ) ? sanitize_text_field( $input['bale_chat_id'] ) : '';

        // آواتارها
        $settings['avatar_user_1_id'] = isset( $input['avatar_user_1_id'] ) ? intval( $input['avatar_user_1_id'] ) : 0;
        $settings['avatar_user_2_id'] = isset( $input['avatar_user_2_id'] ) ? intval( $input['avatar_user_2_id'] ) : 0;
        $settings['avatar_user_3_id'] = isset( $input['avatar_user_3_id'] ) ? intval( $input['avatar_user_3_id'] ) : 0;
        $settings['avatar_user_4_id'] = isset( $input['avatar_user_4_id'] ) ? intval( $input['avatar_user_4_id'] ) : 0;
        $settings['avatar_admin_id'] = isset( $input['avatar_admin_id'] ) ? intval( $input['avatar_admin_id'] ) : 0;
        $settings['avatar_member_id'] = isset( $input['avatar_member_id'] ) ? intval( $input['avatar_member_id'] ) : 0;

        update_option( 'md_comments_settings', $settings );

        // بررسی تغییر تنظیمات مرتبط با ظاهر جهت پاک‌سازی کش
        $appearance_keys = [
            'title_name', 'title_phone', 'label_admin', 'badge_admin', 'badge_member',
            'avatar_user_1_id', 'avatar_user_2_id', 'avatar_user_3_id', 'avatar_user_4_id',
            'avatar_admin_id', 'avatar_member_id'
        ];
        
        $flush_cache = false;
        foreach ( $appearance_keys as $key ) {
            $old_val = isset( $old_settings[$key] ) ? $old_settings[$key] : '';
            $new_val = isset( $settings[$key] ) ? $settings[$key] : '';
            if ( (string) $old_val !== (string) $new_val ) {
                $flush_cache = true;
                break;
            }
        }

        if ( $flush_cache ) {
            // پاک‌سازی کش افزونه‌های معروف کش وردپرس
            if ( function_exists( 'w3tc_pgcache_flush' ) ) {
                w3tc_pgcache_flush();
            }
            if ( function_exists( 'wp_cache_clear_cache' ) ) {
                wp_cache_clear_cache();
            }
            if ( class_exists( 'WPRocket\Plugin' ) && function_exists( 'rocket_clean_domain' ) ) {
                rocket_clean_domain();
            }
            if ( class_exists( 'LiteSpeed_Cache_API' ) && method_exists( 'LiteSpeed_Cache_API', 'purge_all' ) ) {
                \LiteSpeed_Cache_API::purge_all();
            }
        }

        wp_send_json_success( 'تنظیمات با موفقیت ذخیره شد ✓' );
    }

    /**
     * افزودن لینک سریع در نوار مدیریت بالای وردپرس
     */
    public function add_admin_bar_link( $wp_admin_bar ) {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }
        $wp_admin_bar->add_node( [
            'id'    => 'md-comments-bar-link',
            'title' => 'مدیریت نظرات MD',
            'href'  => admin_url( 'admin.php?page=md-custom-comments&tab=comments' ),
            'meta'  => [ 'title' => 'مدیریت نظرات سفارشی کاربران' ]
        ] );
    }

    /**
     * بازیابی تمام نظرات به صورت AJAX
     */
    public function get_admin_comments_ajax() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'دسترسی غیرمجاز.' );
        }

        if ( ! isset( $_POST['md_nonce'] ) || ! wp_verify_nonce( $_POST['md_nonce'], 'md_comments_settings_action' ) ) {
            wp_send_json_error( 'توکن امنیتی نامعتبر است. لطفاً صفحه را بارگذاری مجدد کنید.' );
        }
        
        $repository = new \MDCustomComments\Database\CommentRepository();
        $comments = $repository->get_all_comments_for_admin();
        
        foreach ( $comments as &$comment ) {
            $comment['created_at_human'] = $this->human_time_diff_fa( $comment['created_at'] );
            $comment['permalink'] = get_permalink( $comment['post_id'] );
            $comment['avatar_html'] = $this->get_avatar_html( $comment['avatar_id'], $comment['user_id'], 'w-12 h-12' );
        }
        
        wp_send_json_success( $comments );
    }

    /**
     * تست اتصال به پیام‌رسان بله به صورت AJAX
     */
    public function test_bale_connection_ajax() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'دسترسی غیرمجاز.' );
        }

        if ( ! isset( $_POST['md_nonce'] ) || ! wp_verify_nonce( $_POST['md_nonce'], 'md_comments_settings_action' ) ) {
            wp_send_json_error( 'توکن امنیتی نامعتبر است. لطفاً صفحه را بارگذاری مجدد کنید.' );
        }
        
        $bot_token = isset( $_POST['bot_token'] ) ? sanitize_text_field( $_POST['bot_token'] ) : '';
        $chat_id = isset( $_POST['chat_id'] ) ? sanitize_text_field( $_POST['chat_id'] ) : '';

        if ( empty( $bot_token ) || empty( $chat_id ) ) {
            wp_send_json_error( 'لطفاً توکن ربات و شناسه چت را وارد کنید.' );
        }

        if ( strpos( $chat_id, '@' ) === 0 ) {
            wp_send_json_error( 'شناسه چت نمی‌تواند با @ شروع شود. شناسه چت باید یک مقدار عددی باشد (مانند 987654321). برای دریافت شناسه عددی خود، ربات @userinfobot را در بله جستجو و استارت کنید.' );
        }

        $url = "https://tapi.bale.ai/bot" . $bot_token . "/sendMessage";
        $message = "🛎 پیام تست اتصال از افزونه نظرات سفارشی MD\nاتصال پیام‌رسان بله با موفقیت برقرار شد! ✓";

        $response = wp_remote_post( $url, [
            'method'    => 'POST',
            'headers'   => [ 'Content-Type' => 'application/json; charset=utf-8' ],
            'body'      => json_encode( [
                'chat_id'    => $chat_id,
                'text'       => $message,
            ], JSON_UNESCAPED_UNICODE ),
            'timeout'   => 10,
            'sslverify' => false,
        ] );

        if ( is_wp_error( $response ) ) {
            $err_msg = $response->get_error_message();
            if ( strpos( $err_msg, 'blocked requests through HTTP' ) !== false || strpos( $err_msg, 'cURL error' ) !== false ) {
                $err_msg .= ' — ممکن است در فایل wp-config.php ثابت WP_HTTP_BLOCK_EXTERNAL تعریف شده باشد. برای رفع این مشکل، خط زیر را به wp-config.php اضافه کنید: define(\'WP_ACCESSIBLE_HOSTS\', \'tapi.bale.ai,api.telegram.org\');';
            }
            wp_send_json_error( 'خطا در ارتباط با سرور بله: ' . $err_msg );
        }

        $status_code = wp_remote_retrieve_response_code( $response );
        $body = wp_remote_retrieve_body( $response );
        $data = json_decode( $body, true );

        if ( ! is_array( $data ) ) {
            wp_send_json_error( 'پاسخ نامعتبر از سرور بله (کد: ' . $status_code . '). لطفاً توکن ربات را بررسی کنید.' );
        }

        if ( isset( $data['ok'] ) && $data['ok'] === true ) {
            wp_send_json_success( 'پیام تست با موفقیت به بله ارسال شد. لطفا پیام‌رسان خود را بررسی کنید.' );
        } else {
            $error_code = isset( $data['error_code'] ) ? $data['error_code'] : '';
            $error_description = isset( $data['description'] ) ? $data['description'] : 'خطای ناشناخته';
            $error_msg = $error_code ? "[$error_code] $error_description" : $error_description;
            wp_send_json_error( 'خطا از سمت بله: ' . $error_msg );
        }
    }

    /**
     * تغییر وضعیت تایید دیدگاه به صورت AJAX
     */
    public function change_comment_status_ajax() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'دسترسی غیرمجاز.' );
        }
        
        if ( ! isset( $_POST['md_nonce'] ) || ! wp_verify_nonce( $_POST['md_nonce'], 'md_comments_settings_action' ) ) {
            wp_send_json_error( 'توکن امنیتی نامعتبر است.' );
        }
        
        $id = isset( $_POST['comment_id'] ) ? intval( $_POST['comment_id'] ) : 0;
        $status = isset( $_POST['status'] ) ? sanitize_text_field( $_POST['status'] ) : 'hold';
        
        if ( $id <= 0 || ! in_array( $status, [ 'approved', 'hold' ] ) ) {
            wp_send_json_error( 'پارامترهای ارسالی نامعتبر هستند.' );
        }
        
        $repository = new \MDCustomComments\Database\CommentRepository();
        $updated = $repository->update_status( $id, $status );
        
        if ( $updated !== false ) {
            wp_send_json_success( 'وضعیت دیدگاه با موفقیت بروزرسانی شد.' );
        } else {
            wp_send_json_error( 'خطا در بروزرسانی وضعیت دیدگاه.' );
        }
    }

    /**
     * حذف دیدگاه به صورت AJAX
     */
    public function delete_comment_ajax() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'دسترسی غیرمجاز.' );
        }
        
        if ( ! isset( $_POST['md_nonce'] ) || ! wp_verify_nonce( $_POST['md_nonce'], 'md_comments_settings_action' ) ) {
            wp_send_json_error( 'توکن امنیتی نامعتبر است.' );
        }
        
        $id = isset( $_POST['comment_id'] ) ? intval( $_POST['comment_id'] ) : 0;
        if ( $id <= 0 ) {
            wp_send_json_error( 'شناسه دیدگاه نامعتبر است.' );
        }
        
        $repository = new \MDCustomComments\Database\CommentRepository();
        $deleted = $repository->delete( $id );
        
        if ( $deleted ) {
            wp_send_json_success( 'دیدگاه با موفقیت حذف شد.' );
        } else {
            wp_send_json_error( 'خطا در حذف دیدگاه.' );
        }
    }

    /**
     * ثبت پاسخ ادمین به صورت AJAX
     */
    public function reply_comment_ajax() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'دسترسی غیرمجاز.' );
        }
        
        if ( ! isset( $_POST['md_nonce'] ) || ! wp_verify_nonce( $_POST['md_nonce'], 'md_comments_settings_action' ) ) {
            wp_send_json_error( 'توکن امنیتی نامعتبر است.' );
        }
        
        $parent_id = isset( $_POST['parent_id'] ) ? intval( $_POST['parent_id'] ) : 0;
        $post_id = isset( $_POST['post_id'] ) ? intval( $_POST['post_id'] ) : 0;
        $comment_text = isset( $_POST['comment'] ) ? sanitize_textarea_field( $_POST['comment'] ) : '';
        
        if ( $parent_id <= 0 || $post_id <= 0 || empty( $comment_text ) ) {
            wp_send_json_error( 'لطفاً متن پاسخ را وارد کنید.' );
        }
        
        $current_user = wp_get_current_user();
        $admin_name = ! empty( $current_user->display_name ) ? $current_user->display_name : 'مدیر سایت';
        
        $data = [
            'post_id'    => $post_id,
            'user_id'    => get_current_user_id(),
            'name'       => $admin_name,
            'phone'      => '',
            'avatar'     => 0, // آواتار ادمین
            'rating'     => 5,
            'comment'    => $comment_text,
            'status'     => 'approved',
            'parent_id'  => $parent_id,
            'ip_address' => $_SERVER['REMOTE_ADDR']
        ];
        
        $repository = new \MDCustomComments\Database\CommentRepository();
        $comment_id = $repository->insert( $data );
        
        if ( $comment_id ) {
            $repository->update_status( $parent_id, 'approved' );
            wp_send_json_success( 'پاسخ شما با موفقیت ثبت و منتشر شد.' );
        } else {
            wp_send_json_error( 'خطا در ثبت پاسخ ادمین.' );
        }
    }

    /**
     * ویرایش دیدگاه به صورت AJAX
     */
    public function edit_comment_ajax() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'دسترسی غیرمجاز.' );
        }
        
        if ( ! isset( $_POST['md_nonce'] ) || ! wp_verify_nonce( $_POST['md_nonce'], 'md_comments_settings_action' ) ) {
            wp_send_json_error( 'توکن امنیتی نامعتبر است.' );
        }
        
        $comment_id = isset( $_POST['comment_id'] ) ? intval( $_POST['comment_id'] ) : 0;
        $user_name = isset( $_POST['user_name'] ) ? sanitize_text_field( $_POST['user_name'] ) : '';
        $user_phone = isset( $_POST['user_phone'] ) ? sanitize_text_field( $_POST['user_phone'] ) : '';
        $comment_text = isset( $_POST['comment_text'] ) ? sanitize_textarea_field( $_POST['comment_text'] ) : '';
        $rating = isset( $_POST['rating'] ) ? intval( $_POST['rating'] ) : 5;
        $avatar_id = isset( $_POST['avatar_id'] ) ? intval( $_POST['avatar_id'] ) : 1;
        $status = isset( $_POST['status'] ) ? sanitize_text_field( $_POST['status'] ) : 'approved';
        
        if ( $comment_id <= 0 || empty( $user_name ) || empty( $comment_text ) ) {
            wp_send_json_error( 'نام و متن دیدگاه نمی‌توانند خالی باشند.' );
        }
        
        $data = [
            'user_name'    => $user_name,
            'user_phone'   => $user_phone,
            'comment_text' => $comment_text,
            'rating'       => $rating,
            'avatar_id'    => $avatar_id,
            'status'       => $status
        ];
        
        $repository = new \MDCustomComments\Database\CommentRepository();
        $result = $repository->update( $comment_id, $data );
        
        if ( $result !== false ) {
            wp_send_json_success( 'دیدگاه با موفقیت ویرایش شد.' );
        } else {
            wp_send_json_error( 'خطا در ویرایش دیدگاه.' );
        }
    }

    /**
     * تبدیل اعداد انگلیسی به فارسی
     */
    private function to_persian_num( $num ) {
        $persian_digits = [ '۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹' ];
        return str_replace( range( 0, 9 ), $persian_digits, $num );
    }

    /**
     * نمایش تاریخ به صورت زمان گذشته
     */
    private function human_time_diff_fa( $datetime ) {
        $diff = time() - strtotime( $datetime );
        if ( $diff < 60 ) {
            return 'لحظاتی پیش';
        }
        $diff_minutes = round( $diff / 60 );
        if ( $diff_minutes < 60 ) {
            return $this->to_persian_num( $diff_minutes ) . ' دقیقه پیش';
        }
        $diff_hours = round( $diff / 3600 );
        if ( $diff_hours < 24 ) {
            return $this->to_persian_num( $diff_hours ) . ' ساعت پیش';
        }
        $diff_days = round( $diff / 86400 );
        return $this->to_persian_num( $diff_days ) . ' روز پیش';
    }

    /**
     * رندر صفحه تنظیمات ادمین به صورت مدرن و زیبا با Alpine.js و Tailwind
     */
    public function render_settings_page() {
        $settings = get_option( 'md_comments_settings', [] );

        // بازیابی مقادیر
        $auto_approve = isset( $settings['auto_approve'] ) ? $settings['auto_approve'] : 'no';
        $title_name = isset( $settings['title_name'] ) ? $settings['title_name'] : 'نام و نام خانوادگی';
        $title_phone = isset( $settings['title_phone'] ) ? $settings['title_phone'] : 'شماره همراه (برای اطلاع‌رسانی)';
        $label_admin = isset( $settings['label_admin'] ) ? $settings['label_admin'] : 'کارشناس پشتیبانی';
        $badge_admin = isset( $settings['badge_admin'] ) ? $settings['badge_admin'] : 'ادمین';
        $badge_member = isset( $settings['badge_member'] ) ? $settings['badge_member'] : 'عضو سایت';

        $telegram_enabled = isset( $settings['telegram_enabled'] ) ? $settings['telegram_enabled'] : 'no';
        $telegram_webhook = isset( $settings['telegram_webhook'] ) ? $settings['telegram_webhook'] : '';
        $bale_enabled = isset( $settings['bale_enabled'] ) ? $settings['bale_enabled'] : 'no';
        $bale_bot_token = isset( $settings['bale_bot_token'] ) ? $settings['bale_bot_token'] : '';
        $bale_chat_id = isset( $settings['bale_chat_id'] ) ? $settings['bale_chat_id'] : '';

        $avatar_user_1_id = isset( $settings['avatar_user_1_id'] ) ? intval( $settings['avatar_user_1_id'] ) : 0;
        $avatar_user_2_id = isset( $settings['avatar_user_2_id'] ) ? intval( $settings['avatar_user_2_id'] ) : 0;
        $avatar_user_3_id = isset( $settings['avatar_user_3_id'] ) ? intval( $settings['avatar_user_3_id'] ) : 0;
        $avatar_user_4_id = isset( $settings['avatar_user_4_id'] ) ? intval( $settings['avatar_user_4_id'] ) : 0;
        $avatar_admin_id = isset( $settings['avatar_admin_id'] ) ? intval( $settings['avatar_admin_id'] ) : 0;
        $avatar_member_id = isset( $settings['avatar_member_id'] ) ? intval( $settings['avatar_member_id'] ) : 0;

        $avatar_urls = [];
        for ( $i = 1; $i <= 4; $i++ ) {
            $att_id = isset( $settings['avatar_user_' . $i . '_id'] ) ? intval( $settings['avatar_user_' . $i . '_id'] ) : 0;
            $avatar_urls[$i] = $att_id ? wp_get_attachment_url( $att_id ) : '';
        }
        ?>
        <script>
            window.mdAvatarFallbacks = {
                1: <?php echo $avatar_urls[1] ? "'<img class=\"w-8 h-8 object-cover rounded-full\" src=\"" . esc_url( $avatar_urls[1] ) . "\" />'" : "'<svg class=\"w-8 h-8 text-gold\" viewBox=\"0 0 40 40\"><circle cx=\"20\" cy=\"15\" r=\"8\" fill=\"currentColor\" opacity=\"0.7\"/><ellipse cx=\"20\" cy=\"35\" rx=\"14\" ry=\"10\" fill=\"currentColor\" opacity=\"0.4\"/></svg>'"; ?>,
                2: <?php echo $avatar_urls[2] ? "'<img class=\"w-8 h-8 object-cover rounded-full\" src=\"" . esc_url( $avatar_urls[2] ) . "\" />'" : "'<svg class=\"w-8 h-8 text-teal\" viewBox=\"0 0 40 40\"><rect x=\"8\" y=\"6\" width=\"24\" height=\"24\" rx=\"6\" fill=\"currentColor\" opacity=\"0.6\"/><circle cx=\"20\" cy=\"18\" r=\"5\" fill=\"#fff\" opacity=\"0.8\"/></svg>'"; ?>,
                3: <?php echo $avatar_urls[3] ? "'<img class=\"w-8 h-8 object-cover rounded-full\" src=\"" . esc_url( $avatar_urls[3] ) . "\" />'" : "'<svg class=\"w-8 h-8 text-primary\" viewBox=\"0 0 40 40\"><polygon points=\"20,4 36,30 4,30\" fill=\"currentColor\" opacity=\"0.5\"/><circle cx=\"20\" cy=\"21\" r=\"4\" fill=\"#fff\" opacity=\"0.7\"/></svg>'"; ?>,
                4: <?php echo $avatar_urls[4] ? "'<img class=\"w-8 h-8 object-cover rounded-full\" src=\"" . esc_url( $avatar_urls[4] ) . "\" />'" : "'<svg class=\"w-8 h-8 text-gold\" viewBox=\"0 0 40 40\"><circle cx=\"20\" cy=\"20\" r=\"14\" fill=\"currentColor\" opacity=\"0.3\"/><circle cx=\"14\" cy=\"17\" r=\"2.5\" fill=\"currentColor\" opacity=\"0.7\"/><circle cx=\"26\" cy=\"17\" r=\"2.5\" fill=\"currentColor\" opacity=\"0.7\"/><path d=\"M14 25 Q20 30 26 25\" stroke=\"currentColor\" stroke-width=\"1.5\" fill=\"none\" opacity=\"0.6\"/></svg>'"; ?>,
            };
        </script>
        <div class="wrap min-h-screen bg-gray-50 pr-0 font-peyda" dir="rtl">
            <div class="max-w-full bg-white shadow-xl rounded-2xl overflow-hidden border border-gray-200" 
                 x-data="{ 
                    activeTab: '<?php echo isset($_GET['tab']) ? sanitize_key($_GET['tab']) : 'general'; ?>', 
                    isSubmitting: false, 
                    toastMessage: '', 
                    toastType: 'success',
                    comments: [],
                    loadingComments: false,
                    replyingTo: null,
                    replyText: '',
                    editingComment: null,
                    viewingComment: {},
                    editData: { id: 0, user_name: '', user_phone: '', comment_text: '', rating: 5, avatar_id: 1, status: 'approved' },
                    avatarFallbacks: window.mdAvatarFallbacks,
                    isTestingBale: false,
                    testBaleConnection() {
                        const tokenInput = document.querySelector('input[name=\x22md_comments_settings[bale_bot_token]\x22]').value;
                        const chatIdInput = document.querySelector('input[name=\x22md_comments_settings[bale_chat_id]\x22]').value;

                        if (!tokenInput.trim() || !chatIdInput.trim()) {
                            this.showToast('لطفا توکن ربات و شناسه چت را وارد کنید.', 'error');
                            return;
                        }

                        this.isTestingBale = true;
                        const formData = new FormData();
                        formData.append('action', 'md_test_bale');
                        formData.append('bot_token', tokenInput);
                        formData.append('chat_id', chatIdInput);
                        formData.append('md_nonce', document.querySelector('input[name=md_nonce]').value);

                        fetch(ajaxurl, {
                            method: 'POST',
                            body: formData
                        })
                        .then(res => res.json())
                        .then(res => {
                            this.isTestingBale = false;
                            console.log('[MD Comments] Bale test response:', res);
                            if (res.success) {
                                this.showToast(res.data, 'success');
                            } else {
                                this.showToast(res.data || 'خطایی رخ داد', 'error');
                            }
                        })
                        .catch(err => {
                            this.isTestingBale = false;
                            console.error('[MD Comments] Bale test error:', err);
                            this.showToast('خطا در اتصال به سرور', 'error');
                        });
                    },
                    
                    showToast(msg, type = 'success') {
                        this.toastMessage = msg;
                        this.toastType = type;
                        setTimeout(() => this.toastMessage = '', 4000);
                    },
                    submitForm() {
                        this.isSubmitting = true;
                        const formData = new FormData(this.$refs.settingsForm);
                        formData.append('action', 'md_save_settings');
                        
                        fetch(ajaxurl, {
                            method: 'POST',
                            body: formData
                        })
                        .then(res => res.json())
                        .then(data => {
                            this.isSubmitting = false;
                            if (data.success) {
                                this.showToast(data.data, 'success');
                            } else {
                                this.showToast(data.data || 'خطایی رخ داد', 'error');
                            }
                        })
                        .catch(err => {
                            this.isSubmitting = false;
                            this.showToast('خطا در اتصال به سرور', 'error');
                        });
                    },
                    fetchComments() {
                        this.loadingComments = true;
                        
                        const formData = new FormData();
                        formData.append('action', 'md_get_admin_comments');
                        const nonceEl = document.querySelector('input[name=md_nonce]');
                        formData.append('md_nonce', nonceEl ? nonceEl.value : '');

                        fetch(ajaxurl, {
                            method: 'POST',
                            body: formData
                        })
                        .then(res => res.json())
                        .then(res => {
                            this.loadingComments = false;
                            if (res.success) {
                                this.comments = res.data;
                            } else {
                                this.showToast(res.data || 'خطا در بارگذاری نظرات', 'error');
                            }
                        })
                        .catch(err => {
                            this.loadingComments = false;
                            this.showToast('خطا در ارتباط با سرور', 'error');
                        });
                    },
                    changeStatus(id, newStatus) {
                        const formData = new FormData();
                        formData.append('action', 'md_change_comment_status');
                        formData.append('comment_id', id);
                        formData.append('status', newStatus);
                        formData.append('md_nonce', document.querySelector('input[name=md_nonce]').value);

                        fetch(ajaxurl, {
                            method: 'POST',
                            body: formData
                        })
                        .then(res => res.json())
                        .then(res => {
                            if (res.success) {
                                this.showToast(res.data, 'success');
                                this.fetchComments();
                            } else {
                                this.showToast(res.data || 'خطایی رخ داد', 'error');
                            }
                        });
                    },
                    deleteComment(id) {
                        if (!confirm('آیا از حذف این دیدگاه اطمینان دارید؟ در صورت حذف، پاسخ‌های آن نیز حذف خواهند شد.')) {
                            return;
                        }
                        const formData = new FormData();
                        formData.append('action', 'md_delete_comment');
                        formData.append('comment_id', id);
                        formData.append('md_nonce', document.querySelector('input[name=md_nonce]').value);

                        fetch(ajaxurl, {
                            method: 'POST',
                            body: formData
                        })
                        .then(res => res.json())
                        .then(res => {
                            if (res.success) {
                                this.showToast(res.data, 'success');
                                this.fetchComments();
                            } else {
                                this.showToast(res.data || 'خطایی رخ داد', 'error');
                            }
                        });
                    },
                    openReplyModal(comment) {
                        this.replyingTo = comment;
                        this.replyText = '';
                    },
                    closeReplyModal() {
                        this.replyingTo = null;
                        this.replyText = '';
                    },
                    submitReply() {
                        if (!this.replyText.trim()) {
                            alert('لطفاً متن پاسخ را بنویسید.');
                            return;
                        }
                        const formData = new FormData();
                        formData.append('action', 'md_reply_comment');
                        formData.append('parent_id', this.replyingTo.id);
                        formData.append('post_id', this.replyingTo.post_id);
                        formData.append('comment', this.replyText);
                        formData.append('md_nonce', document.querySelector('input[name=md_nonce]').value);

                        fetch(ajaxurl, {
                            method: 'POST',
                            body: formData
                        })
                        .then(res => res.json())
                        .then(res => {
                            if (res.success) {
                                this.showToast(res.data, 'success');
                                this.closeReplyModal();
                                this.fetchComments();
                            } else {
                                this.showToast(res.data || 'خطایی رخ داد', 'error');
                            }
                        });
                    },
                    openEditModal(comment) {
                        this.editingComment = comment;
                        this.editData = {
                            id: comment.id,
                            user_name: comment.user_name,
                            user_phone: comment.user_phone,
                            comment_text: comment.comment_text,
                            rating: comment.rating,
                            avatar_id: comment.avatar_id || 1,
                            status: comment.status || 'approved'
                        };
                    },
                    closeEditModal() {
                        this.editingComment = null;
                    },
                    submitEdit() {
                        if (!this.editData.user_name.trim() || !this.editData.comment_text.trim()) {
                            alert('نام و متن دیدگاه نمی‌توانند خالی باشند.');
                            return;
                        }
                        const formData = new FormData();
                        formData.append('action', 'md_edit_comment');
                        formData.append('comment_id', this.editData.id);
                        formData.append('user_name', this.editData.user_name);
                        formData.append('user_phone', this.editData.user_phone);
                        formData.append('comment_text', this.editData.comment_text);
                        formData.append('rating', this.editData.rating);
                        formData.append('avatar_id', this.editData.avatar_id);
                        formData.append('status', this.editData.status);
                        formData.append('md_nonce', document.querySelector('input[name=md_nonce]').value);

                        fetch(ajaxurl, {
                            method: 'POST',
                            body: formData
                        })
                        .then(res => res.json())
                        .then(res => {
                            if (res.success) {
                                this.showToast(res.data, 'success');
                                this.closeEditModal();
                                this.fetchComments();
                            } else {
                                this.showToast(res.data || 'خطایی رخ داد', 'error');
                            }
                        });
                    },
                    openViewModal(comment) {
                        this.viewingComment = comment;
                    },
                    closeViewModal() {
                        this.viewingComment = {};
                    }
                 }"
                 x-init="if (activeTab === 'comments') { fetchComments(); }">
                 
                <!-- هدر پنل ادمین -->
                <div class="bg-primary p-8 text-white flex items-center justify-between border-b-4 border-gold">
                    <div class="flex items-center gap-4">
                        <div class="p-3 bg-white/10 rounded-xl">
                            <svg class="w-8 h-8 text-gold" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M8.684 13.342C8.886 12.938 9 12.482 9 12c0-.482-.114-.938-.316-1.342m0 2.684a3 3 0 110-2.684m0 2.684l6.632 3.316m-6.632-6l6.632-3.316m0 0a3 3 0 105.367-2.684 3 3 0 00-5.367 2.684zm0 9.316a3 3 0 105.368 2.684 3 3 0 00-5.368-2.684z"></path>
                            </svg>
                        </div>
                        <div>
                            <h1 class="text-2xl font-bold text-white m-0">تنظیمات نظرات سفارشی MD</h1>
                            <p class="text-gray-300 text-sm mt-1">مدیریت آواتارها، فیلدهای دلخواه و اعلان‌های تلگرام/اسلک</p>
                        </div>
                    </div>
                </div>

                <div class="flex flex-col md:flex-row">
                    <!-- سایدبار منوی تب‌ها -->
                    <div class="w-full md:w-64 bg-gray-100 border-l border-gray-200 p-4 flex flex-col gap-2">
                        <button type="button" 
                                @click="activeTab = 'general'"
                                :class="activeTab === 'general' ? 'bg-primary text-white shadow-md' : 'text-gray-700 hover:bg-gray-200'"
                                class="w-full text-right py-3 px-4 rounded-xl font-bold transition-all flex items-center gap-3">
                            <span class="w-2.5 h-2.5 rounded-full bg-gold"></span>
                            تنظیمات عمومی و ظاهر
                        </button>
                        <button type="button" 
                                @click="activeTab = 'comments'; fetchComments()"
                                :class="activeTab === 'comments' ? 'bg-primary text-white shadow-md' : 'text-gray-700 hover:bg-gray-200'"
                                class="w-full text-right py-3 px-4 rounded-xl font-bold transition-all flex items-center gap-3">
                            <span class="w-2.5 h-2.5 rounded-full bg-teal"></span>
                            مدیریت نظرات کاربران
                        </button>
                        <button type="button" 
                                @click="activeTab = 'avatars'"
                                :class="activeTab === 'avatars' ? 'bg-primary text-white shadow-md' : 'text-gray-700 hover:bg-gray-200'"
                                class="w-full text-right py-3 px-4 rounded-xl font-bold transition-all flex items-center gap-3">
                            <span class="w-2.5 h-2.5 rounded-full bg-teal"></span>
                            مدیریت آواتارهای پویا
                        </button>
                        <button type="button" 
                                @click="activeTab = 'messengers'"
                                :class="activeTab === 'messengers' ? 'bg-primary text-white shadow-md' : 'text-gray-700 hover:bg-gray-200'"
                                class="w-full text-right py-3 px-4 rounded-xl font-bold transition-all flex items-center gap-3">
                            <span class="w-2.5 h-2.5 rounded-full bg-neutral"></span>
                            تنظیمات پیام‌رسان‌ها
                        </button>
                    </div>

                    <!-- محتوای تنظیمات -->
                    <div class="flex-grow p-8">
                        <form x-ref="settingsForm" @submit.prevent="submitForm" x-show="activeTab !== 'comments'">
                            <?php wp_nonce_field( 'md_comments_settings_action', 'md_nonce' ); ?>
                            
                            <!-- ═══ تب تنظیمات عمومی ═══ -->
                            <div x-show="activeTab === 'general'" x-transition:enter="transition ease-out duration-200" class="space-y-6">
                                <h3 class="text-xl font-bold text-primary border-b pb-3 mb-6">تنظیمات عمومی و فرم‌ها</h3>
                                
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                    <div>
                                        <label class="block text-gray-700 font-bold mb-2">تایید خودکار نظرات</label>
                                        <select name="md_comments_settings[auto_approve]" class="w-full border-gray-300 rounded-xl p-3 focus:ring-primary focus:border-primary">
                                            <option value="no" <?php selected( $auto_approve, 'no' ); ?>>خیر (نیاز به تایید ادمین دارد)</option>
                                            <option value="yes" <?php selected( $auto_approve, 'yes' ); ?>>بله (دیدگاه‌ها خودکار تایید و منتشر شوند)</option>
                                        </select>
                                    </div>
                                </div>

                                <h3 class="text-lg font-bold text-primary pt-6 border-b pb-3 mb-4">عنوان اختصاصی فیلدهای فرم</h3>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                    <div>
                                        <label class="block text-gray-700 font-bold mb-2">عنوان فیلد نام</label>
                                        <input type="text" name="md_comments_settings[title_name]" value="<?php echo esc_attr( $title_name ); ?>" class="w-full border-gray-300 rounded-xl p-3" placeholder="نام و نام خانوادگی">
                                    </div>
                                    <div>
                                        <label class="block text-gray-700 font-bold mb-2">عنوان فیلد شماره همراه</label>
                                        <input type="text" name="md_comments_settings[title_phone]" value="<?php echo esc_attr( $title_phone ); ?>" class="w-full border-gray-300 rounded-xl p-3" placeholder="شماره همراه (برای اطلاع‌رسانی)">
                                    </div>
                                </div>

                                <h3 class="text-lg font-bold text-primary pt-6 border-b pb-3 mb-4">برچسب و نقش کاربران سفارشی</h3>
                                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                                    <div>
                                        <label class="block text-gray-700 font-bold mb-2">عنوان نمایشی ادمین</label>
                                        <input type="text" name="md_comments_settings[label_admin]" value="<?php echo esc_attr( $label_admin ); ?>" class="w-full border-gray-300 rounded-xl p-3" placeholder="کارشناس پشتیبانی">
                                    </div>
                                    <div>
                                        <label class="block text-gray-700 font-bold mb-2">برچسب نقش ادمین (بج)</label>
                                        <input type="text" name="md_comments_settings[badge_admin]" value="<?php echo esc_attr( $badge_admin ); ?>" class="w-full border-gray-300 rounded-xl p-3" placeholder="ادمین">
                                    </div>
                                    <div>
                                        <label class="block text-gray-700 font-bold mb-2">برچسب نقش اعضای سایت (بج)</label>
                                        <input type="text" name="md_comments_settings[badge_member]" value="<?php echo esc_attr( $badge_member ); ?>" class="w-full border-gray-300 rounded-xl p-3" placeholder="عضو سایت">
                                    </div>
                                </div>
                            </div>

                            <!-- ═══ تب مدیریت آواتارها ═══ -->
                            <div x-show="activeTab === 'avatars'" x-transition:enter="transition ease-out duration-200" class="space-y-6">
                                <h3 class="text-xl font-bold text-primary border-b pb-3 mb-4">مدیریت آواتارهای پویا</h3>
                                <p class="text-gray-600 text-sm mb-6">در این بخش می‌توانید آواتارهای دلخواه برای فرم نظرات، ادمین و اعضای سایت آپلود نمایید. در صورت عدم آپلود، سیستم از آواتارهای پیش‌فرض استفاده خواهد کرد.</p>
                                
                                <!-- آواتارهای فرم کاربر (۴ مورد) -->
                                <div class="bg-gray-50 p-6 rounded-2xl border border-gray-200">
                                    <h4 class="text-lg font-bold text-primary mb-4 flex items-center gap-2">
                                        <span class="w-2 h-6 bg-gold rounded-full"></span>
                                        آواتارهای انتخابی برای فرم دیدگاه کاربران (۴ آواتار)
                                    </h4>
                                    
                                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-6">
                                        <?php for ( $i = 1; $i <= 4; $i++ ) : 
                                            $option_name = 'avatar_user_' . $i . '_id';
                                            $val = isset( $settings[$option_name] ) ? intval( $settings[$option_name] ) : 0;
                                            $img_url = $val ? wp_get_attachment_url( $val ) : '';
                                        ?>
                                            <div class="md-avatar-upload-box flex flex-col items-center p-4 bg-white border border-gray-300 rounded-xl">
                                                <span class="text-xs font-bold text-gray-400 mb-2">آواتار شماره <?php echo $i; ?></span>
                                                
                                                <div class="w-20 h-20 rounded-full border-2 border-dashed border-gray-300 flex items-center justify-center overflow-hidden mb-3 bg-gray-50 relative">
                                                    <!-- پیش‌فرض در صورت عدم آپلود -->
                                                    <div class="md-avatar-placeholder-svg" style="<?php echo $img_url ? 'display:none' : ''; ?>">
                                                        <?php echo $this->get_default_avatar_svg( $i, 'w-12 h-12 text-gray-400 opacity-60' ); ?>
                                                    </div>
                                                    <!-- تصویر آپلود شده -->
                                                    <img class="md-avatar-preview w-full h-full object-cover" src="<?php echo esc_url($img_url); ?>" style="<?php echo $img_url ? '' : 'display:none'; ?>" alt="آواتار <?php echo $i; ?>" />
                                                </div>

                                                <input type="hidden" name="md_comments_settings[<?php echo $option_name; ?>]" value="<?php echo $val ? $val : ''; ?>" class="md-avatar-id-input">
                                                
                                                <div class="flex gap-2">
                                                    <button type="button" class="md-upload-btn bg-primary hover:bg-primary/95 text-white text-xs py-1.5 px-3 rounded-lg font-bold transition-all">انتخاب</button>
                                                    <button type="button" class="md-remove-btn bg-red-500 hover:bg-red-600 text-white text-xs py-1.5 px-3 rounded-lg font-bold transition-all" style="<?php echo $img_url ? '' : 'display:none'; ?>">حذف</button>
                                                </div>
                                            </div>
                                        <?php endfor; ?>
                                    </div>
                                </div>

                                <!-- آواتار اختصاصی ادمین و اعضا -->
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 pt-4">
                                    <!-- آواتار ادمین -->
                                    <div class="bg-gray-50 p-6 rounded-2xl border border-gray-200 md-avatar-upload-box">
                                        <h4 class="text-lg font-bold text-primary mb-4 flex items-center gap-2">
                                            <span class="w-2 h-6 bg-teal rounded-full"></span>
                                            آواتار اختصاصی ادمین سایت
                                        </h4>
                                        <div class="flex items-center gap-6">
                                            <div class="w-24 h-24 rounded-full border-2 border-dashed border-gray-300 flex items-center justify-center overflow-hidden bg-white relative shrink-0">
                                                <?php 
                                                    $admin_img_url = $avatar_admin_id ? wp_get_attachment_url( $avatar_admin_id ) : '';
                                                ?>
                                                <div class="md-avatar-placeholder-svg" style="<?php echo $admin_img_url ? 'display:none' : ''; ?>">
                                                    <svg class="w-14 h-14 text-teal opacity-60" viewBox="0 0 40 40">
                                                      <rect x="4" y="4" width="32" height="32" rx="8" fill="#009c8f" opacity="0.9"/>
                                                      <path d="M14 20 L18 24 L26 16" stroke="#fff" stroke-width="2.5" fill="none" stroke-linecap="round" stroke-linejoin="round"/>
                                                    </svg>
                                                </div>
                                                <img class="md-avatar-preview w-full h-full object-cover" src="<?php echo esc_url($admin_img_url); ?>" style="<?php echo $admin_img_url ? '' : 'display:none'; ?>" alt="آواتار ادمین" />
                                            </div>
                                            <div>
                                                <p class="text-sm text-gray-500 mb-3">این تصویر به عنوان آواتار پاسخ‌دهنده ادمین در فرانت‌اند نمایش داده می‌شود.</p>
                                                <input type="hidden" name="md_comments_settings[avatar_admin_id]" value="<?php echo $avatar_admin_id ? $avatar_admin_id : ''; ?>" class="md-avatar-id-input">
                                                <div class="flex gap-2">
                                                    <button type="button" class="md-upload-btn bg-primary text-white text-xs py-2 px-4 rounded-xl font-bold transition-all">انتخاب تصویر ادمین</button>
                                                    <button type="button" class="md-remove-btn bg-red-500 text-white text-xs py-2 px-4 rounded-xl font-bold transition-all" style="<?php echo $admin_img_url ? '' : 'display:none'; ?>">حذف</button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- آواتار اعضا -->
                                    <div class="bg-gray-50 p-6 rounded-2xl border border-gray-200 md-avatar-upload-box">
                                        <h4 class="text-lg font-bold text-primary mb-4 flex items-center gap-2">
                                            <span class="w-2 h-6 bg-neutral rounded-full"></span>
                                            آواتار اختصاصی اعضای سایت (کاربران لاگین شده)
                                        </h4>
                                        <div class="flex items-center gap-6">
                                            <div class="w-24 h-24 rounded-full border-2 border-dashed border-gray-300 flex items-center justify-center overflow-hidden bg-white relative shrink-0">
                                                <?php 
                                                    $member_img_url = $avatar_member_id ? wp_get_attachment_url( $avatar_member_id ) : '';
                                                ?>
                                                <div class="md-avatar-placeholder-svg" style="<?php echo $member_img_url ? 'display:none' : ''; ?>">
                                                    <svg class="w-14 h-14 text-primary opacity-60" viewBox="0 0 40 40">
                                                      <circle cx="20" cy="15" r="8" fill="#0c2d28" opacity="0.7"/>
                                                      <ellipse cx="20" cy="35" rx="14" ry="10" fill="#0c2d28" opacity="0.4"/>
                                                    </svg>
                                                </div>
                                                <img class="md-avatar-preview w-full h-full object-cover" src="<?php echo esc_url($member_img_url); ?>" style="<?php echo $member_img_url ? '' : 'display:none'; ?>" alt="آواتار اعضا" />
                                            </div>
                                            <div>
                                                <p class="text-sm text-gray-500 mb-3">این تصویر به عنوان آواتار پیش‌فرض برای دیدگاه‌های اعضا و کاربران لاگین شده سایت نمایش داده می‌شود.</p>
                                                <input type="hidden" name="md_comments_settings[avatar_member_id]" value="<?php echo $avatar_member_id ? $avatar_member_id : ''; ?>" class="md-avatar-id-input">
                                                <div class="flex gap-2">
                                                    <button type="button" class="md-upload-btn bg-primary text-white text-xs py-2 px-4 rounded-xl font-bold transition-all">انتخاب تصویر اعضا</button>
                                                    <button type="button" class="md-remove-btn bg-red-500 text-white text-xs py-2 px-4 rounded-xl font-bold transition-all" style="<?php echo $member_img_url ? '' : 'display:none'; ?>">حذف</button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- ═══ تب تنظیمات پیام‌رسان‌ها ═══ -->
                            <div x-show="activeTab === 'messengers'" x-transition:enter="transition ease-out duration-200" class="space-y-6">
                                <h3 class="text-xl font-bold text-primary border-b pb-3 mb-6">تنظیمات اتصال وب‌هوک پیام‌رسان‌ها</h3>
                                
                                <div class="bg-gray-50 p-6 rounded-2xl border border-gray-200">
                                    <h4 class="text-lg font-bold text-primary mb-4 flex items-center gap-2">
                                        <span class="w-2.5 h-2.5 rounded-full bg-blue-500"></span>
                                        وب‌هوک اطلاع‌رسانی تلگرام
                                    </h4>
                                    <div class="grid grid-cols-1 gap-4">
                                        <div class="flex items-center gap-2">
                                            <span class="text-gray-700 font-bold">فعالسازی وب‌هوک تلگرام:</span>
                                            <input type="checkbox" name="md_comments_settings[telegram_enabled]" value="yes" <?php checked( $telegram_enabled, 'yes' ); ?> class="rounded text-primary border-gray-300 focus:ring-primary h-5 w-5">
                                        </div>
                                        <div>
                                            <label class="block text-gray-700 font-bold mb-1">آدرس وب‌هوک تلگرام (حتما با HTTPS شروع شود)</label>
                                            <input type="url" name="md_comments_settings[telegram_webhook]" value="<?php echo esc_url( $telegram_webhook ); ?>" class="w-full border-gray-300 rounded-xl p-3 text-left" placeholder="https://api.telegram.org/bot...">
                                        </div>
                                    </div>
                                </div>

                                <div class="bg-gray-50 p-6 rounded-2xl border border-gray-200">
                                    <h4 class="text-lg font-bold text-primary mb-4 flex items-center gap-2">
                                        <span class="w-2.5 h-2.5 rounded-full bg-green-500"></span>
                                        ربات اطلاع‌رسانی پیام‌رسان بله (Bale Bot API)
                                    </h4>
                                    <div class="grid grid-cols-1 gap-4">
                                        <div class="flex items-center gap-2">
                                            <span class="text-gray-700 font-bold">فعالسازی ارسال به پیام‌رسان بله:</span>
                                            <input type="checkbox" name="md_comments_settings[bale_enabled]" value="yes" <?php checked( $bale_enabled, 'yes' ); ?> class="rounded text-primary border-gray-300 focus:ring-primary h-5 w-5">
                                        </div>
                                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                            <div>
                                                <label class="block text-gray-700 font-bold mb-1">توکن ربات بله (Bazoo Bot Token)</label>
                                                <input type="text" name="md_comments_settings[bale_bot_token]" value="<?php echo esc_attr( $bale_bot_token ); ?>" class="w-full border-gray-300 rounded-xl p-3 text-left" placeholder="123456789:ABCdefGhIJKlmNoPQRsT...">
                                            </div>
                                            <div>
                                                <label class="block text-gray-700 font-bold mb-1">شناسه چت یا شماره (Chat ID / Receive ID)</label>
                                                <input type="text" name="md_comments_settings[bale_chat_id]" value="<?php echo esc_attr( $bale_chat_id ); ?>" class="w-full border-gray-300 rounded-xl p-3 text-left" placeholder="مثال: 987654321">
                                            </div>
                                        </div>
                                        <div class="mt-2 flex flex-col md:flex-row items-start md:items-center justify-between gap-3 bg-white p-4 rounded-xl border border-gray-100 shadow-sm">
                                            <div class="text-xs text-gray-500 leading-6">
                                                💡 <strong>راهنما:</strong> شناسه چت باید یک <strong>شناسه عددی</strong> باشد (مانند <code>123456789</code>) نه آی‌دی ربات یا یوزرنیم (مانند <code>@username</code>).<br>
                                                برای دریافت شناسه چت عددی خود، ربات <code>@userinfobot</code> را در بله جستجو و استارت نمایید.
                                            </div>
                                            <div class="flex gap-2">
                                                <button type="button" 
                                                        @click="testBaleConnection()" 
                                                        :disabled="isTestingBale"
                                                        class="bg-teal hover:bg-teal/90 text-white text-xs font-bold py-2.5 px-5 rounded-xl flex items-center gap-2 transition-all cursor-pointer shadow-sm shrink-0">
                                                    <span x-show="!isTestingBale">📤 تست اتصال بله</span>
                                                    <span x-show="isTestingBale">در حال ارسال پیام تست...</span>
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- دکمه ذخیره -->
                            <div class="mt-8 pt-6 border-t flex items-center justify-between">
                                <button type="submit" 
                                        :disabled="isSubmitting"
                                        class="bg-gold hover:bg-gold/90 text-white font-bold py-3 px-8 rounded-xl flex items-center gap-3 transition-all cursor-pointer shadow-md"
                                        :class="isSubmitting && 'opacity-60 cursor-not-allowed'">
                                    <span x-show="!isSubmitting">ذخیره تنظیمات</span>
                                    <span x-show="isSubmitting">در حال ذخیره‌سازی...</span>
                                    <svg x-show="!isSubmitting" class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"></path>
                                    </svg>
                                </button>
                            </div>
                        </form>

                        <!-- تب مدیریت نظرات کاربران -->
                        <div x-show="activeTab === 'comments'" class="space-y-6" style="display: none;">
                            <div class="flex items-center justify-between border-b pb-3 mb-6">
                                <h3 class="text-xl font-bold text-primary m-0">مدیریت نظرات سفارشی کاربران</h3>
                                <button type="button" @click="fetchComments()" class="bg-primary hover:bg-primary/95 text-white text-xs py-2 px-4 rounded-xl font-bold transition-all flex items-center gap-2 cursor-pointer">
                                    <svg class="w-4 h-4 text-gold" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" :class="loadingComments && 'animate-spin'">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0l3.181 3.183a8.25 8.25 0 0013.803-3.7M4.031 9.865a8.25 8.25 0 0113.803-3.7l3.181 3.182m0-4.991v4.99"></path>
                                    </svg>
                                    بروزرسانی لیست
                                </button>
                            </div>

                            <!-- در حال بارگذاری -->
                            <div x-show="loadingComments" class="flex flex-col items-center justify-center py-20 text-gray-500">
                                <svg class="w-10 h-10 animate-spin text-gold mb-3" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                                <span>در حال دریافت نظرات...</span>
                            </div>

                            <!-- خالی بودن لیست -->
                            <div x-show="!loadingComments && comments.length === 0" class="text-center py-20 text-gray-500 bg-gray-50 rounded-2xl border border-dashed border-gray-300">
                                <svg class="w-16 h-16 text-gray-300 mx-auto mb-4" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M8.625 12a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0H8.25m4.125 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0H12m4.125 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0h-.375M21 12c0 4.556-4.03 8.25-9 8.25a9.764 9.764 0 01-2.555-.337A5.972 5.972 0 015.41 20.97a5.969 5.969 0 01-.474-.065 4.48 4.48 0 00.978-2.025c.09-.457-.133-.901-.467-1.226C3.93 16.178 3 14.189 3 12c0-4.556 4.03-8.25 9-8.25s9 3.694 9 8.25z"></path>
                                </svg>
                                <span class="text-lg font-bold">هیچ دیدگاهی یافت نشد.</span>
                            </div>

                            <!-- جدول نظرات -->
                            <div x-show="!loadingComments && comments.length > 0" class="overflow-x-auto bg-white rounded-xl border border-gray-200 shadow-sm">
                                <table class="w-full text-right border-collapse table-fixed">
                                    <thead>
                                        <tr class="bg-primary/5 border-b border-gray-200 text-primary font-bold text-sm">
                                            <th class="p-4 w-40">مشخصات کاربر</th>
                                            <th class="p-4 w-32">متن دیدگاه</th>
                                            <th class="p-4 w-28 text-center">امتیاز</th>
                                            <th class="p-4 w-44">صفحه مربوطه</th>
                                            <th class="p-4 w-28 text-center">تاریخ ثبت</th>
                                            <th class="p-4 w-24 text-center">وضعیت</th>
                                            <th class="p-4 w-36 text-center">عملیات</th>
                                        </tr>
                                    </thead>
                                    <tbody class="text-sm divide-y divide-gray-100 text-gray-800">
                                        <template x-for="comment in comments" :key="comment.id">
                                            <tr class="hover:bg-gray-50/50 transition-colors" :class="comment.parent_id > 0 ? 'bg-teal/5 border-r-4 border-teal' : ''">
                                                <!-- مشخصات -->
                                                <td class="p-4">
                                                    <div class="font-bold text-gray-900 truncate" x-text="comment.user_name"></div>
                                                    <div class="text-xs text-gray-500 mt-1 flex items-center gap-1" x-show="comment.user_phone">
                                                        <svg class="w-3.5 h-3.5 text-gray-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 1.5H8.25A2.25 2.25 0 006 3.75v16.5a2.25 2.25 0 002.25 2.25h7.5A2.25 2.25 0 0018 20.25V3.75a2.25 2.25 0 00-2.25-2.25H13.5m-3 0V3h3V1.5m-3 0h3m-3 18.75h3"></path>
                                                        </svg>
                                                        <span class="direction-ltr" x-text="comment.user_phone"></span>
                                                    </div>
                                                    <span class="bg-teal/10 text-teal text-[10px] py-0.5 px-2 rounded-full font-bold mt-1 inline-block" x-show="comment.parent_id > 0">پاسخ ادمین</span>
                                                </td>
                                                
                                                <!-- متن دیدگاه -->
                                                <td class="p-4">
                                                    <div x-text="comment.comment_text.trim().split(/\s+/).slice(0, 2).join(' ') + (comment.comment_text.trim().split(/\s+/).length > 2 ? ' ...' : '')" 
                                                         class="text-xs text-gray-500 font-medium truncate"
                                                         :title="comment.comment_text"></div>
                                                </td>
                                                
                                                <!-- امتیاز -->
                                                <td class="p-4 text-center">
                                                    <div class="flex gap-0.5 text-gold justify-center" dir="ltr">
                                                        <template x-for="i in 5">
                                                            <svg class="w-3.5 h-3.5 fill-current" :class="i <= comment.rating ? 'text-gold' : 'text-gray-200'" viewBox="0 0 20 20">
                                                                <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"></path>
                                                            </svg>
                                                        </template>
                                                    </div>
                                                </td>
                                                
                                                <!-- صفحه مربوطه -->
                                                <td class="p-4">
                                                    <a :href="comment.permalink" target="_blank" class="text-blue-600 hover:text-blue-800 hover:underline font-semibold truncate block" x-text="comment.post_title || ('صفحه با شناسه ' + comment.post_id)" :title="comment.post_title"></a>
                                                </td>
                                                
                                                <!-- تاریخ -->
                                                <td class="p-4 text-xs text-gray-500 whitespace-nowrap text-center" x-text="comment.created_at_human"></td>
                                                
                                                <!-- وضعیت -->
                                                <td class="p-4 text-center">
                                                    <span class="inline-block py-1 px-3 rounded-full text-xs font-bold border whitespace-nowrap" 
                                                          :class="comment.status === 'approved' ? 'bg-green-50 text-green-700 border-green-200/60' : 'bg-yellow-50 text-yellow-700 border-yellow-200/60'"
                                                          x-text="comment.status === 'approved' ? 'تایید شده' : 'در انتظار تایید'">
                                                    </span>
                                                </td>
                                                
                                                <!-- عملیات -->
                                                <td class="p-4 text-center">
                                                    <div class="flex items-center justify-center gap-1.5 flex-nowrap whitespace-nowrap">
                                                        <!-- دکمه تایید / لغو تایید -->
                                                        <template x-if="comment.status === 'approved'">
                                                            <button type="button" 
                                                                    @click="changeStatus(comment.id, 'hold')" 
                                                                    class="p-1.5 rounded-lg border border-yellow-200 text-yellow-700 bg-yellow-50/50 hover:bg-yellow-100 hover:border-yellow-300 transition-all cursor-pointer"
                                                                    title="لغو تایید">
                                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9.75 9.75l4.5 4.5m0-4.5l-4.5 4.5M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                                                </svg>
                                                            </button>
                                                        </template>
                                                        <template x-if="comment.status !== 'approved'">
                                                            <button type="button" 
                                                                    @click="changeStatus(comment.id, 'approved')" 
                                                                    class="p-1.5 rounded-lg border border-green-200 text-green-700 bg-green-50/50 hover:bg-green-100 hover:border-green-300 transition-all cursor-pointer"
                                                                    title="تایید دیدگاه">
                                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                                                </svg>
                                                            </button>
                                                        </template>

                                                        <!-- دکمه نمایش جزئیات -->
                                                        <button type="button" 
                                                                @click="openViewModal(comment)" 
                                                                class="p-1.5 rounded-lg border border-teal-200 text-teal-700 bg-teal-50/50 hover:bg-teal-100 hover:border-teal-300 transition-all cursor-pointer"
                                                                title="نمایش جزئیات دیدگاه">
                                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z" />
                                                                <circle cx="12" cy="12" r="3" />
                                                            </svg>
                                                        </button>

                                                        <!-- دکمه پاسخ -->
                                                        <button type="button" 
                                                                x-show="comment.parent_id == 0"
                                                                @click="openReplyModal(comment)" 
                                                                class="p-1.5 rounded-lg border border-blue-200 text-blue-700 bg-blue-50/50 hover:bg-blue-100 hover:border-blue-300 transition-all cursor-pointer"
                                                                title="ارسال پاسخ به کاربر">
                                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 15L3 9m0 0l6-6M3 9h12a6 6 0 010 12h-3" />
                                                            </svg>
                                                        </button>

                                                        <!-- دکمه ویرایش -->
                                                        <button type="button" 
                                                                @click="openEditModal(comment)" 
                                                                class="p-1.5 rounded-lg border border-purple-200 text-purple-700 bg-purple-50/50 hover:bg-purple-100 hover:border-purple-300 transition-all cursor-pointer"
                                                                title="ویرایش دیدگاه">
                                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L6.832 19.82a4.5 4.5 0 01-1.897 1.13l-2.685.8.8-2.685a4.5 4.5 0 011.13-1.897L16.863 4.487zm0 0L19.5 7.125" />
                                                            </svg>
                                                        </button>

                                                        <!-- دکمه حذف -->
                                                        <button type="button" 
                                                                @click="deleteComment(comment.id)" 
                                                                class="p-1.5 rounded-lg border border-red-200 text-red-700 bg-red-50/50 hover:bg-red-100 hover:border-red-300 transition-all cursor-pointer"
                                                                title="حذف دائمی دیدگاه">
                                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                            </svg>
                                                        </button>
                                                    </div>
                                                </td>
                                            </tr>
                                        </template>
                                    </tbody>
                                </table>
                            </div>

                            <!-- مودال پاسخ -->
                            <div x-show="replyingTo !== null" 
                                 class="fixed inset-0 bg-black/60 z-[9999] flex items-center justify-center p-4" 
                                 x-transition
                                 style="display: none;">
                                <div class="bg-white rounded-2xl max-w-lg w-full overflow-hidden shadow-2xl border border-gray-100 flex flex-col" @click.away="closeReplyModal()">
                                    <div class="bg-primary p-6 text-white flex items-center justify-between border-b-2 border-gold">
                                        <h4 class="text-lg font-bold m-0 flex items-center gap-2">
                                            <svg class="w-5 h-5 text-gold" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 15L3 9m0 0l6-6M3 9h12a6 6 0 010 12h-3"></path>
                                            </svg>
                                            <span>ارسال پاسخ کارشناس به <span class="text-gold font-black" x-text="replyingTo ? replyingTo.user_name : ''"></span></span>
                                        </h4>
                                        <button type="button" @click="closeReplyModal()" class="text-white/80 hover:text-white bg-transparent border-none text-2xl font-bold cursor-pointer">&times;</button>
                                    </div>
                                    <div class="p-6 space-y-4 text-right">
                                        <div class="bg-gray-50 p-4 rounded-xl border border-gray-200">
                                            <span class="text-xs text-gray-400 font-bold block mb-1">متن دیدگاه کاربر:</span>
                                            <p class="text-sm text-gray-700 m-0" x-text="replyingTo ? replyingTo.comment_text : ''"></p>
                                        </div>
                                        <div>
                                            <label class="block text-sm text-gray-700 font-bold mb-2">متن پاسخ شما:</label>
                                            <textarea x-model="replyText" placeholder="پاسخ خود را بنویسید..." class="w-full border border-gray-300 rounded-xl p-3 focus:ring-primary focus:border-primary outline-none" rows="5"></textarea>
                                        </div>
                                    </div>
                                    <div class="bg-gray-50 p-6 border-t flex items-center justify-end gap-3">
                                        <button type="button" @click="closeReplyModal()" class="bg-gray-300 hover:bg-gray-400 text-gray-800 font-bold py-2 px-6 rounded-xl transition-all cursor-pointer">انصراف</button>
                                        <button type="button" @click="submitReply()" class="bg-gold hover:bg-gold/90 text-white font-bold py-2 px-6 rounded-xl transition-all cursor-pointer">ارسال پاسخ</button>
                                    </div>
                                </div>
                            </div>

                            <!-- مودال ویرایش دیدگاه -->
                            <div x-show="editingComment !== null" 
                                 class="fixed inset-0 bg-black/60 z-[9999] flex items-center justify-center p-4" 
                                 x-transition
                                 style="display: none;">
                                <div class="bg-white rounded-2xl max-w-lg w-full overflow-hidden shadow-2xl border border-gray-100 flex flex-col" @click.away="closeEditModal()">
                                    <div class="bg-primary p-6 text-white flex items-center justify-between border-b-2 border-gold">
                                        <h4 class="text-lg font-bold m-0 flex items-center gap-2">
                                            <svg class="w-5 h-5 text-gold" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L6.832 19.82a4.5 4.5 0 01-1.897 1.13l-2.685.8.8-2.685a4.5 4.5 0 011.13-1.897L16.863 4.487zm0 0L19.5 7.125"/>
                                            </svg>
                                            <span>ویرایش دیدگاه کاربر</span>
                                        </h4>
                                        <button type="button" @click="closeEditModal()" class="text-white/80 hover:text-white bg-transparent border-none text-2xl font-bold cursor-pointer">&times;</button>
                                    </div>
                                    <div class="p-6 space-y-4 text-right overflow-y-auto max-h-[70vh]">
                                        <div class="grid grid-cols-2 gap-4">
                                            <div>
                                                <label class="block text-sm text-gray-700 font-bold mb-2">نام کاربر:</label>
                                                <input type="text" x-model="editData.user_name" class="w-full border border-gray-300 rounded-xl p-3 focus:ring-primary focus:border-primary outline-none text-sm" />
                                            </div>
                                            <div>
                                                <label class="block text-sm text-gray-700 font-bold mb-2">شماره تماس:</label>
                                                <input type="text" x-model="editData.user_phone" class="w-full border border-gray-300 rounded-xl p-3 focus:ring-primary focus:border-primary outline-none text-sm direction-ltr text-right" />
                                            </div>
                                        </div>

                                        <div class="grid grid-cols-2 gap-4">
                                            <div>
                                                <label class="block text-sm text-gray-700 font-bold mb-2">امتیاز:</label>
                                                <select x-model="editData.rating" class="w-full border border-gray-300 rounded-xl p-3 focus:ring-primary focus:border-primary outline-none text-sm">
                                                    <option value="1">۱ ستاره</option>
                                                    <option value="2">۲ ستاره</option>
                                                    <option value="3">۳ ستاره</option>
                                                    <option value="4">۴ ستاره</option>
                                                    <option value="5">۵ ستاره</option>
                                                </select>
                                            </div>
                                            <div>
                                                <label class="block text-sm text-gray-700 font-bold mb-2">وضعیت دیدگاه:</label>
                                                <select x-model="editData.status" class="w-full border border-gray-300 rounded-xl p-3 focus:ring-primary focus:border-primary outline-none text-sm">
                                                    <option value="approved">تایید شده</option>
                                                    <option value="hold">در انتظار تایید</option>
                                                </select>
                                            </div>
                                        </div>

                                        <div>
                                            <label class="block text-sm text-gray-700 font-bold mb-2">انتخاب آواتار:</label>
                                            <div class="flex gap-4 items-center bg-gray-50 p-4 rounded-xl border border-gray-200">
                                                <template x-for="id in [1, 2, 3, 4]">
                                                    <button type="button" 
                                                            @click="editData.avatar_id = id"
                                                            class="w-12 h-12 rounded-full border-2 flex items-center justify-center cursor-pointer transition-all overflow-hidden bg-white shadow-sm"
                                                            :class="editData.avatar_id == id ? 'border-gold bg-gold/10 scale-105' : 'border-gray-200 hover:border-gray-400'">
                                                        <div x-html="avatarFallbacks[id]"></div>
                                                    </button>
                                                </template>
                                            </div>
                                        </div>

                                        <div>
                                            <label class="block text-sm text-gray-700 font-bold mb-2">متن دیدگاه:</label>
                                            <textarea x-model="editData.comment_text" class="w-full border border-gray-300 rounded-xl p-3 focus:ring-primary focus:border-primary outline-none text-sm" rows="4"></textarea>
                                        </div>
                                    </div>
                                    <div class="bg-gray-50 p-6 border-t flex items-center justify-end gap-3">
                                        <button type="button" @click="closeEditModal()" class="bg-gray-300 hover:bg-gray-400 text-gray-800 font-bold py-2 px-6 rounded-xl transition-all cursor-pointer">انصراف</button>
                                        <button type="button" @click="submitEdit()" class="bg-gold hover:bg-gold/90 text-white font-bold py-2 px-6 rounded-xl transition-all cursor-pointer">ذخیره تغییرات</button>
                                    </div>
                                </div>
                            </div>

                            <!-- مودال نمایش جزئیات دیدگاه -->
                            <div x-show="viewingComment.id" 
                                 class="fixed inset-0 bg-black/60 z-[9999] flex items-center justify-center p-4" 
                                 x-transition
                                 style="display: none;">
                                <div class="bg-white rounded-2xl max-w-lg w-full overflow-hidden shadow-2xl border border-gray-100 flex flex-col" @click.away="closeViewModal()">
                                    <div class="bg-primary p-6 text-white flex items-center justify-between border-b-2 border-gold">
                                        <h4 class="text-lg font-bold m-0 flex items-center gap-2">
                                            <svg class="w-5 h-5 text-gold" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                            </svg>
                                            <span>جزئیات کامل دیدگاه</span>
                                        </h4>
                                        <button type="button" @click="closeViewModal()" class="text-white/80 hover:text-white bg-transparent border-none text-2xl font-bold cursor-pointer">&times;</button>
                                    </div>
                                    <div class="p-6 space-y-4 text-right overflow-y-auto max-h-[75vh]">
                                        <div class="flex items-center gap-4 bg-gray-50 p-4 rounded-xl border border-gray-200">
                                            <div class="w-14 h-14 rounded-full border border-gray-300 bg-white flex items-center justify-center overflow-hidden shadow-sm" x-html="viewingComment.avatar_html"></div>
                                            <div>
                                                <div class="font-bold text-base text-gray-900" x-text="viewingComment.user_name"></div>
                                                <div class="text-sm text-gray-500 mt-1 flex items-center gap-1" x-show="viewingComment.user_phone">
                                                    <span class="font-bold text-gray-400">شماره تماس:</span>
                                                    <span x-text="viewingComment.user_phone" class="direction-ltr"></span>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="grid grid-cols-2 gap-4">
                                            <div class="bg-gray-50 p-3 rounded-xl border border-gray-100">
                                                <span class="text-xs text-gray-400 font-bold block mb-1">تاریخ ثبت:</span>
                                                <span class="text-sm text-gray-700 font-semibold" x-text="viewingComment.created_at_human"></span>
                                            </div>
                                            <div class="bg-gray-50 p-3 rounded-xl border border-gray-100">
                                                <span class="text-xs text-gray-400 font-bold block mb-1">وضعیت:</span>
                                                <span class="inline-block py-0.5 px-2 rounded-full text-xs font-bold" 
                                                      :class="viewingComment.status === 'approved' ? 'bg-green-50 text-green-700 border border-green-200' : 'bg-yellow-50 text-yellow-700 border border-yellow-200'"
                                                      x-text="viewingComment.status === 'approved' ? 'تایید شده' : 'در انتظار تایید'"></span>
                                            </div>
                                        </div>

                                        <div class="grid grid-cols-2 gap-4">
                                            <div class="bg-gray-50 p-3 rounded-xl border border-gray-100">
                                                <span class="text-xs text-gray-400 font-bold block mb-1">امتیاز:</span>
                                                <div class="flex gap-0.5 text-gold" dir="ltr">
                                                    <template x-for="i in 5">
                                                        <svg class="w-3.5 h-3.5 fill-current" :class="i <= viewingComment.rating ? 'text-gold' : 'text-gray-200'" viewBox="0 0 20 20">
                                                            <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"></path>
                                                        </svg>
                                                    </template>
                                                </div>
                                            </div>
                                            <div class="bg-gray-50 p-3 rounded-xl border border-gray-100">
                                                <span class="text-xs text-gray-400 font-bold block mb-1">صفحه هدف:</span>
                                                <a :href="viewingComment.permalink" target="_blank" class="text-blue-600 hover:text-blue-800 hover:underline text-xs font-bold" x-text="viewingComment.post_title || viewingComment.post_id"></a>
                                            </div>
                                        </div>

                                        <div class="bg-gray-50 p-4 rounded-xl border border-gray-200">
                                            <span class="text-xs text-gray-400 font-bold block mb-2">متن دیدگاه:</span>
                                            <p class="text-sm text-gray-800 leading-7 m-0 whitespace-pre-line" x-text="viewingComment.comment_text"></p>
                                        </div>
                                    </div>
                                    <div class="bg-gray-50 p-6 border-t flex items-center justify-end">
                                        <button type="button" @click="closeViewModal()" class="bg-primary text-white font-bold py-2 px-6 rounded-xl transition-all cursor-pointer hover:bg-primary/95">بستن</button>
                                    </div>
                                </div>
                            </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- پیغام اعلان شناور (Toast Alert) -->
            <div class="fixed bottom-10 left-10 p-4 rounded-xl text-white font-bold text-sm shadow-2xl transition-all duration-300"
                 x-show="toastMessage !== ''"
                 x-text="toastMessage"
                 :class="toastType === 'success' ? 'bg-green-600' : 'bg-red-600'"
                 x-transition>
            </div>
        </div>
        <?php
    }

    /**
     * رندر تصویر یا ساختار آواتار بر اساس شناسه انتخابی و نقش کاربر برای مدیریت نظرات
     */
    private function get_avatar_html( $avatar_id, $user_id = 0, $class = 'w-10 h-10' ) {
        $settings = get_option( 'md_comments_settings', [] );

        // ۱. بررسی آواتار عضو یا ادمین
        if ( $user_id > 0 ) {
            $user = get_userdata( $user_id );
            if ( $user && in_array( 'administrator', (array) $user->roles ) ) {
                $admin_avatar_id = isset( $settings['avatar_admin_id'] ) ? intval( $settings['avatar_admin_id'] ) : 0;
                if ( $admin_avatar_id ) {
                    $img_url = wp_get_attachment_url( $admin_avatar_id );
                    if ( $img_url ) {
                        return '<img class="' . esc_attr( $class ) . ' object-cover rounded-full" src="' . esc_url( $img_url ) . '" alt="ادمین" />';
                    }
                }
            } else {
                $member_avatar_id = isset( $settings['avatar_member_id'] ) ? intval( $settings['avatar_member_id'] ) : 0;
                if ( $member_avatar_id ) {
                    $img_url = wp_get_attachment_url( $member_avatar_id );
                    if ( $img_url ) {
                        return '<img class="' . esc_attr( $class ) . ' object-cover rounded-full" src="' . esc_url( $img_url ) . '" alt="عضو سایت" />';
                    }
                }
            }
        }

        // ۲. بررسی آواتارهای ۴گانه کاربر
        $option_key = 'avatar_user_' . intval( $avatar_id ) . '_id';
        $custom_avatar_id = isset( $settings[$option_key] ) ? intval( $settings[$option_key] ) : 0;
        if ( $custom_avatar_id ) {
            $img_url = wp_get_attachment_url( $custom_avatar_id );
            if ( $img_url ) {
                return '<img class="' . esc_attr( $class ) . ' object-cover rounded-full" src="' . esc_url( $img_url ) . '" alt="آواتار" />';
            }
        }

        // ۳. در غیر این صورت، بازگشت به آواتارهای پیش‌فرض SVG
        return $this->get_default_avatar_svg( $avatar_id, $class );
    }

    /**
     * پیش‌فرض آواتارهای SVG برای پنل ادمین
     */
    private function get_default_avatar_svg( $id, $class = 'w-12 h-12' ) {
        switch ( intval( $id ) ) {
            case 2:
                return '<svg class="' . esc_attr( $class ) . '" viewBox="0 0 40 40"><rect x="8" y="6" width="24" height="24" rx="6" fill="#009c8f" opacity="0.6"/><circle cx="20" cy="18" r="5" fill="#009c8f" opacity="0.8"/></svg>';
            case 3:
                return '<svg class="' . esc_attr( $class ) . '" viewBox="0 0 40 40"><polygon points="20,4 36,30 4,30" fill="#0c2d28" opacity="0.5"/><circle cx="20" cy="21" r="4" fill="#0c2d28" opacity="0.7"/></svg>';
            case 4:
                return '<svg class="' . esc_attr( $class ) . '" viewBox="0 0 40 40"><circle cx="20" cy="20" r="14" fill="#c39854" opacity="0.3"/><circle cx="14" cy="17" r="2.5" fill="#c39854" opacity="0.7"/><circle cx="26" cy="17" r="2.5" fill="#c39854" opacity="0.7"/><path d="M14 25 Q20 30 26 25" stroke="#c39854" stroke-width="1.5" fill="none" opacity="0.6"/></svg>';
            case 1:
            default:
                return '<svg class="' . esc_attr( $class ) . '" viewBox="0 0 40 40"><circle cx="20" cy="15" r="8" fill="#c39854" opacity="0.7"/><ellipse cx="20" cy="35" rx="14" ry="10" fill="#c39854" opacity="0.4"/></svg>';
        }
    }
}
