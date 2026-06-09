<?php
namespace MDCustomComments\Core;

defined( 'ABSPATH' ) || exit;

class Installer {
    public static function install() {
        global $wpdb;

        $table_name = $wpdb->prefix . 'md_comments';
        $charset_collate = $wpdb->get_charset_collate();

        // اسکیما جدول اختصاصی نظرات MD
        $sql = "CREATE TABLE $table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            post_id bigint(20) NOT NULL,
            user_id bigint(20) NOT NULL DEFAULT 0,
            user_name varchar(100) NOT NULL,
            user_phone varchar(20) DEFAULT '',
            avatar_id int(11) DEFAULT 1,
            rating tinyint(4) NOT NULL DEFAULT 0,
            comment_text text NOT NULL,
            status varchar(20) NOT NULL DEFAULT 'hold',
            parent_id bigint(20) NOT NULL DEFAULT 0,
            likes int(11) NOT NULL DEFAULT 0,
            dislikes int(11) NOT NULL DEFAULT 0,
            ip_address varchar(45) DEFAULT '',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY post_id (post_id),
            KEY parent_id (parent_id)
        ) $charset_collate;";

        $reactions_table = $wpdb->prefix . 'md_comment_reactions';
        $sql_reactions = "CREATE TABLE $reactions_table (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            comment_id bigint(20) NOT NULL,
            user_id bigint(20) NOT NULL DEFAULT 0,
            ip_address varchar(45) NOT NULL DEFAULT '',
            reaction_type varchar(10) NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY comment_id (comment_id)
        ) $charset_collate;";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );
        dbDelta( $sql_reactions );

        // اطمینان از وجود ستون user_id برای نصب‌های قدیمی‌تر
        $column_check = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s AND COLUMN_NAME = 'user_id'",
            DB_NAME,
            $table_name
        ) );
        if ( ! $column_check ) {
            $wpdb->query( "ALTER TABLE $table_name ADD COLUMN user_id bigint(20) NOT NULL DEFAULT 0 AFTER post_id" );
        }

        // اطمینان از وجود ستون‌های لایک و دیسلایک برای نصب‌های قدیمی‌تر
        $likes_check = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s AND COLUMN_NAME = 'likes'",
            DB_NAME,
            $table_name
        ) );
        if ( ! $likes_check ) {
            $wpdb->query( "ALTER TABLE $table_name ADD COLUMN likes int(11) NOT NULL DEFAULT 0 AFTER parent_id" );
        }

        $dislikes_check = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s AND COLUMN_NAME = 'dislikes'",
            DB_NAME,
            $table_name
        ) );
        if ( ! $dislikes_check ) {
            $wpdb->query( "ALTER TABLE $table_name ADD COLUMN dislikes int(11) NOT NULL DEFAULT 0 AFTER likes" );
        }

        // ثبت تنظیمات پیش‌فرض
        self::set_default_options();
    }

    private static function set_default_options() {
        if ( get_option( 'md_comments_settings' ) === false ) {
            $default_settings = [
                'auto_approve' => 'no',
                'enable_stars' => 'yes',
                'enable_avatars' => 'yes',
                'telegram_webhook' => '',
                'telegram_enabled' => 'no',
                'slack_webhook' => '',
                'slack_enabled' => 'no',
                'avatar_user_1_id' => 0,
                'avatar_user_2_id' => 0,
                'avatar_user_3_id' => 0,
                'avatar_user_4_id' => 0,
                'avatar_admin_id' => 0,
                'avatar_member_id' => 0,
                'title_name' => 'نام و نام خانوادگی',
                'title_phone' => 'شماره همراه (برای اطلاع‌رسانی)',
            ];
            update_option( 'md_comments_settings', $default_settings );
        }
    }
}
