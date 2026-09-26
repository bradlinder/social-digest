<?php
/**
 * Plugin Name: Social Digest
 * Plugin URI: https://github.com/BradLinder/social-digest
 * Description: Automated digest builder for Bluesky and Mastodon with tabbed admin workflows, next-run workbench, dry-run simulation, stock media sideloading, local asset caching, and RSS-only syndication.
 * Version: 5.7.46
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
    define('SOCIAL_DIGEST_VERSION', '5.7.46');
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

add_action('wp_head', function() {
    if (is_singular('post')) {
        $opts = get_option('social_digest_options', []);
        $dark_mode_mode = $opts['dark_mode_mode'] ?? 'auto';
        ?>
        <style id="social-digest-embed-styles">
        /* Social Digest Native Card Base Styling (Light Mode Default) */
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
            color: #1e293b;
            transition: background 0.2s ease, border-color 0.2s ease, color 0.2s ease;
        }
        div.social-post.social-card a {
            color: #0284c7;
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
        div.social-post .social-link-card {
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            overflow: hidden;
            margin: 12px 0;
            max-width: 500px;
            background: #f8fafc;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.04);
            text-decoration: none !important;
        }
        div.social-post .social-link-card-title {
            font-weight: 700 !important;
            font-size: 14px !important;
            margin-bottom: 4px;
            color: #0f172a;
            line-height: 1.35;
        }
        div.social-post .social-link-card-desc {
            font-weight: 400 !important;
            font-size: 12.5px !important;
            color: #475569;
            line-height: 1.45;
            margin-bottom: 6px;
        }
        div.social-post .social-link-card-domain {
            display: flex;
            align-items: center;
            gap: 5px;
            font-size: 12px !important;
            color: #64748b;
            font-weight: 400 !important;
            margin-top: 4px;
        }
        @media (max-width: 480px) {
            div.social-post .social-card-footer {
                flex-direction: column;
                align-items: flex-start !important;
            }
        }

        <?php if ($dark_mode_mode === 'auto' || $dark_mode_mode === 'dark'): ?>
        /* =========================================================================
           Dark Mode Styles: High-contrast pure white typography & theme matching
           ========================================================================= */
        div.social-post.social-card.is-dark,
        div.social-post.social-card[data-social-theme="dark"],
        .dark div.social-post.social-card:not(.is-light):not([data-social-theme="light"]),
        .dark-theme div.social-post.social-card:not(.is-light):not([data-social-theme="light"]),
        .dark-mode div.social-post.social-card:not(.is-light):not([data-social-theme="light"]),
        body.dark-mode div.social-post.social-card:not(.is-light):not([data-social-theme="light"]),
        [data-theme="dark"] div.social-post.social-card:not(.is-light):not([data-social-theme="light"]),
        [data-bs-theme="dark"] div.social-post.social-card:not(.is-light):not([data-social-theme="light"]),
        [data-color-scheme="dark"] div.social-post.social-card:not(.is-light):not([data-social-theme="light"]),
        .is-dark-theme div.social-post.social-card:not(.is-light):not([data-social-theme="light"]),
        .theme-dark div.social-post.social-card:not(.is-light):not([data-social-theme="light"]),
        .night-mode div.social-post.social-card:not(.is-light):not([data-social-theme="light"])
        <?php if ($dark_mode_mode === 'dark') echo ', div.social-post.social-card'; ?> {
            background: #0f172a !important;
            border-color: #334155 !important;
            border-left: 4px solid #38bdf8 !important;
            color: #ffffff !important;
            box-shadow: 0 4px 14px rgba(0, 0, 0, 0.45) !important;
        }

        /* Body & Content Text in Dark Mode (Crisp pure white #ffffff) */
        div.social-post.social-card.is-dark .social-card-body,
        div.social-post.social-card.is-dark .social-card-body p,
        div.social-post.social-card[data-social-theme="dark"] .social-card-body,
        div.social-post.social-card[data-social-theme="dark"] .social-card-body p,
        .dark div.social-post.social-card:not(.is-light):not([data-social-theme="light"]) .social-card-body,
        .dark-theme div.social-post.social-card:not(.is-light):not([data-social-theme="light"]) .social-card-body,
        .dark-mode div.social-post.social-card:not(.is-light):not([data-social-theme="light"]) .social-card-body,
        [data-theme="dark"] div.social-post.social-card:not(.is-light):not([data-social-theme="light"]) .social-card-body,
        [data-bs-theme="dark"] div.social-post.social-card:not(.is-light):not([data-social-theme="light"]) .social-card-body
        <?php if ($dark_mode_mode === 'dark') echo ', div.social-post.social-card .social-card-body, div.social-post.social-card .social-card-body p'; ?> {
            color: #ffffff !important;
        }

        /* Author Name in Dark Mode (Pure white #ffffff) */
        div.social-post.social-card.is-dark .social-author-name,
        div.social-post.social-card[data-social-theme="dark"] .social-author-name,
        .dark div.social-post.social-card:not(.is-light):not([data-social-theme="light"]) .social-author-name,
        .dark-theme div.social-post.social-card:not(.is-light):not([data-social-theme="light"]) .social-author-name,
        .dark-mode div.social-post.social-card:not(.is-light):not([data-social-theme="light"]) .social-author-name,
        [data-theme="dark"] div.social-post.social-card:not(.is-light):not([data-social-theme="light"]) .social-author-name,
        [data-bs-theme="dark"] div.social-post.social-card:not(.is-light):not([data-social-theme="light"]) .social-author-name
        <?php if ($dark_mode_mode === 'dark') echo ', div.social-post.social-card .social-author-name'; ?> {
            color: #ffffff !important;
        }

        /* Author Handles, Timestamps & Footer Metadata in Dark Mode (High-contrast slate-200 #e2e8f0) */
        div.social-post.social-card.is-dark .social-author-handles,
        div.social-post.social-card.is-dark .social-author-handles a,
        div.social-post.social-card.is-dark .social-identity a,
        div.social-post.social-card.is-dark .social-timestamp,
        div.social-post.social-card.is-dark .social-card-footer,
        div.social-post.social-card.is-dark .social-identity-sep,
        div.social-post.social-card[data-social-theme="dark"] .social-author-handles,
        div.social-post.social-card[data-social-theme="dark"] .social-author-handles a,
        div.social-post.social-card[data-social-theme="dark"] .social-identity a,
        div.social-post.social-card[data-social-theme="dark"] .social-timestamp,
        div.social-post.social-card[data-social-theme="dark"] .social-card-footer,
        div.social-post.social-card[data-social-theme="dark"] .social-identity-sep,
        .dark div.social-post.social-card:not(.is-light):not([data-social-theme="light"]) .social-author-handles,
        .dark div.social-post.social-card:not(.is-light):not([data-social-theme="light"]) .social-author-handles a,
        .dark div.social-post.social-card:not(.is-light):not([data-social-theme="light"]) .social-identity a,
        .dark div.social-post.social-card:not(.is-light):not([data-social-theme="light"]) .social-timestamp,
        .dark div.social-post.social-card:not(.is-light):not([data-social-theme="light"]) .social-card-footer,
        .dark div.social-post.social-card:not(.is-light):not([data-social-theme="light"]) .social-identity-sep
        <?php if ($dark_mode_mode === 'dark') echo ', div.social-post.social-card .social-author-handles, div.social-post.social-card .social-author-handles a, div.social-post.social-card .social-identity a, div.social-post.social-card .social-timestamp, div.social-post.social-card .social-card-footer, div.social-post.social-card .social-identity-sep'; ?> {
            color: #e2e8f0 !important;
        }

        /* Links in Dark Mode (Vibrant sky-blue #38bdf8) */
        div.social-post.social-card.is-dark a,
        div.social-post.social-card[data-social-theme="dark"] a,
        div.social-post.social-card.is-dark .social-card-body a,
        div.social-post.social-card[data-social-theme="dark"] .social-card-body a,
        .dark div.social-post.social-card:not(.is-light):not([data-social-theme="light"]) a,
        .dark div.social-post.social-card:not(.is-light):not([data-social-theme="light"]) .social-card-body a
        <?php if ($dark_mode_mode === 'dark') echo ', div.social-post.social-card a, div.social-post.social-card .social-card-body a'; ?> {
            color: #38bdf8 !important;
        }

        /* Borders & Headers in Dark Mode */
        div.social-post.social-card.is-dark .social-card-header,
        div.social-post.social-card[data-social-theme="dark"] .social-card-header,
        div.social-post.social-card.is-dark .social-card-footer,
        div.social-post.social-card[data-social-theme="dark"] .social-card-footer,
        .dark div.social-post.social-card:not(.is-light):not([data-social-theme="light"]) .social-card-header,
        .dark div.social-post.social-card:not(.is-light):not([data-social-theme="light"]) .social-card-footer
        <?php if ($dark_mode_mode === 'dark') echo ', div.social-post.social-card .social-card-header, div.social-post.social-card .social-card-footer'; ?> {
            border-color: #334155 !important;
        }

        /* Nested Link Preview Cards in Dark Mode */
        div.social-post.social-card.is-dark .social-link-card,
        div.social-post.social-card[data-social-theme="dark"] .social-link-card,
        .dark div.social-post.social-card:not(.is-light):not([data-social-theme="light"]) .social-link-card
        <?php if ($dark_mode_mode === 'dark') echo ', div.social-post.social-card .social-link-card'; ?> {
            background: #1e293b !important;
            border-color: #334155 !important;
        }
        div.social-post.social-card.is-dark .social-link-card .social-link-card-domain,
        div.social-post.social-card[data-social-theme="dark"] .social-link-card .social-link-card-domain {
            color: #cbd5e1 !important;
        }
        div.social-post.social-card.is-dark .social-link-card .social-link-card-title,
        div.social-post.social-card[data-social-theme="dark"] .social-link-card .social-link-card-title {
            color: #38bdf8 !important;
        }
        div.social-post.social-card.is-dark .social-link-card .social-link-card-desc,
        div.social-post.social-card[data-social-theme="dark"] .social-link-card .social-link-card-desc {
            color: #ffffff !important;
        }

        /* Thread Replies in Dark Mode */
        div.social-post.social-card.is-dark .social-thread-collapse,
        div.social-post.social-card[data-social-theme="dark"] .social-thread-collapse,
        .dark div.social-post.social-card:not(.is-light):not([data-social-theme="light"]) .social-thread-collapse
        <?php if ($dark_mode_mode === 'dark') echo ', div.social-post.social-card .social-thread-collapse'; ?> {
            border-top-color: #334155 !important;
        }
        div.social-post.social-card.is-dark .social-thread-replies,
        div.social-post.social-card[data-social-theme="dark"] .social-thread-replies,
        .dark div.social-post.social-card:not(.is-light):not([data-social-theme="light"]) .social-thread-replies
        <?php if ($dark_mode_mode === 'dark') echo ', div.social-post.social-card .social-thread-replies'; ?> {
            border-left-color: #38bdf8 !important;
        }
        div.social-post.social-card.is-dark .social-thread-reply-item,
        div.social-post.social-card[data-social-theme="dark"] .social-thread-reply-item,
        .dark div.social-post.social-card:not(.is-light):not([data-social-theme="light"]) .social-thread-reply-item
        <?php if ($dark_mode_mode === 'dark') echo ', div.social-post.social-card .social-thread-reply-item'; ?> {
            color: #ffffff !important;
        }

        /* Badges & Stat Pills in Dark Mode */
        div.social-post.social-card.is-dark .social-badge.bsky-badge,
        div.social-post.social-card[data-social-theme="dark"] .social-badge.bsky-badge {
            background: #082f49 !important;
            border-color: #0284c7 !important;
            color: #7dd3fc !important;
        }
        div.social-post.social-card.is-dark .social-badge.masto-badge,
        div.social-post.social-card[data-social-theme="dark"] .social-badge.masto-badge {
            background: #2e1065 !important;
            border-color: #7e22ce !important;
            color: #d8b4fe !important;
        }
        div.social-post.social-card.is-dark .social-stat-pill,
        div.social-post.social-card[data-social-theme="dark"] .social-stat-pill {
            background: #1e293b !important;
            color: #ffffff !important;
            border: 1px solid #334155 !important;
        }
        <?php endif; ?>
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
 * Smart Mobile App Deep-Linking Handler
 * On mobile devices, clicking a Bluesky or Mastodon badge launches the installed app.
 * If the native app is not installed, it cleanly opens the web URL in the browser without requiring extra buttons.
 */
function social_digest_render_smart_deep_links_script() {
    $opts = get_option('social_digest_options', []);
    if (isset($opts['mobile_deep_links']) && empty($opts['mobile_deep_links'])) {
        return;
    }
    ?>
    <script id="social-digest-smart-deep-links">
    (function() {
        function initSocialDeepLinks() {
            document.addEventListener('click', function(e) {
                var badge = e.target.closest('a.social-badge[data-app-url]');
                if (!badge) return;

                var appUrl = badge.getAttribute('data-app-url');
                var webUrl = badge.getAttribute('href');
                if (!appUrl) return;

                // Only intercept on mobile / tablet touch devices
                var isMobile = /iPhone|iPad|iPod|Android/i.test(navigator.userAgent || '');
                if (!isMobile) return;

                e.preventDefault();

                var appOpened = false;
                var onVisibilityChange = function() {
                    if (document.hidden || document.webkitHidden) {
                        appOpened = true;
                    }
                };
                var onBlurOrHide = function() {
                    appOpened = true;
                };

                document.addEventListener('visibilitychange', onVisibilityChange, { once: true });
                window.addEventListener('pagehide', onBlurOrHide, { once: true });
                window.addEventListener('blur', onBlurOrHide, { once: true });

                var start = Date.now();

                // Attempt to launch native app via custom URI scheme
                window.location.href = appUrl;

                // Fallback: If device has not switched focus to native app, open browser URL
                setTimeout(function() {
                    document.removeEventListener('visibilitychange', onVisibilityChange);
                    window.removeEventListener('pagehide', onBlurOrHide);
                    window.removeEventListener('blur', onBlurOrHide);

                    if (!appOpened && (Date.now() - start < 2000)) {
                        window.open(webUrl, '_blank', 'noopener,noreferrer');
                    }
                }, 800);
            });
        }

        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', initSocialDeepLinks);
        } else {
            initSocialDeepLinks();
        }
    })();
    </script>
    <?php
}
add_action('wp_footer', 'SocialDigest\\social_digest_render_smart_deep_links_script');

