<?php
/**
 * Plugin Name: Social Digest
 * Plugin URI: https://github.com/BradLinder/social-digest
 * Description: Automated digest builder for Bluesky and Mastodon with tabbed admin workflows, next-run workbench, dry-run simulation, media optimization (WebP/AVIF), local asset caching, and RSS-only syndication.
 * Version: 5.7.1
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
    define('SOCIAL_DIGEST_VERSION', '5.7.1');
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
    $target_time = !empty($opts['schedule_time']) ? $opts['schedule_time'] : '17:00';
    list($hours, $minutes) = explode(':', $target_time);

    $now_local = new DateTimeImmutable('now', $site_tz);
    $target_run = $now_local->setTime((int)$hours, (int)$minutes, 0);

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

add_action('wp_head', function() {
    if (is_singular('post')) {
        ?>
        <style id="social-digest-embed-styles">
        /* Social Digest Native Card Styling */
        div.social-post.social-card {
            margin-left: auto !important;
            margin-right: auto !important;
            max-width: 600px !important;
            box-sizing: border-box !important;
            overflow-wrap: anywhere !important;
            word-break: break-word !important;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif;
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-left: 4px solid #0284c7;
            border-radius: 12px;
            padding: 18px;
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.04);
        }
        div.social-post.social-card a {
            text-decoration: none;
            transition: opacity 0.15s ease;
        }
        div.social-post.social-card a:hover {
            opacity: 0.85;
            text-decoration: underline;
        }
        div.social-post .social-avatar {
            border-radius: 50%;
            object-fit: cover;
        }
        div.social-post .social-follow-link:hover {
            text-decoration: underline !important;
            opacity: 0.85;
        }
        div.social-post .social-badge:hover {
            text-decoration: none !important;
            filter: brightness(0.96);
        }
        @media (max-width: 480px) {
            div.social-post .social-card-footer {
                flex-direction: column;
                align-items: flex-start !important;
            }
        }
        </style>
        <?php
    }

    // Fediverse Attribution Meta Tag (<meta name="fediverse:creator" content="@user@instance.social">)
    if (is_singular('post')) {
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
                if (get_post_meta($post->ID, '_social_digest_created', true) || get_post_meta($post->ID, '_social_digest_rss_only', true)) {
                    $is_digest = true;
                } elseif (strpos($post->post_content, 'class="social-post') !== false || strpos($post->post_content, 'wp:social-digest/') !== false) {
                    $is_digest = true;
                }
            }

            if ($is_digest) {
                // Format handle to standard @user@instance if full profile URL was provided
                if (filter_var($creator_handle, FILTER_VALIDATE_URL)) {
                    $parsed_host = parse_url($creator_handle, PHP_URL_HOST);
                    $parsed_path = trim(parse_url($creator_handle, PHP_URL_PATH) ?? '', '/');
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
    }
});

add_action('admin_enqueue_scripts', function($hook) {
    if ($hook === 'settings_page_social-digest-settings') {
        wp_enqueue_editor();
        wp_enqueue_script('postbox');
        wp_enqueue_script('jquery-ui-sortable');
    }
});

add_filter('plugin_action_links_' . plugin_basename(__FILE__), function($links) {
    $actions_link = '<a href="' . esc_url(admin_url('edit.php?page=social-digest-settings&tab=workbench')) . '"><strong>' . __('Actions & Preview') . '</strong></a>';
    $settings_link = '<a href="' . esc_url(admin_url('edit.php?page=social-digest-settings&tab=settings')) . '">' . __('Settings') . '</a>';
    array_unshift($links, $actions_link, $settings_link);
    return $links;
});

// Custom TinyMCE Toolbar Button for inserting the Splitter
add_filter('mce_buttons', function($buttons) {
    if (isset($_GET['page']) && $_GET['page'] === 'social-digest-settings') {
        $buttons[] = 'social_digest_split_button';
    }
    return $buttons;
});

add_filter('mce_external_plugins', function($plugins) {
    if (isset($_GET['page']) && $_GET['page'] === 'social-digest-settings') {
        $plugins['social_digest_split_plugin'] = plugin_dir_url(__FILE__) . 'assets/js/social-digest-editor.js';
    }
    return $plugins;
});

// Hide RSS-Only digests from main public queries if enabled
add_action('pre_get_posts', function($query) {
    if (!is_admin() && $query->is_main_query() && !$query->is_feed()) {
        $meta_query = $query->get('meta_query') ?: [];
        $meta_query[] = [
            'key'     => '_social_digest_rss_only',
            'compare' => 'NOT EXISTS'
        ];
        $query->set('meta_query', $meta_query);
    }
});

// ==========================================
// 4. CORE MODULE LOADERS
// ==========================================

require_once SOCIAL_DIGEST_PATH . 'includes/helpers.php';
require_once SOCIAL_DIGEST_PATH . 'includes/api-clients.php';
require_once SOCIAL_DIGEST_PATH . 'includes/feed-builder.php';
require_once SOCIAL_DIGEST_PATH . 'includes/admin.php';
