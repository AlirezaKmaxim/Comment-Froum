<?php
namespace MDCustomComments\Events;

defined( 'ABSPATH' ) || exit;

class EventDispatcher {
    /**
     * انتشار اکشن در سیستم وردپرس (do_action wrapper)
     */
    public static function dispatch( $event_name, ...$args ) {
        do_action( $event_name, ...$args );
    }

    /**
     * فیلتر کردن مقادیر در سیستم وردپرس (apply_filters wrapper)
     */
    public static function filter( $filter_name, $value, ...$args ) {
        return apply_filters( $filter_name, $value, ...$args );
    }
}
