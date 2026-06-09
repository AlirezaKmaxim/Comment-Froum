<?php
namespace MDCustomComments\Core;

defined( 'ABSPATH' ) || exit;

class Plugin {
    private static $instance = null;
    private $services = [];

    public static function instance() {
        if ( is_null( self::$instance ) ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $this->bootstrap();
    }

    private function bootstrap() {
        // اطمینان از نصب/آپدیت دیتابیس در صورت نیاز
        $db_version = get_option( 'md_comments_db_version', '0' );
        if ( version_compare( $db_version, MD_CUSTOM_COMMENTS_VERSION, '<' ) ) {
            \MDCustomComments\Core\Installer::install();
            update_option( 'md_comments_db_version', MD_CUSTOM_COMMENTS_VERSION );
        }

        // ۱. مقداردهی اولیه‌ی سرویس‌های فرانت‌اند
        $this->services['front_assets'] = new \MDCustomComments\Front\Assets();
        $this->services['front_controller'] = new \MDCustomComments\Front\Controller();
        $this->services['front_shortcode'] = new \MDCustomComments\Front\Shortcode();

        // ۲. مقداردهی سرویس‌های ادمین
        if ( is_admin() ) {
            $this->services['admin_assets'] = new \MDCustomComments\Admin\Assets();
            $this->services['admin_settings'] = new \MDCustomComments\Admin\Controllers\SettingsController();
        }

        // ۳. مقداردهی رویدادها و شنونده‌ها (EDA)
        $this->register_events();
    }

    private function register_events() {
        // اکشن‌های سفارشی برای وب‌هوک‌ها
        add_action( 'md_comment_inserted', [ $this, 'dispatch_comment_events' ], 10, 2 );
        
        // هندلرهای ارسال غیرهمزمان
        add_action( 'md_send_telegram_webhook_async', [ $this, 'handle_telegram_async' ], 10, 2 );
        add_action( 'md_send_bale_async', [ $this, 'handle_bale_async' ], 10, 3 );
        add_action( 'md_send_slack_webhook_async', [ $this, 'handle_slack_async' ], 10, 2 );
    }

    public function handle_telegram_async( $webhook_url, $message ) {
        $telegram = new \MDCustomComments\Events\Listeners\SendTelegram();
        $telegram->send_request( $webhook_url, $message );
    }

    public function handle_bale_async( $bot_token, $chat_id, $message ) {
        $bale = new \MDCustomComments\Events\Listeners\SendBale();
        $bale->send_request( $bot_token, $chat_id, $message );
    }

    public function handle_slack_async( $webhook_url, $payload ) {
        $slack = new \MDCustomComments\Events\Listeners\SendSlack();
        $decoded_payload = json_decode( $payload, true );
        if ( is_array( $decoded_payload ) ) {
            $slack->send_request( $webhook_url, $decoded_payload );
        }
    }

    public function dispatch_comment_events( $comment_id, $comment_data ) {
        // نمونه‌سازی و اجرای لیسنرهای تلگرام، بله و اسلک
        $telegram = new \MDCustomComments\Events\Listeners\SendTelegram();
        $telegram->dispatch( $comment_id, $comment_data );

        $bale = new \MDCustomComments\Events\Listeners\SendBale();
        $bale->dispatch( $comment_id, $comment_data );

        $slack = new \MDCustomComments\Events\Listeners\SendSlack();
        $slack->dispatch( $comment_id, $comment_data );
    }

    public function get_service( $key ) {
        return isset( $this->services[$key] ) ? $this->services[$key] : null;
    }
}