/**
 * Smart Theme & Preference Adaptor for Social Digest Native Cards
 * Dynamically resolves website light/dark themes, body background luminance, and user preferences.
 * Ensures cards never display dark-mode styles on a light website (or light styles on a dark website),
 * and live-adapts to theme toggle buttons across popular WordPress themes.
 */
function social_digest_render_smart_theme_script() {
    $opts = get_option('social_digest_options', []);
    $mode = $opts['dark_mode_mode'] ?? 'auto';
    ?>
    <script id="social-digest-theme-adapter">
    (function() {
        var forcedMode = <?php echo json_encode($mode); ?>;

        function getLuminance(rgbStr) {
            if (!rgbStr || rgbStr === 'transparent' || rgbStr.indexOf('rgba(0, 0, 0, 0)') === 0) return null;
            var match = rgbStr.match(/rgba?\((\d+),\s*(\d+),\s*(\d+)/i);
            if (!match) return null;
            var r = parseInt(match[1], 10) / 255;
            var g = parseInt(match[2], 10) / 255;
            var b = parseInt(match[3], 10) / 255;
            return (0.2126 * r) + (0.7152 * g) + (0.0722 * b);
        }

        function detectSiteTheme() {
            if (forcedMode === 'dark') return 'dark';
            if (forcedMode === 'light') return 'light';

            var docEl = document.documentElement;
            var body = document.body || docEl;

            // 1. Check explicit dark selectors on html/body
            var darkSelectors = [
                '.dark', '.dark-theme', '.dark-mode', '.theme-dark', '.is-dark-theme', '.night-mode',
                '[data-theme="dark"]', '[data-bs-theme="dark"]', '[data-color-scheme="dark"]', '[data-theme-mode="dark"]',
                '[data-theme*="dark"]', '[class*="dark-theme"]', '[class*="theme-dark"]'
            ];
            for (var i = 0; i < darkSelectors.length; i++) {
                try {
                    if (docEl.matches(darkSelectors[i]) || (body && body.matches(darkSelectors[i]))) {
                        return 'dark';
                    }
                } catch(e) {}
            }

            // 2. Check explicit light selectors on html/body
            var lightSelectors = [
                '.light', '.light-theme', '.light-mode', '.theme-light', '.is-light-theme', '.day-mode',
                '[data-theme="light"]', '[data-bs-theme="light"]', '[data-color-scheme="light"]', '[data-theme-mode="light"]'
            ];
            for (var j = 0; j < lightSelectors.length; j++) {
                try {
                    if (docEl.matches(lightSelectors[j]) || (body && body.matches(lightSelectors[j]))) {
                        return 'light';
                    }
                } catch(e) {}
            }

            // 3. Computed background luminance check of body / article / container
            var elementsToCheck = [
                body,
                docEl,
                document.querySelector('main'),
                document.querySelector('article'),
                document.querySelector('.entry-content'),
                document.querySelector('.post-content')
            ];

            for (var k = 0; k < elementsToCheck.length; k++) {
                var el = elementsToCheck[k];
                if (!el) continue;
                try {
                    var bg = window.getComputedStyle(el).backgroundColor;
                    var lum = getLuminance(bg);
                    if (lum !== null) {
                        // Luminance < 0.35 represents a dark background; >= 0.35 represents a light background
                        return (lum < 0.35) ? 'dark' : 'light';
                    }
                } catch(e) {}
            }

            // 4. Default to 'light' for websites (prevents dark cards on standard white WordPress themes)
            return 'light';
        }

        function updateSocialCardsTheme() {
            var theme = detectSiteTheme();
            var cards = document.querySelectorAll('div.social-post.social-card');
            for (var i = 0; i < cards.length; i++) {
                var card = cards[i];
                card.setAttribute('data-social-theme', theme);
                if (theme === 'dark') {
                    card.classList.add('is-dark');
                    card.classList.remove('is-light');
                } else {
                    card.classList.add('is-light');
                    card.classList.remove('is-dark');
                }
            }
        }

        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', updateSocialCardsTheme);
        } else {
            updateSocialCardsTheme();
        }
        window.addEventListener('load', updateSocialCardsTheme);

        // Listen for system preference changes
        if (window.matchMedia) {
            window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', updateSocialCardsTheme);
        }

        // MutationObserver to adapt dynamically to site dark-mode toggle buttons
        if (window.MutationObserver) {
            var observer = new MutationObserver(function() {
                updateSocialCardsTheme();
            });
            if (document.documentElement) {
                observer.observe(document.documentElement, { attributes: true, attributeFilter: ['class', 'data-theme', 'data-bs-theme', 'data-color-scheme', 'style'] });
            }
            if (document.body) {
                observer.observe(document.body, { attributes: true, attributeFilter: ['class', 'data-theme', 'data-bs-theme', 'data-color-scheme', 'style'] });
            }
        }
    })();
    </script>
    <?php
}
add_action('wp_footer', 'SocialDigest\\social_digest_render_smart_theme_script');
add_action('admin_footer', function() {
    $screen = function_exists('get_current_screen') ? get_current_screen() : null;
    if ($screen && strpos($screen->id, 'social-digest') !== false) {
        social_digest_render_smart_theme_script();
        social_digest_render_smart_deep_links_script();
    }
});

// ==========================================
// 4. CORE MODULE LOADERS
// ==========================================

$includes_path = SOCIAL_DIGEST_PATH . 'includes/';
$module_files  = [
    'helpers'      => $includes_path . 'helpers.php',
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
