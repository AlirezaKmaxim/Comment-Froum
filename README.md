# MD Custom Comments Plugin Architecture — Development & Maintenance Guide

## Overview

---

## Directory Structure

md-custom-comments/
├── md-custom-comments.php # Bootstrap (main entry point)
├── package.json # Node dependencies (Tailwind CLI)
├── tailwind.config.js # Tailwind configuration
├── src/
│ ├── input.css # Tailwind source (for build)
│ ├── Core/
│ │ ├── Plugin.php # Central core + DI container
│ │ ├── Activator.php # Activation hook
│ │ ├── Installer.php # Create database table + default settings
│ │ └── Logger.php # File-based logging system
│ ├── Front/
│ │ ├── Shortcode.php # Registers [md_comments] shortcode
│ │ ├── View.php # Renders HTML form + comment list
│ │ ├── Controller.php # AJAX handler for comment submission
│ │ └── Assets.php # Conditional front-end CSS/JS loading
│ ├── Admin/
│ │ ├── Assets.php # Conditional admin CSS/JS loading
│ │ └── Controllers/
│ │ └── SettingsController.php # Settings page + comment management
│ ├── Database/
│ │ └── CommentRepository.php # All database queries
│ ├── Security/
│ │ └── Validator.php # Input validation (Zero-Trust)
│ └── Events/
│ ├── EventDispatcher.php # Abstract layer for do_action/apply_filters
│ └── Listeners/
│ ├── SendTelegram.php # Send Telegram webhook
│ ├── SendBale.php # Send Bale message
│ └── SendSlack.php # Send Slack webhook
├── config/
│ └── settings.php # Admin menu settings
├── assets/
│ ├── front/
│ │ ├── front.css # Compiled front-end style
│ │ └── front.js # Front-end script (stars, AJAX submit)
│ └── admin/
│ ├── admin.js # Admin media uploader
│ ├── alpine.min.js # Alpine.js (local)
│ └── tailwind.min.js # Tailwind (local)
└── docs/
├── dev_guide.md # Development guide
├── styling-guide.md # Styling guide
└── architecture.md # ← This file


---

## Execution Flow

### 1. Plugin Loading

wp-admin/plugins.php → Activation
↓
register_activation_hook → Activator::activate()
↓
Installer::install() → Create wp_md_comments table + default settings

### 2. Bootstrap on Every Page
WordPress → plugins_loaded hook
↓
Plugin::instance() (Singleton pattern)
↓
bootstrap():
├── Check database version (auto-update)
├── new Front\Assets() → Register hooks for front-end CSS/JS
├── new Front\Controller() → Register hooks for AJAX
├── new Front\Shortcode() → Register [md_comments] shortcode
├── if (is_admin):
│ ├── new Admin\Assets() → Register hooks for admin asset loading
│ └── new Admin\SettingsController() → Admin menu + admin AJAX
└── register_events() → Register EDA hooks

### 3. Front-End Rendering with Shortcode
[md_comments] in post content
↓
Shortcode::render()
↓
Assets::enqueue_assets() → Load front.css + front.js
↓
View::render() → Full HTML (form + comment list)

