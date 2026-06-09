# Comment-Froum
an speical comment froum plugin with multiple avatar selection + like or dislike + replay comment + admin panel for comment management + extenal webhook 
Namespace:    MDCustomComments\
Prefix:       md_comments_ / md_
DB Version:   1.0.0
Text Domain:  md-custom-comments


md-custom-comments/
├── md-custom-comments.php          # Bootstrap (main entry point)
├── package.json                     # Node dependencies (Tailwind CLI)
├── tailwind.config.js               # Tailwind configuration
├── src/
│   ├── input.css                    # Tailwind source (for build)
│   ├── Core/
│   │   ├── Plugin.php               # Central core + DI container
│   │   ├── Activator.php            # Activation hook
│   │   ├── Installer.php            # Create database table + default settings
│   │   └── Logger.php               # File-based logging system
│   ├── Front/
│   │   ├── Shortcode.php            # Registers [md_comments] shortcode
│   │   ├── View.php                 # Renders HTML form + comment list
│   │   ├── Controller.php           # AJAX handler for comment submission
│   │   └── Assets.php               # Conditional front-end CSS/JS loading
│   ├── Admin/
│   │   ├── Assets.php               # Conditional admin CSS/JS loading
│   │   └── Controllers/
│   │       └── SettingsController.php  # Settings page + comment management
│   ├── Database/
│   │   └── CommentRepository.php    # All database queries
│   ├── Security/
│   │   └── Validator.php            # Input validation (Zero-Trust)
│   └── Events/
│       ├── EventDispatcher.php      # Abstract layer for do_action/apply_filters
│       └── Listeners/
│           ├── SendTelegram.php     # Send Telegram webhook
│           ├── SendBale.php         # Send Bale message
│           └── SendSlack.php        # Send Slack webhook
├── config/
│   └── settings.php                 # Admin menu settings
├── assets/
│   ├── front/
│   │   ├── front.css                # Compiled front-end style
│   │   └── front.js                 # Front-end script (stars, AJAX submit)
│   └── admin/
│       ├── admin.js                 # Admin media uploader
│       ├── alpine.min.js            # Alpine.js (local)
│       └── tailwind.min.js           # Tailwind (local)
----------------------------------


 Plugin Loading
 
wp-admin/plugins.php → Activation
       ↓
register_activation_hook → Activator::activate()
       ↓
Installer::install() → Create wp_md_comments table + default settings

Bootstrap on Every Page
WordPress → plugins_loaded hook
       ↓
Plugin::instance()  (Singleton pattern)
       ↓
bootstrap():
  ├── Check database version (auto-update)
  ├── new Front\Assets()        → Register hooks for front-end CSS/JS
  ├── new Front\Controller()    → Register hooks for AJAX
  ├── new Front\Shortcode()     → Register [md_comments] shortcode
  ├── if (is_admin):
  │   ├── new Admin\Assets()          → Register hooks for admin asset loading
  │   └── new Admin\SettingsController() → Admin menu + admin AJAX
  └── register_events()         → Register EDA hooks

Front-End Rendering with Shortcode
[md_comments] in post content
       ↓
Shortcode::render()
       ↓
Assets::enqueue_assets()  → Load front.css + front.js
       ↓
View::render()  → Full HTML (form + comment list)



Submitting a Comment via AJAX
User → Click submit
       ↓
front.js → fetch() to admin-ajax.php
       ↓
Controller::handle_submit()
       ↓
Validator::validate_comment_submission()
  ├── Nonce check
  ├── Honeypot check
  ├── sanitize_text_field / wp_kses_post
  ├── Iranian mobile number regex
  └── Determine status (approved/hold)
       ↓
CommentRepository::insert()
  └── $wpdb->insert with prepare
       ↓
do_action('md_comment_inserted', $comment_id, $data)
       ↓
Plugin::dispatch_comment_events()
  ├── SendTelegram::dispatch()
  └── SendBale::dispatch()
       ↓
wp_send_json_success() → front.js appends comment to DOM
    
