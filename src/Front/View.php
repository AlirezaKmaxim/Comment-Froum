<?php
namespace MDCustomComments\Front;

defined( 'ABSPATH' ) || exit;

class View {
    /**
     * تعداد نظرات اصلی که در بار اول (و در هر بار کلیک «نمایش بیشتر») بارگذاری می‌شود.
     * برای جلوگیری از رندر یک‌جای صدها دیدگاه در HTML اولیه صفحه (Lazy Load).
     */
    const COMMENTS_PER_PAGE = 10;

    /**
     * کش تنظیمات افزونه در طول یک درخواست، تا به‌جای فراخوانی مکرر get_option()
     * به ازای هر دیدگاه/پاسخ/آواتار، فقط یک‌بار در طول Request خوانده شود.
     */
    private static $settings_cache = null;

    private function get_settings() {
        if ( self::$settings_cache === null ) {
            self::$settings_cache = get_option( 'md_comments_settings', [] );
        }
        return self::$settings_cache;
    }

    /**
     * رندر کامل فرانت‌اند شامل فرم و لیست نظرات
     */
    public function render() {
        $post_id = get_the_ID();
        if ( ! $post_id ) {
            global $post;
            $post_id = is_a( $post, 'WP_Post' ) ? $post->ID : 0;
        }
        if ( ! $post_id ) {
            $post_id = get_queried_object_id();
        }
        if ( ! $post_id ) {
            $post_id = 0;
        }

        // بازیابی نظرات و آمار مربوطه از دیتابیس — فقط اولین صفحه (Lazy Load)
        $repository = new \MDCustomComments\Database\CommentRepository();
        $comments = $repository->get_approved_comments( $post_id, 1, self::COMMENTS_PER_PAGE );
        $comments_count = $repository->get_comments_count( $post_id );
        $users_count = $repository->get_unique_users_count( $post_id );
        $top_level_count = $repository->get_top_level_comments_count( $post_id );
        $has_more_comments = $top_level_count > self::COMMENTS_PER_PAGE;

        // تبدیل اعداد به یونیکد فارسی برای نمایش زیباتر
        $comments_count_fa = $this->to_persian_num( $comments_count );
        $users_count_fa = $this->to_persian_num( $users_count );

        // لود تنطیمات عمومی
        $settings = $this->get_settings();
        $title_name = isset( $settings['title_name'] ) && ! empty( $settings['title_name'] ) ? $settings['title_name'] : 'نام و نام خانوادگی';
        $title_phone = isset( $settings['title_phone'] ) && ! empty( $settings['title_phone'] ) ? $settings['title_phone'] : 'شماره همراه (برای اطلاع‌رسانی)';
        $label_admin = isset( $settings['label_admin'] ) && ! empty( $settings['label_admin'] ) ? $settings['label_admin'] : 'کارشناس پشتیبانی';
        $badge_admin = isset( $settings['badge_admin'] ) && ! empty( $settings['badge_admin'] ) ? $settings['badge_admin'] : 'ادمین';
        $badge_member = isset( $settings['badge_member'] ) && ! empty( $settings['badge_member'] ) ? $settings['badge_member'] : 'عضو سایت';

        ob_start();
        ?>
        <div class="md-custom-comments-scope" id="mdCommentsContainer"
             data-ajax-url="<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>"
             data-nonce="<?php echo esc_attr( wp_create_nonce( 'md_submit_comment_nonce' ) ); ?>"
             data-post-id="<?php echo esc_attr( $post_id ); ?>"
             data-user-logged-in="<?php echo is_user_logged_in() ? 'true' : 'false'; ?>">
          <div class="comments-form-wrapper">
            <!-- ═══ Header & Watermark ═══ -->
            <div class="relative text-center mb-6 overflow-hidden flex flex-col items-center justify-center min-h-[90px]">
              <h1 class="text-[50px] font-black leading-none text-transparent bg-clip-text bg-gradient-to-b from-gold to-white select-none pointer-events-none absolute top-1/2 -translate-y-1/2 watermark-mask opacity-40">
                COMMENTS
              </h1>
              <div class="relative z-10 flex items-center justify-center gap-4 pt-6 pb-2">
                <!-- Left SVG Ornament -->
                <svg class="w-5 h-5 text-gold flex-shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                  <path d="M12 2L2 12l10 10 10-10L12 2z"/>
                  <path d="M12 6L6 12l6 6 6-6-6-6z" fill="currentColor" fill-opacity="0.2"/>
                </svg>
                <h2 class="text-3xl font-bold text-primary">ثبت دیدگاه شما</h2>
                <!-- Right SVG Ornament -->
                <svg class="w-5 h-5 text-gold flex-shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                  <path d="M12 2L2 12l10 10 10-10L12 2z"/>
                  <path d="M12 6L6 12l6 6 6-6-6-6z" fill="currentColor" fill-opacity="0.2"/>
                </svg>
              </div>
            </div>

            <!-- Hidden Fields for Form Processing -->
            <input type="hidden" id="selectedRatingInput" name="rating" value="0">
            <input type="hidden" id="selectedAvatarInput" name="avatar" value="1">
            <!-- Honeypot Field (Anti-Spam) -->
            <?php $hp_field_name = 'md_hp_' . substr( md5( $post_id . ( defined( 'NONCE_KEY' ) ? NONCE_KEY : 'md_fallback_salt' ) ), 0, 10 ); ?>
            <input type="text" id="honeypotInput" name="<?php echo esc_attr( $hp_field_name ); ?>" style="display:none !important;" aria-hidden="true" tabindex="-1" autocomplete="off">

            <!-- ═══ Rating Section ═══ -->
            <div class="flex flex-col items-center gap-3 mb-6 md:mb-2 w-full">
              <!-- Dynamic Rating Circle with Dashed Lines -->
              <div class="flex items-center justify-center w-full gap-4">
                <div class="flex-grow border-t-2 border-dashed border-border"></div>
                <div class="rounded-full border-4 border-dashed border-border flex-shrink-0 flex items-center justify-center transition-colors hover:border-gold bg-white" style="width:90px;height:90px;">
                  <span id="ratingValue" class="text-xl md:text-3xl font-bold text-primary">۰</span>
                </div>
                <div class="flex-grow border-t-2 border-dashed border-border"></div>
              </div>
              
              <div class="flex flex-col items-center gap-1" role="group" aria-label="امتیازدهی ستاره‌ای">
                <div class="flex gap-1.5 direction-ltr" id="starsContainer" dir="ltr">
                  <button type="button" class="star-btn p-0.5 transform hover:scale-110 transition-transform focus:outline-none focus:ring-2 focus:ring-gold/50 rounded" data-value="5">
                    <svg class="w-[26px] h-[26px]" viewBox="0 0 24 24">
                      <polygon class="star-empty" points="12,2 15.09,8.26 22,9.27 17,14.14 18.18,21.02 12,17.77 5.82,21.02 7,14.14 2,9.27 8.91,8.26"/>
                    </svg>
                  </button>
                  <button type="button" class="star-btn p-0.5 transform hover:scale-110 transition-transform focus:outline-none focus:ring-2 focus:ring-gold/50 rounded" data-value="4">
                    <svg class="w-[26px] h-[26px]" viewBox="0 0 24 24">
                      <polygon class="star-empty" points="12,2 15.09,8.26 22,9.27 17,14.14 18.18,21.02 12,17.77 5.82,21.02 7,14.14 2,9.27 8.91,8.26"/>
                    </svg>
                  </button>
                  <button type="button" class="star-btn p-0.5 transform hover:scale-110 transition-transform focus:outline-none focus:ring-2 focus:ring-gold/50 rounded" data-value="3">
                    <svg class="w-[26px] h-[26px]" viewBox="0 0 24 24">
                      <polygon class="star-empty" points="12,2 15.09,8.26 22,9.27 17,14.14 18.18,21.02 12,17.77 5.82,21.02 7,14.14 2,9.27 8.91,8.26"/>
                    </svg>
                  </button>
                  <button type="button" class="star-btn p-0.5 transform hover:scale-110 transition-transform focus:outline-none focus:ring-2 focus:ring-gold/50 rounded" data-value="2">
                    <svg class="w-[26px] h-[26px]" viewBox="0 0 24 24">
                      <polygon class="star-empty" points="12,2 15.09,8.26 22,9.27 17,14.14 18.18,21.02 12,17.77 5.82,21.02 7,14.14 2,9.27 8.91,8.26"/>
                    </svg>
                  </button>
                  <button type="button" class="star-btn p-0.5 transform hover:scale-110 transition-transform focus:outline-none focus:ring-2 focus:ring-gold/50 rounded" data-value="1">
                    <svg class="w-[26px] h-[26px]" viewBox="0 0 24 24">
                      <polygon class="star-empty" points="12,2 15.09,8.26 22,9.27 17,14.14 18.18,21.02 12,17.77 5.82,21.02 7,14.14 2,9.27 8.91,8.26"/>
                    </svg>
                  </button>
                </div>
                <span class="text-xs text-neutral">امتیاز شما به این محتوا</span>
              </div>
            </div>

            <!-- ═══ Avatar Selection ═══ -->
            <style>
              @media (max-width:767px){.md-avatar-group{justify-content:center}}
              @media (min-width:768px){.md-avatar-group{justify-content:flex-start}}
            </style>
            <div class="mb-5">
              <span class="block text-neutral mb-2 text-sm">دیدگاه شما را با چه تصویری در سایت نمایش دهیم؟</span>
              <div class="flex gap-3 md:gap-4 md-avatar-group" role="radiogroup" aria-label="انتخاب آواتار">
                <button type="button" class="avatar-option w-12 h-12 md:w-14 md:h-14 rounded-full border-2 border-border opacity-50 grayscale hover:opacity-100 hover:grayscale-0 hover:border-gold/70 transition-all bg-gray-50 flex items-center justify-center focus:outline-none selected" data-avatar="1" role="radio" aria-checked="true">
                  <?php echo $this->get_avatar_html(1, 0, 'w-8 h-8 md:w-10 md:h-10'); ?>
                </button>
                <button type="button" class="avatar-option w-12 h-12 md:w-14 md:h-14 rounded-full border-2 border-border opacity-50 grayscale hover:opacity-100 hover:grayscale-0 hover:border-gold/70 transition-all bg-gray-50 flex items-center justify-center focus:outline-none" data-avatar="2" role="radio" aria-checked="false">
                  <?php echo $this->get_avatar_html(2, 0, 'w-8 h-8 md:w-10 md:h-10'); ?>
                </button>
                <button type="button" class="avatar-option w-12 h-12 md:w-14 md:h-14 rounded-full border-2 border-border opacity-50 grayscale hover:opacity-100 hover:grayscale-0 hover:border-gold/70 transition-all bg-gray-50 flex items-center justify-center focus:outline-none" data-avatar="3" role="radio" aria-checked="false">
                  <?php echo $this->get_avatar_html(3, 0, 'w-8 h-8 md:w-10 md:h-10'); ?>
                </button>
                <button type="button" class="avatar-option w-12 h-12 md:w-14 md:h-14 rounded-full border-2 border-border opacity-50 grayscale hover:opacity-100 hover:grayscale-0 hover:border-gold/70 transition-all bg-gray-50 flex items-center justify-center focus:outline-none" data-avatar="4" role="radio" aria-checked="false">
                  <?php echo $this->get_avatar_html(4, 0, 'w-8 h-8 md:w-10 md:h-10'); ?>
                </button>
              </div>
            </div>

            <!-- ═══ Form Inputs ═══ -->
            <div class="mb-4">
              <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                <div class="relative">
                    <input type="text" id="nameInput" placeholder="<?php echo esc_attr( $title_name ); ?>" class="w-full h-12 border-2 border-border rounded-xl px-12 text-sm text-primary placeholder:text-neutral focus:border-gold focus:ring-4 focus:ring-gold/20 outline-none transition-all bg-white" autocomplete="name" value="<?php echo is_user_logged_in() ? esc_attr( wp_get_current_user()->display_name ) : ''; ?>">
                  <svg class="w-5 h-5 absolute right-4 top-1/2 -translate-y-1/2 text-border pointer-events-none transition-colors" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                    <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>
                  </svg>
                </div>
                <div class="relative">
                  <input type="tel" id="phoneInput" placeholder="<?php echo esc_attr( $title_phone ); ?>" class="w-full h-12 border-2 border-border rounded-xl px-12 text-sm text-primary placeholder:text-neutral focus:border-gold focus:ring-4 focus:ring-gold/20 outline-none transition-all bg-white" autocomplete="tel" dir="ltr" style="text-align:right" value="<?php echo is_user_logged_in() ? esc_attr( get_user_meta( get_current_user_id(), 'billing_phone', true ) ) : ''; ?>">
                  <svg class="w-5 h-5 absolute right-4 top-1/2 -translate-y-1/2 text-border pointer-events-none transition-colors" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                    <rect x="5" y="2" width="14" height="20" rx="2" ry="2"/><line x1="12" y1="18" x2="12.01" y2="18"/>
                  </svg>
                </div>
              </div>
            </div>

            <!-- ═══ Textarea ═══ -->
            <div class="mb-4">
              <textarea id="commentTextarea" placeholder="دیدگاه خود را وارد نمایید..." class="w-full min-h-[140px] border-2 border-border rounded-xl p-4 text-base text-primary placeholder:text-neutral focus:border-gold focus:ring-4 focus:ring-gold/20 outline-none transition-all resize-y leading-6 bg-white" aria-label="متن دیدگاه"></textarea>
            </div>

            <!-- ═══ Footer: Badges Left, Button Right ═══ -->
            <div class="flex flex-col md:flex-row items-center md:justify-between gap-4 mb-6">
              <div class="flex items-center gap-3">
                <span class="inline-flex items-center gap-1.5 bg-white text-gold text-sm font-semibold py-1.5 px-4 rounded-full whitespace-nowrap">
                  <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>
                  </svg>
                  <span id="commentsCount"><?php echo esc_html( $comments_count_fa ); ?> دیدگاه</span>
                </span>
                <span class="text-sm text-neutral" id="usersCount">از سوی <?php echo esc_html( $users_count_fa ); ?> نفر</span>
              </div>
              <button type="button" id="submitBtn" class="w-full md:w-auto inline-flex items-center justify-center gap-2 bg-gold text-white font-bold text-base border-none rounded-full py-2.5 px-8 cursor-pointer hover:bg-teal hover:shadow-lg hover:shadow-teal/30 active:scale-95 transition-all focus:outline-none focus:ring-4 focus:ring-teal/30">
                ارسال دیدگاه
                <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                  <line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/>
                </svg>
              </button>
            </div>
          </div>

          <!-- Toast Alert Container -->
          <div id="toast"></div>

        <!-- ═══ Comments List Component ═══ -->
        <div class="border-t-2 border-subtle pt-5 md:pt-[45px]">
          <div class="mb-8">
            <h3 class="text-2xl font-bold text-primary text-center sm:text-right m-0">دیدگاه‌های کاربران</h3>
          </div>
          
          <div class="flex flex-col gap-6 transition-all duration-300" id="commentsList">
            <?php echo $this->render_comments_list_html( $comments ); ?>
          </div>

          <?php if ( $has_more_comments ) : ?>
          <!-- دکمه نمایش دیدگاه‌های بیشتر (Lazy Load) -->
          <div class="text-center mt-6">
            <button type="button" id="loadMoreCommentsBtn"
                    class="inline-flex items-center justify-center gap-2 bg-white border-2 border-border text-primary font-bold text-sm rounded-full py-2.5 px-8 cursor-pointer hover:border-gold hover:text-gold transition-all"
                    data-next-page="2">
              نمایش دیدگاه‌های بیشتر
            </button>
          </div>
          <?php endif; ?>
        </div>
        </div> <!-- Close md-custom-comments-scope -->
        <?php
        return ob_get_clean();
    }

