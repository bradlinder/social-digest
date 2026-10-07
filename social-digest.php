<?php
/**
 * Plugin Name: Social Digest
 * Plugin URI: https://github.com/BradLinder/social-digest
 * Description: Automated digest builder for Bluesky and Mastodon with tabbed admin workflows, next-run workbench, dry-run simulation, stock media sideloading, local asset caching, and RSS-only syndication.
 * Version: 5.8.21
 * Author: Brad Linder
 * Author URI: https://github.com/BradLinder
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: social-digest
 */

namespace SocialDigest;

if (!defined('ABSPATH')) exit;

// Plugin constants
if (!defined('SOCIAL_DIGEST_VERSION')) {
    define('SOCIAL_DIGEST_VERSION', '5.8.21');
}
if (!defined('SOCIAL_DIGEST_FILE')) {
    define('SOCIAL_DIGEST_FILE', __FILE__);
}
if (!defined('SOCIAL_DIGEST_PATH')) {
    define('SOCIAL_DIGEST_PATH', plugin_dir_path(__FILE__));
}
if (!defined('SOCIAL_DIGEST_URL')) {
    define('SOCIAL_DIGEST_URL', plugin_dir_url(__FILE__));
}

// Whitelist custom mobile app protocols so esc_url() preserves them
add_filter('kses_allowed_protocols', function($protocols) {
    if (!is_array($protocols)) {
        $protocols = is_null($protocols) ? [] : (array)$protocols;
    }
    if (!in_array('bsky', $protocols, true)) {
        $protocols[] = 'bsky';
    }
    if (!in_array('mastodon', $protocols, true)) {
        $protocols[] = 'mastodon';
    }
    return $protocols;
});

// ==========================================
// 1. UNINSTALLATION & LIFECYCLE HOOKS
// ==========================================

register_uninstall_hook(__FILE__, __NAMESPACE__ . '\social_digest_uninstall_cleanup');

function social_digest_uninstall_cleanup() {
    $opts = get_option('social_digest_options', []);

    wp_clear_scheduled_hook('social_digest_cron');
    wp_clear_scheduled_hook('bsky_digest_cron');
    wp_clear_scheduled_hook('bsky_daily_digest_cron');

    if (!empty($opts['wipe_data_on_uninstall'])) {
        delete_option('social_digest_options');
        delete_option('social_digest_logs');
        delete_option('social_digest_next_run');
        delete_option('social_digest_staging_queue');
        delete_option('social_digest_avatar_cache');
        delete_option('bsky_last_digest_time');
        delete_option('masto_last_digest_time');
        delete_option('bsky_digest_options');
    }
}

// ==========================================
// 2. CRON INTERVALS, SCHEDULES & HOOKS
// ==========================================

add_filter('cron_schedules', function($schedules) {
    if (!isset($schedules['six_hours'])) {
        $schedules['six_hours'] = ['interval' => 21600, 'display' => 'Every 6 Hours'];
    }
    if (!isset($schedules['twelve_hours'])) {
        $schedules['twelve_hours'] = ['interval' => 43200, 'display' => 'Every 12 Hours'];
    }

    $opts = get_option('social_digest_options', []);
    if (($opts['schedule_freq'] ?? 'interval_days') === 'interval_days') {
        $days = max(1, absint($opts['schedule_interval_days'] ?? 1));
        $schedules['social_custom_days'] = [
            'interval' => $days * DAY_IN_SECONDS,
            'display'  => "Every {$days} Day(s)"
        ];
    }

    return $schedules;
});

