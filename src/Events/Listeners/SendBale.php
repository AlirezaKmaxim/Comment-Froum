<?php
namespace MDCustomComments\Events\Listeners;

defined( 'ABSPATH' ) || exit;

class SendBale {
    /**
     * ارسال پیام به پیام‌رسان بله
     */
    public function dispatch( $comment_id, $comment_data ) {
        $settings = get_option( 'md_comments_settings', [] );
        
        $has_constants = defined( 'MD_COMMENTS_BALE_BOT_TOKEN' ) && defined( 'MD_COMMENTS_BALE_CHAT_ID' ) && MD_COMMENTS_BALE_BOT_TOKEN && MD_COMMENTS_BALE_CHAT_ID;
        $enabled = $has_constants ? 'yes' : ( isset( $settings['bale_enabled'] ) ? $settings['bale_enabled'] : 'no' );
        $bot_token = defined( 'MD_COMMENTS_BALE_BOT_TOKEN' ) ? MD_COMMENTS_BALE_BOT_TOKEN : ( isset( $settings['bale_bot_token'] ) ? $settings['bale_bot_token'] : '' );
        $chat_id = defined( 'MD_COMMENTS_BALE_CHAT_ID' ) ? MD_COMMENTS_BALE_CHAT_ID : ( isset( $settings['bale_chat_id'] ) ? $settings['bale_chat_id'] : '' );

        if ( $enabled !== 'yes' || empty( $bot_token ) || empty( $chat_id ) ) {
            return;
        }

        // پیام ارسالی به بله
        $message = "💬 *دیدگاه جدید در سایت ثبت شد!*\n\n";
        $message .= "👤 *نام فرستنده:* " . esc_html( $comment_data['name'] ) . "\n";
        if ( ! empty( $comment_data['phone'] ) ) {
            $message .= "📞 *شماره تماس:* " . esc_html( $comment_data['phone'] ) . "\n";
        }
        $message .= "⭐️ *امتیاز ثبت شده:* " . intval( $comment_data['rating'] ) . " ستاره\n";
        $message .= "📝 *متن دیدگاه:*\n" . esc_html( $comment_data['comment'] ) . "\n\n";
        $message .= "🔗 [مشاهده نوشته جاری](" . get_permalink( $comment_data['post_id'] ) . ")";

        // استفاده از Action Scheduler در صورت وجود، برای ارسال غیرهمزمان
        if ( function_exists( 'as_enqueue_async_action' ) ) {
            as_enqueue_async_action( 'md_send_bale_async', [
                'bot_token' => $bot_token,
                'chat_id'   => $chat_id,
                'message'   => $message,
            ]);
        } else {
            // ارسال غیرهمزمان با WP Cron در صورت عدم وجود Action Scheduler
            wp_schedule_single_event( time(), 'md_send_bale_async', [
                'bot_token' => $bot_token,
                'chat_id'   => $chat_id,
                'message'   => $message,
            ]);
        }
    }

    public function send_request( $bot_token, $chat_id, $message ) {
        $url = "https://tapi.bale.ai/bot" . $bot_token . "/sendMessage";
        
        $response = wp_remote_post( $url, [
            'method'    => 'POST',
            'headers'   => [ 'Content-Type' => 'application/json; charset=utf-8' ],
            'body'      => json_encode( [
                'chat_id'    => $chat_id,
                'text'       => $message,
                'parse_mode' => 'Markdown'
            ], JSON_UNESCAPED_UNICODE ),
            'timeout'   => 10,
            'sslverify' => false,
        ] );

        if ( is_wp_error( $response ) ) {
            $err_msg = $response->get_error_message();
            if ( ! empty( $bot_token ) ) {
                $err_msg = str_replace( $bot_token, '[REDACTED_BOT_TOKEN]', $err_msg );
            }
            \MDCustomComments\Core\Logger::error( 'MD Comments - Bale send error: ' . $err_msg );
        }
    }
}