    /**
     * رندر تصویر یا ساختار آواتار بر اساس شناسه انتخابی و نقش کاربر
     */
    private function get_avatar_html( $avatar_id, $user_id = 0, $class = 'w-14 h-14' ) {
        return \MDCustomComments\Support\AvatarRenderer::render( $avatar_id, $user_id, $class, $this->get_settings(), 'front' );
    }

    /**
     * تبدیل اعداد انگلیسی به فارسی
     */
    private function to_persian_num( $num ) {
        return \MDCustomComments\Support\PersianFormatter::to_persian_num( $num );
    }

    /**
     * نمایش تاریخ به صورت زمان گذشته (مانند "۲ ساعت پیش")
     */
    private function human_time_diff_fa( $datetime ) {
        return \MDCustomComments\Support\PersianFormatter::human_time_diff_fa( $datetime );
    }

    /**
     * رندر کل لیست نظرات
     */
    public function render_comments_list_html( $comments ) {
        ob_start();
        if ( ! empty( $comments ) ) {
            foreach ( $comments as $comment ) {
                echo $this->render_single_comment_html( $comment );
                if ( ! empty( $comment['replies'] ) ) {
                    foreach ( $comment['replies'] as $reply ) {
                        echo $this->render_reply_html( $reply );
                    }
                }
            }
        } else {
            ?>
            <div class="text-center py-8 text-neutral" id="noCommentsText">هنوز هیچ دیدگاهی ثبت نشده است. اولین نفری باشید که نظر خود را ارسال می‌کند!</div>
            <?php
        }
        return ob_get_clean();
    }

