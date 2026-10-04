<?php
namespace MDCustomComments\Support;

defined( 'ABSPATH' ) || exit;

/**
 * منطق مشترک رندر آواتار (تصویر اختصاصی آپلودشده یا SVG پیش‌فرض)، که قبلاً به‌صورت
 * کپی-پیست‌شده هم در Front\View و هم در Admin\Controllers\SettingsController وجود داشت.
 *
 * تنها تفاوت بصری بین دو محیط، رنگ نقطه/دایره داخلی SVGهای پیش‌فرض شماره ۲ و ۳ است
 * (در فرانت سفید، در ادمین هم‌رنگ خود شکل) که با پارامتر $context حفظ شده است.
 */
class AvatarRenderer {
    /**
     * @param int    $avatar_id شناسه آواتار انتخابی (۱ تا ۴) برای کاربران مهمان
     * @param int    $user_id   شناسه کاربر وردپرسی (۰ = مهمان)
     * @param string $class     کلاس‌های CSS تصویر/SVG خروجی
     * @param array  $settings  آرایه تنظیمات افزونه (نتیجه get_option('md_comments_settings'))
     * @param string $context   'front' یا 'admin' — فقط رنگ نقطه داخلی SVG پیش‌فرض را متمایز می‌کند
     */
    public static function render( $avatar_id, $user_id, $class, array $settings, $context = 'front' ) {
        // ۱. آواتار عضو یا ادمین بر اساس نقش کاربر لاگین‌شده
        if ( $user_id > 0 ) {
            $user = get_userdata( $user_id );
            if ( $user && in_array( 'administrator', (array) $user->roles ) ) {
                $html = self::resolve_attachment( isset( $settings['avatar_admin_id'] ) ? intval( $settings['avatar_admin_id'] ) : 0, $class, 'ادمین' );
                if ( $html ) {
                    return $html;
                }
            } else {
                $html = self::resolve_attachment( isset( $settings['avatar_member_id'] ) ? intval( $settings['avatar_member_id'] ) : 0, $class, 'عضو سایت' );
                if ( $html ) {
                    return $html;
                }
            }
        }

        // ۲. آواتارهای ۴گانه انتخابی کاربر مهمان
        $option_key = 'avatar_user_' . intval( $avatar_id ) . '_id';
        $html = self::resolve_attachment( isset( $settings[ $option_key ] ) ? intval( $settings[ $option_key ] ) : 0, $class, 'آواتار' );
        if ( $html ) {
            return $html;
        }

        // ۳. در غیر این صورت، بازگشت به آواتار پیش‌فرض SVG
        return self::default_svg( $avatar_id, $class, $context );
    }

    /**
     * تبدیل شناسه ضمیمه (Attachment ID) به تگ <img>، یا null در صورت نبود تصویر
     */
    private static function resolve_attachment( $attachment_id, $class, $alt ) {
        if ( ! $attachment_id ) {
            return null;
        }
        $img_url = wp_get_attachment_url( $attachment_id );
        if ( ! $img_url ) {
            return null;
        }
        return '<img class="' . esc_attr( $class ) . ' object-cover rounded-full" src="' . esc_url( $img_url ) . '" alt="' . esc_attr( $alt ) . '" />';
    }

    /**
     * آواتار پیش‌فرض SVG برای شناسه‌های ۱ تا ۴
     */
    public static function default_svg( $avatar_id, $class = 'w-14 h-14', $context = 'front' ) {
        $is_admin_context = ( $context === 'admin' );

        switch ( intval( $avatar_id ) ) {
            case 2:
                $dot_color = $is_admin_context ? '#009c8f' : '#fff';
                return '<svg class="' . esc_attr( $class ) . '" viewBox="0 0 40 40"><rect x="8" y="6" width="24" height="24" rx="6" fill="#009c8f" opacity="0.6"/><circle cx="20" cy="18" r="5" fill="' . $dot_color . '" opacity="0.8"/></svg>';
            case 3:
                $dot_color = $is_admin_context ? '#0c2d28' : '#fff';
                return '<svg class="' . esc_attr( $class ) . '" viewBox="0 0 40 40"><polygon points="20,4 36,30 4,30" fill="#0c2d28" opacity="0.5"/><circle cx="20" cy="21" r="4" fill="' . $dot_color . '" opacity="0.7"/></svg>';
            case 4:
                return '<svg class="' . esc_attr( $class ) . '" viewBox="0 0 40 40"><circle cx="20" cy="20" r="14" fill="#c39854" opacity="0.3"/><circle cx="14" cy="17" r="2.5" fill="#c39854" opacity="0.7"/><circle cx="26" cy="17" r="2.5" fill="#c39854" opacity="0.7"/><path d="M14 25 Q20 30 26 25" stroke="#c39854" stroke-width="1.5" fill="none" opacity="0.6"/></svg>';
            case 1:
            default:
                return '<svg class="' . esc_attr( $class ) . '" viewBox="0 0 40 40"><circle cx="20" cy="15" r="8" fill="#c39854" opacity="0.7"/><ellipse cx="20" cy="35" rx="14" ry="10" fill="#c39854" opacity="0.4"/></svg>';
        }
    }
}
