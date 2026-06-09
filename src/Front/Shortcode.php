<?php
namespace MDCustomComments\Front;

defined( 'ABSPATH' ) || exit;

class Shortcode {
    public function __construct() {
        add_shortcode( 'md_comments', [ $this, 'render' ] );
    }

    public function render( $atts ) {
        // ۱. فعال‌سازی و بارگذاری اسکریپت‌ها و استایل‌های مورد نیاز
        Assets::enqueue_assets();

        // ۲. رندر خروجی از طریق لایه View
        $view = new View();
        return $view->render();
    }
}