    /**
     * رندر قالب یک نظر کاربر
     */
    public function render_single_comment_html( $comment ) {
        $comment = wp_parse_args( $comment, [
            'id'           => 0,
            'avatar_id'    => 1,
            'user_id'      => 0,
            'user_name'    => '',
            'rating'       => 0,
            'comment_text' => '',
            'created_at'   => current_time( 'mysql' ),
            'likes'        => 0,
            'dislikes'     => 0,
        ] );

        $settings = $this->get_settings();
        $badge_admin = isset( $settings['badge_admin'] ) && ! empty( $settings['badge_admin'] ) ? $settings['badge_admin'] : 'ادمین';
        $badge_member = isset( $settings['badge_member'] ) && ! empty( $settings['badge_member'] ) ? $settings['badge_member'] : 'عضو سایت';

        ob_start();
        ?>
        <div class="flex gap-3 md:gap-4 items-start user-comment" id="comment-<?php echo intval( $comment['id'] ); ?>">
          <div class="w-12 h-12 md:w-20 md:h-20 rounded-full border-2 border-border flex-shrink-0 overflow-hidden bg-gray-50 flex items-center justify-center">
            <?php echo $this->get_avatar_html( $comment['avatar_id'], $comment['user_id'], 'w-8 h-8 md:w-12 md:h-12' ); ?>
          </div>
          <div class="flex-1 bg-gray-50 p-4 md:p-6 rounded-2xl border border-gray-200/80 flex flex-col justify-between">
            <div>
              <div class="flex justify-between items-start md:items-center gap-2 mb-4 pb-3 border-b border-gray-200">
                <div class="flex items-center gap-3 flex-wrap min-w-0">
                  <span class="font-bold text-base md:text-lg text-primary"><?php echo esc_html( $comment['user_name'] ); ?></span>
                  <?php 
                  if ( $comment['user_id'] > 0 ) {
                      $user = get_userdata( $comment['user_id'] );
                      if ( $user && in_array( 'administrator', (array) $user->roles ) ) {
                          if ( $comment['user_name'] === $user->display_name || $comment['user_name'] === $user->user_nicename || $comment['user_name'] === $user->user_login || $comment['user_name'] === 'مدیر سایت' ) {
                              ?>
                              <span class="bg-teal text-white text-[10px] md:text-xs font-bold py-1 px-3 rounded-full whitespace-nowrap"><?php echo esc_html( $badge_admin ); ?></span>
                              <?php
                          }
                      } else {
                          ?>
                          <span class="bg-primary/10 text-primary text-[10px] md:text-xs font-bold py-1 px-3 rounded-full whitespace-nowrap"><?php echo esc_html( $badge_member ); ?></span>
                          <?php
                      }
                  }
                  ?>
                  <?php if ( ! empty( $comment['rating'] ) ) : ?>
                    <div class="flex gap-0.5 text-gold" dir="ltr">
                      <?php for ( $i = 1; $i <= 5; $i++ ) : ?>
                        <svg class="w-4 h-4 fill-current <?php echo $i <= intval( $comment['rating'] ) ? 'text-gold' : 'text-gray-200'; ?>" viewBox="0 0 20 20">
                          <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"></path>
                        </svg>
                      <?php endfor; ?>
                    </div>
                  <?php endif; ?>
                </div>
                <span class="text-xs md:text-sm text-neutral whitespace-nowrap flex-shrink-0"><?php echo esc_html( $this->human_time_diff_fa( $comment['created_at'] ) ); ?></span>
              </div>
              <div class="text-sm md:text-lg leading-8 md:leading-9 text-gray-800 mb-4">
                <?php echo esc_html( $comment['comment_text'] ); ?>
              </div>
            </div>

            <!-- Reactions Section -->
            <div class="flex justify-between items-center pt-3 border-t border-gray-200/50">
              <div></div>
              <div class="flex items-center gap-3" dir="ltr">
                <button type="button" class="btn-react btn-like flex items-center gap-1.5 py-1 px-3 rounded-full border border-border text-neutral hover:text-teal hover:border-teal/40 hover:bg-teal/5 transition-all duration-200 active:scale-90" data-comment-id="<?php echo intval( $comment['id'] ); ?>" data-type="like" aria-label="پسندیدن">
                  <svg class="w-4 h-4 fill-none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M14 9V5a3 3 0 0 0-3-3l-4 9v11h11.28a2 2 0 0 0 2-1.7l1.38-9a2 2 0 0 0-2-2.3zM7 22H4a2 2 0 0 1-2-2v-7a2 2 0 0 1 2-2h3"/>
                  </svg>
                  <span class="like-count text-xs font-semibold"><?php echo $this->to_persian_num( intval( $comment['likes'] ) ); ?></span>
                </button>
                <button type="button" class="btn-react btn-dislike flex items-center gap-1.5 py-1 px-3 rounded-full border border-border text-neutral hover:text-red-500 hover:border-red-500/40 hover:bg-red-50 transition-all duration-200 active:scale-90" data-comment-id="<?php echo intval( $comment['id'] ); ?>" data-type="dislike" aria-label="نپسندیدن">
                  <svg class="w-4 h-4 fill-none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M10 15v4a3 3 0 0 0 3 3l4-9V2H5.72a2 2 0 0 0-2 1.7l-1.38 9a2 2 0 0 0 2 2.3zm12-3h3a2 2 0 0 1 2 2v7a2 2 0 0 1-2 2h-3"/>
                  </svg>
                  <span class="dislike-count text-xs font-semibold"><?php echo $this->to_persian_num( intval( $comment['dislikes'] ) ); ?></span>
                </button>
              </div>
            </div>
          </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * رندر قالب یک پاسخ ادمین
     */
    public function render_reply_html( $reply ) {
        $reply = wp_parse_args( $reply, [
            'id'           => 0,
            'user_id'      => 0,
            'comment_text' => '',
            'created_at'   => current_time( 'mysql' ),
            'likes'        => 0,
            'dislikes'     => 0,
        ] );

        $settings = $this->get_settings();
        $label_admin = isset( $settings['label_admin'] ) && ! empty( $settings['label_admin'] ) ? $settings['label_admin'] : 'کارشناس پشتیبانی';
        $badge_admin = isset( $settings['badge_admin'] ) && ! empty( $settings['badge_admin'] ) ? $settings['badge_admin'] : 'ادمین';

        ob_start();
        ?>
        <div class="flex gap-3 md:gap-4 items-start admin-comment mr-6 md:mr-20 border-r-4 border-teal pr-3 md:pr-6" id="comment-<?php echo intval( $reply['id'] ); ?>">
          <div class="w-12 h-12 md:w-20 md:h-20 rounded-full border-2 border-teal flex-shrink-0 overflow-hidden bg-teal/5 flex items-center justify-center">
            <?php echo $this->get_avatar_html( 0, $reply['user_id'], 'w-8 h-8 md:w-12 md:h-12' ); ?>
          </div>
          <div class="flex-1 bg-teal/10 p-4 md:p-6 rounded-2xl border border-teal/20">
            <div>
              <div class="flex justify-between items-start md:items-center gap-2 mb-4 pb-3 border-b border-teal/20">
                <div class="flex items-center gap-3 flex-wrap min-w-0">
                  <span class="font-bold text-base md:text-lg text-primary"><?php echo esc_html( $label_admin ); ?></span>
                  <span class="bg-teal text-white text-[10px] md:text-sm font-semibold py-1 px-3 rounded-full whitespace-nowrap"><?php echo esc_html( $badge_admin ); ?></span>
                </div>
                <span class="text-xs md:text-sm text-neutral whitespace-nowrap flex-shrink-0"><?php echo esc_html( $this->human_time_diff_fa( $reply['created_at'] ) ); ?></span>
              </div>
              <div class="text-sm md:text-lg leading-8 md:leading-9 text-primary mb-4">
                <?php echo esc_html( $reply['comment_text'] ); ?>
              </div>
            </div>
          </div>
        </div>
        <?php
        return ob_get_clean();
    }
}
