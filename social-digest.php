<?php
/**
 * Plugin Name: Social Digest
 * Plugin URI: https://github.com/BradLinder/social-digest
 * Description: Automated digest builder for Bluesky and Mastodon with tabbed admin workflows, next-run workbench, dry-run simulation, media optimization (WebP/AVIF), local asset caching, and RSS-only syndication.
 * Version: 5.6.4
 * Author: Brad Linder
 * Author URI: https://github.com/BradLinder
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: social-digest
 */

namespace SocialDigest;

if (!defined('ABSPATH')) exit;

// ==========================================
// 1. UNINSTALLATION & LIFECYCLE HOOKS
// ==========================================

register_uninstall_hook(__FILE__, __NAMESPACE__ . '\\social_digest_uninstall_cleanup');

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
// 2. CRON INTERVALS & ASSETS
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
// 3. ADMIN SETTINGS, TABS & WORKBENCH ACTIONS
// ==========================================

add_action('admin_menu', function() {
    // Primary placement: Submenu directly under Posts (defaults to Actions & Preview)
    add_submenu_page(
        'edit.php',
        'Social Digest',
        'Social Digest',
        'manage_options',
        'social-digest-settings',
        __NAMESPACE__ . '\\social_render_settings_page'
    );

    // Backwards-compatibility: Keep Settings -> Social Digest registered
    add_options_page(
        'Social Digest Settings',
        'Social Digest',
        'manage_options',
        'social-digest-settings',
        __NAMESPACE__ . '\\social_render_settings_page'
    );
});

// Top WordPress Admin Bar Quick-Access Shortcut
add_action('admin_bar_menu', function(\WP_Admin_Bar $wp_admin_bar) {
    if (!current_user_can('manage_options')) return;

    $wp_admin_bar->add_node([
        'id'    => 'social_digest_admin_bar',
        'title' => '<span class="ab-icon dashicons dashicons-share" style="top:2px;"></span><span class="ab-label">Social Digest</span>',
        'href'  => admin_url('edit.php?page=social-digest-settings&tab=workbench'),
    ]);

    $wp_admin_bar->add_node([
        'id'     => 'social_digest_bar_actions',
        'parent' => 'social_digest_admin_bar',
        'title'  => '⚡ Actions &amp; Preview',
        'href'   => admin_url('edit.php?page=social-digest-settings&tab=workbench'),
    ]);

    $wp_admin_bar->add_node([
        'id'     => 'social_digest_bar_settings',
        'parent' => 'social_digest_admin_bar',
        'title'  => '⚙️ Settings',
        'href'   => admin_url('edit.php?page=social-digest-settings&tab=settings'),
    ]);
}, 90);

add_action('admin_init', function() {
    register_setting('social_digest_group', 'social_digest_options', [
        'type'              => 'array',
        'sanitize_callback' => __NAMESPACE__ . '\\social_sanitize_settings',
        'default'           => [
            'network_mode'           => 'both',
            'avatar_source'          => 'auto',
            'bsky_handle'            => '',
            'masto_handle'           => '',
            'cross_dedup'            => 1,
            'preferred_platform'     => 'masto_if_longer',
            'schedule_freq'          => 'interval_days',
            'schedule_interval_days' => 1,
            'schedule_time'          => '17:00',
            'min_posts'              => 3,
            'max_posts'              => 20,
            'max_age_days'           => 0,
            'auto_thumb'             => 1,
            'thumb_selection_scope'  => 'exclude_first',
            'thumb_selection_mode'   => 'random',
            'convert_modern_media'   => 1,
            'cache_local_assets'     => 1,
            'generate_srcsets'       => 1,
            'enable_staging_queue'   => 0,
            'rss_only_mode'          => 0,
            'excerpt_fold_limit'     => 400,
            'fetch_order'            => 'newest',
            'display_order'          => 'reverse',
            'post_status'            => 'publish',
            'post_author'            => 1,
            'categories'             => [],
            'default_tags'           => 'Social Digest, Roundup',
            'extract_tags'           => 1,
            'max_tags_per_post'      => 2,
            'max_total_tags'         => 8,
            'min_tag_length'         => 3,
            'keep_threads'           => 1,
            'include_reposts'        => 0,
            'exclude_titles'         => 0,
            'exclude_self_syndicated'=> 1,
            'title_template'         => 'Social Digest {hashtags}',
            'title_tag_enclosure'    => 'parentheses',
            'title_tag_delimiter'    => 'oxford',
            'same_day_suffix_tpl'    => ' (Part {part})',
            'excluded_words'         => '#ad, sponsored',
            'header_text'            => '<p>Here is what we shared across social channels today:</p>',
            'footer_text'            => '<hr><p>Follow us directly on social media for real-time updates!</p>',
            'nosnippet_header'       => 1,
            'nosnippet_footer'       => 1,
            'wipe_data_on_uninstall' => 1
        ]
    ]);

    // Workbench Form Handlers
    if (!current_user_can('manage_options')) return;
    if (empty($_GET['page']) || $_GET['page'] !== 'social-digest-settings') return;

    if (isset($_POST['sd53_fetch']) && check_admin_referer('sd53_workbench_action', 'sd53_nonce')) {
        $result = social_fetch_workbench_candidates();
        add_settings_error('sd53', 'fetch', $result['message'], !empty($result['success']) ? 'updated' : 'error');
    }

    if (isset($_POST['sd53_save']) && check_admin_referer('sd53_workbench_action', 'sd53_nonce')) {
        social_save_workbench_state(social_sanitize_next_run($_POST));
        add_settings_error('sd53', 'save', 'Next-run editorial changes saved.', 'updated');
    }

    if (isset($_POST['sd53_reset']) && check_admin_referer('sd53_workbench_action', 'sd53_nonce')) {
        social_clear_workbench_state();
        add_settings_error('sd53', 'reset', 'Next-run workbench reset.', 'updated');
    }

    if (isset($_POST['sd53_publish']) && check_admin_referer('sd53_workbench_action', 'sd53_nonce')) {
        $state = social_sanitize_next_run($_POST);
        social_save_workbench_state($state);
        $result = social_publish_workbench_run($state, 'publish');
        if (!empty($result['success'])) {
            social_clear_workbench_state();
            $post_id = absint($result['post_id'] ?? 1);
            wp_safe_redirect(admin_url('edit.php?page=social-digest-settings&tab=workbench&sd53_published=' . $post_id));
            exit;
        } else {
            add_settings_error('sd53', 'publish', $result['message'] ?? 'Publishing failed.', 'error');
        }
    }

    if (isset($_POST['sd53_save_draft']) && check_admin_referer('sd53_workbench_action', 'sd53_nonce')) {
        $state = social_sanitize_next_run($_POST);
        social_save_workbench_state($state);
        $result = social_publish_workbench_run($state, 'draft');
        if (!empty($result['success'])) {
            social_clear_workbench_state();
            $post_id = absint($result['post_id'] ?? 1);
            wp_safe_redirect(admin_url('edit.php?page=social-digest-settings&tab=workbench&sd53_drafted=' . $post_id));
            exit;
        } else {
            add_settings_error('sd53', 'draft', $result['message'] ?? 'Draft creation failed.', 'error');
        }
    }

    if (isset($_POST['sd53_manual_run']) && check_admin_referer('sd53_workbench_action', 'sd53_nonce')) {
        try {
            $result = social_run_digest_import(false);
        } catch (\Throwable $e) {
            $result = ['success' => false, 'message' => 'Manual run error: ' . $e->getMessage()];
        }
        add_settings_error('sd53', 'manual', $result['message'] ?? 'Import failed.', !empty($result['success']) ? 'updated' : 'error');
    }

    if (isset($_POST['sd53_reset_cutoff']) && check_admin_referer('sd53_workbench_action', 'sd53_nonce')) {
        delete_option('bsky_last_digest_time');
        delete_option('masto_last_digest_time');
        add_settings_error('sd53', 'cutoff', 'Cutoff markers cleared for both networks.', 'updated');
    }

    if (isset($_POST['sd53_set_cutoff_now']) && check_admin_referer('sd53_workbench_action', 'sd53_nonce')) {
        $now = time();
        update_option('bsky_last_digest_time', $now);
        update_option('masto_last_digest_time', $now);
        $tz = wp_timezone();
        add_settings_error('sd53', 'cutoff', 'Cutoff markers set to current time (' . esc_html(wp_date('Y-m-d H:i:s T', $now, $tz)) . '). No posts older than this moment will be used.', 'updated');
    }

    if (isset($_POST['sd53_set_cutoff_custom']) && check_admin_referer('sd53_workbench_action', 'sd53_nonce')) {
        $custom_dt = sanitize_text_field($_POST['sd53_custom_cutoff_datetime'] ?? '');
        if (!empty($custom_dt)) {
            $tz = wp_timezone();
            $dt = date_create_immutable($custom_dt, $tz);
            if ($dt) {
                $custom_ts = $dt->getTimestamp();
                update_option('bsky_last_digest_time', $custom_ts);
                update_option('masto_last_digest_time', $custom_ts);
                add_settings_error('sd53', 'cutoff', 'Cutoff markers set to ' . esc_html(wp_date('Y-m-d H:i:s T', $custom_ts, $tz)) . '. Posts older than this will be ignored.', 'updated');
            } else {
                add_settings_error('sd53', 'cutoff', 'Invalid date/time format provided for custom cutoff.', 'error');
            }
        } else {
            add_settings_error('sd53', 'cutoff', 'Please select a date and time to set a custom cutoff.', 'error');
        }
    }
});

function social_sanitize_settings($input) {
    $output = [];
    $output['network_mode']       = in_array($input['network_mode'] ?? '', ['bsky', 'mastodon', 'both']) ? $input['network_mode'] : 'both';
    $output['avatar_source']      = in_array($input['avatar_source'] ?? '', ['auto', 'bsky', 'mastodon', 'none'], true) ? $input['avatar_source'] : 'auto';
    $output['bsky_handle']        = sanitize_text_field($input['bsky_handle'] ?? '');
    $output['masto_handle']       = sanitize_text_field($input['masto_handle'] ?? '');
    $output['cross_dedup']        = !empty($input['cross_dedup']) ? 1 : 0;
    $allowed_strategies           = ['masto_if_longer', 'longest', 'bsky', 'mastodon'];
    $output['preferred_platform'] = in_array($input['preferred_platform'] ?? '', $allowed_strategies) ? $input['preferred_platform'] : 'masto_if_longer';

    $allowed_freqs = ['disabled', 'hourly', 'six_hours', 'twelve_hours', 'interval_days'];
    $output['schedule_freq'] = in_array($input['schedule_freq'] ?? '', $allowed_freqs) ? $input['schedule_freq'] : 'interval_days';

    $output['schedule_interval_days'] = max(1, absint($input['schedule_interval_days'] ?? 1));
    $raw_time = sanitize_text_field($input['schedule_time'] ?? '17:00');
    $output['schedule_time'] = preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $raw_time) ? $raw_time : '17:00';

    $output['min_posts']             = max(1, absint($input['min_posts'] ?? 1));
    $output['max_posts']             = max($output['min_posts'], absint($input['max_posts'] ?? 20));
    $output['max_age_days']          = absint($input['max_age_days'] ?? 0);
    $output['auto_thumb']            = !empty($input['auto_thumb']) ? 1 : 0;
    $output['thumb_selection_scope'] = in_array($input['thumb_selection_scope'] ?? '', ['exclude_first', 'all_posts']) ? $input['thumb_selection_scope'] : 'exclude_first';
    
    $allowed_modes = ['random', '1', '2', '3', '4', '5', '6', '7', '8', '9', '10'];
    $output['thumb_selection_mode'] = in_array($input['thumb_selection_mode'] ?? '', $allowed_modes, true) ? $input['thumb_selection_mode'] : 'random';

    $output['convert_modern_media'] = !empty($input['convert_modern_media']) ? 1 : 0;
    $output['cache_local_assets']   = !empty($input['cache_local_assets']) ? 1 : 0;
    $output['generate_srcsets']     = !empty($input['generate_srcsets']) ? 1 : 0;

    $output['enable_staging_queue'] = !empty($input['enable_staging_queue']) ? 1 : 0;
    $output['rss_only_mode']        = !empty($input['rss_only_mode']) ? 1 : 0;
    $output['excerpt_fold_limit']   = max(0, absint($input['excerpt_fold_limit'] ?? 400));

    $output['fetch_order']     = in_array($input['fetch_order'] ?? '', ['newest', 'oldest']) ? $input['fetch_order'] : 'newest';
    $display_order_val         = $input['display_order'] ?? 'reverse';
    $output['display_order']   = in_array($display_order_val, ['chronological', 'reverse', 'random']) ? $display_order_val : 'reverse';
    $output['post_status']     = in_array($input['post_status'] ?? '', ['publish', 'draft', 'pending']) ? $input['post_status'] : 'publish';
    $output['post_author']     = absint($input['post_author'] ?? 1);
    
    $output['categories'] = [];
    if (!empty($input['categories']) && is_array($input['categories'])) {
        $output['categories'] = array_map('absint', $input['categories']);
    }

    $output['default_tags']        = sanitize_text_field($input['default_tags'] ?? '');
    $output['extract_tags']        = !empty($input['extract_tags']) ? 1 : 0;
    $output['max_tags_per_post']   = max(1, absint($input['max_tags_per_post'] ?? 2));
    $output['max_total_tags']      = max(1, absint($input['max_total_tags'] ?? 8));
    $output['min_tag_length']      = max(1, absint($input['min_tag_length'] ?? 3));

    $output['keep_threads']        = !empty($input['keep_threads']) ? 1 : 0;
    $output['include_reposts']     = !empty($input['include_reposts']) ? 1 : 0;
    $output['exclude_titles']      = !empty($input['exclude_titles']) ? 1 : 0;
    $output['exclude_self_syndicated'] = !empty($input['exclude_self_syndicated']) ? 1 : 0;
    $output['title_template']      = sanitize_text_field($input['title_template'] ?? 'Social Digest {hashtags}');
    
    $allowed_enclosures = ['parentheses', 'brackets', 'none'];
    $output['title_tag_enclosure'] = in_array($input['title_tag_enclosure'] ?? '', $allowed_enclosures, true) ? $input['title_tag_enclosure'] : 'parentheses';

    $allowed_delimiters = ['oxford', 'commas', 'ampersand', 'pipe', 'slash'];
    $output['title_tag_delimiter'] = in_array($input['title_tag_delimiter'] ?? '', $allowed_delimiters, true) ? $input['title_tag_delimiter'] : 'oxford';

    $output['same_day_suffix_tpl'] = sanitize_text_field($input['same_day_suffix_tpl'] ?? ' (Part {part})');
    $output['excluded_words']      = sanitize_textarea_field($input['excluded_words'] ?? '');

    $raw_unified = $input['unified_content'] ?? '';
    $cleaned_raw = preg_replace('/<p[^>]*class=["\'][^"\']*social-digest-split-marker[^"\']*["\'][^>]*>.*?<!--digest_split-->.*?<\/p>/is', '<!--digest_split-->', $raw_unified);
    
    if (strpos($cleaned_raw, '<!--digest_split-->') !== false) {
        $parts = explode('<!--digest_split-->', $cleaned_raw, 2);
        $output['header_text'] = wp_kses_post(trim($parts[0]));
        $output['footer_text'] = wp_kses_post(trim($parts[1]));
    } else {
        $output['header_text'] = wp_kses_post(trim($cleaned_raw));
        $output['footer_text'] = '';
    }

    $output['nosnippet_header']       = !empty($input['nosnippet_header']) ? 1 : 0;
    $output['nosnippet_footer']       = !empty($input['nosnippet_footer']) ? 1 : 0;
    $output['wipe_data_on_uninstall'] = !empty($input['wipe_data_on_uninstall']) ? 1 : 0;

    social_reschedule_cron($output);

    return $output;
}

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

    $now_local = new \DateTimeImmutable('now', $site_tz);
    $target_run = $now_local->setTime((int)$hours, (int)$minutes, 0);

    if ($target_run->getTimestamp() <= time()) {
        $target_run = $target_run->modify('+1 day');
    }

    wp_schedule_event($target_run->getTimestamp(), 'social_custom_days', 'social_digest_cron');
}