function social_reschedule_cron($opts) {
    wp_clear_scheduled_hook('social_digest_cron');

    $freq = $opts['schedule_freq'] ?? 'interval_days';
    if ($freq === 'disabled') {
        return;
    }

    if ($freq !== 'interval_days') {
        $intervals = [
            'hourly'       => HOUR_IN_SECONDS,
            'six_hours'    => 6 * HOUR_IN_SECONDS,
            'twelve_hours' => 12 * HOUR_IN_SECONDS,
        ];
        $delay = $intervals[$freq] ?? HOUR_IN_SECONDS;
        // Schedule future start to guarantee the activation HTTP cycle finishes cleanly
        wp_schedule_event(time() + $delay, $freq, 'social_digest_cron');
        return;
    }

    $site_tz = wp_timezone();
    $target_time = !empty($opts['schedule_time']) ? (string)$opts['schedule_time'] : '17:00';
    $time_parts = explode(':', $target_time);
    $hours = isset($time_parts[0]) ? (int)$time_parts[0] : 17;
    $minutes = isset($time_parts[1]) ? (int)$time_parts[1] : 0;

    $now_local = new \DateTimeImmutable('now', $site_tz);
    $target_run = $now_local->setTime($hours, $minutes, 0);

    if ($target_run->getTimestamp() <= time()) {
        $target_run = $target_run->modify('+1 day');
    }

    wp_schedule_event($target_run->getTimestamp(), 'social_custom_days', 'social_digest_cron');
}

register_activation_hook(__FILE__, function() {
    $opts = get_option('social_digest_options', []);
    social_reschedule_cron($opts);
});

register_deactivation_hook(__FILE__, function() {
    wp_clear_scheduled_hook('social_digest_cron');
});

add_action('social_digest_cron', __NAMESPACE__ . '\social_run_digest_import');

// ==========================================
// 3. ASSETS & FILTERS
// ==========================================

add_action('wp_enqueue_scripts', function() {
    if (!is_singular('post')) {
        return;
    }

    $opts = get_option('social_digest_options', []);
    $dark_mode_mode = $opts['dark_mode_mode'] ?? 'auto';

    // 1. Native Embed Stylesheet with cache busting
    wp_enqueue_style(
        'social-digest-embed',
        plugins_url('assets/css/embed.css', __FILE__),
        [],
        SOCIAL_DIGEST_VERSION
    );

    // If site forced dark mode is selected in settings, add inline override
    if ($dark_mode_mode === 'dark') {
        wp_add_inline_style(
            'social-digest-embed',
            'div.social-post.social-card { background: #0f172a !important; border-color: #334155 !important; color: #ffffff !important; }'
        );
    }

    // 2. Mobile Deep Linking Handler
    if (empty($opts['mobile_deep_links']) === false || !isset($opts['mobile_deep_links'])) {
        wp_enqueue_script(
            'social-digest-deep-links',
            plugins_url('assets/js/deep-links.js', __FILE__),
            [],
            SOCIAL_DIGEST_VERSION,
            true
        );
    }

    // 3. Smart Theme Adapter
    wp_enqueue_script(
        'social-digest-smart-theme',
        plugins_url('assets/js/smart-theme.js', __FILE__),
        [],
        SOCIAL_DIGEST_VERSION,
        true
    );
    wp_localize_script('social-digest-smart-theme', 'sdThemeConfig', [
        'mode' => $dark_mode_mode
    ]);

    // 4. Gallery Lightbox Connector
    $action = $opts['gallery_click_action'] ?? 'lightbox';
    if ($action === 'lightbox') {
        wp_enqueue_script(
            'social-digest-gallery',
            plugins_url('assets/js/gallery-lightbox.js', __FILE__),
            ['jquery'],
            SOCIAL_DIGEST_VERSION,
            true
        );
    }
});

