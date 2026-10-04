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
        $input = isset( $_POST['md_comments_settings'] ) ? wp_unslash( $_POST['md_comments_settings'] ) : [];
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
        
        $page = isset( $_POST['page'] ) ? intval( $_POST['page'] ) : 1;

        $repository = new \MDCustomComments\Database\CommentRepository();
        $result = $repository->get_all_comments_for_admin( $page, 20 );

        foreach ( $result['items'] as &$comment ) {
            $comment['created_at_human'] = $this->human_time_diff_fa( $comment['created_at'] );
            $comment['permalink'] = get_permalink( $comment['post_id'] );
            $comment['avatar_html'] = $this->get_avatar_html( $comment['avatar_id'], $comment['user_id'], 'w-12 h-12' );
        }
        unset( $comment );

        wp_send_json_success( $result );
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
        
        $bot_token = isset( $_POST['bot_token'] ) ? sanitize_text_field( wp_unslash( $_POST['bot_token'] ) ) : '';
        $chat_id = isset( $_POST['chat_id'] ) ? sanitize_text_field( wp_unslash( $_POST['chat_id'] ) ) : '';

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
        $status = isset( $_POST['status'] ) ? sanitize_text_field( wp_unslash( $_POST['status'] ) ) : 'hold';
        
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
        $comment_text = isset( $_POST['comment'] ) ? sanitize_textarea_field( wp_unslash( $_POST['comment'] ) ) : '';
        
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
            'ip_address' => \MDCustomComments\Security\Validator::get_ip_address()
        ];
        
        $repository = new \MDCustomComments\Database\CommentRepository();
        $comment_id = $repository->insert( $data );
        
        if ( $comment_id ) {
            // نکته: پاسخ ادمین به‌طور ضمنی وضعیت کامنت والد را هم به approved تغییر می‌دهد،
            // حتی اگر والد قبلاً hold بوده باشد (یعنی پاسخ‌دادن = تایید ضمنی کامنت اصلی).
            // این رفتار عمدی است (منطقی نیست پاسخ منتشر شود ولی سوال اصلی مخفی بماند)
            // اما جایی به ادمین نمایش داده نمی‌شود؛ اگر تصمیم متفاوتی مد نظر است این خط را تغییر دهید.
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
        $user_name = isset( $_POST['user_name'] ) ? sanitize_text_field( wp_unslash( $_POST['user_name'] ) ) : '';
        $user_phone = isset( $_POST['user_phone'] ) ? sanitize_text_field( wp_unslash( $_POST['user_phone'] ) ) : '';
        $comment_text = isset( $_POST['comment_text'] ) ? sanitize_textarea_field( wp_unslash( $_POST['comment_text'] ) ) : '';
        $rating = isset( $_POST['rating'] ) ? intval( $_POST['rating'] ) : 5;
        $avatar_id = isset( $_POST['avatar_id'] ) ? intval( $_POST['avatar_id'] ) : 1;
        $status = isset( $_POST['status'] ) ? sanitize_text_field( wp_unslash( $_POST['status'] ) ) : 'approved';

        if ( $comment_id <= 0 || empty( $user_name ) || empty( $comment_text ) ) {
            wp_send_json_error( 'نام و متن دیدگاه نمی‌توانند خالی باشند.' );
        }

        // اعتبارسنجی دامنه مقادیر، هماهنگ با Validator::validate_comment_submission()
        // و change_comment_status_ajax تا مقدار نامعتبر در دیتابیس ذخیره نشود.
        if ( $rating < 1 || $rating > 5 ) {
            wp_send_json_error( 'امتیاز باید بین ۱ تا ۵ باشد.' );
        }
        if ( $avatar_id < 1 || $avatar_id > 4 ) {
            wp_send_json_error( 'شناسه آواتار نامعتبر است.' );
        }
        if ( ! in_array( $status, [ 'approved', 'hold' ], true ) ) {
            wp_send_json_error( 'وضعیت دیدگاه نامعتبر است.' );
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
        return \MDCustomComments\Support\PersianFormatter::to_persian_num( $num );
    }

    /**
     * نمایش تاریخ به صورت زمان گذشته
     */
    private function human_time_diff_fa( $datetime ) {
        return \MDCustomComments\Support\PersianFormatter::human_time_diff_fa( $datetime );
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

        include MD_CUSTOM_COMMENTS_PATH . 'src/Admin/Views/settings-page.php';
    }

    /**
     * رندر تصویر یا ساختار آواتار بر اساس شناسه انتخابی و نقش کاربر برای مدیریت نظرات
     */
    private function get_avatar_html( $avatar_id, $user_id = 0, $class = 'w-10 h-10' ) {
        $settings = get_option( 'md_comments_settings', [] );
        return \MDCustomComments\Support\AvatarRenderer::render( $avatar_id, $user_id, $class, $settings, 'admin' );
    }

    /**
     * پیش‌فرض آواتارهای SVG برای پنل ادمین
     */
    private function get_default_avatar_svg( $id, $class = 'w-12 h-12' ) {
        return \MDCustomComments\Support\AvatarRenderer::default_svg( $id, $class, 'admin' );
    }
}
