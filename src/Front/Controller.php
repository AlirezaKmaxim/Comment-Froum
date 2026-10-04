<?php
namespace MDCustomComments\Front;

use MDCustomComments\Security\Validator;
use MDCustomComments\Database\CommentRepository;

defined( 'ABSPATH' ) || exit;

class Controller {
    public function __construct() {
        add_action( 'wp_ajax_md_submit_comment', [ $this, 'handle_submit' ] );
        add_action( 'wp_ajax_nopriv_md_submit_comment', [ $this, 'handle_submit' ] );

        add_action( 'wp_ajax_md_react_comment', [ $this, 'handle_reaction' ] );
        add_action( 'wp_ajax_nopriv_md_react_comment', [ $this, 'handle_reaction' ] );

        add_action( 'wp_ajax_md_load_more_comments', [ $this, 'handle_load_more' ] );
        add_action( 'wp_ajax_nopriv_md_load_more_comments', [ $this, 'handle_load_more' ] );
    }

    public function handle_submit() {
        // ۱. اعتبارسنجی ورودی‌ها با سیستم Zero-Trust
        $validated = Validator::validate_comment_submission();

        // ۲. ذخیره کامنت در دیتابیس
        $repository = new CommentRepository();
        $comment_id = $repository->insert( $validated );

        if ( $comment_id ) {
            $is_approved = $validated['status'] === 'approved';
            
            $html = '';
            if ( $is_approved ) {
                // اگر کامنت خودکار تایید شده باشد، قالب HTML آن ارسال می‌شود تا با JS آپند شود
                $html = $this->get_single_comment_html( $comment_id, $validated );
            }

            wp_send_json_success([
                'message'     => $is_approved ? 'دیدگاه شما با موفقیت ثبت شد ✓' : 'دیدگاه شما ثبت شد و پس از تایید نمایش داده خواهد شد.',
                'is_approved' => $is_approved,
                'html'        => $html
            ]);
        } else {
            wp_send_json_error( 'خطایی در ثبت دیدگاه در دیتابیس رخ داد.' );
        }
    }

    public function handle_reaction() {
        if ( ! isset( $_POST['_ajax_nonce'] ) || ! wp_verify_nonce( $_POST['_ajax_nonce'], 'md_submit_comment_nonce' ) ) {
            wp_send_json_error( 'توکن امنیتی نامعتبر است.' );
        }

        if ( ! is_user_logged_in() ) {
            wp_send_json_error( 'لطفا ابتدا وارد شوید' );
        }

        $comment_id = isset( $_POST['comment_id'] ) ? intval( $_POST['comment_id'] ) : 0;
        $type = isset( $_POST['type'] ) ? sanitize_text_field( wp_unslash( $_POST['type'] ) ) : '';

        if ( $comment_id <= 0 || ! in_array( $type, [ 'like', 'dislike' ], true ) ) {
            wp_send_json_error( 'اطلاعات ارسالی نامعتبر است.' );
        }

        $repository = new CommentRepository();
        $result = $repository->react( $comment_id, $type );

        if ( $result ) {
            wp_send_json_success( $result );
        } else {
            wp_send_json_error( 'خطایی در ثبت واکنش رخ داد.' );
        }
    }


    /**
     * بارگذاری صفحه بعدی نظرات به‌صورت AJAX (دکمه «نمایش دیدگاه‌های بیشتر»)
     */
    public function handle_load_more() {
        if ( ! isset( $_POST['_ajax_nonce'] ) || ! wp_verify_nonce( $_POST['_ajax_nonce'], 'md_submit_comment_nonce' ) ) {
            wp_send_json_error( 'توکن امنیتی نامعتبر است.' );
        }

        $post_id = isset( $_POST['post_id'] ) ? intval( $_POST['post_id'] ) : 0;
        $page = isset( $_POST['page'] ) ? intval( $_POST['page'] ) : 2;

        if ( $post_id <= 0 || $page < 1 ) {
            wp_send_json_error( 'پارامترهای ارسالی نامعتبر هستند.' );
        }

        $per_page = \MDCustomComments\Front\View::COMMENTS_PER_PAGE;

        $repository = new CommentRepository();
        $comments = $repository->get_approved_comments( $post_id, $page, $per_page );
        $total_top_level = $repository->get_top_level_comments_count( $post_id );

        $view = new \MDCustomComments\Front\View();
        $html = $view->render_comments_list_html( $comments );
        $has_more = ( $page * $per_page ) < $total_top_level;

        wp_send_json_success([
            'html'      => $html,
            'has_more'  => $has_more,
            'next_page' => $page + 1,
        ]);
    }

    private function get_single_comment_html( $id, $data ) {
        $view = new \MDCustomComments\Front\View();
        
        $normalized_comment = [
            'id'           => $id,
            'avatar_id'    => isset( $data['avatar'] ) ? intval( $data['avatar'] ) : 1,
            'user_id'      => isset( $data['user_id'] ) ? intval( $data['user_id'] ) : 0,
            'user_name'    => $data['name'],
            'rating'       => isset( $data['rating'] ) ? intval( $data['rating'] ) : 0,
            'comment_text' => $data['comment'],
            'created_at'   => current_time( 'mysql' ),
            'likes'        => 0,
            'dislikes'     => 0,
        ];

        return $view->render_single_comment_html( $normalized_comment );
    }
}
