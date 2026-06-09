<?php
namespace MDCustomComments\Front;

defined( 'ABSPATH' ) || exit;

class Assets {
    public function __construct() {
        add_action( 'wp_enqueue_scripts', [ $this, 'register_assets' ] );
    }

    public function register_assets() {
        // ثبت استایل و اسکریپت فرانت‌اند
        wp_register_style(
            'md-comments-front',
            plugins_url( 'assets/front/front.css', MD_CUSTOM_COMMENTS_FILE ),
            [],
            MD_CUSTOM_COMMENTS_VERSION
        );

        wp_register_script(
            'md-comments-front',
            plugins_url( 'assets/front/front.js', MD_CUSTOM_COMMENTS_FILE ),
            [],
            MD_CUSTOM_COMMENTS_VERSION,
            true // اجرای اسکریپت در انتهای فوتر
        );

        // لود شرطی دارایی‌ها — فقط در صورتی که صفحه حاوی شورتکد باشد
        if ( $this->should_load_assets() ) {
            self::enqueue_assets();
        }
    }

    /**
     * بررسی وجود شورتکد نظرات در محتوای صفحه
     */
    public function should_load_assets() {
        global $post;
        if ( is_a( $post, 'WP_Post' ) ) {
            if ( has_shortcode( $post->post_content, 'md_comments' ) ) {
                return true;
            }
            if ( has_block( 'core/shortcode', $post ) && strpos( $post->post_content, 'md_comments' ) !== false ) {
                return true;
            }
        }
        return false;
    }

    /**
     * لود دارایی‌های فرانت‌اند
     */
    public static function enqueue_assets() {
        wp_enqueue_style( 'md-comments-front' );
        wp_enqueue_script( 'md-comments-front' );

        // پیدا کردن ID پست جاری
        global $post;
        $post_id = is_a( $post, 'WP_Post' ) ? $post->ID : 0;

        // پاس دادن اطلاعات مورد نیاز به اسکریپت جاوااسکریپت
        wp_localize_script( 'md-comments-front', 'mdCommentsData', [
            'ajaxUrl' => admin_url( 'admin-ajax.php' ),
            'nonce'   => wp_create_nonce( 'md_submit_comment_nonce' ),
            'postId'  => $post_id,
        ] );
    }
}