// Fediverse Attribution Meta Tag (<meta name="fediverse:creator" content="@user@instance.social">)
add_action('wp_head', function() {
    if (!is_singular('post')) {
        return;
    }
        global $post;
        $opts = get_option('social_digest_options', []);
        $creator_handle = trim($opts['fediverse_creator'] ?? '');

        // Fallback to Mastodon handle if fediverse_creator is not explicitly set
        if (!$creator_handle && !empty($opts['masto_handle'])) {
            $creator_handle = trim($opts['masto_handle']);
        }

        if ($creator_handle) {
            // Check if this post is a social digest
            $is_digest = false;
            if ($post && !empty($post->ID)) {
                $post_content = (string)($post->post_content ?? '');
                if (get_post_meta($post->ID, '_social_digest_created', true) || get_post_meta($post->ID, '_social_digest_rss_only', true)) {
                    $is_digest = true;
                } elseif ($post_content !== '' && (strpos($post_content, 'class="social-post') !== false || strpos($post_content, 'wp:social-digest/') !== false)) {
                    $is_digest = true;
                }
            }

            if ($is_digest) {
                // Format handle to standard @user@instance if full profile URL was provided
                if (filter_var($creator_handle, FILTER_VALIDATE_URL)) {
                    $parsed_host = wp_parse_url($creator_handle, PHP_URL_HOST);
                    $raw_path = wp_parse_url($creator_handle, PHP_URL_PATH);
                    $parsed_path = is_string($raw_path) ? trim($raw_path, '/') : '';
                    $parts = explode('/', $parsed_path);
                    $account = end($parts);
                    if ($parsed_host && $account) {
                        $creator_handle = '@' . ltrim($account, '@') . '@' . $parsed_host;
                    }
                } elseif (strpos($creator_handle, '@') !== 0) {
                    $creator_handle = '@' . $creator_handle;
                }
                echo '<meta name="fediverse:creator" content="' . esc_attr($creator_handle) . '" />' . "\n";
            }
        }
});

add_action('admin_enqueue_scripts', function($hook) {
    if (
        strpos((string)$hook, 'social-digest-settings') !== false ||
        (isset($_GET['page']) && $_GET['page'] === 'social-digest-settings')
    ) {
        wp_enqueue_editor();
        wp_enqueue_script('postbox');
        wp_enqueue_script('jquery-ui-sortable');

        // Enqueue preview assets for workbench live preview
        wp_enqueue_style(
            'social-digest-embed',
            plugins_url('assets/css/embed.css', __FILE__),
            [],
            SOCIAL_DIGEST_VERSION
        );
        wp_enqueue_script(
            'social-digest-smart-theme',
            plugins_url('assets/js/smart-theme.js', __FILE__),
            [],
            SOCIAL_DIGEST_VERSION,
            true
        );
        $opts = get_option('social_digest_options', []);
        wp_localize_script('social-digest-smart-theme', 'sdThemeConfig', [
            'mode' => $opts['dark_mode_mode'] ?? 'auto'
        ]);
        wp_enqueue_script(
            'social-digest-deep-links',
            plugins_url('assets/js/deep-links.js', __FILE__),
            [],
            SOCIAL_DIGEST_VERSION,
            true
        );
    }
});

add_filter('plugin_action_links_' . plugin_basename(__FILE__), function($links) {
    if (!is_array($links)) {
        $links = [];
    }
    $actions_link = '<a href="' . esc_url(admin_url('edit.php?page=social-digest-settings&tab=workbench')) . '"><strong>' . __('Actions & Preview', 'social-digest') . '</strong></a>';
    $settings_link = '<a href="' . esc_url(admin_url('edit.php?page=social-digest-settings&tab=settings')) . '">' . __('Settings', 'social-digest') . '</a>';
    array_unshift($links, $actions_link, $settings_link);
    return $links;
});

// Hide RSS-Only digests from main public queries if enabled
add_action('pre_get_posts', function($query) {
    if (is_admin() || !is_object($query) || !method_exists($query, 'is_main_query') || !$query->is_main_query() || (method_exists($query, 'is_feed') && $query->is_feed())) {
        return;
    }
    $opts = get_option('social_digest_options', []);
    if (empty($opts['rss_only_mode'])) {
        return;
    }
    $meta_query = method_exists($query, 'get') ? (array)$query->get('meta_query') : [];
    $meta_query[] = [
        'key'     => '_social_digest_rss_only',
        'compare' => 'NOT EXISTS'
    ];
    if (method_exists($query, 'set')) {
        $query->set('meta_query', $meta_query);
    }
});



/**
 * Detect whether a third-party lightbox plugin is installed and active on the site.
 */
function social_digest_has_lightbox_plugin() {
    return function_exists('Responsive_Lightbox')
        || class_exists('Responsive_Lightbox')
        || class_exists('Responsive_Lightbox_Front')
        || class_exists('Simple_Lightbox')
        || class_exists('FooBox')
        || defined('FOOBOX_VERSION')
        || class_exists('WP_Featherlight')
        || class_exists('Easy_FancyBox')
        || function_exists('lightbox_gallery');
}

