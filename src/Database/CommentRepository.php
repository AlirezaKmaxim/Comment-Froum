<?php
namespace MDCustomComments\Database;

defined( 'ABSPATH' ) || exit;

class CommentRepository {
    private $table_name;

    public function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'md_comments';
    }

    /**
     * ثبت دیدگاه جدید در دیتابیس
     */
    public function insert( $data ) {
        global $wpdb;

        $inserted = $wpdb->insert(
            $this->table_name,
            [
                'post_id'      => intval( $data['post_id'] ),
                'user_id'      => intval( isset($data['user_id']) ? $data['user_id'] : 0 ),
                'user_name'    => sanitize_text_field( $data['name'] ),
                'user_phone'   => sanitize_text_field( $data['phone'] ),
                'avatar_id'    => intval( $data['avatar'] ),
                'rating'       => intval( $data['rating'] ),
                'comment_text' => sanitize_textarea_field( $data['comment'] ),
                'status'       => sanitize_text_field( $data['status'] ),
                'parent_id'    => intval( $data['parent_id'] ),
                'ip_address'   => sanitize_text_field( $data['ip_address'] ),
            ],
            [ '%d', '%d', '%s', '%s', '%d', '%d', '%s', '%s', '%d', '%s' ]
        );

        if ( $inserted ) {
            $comment_id = $wpdb->insert_id;
            
            // انتشار رویداد با الگوی EDA
            do_action( 'md_comment_inserted', $comment_id, $data );
            
            return $comment_id;
        }

        return false;
    }

    /**
     * بازیابی یک صفحه از نظرات اصلی تایید شده یک نوشته به‌همراه پاسخ‌هایشان (Lazy Load)
     *
     * برای جلوگیری از N+1 Query، پاسخ‌های همان دسته از نظرات اصلیِ بازگردانده‌شده
     * در یک کوئری واحد (WHERE parent_id IN (...)) گرفته می‌شوند؛ نه یک کوئری به ازای هر نظر.
     */
    public function get_approved_comments( $post_id, $page = 1, $per_page = 10 ) {
        global $wpdb;

        $page = max( 1, intval( $page ) );
        $per_page = max( 1, min( 50, intval( $per_page ) ) );
        $offset = ( $page - 1 ) * $per_page;

        // ۱. یک صفحه از نظرات اصلی (جدیدترین اول)
        $top_query = $wpdb->prepare(
            "SELECT * FROM {$this->table_name} WHERE post_id = %d AND status = 'approved' AND parent_id = 0 ORDER BY created_at DESC LIMIT %d OFFSET %d",
            $post_id,
            $per_page,
            $offset
        );
        $top_level = $wpdb->get_results( $top_query, ARRAY_A );

        if ( ! is_array( $top_level ) || empty( $top_level ) ) {
            return [];
        }

        // ۲. پاسخ‌های همین دسته از نظرات اصلی، در یک کوئری واحد
        $ids = wp_list_pluck( $top_level, 'id' );
        $placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
        $reply_query = $wpdb->prepare(
            "SELECT * FROM {$this->table_name} WHERE parent_id IN ({$placeholders}) AND status = 'approved' ORDER BY created_at ASC",
            $ids
        );
        $replies = $wpdb->get_results( $reply_query, ARRAY_A );

        $replies_by_parent = [];
        if ( is_array( $replies ) ) {
            foreach ( $replies as $reply ) {
                $replies_by_parent[ intval( $reply['parent_id'] ) ][] = $reply;
            }
        }

        foreach ( $top_level as &$comment ) {
            $comment['replies'] = isset( $replies_by_parent[ $comment['id'] ] ) ? $replies_by_parent[ $comment['id'] ] : [];
        }
        unset( $comment );

        return $top_level;
    }

    /**
     * تعداد کل نظرات اصلی تایید شده یک پست (بدون احتساب پاسخ‌ها) — برای تصمیم‌گیری «نمایش بیشتر»
     */
    public function get_top_level_comments_count( $post_id ) {
        global $wpdb;
        $query = $wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->table_name} WHERE post_id = %d AND status = 'approved' AND parent_id = 0",
            $post_id
        );
        return intval( $wpdb->get_var( $query ) );
    }

    /**
     * شمارش کل نظرات تایید شده یک نوشته
     */
    public function get_comments_count( $post_id ) {
        global $wpdb;
        $query = $wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->table_name} WHERE post_id = %d AND status = 'approved'",
            $post_id
        );
        return intval( $wpdb->get_var( $query ) );
    }

    /**
     * شمارش تعداد کاربران منحصر به فردی که نظر داده‌اند
     */
    public function get_unique_users_count( $post_id ) {
        global $wpdb;
        $query = $wpdb->prepare(
            "SELECT COUNT(DISTINCT user_phone) FROM {$this->table_name} WHERE post_id = %d AND status = 'approved' AND user_phone != ''",
            $post_id
        );
        $phone_count = intval( $wpdb->get_var( $query ) );

        $query_names = $wpdb->prepare(
            "SELECT COUNT(DISTINCT user_name) FROM {$this->table_name} WHERE post_id = %d AND status = 'approved' AND user_phone = ''",
            $post_id
        );
        $name_count = intval( $wpdb->get_var( $query_names ) );

        return $phone_count + $name_count;
    }
    /**
     * بازیابی نظرات برای پنل ادمین به‌صورت صفحه‌بندی‌شده (جلوگیری از بازگرداندن کل جدول یک‌جا)
     *
     * @return array{items: array, total: int, page: int, per_page: int, total_pages: int}
     */
    public function get_all_comments_for_admin( $page = 1, $per_page = 20 ) {
        global $wpdb;

        $page = max( 1, intval( $page ) );
        $per_page = max( 1, min( 100, intval( $per_page ) ) );
        $offset = ( $page - 1 ) * $per_page;

        $total = intval( $wpdb->get_var( "SELECT COUNT(*) FROM {$this->table_name}" ) );

        $query = $wpdb->prepare(
            "SELECT c.*, p.post_title
             FROM {$this->table_name} c
             LEFT JOIN {$wpdb->posts} p ON c.post_id = p.ID
             ORDER BY c.created_at DESC
             LIMIT %d OFFSET %d",
            $per_page,
            $offset
        );
        $items = $wpdb->get_results( $query, ARRAY_A );

        return [
            'items'       => is_array( $items ) ? $items : [],
            'total'       => $total,
            'page'        => $page,
            'per_page'    => $per_page,
            'total_pages' => $per_page > 0 ? (int) ceil( $total / $per_page ) : 0,
        ];
    }

    /**
     * بروزرسانی جزئیات یک دیدگاه
     */
    public function update( $id, $data ) {
        global $wpdb;
        return $wpdb->update(
            $this->table_name,
            [
                'user_name'    => sanitize_text_field( $data['user_name'] ),
                'user_phone'   => sanitize_text_field( $data['user_phone'] ),
                'comment_text' => sanitize_textarea_field( $data['comment_text'] ),
                'rating'       => intval( $data['rating'] ),
                'avatar_id'    => intval( $data['avatar_id'] ),
                'status'       => sanitize_text_field( $data['status'] )
            ],
            [ 'id' => intval( $id ) ],
            [ '%s', '%s', '%s', '%d', '%d', '%s' ],
            [ '%d' ]
        );
    }

    /**
     * بروزرسانی وضعیت دیدگاه
     */
    public function update_status( $id, $status ) {
        global $wpdb;
        return $wpdb->update(
            $this->table_name,
            [ 'status' => sanitize_text_field( $status ) ],
            [ 'id' => intval( $id ) ],
            [ '%s' ],
            [ '%d' ]
        );
    }

    /**
     * حذف دیدگاه و پاسخ‌های مربوط به آن
     */
    public function delete( $id ) {
        global $wpdb;
        $id = intval( $id );
        // ابتدا حذف پاسخ‌های مربوط به این نظر
        $wpdb->delete( $this->table_name, [ 'parent_id' => $id ], [ '%d' ] );
        // سپس حذف خود نظر
        return $wpdb->delete( $this->table_name, [ 'id' => $id ], [ '%d' ] );
    }

    /**
     * ثبت یا انصراف از پسندیدن/نپسندیدن دیدگاه
     */
    public function react( $comment_id, $type ) {
        global $wpdb;
        $comment_id = intval( $comment_id );
        if ( ! in_array( $type, [ 'like', 'dislike' ], true ) ) {
            return false;
        }

        // ۰. بررسی اینکه دیدگاه پاسخ نباشد (تنها دیدگاه‌های اصلی لایک/دیسلایک می‌پذیرند)
        $comment_row = $wpdb->get_row( $wpdb->prepare(
            "SELECT parent_id FROM {$this->table_name} WHERE id = %d",
            $comment_id
        ), ARRAY_A );

        if ( ! $comment_row || intval( $comment_row['parent_id'] ) > 0 ) {
            return false;
        }

        // ۱. دریافت اطلاعات کاربر و IP
        $user_id = get_current_user_id();

        // واکنش (لایک/دیسلایک) فقط برای کاربران لاگین‌شده مجاز است؛ همین محدودیت در
        // Front\Controller::handle_reaction() و در front.js هم اعمال شده. این چک در
        // این‌جا هم برای دفاع در عمق (defense in depth) تکرار می‌شود.
        if ( $user_id <= 0 ) {
            return false;
        }

        $ip_address = \MDCustomComments\Security\Validator::get_ip_address();

        $reactions_table = $wpdb->prefix . 'md_comment_reactions';

        // ۲. بررسی وجود عکس‌العمل قبلی همین کاربر روی این دیدگاه
        $existing = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$reactions_table} WHERE comment_id = %d AND user_id = %d",
            $comment_id,
            $user_id
        ), ARRAY_A );

        if ( $existing ) {
            $old_type = $existing['reaction_type'];
            if ( $old_type === $type ) {
                // الف) کلیک مجدد روی همان واکنش -> حذف واکنش (Unlike/Undislike)
                $wpdb->delete(
                    $reactions_table,
                    [ 'id' => intval( $existing['id'] ) ],
                    [ '%d' ]
                );

                // کاهش اتمیک کانتر مربوطه
                $column = ( $type === 'like' ) ? 'likes' : 'dislikes';
                $wpdb->query( $wpdb->prepare(
                    "UPDATE {$this->table_name} SET {$column} = GREATEST(0, {$column} - 1) WHERE id = %d",
                    $comment_id
                ) );

                $user_reaction = null;
            } else {
                // ب) کلیک روی واکنش مخالف -> تغییر واکنش (تغییر لایک به دیسلایک یا برعکس)
                $wpdb->update(
                    $reactions_table,
                    [ 'reaction_type' => $type ],
                    [ 'id' => intval( $existing['id'] ) ],
                    [ '%s' ],
                    [ '%d' ]
                );

                $old_column = ( $old_type === 'like' ) ? 'likes' : 'dislikes';
                $new_column = ( $type === 'like' ) ? 'likes' : 'dislikes';

                $wpdb->query( $wpdb->prepare(
                    "UPDATE {$this->table_name} SET 
                        {$old_column} = GREATEST(0, {$old_column} - 1), 
                        {$new_column} = {$new_column} + 1 
                     WHERE id = %d",
                    $comment_id
                ) );

                $user_reaction = $type;
            }
        } else {
            // ج) ثبت واکنش جدید
            $inserted = $wpdb->insert(
                $reactions_table,
                [
                    'comment_id'    => $comment_id,
                    'user_id'       => $user_id,
                    'ip_address'    => $ip_address,
                    'reaction_type' => $type,
                ],
                [ '%d', '%d', '%s', '%s' ]
            );

            if ( $inserted ) {
                $column = ( $type === 'like' ) ? 'likes' : 'dislikes';
                $wpdb->query( $wpdb->prepare(
                    "UPDATE {$this->table_name} SET {$column} = {$column} + 1 WHERE id = %d",
                    $comment_id
                ) );

                $user_reaction = $type;
            } else {
                // درج ناموفق بود؛ محتمل‌ترین دلیل برخورد با قید یکتایی (comment_user) در اثر
                // یک درخواست هم‌زمان دیگر از همین کاربر است. برای جلوگیری از عدم تطابق شمارنده
                // با تعداد واقعی ردیف‌های reactions، شمارنده را تغییر نمی‌دهیم و وضعیت واقعیِ
                // فعلی را که همان درخواست هم‌زمان ثبت کرده از دیتابیس می‌خوانیم.
                $current = $wpdb->get_row( $wpdb->prepare(
                    "SELECT reaction_type FROM {$reactions_table} WHERE comment_id = %d AND user_id = %d",
                    $comment_id,
                    $user_id
                ), ARRAY_A );
                $user_reaction = $current ? $current['reaction_type'] : null;
            }
        }

        // بازیابی شمارش جدید اتمیک برای بازگرداندن به فرانت‌اند
        $comment_row = $wpdb->get_row( $wpdb->prepare(
            "SELECT likes, dislikes FROM {$this->table_name} WHERE id = %d",
            $comment_id
        ), ARRAY_A );

        return [
            'likes'         => isset( $comment_row['likes'] ) ? intval( $comment_row['likes'] ) : 0,
            'dislikes'      => isset( $comment_row['dislikes'] ) ? intval( $comment_row['dislikes'] ) : 0,
            'user_reaction' => $user_reaction,
        ];
    }
}