// ==========================================
// 4. WORKBENCH LOGIC & DATA HELPERS
// ==========================================

function social_workbench_state() {
    $state = get_option('social_digest_next_run', []);
    return is_array($state) ? $state : [];
}

function social_save_workbench_state($state) {
    update_option('social_digest_next_run', $state, false);
}

function social_clear_workbench_state() {
    delete_option('social_digest_next_run');
}

function social_make_candidate_key($html, $index) {
    $plain = trim(wp_strip_all_tags($html));
    return 'item_' . substr(md5($plain . '|' . $index), 0, 16);
}

function social_fetch_workbench_candidates() {
    global $wpdb;
    $opts = get_option('social_digest_options', []);
    $mode = $opts['network_mode'] ?? 'both';
    $cross_dedup = !empty($opts['cross_dedup']);
    $preferred = $opts['preferred_platform'] ?? 'masto_if_longer';
    $keep_threads = !empty($opts['keep_threads']);
    $include_reposts = !empty($opts['include_reposts']);
    $exclude_titles = !empty($opts['exclude_titles']);
    $exclude_self_syndicated = !isset($opts['exclude_self_syndicated']) || !empty($opts['exclude_self_syndicated']);
    $excluded_words = array_filter(array_map('trim', explode(',', $opts['excluded_words'] ?? '')));
    $fetch_order = $opts['fetch_order'] ?? 'newest';
    $max_posts = max(1, (int)($opts['max_posts'] ?? 20));
    $min_tag_length = max(1, (int)($opts['min_tag_length'] ?? 3));
    $max_tags_per_post = max(1, (int)($opts['max_tags_per_post'] ?? 2));
    $max_total_tags = max(1, (int)($opts['max_total_tags'] ?? 8));
    $bsky_last = (int)get_option('bsky_last_digest_time', 0);
    $masto_last = (int)get_option('masto_last_digest_time', 0);

    // Apply max_age_days boundary to prevent massive backlogs on fresh or reset cutoffs
    $max_age_days = absint($opts['max_age_days'] ?? 0);
    $age_boundary = ($max_age_days > 0) ? (time() - ($max_age_days * DAY_IN_SECONDS)) : 0;
    if ($age_boundary > 0) {
        $bsky_last  = max($bsky_last, $age_boundary);
        $masto_last = max($masto_last, $age_boundary);
    }

    $raw = [];
    $new_bsky = $bsky_last;
    $new_masto = $masto_last;
    if ($mode === 'bsky' || $mode === 'both') {
        $r = social_fetch_bluesky($opts['bsky_handle'] ?? '', $bsky_last, $keep_threads, $include_reposts);
        $raw = array_merge($raw, (array)($r['posts'] ?? []));
        $new_bsky = (int)($r['newest_timestamp'] ?? $bsky_last);
    }
    if ($mode === 'mastodon' || $mode === 'both') {
        $r = social_fetch_mastodon($opts['masto_handle'] ?? '', $masto_last, $keep_threads, $include_reposts);
        $raw = array_merge($raw, (array)($r['posts'] ?? []));
        $new_masto = (int)($r['newest_timestamp'] ?? $masto_last);
    }

    // Notice: Do NOT fall back to historical posts when the network query yields no new posts.
    // If the cutoff was manually set to "Right Now" or a recent publish, empty queue is the correct result.
    if (!$raw) {
        $empty_state = [
            'created' => time(),
            'preview' => ['title' => '', 'tags' => [], 'featured_image' => ''],
            'candidates' => [],
            'framing_override_enabled' => false,
            'header_override' => '',
            'footer_override' => '',
            'cutoffs' => [
                'bsky'  => $new_bsky,
                'masto' => $new_masto,
            ],
        ];
        social_save_workbench_state($empty_state);
        return [
            'success' => true,
            'message' => 'No new posts found since the active cutoff timestamp. Next-run queue is clear.',
            'state'   => $empty_state
        ];
    }

    $normalized_site_titles = [];
    if ($exclude_titles) {
        $raw_posts = get_posts([
            'numberposts' => 100,
            'post_status' => 'publish',
            'post_type'   => 'post',
            'fields'      => 'ids',
        ]);
        foreach ($raw_posts as $pid) {
            $clean = strtolower(trim(preg_replace('/[^\p{L}\p{N}\s]/u', '', html_entity_decode(get_the_title($pid), ENT_QUOTES, 'UTF-8'))));
            if ($clean !== '') $normalized_site_titles[$clean] = true;
        }
    }

    $eligible = [];
    foreach ($raw as $item) {
        $text = (string)($item['text'] ?? '');
        $skip = false;
        if ($age_boundary > 0 && (int)($item['timestamp'] ?? 0) < $age_boundary) {
            continue;
        }
        if ($exclude_self_syndicated && social_post_links_to_site($item)) {
            continue;
        }
        foreach ($excluded_words as $word) {
            if ($word !== '' && stripos($text, $word) !== false) { $skip = true; break; }
        }
        if ($skip) continue;
        if ($exclude_titles && $normalized_site_titles) {
            $without_urls = preg_replace('/\bhttps?:\/\/\S+/i', '', $text);
            $norm = strtolower(trim(preg_replace('/[^\p{L}\p{N}\s]/u', '', html_entity_decode($without_urls, ENT_QUOTES, 'UTF-8'))));
            if (isset($normalized_site_titles[$norm])) continue;
            foreach ($normalized_site_titles as $title => $_) {
                if (mb_strlen($title) >= 12 && strpos($norm, $title) !== false) { $skip = true; break; }
            }
            if ($skip) continue;
        }
        $item['timestamp'] = (int)($item['timestamp'] ?? 0);
        $eligible[] = $item;
    }

    if ($mode === 'both' && $cross_dedup) {
        $bsky = []; $masto = [];
        foreach ($eligible as $p) {
            if (($p['network'] ?? '') === 'bsky') $bsky[] = $p; else $masto[] = $p;
        }
        $merged = []; $matched = [];
        foreach ($bsky as $bp) {
            $winner = $bp; $secondary = null; $mi = null;
            foreach ($masto as $i => $mp) {
                if (isset($matched[$i])) continue;
                if (social_check_posts_match($bp, $mp)) { $secondary = $mp; $mi = $i; break; }
            }
            if ($secondary !== null) {
                $matched[$mi] = true;
                $prefer_masto = false;
                if ($preferred === 'masto_if_longer' || $preferred === 'longest') {
                    $prefer_masto = social_get_clean_text_length($secondary['text'] ?? '') > social_get_clean_text_length($bp['text'] ?? '');
                } elseif ($preferred === 'mastodon') {
                    $prefer_masto = true;
                }
                if ($prefer_masto) { $winner = $secondary; $secondary = $bp; }
                $winner_tags = array_merge((array)($winner['extra_tags'] ?? []), preg_match_all('/#(\w+)/u', $winner['text'] ?? '', $wm) ? $wm[1] : []);
                $secondary_tags = array_merge((array)($secondary['extra_tags'] ?? []), preg_match_all('/#(\w+)/u', $secondary['text'] ?? '', $sm) ? $sm[1] : []);
                $winner['extra_tags'] = array_values(array_unique(array_merge($winner_tags, array_diff($secondary_tags, $winner_tags))));
                if (empty($winner['thumb_image']) && !empty($secondary['thumb_image'])) $winner['thumb_image'] = $secondary['thumb_image'];

                // Merge platform links for dual-network footer actions
                $winner_links = (array)($winner['platform_links'] ?? []);
                $secondary_links = (array)($secondary['platform_links'] ?? []);
                $winner['platform_links'] = array_merge($winner_links, $secondary_links);

                // Re-render native card HTML with merged dual-platform links and metadata
                $winner['html'] = social_render_native_card($winner, $opts);
            }
            $merged[] = $winner;
        }
        foreach ($masto as $i => $mp) if (!isset($matched[$i])) $merged[] = $mp;
        $eligible = $merged;
    }

    usort($eligible, function($a, $b) { return (int)$b['timestamp'] <=> (int)$a['timestamp']; });
    if (count($eligible) > $max_posts) $eligible = array_slice($eligible, 0, $max_posts);
    if (!$eligible) return ['success' => false, 'message' => 'No eligible posts are available after filtering and deduplication.'];

    // Preserve chronological list (newest first) for thumbnail selection so 'exclude_first' always excludes the most recent post in time
    $chronological_posts = $eligible;

    // Apply display ordering preference (reverse chronological, chronological, or random)
    $display_order = $opts['display_order'] ?? 'reverse';
    if ($display_order === 'chronological' || $display_order === 'oldest') {
        usort($eligible, function($a, $b) { return (int)$a['timestamp'] <=> (int)$b['timestamp']; });
    } elseif ($display_order === 'random') {
        shuffle($eligible);
    } else {
        usort($eligible, function($a, $b) { return (int)$b['timestamp'] <=> (int)$a['timestamp']; });
    }

    $candidates = [];
    foreach ($eligible as $i => $item) {
        $html = wp_kses_post($item['html'] ?? '');
        if ($html === '') continue;
        $key = social_make_candidate_key($html, $i);
        $url = '';
        if (!empty($item['urls'][0])) $url = esc_url_raw($item['urls'][0]);
        $candidates[] = [
            'key' => $key,
            'network' => ($item['network'] ?? '') === 'mastodon' ? 'Mastodon' : 'Bluesky',
            'timestamp' => (int)$item['timestamp'],
            'html' => $html,
            'text' => wp_trim_words(wp_strip_all_tags($item['text'] ?? ''), 55, '…'),
            'url' => $url,
            'thumb_image' => esc_url_raw($item['thumb_image'] ?? ''),
            'extra_tags' => (array)($item['extra_tags'] ?? []),
            'excluded' => false,
            'pinned' => false,
            'commentary' => '',
        ];
    }

    $featured = '';
    $featured_post_tags = [];
    if (!empty($opts['auto_thumb'])) {
        $thumbs = [];
        $ordered = $chronological_posts;
        if (($opts['thumb_selection_scope'] ?? 'exclude_first') === 'exclude_first') $ordered = array_slice($ordered, 1);
        foreach ($ordered as $p) if (!empty($p['thumb_image'])) $thumbs[] = $p;
        if (!$thumbs) foreach ($chronological_posts as $p) if (!empty($p['thumb_image'])) $thumbs[] = $p;
        if ($thumbs) {
            $mode_thumb = $opts['thumb_selection_mode'] ?? 'random';
            if ($mode_thumb === 'random') $sel = $thumbs[array_rand($thumbs)];
            else $sel = $thumbs[min(count($thumbs)-1, max(0, (int)$mode_thumb-1))];
            
            $featured = esc_url_raw($sel['thumb_image']);
            $featured_post_tags = array_merge((array)($sel['extra_tags'] ?? []), preg_match_all('/#(\w+)/u', $sel['text'] ?? '', $tm) ? $tm[1] : []);
            $featured_post_tags = array_values(array_unique(array_filter($featured_post_tags, fn($t) => mb_strlen($t) >= $min_tag_length)));
        }
    }

    $raw_tags = [];
    $post_tags_list = [];
    
    // Add the featured post tags first so they are prioritized for the title
    if (!empty($featured_post_tags)) {
        $post_tags_list[] = $featured_post_tags;
    }

    foreach ($eligible as $item) {
        $tags = array_merge((array)($item['extra_tags'] ?? []), preg_match_all('/#(\w+)/u', $item['text'] ?? '', $tm) ? $tm[1] : []);
        $valid_post_tags = array_slice(array_values(array_unique(array_filter($tags, fn($t) => mb_strlen($t) >= $min_tag_length))), 0, $max_tags_per_post);
        $raw_tags = array_merge($raw_tags, $valid_post_tags);
        if (!empty($valid_post_tags)) {
            $post_tags_list[] = $valid_post_tags;
        }
    }
    
    $title_hashtags = social_rank_and_format_title_tags($post_tags_list, $opts['default_tags'] ?? '', $opts['title_tag_enclosure'] ?? 'parentheses', $opts['title_tag_delimiter'] ?? 'oxford');    
    $tag_map = [];
    foreach (array_filter(array_map('trim', explode(',', $opts['default_tags'] ?? ''))) as $tag) $tag_map[mb_strtolower($tag)] = $tag;
    foreach ($raw_tags as $tag) $tag_map[mb_strtolower($tag)] = $tag;
    $tags = array_slice(array_values($tag_map), 0, $max_total_tags);
    $title_tpl = $opts['title_template'] ?? 'Social Digest {hashtags}';
    $title = trim(preg_replace('/\s*[:\-–]\s*$/u', '', preg_replace('/\s+/', ' ', str_replace(['{hashtags}','{date}','{count}'], [$title_hashtags, wp_date(get_option('date_format'), time(), wp_timezone()), count($candidates)], $title_tpl))));


    $next = [
        'created' => time(),
        'preview' => ['title' => $title, 'tags' => $tags, 'featured_image' => $featured],
        'candidates' => $candidates,
        'framing_override_enabled' => false,
        'header_override' => '',
        'footer_override' => '',
        'cutoffs' => [
            'bsky'  => $new_bsky,
            'masto' => $new_masto,
        ],
    ];
    social_save_workbench_state($next);
    return ['success' => true, 'message' => 'Fetched the next-run candidates.', 'state' => $next];
}

