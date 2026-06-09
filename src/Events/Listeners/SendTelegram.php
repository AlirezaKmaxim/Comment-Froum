<?php
namespace MDCustomComments\Events\Listeners;

defined( 'ABSPATH' ) || exit;

class SendTelegram {
    /**
     * ارسال وب‌هوک به تلگرام
     */
    public function dispatch( $comment_id, $comment_data ) {
        $settings = get_option( 'md_comments_settings', [] );
        
        $enabled = ( defined( 'MD_COMMENTS_TELEGRAM_WEBHOOK' ) && MD_COMMENTS_TELEGRAM_WEBHOOK ) ? 'yes' : ( isset( $settings['telegram_enabled'] ) ? $settings['telegram_enabled'] : 'no' );
        $webhook_url = defined( 'MD_COMMENTS_TELEGRAM_WEBHOOK' ) ? MD_COMMENTS_TELEGRAM_WEBHOOK : ( isset( $settings['telegram_webhook'] ) ? $settings['telegram_webhook'] : '' );

        if ( $enabled !== 'yes' || empty( $webhook_url ) ) {
            return;
        }

        // پیام ارسالی به تلگرام
        $message = "💬 *دیدگاه جدید در سایت ثبت شد!*\n\n";
        $message .= "👤 *نام فرستنده:* " . esc_html( $comment_data['name'] ) . "\n";
        if ( ! empty( $comment_data['phone'] ) ) {
            $message .= "📞 *شماره تماس:* " . esc_html( $comment_data['phone'] ) . "\n";
        }
        $message .= "⭐️ *امتیاز ثبت شده:* " . intval( $comment_data['rating'] ) . " ستاره\n";
        $message .= "📝 *متن دیدگاه:*\n" . esc_html( $comment_data['comment'] ) . "\n\n";
        $message .= "🔗 [مشاهده نوشته جاری](" . get_permalink( $comment_data['post_id'] ) . ")";

        // استفاده از Action Scheduler در صورت وجود، برای ارسال غیرهمزمان (Async) جهت افزایش پرفورمنس
        if ( function_exists( 'as_enqueue_async_action' ) ) {
            as_enqueue_async_action( 'md_send_telegram_webhook_async', [
                'webhook_url' => $webhook_url,
                'message'     => $message,
            ]);
        } else {
            // ارسال غیرهمزمان با WP Cron در صورت عدم وجود Action Scheduler
            wp_schedule_single_event( time(), 'md_send_telegram_webhook_async', [
                'webhook_url' => $webhook_url,
                'message'     => $message,
            ]);
        }
    }

    public function send_request( $webhook_url, $message ) {
        $response = wp_remote_post( $webhook_url, [
            'method'    => 'POST',
            'headers'   => [ 'Content-Type' => 'application/json; charset=utf-8' ],
            'body'      => json_encode( [
                'text'       => $message,
                'parse_mode' => 'Markdown'
            ], JSON_UNESCAPED_UNICODE ),
            'timeout'   => 10,
        ] );

        if ( is_wp_error( $response ) ) {
            $err_msg = $response->get_error_message();
            if ( ! empty( $webhook_url ) ) {
                $err_msg = preg_replace( '/bot[0-9]+:[a-zA-Z0-9_-]+/', 'bot[REDACTED_BOT_TOKEN]', $err_msg );
                $err_msg = str_replace( $webhook_url, '[REDACTED_WEBHOOK_URL]', $err_msg );
            }
            \MDCustomComments\Core\Logger::error( 'MD Comments - Telegram send error: ' . $err_msg );
        }
    }
}
