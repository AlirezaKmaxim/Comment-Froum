<?php
namespace MDCustomComments\Events\Listeners;

defined( 'ABSPATH' ) || exit;

class SendSlack {
    /**
     * ارسال وب‌هوک به اسلک
     */
    public function dispatch( $comment_id, $comment_data ) {
        $settings = get_option( 'md_comments_settings', [] );
        
        $enabled = ( defined( 'MD_COMMENTS_SLACK_WEBHOOK' ) && MD_COMMENTS_SLACK_WEBHOOK ) ? 'yes' : ( isset( $settings['slack_enabled'] ) ? $settings['slack_enabled'] : 'no' );
        $webhook_url = defined( 'MD_COMMENTS_SLACK_WEBHOOK' ) ? MD_COMMENTS_SLACK_WEBHOOK : ( isset( $settings['slack_webhook'] ) ? $settings['slack_webhook'] : '' );

        if ( $enabled !== 'yes' || empty( $webhook_url ) ) {
            return;
        }

        // پیام ارسالی به اسلک
        $payload = [
            'text' => '💬 دیدگاه جدید در سایت ثبت شد!',
            'attachments' => [
                [
                    'color' => '#c39854',
                    'fields' => [
                        [ 'title' => 'نام فرستنده', 'value' => esc_html( $comment_data['name'] ), 'short' => true ],
                        [ 'title' => 'امتیاز', 'value' => intval( $comment_data['rating'] ) . ' ستاره', 'short' => true ],
                        [ 'title' => 'متن دیدگاه', 'value' => esc_html( $comment_data['comment'] ), 'short' => false ],
                    ]
                ]
            ]
        ];
        
        if ( ! empty( $comment_data['phone'] ) ) {
            $payload['attachments'][0]['fields'][] = [
                'title' => 'شماره تماس',
                'value' => esc_html( $comment_data['phone'] ),
                'short' => true
            ];
        }

        // ارسال از طریق Action Scheduler در صورت وجود، یا ارسال همزمان به عنوان بک‌آپ
        if ( function_exists( 'as_enqueue_async_action' ) ) {
            as_enqueue_async_action( 'md_send_slack_webhook_async', [
                'webhook_url' => $webhook_url,
                'payload'     => json_encode( $payload ),
            ]);
        } else {
            // ارسال غیرهمزمان با WP Cron در صورت عدم وجود Action Scheduler
            wp_schedule_single_event( time(), 'md_send_slack_webhook_async', [
                'webhook_url' => $webhook_url,
                'payload'     => json_encode( $payload ),
            ]);
        }
    }

    public function send_request( $webhook_url, $payload ) {
        $response = wp_remote_post( $webhook_url, [
            'method'    => 'POST',
            'headers'   => [ 'Content-Type' => 'application/json; charset=utf-8' ],
            'body'      => json_encode( $payload, JSON_UNESCAPED_UNICODE ),
            'timeout'   => 10,
        ] );

        if ( is_wp_error( $response ) ) {
            $err_msg = $response->get_error_message();
            if ( ! empty( $webhook_url ) ) {
                $err_msg = str_replace( $webhook_url, '[REDACTED_WEBHOOK_URL]', $err_msg );
            }
            \MDCustomComments\Core\Logger::error( 'MD Comments - Slack send error: ' . $err_msg );
        }
    }
}