function social_sanitize_next_run($input) {
    $old = social_workbench_state();
    $out = $old;
    $out['candidates'] = [];
    $old_candidates = [];
    foreach ((array)($old['candidates'] ?? []) as $c) {
        if (!empty($c['key'])) $old_candidates[$c['key']] = $c;
    }
    
    $pinned_key   = sanitize_key($input['pinned_lead_post'] ?? '');
    $featured_sel = sanitize_key($input['selected_featured_post'] ?? '');

    if ($featured_sel === 'none') {
        $out['featured_image_override_key'] = 'none';
        $out['preview']['featured_image']   = '';
    } elseif ($featured_sel !== '' && $featured_sel !== 'auto' && isset($old_candidates[$featured_sel]) && !empty($old_candidates[$featured_sel]['thumb_image'])) {
        $out['featured_image_override_key'] = $featured_sel;
        $out['preview']['featured_image']   = esc_url_raw($old_candidates[$featured_sel]['thumb_image']);
    } else {
        $out['featured_image_override_key'] = 'auto';
    }

    foreach ((array)($input['candidate'] ?? []) as $key => $raw) {
        $key = sanitize_key($key);
        if (!$key || !isset($old_candidates[$key])) continue;
        $base = $old_candidates[$key];
        $base['excluded']   = !empty($raw['excluded']);
        $base['pinned']     = ($key === $pinned_key && empty($base['excluded']));
        $base['commentary'] = sanitize_textarea_field($raw['commentary'] ?? '');
        $out['candidates'][] = $base;
    }

    $out['framing_override_enabled'] = !empty($input['framing_override_enabled']);

    // Parse the unified TinyMCE visual editor split by <!--digest_split-->
    $raw_content = $input['workbench_unified_content'] ?? '';
    $cleaned_raw = preg_replace('/<p[^>]*class=["\'][^"\']*social-digest-split-marker[^"\']*["\'][^>]*>.*?<!--digest_split-->.*?<\/p>/is', '<!--digest_split-->', $raw_content);

    if (strpos($cleaned_raw, '<!--digest_split-->') !== false) {
        $parts = explode('<!--digest_split-->', $cleaned_raw, 2);
        $out['header_override'] = wp_kses_post(trim($parts[0]));
        $out['footer_override'] = wp_kses_post(trim($parts[1]));
    } else {
        $out['header_override'] = wp_kses_post(trim($cleaned_raw));
        $out['footer_override'] = '';
    }

    return $out;
}

function social_build_workbench_content($state) {
    $opts = get_option('social_digest_options', []);
    $selected = [];
    $pinned_block = null;

    foreach ((array)($state['candidates'] ?? []) as $c) {
        if (!empty($c['excluded'])) continue;
        $block = wp_kses_post((string)($c['html'] ?? ''));
        if ($block === '') continue;
        $commentary = trim((string)($c['commentary'] ?? ''));
        if ($commentary !== '') {
            $block = '<div class="social-digest-editorial-commentary" style="margin:0 0 10px 0;padding:10px 12px;border-left:4px solid #f59e0b;background:#fffbeb;color:#92400e;">' . esc_html($commentary) . '</div>' . $block;
        }

        if (!empty($c['pinned'])) {
            $pinned_block = '<div class="social-digest-pinned-story" style="margin-bottom:20px;">' . $block . '</div>';
        } else {
            $selected[] = $block;
        }
    }

    if ($pinned_block) {
        array_unshift($selected, $pinned_block);
    }

    if (!$selected) return ['success' => false, 'message' => 'No articles are selected for the next run.'];

    $use_override = !empty($state['framing_override_enabled']);
    $header = $use_override ? trim($state['header_override'] ?? '') : trim($opts['header_text'] ?? '');
    $footer = $use_override ? trim($state['footer_override'] ?? '') : trim($opts['footer_text'] ?? '');

    if (!empty($opts['nosnippet_header']) && $header !== '') $header = '<section data-nosnippet class="social-digest-header">' . $header . '</section>';
    if (!empty($opts['nosnippet_footer']) && $footer !== '') $footer = '<section data-nosnippet class="social-digest-footer">' . $footer . '</section>';
    $content = ($header !== '' ? $header . "\n\n" : '') . implode("\n\n", $selected) . ($footer !== '' ? "\n\n" . $footer : '');
    return ['success' => true, 'content' => $content, 'count' => count($selected)];
}

function social_publish_workbench_run($state, $force_status = null) {
    try {
        if (function_exists('wp_raise_memory_limit')) {
            wp_raise_memory_limit('admin');
        }

        $opts = get_option('social_digest_options', []);
        $min = (int)($opts['min_posts'] ?? 1);
        $built = social_build_workbench_content($state);
        if (empty($built['success'])) return $built;
        if ($built['count'] < $min) {
            return ['success' => false, 'message' => "Threshold not met: {$built['count']} selected article(s); minimum required is {$min}."];
        }

        global $wpdb;
        $title = sanitize_text_field($state['preview']['title'] ?? 'Social Digest');
        $base_title = trim(preg_replace('/\s*[:\-–]\s*$/u', '', preg_replace('/\s+/', ' ', $title)));
        $today = wp_date('Y-m-d', time(), wp_timezone());
        $count_today = (int)$wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(ID) FROM {$wpdb->posts} WHERE post_title LIKE %s AND post_date LIKE %s AND post_status IN ('publish','draft','pending','future')",
            $wpdb->esc_like($base_title) . '%', $today . '%'
        ));
        if ($count_today > 0) {
            $suffix = $opts['same_day_suffix_tpl'] ?? ' (Part {part})';
            $title = $base_title . str_replace('{part}', (string)($count_today + 1), $suffix);
        }

        $author_id = absint($opts['post_author'] ?? get_current_user_id());
        if ($author_id === 0) {
            $author_id = get_current_user_id();
        }

        $status_to_use = ($force_status !== null) ? $force_status : ($opts['post_status'] ?? 'publish');

        $post_args = [
            'post_title'   => $title,
            'post_content' => $built['content'],
            'post_status'  => $status_to_use,
            'post_author'  => $author_id,
            'post_type'    => 'post',
            'tags_input'   => array_values(array_map('sanitize_text_field', (array)($state['preview']['tags'] ?? []))),
        ];
        if (!empty($opts['categories']) && is_array($opts['categories'])) {
            $post_args['post_category'] = array_map('absint', $opts['categories']);
        }

        $post_id = wp_insert_post($post_args, true);
        if (is_wp_error($post_id)) {
            return ['success' => false, 'message' => 'Post creation failed: ' . $post_id->get_error_message()];
        }
        if (!empty($opts['rss_only_mode'])) {
            update_post_meta($post_id, '_social_digest_rss_only', 1);
        }
        if (!empty($state['preview']['featured_image'])) {
            $attachment_id = social_sideload_image_by_mime($state['preview']['featured_image'], $post_id, $title);
            if ($attachment_id) {
                set_post_thumbnail($post_id, $attachment_id);
            }
        }

        // Advance cutoff timestamps on successful publish or draft creation
        $new_bsky = (int)($state['cutoffs']['bsky'] ?? 0);
        $new_masto = (int)($state['cutoffs']['masto'] ?? 0);

        if ($new_bsky <= 0 || $new_masto <= 0) {
            foreach ((array)($state['candidates'] ?? []) as $cand) {
                $cand_ts = (int)($cand['timestamp'] ?? 0);
                $net = strtolower($cand['network'] ?? '');
                if ($net === 'bluesky' && $cand_ts > $new_bsky) {
                    $new_bsky = $cand_ts;
                } elseif ($net === 'mastodon' && $cand_ts > $new_masto) {
                    $new_masto = $cand_ts;
                }
            }
        }

        if ($new_bsky > 0) {
            update_option('bsky_last_digest_time', $new_bsky);
        }
        if ($new_masto > 0) {
            update_option('masto_last_digest_time', $new_masto);
        }

        $status_label = ($status_to_use === 'draft') ? 'Draft created' : 'Digest published';
        social_log_run(true, "Workbench {$status_label} (ID: {$post_id}) with {$built['count']} article(s).", $post_id);
        return ['success' => true, 'post_id' => $post_id, 'status' => $status_to_use, 'message' => "{$status_label} (ID: {$post_id}) with {$built['count']} selected article(s)."];
    } catch (\Throwable $e) {
        return ['success' => false, 'message' => 'Fatal runtime error publishing digest: ' . $e->getMessage()];
    }
}

// ==========================================
// 5. SETTINGS & WORKBENCH VIEW RENDERER
// ==========================================

