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
     * بازیابی نظرات تایید شده مربوط به یک نوشته
     */
    public function get_approved_comments( $post_id ) {
        global $wpdb;

        // دریافت نظرات اصلی
        $query = $wpdb->prepare(
            "SELECT * FROM {$this->table_name} WHERE post_id = %d AND status = 'approved' AND parent_id = 0 ORDER BY created_at DESC",
            $post_id
        );
        $comments = $wpdb->get_results( $query, ARRAY_A );

        if ( ! is_array( $comments ) ) {
            return [];
        }

        // دریافت پاسخ‌ها برای هر نظر
        foreach ( $comments as &$comment ) {
            $reply_query = $wpdb->prepare(
                "SELECT * FROM {$this->table_name} WHERE parent_id = %d AND status = 'approved' ORDER BY created_at ASC",
                $comment['id']
            );
            $replies = $wpdb->get_results( $reply_query, ARRAY_A );
            $comment['replies'] = is_array( $replies ) ? $replies : [];
        }

        return $comments;
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
     * بازیابی تمام نظرات برای پنل ادمین
     */
    public function get_all_comments_for_admin() {
        global $wpdb;
        $query = "SELECT c.*, p.post_title 
                  FROM {$this->table_name} c 
                  LEFT JOIN {$wpdb->posts} p ON c.post_id = p.ID 
                  ORDER BY c.created_at DESC";
        return $wpdb->get_results( $query, ARRAY_A );
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
        $ip_address = \MDCustomComments\Security\Validator::get_ip_address();

        $reactions_table = $wpdb->prefix . 'md_comment_reactions';

        // ۲. بررسی وجود عکس‌العمل قبلی
        if ( $user_id > 0 ) {
            $existing = $wpdb->get_row( $wpdb->prepare(
                "SELECT * FROM {$reactions_table} WHERE comment_id = %d AND user_id = %d",
                $comment_id,
                $user_id
            ), ARRAY_A );
        } else {
            $existing = $wpdb->get_row( $wpdb->prepare(
                "SELECT * FROM {$reactions_table} WHERE comment_id = %d AND user_id = 0 AND ip_address = %s",
                $comment_id,
                $ip_address
            ), ARRAY_A );
        }

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
            $wpdb->insert(
                $reactions_table,
                [
                    'comment_id'    => $comment_id,
                    'user_id'       => $user_id,
                    'ip_address'    => $ip_address,
                    'reaction_type' => $type,
                ],
                [ '%d', '%d', '%s', '%s' ]
            );

            $column = ( $type === 'like' ) ? 'likes' : 'dislikes';
            $wpdb->query( $wpdb->prepare(
                "UPDATE {$this->table_name} SET {$column} = {$column} + 1 WHERE id = %d",
                $comment_id
            ) );

            $user_reaction = $type;
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