### 4. Submitting a Comment via AJAX
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
└── 
w
p
d
b
−
>
i
n
s
e
r
t
w
i
t
h
p
r
e
p
a
r
e
↓
d
o
a
c
t
i
o
n
(
′
m
d
c
o
m
m
e
n
t
i
n
s
e
r
t
e
d
′
,
wpdb−>insertwithprepare↓do 
a
​
 ction( 
′
 md 
c
​
 omment 
i
​
 nserted 
′
 ,comment_id, $data)
↓
Plugin::dispatch_comment_events()
├── SendTelegram::dispatch()
└── SendBale::dispatch()
↓
wp_send_json_success() → front.js appends comment to DOM

md-custom-comments.php
└── Depends on: All src/ classes via autoloader
└── Note: It's only a loader, contains no logic

Core/Plugin.php
└── Depends on: Front/* , Admin/* , Events/* (initialized in bootstrap)
└── Depends on: Core/Installer (database update)
└── Events: md_comment_inserted → dispatch_comment_events
└── Pattern: Singleton — global access via Plugin::instance()

Core/Installer.php
└── Called by: Core/Activator , Core/Plugin
└── Creates: wp_md_comments table + default wp_options

Front/Shortcode.php
└── Depends on: Front/Assets , Front/View
└── Registers: add_shortcode('md_comments')

Front/View.php
└── Depends on: Database/CommentRepository
└── Depends on: get_option('md_comments_settings')
└── Helper methods: get_avatar_html, to_persian_num, human_time_diff_fa

Front/Controller.php
└── Depends on: Security/Validator , Database/CommentRepository
└── AJAX actions: wp_ajax_md_submit_comment

Front/Assets.php
└── Depends on: assets/front/front.css , assets/front/front.js
└── Hook: wp_enqueue_scripts

Admin/Assets.php
└── Depends on: assets/admin/{admin.js, alpine.min.js, tailwind.min.js}
└── Hook: admin_enqueue_scripts (conditional: only settings page)

Admin/SettingsController.php
└── Depends on: config/settings.php , Database/CommentRepository
└── AJAX: md_save_settings, md_get_admin_comments, md_change_comment_status,
│ md_delete_comment, md_reply_comment, md_edit_comment, md_test_bale
└── Hooks: admin_menu, admin_init, admin_bar_menu
└── Render: render_settings_page() — HTML with Alpine.js + Tailwind

Database/CommentRepository.php
└── Depends on: $wpdb (only class that talks to the database)
└── Methods: insert, get_approved_comments, get_all_comments_for_admin,
│ get_comments_count, get_unique_users_count, update, update_status, delete
└── Event: do_action('md_comment_inserted') after insert

Security/Validator.php
└── Static: validate_comment_submission()
└── Depends on: wp_verify_nonce, sanitize_text_field, preg_match

Events/Listeners/SendTelegram.php
└── Depends on: get_option('md_comments_settings')
└── Uses: Action Scheduler (as_enqueue_async_action)
└── API: wp_remote_post to webhook_url

Events/Listeners/SendBale.php
└── Depends on: get_option('md_comments_settings')
└── API: wp_remote_post to https://tapi.bale.ai/bot...

Events/Listeners/SendSlack.php
└── Depends on: get_option('md_comments_settings')
└── API: wp_remote_post with payload containing attachments


---

## Database Table

### `wp_md_comments`
| Field | Type | Description |
|------|-----|--------|
| id | bigint(20) PK | Unique identifier |
| post_id | bigint(20) | Associated post |
| user_id | bigint(20) | WordPress user (0 = guest) |
| user_name | varchar(100) | User's name |
| user_phone | varchar(20) | Mobile number |
| avatar_id | int(11) | Selected avatar ID (1-4) |
| rating | tinyint(4) | Rating (1-5) |
| comment_text | text | Comment text |
| status | varchar(20) | hold / approved |
| parent_id | bigint(20) | Reply to which comment (0 = top-level) |
| ip_address | varchar(45) | Sender's IP |
| created_at | datetime | Registration date |

---

## Event-Driven Architecture (EDA)

Custom events:

| Event | Sender | Receiver | Description |
|--------|---------|--------|-------|
| `md_comment_inserted` | `CommentRepository::insert()` | `Plugin::dispatch_comment_events()` | After a comment is saved in the database |
| `md_send_telegram_webhook_async` | `SendTelegram::dispatch()` | `Plugin::handle_telegram_async()` | Asynchronous Telegram webhook sending |
| `md_send_bale_async` | `SendBale::dispatch()` | `Plugin::handle_bale_async()` | Asynchronous Bale message sending |

To add a new messenger:
1. Create a new listener file in `src/Events/Listeners/`
2. Implement the `dispatch()` method
3. Instantiate and call it in `Plugin::dispatch_comment_events()`
4. Add configuration fields in `SettingsController`

---

## Important Maintenance Notes

### Adding a New Database Field
1. Add an `ALTER TABLE` statement to `Installer::install()`
2. Add corresponding methods to `CommentRepository`
3. Validate the field in `Validator::validate_comment_submission()`
4. Display the field in `View::render()` and `SettingsController`

### Adding a New Admin Setting
1. Add the default value in `Installer::set_default_options()`
2. Add the HTML field in `SettingsController::render_settings_page()`
3. Add saving logic in `SettingsController::save_settings_ajax()`
4. Read the value in `View.php` using `get_option('md_comments_settings')`

### Security Notes
- **All** inputs pass through `Validator` (Nonce + Honeypot + Sanitize)
- **Only** `CommentRepository` interacts with the database using `$wpdb->prepare()`
- PHP files are protected from direct access with `defined('ABSPATH') || exit;`