function social_render_settings_page() {
    if (!current_user_can('manage_options')) return;
    $active_tab = isset($_GET['tab']) ? sanitize_key($_GET['tab']) : 'workbench';
    $opts       = get_option('social_digest_options', []);
    $site_tz    = wp_timezone();
    $bsky_last  = get_option('bsky_last_digest_time', 0);
    $masto_last = get_option('masto_last_digest_time', 0);
    $next_run   = wp_next_scheduled('social_digest_cron');
    $all_cats   = get_categories(['hide_empty' => 0]);
    $selected_cats = (array)($opts['categories'] ?? []);

    $saved_header = $opts['header_text'] ?? '';
    $saved_footer = $opts['footer_text'] ?? '';
    $split_marker_html = '<p class="social-digest-split-marker" style="text-align:center; background:#eee; padding:6px; border:1px dashed #999; color:#555; font-weight:bold; user-select:none;"><!--digest_split--> (Header / Footer Split)</p>';
    $unified_editor_value = trim($saved_header) . "\n\n" . $split_marker_html . "\n\n" . trim($saved_footer);
    ?>
    <style>
        .postbox {
            border-radius: 6px;
            overflow: hidden;
            border: 1px solid #ccd0d4;
            box-shadow: 0 1px 3px rgba(0,0,0,0.04);
            margin-bottom: 20px;
            background: #fff;
        }
        .postbox .postbox-header {
            padding: 8px 14px;
            background: #f6f7f7;
            border-bottom: 1px solid #c3c4c7;
            display: flex;
            align-items: center;
            justify-content: space-between;
            user-select: none;
        }
        .postbox .hndle {
            margin: 0;
            font-size: 13px;
            font-weight: 700;
            color: #1d2327;
            cursor: grab;
            flex-grow: 1;
        }
        .postbox .hndle:active { cursor: grabbing; }
        .postbox .inside { padding: 16px 20px; margin: 0; }
        .social-sortable-placeholder {
            border: 2px dashed #2271b1 !important;
            background: #f0f6fc !important;
            min-height: 80px;
            margin-bottom: 20px;
            border-radius: 6px;
        }
        .sd53-grid {
            display: grid;
            grid-template-columns: minmax(360px, 5fr) minmax(420px, 7fr);
            gap: 20px;
            align-items: start;
        }
        @media (max-width: 1024px) {
            .sd53-grid { grid-template-columns: 1fr; }
        }
        .sd53-item { border: 1px solid #dcdcde; border-radius: 6px; margin-bottom: 12px; background: #fff; }
        .sd53-item.excluded { opacity: .55; background: #f6f6f6; }
        .sd53-item.is-pinned { border: 2px solid #f59e0b; background: #fffdf5; }
        .sd53-item.is-featured-thumb { border: 2px solid #10b981; background: #f0fdf4; }
        .sd53-item.is-pinned.is-featured-thumb { border: 2px solid #f59e0b; outline: 2px solid #10b981; }
        .sd53-item-head {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 10px;
            padding: 8px 12px;
            background: #f0f6fc;
            border-bottom: 1px solid #dcdcde;
            font-size: 11px;
        }
        .sd53-item-body { padding: 12px; }
        .sd53-item-body blockquote { margin: 8px 0 !important; }
        .sd53-comment { width: 100%; min-height: 55px; box-sizing: border-box; }
        .sd53-preview { background: #fff; border: 1px solid #dcdcde; border-radius: 6px; overflow: hidden; }
        .sd53-preview-head { background: #2e7d32; color: #fff; padding: 9px 14px; font-weight: 700; font-size: 12px; }
        .sd53-preview-body { padding: 24px; max-width: 850px; margin: 0 auto; }
        .sd53-sticky { position: sticky; top: 32px; }
    </style>

    <div class="wrap">
        <h1>Social Digest</h1>
        <nav class="nav-tab-wrapper wp-clearfix" style="margin-top: 15px; margin-bottom: 20px;">
            <a href="<?php echo esc_url(admin_url('edit.php?page=social-digest-settings&tab=workbench')); ?>" class="nav-tab <?php echo $active_tab === 'workbench' ? 'nav-tab-active' : ''; ?>">
                <span class="dashicons dashicons-performance" style="vertical-align: -3px; font-size: 17px;"></span> Actions &amp; Preview
            </a>
            <a href="<?php echo esc_url(admin_url('edit.php?page=social-digest-settings&tab=settings')); ?>" class="nav-tab <?php echo $active_tab === 'settings' ? 'nav-tab-active' : ''; ?>">
                <span class="dashicons dashicons-admin-generic" style="vertical-align: -3px; font-size: 17px;"></span> Settings
            </a>
        </nav>
        <?php 
        if (!empty($_GET['sd53_published'])) {
            $pub_id = absint($_GET['sd53_published']);
            $edit_link = ($pub_id > 1 && function_exists('get_edit_post_link')) ? ' <a href="' . esc_url(get_edit_post_link($pub_id)) . '" class="button button-small" style="margin-left:10px;">Edit Post ↗</a>' : '';
            add_settings_error('sd53', 'publish', 'Digest published successfully!' . $edit_link, 'updated');
        }
        if (!empty($_GET['sd53_drafted'])) {
            $draft_id = absint($_GET['sd53_drafted']);
            $edit_link = ($draft_id > 1 && function_exists('get_edit_post_link')) ? ' <a href="' . esc_url(get_edit_post_link($draft_id)) . '" class="button button-small" style="margin-left:10px;">Edit Draft in WordPress ↗</a>' : '';
            add_settings_error('sd53', 'draft', 'Draft post created successfully!' . $edit_link, 'updated');
        }
        ?>
        <?php settings_errors('sd53'); ?>

        <?php if ($active_tab === 'settings'): ?>
            <div style="background:#fff; border:1px solid #c3c4c7; padding:8px 12px; border-radius:4px; margin-bottom:15px; font-size:12px; color:#50575e; display:flex; align-items:center; gap:6px;">
                <span class="dashicons dashicons-move" style="color:#2271b1;"></span>
                <span>Drag widget headers or click toggle arrows to expand, collapse, and reorder.</span>
            </div>

            <form method="post" action="options.php">
                <?php settings_fields('social_digest_group'); ?>
                <div id="poststuff">
                    <div id="post-body" class="metabox-holder columns-1">
                        <div id="postbox-container-1" class="postbox-container">
                            <div class="meta-box-sortables ui-sortable" id="social_settings_sortable">

                                <!-- SOURCES & CROSS-PLATFORM SETTINGS -->
                                <div class="postbox" id="social_box_sources" style="border-left: 5px solid #2271b1;">
                                    <div class="postbox-header">
                                        <h2 class="hndle">
                                            <span class="dashicons dashicons-share" style="color:#2271b1; margin-right:4px;"></span>
                                            Sources &amp; Cross-Platform Settings
                                        </h2>
                                        <button type="button" class="handlediv" aria-expanded="true"><span class="toggle-indicator" aria-hidden="true"></span></button>
                                    </div>
                                    <div class="inside">
                                        <table class="form-table">
                                            <tr>
                                                <th><label for="social_network_mode">Active Platforms</label></th>
                                                <td>
                                                    <select name="social_digest_options[network_mode]" id="social_network_mode" onchange="socialToggleModeFields(this.value)">
                                                        <option value="bsky" <?php selected($opts['network_mode'] ?? 'both', 'bsky'); ?>>Bluesky Only</option>
                                                        <option value="mastodon" <?php selected($opts['network_mode'] ?? 'both', 'mastodon'); ?>>Mastodon Only</option>
                                                        <option value="both" <?php selected($opts['network_mode'] ?? 'both', 'both'); ?>>Combined (Bluesky + Mastodon)</option>
                                                    </select>
                                                </td>
                                            </tr>
                                            <tr id="row_bsky_handle">
                                                <th><label for="social_bsky_handle">Bluesky Handle</label></th>
                                                <td><input name="social_digest_options[bsky_handle]" type="text" id="social_bsky_handle" value="<?php echo esc_attr($opts['bsky_handle'] ?? ''); ?>" class="regular-text" placeholder="username.bsky.social" /></td>
                                            </tr>
                                            <tr id="row_masto_handle">
                                                <th><label for="social_masto_handle">Mastodon Handle</label></th>
                                                <td>
                                                    <input name="social_digest_options[masto_handle]" type="text" id="social_masto_handle" value="<?php echo esc_attr($opts['masto_handle'] ?? ''); ?>" class="regular-text" placeholder="@user@instance.social or profile URL" />
                                                    <p class="description">Accepts user@instance.social or profile URL.</p>
                                                </td>
                                            </tr>
                                            <tr class="social-combined-field">
                                                <th>Avatar Source Preference</th>
                                                <td>
                                                    <select name="social_digest_options[avatar_source]" id="social_avatar_source">
                                                        <option value="auto" <?php selected($opts['avatar_source'] ?? 'auto', 'auto'); ?>>Automatic (Source / Post Winner)</option>
                                                        <option value="bsky" <?php selected($opts['avatar_source'] ?? '', 'bsky'); ?>>Prefer Bluesky Profile Avatar</option>
                                                        <option value="mastodon" <?php selected($opts['avatar_source'] ?? '', 'mastodon'); ?>>Prefer Mastodon Profile Avatar</option>
                                                        <option value="none" <?php selected($opts['avatar_source'] ?? '', 'none'); ?>>None (Hide Profile Avatars)</option>
                                                    </select>
                                                    <p class="description">Select which platform profile avatar to display in the native social post header.</p>
                                                </td>
                                            </tr>
                                            <tr class="social-combined-field">
                                                <th>Cross-Platform Deduplication</th>
                                                <td>
                                                    <label><input type="checkbox" name="social_digest_options[cross_dedup]" value="1" <?php checked($opts['cross_dedup'] ?? 1, 1); ?> /> <strong>Deduplicate matching cross-posts</strong></label>
                                                </td>
                                            </tr>
                                            <tr class="social-combined-field">
                                                <th>Duplicate Resolution Strategy</th>
                                                <td>
                                                    <select name="social_digest_options[preferred_platform]" id="social_preferred_platform">
                                                        <option value="masto_if_longer" <?php selected($opts['preferred_platform'] ?? 'masto_if_longer', 'masto_if_longer'); ?>>Prefer Mastodon if longer, otherwise Bluesky</option>
                                                        <option value="longest" <?php selected($opts['preferred_platform'] ?? '', 'longest'); ?>>Longest clean text wins</option>
                                                        <option value="bsky" <?php selected($opts['preferred_platform'] ?? '', 'bsky'); ?>>Always prefer Bluesky</option>
                                                        <option value="mastodon" <?php selected($opts['preferred_platform'] ?? '', 'mastodon'); ?>>Always prefer Mastodon</option>
                                                    </select>
                                                </td>
                                            </tr>
                                        </table>
                                    </div>
                                </div>

                                <!-- SCHEDULE & THRESHOLDS -->
                                <div class="postbox" id="social_box_schedule" style="border-left: 5px solid #0284c7;">
                                    <div class="postbox-header">
                                        <h2 class="hndle">
                                            <span class="dashicons dashicons-clock" style="color:#0284c7; margin-right:4px;"></span>
                                            Schedule &amp; Ingestion Thresholds
                                        </h2>
                                        <button type="button" class="handlediv" aria-expanded="true"><span class="toggle-indicator" aria-hidden="true"></span></button>
                                    </div>
                                    <div class="inside">
                                        <table class="form-table">
                                            <tr>
                                                <th>Check Frequency</th>
                                                <td>
                                                    <select name="social_digest_options[schedule_freq]" id="social_schedule_freq" onchange="socialToggleScheduleFields(this.value)">
                                                        <option value="disabled" <?php selected($opts['schedule_freq'] ?? '', 'disabled'); ?>>Disabled (Manual Workbench Curation Only)</option>
                                                        <option value="hourly" <?php selected($opts['schedule_freq'] ?? '', 'hourly'); ?>>Hourly</option>
                                                        <option value="six_hours" <?php selected($opts['schedule_freq'] ?? '', 'six_hours'); ?>>Every 6 Hours</option>
                                                        <option value="twelve_hours" <?php selected($opts['schedule_freq'] ?? '', 'twelve_hours'); ?>>Every 12 Hours</option>
                                                        <option value="interval_days" <?php selected($opts['schedule_freq'] ?? 'interval_days', 'interval_days'); ?>>Every X Days at Selected Time...</option>
                                                    </select>
                                                    <div id="socialIntervalConfig" style="margin-top: 10px;">
                                                        Run every <input name="social_digest_options[schedule_interval_days]" type="number" min="1" max="60" value="<?php echo esc_attr($opts['schedule_interval_days'] ?? 1); ?>" class="small-text" /> day(s) at 
                                                        <input name="social_digest_options[schedule_time]" type="time" value="<?php echo esc_attr($opts['schedule_time'] ?? '17:00'); ?>" />
                                                    </div>
                                                    <p class="description" style="margin-top:6px;"><strong>Workbench Staging Notice:</strong> If you manually curate digests in the Workbench tab, turn off automated scheduling (or set to <em>Disabled</em>) to prevent scheduled background imports from overwriting unpublished workbench drafts.</p>
                                                </td>
                                            </tr>
                                            <tr>
                                                <th>Minimum Threshold</th>
                                                <td><input name="social_digest_options[min_posts]" type="number" min="1" max="50" value="<?php echo esc_attr($opts['min_posts'] ?? 3); ?>" class="small-text" /> new posts required</td>
                                            </tr>
                                            <tr>
                                                <th>Maximum Age Fallback</th>
                                                <td><input name="social_digest_options[max_age_days]" type="number" min="0" max="60" value="<?php echo esc_attr($opts['max_age_days'] ?? 0); ?>" class="small-text" /> Days (0 to disable)</td>
                                            </tr>
                                            <tr>
                                                <th>Maximum Posts</th>
                                                <td><input name="social_digest_options[max_posts]" type="number" min="1" max="50" value="<?php echo esc_attr($opts['max_posts'] ?? 20); ?>" class="small-text" /></td>
                                            </tr>
                                            <tr>
                                                <th>Keyword Filters</th>
                                                <td><textarea name="social_digest_options[excluded_words]" rows="2" class="large-text"><?php echo esc_textarea($opts['excluded_words'] ?? ''); ?></textarea></td>
                                            </tr>
                                            <tr>
                                                <th>Feed Rules</th>
                                                <td>
                                                    <label><input type="checkbox" name="social_digest_options[include_reposts]" value="1" <?php checked($opts['include_reposts'] ?? 0, 1); ?> /> Include Reposts / Boosts</label><br>
                                                    <label><input type="checkbox" name="social_digest_options[exclude_self_syndicated]" value="1" <?php checked($opts['exclude_self_syndicated'] ?? 1, 1); ?> /> Exclude self-syndicated posts (posts linking back to this WordPress site)</label><br>
                                                    <label><input type="checkbox" name="social_digest_options[exclude_titles]" value="1" <?php checked($opts['exclude_titles'] ?? 0, 1); ?> /> Exclude posts matching existing WordPress headlines</label><br>
                                                    <label><input type="checkbox" name="social_digest_options[keep_threads]" value="1" <?php checked($opts['keep_threads'] ?? 1, 1); ?> /> Include self-replies / threads</label>
                                                </td>
                                            </tr>
                                            <tr>
                                                <th>Article Ordering</th>
                                                <td>
                                                    <select name="social_digest_options[display_order]" id="social_display_order">
                                                        <option value="reverse" <?php selected($opts['display_order'] ?? 'reverse', 'reverse'); ?>>Reverse Chronological (Newest First)</option>
                                                        <option value="chronological" <?php selected($opts['display_order'] ?? '', 'chronological'); ?>>Chronological (Oldest First)</option>
                                                        <option value="random" <?php selected($opts['display_order'] ?? '', 'random'); ?>>Random Order</option>
                                                    </select>
                                                    <p class="description" style="margin-top:4px;">Controls the display sequence of social posts in the published digest article and Next-Run Workbench preview.</p>
                                                </td>
                                            </tr>
                                        </table>
                                    </div>
                                </div>

                                <!-- MEDIA & STORAGE HYGIENE -->
                                <div class="postbox" id="social_box_media_hygiene" style="border-left: 5px solid #10b981;">
                                    <div class="postbox-header">
                                        <h2 class="hndle">
                                            <span class="dashicons dashicons-images-alt2" style="color:#10b981; margin-right:4px;"></span>
                                            Media Optimization &amp; Storage Hygiene
                                        </h2>
                                        <button type="button" class="handlediv" aria-expanded="true"><span class="toggle-indicator" aria-hidden="true"></span></button>
                                    </div>
                                    <div class="inside">
                                        <table class="form-table">
                                            <tr>
                                                <th>Conversion &amp; Cache</th>
                                                <td>
                                                    <label><input type="checkbox" name="social_digest_options[convert_modern_media]" value="1" <?php checked($opts['convert_modern_media'] ?? 1, 1); ?> /> Convert images to WebP / AVIF</label><br>
                                                    <label><input type="checkbox" name="social_digest_options[cache_local_assets]" value="1" <?php checked($opts['cache_local_assets'] ?? 1, 1); ?> /> Cache remote avatars &amp; cards locally</label><br>
                                                    <label><input type="checkbox" name="social_digest_options[generate_srcsets]" value="1" <?php checked($opts['generate_srcsets'] ?? 1, 1); ?> /> Generate standard responsive srcset sizes</label>
                                                </td>
                                            </tr>
                                        </table>
                                    </div>
                                </div>

                                <!-- FEATURED IMAGE -->
                                <div class="postbox" id="social_box_featured_image" style="border-left: 5px solid #f59e0b;">
                                    <div class="postbox-header">
                                        <h2 class="hndle">
                                            <span class="dashicons dashicons-format-image" style="color:#f59e0b; margin-right:4px;"></span>
                                            Featured Image Selection
                                        </h2>
                                        <button type="button" class="handlediv" aria-expanded="true"><span class="toggle-indicator" aria-hidden="true"></span></button>
                                    </div>
                                    <div class="inside">
                                        <table class="form-table">
                                            <tr>
                                                <th>Auto-Thumbnail</th>
                                                <td><label><input type="checkbox" name="social_digest_options[auto_thumb]" value="1" <?php checked($opts['auto_thumb'] ?? 1, 1); ?> /> Sideload and assign Featured Image</label></td>
                                            </tr>
                                            <tr>
                                                <th>Candidate Scope</th>
                                                <td>
                                                    <select name="social_digest_options[thumb_selection_scope]">
                                                        <option value="exclude_first" <?php selected($opts['thumb_selection_scope'] ?? 'exclude_first', 'exclude_first'); ?>>Exclude Most Recent Post (Pick from Posts 2+)</option>
                                                        <option value="all_posts" <?php selected($opts['thumb_selection_scope'] ?? '', 'all_posts'); ?>>Include All Posts</option>
                                                    </select>
                                                </td>
                                            </tr>
                                            <tr>
                                                <th>Strategy</th>
                                                <td>
                                                    <select name="social_digest_options[thumb_selection_mode]">
                                                        <option value="random" <?php selected($opts['thumb_selection_mode'] ?? 'random', 'random'); ?>>Random candidate</option>
                                                        <option value="1" <?php selected($opts['thumb_selection_mode'] ?? '', '1'); ?>>1st available</option>
                                                        <option value="2" <?php selected($opts['thumb_selection_mode'] ?? '', '2'); ?>>2nd available</option>
                                                    </select>
                                                </td>
                                            </tr>
                                        </table>
                                    </div>
                                </div>

                                <!-- PUBLISHING & FRAMING -->
                                <div class="postbox" id="social_box_content" style="border-left: 5px solid #ec4899;">
                                    <div class="postbox-header">
                                        <h2 class="hndle">
                                            <span class="dashicons dashicons-admin-post" style="color:#ec4899; margin-right:4px;"></span>
                                            Publishing, Tags &amp; Article Framing
                                        </h2>
                                        <button type="button" class="handlediv" aria-expanded="true"><span class="toggle-indicator" aria-hidden="true"></span></button>
                                    </div>
                                    <div class="inside">
                                        <table class="form-table">
                                            <tr>
                                                <th>Post Status</th>
                                                <td>
                                                    <select name="social_digest_options[post_status]" id="social_post_status">
                                                        <option value="publish" <?php selected($opts['post_status'] ?? 'publish', 'publish'); ?>>Auto-Publish Immediately</option>
                                                        <option value="pending" <?php selected($opts['post_status'] ?? '', 'pending'); ?>>Pending Review</option>
                                                        <option value="draft" <?php selected($opts['post_status'] ?? '', 'draft'); ?>>Save as Draft</option>
                                                    </select>
                                                </td>
                                            </tr>
                                            <tr>
                                                <th>RSS-Only</th>
                                                <td><label><input type="checkbox" name="social_digest_options[rss_only_mode]" value="1" <?php checked($opts['rss_only_mode'] ?? 0, 1); ?> /> Publish exclusively to RSS feeds</label></td>
                                            </tr>
                                            <tr>
                                                <th>Post Author</th>
                                                <td><?php wp_dropdown_users(['name' => 'social_digest_options[post_author]', 'id' => 'social_post_author', 'selected' => $opts['post_author'] ?? 1]); ?></td>
                                            </tr>
                                            <tr>
                                                <th>Categories</th>
                                                <td>
                                                    <div style="max-height: 120px; overflow-y: auto; border: 1px solid #ccd0d4; padding: 6px 10px; width: 280px; background:#fff;">
                                                        <?php foreach ($all_cats as $cat): ?>
                                                            <label style="display:block;"><input type="checkbox" name="social_digest_options[categories][]" value="<?php echo $cat->term_id; ?>" <?php checked(in_array($cat->term_id, $selected_cats)); ?> /> <?php echo esc_html($cat->name); ?></label>
                                                        <?php endforeach; ?>
                                                    </div>
                                                </td>
                                            </tr>
                                            <tr>
                                                <th>Title Template</th>
                                                <td>
                                                    <input name="social_digest_options[title_template]" type="text" value="<?php echo esc_attr($opts['title_template'] ?? 'Social Digest {hashtags}'); ?>" class="regular-text" />
                                                    <p class="description">Available variables: <code>{hashtags}</code>, <code>{date}</code>, <code>{count}</code></p>
                                                    <div style="margin-top: 10px; padding: 12px; background: #f6f7f7; border: 1px solid #ccd0d4; border-radius: 4px; max-width: 500px;">
                                                        <strong style="display:block; margin-bottom: 8px;">Hashtag Formatting Rules:</strong>
                                                        <div style="margin-bottom: 8px;">
                                                            <label style="display:inline-block; width: 80px;">Enclosure:</label>
                                                            <select name="social_digest_options[title_tag_enclosure]">
                                                                <option value="parentheses" <?php selected($opts['title_tag_enclosure'] ?? 'parentheses', 'parentheses'); ?>>(Tag 1, Tag 2)</option>
                                                                <option value="brackets" <?php selected($opts['title_tag_enclosure'] ?? '', 'brackets'); ?>>[Tag 1, Tag 2]</option>
                                                                <option value="none" <?php selected($opts['title_tag_enclosure'] ?? '', 'none'); ?>>Tag 1, Tag 2 (No Enclosure)</option>
                                                            </select>
                                                        </div>
                                                        <div>
                                                            <label style="display:inline-block; width: 80px;">Delimiter:</label>
                                                            <select name="social_digest_options[title_tag_delimiter]">
                                                                <option value="oxford" <?php selected($opts['title_tag_delimiter'] ?? 'oxford', 'oxford'); ?>>Tag 1, Tag 2, and Tag 3</option>
                                                                <option value="commas" <?php selected($opts['title_tag_delimiter'] ?? '', 'commas'); ?>>Tag 1, Tag 2, Tag 3</option>
                                                                <option value="ampersand" <?php selected($opts['title_tag_delimiter'] ?? '', 'ampersand'); ?>>Tag 1, Tag 2 &amp; Tag 3</option>
                                                                <option value="pipe" <?php selected($opts['title_tag_delimiter'] ?? '', 'pipe'); ?>>Tag 1 | Tag 2 | Tag 3</option>
                                                                <option value="slash" <?php selected($opts['title_tag_delimiter'] ?? '', 'slash'); ?>>Tag 1 / Tag 2 / Tag 3</option>
                                                            </select>
                                                        </div>
                                                    </div>
                                                </td>
                                            </tr>
                                            <tr>
                                                <th>Article Header &amp; Footer</th>
                                                <td>
                                                    <div style="max-width: 800px;">
                                                        <?php 
                                                        wp_editor($unified_editor_value, 'social_digest_unified_content', [
                                                            'textarea_name' => 'social_digest_options[unified_content]',
                                                            'textarea_rows' => 10,
                                                            'media_buttons' => false,
                                                            'teeny'         => false
                                                        ]); 
                                                        ?>
                                                    </div>
                                                </td>
                                            </tr>
                                            <tr>
                                                <th>Uninstallation Policy</th>
                                                <td><label><input type="checkbox" name="social_digest_options[wipe_data_on_uninstall]" value="1" <?php checked($opts['wipe_data_on_uninstall'] ?? 1, 1); ?> /> Delete settings on uninstall</label></td>
                                            </tr>
                                        </table>
                                    </div>
                                </div>

                            </div>
                        </div>
                    </div>
                </div>
                <?php submit_button('Save Settings'); ?>
            </form>

        <?php elseif ($active_tab === 'workbench'): ?>
            <?php 
            $state = social_workbench_state();
            $candidates = (array)($state['candidates'] ?? []);
            $has_candidates = !empty($candidates);
            $fetch_confirm_attr = $has_candidates ? ' onclick="return confirm(\'Refreshing will discard your current exclusions, pin, and notes \u2014 continue?\');"' : '';
            ?>

            <?php if ($next_run && $has_candidates): ?>
                <div class="notice notice-warning" style="margin: 0 0 15px 0; padding: 12px 14px; border-left: 4px solid #dba617; background: #fff8e5;">
                    <p style="margin: 0; font-size: 13px; line-height: 1.5; color: #614700;">
                        <strong>Warning: Automated Schedule Active:</strong> Automated cron scheduling is currently running and will overwrite this staged workbench draft on its next scheduled cycle (<strong><?php echo esc_html(wp_date('Y-m-d H:i:s T', $next_run, $site_tz)); ?></strong>). If you are curating this digest by hand, set <em>Check Frequency</em> to <strong>"Disabled (Manual Workbench Curation Only)"</strong> under <a href="<?php echo esc_url(admin_url('edit.php?page=social-digest-settings&tab=settings')); ?>" style="color: #2271b1; text-decoration: underline;">General Settings</a> until you publish or discard this draft.
                    </p>
                </div>
            <?php endif; ?>

            <div style="background:#fff; border:1px solid #c3c4c7; padding:8px 12px; border-radius:4px; margin-bottom:15px; font-size:12px; color:#50575e; display:flex; align-items:center; gap:6px;">
                <span class="dashicons dashicons-move" style="color:#2271b1;"></span>
                <span>Drag widget headers or click toggle arrows to expand, collapse, and reorder.</span>
            </div>

            <form method="post">
                <?php wp_nonce_field('sd53_workbench_action', 'sd53_nonce'); ?>

                <!-- TOP QUICK ACTIONS WORKBENCH -->
                <div class="postbox" id="social_wb_box_quick_actions" style="border-left: 5px solid #2271b1;">
                    <div class="postbox-header">
                        <h2 class="hndle">
                            <span class="dashicons dashicons-admin-generic" style="color:#2271b1; margin-right:4px;"></span>
                            Prepare Next Digest Run
                        </h2>
                        <button type="button" class="handlediv" aria-expanded="true"><span class="toggle-indicator" aria-hidden="true"></span></button>
                    </div>
                    <div class="inside">
                        <p style="margin-top:0">Fetch a preview of what the next digest will contain. Exclude articles, pin a lead story, add commentary, or temporarily override framing before publishing.</p>
                        <div style="display:flex; gap:8px; flex-wrap:wrap; align-items:center;">
                            <button class="button button-primary" name="sd53_fetch" value="1"<?php echo $fetch_confirm_attr; ?>>Fetch / Refresh Next Run</button>
                            <button class="button" name="sd53_save" value="1">Save Next-Run Changes</button>
                            <button class="button" name="sd53_reset" value="1" onclick="return confirm('Discard all changes?')">Reset Next Run</button>
                            <button class="button" name="sd53_save_draft" value="1" onclick="return confirm('Save this staged digest as a WordPress Draft post?')"><span class="dashicons dashicons-edit" style="vertical-align:text-bottom; margin-right:2px; font-size:16px;"></span> Save as Draft</button>
                            <button class="button button-primary" name="sd53_publish" value="1" onclick="return confirm('Publish this digest immediately?')">Publish Next Run</button>
                        </div>
                    </div>
                </div>

                <div class="meta-box-sortables ui-sortable" id="social_wb_main_sortable">
                    
                    <!-- ARTICLES LIST & PINNING -->
                    <div class="postbox" id="social_wb_box_candidates" style="border-left: 5px solid #0284c7;">
                        <div class="postbox-header">
                            <h2 class="hndle">
                                <span class="dashicons dashicons-list-view" style="color:#0284c7; margin-right:4px;"></span>
                                Articles for Next Run
                            </h2>
                            <button type="button" class="handlediv" aria-expanded="true"><span class="toggle-indicator" aria-hidden="true"></span></button>
                        </div>
                        <div class="inside">
                            <?php if (!$candidates): ?>
                                <p><strong>No next-run candidates loaded.</strong> Click <em>Fetch / Refresh Next Run</em> above.</p>
                            <?php else: ?>
                                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:12px; border-bottom:1px solid #e2e4e7; padding-bottom:8px; flex-wrap:wrap; gap:10px;">
                                    <span style="font-size:12px; color:#50575e;">Select <strong>Pin as Lead Story</strong> to pin an update to top, or <strong>Set as Featured Image</strong> to choose the digest's featured post thumbnail.</span>
                                    <div style="display:flex; gap:12px; font-size:11px; color:#646970; align-items:center; flex-wrap:wrap;">
                                        <label>
                                            <input type="radio" name="pinned_lead_post" value="" <?php checked(empty(array_filter($candidates, fn($item) => !empty($item['pinned'])))); ?> onchange="document.querySelectorAll('.sd53-item').forEach(el=>el.classList.remove('is-pinned'));"> 
                                            <em>No Pinned Lead</em>
                                        </label>
                                        <label>
                                            <input type="radio" name="selected_featured_post" value="auto" <?php checked(empty($state['featured_image_override_key']) || $state['featured_image_override_key'] === 'auto'); ?>> 
                                            <em>Auto Featured Image</em>
                                        </label>
                                        <label>
                                            <input type="radio" name="selected_featured_post" value="none" <?php checked(($state['featured_image_override_key'] ?? '') === 'none'); ?> onchange="document.querySelectorAll('.sd53-item').forEach(el=>el.classList.remove('is-featured-thumb'));"> 
                                            <em>No Featured Image</em>
                                        </label>
                                    </div>
                                </div>

                                <?php foreach ($candidates as $i => $c): 
                                    $key = sanitize_key($c['key']); 
                                    $excluded = !empty($c['excluded']); 
                                    $pinned = !empty($c['pinned']);
                                    $c_thumb = esc_url($c['thumb_image'] ?? '');
                                    $active_featured = esc_url($state['preview']['featured_image'] ?? '');
                                    $is_featured_thumb = ($c_thumb !== '' && $active_featured === $c_thumb);
                                ?>
                                    <div class="sd53-item <?php echo $excluded ? 'excluded' : ''; ?> <?php echo $pinned ? 'is-pinned' : ''; ?> <?php echo $is_featured_thumb ? 'is-featured-thumb' : ''; ?>" id="sd53_<?php echo esc_attr($key); ?>" style="transition: all 0.2s ease;">
                                        <div class="sd53-item-head">
                                            <div style="display:flex; align-items:center; gap:16px; flex-wrap:wrap;">
                                                <label style="font-weight:600;"><input type="checkbox" name="candidate[<?php echo esc_attr($key); ?>][excluded]" value="1" <?php checked($excluded); ?> onchange="this.closest('.sd53-item').classList.toggle('excluded', this.checked)"> Exclude from next post</label>
                                                <label style="color:#b45309; font-weight:700; cursor:pointer;">
                                                    <input type="radio" name="pinned_lead_post" value="<?php echo esc_attr($key); ?>" <?php checked($pinned); ?> onchange="document.querySelectorAll('.sd53-item').forEach(el=>el.classList.remove('is-pinned')); this.closest('.sd53-item').classList.add('is-pinned');"> 
                                                    📌 Pin as Lead Story
                                                </label>
                                                <?php if ($c_thumb): ?>
                                                    <label style="color:#047857; font-weight:700; cursor:pointer; display:inline-flex; align-items:center; gap:4px;">
                                                        <input type="radio" name="selected_featured_post" value="<?php echo esc_attr($key); ?>" <?php checked($is_featured_thumb); ?> onchange="document.querySelectorAll('.sd53-item').forEach(el=>el.classList.remove('is-featured-thumb')); if (this.checked) this.closest('.sd53-item').classList.add('is-featured-thumb');"> 
                                                        🖼️ Set as Featured Image
                                                        <img src="<?php echo esc_url($c_thumb); ?>" style="width:20px; height:20px; object-fit:cover; border-radius:3px; border:1px solid #10b981; vertical-align:middle;" title="Candidate Featured Thumbnail" />
                                                    </label>
                                                <?php else: ?>
                                                    <span style="color:#8c8f94; font-size:11px; font-style:italic;">(No post image)</span>
                                                <?php endif; ?>
                                            </div>
                                            <span style="background:#e8f0fe; color:#1a73e8; font-weight:700; padding:2px 8px; border-radius:3px; font-size:10px; text-transform:uppercase;">
                                                <?php echo esc_html($c['network'] ?? 'Social'); ?>
                                            </span>
                                        </div>
                                        <div class="sd53-item-body">
                                            <div style="font-size:12px; color:#50575e; margin-bottom:8px; line-height:1.4;"><?php echo esc_html($c['text'] ?? ''); ?></div>
                                            <?php echo wp_kses_post($c['html'] ?? ''); ?>
                                            <label style="display:block; margin-top:12px; font-weight:600; font-size:11px; color:#2c3338;">Attach Custom Takeaway / Author Note:</label>
                                            <textarea class="sd53-comment" name="candidate[<?php echo esc_attr($key); ?>][commentary]" placeholder="Optional author lead-in commentary..."><?php echo esc_textarea($c['commentary'] ?? ''); ?></textarea>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- TEMPORARY FRAMING -->
                    <div class="postbox" id="social_wb_box_framing" style="border-left: 5px solid #8e24aa;">
                        <div class="postbox-header">
                            <h2 class="hndle">
                                <span class="dashicons dashicons-edit" style="color:#8e24aa; margin-right:4px;"></span>
                                Temporary Article Framing Overrides
                            </h2>
                            <button type="button" class="handlediv" aria-expanded="true"><span class="toggle-indicator" aria-hidden="true"></span></button>
                        </div>
                        <div class="inside">
                            <p style="margin-top:0; color:#50575e; font-size:12px;">
                                Overrides apply only to the next published digest. Use the <strong>Insert Post Splitter</strong> button to place the divider between header and footer.
                            </p>

                            <p style="margin-bottom:15px;">
                                <label>
                                    <input type="checkbox" name="framing_override_enabled" value="1" <?php checked(!empty($state['framing_override_enabled'])); ?>>
                                    <strong>Enable temporary header &amp; footer overrides for this run</strong>
                                </label>
                            </p>

                            <?php
                            $wb_header = !empty($state['header_override']) ? $state['header_override'] : ($opts['header_text'] ?? '');
                            $wb_footer = !empty($state['footer_override']) ? $state['footer_override'] : ($opts['footer_text'] ?? '');
                            $wb_unified = trim($wb_header) . "\n\n" . $split_marker_html . "\n\n" . trim($wb_footer);

                            wp_editor($wb_unified, 'social_digest_workbench_unified_content', [
                                'textarea_name' => 'workbench_unified_content',
                                'textarea_rows' => 8,
                                'media_buttons' => false,
                                'teeny'         => false,
                                'quicktags'     => [
                                    'buttons' => 'strong,em,link,close'
                                ]
                            ]);
                            ?>
                        </div>
                    </div>

                    <!-- ACTIONS & DIAGNOSTICS -->
                    <div class="postbox" id="social_wb_box_diagnostics" style="border-left: 5px solid #0085ff;">
                        <div class="postbox-header">
                            <h2 class="hndle">
                                <span class="dashicons dashicons-heart" style="color:#0085ff; margin-right:4px;"></span>
                                Network Actions &amp; Diagnostics
                            </h2>
                            <button type="button" class="handlediv" aria-expanded="true"><span class="toggle-indicator" aria-hidden="true"></span></button>
                        </div>
                        <div class="inside">
                            <p>Bluesky cutoff: <strong><?php echo $bsky_last ? esc_html(wp_date('Y-m-d H:i:s T', $bsky_last, $site_tz)) : 'None'; ?></strong> | Mastodon cutoff: <strong><?php echo $masto_last ? esc_html(wp_date('Y-m-d H:i:s T', $masto_last, $site_tz)) : 'None'; ?></strong> | Next auto-run: <strong><?php echo $next_run ? esc_html(wp_date('Y-m-d H:i:s T', $next_run, $site_tz)) : 'Not scheduled'; ?></strong></p>
                            
                            <div style="margin-top:14px; padding-top:12px; border-top:1px solid #dcdcde;">
                                <strong style="display:block; margin-bottom:6px; color:#1d2327;">Cutoff Management &amp; Time Boundary:</strong>
                                <p class="description" style="margin-bottom:10px;">Control which posts are considered "new". Any posts created before the cutoff timestamp will not be included in upcoming digests.</p>
                                
                                <div style="display:flex; gap:8px; flex-wrap:wrap; align-items:center; margin-bottom:12px;">
                                    <button type="submit" class="button button-primary" name="sd53_set_cutoff_now" value="1" onclick="return confirm('Set cutoff markers to right now? No posts published before this exact moment will be pulled into future digests.')">
                                        <span class="dashicons dashicons-clock" style="vertical-align:text-bottom; margin-right:3px;"></span> Set Cutoff to Right Now
                                    </button>
                                    <button type="submit" class="button" name="sd53_reset_cutoff" value="1" onclick="return confirm('Clear cutoff markers for both networks? Upcoming fetch will pull posts back to your Max Post Age limit.')">Clear Cutoffs (Fetch Backlog)</button>
                                </div>

                                <div style="display:inline-flex; gap:6px; flex-wrap:wrap; align-items:center; background:#f6f7f7; border:1px solid #dcdcde; padding:8px 12px; border-radius:4px;">
                                    <label for="sd53_custom_cutoff_datetime" style="font-weight:600; font-size:12px;">Or Set Custom Cutoff Date &amp; Time:</label>
                                    <input type="datetime-local" id="sd53_custom_cutoff_datetime" name="sd53_custom_cutoff_datetime" class="regular-text" style="width:auto; max-width:210px;" value="<?php echo esc_attr(wp_date('Y-m-d\TH:i', time(), $site_tz)); ?>" />
                                    <button type="submit" class="button" name="sd53_set_cutoff_custom" value="1">Apply Custom Cutoff</button>
                                </div>
                            </div>

                            <div style="margin-top:14px; padding-top:12px; border-top:1px solid #dcdcde; display:flex; gap:8px; flex-wrap:wrap;">
                                <button type="submit" class="button" name="sd53_manual_run" value="1">Run Standard Automated Import Now</button>
                            </div>
                        </div>
                    </div>

                </div>
            </form>
        <?php endif; ?>
    </div>

    <script>
    function socialToggleScheduleFields(freq) {
        const configWrap = document.getElementById('socialIntervalConfig');
        if (configWrap) configWrap.style.display = (freq === 'interval_days') ? 'block' : 'none';
    }
    function socialToggleModeFields(mode) {
        const combinedFields = document.querySelectorAll('.social-combined-field');
        combinedFields.forEach(el => { el.style.display = (mode === 'both') ? 'table-row' : 'none'; });
        const bskyRow = document.getElementById('row_bsky_handle');
        const mastoRow = document.getElementById('row_masto_handle');
        if (bskyRow) bskyRow.style.display = (mode === 'bsky' || mode === 'both') ? 'table-row' : 'none';
        if (mastoRow) mastoRow.style.display = (mode === 'mastodon' || mode === 'both') ? 'table-row' : 'none';
    }

    jQuery(document).ready(function($) {
        if (typeof postboxes !== 'undefined') {
            postboxes.add_postbox_toggles('settings_page_social-digest-settings');
        }
        if ($.fn.sortable) {
            $('#social_settings_sortable, #social_wb_main_sortable').sortable({
                handle: '.hndle',
                items: '.postbox',
                placeholder: 'social-sortable-placeholder',
                forcePlaceholderSize: true,
                opacity: 0.8,
                cursor: 'grabbing'
            });
        }
    });
    </script>
    <?php
}

// ==========================================
// 6. CORE FETCHERS, NATIVE RENDERER & ADAPTERS
// ==========================================

function social_render_native_card($item, $opts = []) {
    $mode = $opts['network_mode'] ?? 'both';
    $avatar_pref = $opts['avatar_source'] ?? 'auto';
    $timestamp = (int)($item['timestamp'] ?? time());
    $site_tz = wp_timezone();
    $formatted_date = wp_date('M j, Y · g:i A', $timestamp, $site_tz);

    $is_repost = !empty($item['is_repost']);
    $repost_user = esc_html($item['repost_user'] ?? '');

    // Resolve active platform links based on network_mode
    $links = (array)($item['platform_links'] ?? []);
    $has_bsky = !empty($links['bsky']['url']) && ($mode === 'bsky' || $mode === 'both');
    $has_masto = !empty($links['mastodon']['url']) && ($mode === 'mastodon' || $mode === 'both');

    // Determine author name & primary handle
    $author_name = esc_html($item['author_name'] ?? 'Author');

    // Avatar resolution
    $avatar_url = '';
    if ($avatar_pref !== 'none') {
        if ($avatar_pref === 'bsky' && !empty($links['bsky']['avatar'])) {
            $avatar_url = $links['bsky']['avatar'];
        } elseif ($avatar_pref === 'mastodon' && !empty($links['mastodon']['avatar'])) {
            $avatar_url = $links['mastodon']['avatar'];
        } elseif (!empty($item['author_avatar'])) {
            $avatar_url = $item['author_avatar'];
        } elseif (!empty($links['bsky']['avatar'])) {
            $avatar_url = $links['bsky']['avatar'];
        } elseif (!empty($links['mastodon']['avatar'])) {
            $avatar_url = $links['mastodon']['avatar'];
        }
    }

    // Platform handles & inline follow links
    $social_identities = [];

    if ($has_bsky && !empty($links['bsky']['handle'])) {
        $b_raw_handle = ltrim($links['bsky']['handle'], '@');
        $b_handle = '@' . esc_html($b_raw_handle);
        $b_profile = esc_url($links['bsky']['profile_url'] ?? ('https://bsky.app/profile/' . $b_raw_handle));
        $b_html  = '<span class="social-identity bsky-identity" style="display:inline-flex; align-items:center; white-space:nowrap;">';
        $b_html .= '<a href="' . $b_profile . '" target="_blank" rel="noopener" style="color:#64748b; text-decoration:none;">' . $b_handle . '</a>';
        $b_html .= '<span style="color:#94a3b8; margin:0 5px;">·</span>';
        $b_html .= '<a href="' . $b_profile . '" target="_blank" rel="noopener" class="social-follow-link bsky-follow" style="color:#0284c7; font-weight:600; text-decoration:none;" title="Follow ' . $b_handle . ' on Bluesky">Follow</a>';
        $b_html .= '</span>';
        $social_identities[] = $b_html;
    }

    if ($has_masto && !empty($links['mastodon']['handle'])) {
        $m_raw_handle = ltrim($links['mastodon']['handle'], '@');
        if (strpos($m_raw_handle, '@') === false && !empty($links['mastodon']['profile_url'])) {
            $p_host = parse_url($links['mastodon']['profile_url'], PHP_URL_HOST);
            if ($p_host) {
                $m_raw_handle .= '@' . $p_host;
            }
        }
        $m_handle = '@' . esc_html($m_raw_handle);
        $m_profile = esc_url($links['mastodon']['profile_url'] ?? '');
        $m_html  = '<span class="social-identity masto-identity" style="display:inline-flex; align-items:center; white-space:nowrap;">';
        $m_html .= '<a href="' . $m_profile . '" target="_blank" rel="noopener" style="color:#64748b; text-decoration:none;">' . $m_handle . '</a>';
        $m_html .= '<span style="color:#94a3b8; margin:0 5px;">·</span>';
        $m_html .= '<a href="' . $m_profile . '" target="_blank" rel="noopener" class="social-follow-link masto-follow" style="color:#7e22ce; font-weight:600; text-decoration:none;" title="Follow ' . $m_handle . ' on Mastodon">Follow</a>';
        $m_html .= '</span>';
        $social_identities[] = $m_html;
    }

    if (empty($social_identities) && !empty($item['author_handle'])) {
        $fallback_handle = '@' . ltrim(esc_html($item['author_handle']), '@');
        $social_identities[] = '<span style="color:#64748b;">' . $fallback_handle . '</span>';
    }

    // Repost / Boost banner
    $repost_html = '';
    if ($is_repost && $repost_user) {
        $repost_html = '<div class="social-repost-banner" style="font-size:12px; font-weight:700; color:#0284c7; margin-bottom:10px; display:flex; align-items:center; gap:5px;">&#x1F501; Reposted by @' . $repost_user . '</div>';
    }

    // Build the Native Card HTML
    $html = '<div class="social-post social-card" style="border:1px solid #e2e8f0; border-left:4px solid #0284c7; border-radius:12px; padding:18px; margin:26px auto; background:#ffffff; box-shadow:0 2px 6px rgba(0,0,0,0.04); max-width:600px; box-sizing:border-box; font-family:-apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, Helvetica, Arial, sans-serif;">';

    if ($repost_html) {
        $html .= $repost_html;
    }

    // Card Header (Avatar + Name + Inline Handles with Follow Links)
    $html .= '<div class="social-card-header" style="display:flex; align-items:center; gap:12px; margin-bottom:14px; padding-bottom:12px; border-bottom:1px solid #f1f5f9;">';
    if ($avatar_url) {
        $html .= '<img src="' . esc_url($avatar_url) . '" alt="' . esc_attr($author_name) . '" class="social-avatar" style="width:44px; height:44px; border-radius:50%; object-fit:cover; border:1px solid #e2e8f0; flex-shrink:0;" />';
    }
    $html .= '<div style="min-width:0; line-height:1.35; flex:1;">';
    $html .= '<div class="social-author-name" style="font-weight:700; font-size:15px; color:#0f172a; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">' . $author_name . '</div>';
    $html .= '<div class="social-author-handles" style="font-size:13px; color:#64748b; display:flex; flex-wrap:wrap; align-items:center; column-gap:8px; row-gap:2px;">' . implode('<span style="color:#cbd5e1; margin:0 2px;">·</span>', $social_identities) . '</div>';
    $html .= '</div>';
    $html .= '</div>';

    // Body content
    $html .= '<div class="social-card-body" style="font-size:15px; line-height:1.6; color:#1e293b; margin-bottom:14px; overflow-wrap:anywhere; word-break:break-word;">';
    $body_text = $item['body_html'] ?? nl2br(esc_html($item['text'] ?? ''));
    $html .= $body_text;
    $html .= '</div>';

    if (!empty($item['media_html'])) {
        $html .= $item['media_html'];
    }

    // Card Footer (Date on left + Interactive Platform badges on right)
    $html .= '<div class="social-card-footer" style="display:flex; flex-wrap:wrap; align-items:center; justify-content:space-between; gap:10px; margin-top:14px; padding-top:12px; border-top:1px solid #f1f5f9; font-size:13px; color:#64748b;">';
    $html .= '<div class="social-timestamp" style="display:flex; align-items:center;">';
    $html .= '<span>' . esc_html($formatted_date) . '</span>';
    $html .= '</div>';

    // Platform action badges with live engagement counters
    $badges = [];
    if ($has_bsky) {
        $bsky_data = $links['bsky'];
        $b_url = esc_url($bsky_data['url'] ?? '');
        $b_likes = absint($bsky_data['likes'] ?? 0);
        $like_str = ($b_likes > 0) ? ' <span style="font-size:11px; background:#eff6ff; color:#1d4ed8; padding:1px 6px; border-radius:9999px; margin-left:3px;">❤️ ' . $b_likes . '</span>' : '';
        $badges[] = '<a href="' . $b_url . '" target="_blank" rel="noopener" class="social-badge bsky-badge" style="display:inline-flex; align-items:center; gap:5px; padding:4px 10px; border-radius:6px; background:#f0f9ff; color:#0284c7; text-decoration:none; border:1px solid #bae6fd; font-weight:600; font-size:12px;" title="View and like post on Bluesky">🦋 Bluesky' . $like_str . ' ↗</a>';
    }
    if ($has_masto) {
        $masto_data = $links['mastodon'];
        $m_url = esc_url($masto_data['url'] ?? '');
        $m_favs = absint($masto_data['favs'] ?? 0);
        $m_boosts = absint($masto_data['boosts'] ?? 0);
        $m_stats = [];
        if ($m_favs > 0) $m_stats[] = '⭐ ' . $m_favs;
        if ($m_boosts > 0) $m_stats[] = '🔁 ' . $m_boosts;
        $m_stat_str = $m_stats ? ' <span style="font-size:11px; background:#faf5ff; color:#6b21a8; padding:1px 6px; border-radius:9999px; margin-left:3px;">' . implode(' · ', $m_stats) . '</span>' : '';
        $badges[] = '<a href="' . $m_url . '" target="_blank" rel="noopener" class="social-badge masto-badge" style="display:inline-flex; align-items:center; gap:5px; padding:4px 10px; border-radius:6px; background:#faf5ff; color:#7e22ce; text-decoration:none; border:1px solid #e9d5ff; font-weight:600; font-size:12px;" title="View and favorite on Mastodon">🐘 Mastodon' . $m_stat_str . ' ↗</a>';
    }

    if ($badges) {
        $html .= '<div class="social-platform-badges" style="display:flex; flex-wrap:wrap; align-items:center; gap:6px;">' . implode('', $badges) . '</div>';
    }

    $html .= '</div>';
    $html .= '</div>';

    return $html;
}

register_activation_hook(__FILE__, function() {
    $opts = get_option('social_digest_options', []);
    social_reschedule_cron($opts);
});

register_deactivation_hook(__FILE__, function() {
    wp_clear_scheduled_hook('social_digest_cron');
});

add_action('social_digest_cron', __NAMESPACE__ . '\\social_run_digest_import');

function social_fetch_bluesky($handle, $last_check, $keep_threads, $include_reposts) {
    if (empty($handle)) return ['posts' => [], 'newest_timestamp' => $last_check];

    $opts = get_option('social_digest_options', []);
    $api_url = 'https://public.api.bsky.app/xrpc/app.bsky.feed.getAuthorFeed?actor=' . urlencode($handle) . '&limit=50';
    $response = wp_remote_get($api_url, ['timeout' => 20]);
    if (is_wp_error($response)) return ['posts' => [], 'newest_timestamp' => $last_check];

    $body = json_decode(wp_remote_retrieve_body($response), true);
    if (empty($body['feed'])) return ['posts' => [], 'newest_timestamp' => $last_check];

    $items = [];
    $newest_timestamp = $last_check;

    foreach ($body['feed'] as $item) {
        if (!isset($item['post']) || !is_array($item['post'])) continue;
        $post = $item['post'];
        $is_repost = isset($item['reason']);

        if ($is_repost && !$include_reposts) continue;

        if ($is_repost && !empty($item['reason']['indexedAt'])) {
            $created_at = strtotime($item['reason']['indexedAt']);
        } elseif (!empty($post['record']['createdAt'])) {
            $created_at = strtotime($post['record']['createdAt']);
        } elseif (!empty($post['indexedAt'])) {
            $created_at = strtotime($post['indexedAt']);
        } else {
            $created_at = time();
        }

        if ($created_at <= $last_check) continue;
        if ($created_at > $newest_timestamp) $newest_timestamp = $created_at;

        if (!$is_repost && !empty($post['record']['reply'])) {
            if (!$keep_threads) continue;
            $parent_author_did = $item['reply']['parent']['author']['did'] ?? '';
            $author_did        = $post['author']['did'] ?? '';
            if ($parent_author_did !== $author_did) continue;
        }

        $text = $post['record']['text'] ?? '';
        $first_image_url = null;
        $media_html = '';

        if (isset($post['embed']['images']) && is_array($post['embed']['images'])) {
            $images = $post['embed']['images'];
            $num_imgs = count($images);
            if ($num_imgs > 1) {
                $cols = $num_imgs == 2 || $num_imgs == 4 ? 2 : 3;
                $media_html .= '<div class="social-embed-images" style="display:grid; grid-template-columns: repeat(' . $cols . ', 1fr); gap:10px; margin:12px 0;">';
            } else {
                $media_html .= '<div class="social-embed-images" style="margin:12px 0;">';
            }
            foreach ($images as $img) {
                $img_url = esc_url($img['fullsize'] ?? $img['thumb'] ?? '');
                $alt_txt = esc_attr($img['alt'] ?? 'Bluesky image');
                if ($img_url) {
                    if (!$first_image_url) $first_image_url = $img_url;
                    if ($num_imgs > 1) {
                        $media_html .= '<figure style="margin:0;"><img src="' . $img_url . '" alt="' . $alt_txt . '" style="width:100%; aspect-ratio:1/1; object-fit:cover; border-radius:8px; display:block;" /></figure>';
                    } else {
                        $media_html .= '<figure style="margin:0;"><img src="' . $img_url . '" alt="' . $alt_txt . '" style="max-width:100%; max-height:400px; width:auto; border-radius:8px; display:block;" /></figure>';
                    }
                }
            }
            $media_html .= '</div>';
        }

        if (isset($post['embed']['external'])) {
            $ext = $post['embed']['external'];
            $link_url   = esc_url($ext['uri'] ?? '');
            $link_title = esc_html($ext['title'] ?? '');
            $link_desc  = esc_html($ext['description'] ?? '');
            $link_thumb = esc_url($ext['thumb'] ?? '');

            if (!$link_thumb && $link_url) {
                $link_thumb = social_get_og_image_cached($link_url);
            }

            if (!$first_image_url && $link_thumb) $first_image_url = $link_thumb;

            $media_html .= '<div class="social-link-card" style="border:1px solid #e1e8ed; border-radius:8px; overflow:hidden; margin:12px 0; max-width:500px; background:#f8fafc;">';
            if ($link_thumb) $media_html .= '<img src="' . $link_thumb . '" alt="' . $link_title . '" style="width:100%; max-height:220px; object-fit:cover; display:block;" />';
            $media_html .= '<div style="padding:10px 14px;"><div style="font-weight:bold; font-size:1em; margin-bottom:4px;"><a href="' . $link_url . '" target="_blank" rel="noopener">' . $link_title . '</a></div>';
            if ($link_desc) $media_html .= '<div style="font-size:0.85em; color:#555; line-height:1.4;">' . wp_trim_words($link_desc, 25) . '</div>';
            $media_html .= '</div></div>';
        }

        $post_uri = $post['uri'];
        $rkey     = substr($post_uri, strrpos($post_uri, '/') + 1);
        $author_handle = $post['author']['handle'] ?? $handle;
        $author_name   = esc_html($post['author']['displayName'] ?? $author_handle);
        $author_avatar = esc_url_raw($post['author']['avatar'] ?? '');
        $author_profile_url = 'https://bsky.app/profile/' . $author_handle;
        $web_url       = 'https://bsky.app/profile/' . $author_handle . '/post/' . $rkey;

        $like_count   = absint($post['likeCount'] ?? 0);
        $repost_count = absint($post['repostCount'] ?? 0);

        $extracted_urls = [];
        if (isset($post['embed']['external']['uri'])) $extracted_urls[] = $post['embed']['external']['uri'];
        if (preg_match_all('/\b(?:https?:\/\/|www\.)[^\s<"\'\)]+/i', $text, $u_m)) $extracted_urls = array_merge($extracted_urls, $u_m[0]);

        $item_data = [
            'network'        => 'bsky',
            'timestamp'      => $created_at,
            'text'           => $text,
            'body_html'      => nl2br(esc_html($text)),
            'author_name'    => $author_name,
            'author_handle'  => $author_handle,
            'author_avatar'  => $author_avatar,
            'media_html'     => $media_html,
            'thumb_image'    => $first_image_url,
            'is_repost'      => $is_repost,
            'repost_user'    => $handle,
            'platform_links' => [
                'bsky' => [
                    'url'         => $web_url,
                    'profile_url' => $author_profile_url,
                    'handle'      => $author_handle,
                    'avatar'      => $author_avatar,
                    'likes'       => $like_count,
                    'reposts'     => $repost_count,
                ]
            ],
            'extra_tags'     => [],
            'urls'           => array_values(array_unique($extracted_urls))
        ];

        $item_data['html'] = social_render_native_card($item_data, $opts);
        $items[] = $item_data;
    }

    return ['posts' => $items, 'newest_timestamp' => $newest_timestamp];
}

function social_fetch_mastodon($handle_raw, $last_check, $keep_threads, $include_reposts) {
    if (empty($handle_raw)) return ['posts' => [], 'newest_timestamp' => $last_check];

    $opts = get_option('social_digest_options', []);
    $raw = trim($handle_raw);
    if (preg_match('#^https?://([^/]+)/@?([^/?\#]+)#i', $raw, $url_parts)) {
        $instance = $url_parts[1];
        $username = $url_parts[2];
    } else {
        $clean = ltrim($raw, '@');
        $parts = explode('@', $clean);
        if (count($parts) === 2) {
            $username = $parts[0];
            $instance = $parts[1];
        } else {
            return ['posts' => [], 'newest_timestamp' => $last_check];
        }
    }

    $lookup_url = "https://{$instance}/api/v1/accounts/lookup?acct=" . urlencode($username);
    $res = wp_remote_get($lookup_url, ['timeout' => 15]);
    if (is_wp_error($res)) return ['posts' => [], 'newest_timestamp' => $last_check];

    $account = json_decode(wp_remote_retrieve_body($res), true);
    $account_id = $account['id'] ?? null;
    if (!$account_id) return ['posts' => [], 'newest_timestamp' => $last_check];

    $exclude_param = $include_reposts ? 'false' : 'true';
    $statuses_url = "https://{$instance}/api/v1/accounts/{$account_id}/statuses?exclude_reblogs={$exclude_param}&limit=40";
    $res = wp_remote_get($statuses_url, ['timeout' => 20]);
    if (is_wp_error($res)) return ['posts' => [], 'newest_timestamp' => $last_check];

    $statuses = json_decode(wp_remote_retrieve_body($res), true);
    if (!is_array($statuses)) return ['posts' => [], 'newest_timestamp' => $last_check];

    $items = [];
    $newest_timestamp = $last_check;

    foreach ($statuses as $st) {
        if (!is_array($st)) continue;
        $created_at = !empty($st['created_at']) ? strtotime($st['created_at']) : time();
        if ($created_at <= $last_check) continue;
        if ($created_at > $newest_timestamp) $newest_timestamp = $created_at;

        $is_reblog = !empty($st['reblog']) && is_array($st['reblog']);
        $post_data = $is_reblog ? $st['reblog'] : $st;
        if (!is_array($post_data)) continue;

        if (!$is_reblog && !empty($post_data['in_reply_to_id'])) {
            if (!$keep_threads) continue;
            if (($post_data['in_reply_to_account_id'] ?? '') !== $account_id) continue;
        }

        $body_content = $post_data['content'] ?? '';
        $clean_text   = wp_strip_all_tags($body_content);
        $clean_handle = $username;
        $author_acct  = !empty($post_data['account']['acct']) ? $post_data['account']['acct'] : ($post_data['account']['username'] ?? $username);
        $full_acct    = (strpos($author_acct, '@') === false && !empty($instance)) ? "{$author_acct}@{$instance}" : $author_acct;
        $author_avatar = esc_url_raw($post_data['account']['avatar'] ?? $post_data['account']['avatar_static'] ?? '');
        $author_profile_url = esc_url_raw($post_data['account']['url'] ?? "https://{$instance}/@{$username}");
        $first_image_url = null;
        $media_html = '';

        $fav_count   = absint($post_data['favourites_count'] ?? $post_data['favorites_count'] ?? 0);
        $boost_count = absint($post_data['reblogs_count'] ?? 0);

        $extracted_urls = [];
        if (!empty($post_data['card']['url'])) $extracted_urls[] = $post_data['card']['url'];
        if (preg_match_all('/\b(?:https?:\/\/|www\.)[^\s<"\'\)]+/i', $clean_text, $u_m)) $extracted_urls = array_merge($extracted_urls, $u_m[0]);
        $extracted_urls = array_values(array_unique($extracted_urls));

        if (!empty($post_data['media_attachments']) && is_array($post_data['media_attachments'])) {
            $images = [];
            foreach ($post_data['media_attachments'] as $med) {
                if ($med['type'] === 'image') $images[] = $med;
            }
            if (!empty($images)) {
                $num_imgs = count($images);
                if ($num_imgs > 1) {
                    $cols = $num_imgs == 2 || $num_imgs == 4 ? 2 : 3;
                    $media_html .= '<div class="social-embed-media" style="display:grid; grid-template-columns: repeat(' . $cols . ', 1fr); gap:10px; margin:12px 0;">';
                } else {
                    $media_html .= '<div class="social-embed-media" style="margin:12px 0;">';
                }
                foreach ($images as $med) {
                    $img_url = esc_url($med['url'] ?? $med['preview_url'] ?? '');
                    if ($img_url) {
                        if (!$first_image_url) $first_image_url = $img_url;
                        if ($num_imgs > 1) {
                            $media_html .= '<figure style="margin:0;"><img src="' . $img_url . '" style="width:100%; aspect-ratio:1/1; object-fit:cover; border-radius:8px; display:block;" /></figure>';
                        } else {
                            $media_html .= '<figure style="margin:0;"><img src="' . $img_url . '" style="max-width:100%; max-height:400px; width:auto; border-radius:8px; display:block;" /></figure>';
                        }
                    }
                }
                $media_html .= '</div>';
            }
        }

        if (!empty($post_data['card']) && is_array($post_data['card'])) {
            $card       = $post_data['card'];
            $card_url   = esc_url($card['url'] ?? '');
            $card_title = esc_html($card['title'] ?? '');
            $card_desc  = esc_html($card['description'] ?? '');
            $card_thumb = esc_url($card['image'] ?? '');
            $card_prov  = esc_html($card['provider_name'] ?? '');

            if (!$card_thumb && $card_url) {
                $card_thumb = social_get_og_image_cached($card_url);
            }

            if (!$first_image_url && $card_thumb) {
                $first_image_url = $card_thumb;
            }

            if ($card_url && ($card_title || $card_thumb)) {
                $media_html .= '<div class="social-link-card" style="border:1px solid #e1e8ed; border-radius:8px; overflow:hidden; margin:12px 0; max-width:500px; background:#f8fafc;">';
                if ($card_thumb) {
                    $media_html .= '<img src="' . $card_thumb . '" alt="' . $card_title . '" style="width:100%; max-height:220px; object-fit:cover; display:block;" />';
                }
                $media_html .= '<div style="padding:10px 14px;">';
                if ($card_prov) {
                    $media_html .= '<div style="font-size:0.75em; text-transform:uppercase; color:#657786; margin-bottom:2px;">' . $card_prov . '</div>';
                }
                $media_html .= '<div style="font-weight:bold; font-size:1em; margin-bottom:4px;"><a href="' . $card_url . '" target="_blank" rel="noopener" style="color:#0085ff; text-decoration:none;">' . ($card_title ?: $card_url) . '</a></div>';
                if ($card_desc) {
                    $media_html .= '<div style="font-size:0.85em; color:#555; line-height:1.4;">' . wp_trim_words($card_desc, 25) . '</div>';
                }
                $media_html .= '</div></div>';
            }
        } elseif (!$first_image_url && !empty($extracted_urls[0])) {
            $fallback_thumb = social_get_og_image_cached($extracted_urls[0]);
            if ($fallback_thumb) {
                $first_image_url = $fallback_thumb;
            }
        }

        $author_name = esc_html(!empty($post_data['account']['display_name']) ? $post_data['account']['display_name'] : $post_data['account']['username']);
        $post_url    = esc_url($post_data['url'] ?? "https://{$instance}/@{$username}/{$post_data['id']}");

        $item_data = [
            'network'        => 'mastodon',
            'timestamp'      => $created_at,
            'text'           => $clean_text,
            'body_html'      => wp_kses_post($body_content),
            'author_name'    => $author_name,
            'author_handle'  => $full_acct,
            'author_avatar'  => $author_avatar,
            'media_html'     => $media_html,
            'thumb_image'    => $first_image_url,
            'is_repost'      => $is_reblog,
            'repost_user'    => $clean_handle,
            'platform_links' => [
                'mastodon' => [
                    'url'         => $post_url,
                    'profile_url' => $author_profile_url,
                    'handle'      => $full_acct,
                    'avatar'      => $author_avatar,
                    'favs'        => $fav_count,
                    'boosts'      => $boost_count,
                ]
            ],
            'extra_tags'     => [],
            'urls'           => array_values(array_unique($extracted_urls))
        ];

        $item_data['html'] = social_render_native_card($item_data, $opts);
        $items[] = $item_data;
    }

    return ['posts' => $items, 'newest_timestamp' => $newest_timestamp];
}

// ==========================================
// 7. STRING, IMAGE & HASHTAG HELPERS
// ==========================================

function social_get_og_image_cached($url) {
    if (empty($url) || !filter_var($url, FILTER_VALIDATE_URL)) return '';
    $transient_key = 'sd_og_img_' . md5($url);
    $cached = get_transient($transient_key);
    if ($cached !== false) return (string)$cached;

    $res = wp_remote_get($url, [
        'timeout'     => 3,
        'user-agent'  => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) WordPress-SocialDigest/1.0',
        'redirection' => 2,
    ]);

    if (is_wp_error($res)) {
        set_transient($transient_key, '', DAY_IN_SECONDS);
        return '';
    }

    $body = wp_remote_retrieve_body($res);
    if (empty($body)) {
        set_transient($transient_key, '', DAY_IN_SECONDS);
        return '';
    }

    $img_url = '';
    if (preg_match('/<meta[^>]+property=["\']og:image["\'][^>]+content=["\']([^"\']+)["\']/i', $body, $m)) {
        $img_url = $m[1];
    } elseif (preg_match('/<meta[^>]+content=["\']([^"\']+)["\'][^>]+property=["\']og:image["\']/i', $body, $m)) {
        $img_url = $m[1];
    } elseif (preg_match('/<meta[^>]+name=["\']twitter:image["\'][^>]+content=["\']([^"\']+)["\']/i', $body, $m)) {
        $img_url = $m[1];
    }

    $img_url = esc_url_raw(html_entity_decode($img_url));
    set_transient($transient_key, $img_url, DAY_IN_SECONDS);
    return $img_url;
}

function social_normalize_for_matching($text) {
    $t = wp_strip_all_tags($text);
    $t = preg_replace('/\b(?:https?:\/\/|www\.)\S+/i', '', $t);
    $t = preg_replace('/[#@]\w+/u', '', $t);
    $t = preg_replace('/[^\p{L}\p{N}\s]/u', '', html_entity_decode($t, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    return trim(preg_replace('/\s+/', ' ', mb_strtolower($t)));
}

function social_clean_url($url) {
    if (empty($url)) return '';
    $clean = strtok(html_entity_decode((string)$url, ENT_QUOTES | ENT_HTML5, 'UTF-8'), '?#');
    $clean = preg_replace('#^https?://#i', '', $clean);
    $clean = preg_replace('#^www\.#i', '', $clean);
    return strtolower(trim(preg_replace('#[\./]+$#', '', $clean)));
}

function social_post_links_to_site($item) {
    $site_host = wp_parse_url(home_url(), PHP_URL_HOST);
    if (empty($site_host)) {
        $site_host = wp_parse_url(site_url(), PHP_URL_HOST);
    }
    if (empty($site_host)) return false;
    $site_host = strtolower(preg_replace('#^www\.#i', '', (string)$site_host));
    if ($site_host === '') return false;

    // Check extracted structured URLs from facets/entities
    foreach ((array)($item['urls'] ?? []) as $u) {
        $u_host = wp_parse_url((string)$u, PHP_URL_HOST);
        if ($u_host) {
            $u_host = strtolower(preg_replace('#^www\.#i', '', (string)$u_host));
            if ($u_host === $site_host || str_ends_with($u_host, '.' . $site_host)) {
                return true;
            }
        }
    }

    // Also inspect any unparsed inline URLs in the post text
    $text = (string)($item['text'] ?? '');
    if ($text !== '' && preg_match_all('/\bhttps?:\/\/[^\s<>"\'\)]+/i', $text, $matches)) {
        foreach ($matches[0] as $match_url) {
            $u_host = wp_parse_url($match_url, PHP_URL_HOST);
            if ($u_host) {
                $u_host = strtolower(preg_replace('#^www\.#i', '', (string)$u_host));
                if ($u_host === $site_host || str_ends_with($u_host, '.' . $site_host)) {
                    return true;
                }
            }
        }
    }

    return false;
}

function social_check_posts_match($p1, $p2) {
    $urls1 = array_map(__NAMESPACE__ . '\\social_clean_url', (array)($p1['urls'] ?? []));
    $urls2 = array_map(__NAMESPACE__ . '\\social_clean_url', (array)($p2['urls'] ?? []));
    if (!empty(array_intersect(array_filter($urls1), array_filter($urls2)))) return true;

    $t1 = social_normalize_for_matching($p1['text'] ?? '');
    $t2 = social_normalize_for_matching($p2['text'] ?? '');
    $len1 = mb_strlen($t1, 'UTF-8');
    $len2 = mb_strlen($t2, 'UTF-8');

    if ($len1 >= 15 && $len2 >= 15) {
        $short = $len1 <= $len2 ? $t1 : $t2;
        $long  = $len1 <= $len2 ? $t2 : $t1;
        if (mb_strpos($long, $short) !== false) return true;
        similar_text($t1, $t2, $sim);
        if ($sim >= 68) return true;
    }
    return false;
}

function social_get_clean_text_length($text) {
    $t = preg_replace('/\bhttps?:\/\/\S+/i', '', wp_strip_all_tags($text));
    $t = preg_replace('/#[\p{L}\p{N}_]+/u', '', $t);
    return mb_strlen(trim(preg_replace('/\s+/', ' ', html_entity_decode($t, ENT_QUOTES | ENT_HTML5, 'UTF-8'))), 'UTF-8');
}

function social_sideload_image_by_mime($url, $post_id, $desc = '') {
    if (empty($url)) return false;

    // Temporary memory elevation for GD/Imagick thumbnail processing
    if (function_exists('wp_raise_memory_limit')) {
        wp_raise_memory_limit('image');
    }

    // Guard: Prevent downloading massive non-thumbnail images (cap at 12MB)
    $response = wp_safe_remote_get($url, [
        'timeout' => 25,
        'stream'  => false,
    ]);
    if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) return false;
    
    $headers = wp_remote_retrieve_headers($response);
    $content_length = (int)($headers['content-length'] ?? 0);
    if ($content_length > 12 * 1024 * 1024) {
        return false; // Skip excessive remote assets
    }

    $image_data = wp_remote_retrieve_body($response);
    if (empty($image_data) || strlen($image_data) > 12 * 1024 * 1024) return false;

    $filename = 'digest-thumb-' . $post_id . '-' . wp_generate_password(6, false) . '.jpg';
    $upload = wp_upload_bits($filename, null, $image_data);
    if (!empty($upload['error'])) return false;

    $attach_id = wp_insert_attachment([
        'post_mime_type' => 'image/jpeg',
        'post_title'     => sanitize_file_name($desc ?: $filename),
        'post_status'    => 'inherit'
    ], $upload['file'], $post_id);

    if ($attach_id && !is_wp_error($attach_id)) {
        require_once(ABSPATH . 'wp-admin/includes/image.php');

        // CPU & Memory Mitigation: Limit thumbnail resizing to essential standard sizes
        $size_limiter = function($sizes) {
            $allowed = ['thumbnail', 'medium', 'medium_large', 'large', 'post-thumbnail'];
            return array_intersect_key((array)$sizes, array_flip($allowed));
        };
        add_filter('intermediate_image_sizes_advanced', $size_limiter, 99);

        $metadata = wp_generate_attachment_metadata($attach_id, $upload['file']);
        remove_filter('intermediate_image_sizes_advanced', $size_limiter, 99);

        if (!empty($metadata) && !is_wp_error($metadata)) {
            wp_update_attachment_metadata($attach_id, $metadata);
        }
        return $attach_id;
    }
    return false;
}

function social_split_camelcase_tag($tag) {
    $t = ltrim(trim($tag), '#');
    $t = preg_replace('/([a-z]{2,})([A-Z0-9])/u', '$1 $2', $t);
    return trim(preg_replace('/([A-Z]+)([A-Z][a-z])/u', '$1 $2', $t));
}

function social_rank_and_format_title_tags($candidate_tags, $default_tags_str, $enclosure = 'parentheses', $delimiter = 'oxford') {
    $selected_tags = [];
    $seen_normalized = [];

    if (!empty($candidate_tags)) {
        $is_grouped = false;
        foreach ($candidate_tags as $elem) {
            if (is_array($elem)) {
                $is_grouped = true;
                break;
            }
        }

        if ($is_grouped) {
            // Select at most 1 distinct tag per post
            foreach ($candidate_tags as $post_tags) {
                if (!is_array($post_tags)) {
                    $post_tags = [$post_tags];
                }
                foreach ($post_tags as $tag) {
                    $clean_tag = social_split_camelcase_tag($tag);
                    $norm = mb_strtolower(trim($clean_tag));
                    if ($norm !== '' && !isset($seen_normalized[$norm])) {
                        $seen_normalized[$norm] = true;
                        $selected_tags[] = $clean_tag;
                        break; // Pick only 1 tag from this post, then move to the next post
                    }
                }
                if (count($selected_tags) >= 3) {
                    break;
                }
            }
        } else {
            // Flat array fallback
            foreach ($candidate_tags as $tag) {
                $clean_tag = social_split_camelcase_tag($tag);
                $norm = mb_strtolower(trim($clean_tag));
                if ($norm !== '' && !isset($seen_normalized[$norm])) {
                    $seen_normalized[$norm] = true;
                    $selected_tags[] = $clean_tag;
                }
                if (count($selected_tags) >= 3) {
                    break;
                }
            }
        }
    }

    // Fall back to default tags if no post hashtags were found
    if (empty($selected_tags)) {
        $defaults = array_filter(array_map('trim', explode(',', $default_tags_str)));
        foreach ($defaults as $d) {
            $clean_tag = social_split_camelcase_tag($d);
            $norm = mb_strtolower(trim($clean_tag));
            if ($norm !== '' && !isset($seen_normalized[$norm])) {
                $seen_normalized[$norm] = true;
                $selected_tags[] = $clean_tag;
            }
            if (count($selected_tags) >= 3) {
                break;
            }
        }
    }

    $c = count($selected_tags);
    if ($c === 0) return '';

    $joined = '';
    switch ($delimiter) {
        case 'commas':
            $joined = implode(', ', $selected_tags);
            break;
        case 'ampersand':
            if ($c === 1) $joined = $selected_tags[0];
            elseif ($c === 2) $joined = $selected_tags[0] . ' & ' . $selected_tags[1];
            else $joined = $selected_tags[0] . ', ' . $selected_tags[1] . ' & ' . $selected_tags[2];
            break;
        case 'pipe':
            $joined = implode(' | ', $selected_tags);
            break;
        case 'slash':
            $joined = implode(' / ', $selected_tags);
            break;
        case 'oxford':
        default:
            if ($c === 1) $joined = $selected_tags[0];
            elseif ($c === 2) $joined = $selected_tags[0] . ' and ' . $selected_tags[1];
            else $joined = $selected_tags[0] . ', ' . $selected_tags[1] . ', and ' . $selected_tags[2];
            break;
    }

    if ($enclosure === 'brackets') {
        return '[' . $joined . ']';
    } elseif ($enclosure === 'none') {
        return $joined;
    }

    return '(' . $joined . ')';
}

function social_log_run($success, $message, $post_id = 0) {
    $logs = get_option('social_digest_logs', []);
    $logs[] = ['time' => time(), 'success' => (bool)$success, 'message' => sanitize_text_field($message), 'post_id' => (int)$post_id];
    update_option('social_digest_logs', array_slice($logs, -10));
}

// ==========================================
// 8. SCHEDULED RUNNER & DRY-RUN SIMULATOR
// ==========================================

function social_run_digest_import($is_dry_run = false) {
    $state_res = social_fetch_workbench_candidates();
    if (empty($state_res['success'])) return $state_res;
    return social_publish_workbench_run($state_res['state']);
}