/**
 * Ensure Responsive Lightbox & Gallery (dFactory) scripts and styles are enqueued on Social Digest posts.
 * Bypasses Responsive Lightbox's conditional loading check which otherwise skips enqueuing
 * when posts contain custom HTML blocks or unlinked images in raw post_content.
 */
function social_digest_ensure_responsive_lightbox() {
    if (!function_exists('Responsive_Lightbox')) {
        return;
    }
    global $post;
    if (!is_singular() || !is_object($post) || empty($post->post_content)) {
        return;
    }
    if (strpos($post->post_content, 'social-post') !== false || strpos($post->post_content, 'social-embed') !== false) {
        $rl = Responsive_Lightbox();
        if (isset($rl->options['settings']['conditional_loading']) && $rl->options['settings']['conditional_loading'] === true) {
            $rl->options['settings']['conditional_loading'] = false;
        }
        if (!wp_script_is('responsive-lightbox', 'enqueued') && method_exists($rl, 'front_scripts_styles')) {
            $rl->front_scripts_styles();
        }
    }
}
add_action('wp_enqueue_scripts', 'SocialDigest\\social_digest_ensure_responsive_lightbox', 20);

/**
 * Native WordPress Lightbox Fallback
 * If no lightbox plugin (like Responsive Lightbox & Gallery) is active,
 * enqueues WordPress core's native image lightbox assets and registers the core lightbox overlay.
 */
function social_digest_enqueue_native_lightbox() {
    if (social_digest_has_lightbox_plugin()) {
        return;
    }

    $opts = get_option('social_digest_options', []);
    $action = $opts['gallery_click_action'] ?? 'lightbox';
    if ($action !== 'lightbox') {
        return;
    }

    global $post;
    if (!is_singular() || !is_object($post) || empty($post->post_content)) {
        return;
    }

    if (strpos($post->post_content, 'social-post') !== false || strpos($post->post_content, 'social-embed') !== false) {
        // Enqueue WordPress core image lightbox view script (Interactivity API, WP 6.4+)
        if (function_exists('wp_enqueue_script_module')) {
            wp_enqueue_script_module('@wordpress/block-library/image/view');
        } elseif (wp_script_is('wp-interactivity', 'registered')) {
            wp_enqueue_script('wp-interactivity');
        }

        if (wp_style_is('wp-block-image', 'registered')) {
            wp_enqueue_style('wp-block-image');
        }
        if (wp_style_is('wp-block-library', 'registered')) {
            wp_enqueue_style('wp-block-library');
        }

        // Print native WordPress core lightbox overlay in footer if available
        if (function_exists('block_core_image_print_lightbox_overlay')) {
            add_action('wp_footer', 'block_core_image_print_lightbox_overlay');
        }
    }
}
add_action('wp_enqueue_scripts', 'SocialDigest\\social_digest_enqueue_native_lightbox', 25);



// ==========================================
// 4. CORE MODULE LOADERS
// ==========================================

$includes_path = SOCIAL_DIGEST_PATH . 'includes/';
$module_files  = [
    'helpers'      => $includes_path . 'helpers.php',
    'cards'        => $includes_path . 'cards.php',
    'tags'         => $includes_path . 'tags.php',
    'media'        => $includes_path . 'media.php',
    'api-clients'  => $includes_path . 'api-clients.php',
    'feed-builder' => $includes_path . 'feed-builder.php',
    'admin'        => $includes_path . 'admin.php',
];

$missing_modules = [];
foreach ($module_files as $file) {
    if (file_exists($file)) {
        require_once $file;
    } else {
        $missing_modules[] = basename($file);
    }
}

if (!empty($missing_modules)) {
    add_action('admin_notices', function() use ($missing_modules) {
        if (!current_user_can('manage_options')) return;
        echo '<div class="notice notice-error"><p><strong>Social Digest Error:</strong> Required module file(s) (<code>' . esc_html(implode(', ', $missing_modules)) . '</code>) could not be found in <code>' . esc_html(SOCIAL_DIGEST_PATH . 'includes/') . '</code>. Please reinstall the complete plugin package with the <code>includes/</code> folder intact.</p></div>';
    });
}
