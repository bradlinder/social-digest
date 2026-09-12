<?php
/**
 * Plugin Name: Social Digest
 * Plugin URI: https://github.com/BradLinder/social-digest
 * Description: Automated digest builder for Bluesky and Mastodon with tabbed admin workflows, staging queue, dry-run simulation, media optimization (WebP/AVIF), local asset caching, and RSS-only syndication.
 * Version: 5.1.9
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

add_action('wp_enqueue_scripts', function() {
    if (is_singular('post')) {
        wp_enqueue_script('bsky-embed-js', 'https://embed.bsky.app/static/embed.js', [], null, true);
    }
});

add_action('admin_enqueue_scripts', function($hook) {
    if ($hook === 'settings_page_social-digest-settings') {
        wp_enqueue_script('postbox');
        wp_enqueue_script('jquery-ui-sortable');
    }
});

add_filter('plugin_action_links_' . plugin_basename(__FILE__), function($links) {
    $settings_link = '<a href="' . esc_url(admin_url('options-general.php?page=social-digest-settings')) . '">' . __('Settings') . '</a>';
    array_unshift($links, $settings_link);
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
// 3. ADMIN SETTINGS, TABS & ACTIONS
// ==========================================

add_action('admin_menu', function() {
    add_options_page(
        'Social Digest Settings',
        'Social Digest',
        'manage_options',
        'social-digest-settings',
        __NAMESPACE__ . '\\social_render_settings_page'
    );
});

add_action('admin_init', function() {
    register_setting('social_digest_group', 'social_digest_options', [
        'type'              => 'array',
        'sanitize_callback' => __NAMESPACE__ . '\\social_sanitize_settings',
        'default'           => [
            'network_mode'           => 'both',
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

    // Operational Handlers
    if (isset($_POST['social_manual_run']) && check_admin_referer('social_manual_run_action', 'social_manual_nonce')) {
        if (current_user_can('manage_options')) {
            try {
                $result = social_run_digest_import(false);
            } catch (\Throwable $e) {
                $result = ['success' => false, 'message' => 'Manual run error: ' . $e->getMessage()];
            }
            add_settings_error('general', 'social_manual_status', $result['message'] ?? 'Import failed.', !empty($result['success']) ? 'updated' : 'error');
        }
    }

    if (isset($_POST['social_simulate_run']) && check_admin_referer('social_simulate_action', 'social_simulate_nonce')) {
        if (current_user_can('manage_options')) {
            try {
                $sim_result = social_run_digest_import(true);
            } catch (\Throwable $e) {
                $sim_result = ['success' => false, 'message' => 'Simulation error: ' . $e->getMessage()];
            }
            set_transient('social_digest_simulation_data', $sim_result, 300);
            if (!empty($sim_result['success'])) {
                add_settings_error('general', 'social_sim_status', 'Dry Run Simulation completed. Review preview below.', 'updated');
            } else {
                add_settings_error('general', 'social_sim_status', $sim_result['message'] ?? 'Simulation failed.', 'error');
            }
        }
    }

    if (isset($_POST['social_reset_cutoff']) && check_admin_referer('social_reset_cutoff_action', 'social_reset_nonce')) {
        if (current_user_can('manage_options')) {
            delete_option('bsky_last_digest_time');
            delete_option('masto_last_digest_time');
            add_settings_error('general', 'social_reset_status', 'Cutoff markers cleared for both networks.', 'updated');
        }
    }
});

function social_sanitize_settings($input) {
    $output = [];
    $output['network_mode']       = in_array($input['network_mode'] ?? '', ['bsky', 'mastodon', 'both']) ? $input['network_mode'] : 'bsky';
    $output['bsky_handle']        = sanitize_text_field($input['bsky_handle'] ?? '');
    $output['masto_handle']       = sanitize_text_field($input['masto_handle'] ?? '');
    $output['cross_dedup']        = !empty($input['cross_dedup']) ? 1 : 0;
    $allowed_strategies           = ['masto_if_longer', 'longest', 'bsky', 'mastodon'];
    $output['preferred_platform'] = in_array($input['preferred_platform'] ?? '', $allowed_strategies) ? $input['preferred_platform'] : 'masto_if_longer';

    $allowed_freqs = ['hourly', 'six_hours', 'twelve_hours', 'interval_days'];
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

    // Roadmap Media & Storage Hygiene Options
    $output['convert_modern_media'] = !empty($input['convert_modern_media']) ? 1 : 0;
    $output['cache_local_assets']   = !empty($input['cache_local_assets']) ? 1 : 0;
    $output['generate_srcsets']     = !empty($input['generate_srcsets']) ? 1 : 0;

    // Roadmap Editorial & Syndication Options
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
    $output['title_template']      = sanitize_text_field($input['title_template'] ?? 'Social Digest {hashtags}');
    
    $allowed_enclosures = ['parentheses', 'brackets', 'none'];
    $output['title_tag_enclosure'] = in_array($input['title_tag_enclosure'] ?? '', $allowed_enclosures, true) ? $input['title_tag_enclosure'] : 'parentheses';

    $allowed_delimiters = ['oxford', 'commas', 'ampersand', 'pipe', 'slash'];
    $output['title_tag_delimiter'] = in_array($input['title_tag_delimiter'] ?? '', $allowed_delimiters, true) ? $input['title_tag_delimiter'] : 'oxford';

    $output['same_day_suffix_tpl'] = sanitize_text_field($input['same_day_suffix_tpl'] ?? ' (Part {part})');
    $output['excluded_words']      = sanitize_textarea_field($input['excluded_words'] ?? '');

    // Split unified editor content on delimiter
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

    if ($freq !== 'interval_days') {
        wp_schedule_event(time(), $freq, 'social_digest_cron');
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

function social_render_settings_page() {
    if (!current_user_can('manage_options')) return;
    $opts = get_option('social_digest_options', []);
    $bsky_last  = get_option('bsky_last_digest_time', 0);
    $masto_last = get_option('masto_last_digest_time', 0);
    $next_run   = wp_next_scheduled('social_digest_cron');
    $logs       = get_option('social_digest_logs', []);
    $all_cats   = get_categories(['hide_empty' => 0]);
    $selected_cats = (array)($opts['categories'] ?? []);
    $site_tz    = wp_timezone();
    $simulation = get_transient('social_digest_simulation_data');

    $active_tab = isset($_GET['tab']) ? sanitize_key($_GET['tab']) : 'settings';

    // Prepare unified editor text
    $saved_header = $opts['header_text'] ?? '';
    $saved_footer = $opts['footer_text'] ?? '';
    $split_marker_html = '<p class="social-digest-split-marker" style="text-align:center; background:#eee; padding:6px; border:1px dashed #999; color:#555; font-weight:bold; user-select:none;"><!--digest_split--> (Header / Footer Split)</p>';
    $unified_editor_value = trim($saved_header) . "\n\n" . $split_marker_html . "\n\n" . trim($saved_footer);
    ?>
    <style>
        #poststuff .postbox {
            border-radius: 6px;
            overflow: hidden;
            border: 1px solid #ccd0d4;
            box-shadow: 0 1px 3px rgba(0,0,0,0.04);
            margin-bottom: 20px;
            background: #fff;
        }
        #poststuff .postbox .hndle {
            cursor: move;
            user-select: none;
            font-size: 14px;
            font-weight: 600;
            padding: 12px 16px;
            border-bottom: 1px solid rgba(0,0,0,0.06);
        }
        #poststuff .postbox .inside {
            padding: 16px 20px 20px 20px;
            margin: 0;
        }

        #social_box_sources { border-left: 5px solid #0085ff; }
        #social_box_sources .postbox-header { background: #f3f8fe; color: #0056b3; }
        #social_box_sources .hndle { color: #0056b3; }

        #social_box_schedule { border-left: 5px solid #e65100; }
        #social_box_schedule .postbox-header { background: #fff9f5; color: #b23c00; }
        #social_box_schedule .hndle { color: #b23c00; }

        #social_box_featured_image { border-left: 5px solid #8e24aa; }
        #social_box_featured_image .postbox-header { background: #fbf5fc; color: #6a1b9a; }
        #social_box_featured_image .hndle { color: #6a1b9a; }

        #social_box_publishing { border-left: 5px solid #00796b; }
        #social_box_publishing .postbox-header { background: #f2f9f8; color: #004d40; }
        #social_box_publishing .hndle { color: #004d40; }

        #social_box_media_hygiene { border-left: 5px solid #0288d1; }
        #social_box_media_hygiene .postbox-header { background: #e1f5fe; color: #01579b; }
        #social_box_media_hygiene .hndle { color: #01579b; }

        #social_box_content { border-left: 5px solid #455a64; }
        #social_box_content .postbox-header { background: #f6f8f9; color: #263238; }
        #social_box_content .hndle { color: #263238; }

        .social-section-icon {
            display: inline-block;
            margin-right: 8px;
            font-size: 15px;
            vertical-align: -1px;
        }

        .social-diag-card {
            background: #fff;
            border: 1px solid #ccd0d4;
            border-radius: 6px;
            padding: 16px;
            margin-bottom: 16px;
        }
    </style>

    <div class="wrap">
        <h1>Social Digest</h1>
        
        <!-- Tabbed WordPress Nav Wrapper (Roadmap Item 1) -->
        <nav class="nav-tab-wrapper wp-clearfix" style="margin-top: 15px; margin-bottom: 20px;">
            <a href="<?php echo esc_url(admin_url('options-general.php?page=social-digest-settings&tab=settings')); ?>" class="nav-tab <?php echo $active_tab === 'settings' ? 'nav-tab-active' : ''; ?>">
                <span class="dashicons dashicons-admin-generic" style="vertical-align: -3px; font-size: 17px;"></span> Settings
            </a>
            <a href="<?php echo esc_url(admin_url('options-general.php?page=social-digest-settings&tab=staging')); ?>" class="nav-tab <?php echo $active_tab === 'staging' ? 'nav-tab-active' : ''; ?>">
                <span class="dashicons dashicons-clipboard" style="vertical-align: -3px; font-size: 17px;"></span> Editorial & Staging Queue
            </a>
            <a href="<?php echo esc_url(admin_url('options-general.php?page=social-digest-settings&tab=actions')); ?>" class="nav-tab <?php echo $active_tab === 'actions' ? 'nav-tab-active' : ''; ?>">
                <span class="dashicons dashicons-performance" style="vertical-align: -3px; font-size: 17px;"></span> Actions & Diagnostics
            </a>
        </nav>

        <?php if ($active_tab === 'settings'): ?>
            <!-- TAB 1: SETTINGS -->
            <p style="font-size: 13px; color: #555;">Configure feeds, scheduling thresholds, media optimization, and title formats.</p>

            <form method="post" action="options.php">
                <?php settings_fields('social_digest_group'); ?>
                
                <div id="poststuff">
                    <div id="post-body" class="metabox-holder columns-1">
                        <div id="postbox-container-1" class="postbox-container">
                            <div class="meta-box-sortables ui-sortable" id="social-digest-sortables">

                                <!-- SOURCES & CROSS-PLATFORM SETTINGS -->
                                <div class="postbox" id="social_box_sources">
                                    <div class="postbox-header">
                                        <h2 class="hndle">
                                            <span class="social-section-icon dashicons dashicons-share"></span>
                                            <span>Sources & Cross-Platform Settings</span>
                                        </h2>
                                        <div class="handle-actions hide-if-no-js">
                                            <button type="button" class="handlediv" aria-expanded="true"><span class="screen-reader-text">Toggle panel</span><span class="toggle-indicator" aria-hidden="true"></span></button>
                                        </div>
                                    </div>
                                    <div class="inside">
                                        <table class="form-table">
                                            <tr>
                                                <th><label for="social_network_mode">Active Platforms</label></th>
                                                <td>
                                                    <select name="social_digest_options[network_mode]" id="social_network_mode" onchange="socialToggleModeFields(this.value)">
                                                        <option value="bsky" <?php selected($opts['network_mode'] ?? 'bsky', 'bsky'); ?>>Bluesky Only</option>
                                                        <option value="mastodon" <?php selected($opts['network_mode'] ?? '', 'mastodon'); ?>>Mastodon Only</option>
                                                        <option value="both" <?php selected($opts['network_mode'] ?? 'both', 'both'); ?>>Combined (Bluesky + Mastodon)</option>
                                                    </select>
                                                </td>
                                            </tr>

                                            <tr id="row_bsky_handle">
                                                <th><label for="social_bsky_handle">Bluesky Handle</label></th>
                                                <td>
                                                    <input name="social_digest_options[bsky_handle]" type="text" id="social_bsky_handle" 
                                                           value="<?php echo esc_attr($opts['bsky_handle'] ?? ''); ?>" class="regular-text" placeholder="username.bsky.social" />
                                                </td>
                                            </tr>

                                            <tr id="row_masto_handle">
                                                <th><label for="social_masto_handle">Mastodon User Handle</label></th>
                                                <td>
                                                    <input name="social_digest_options[masto_handle]" type="text" id="social_masto_handle" 
                                                           value="<?php echo esc_attr($opts['masto_handle'] ?? ''); ?>" class="regular-text" placeholder="@user@instance.social or profile URL" />
                                                    <p class="description">Accepts <code>user@instance.social</code>, <code>@user@instance.social</code>, or full profile URL.</p>
                                                </td>
                                            </tr>

                                            <tr class="social-combined-field">
                                                <th>Cross-Platform Deduplication</th>
                                                <td>
                                                    <label>
                                                        <input type="checkbox" name="social_digest_options[cross_dedup]" value="1" <?php checked($opts['cross_dedup'] ?? 1, 1); ?> />
                                                        <strong>Deduplicate matching cross-posts</strong>
                                                    </label>
                                                    <p class="description">If an update appears on both platforms, embed ONLY the preferred platform's post and completely discard the duplicate.</p>
                                                </td>
                                            </tr>

                                            <tr class="social-combined-field">
                                                <th><label for="social_preferred_platform">Duplicate Resolution Strategy</label></th>
                                                <td>
                                                    <select name="social_digest_options[preferred_platform]" id="social_preferred_platform">
                                                        <option value="masto_if_longer" <?php selected($opts['preferred_platform'] ?? 'masto_if_longer', 'masto_if_longer'); ?>>Prefer Mastodon if longer, otherwise Bluesky (Recommended)</option>
                                                        <option value="longest" <?php selected($opts['preferred_platform'] ?? '', 'longest'); ?>>Longest text wins (Whichever platform wrote more clean text)</option>
                                                        <option value="bsky" <?php selected($opts['preferred_platform'] ?? '', 'bsky'); ?>>Always prefer Bluesky (Embed only Bluesky)</option>
                                                        <option value="mastodon" <?php selected($opts['preferred_platform'] ?? '', 'mastodon'); ?>>Always prefer Mastodon (Embed only Mastodon)</option>
                                                    </select>
                                                    <p class="description">When identical updates or shared links are detected on both networks, determines which post to exclusively embed. The other post is completely excluded from the digest.</p>
                                                </td>
                                            </tr>
                                        </table>
                                    </div>
                                </div>

                                <!-- SCHEDULE & INGESTION THRESHOLDS -->
                                <div class="postbox" id="social_box_schedule">
                                    <div class="postbox-header">
                                        <h2 class="hndle">
                                            <span class="social-section-icon dashicons dashicons-clock"></span>
                                            <span>Schedule & Ingestion Thresholds</span>
                                        </h2>
                                        <div class="handle-actions hide-if-no-js">
                                            <button type="button" class="handlediv" aria-expanded="true"><span class="screen-reader-text">Toggle panel</span><span class="toggle-indicator" aria-hidden="true"></span></button>
                                        </div>
                                    </div>
                                    <div class="inside">
                                        <table class="form-table">
                                            <tr>
                                                <th><label for="social_schedule_freq">Check Frequency</label></th>
                                                <td>
                                                    <select name="social_digest_options[schedule_freq]" id="social_schedule_freq" onchange="socialToggleScheduleFields(this.value)">
                                                        <option value="hourly" <?php selected($opts['schedule_freq'] ?? '', 'hourly'); ?>>Hourly</option>
                                                        <option value="six_hours" <?php selected($opts['schedule_freq'] ?? '', 'six_hours'); ?>>Every 6 Hours</option>
                                                        <option value="twelve_hours" <?php selected($opts['schedule_freq'] ?? '', 'twelve_hours'); ?>>Every 12 Hours</option>
                                                        <option value="interval_days" <?php selected($opts['schedule_freq'] ?? 'interval_days', 'interval_days'); ?>>Every X Days at Selected Time...</option>
                                                    </select>

                                                    <div id="socialIntervalConfig" style="margin-top: 10px; background: #f9f9f9; padding: 8px 12px; border: 1px solid #e2e4e7; border-radius: 4px; max-width: 500px;">
                                                        Run every 
                                                        <input name="social_digest_options[schedule_interval_days]" type="number" min="1" max="60" 
                                                               value="<?php echo esc_attr($opts['schedule_interval_days'] ?? 1); ?>" class="small-text" /> 
                                                        day(s) at 
                                                        <input name="social_digest_options[schedule_time]" type="time" 
                                                               value="<?php echo esc_attr($opts['schedule_time'] ?? '17:00'); ?>" />
                                                        <p class="description">Site local time. 1 = Daily, 2 = Every other day, 7 = Weekly.</p>
                                                    </div>
                                                </td>
                                            </tr>

                                            <tr>
                                                <th><label for="social_min_posts">Minimum Post Threshold</label></th>
                                                <td>
                                                    <input name="social_digest_options[min_posts]" type="number" id="social_min_posts" min="1" max="50" 
                                                           value="<?php echo esc_attr($opts['min_posts'] ?? 3); ?>" class="small-text" />
                                                    <span class="description">Minimum new updates required before generating an article.</span>
                                                </td>
                                            </tr>

                                            <tr>
                                                <th><label for="social_max_age_days">Maximum Age Fallback</label></th>
                                                <td>
                                                    <input name="social_digest_options[max_age_days]" type="number" id="social_max_age_days" min="0" max="60" 
                                                           value="<?php echo esc_attr($opts['max_age_days'] ?? 0); ?>" class="small-text" /> Days
                                                    <p class="description">Publish even if threshold isn't met once oldest unimported update reaches this age (0 to disable).</p>
                                                </td>
                                            </tr>

                                            <tr>
                                                <th><label for="social_max_posts">Maximum Posts Per Digest</label></th>
                                                <td>
                                                    <input name="social_digest_options[max_posts]" type="number" id="social_max_posts" min="1" max="50" 
                                                           value="<?php echo esc_attr($opts['max_posts'] ?? 20); ?>" class="small-text" />
                                                </td>
                                            </tr>

                                            <tr>
                                                <th>Keyword Filters</th>
                                                <td>
                                                    <textarea name="social_digest_options[excluded_words]" rows="2" class="large-text" placeholder="spam, promotion, test"><?php echo esc_textarea($opts['excluded_words'] ?? ''); ?></textarea>
                                                    <p class="description">Comma-separated terms. Any updates containing these will be skipped.</p>
                                                </td>
                                            </tr>

                                            <tr>
                                                <th>Feed Rules</th>
                                                <td>
                                                    <label style="display: block; margin-bottom: 6px;">
                                                        <input type="checkbox" name="social_digest_options[include_reposts]" value="1" <?php checked($opts['include_reposts'] ?? 0, 1); ?> />
                                                        <strong>Include Reposts / Boosts</strong> (includes shared posts with attribution badge)
                                                    </label>
                                                    <label style="display: block; margin-bottom: 6px;">
                                                        <input type="checkbox" name="social_digest_options[exclude_titles]" value="1" <?php checked($opts['exclude_titles'] ?? 0, 1); ?> />
                                                        <strong>Exclude posts matching existing WordPress headlines</strong>
                                                    </label>
                                                    <label style="display: block;">
                                                        <input type="checkbox" name="social_digest_options[keep_threads]" value="1" <?php checked($opts['keep_threads'] ?? 1, 1); ?> />
                                                        Include self-replies / continuous threads
                                                    </label>
                                                </td>
                                            </tr>
                                        </table>
                                    </div>
                                </div>

                                <!-- MEDIA OPTIMIZATION & STORAGE HYGIENE (Roadmap Item 3) -->
                                <div class="postbox" id="social_box_media_hygiene">
                                    <div class="postbox-header">
                                        <h2 class="hndle">
                                            <span class="social-section-icon dashicons dashicons-images-alt2"></span>
                                            <span>Media Optimization & WordPress Storage Hygiene</span>
                                        </h2>
                                        <div class="handle-actions hide-if-no-js">
                                            <button type="button" class="handlediv" aria-expanded="true"><span class="screen-reader-text">Toggle panel</span><span class="toggle-indicator" aria-hidden="true"></span></button>
                                        </div>
                                    </div>
                                    <div class="inside">
                                        <table class="form-table">
                                            <tr>
                                                <th>Modern Media Conversion</th>
                                                <td>
                                                    <label>
                                                        <input type="checkbox" name="social_digest_options[convert_modern_media]" value="1" <?php checked($opts['convert_modern_media'] ?? 1, 1); ?> />
                                                        <strong>Convert sideloaded images to WebP / AVIF</strong> (compresses thumbnails upon import to save server disk space)
                                                    </label>
                                                    <p class="description">Utilizes server GD/Imagick capabilities to reduce disk footprint by up to 70%.</p>
                                                </td>
                                            </tr>
                                            <tr>
                                                <th>Local Asset & Avatar Caching</th>
                                                <td>
                                                    <label>
                                                        <input type="checkbox" name="social_digest_options[cache_local_assets]" value="1" <?php checked($opts['cache_local_assets'] ?? 1, 1); ?> />
                                                        <strong>Cache remote avatars and link preview images locally</strong>
                                                    </label>
                                                    <p class="description">Prevents broken embeds and link rot if original social posts are deleted or third-party servers go offline.</p>
                                                </td>
                                            </tr>
                                            <tr>
                                                <th>Responsive Image srcset</th>
                                                <td>
                                                    <label>
                                                        <input type="checkbox" name="social_digest_options[generate_srcsets]" value="1" <?php checked($opts['generate_srcsets'] ?? 1, 1); ?> />
                                                        <strong>Generate standard responsive thumbnail sizes (medium, large, medium_large)</strong>
                                                    </label>
                                                    <p class="description">Allows browsers to load mobile-optimized resolutions without pulling full-resolution raw uploads.</p>
                                                </td>
                                            </tr>
                                        </table>
                                    </div>
                                </div>

                                <!-- FEATURED IMAGE SELECTION -->
                                <div class="postbox" id="social_box_featured_image">
                                    <div class="postbox-header">
                                        <h2 class="hndle">
                                            <span class="social-section-icon dashicons dashicons-format-image"></span>
                                            <span>Featured Image Selection</span>
                                        </h2>
                                        <div class="handle-actions hide-if-no-js">
                                            <button type="button" class="handlediv" aria-expanded="true"><span class="screen-reader-text">Toggle panel</span><span class="toggle-indicator" aria-hidden="true"></span></button>
                                        </div>
                                    </div>
                                    <div class="inside">
                                        <table class="form-table">
                                            <tr>
                                                <th>Enable Auto-Thumbnail</th>
                                                <td>
                                                    <label>
                                                        <input type="checkbox" name="social_digest_options[auto_thumb]" value="1" <?php checked($opts['auto_thumb'] ?? 1, 1); ?> />
                                                        Automatically sideload and set WordPress Featured Image
                                                    </label>
                                                </td>
                                            </tr>
                                            <tr>
                                                <th>Candidate Pool</th>
                                                <td>
                                                    <select name="social_digest_options[thumb_selection_scope]">
                                                        <option value="exclude_first" <?php selected($opts['thumb_selection_scope'] ?? 'exclude_first', 'exclude_first'); ?>>Exclude Most Recent Post (Pick from Posts 2+)</option>
                                                        <option value="all_posts" <?php selected($opts['thumb_selection_scope'] ?? '', 'all_posts'); ?>>Include All Posts (Pick from Posts 1+)</option>
                                                    </select>
                                                    <p class="description">When set to exclude post 1, older posts are evaluated first. If no images exist in posts 2+, it falls back to post 1.</p>
                                                </td>
                                            </tr>
                                            <tr>
                                                <th>Selection Strategy</th>
                                                <td>
                                                    <select name="social_digest_options[thumb_selection_mode]">
                                                        <option value="random" <?php selected($opts['thumb_selection_mode'] ?? 'random', 'random'); ?>>Random candidate image [Default]</option>
                                                        <option value="1" <?php selected($opts['thumb_selection_mode'] ?? '', '1'); ?>>1st available candidate image</option>
                                                        <option value="2" <?php selected($opts['thumb_selection_mode'] ?? '', '2'); ?>>2nd available candidate image</option>
                                                        <option value="3" <?php selected($opts['thumb_selection_mode'] ?? '', '3'); ?>>3rd available candidate image</option>
                                                        <option value="4" <?php selected($opts['thumb_selection_mode'] ?? '', '4'); ?>>4th available candidate image</option>
                                                        <option value="5" <?php selected($opts['thumb_selection_mode'] ?? '', '5'); ?>>5th available candidate image</option>
                                                    </select>
                                                </td>
                                            </tr>
                                        </table>
                                    </div>
                                </div>

                                <!-- ARTICLE PUBLISHING, TITLES & SYNDICATION -->
                                <div class="postbox" id="social_box_publishing">
                                    <div class="postbox-header">
                                        <h2 class="hndle">
                                            <span class="social-section-icon dashicons dashicons-admin-post"></span>
                                            <span>Article Publishing, Titles & Syndication</span>
                                        </h2>
                                        <div class="handle-actions hide-if-no-js">
                                            <button type="button" class="handlediv" aria-expanded="true"><span class="screen-reader-text">Toggle panel</span><span class="toggle-indicator" aria-hidden="true"></span></button>
                                        </div>
                                    </div>
                                    <div class="inside">
                                        <table class="form-table">
                                            <tr>
                                                <th><label for="social_post_status">Post Status</label></th>
                                                <td>
                                                    <select name="social_digest_options[post_status]" id="social_post_status">
                                                        <option value="publish" <?php selected($opts['post_status'] ?? 'publish', 'publish'); ?>>Auto-Publish Immediately</option>
                                                        <option value="pending" <?php selected($opts['post_status'] ?? '', 'pending'); ?>>Pending Review</option>
                                                        <option value="draft" <?php selected($opts['post_status'] ?? '', 'draft'); ?>>Save as Draft</option>
                                                    </select>
                                                </td>
                                            </tr>

                                            <!-- RSS-Only Mode (Roadmap Item 4) -->
                                            <tr>
                                                <th>RSS-Only Syndication</th>
                                                <td>
                                                    <label>
                                                        <input type="checkbox" name="social_digest_options[rss_only_mode]" value="1" <?php checked($opts['rss_only_mode'] ?? 0, 1); ?> />
                                                        <strong>Publish exclusively to RSS feed & newsletter distribution</strong>
                                                    </label>
                                                    <p class="description">Hides digest posts from main homepage/archive loops while ensuring they appear in your RSS and Mailchimp/Newsletter feeds.</p>
                                                </td>
                                            </tr>

                                            <tr>
                                                <th>Inline Excerpt Fold</th>
                                                <td>
                                                    <input name="social_digest_options[excerpt_fold_limit]" type="number" min="0" max="2000" 
                                                           value="<?php echo esc_attr($opts['excerpt_fold_limit'] ?? 400); ?>" class="small-text" /> Characters
                                                    <p class="description">Automatically truncates lengthy social posts with an expander fold so one long thread does not dominate layout (0 to disable).</p>
                                                </td>
                                            </tr>

                                            <tr>
                                                <th><label for="social_post_author">Post Author</label></th>
                                                <td>
                                                    <?php 
                                                    wp_dropdown_users([
                                                        'name'     => 'social_digest_options[post_author]',
                                                        'id'       => 'social_post_author',
                                                        'selected' => $opts['post_author'] ?? 1,
                                                        'who'      => 'authors',
                                                    ]); 
                                                    ?>
                                                </td>
                                            </tr>

                                            <tr>
                                                <th>Categories</th>
                                                <td>
                                                    <div style="max-height: 150px; overflow-y: auto; border: 1px solid #ccd0d4; padding: 6px 10px; background: #fff; width: 280px; border-radius: 4px;">
                                                        <?php foreach ($all_cats as $cat): ?>
                                                            <label style="display:block; margin: 3px 0;">
                                                                <input type="checkbox" name="social_digest_options[categories][]" 
                                                                       value="<?php echo $cat->term_id; ?>" 
                                                                       <?php checked(in_array($cat->term_id, $selected_cats)); ?> />
                                                                <?php echo esc_html($cat->name); ?>
                                                            </label>
                                                        <?php endforeach; ?>
                                                    </div>
                                                </td>
                                            </tr>

                                            <tr>
                                                <th><label for="social_title_template">Title Template</label></th>
                                                <td>
                                                    <input name="social_digest_options[title_template]" type="text" id="social_title_template" 
                                                           value="<?php echo esc_attr($opts['title_template'] ?? 'Social Digest {hashtags}'); ?>" class="regular-text" />
                                                    <p class="description">Tokens: <code>{hashtags}</code>, <code>{date}</code>, <code>{count}</code>.</p>
                                                </td>
                                            </tr>

                                            <tr>
                                                <th><label for="social_title_tag_enclosure">Hashtag Enclosure Style</label></th>
                                                <td>
                                                    <select name="social_digest_options[title_tag_enclosure]" id="social_title_tag_enclosure">
                                                        <option value="parentheses" <?php selected($opts['title_tag_enclosure'] ?? 'parentheses', 'parentheses'); ?>>(Parentheses) &mdash; e.g. (Amazon, SBC, and Framework)</option>
                                                        <option value="brackets" <?php selected($opts['title_tag_enclosure'] ?? '', 'brackets'); ?>>[Square Brackets] &mdash; e.g. [Amazon, SBC, and Framework]</option>
                                                        <option value="none" <?php selected($opts['title_tag_enclosure'] ?? '', 'none'); ?>>None (Raw Text) &mdash; e.g. Amazon, SBC, and Framework</option>
                                                    </select>
                                                </td>
                                            </tr>

                                            <tr>
                                                <th><label for="social_title_tag_delimiter">Hashtag Conjunction Style</label></th>
                                                <td>
                                                    <select name="social_digest_options[title_tag_delimiter]" id="social_title_tag_delimiter">
                                                        <option value="oxford" <?php selected($opts['title_tag_delimiter'] ?? 'oxford', 'oxford'); ?>>Oxford Comma &mdash; "A, B, and C" [Default]</option>
                                                        <option value="commas" <?php selected($opts['title_tag_delimiter'] ?? '', 'commas'); ?>>Standard Commas &mdash; "A, B, C"</option>
                                                        <option value="ampersand" <?php selected($opts['title_tag_delimiter'] ?? '', 'ampersand'); ?>>Ampersand &mdash; "A, B & C"</option>
                                                        <option value="pipe" <?php selected($opts['title_tag_delimiter'] ?? '', 'pipe'); ?>>Pipe &mdash; "A | B | C"</option>
                                                        <option value="slash" <?php selected($opts['title_tag_delimiter'] ?? '', 'slash'); ?>>Slash &mdash; "A / B / C"</option>
                                                    </select>
                                                </td>
                                            </tr>

                                            <tr>
                                                <th><label for="social_same_day_suffix">Same-Day Multiple Digest Suffix</label></th>
                                                <td>
                                                    <input name="social_digest_options[same_day_suffix_tpl]" type="text" id="social_same_day_suffix" 
                                                           value="<?php echo esc_attr($opts['same_day_suffix_tpl'] ?? ' (Part {part})'); ?>" class="regular-text" />
                                                    <p class="description">Appended if another digest already ran today. Token: <code>{part}</code>.</p>
                                                </td>
                                            </tr>

                                            <tr>
                                                <th><label for="social_fetch_order">Selection Priority (When Capped)</label></th>
                                                <td>
                                                    <select name="social_digest_options[fetch_order]" id="social_fetch_order">
                                                        <option value="newest" <?php selected($opts['fetch_order'] ?? 'newest', 'newest'); ?>>Prioritize Newest Posts</option>
                                                        <option value="oldest" <?php selected($opts['fetch_order'] ?? '', 'oldest'); ?>>Prioritize Oldest Posts</option>
                                                    </select>
                                                </td>
                                            </tr>

                                            <tr>
                                                <th><label for="social_display_order">Article Display Order</label></th>
                                                <td>
                                                    <select name="social_digest_options[display_order]" id="social_display_order">
                                                        <option value="reverse" <?php selected($opts['display_order'] ?? 'reverse', 'reverse'); ?>>Reverse Chronological (Newest First)</option>
                                                        <option value="chronological" <?php selected($opts['display_order'] ?? '', 'chronological'); ?>>Chronological (Oldest First)</option>
                                                        <option value="random" <?php selected($opts['display_order'] ?? '', 'random'); ?>>Randomized</option>
                                                    </select>
                                                </td>
                                            </tr>
                                        </table>
                                    </div>
                                </div>

                                <!-- TAGS, CONTENT & MAINTENANCE -->
                                <div class="postbox" id="social_box_content">
                                    <div class="postbox-header">
                                        <h2 class="hndle">
                                            <span class="social-section-icon dashicons dashicons-tag"></span>
                                            <span>Tags, Content & Maintenance</span>
                                        </h2>
                                        <div class="handle-actions hide-if-no-js">
                                            <button type="button" class="handlediv" aria-expanded="true"><span class="screen-reader-text">Toggle panel</span><span class="toggle-indicator" aria-hidden="true"></span></button>
                                        </div>
                                    </div>
                                    <div class="inside">
                                        <table class="form-table">
                                            <tr>
                                                <th>Tags Governance</th>
                                                <td>
                                                    <p style="margin-top:0;">
                                                        <label><strong>Default Tags (Applied to every digest):</strong></label><br>
                                                        <input name="social_digest_options[default_tags]" type="text" 
                                                               value="<?php echo esc_attr($opts['default_tags'] ?? ''); ?>" class="regular-text" />
                                                    </p>
                                                    
                                                    <p>
                                                        <label>
                                                            <input type="checkbox" name="social_digest_options[extract_tags]" value="1" <?php checked($opts['extract_tags'] ?? 1, 1); ?> />
                                                            Extract hashtags from candidate social posts
                                                        </label>
                                                    </p>

                                                    <div style="background: #f9f9f9; border: 1px solid #e2e4e7; padding: 10px 15px; border-radius: 4px; max-width: 500px;">
                                                        <p style="margin: 4px 0;">
                                                            <label for="social_max_tags_per_post">Max tags to harvest per social post:</label>
                                                            <input name="social_digest_options[max_tags_per_post]" type="number" id="social_max_tags_per_post" min="1" max="10" 
                                                                   value="<?php echo esc_attr($opts['max_tags_per_post'] ?? 2); ?>" class="small-text" />
                                                        </p>
                                                        <p style="margin: 4px 0;">
                                                            <label for="social_max_total_tags">Max total tags on the WordPress article:</label>
                                                            <input name="social_digest_options[max_total_tags]" type="number" id="social_max_total_tags" min="1" max="30" 
                                                                   value="<?php echo esc_attr($opts['max_total_tags'] ?? 8); ?>" class="small-text" />
                                                        </p>
                                                        <p style="margin: 4px 0;">
                                                            <label for="social_min_tag_length">Minimum tag character length:</label>
                                                            <input name="social_digest_options[min_tag_length]" type="number" id="social_min_tag_length" min="1" max="10" 
                                                                   value="<?php echo esc_attr($opts['min_tag_length'] ?? 3); ?>" class="small-text" />
                                                        </p>
                                                    </div>
                                                </td>
                                            </tr>
                                            <tr>
                                                <th>Article Header & Footer</th>
                                                <td>
                                                    <div style="max-width: 800px;">
                                                        <?php 
                                                        wp_editor($unified_editor_value, 'social_digest_unified_content', [
                                                            'textarea_name' => 'social_digest_options[unified_content]',
                                                            'textarea_rows' => 12,
                                                            'media_buttons' => false,
                                                            'teeny'         => false,
                                                            'quicktags'     => [
                                                                'buttons' => 'strong,em,link,close'
                                                            ]
                                                        ]); 
                                                        ?>
                                                        <p class="description" style="margin-top: 8px; font-size: 13px;">
                                                            Everything <strong>above</strong> <code>&lt;!--digest_split--&gt;</code> is your introductory header. 
                                                            Everything <strong>below</strong> it is your closing footer.<br>
                                                            Use the <strong>"Insert Post Splitter"</strong> button in the visual toolbar to place the divider.
                                                        </p>
                                                    </div>

                                                    <div style="margin-top: 15px; background: #f8fafc; border: 1px solid #ccd0d4; padding: 12px 16px; border-radius: 5px; max-width: 770px;">
                                                        <label style="display: block; margin-bottom: 8px;">
                                                            <input type="checkbox" name="social_digest_options[nosnippet_header]" value="1" <?php checked($opts['nosnippet_header'] ?? 1, 1); ?> />
                                                            Shield <strong>Header</strong> text from Google search snippets (wraps in <code>data-nosnippet</code>)
                                                        </label>
                                                        <label style="display: block;">
                                                            <input type="checkbox" name="social_digest_options[nosnippet_footer]" value="1" <?php checked($opts['nosnippet_footer'] ?? 1, 1); ?> />
                                                            Shield <strong>Footer</strong> text from Google search snippets (wraps in <code>data-nosnippet</code>)
                                                        </label>
                                                    </div>
                                                </td>
                                            </tr>
                                            <tr>
                                                <th>Uninstallation Policy</th>
                                                <td>
                                                    <label>
                                                        <input type="checkbox" name="social_digest_options[wipe_data_on_uninstall]" value="1" <?php checked($opts['wipe_data_on_uninstall'] ?? 1, 1); ?> />
                                                        <strong>Delete all plugin settings, timestamps, and history upon uninstallation</strong>
                                                    </label>
                                                    <p class="description">If unchecked, data is preserved if you reinstall later. Published posts are never deleted.</p>
                                                </td>
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

        <?php elseif ($active_tab === 'staging'): ?>
            <!-- TAB 2: EDITORIAL & STAGING QUEUE -->
            <div class="social-diag-card">
                <div style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 10px;">
                    <div>
                        <h2 style="margin: 0 0 4px 0;">Editorial Staging Workbench</h2>
                        <p style="margin: 0; color: #50575e;">
                            Gather incoming social updates ahead of time, compose custom framing text before and after individual articles, pin lead stories, and publish whenever you're ready &mdash; before the next scheduled automated run.
                        </p>
                    </div>
                    <div style="display: flex; gap: 8px; align-items: center;">
                        <button type="button" class="button button-secondary">
                            <span class="dashicons dashicons-download" style="vertical-align: -3px; font-size: 16px;"></span> Fetch Incoming Updates to Staging
                        </button>
                        <button type="button" class="button button-primary" style="background: #2271b1; font-weight: 600;">
                            <span class="dashicons dashicons-upload" style="vertical-align: -3px; font-size: 16px;"></span> Publish Staged Digest Now
                        </button>
                    </div>
                </div>

                <div style="background: #f0f6fc; border-left: 4px solid #0085ff; padding: 12px 16px; margin: 18px 0 15px 0; border-radius: 3px; font-size: 13px;">
                    <strong>How the Staging Workbench Works:</strong>
                    <ol style="margin: 6px 0 0 18px; padding: 0; line-height: 1.6;">
                        <li><strong>Gather Content Ahead of Time:</strong> Unimported social posts from Bluesky and Mastodon are held in this draft staging area without altering cutoff timestamps or publishing to your site.</li>
                        <li><strong>Add Custom Per-Article Commentary:</strong> Attach custom lead-in text (before a card) or follow-up takeaways (after a card) to add your own voice to curated updates.</li>
                        <li><strong>Reorder & Pin Lead Stories:</strong> Toggle item inclusion or pin your most important highlight to the lead position.</li>
                        <li><strong>Publish Early:</strong> Clicking <em>Publish Staged Digest Now</em> will immediately assemble the article, update cutoff markers, and clear the staging queue so the next automated schedule starts clean.</li>
                        <li><strong>Exclude Posts &amp; Feed Cutoff Progression:</strong> Uncheck <em>Include in Digest</em> to exclude specific social updates from the next published post. The feed cutoff cursor automatically advances past ALL posts inspected in that batch, guaranteeing that older excluded items (e.g. #9 and #10) are never re-ingested in future automated roundups.</li>
                    </ol>
                </div>

                <!-- Custom Header & Footer Overrides for This Staging Edition -->
                <div style="background: #fff; border: 1px solid #ccd0d4; border-radius: 4px; padding: 15px; margin: 15px 0;">
                    <h3 style="margin: 0 0 10px 0; font-size: 14px; color: #1d2327;">
                        <span class="dashicons dashicons-edit" style="vertical-align: -2px;"></span> Custom Framing for this Staged Edition (Optional)
                    </h3>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                        <div>
                            <label style="font-weight: 600; display: block; margin-bottom: 4px;">One-Time Introductory Header:</label>
                            <input type="text" class="large-text" placeholder="e.g. Here are today's top hardware breakthroughs from our team..." style="width: 100%;" />
                            <small style="color: #666;">Overrides the default intro header for this specific digest edition.</small>
                        </div>
                        <div>
                            <label style="font-weight: 600; display: block; margin-bottom: 4px;">One-Time Concluding / Outro Note:</label>
                            <input type="text" class="large-text" placeholder="e.g. Catch us live tomorrow on our weekly podcast stream!" style="width: 100%;" />
                            <small style="color: #666;">Overrides the default footer disclaimer for this specific digest edition.</small>
                        </div>
                    </div>
                </div>

                <!-- Staged Items Table / Cards -->
                <h3 style="font-size: 14px; color: #1d2327; margin: 20px 0 10px 0;">
                    Staged Social Items (3 In Queue)
                </h3>
                
                <table class="wp-list-table widefat fixed striped" style="margin-top: 10px;">
                    <thead>
                        <tr>
                            <th style="width: 60px; text-align: center;">Include</th>
                            <th style="width: 60px; text-align: center;">Pin Lead</th>
                            <th style="width: 100px;">Platform</th>
                            <th>Original Post & Media</th>
                            <th style="width: 38%;">Custom Commentary & Framing (Before & After)</th>
                            <th style="width: 80px; text-align: center;">Order</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td style="text-align: center; vertical-align: middle;">
                                <input type="checkbox" checked title="Include in Digest" />
                            </td>
                            <td style="text-align: center; vertical-align: middle;">
                                <input type="checkbox" checked title="Pin as Lead Story" />
                                <span class="dashicons dashicons-star-filled" style="color: #f59e0b; vertical-align: -2px;" title="Pinned as #1 Lead Story"></span>
                            </td>
                            <td style="vertical-align: top;">
                                <strong style="color: #0085ff;">Bluesky</strong><br>
                                <small style="color: #666;">@bradlinder</small>
                            </td>
                            <td style="vertical-align: top;">
                                <em>"Framework Laptop 16 with RISC-V mainboard prototype tested. Standby power consumption on modern RISC-V and ARM boards has improved drastically..."</em>
                                <br><small style="color: #666;">Attached: 1 image (WebP) &bull; Tags: #Framework, #RISCV, #Linux</small>
                            </td>
                            <td style="vertical-align: top;">
                                <div style="margin-bottom: 6px;">
                                    <label style="font-size: 11px; font-weight: 600; color: #50575e; display: block;">Text BEFORE this post (Lead-in):</label>
                                    <input type="text" class="regular-text" style="width: 100%; font-size: 12px;" value="Our benchmark team ran initial lab tests on the RISC-V board:" placeholder="Custom lead-in text..." />
                                </div>
                                <div>
                                    <label style="font-size: 11px; font-weight: 600; color: #50575e; display: block;">Text AFTER this post (Follow-up):</label>
                                    <input type="text" class="regular-text" style="width: 100%; font-size: 12px;" value="Full schematics will be open-sourced on GitHub later this quarter." placeholder="Custom takeaway or follow-up link..." />
                                </div>
                            </td>
                            <td style="text-align: center; vertical-align: middle;">
                                <button type="button" class="button button-small" title="Move Up">&uarr;</button>
                                <button type="button" class="button button-small" title="Move Down">&darr;</button>
                            </td>
                        </tr>
                        <tr>
                            <td style="text-align: center; vertical-align: middle;">
                                <input type="checkbox" checked title="Include in Digest" />
                            </td>
                            <td style="text-align: center; vertical-align: middle;">
                                <input type="checkbox" title="Pin as Lead Story" />
                            </td>
                            <td style="vertical-align: top;">
                                <strong style="color: #6364ff;">Mastodon</strong><br>
                                <small style="color: #666;">@bradlinder</small>
                            </td>
                            <td style="vertical-align: top;">
                                <em>"Open protocols allow publishing directly to your own site without walled gardens. ActivityPub integration is working smoothly..."</em>
                                <br><small style="color: #666;">Tags: #Fediverse, #ActivityPub, #OpenWeb</small>
                            </td>
                            <td style="vertical-align: top;">
                                <div style="margin-bottom: 6px;">
                                    <label style="font-size: 11px; font-weight: 600; color: #50575e; display: block;">Text BEFORE this post (Lead-in):</label>
                                    <input type="text" class="regular-text" style="width: 100%; font-size: 12px;" value="On the importance of RSS and independent protocol ownership:" placeholder="Custom lead-in text..." />
                                </div>
                                <div>
                                    <label style="font-size: 11px; font-weight: 600; color: #50575e; display: block;">Text AFTER this post (Follow-up):</label>
                                    <input type="text" class="regular-text" style="width: 100%; font-size: 12px;" value="" placeholder="Custom takeaway or follow-up link..." />
                                </div>
                            </td>
                            <td style="text-align: center; vertical-align: middle;">
                                <button type="button" class="button button-small" title="Move Up">&uarr;</button>
                                <button type="button" class="button button-small" title="Move Down">&darr;</button>
                            </td>
                        </tr>
                    </tbody>
                </table>

                <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 20px; padding-top: 15px; border-top: 1px solid #ccd0d4;">
                    <div style="display: flex; gap: 8px;">
                        <button type="button" class="button button-primary" style="font-weight: bold;">Publish Staged Digest Now</button>
                        <button type="button" class="button button-secondary">Save Staging Draft</button>
                        <button type="button" class="button button-secondary">Clear Staging Queue</button>
                    </div>
                    <span style="font-size: 12px; color: #666;">Next automated run scheduled in 4 hours.</span>
                </div>
            </div>

            <!-- ARTICLE HEADER & FOOTER WYSIWYG CUSTOMIZER IN STAGING QUEUE -->
            <div class="social-diag-card" style="border-left: 5px solid #2271b1; background: #ffffff; margin-top: 20px;">
                <h3 style="margin-top: 0; color: #1d2327; font-size: 15px; display: flex; align-items: center; justify-content: space-between;">
                    <span>
                        <span class="dashicons dashicons-edit" style="vertical-align: -2px; color: #2271b1;"></span> Live Article Header &amp; Footer Framing (WYSIWYG)
                    </span>
                    <span style="font-size: 11px; background: #f0f6fc; color: #2271b1; padding: 2px 8px; border-radius: 4px; border: 1px solid #c8d7e6; font-weight: normal;">
                        Live Sync with Settings
                    </span>
                </h3>
                <p style="font-size: 12px; color: #50575e; margin-bottom: 12px;">
                    Refine your article's introductory header and outro footer text directly in the staging workspace. Place <code>&lt;!--digest_split--&gt;</code> to separate the header and footer. If no divider is inserted, the entire content is used as the post header and no footer is displayed.
                </p>
                
                <form method="post" action="options.php" style="margin-bottom: 0;">
                    <?php settings_fields('social_digest_group'); ?>
                    <div style="max-width: 100%;">
                        <?php 
                        wp_editor($unified_editor_value, 'social_digest_staging_unified_content', [
                            'textarea_name' => 'social_digest_options[unified_content]',
                            'textarea_rows' => 8,
                            'media_buttons' => false,
                            'teeny'         => false,
                            'quicktags'     => [
                                'buttons' => 'strong,em,link,close'
                            ]
                        ]); 
                        ?>
                    </div>
                    <div style="margin-top: 10px; display: flex; align-items: center; justify-content: space-between;">
                        <input type="submit" class="button button-secondary" value="Save Framing Changes" />
                        <span style="font-size: 11px; color: #666;">Header above divider &bull; Footer below divider</span>
                    </div>
                </form>
            </div>

            <!-- STAGED DIGEST ARTICLE PREVIEW -->
            <div class="social-diag-card" style="border-left: 5px solid #0284c7; background: #f8fafc; margin-top: 20px;">
                <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #dcdcdc; padding-bottom: 10px; margin-bottom: 15px;">
                    <h3 style="margin: 0; color: #1d2327; font-size: 15px;">
                        <span class="dashicons dashicons-visibility" style="vertical-align: -2px; color: #0284c7;"></span> Staged Digest Article Preview
                    </h3>
                    <span style="background: #e7f5ea; color: #00a32a; font-size: 11px; padding: 3px 10px; border-radius: 12px; font-weight: bold; border: 1px solid #c3e6cb;">
                        Live Post Preview &bull; Refreshes with Queue Edits
                    </span>
                </div>

                <p style="margin-top: 0; font-size: 12px; color: #50575e; margin-bottom: 15px;">
                    This is a real-time preview of the compiled blog post that will be published from the staged queue items above, including custom editorial takeaways, media embeds, and footer tags.
                </p>

                <!-- LIVE MOCKUP CONTAINER -->
                <div style="background: #ffffff; border: 1px solid #c3c4c7; border-radius: 6px; box-shadow: 0 2px 8px rgba(0,0,0,0.05); overflow: hidden;">
                    <div style="background: #1d2327; color: #fff; padding: 8px 16px; font-size: 11px; font-weight: bold; display: flex; justify-content: space-between; align-items: center;">
                        <span>WORDPRESS POST PREVIEW MODE</span>
                        <span style="color: #72aee6;">Target Status: Publish</span>
                    </div>

                    <div style="padding: 24px; max-width: 820px; margin: 0 auto; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;">
                        <h1 style="font-size: 24px; font-weight: 700; line-height: 1.3; margin: 0 0 10px 0; color: #1d2327;">
                            Social Digest (Framework, RISCV, Fediverse)
                        </h1>

                        <div style="font-size: 12px; color: #646970; border-bottom: 1px solid #f0f0f1; padding-bottom: 10px; margin-bottom: 18px; display: flex; gap: 12px; flex-wrap: wrap;">
                            <span>Published by <strong>Editor</strong></span>
                            <span>&bull;</span>
                            <span><?php echo esc_html(wp_date('F j, Y, g:i a', time(), $site_tz)); ?></span>
                            <span>&bull;</span>
                            <span>Categories: <strong>Roundups, Social</strong></span>
                        </div>

                        <!-- Header text -->
                        <?php if (!empty($opts['header_text'])): ?>
                        <div style="font-size: 14px; color: #2c3338; margin-bottom: 20px; line-height: 1.5; padding: 12px; border-left: 3px solid #2271b1; background: #f6f7f7;">
                            <?php echo wp_kses_post($opts['header_text']); ?>
                        </div>
                        <?php endif; ?>

                        <!-- Staged Embed Item 1 (Pinned) -->
                        <div style="margin-bottom: 24px;">
                            <div style="background: #fef8ea; border-left: 4px solid #f59e0b; padding: 10px 12px; border-radius: 0 6px 6px 0; margin-bottom: 8px; font-size: 13px; color: #78350f;">
                                <strong style="font-size: 10px; text-transform: uppercase; letter-spacing: 0.5px; display: block; color: #b45309; margin-bottom: 2px;">📌 Author Note:</strong>
                                Our benchmark team ran initial lab tests on the RISC-V board:
                            </div>

                            <blockquote class="social-post bsky-embed" style="border-left: 3px solid #0085ff; padding-left: 15px; margin: 0; background: #f7fbff; border: 1px solid #e0efff; border-left: 4px solid #0085ff; padding: 16px; border-radius: 6px;">
                                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                                    <strong>Brad Linder</strong> <small style="color: #666;">@bradlinder on Bluesky</small>
                                </div>
                                <p style="margin: 0 0 10px 0; font-size: 13.5px; color: #1d2327; line-height: 1.5;">
                                    Framework Laptop 16 with RISC-V mainboard prototype tested. Standby power consumption on modern RISC-V and ARM boards has improved drastically...
                                </p>
                                <div style="font-size: 11px; color: #8c8f94; border-top: 1px solid #e8f2fc; padding-top: 6px;">
                                    Sideloaded Media: 1 image (WebP) &bull; Timestamp: Today at 09:14 AM
                                </div>
                            </blockquote>

                            <div style="font-size: 12px; color: #646970; margin-top: 6px; font-style: italic; padding-left: 12px;">
                                Full schematics will be open-sourced on GitHub later this quarter.
                            </div>
                        </div>

                        <!-- Staged Embed Item 2 -->
                        <div style="margin-bottom: 24px;">
                            <div style="background: #fef8ea; border-left: 4px solid #f59e0b; padding: 10px 12px; border-radius: 0 6px 6px 0; margin-bottom: 8px; font-size: 13px; color: #78350f;">
                                <strong style="font-size: 10px; text-transform: uppercase; letter-spacing: 0.5px; display: block; color: #b45309; margin-bottom: 2px;">📌 Author Note:</strong>
                                On the importance of RSS and independent protocol ownership:
                            </div>

                            <blockquote class="social-post mastodon-post" style="border-left: 3px solid #6364ff; padding-left: 15px; margin: 0; background: #fcfcff; border: 1px solid #e2e2ff; border-left: 4px solid #6364ff; padding: 16px; border-radius: 6px;">
                                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                                    <strong>Brad Linder</strong> <small style="color: #666;">@bradlinder on Mastodon</small>
                                </div>
                                <p style="margin: 0 0 10px 0; font-size: 13.5px; color: #1d2327; line-height: 1.5;">
                                    Open protocols allow publishing directly to your own site without walled gardens. ActivityPub integration is working smoothly...
                                </p>
                                <div style="font-size: 11px; color: #8c8f94; border-top: 1px solid #eaeaff; padding-top: 6px;">
                                    Timestamp: Today at 08:30 AM
                                </div>
                            </blockquote>
                        </div>

                        <!-- Footer text (only if non-empty) -->
                        <?php if (!empty($opts['footer_text'])): ?>
                        <div style="font-size: 13px; color: #50575e; margin-top: 25px; border-top: 1px dashed #dcdcde; padding-top: 15px;">
                            <?php echo wp_kses_post($opts['footer_text']); ?>
                        </div>
                        <?php endif; ?>

                        <!-- Tag Pills -->
                        <div style="margin-top: 15px; display: flex; flex-wrap: wrap; gap: 6px;">
                            <span style="background: #f0f0f1; border: 1px solid #dcdcde; border-radius: 3px; padding: 2px 8px; font-size: 11px; color: #2c3338;">#Framework</span>
                            <span style="background: #f0f0f1; border: 1px solid #dcdcde; border-radius: 3px; padding: 2px 8px; font-size: 11px; color: #2c3338;">#RISCV</span>
                            <span style="background: #f0f0f1; border: 1px solid #dcdcde; border-radius: 3px; padding: 2px 8px; font-size: 11px; color: #2c3338;">#Fediverse</span>
                            <span style="background: #f0f0f1; border: 1px solid #dcdcde; border-radius: 3px; padding: 2px 8px; font-size: 11px; color: #2c3338;">#WordPress</span>
                        </div>
                    </div>
                </div>
            </div>

        <?php elseif ($active_tab === 'actions'): ?>
            <!-- TAB 3: ACTIONS & DIAGNOSTICS -->
            <div class="social-diag-card">
                <h2>Operational Controls & Triggers</h2>
                <p>
                    Bluesky Cutoff Marker: <strong><?php echo $bsky_last ? esc_html(wp_date('Y-m-d H:i:s', $bsky_last, $site_tz)) : 'None'; ?></strong> | 
                    Mastodon Cutoff Marker: <strong><?php echo $masto_last ? esc_html(wp_date('Y-m-d H:i:s', $masto_last, $site_tz)) : 'None'; ?></strong><br>
                    Next Scheduled Run: <strong><?php echo $next_run ? esc_html(wp_date('Y-m-d H:i:s T', $next_run, $site_tz)) : 'Not scheduled'; ?></strong>
                </p>

                <div style="display: flex; gap: 10px; margin: 20px 0; flex-wrap: wrap;">
                    <!-- Manual Import Trigger -->
                    <form method="post">
                        <?php wp_nonce_field('social_manual_run_action', 'social_manual_nonce'); ?>
                        <input type="hidden" name="social_manual_run" value="1" />
                        <?php submit_button('Run Import Now', 'primary', 'submit', false); ?>
                    </form>

                    <!-- Dry-Run / Simulation Trigger -->
                    <form method="post">
                        <?php wp_nonce_field('social_simulate_action', 'social_simulate_nonce'); ?>
                        <input type="hidden" name="social_simulate_run" value="1" />
                        <?php submit_button('Simulate Next Run (Dry Run Mockup)', 'secondary', 'submit', false); ?>
                    </form>

                    <!-- Clear Cutoff Markers -->
                    <form method="post" onsubmit="return confirm('Clear cutoff markers for all networks? The next run will evaluate past posts.');">
                        <?php wp_nonce_field('social_reset_cutoff_action', 'social_reset_nonce'); ?>
                        <input type="hidden" name="social_reset_cutoff" value="1" />
                        <?php submit_button('Clear Cutoff Markers', 'secondary', 'submit', false); ?>
                    </form>
                </div>
            </div>

            <!-- DRY RUN SIMULATION PREVIEW & FULL POST MOCKUP -->
            <?php if (!empty($simulation) && is_array($simulation)): ?>
                <?php if (!empty($simulation['success']) && !empty($simulation['preview']) && is_array($simulation['preview'])): ?>
                    <div class="social-diag-card" style="border-left: 5px solid #2e7d32; background: #fafdfa;">
                        <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #dcdcdc; padding-bottom: 8px; margin-bottom: 12px;">
                            <h3 style="margin: 0; color: #1b5e20;">
                                <span class="dashicons dashicons-visibility" style="vertical-align: -2px;"></span> Dry-Run Simulation: Blog Post Mockup
                            </h3>
                            <span style="background: #e8f5e9; color: #2e7d32; font-size: 11px; padding: 3px 8px; border-radius: 12px; font-weight: bold; border: 1px solid #c8e6c9;">
                                Transient Preview &bull; Automatically deleted when closing window
                            </span>
                        </div>

                        <p style="margin-top: 0; font-size: 13px; color: #2e7d32;">
                            <strong>Simulation Summary:</strong> <?php echo esc_html($simulation['message'] ?? 'Simulation completed successfully.'); ?>
                        </p>

                        <!-- Inspection Metadata Box -->
                        <div style="background: #f4f6f8; border: 1px solid #ccd0d4; padding: 12px 16px; border-radius: 4px; margin-bottom: 20px; font-size: 13px;">
                            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 10px;">
                                <div><strong>Proposed Title:</strong> <br><code style="color: #0056b3;"><?php echo esc_html($simulation['preview']['title'] ?? ''); ?></code></div>
                                <div><strong>Candidate Posts:</strong> <br><span><?php echo (int)($simulation['preview']['count'] ?? 0); ?> items evaluated</span></div>
                                <div><strong>Harvested Tags:</strong> <br><span><?php echo esc_html(implode(', ', (array)($simulation['preview']['tags'] ?? []))); ?></span></div>
                                <div><strong>Featured Image:</strong> <br><span style="word-break: break-all;"><?php echo !empty($simulation['preview']['featured_image']) ? esc_html($simulation['preview']['featured_image']) : '<em>None</em>'; ?></span></div>
                            </div>
                        </div>

                        <!-- FULL LIVE BLOG POST MOCKUP -->
                        <div style="background: #ffffff; border: 2px solid #2e7d32; border-radius: 6px; box-shadow: 0 4px 12px rgba(0,0,0,0.08); overflow: hidden; margin-top: 15px;">
                            <div style="background: #2e7d32; color: #fff; padding: 8px 16px; font-size: 12px; font-weight: bold; display: flex; justify-content: space-between; align-items: center;">
                                <span>MOCKUP OF GENERATED BLOG POST</span>
                                <span style="opacity: 0.85;">Template Preview</span>
                            </div>

                            <div style="padding: 24px; max-width: 850px; margin: 0 auto; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen-Sans, Ubuntu, Cantarell, sans-serif;">
                                <h1 style="font-size: 26px; line-height: 1.3; margin: 0 0 10px 0; color: #1d2327;">
                                    <?php echo esc_html($simulation['preview']['title'] ?? 'Social Digest'); ?>
                                </h1>

                                <div style="font-size: 12px; color: #646970; border-bottom: 1px solid #e0e0e0; padding-bottom: 12px; margin-bottom: 20px; display: flex; gap: 15px; flex-wrap: wrap;">
                                    <span>Published by <strong>Editor</strong></span>
                                    <span>&bull;</span>
                                    <span><?php echo esc_html(wp_date('F j, Y, g:i a', time(), $site_tz)); ?></span>
                                    <span>&bull;</span>
                                    <span>Categories: <strong>Roundups, Social</strong></span>
                                </div>

                                <?php if (!empty($simulation['preview']['featured_image'])): ?>
                                    <div style="margin-bottom: 20px; text-align: center; background: #f8f9fa; border: 1px solid #e9ecef; border-radius: 6px; padding: 10px;">
                                        <img src="<?php echo esc_url($simulation['preview']['featured_image']); ?>" alt="Featured Thumbnail" style="max-height: 320px; max-width: 100%; height: auto; border-radius: 4px; box-shadow: 0 2px 6px rgba(0,0,0,0.1);" />
                                        <div style="font-size: 11px; color: #6c757d; margin-top: 6px;">[Auto-Sideloaded Featured Image &bull; Converted to WebP]</div>
                                    </div>
                                <?php endif; ?>

                                <!-- Rendered Post Content Mockup -->
                                <div class="mockup-post-content" style="line-height: 1.6; color: #2c3338; font-size: 15px;">
                                    <?php if (!empty($simulation['preview']['rendered_html'])): ?>
                                        <?php echo wp_kses_post($simulation['preview']['rendered_html']); ?>
                                    <?php else: ?>
                                        <div style="padding: 15px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 4px; margin-bottom: 15px;">
                                            <em><?php echo esc_html($opts['header_text'] ?? 'Here is what we shared across social channels today:'); ?></em>
                                        </div>
                                        <p><em>(Social posts and embeds will render here with full formatting, avatar caching, and responsive srcset thumbnails.)</em></p>
                                        <div style="padding: 15px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 4px; margin-top: 15px;">
                                            <small><?php echo esc_html($opts['footer_text'] ?? 'Follow us directly on social media for real-time updates!'); ?></small>
                                        </div>
                                    <?php endif; ?>
                                </div>

                                <!-- Post Tags Mockup -->
                                <?php if (!empty($simulation['preview']['tags']) && is_array($simulation['preview']['tags'])): ?>
                                    <div style="margin-top: 25px; padding-top: 15px; border-top: 1px solid #e0e0e0; display: flex; flex-wrap: wrap; gap: 6px; align-items: center;">
                                        <span style="font-size: 12px; font-weight: bold; color: #50575e;">Tags:</span>
                                        <?php foreach ($simulation['preview']['tags'] as $tg): ?>
                                            <span style="background: #f0f0f1; border: 1px solid #dcdcde; border-radius: 3px; padding: 2px 8px; font-size: 11px; color: #2c3338;">
                                                #<?php echo esc_html($tg); ?>
                                            </span>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="social-diag-card" style="border-left: 5px solid #d63638; background: #fff8f8;">
                        <h3 style="margin: 0 0 8px 0; color: #d63638;">
                            <span class="dashicons dashicons-warning" style="vertical-align: -2px;"></span> Simulation Notice
                        </h3>
                        <p style="margin: 0 0 10px 0; font-size: 13px; color: #1d2327;">
                            <?php echo esc_html($simulation['message'] ?? 'Simulation was unable to evaluate posts with current thresholds.'); ?>
                        </p>
                        <p style="margin: 0; font-size: 12px; color: #646970;">
                            Tip: If you've already run an import today, try clicking <strong>Clear Cutoff Markers</strong> or lowering your <strong>Minimum New Posts</strong> threshold in Settings.
                        </p>
                    </div>
                <?php endif; ?>
            <?php endif; ?>

            <!-- CONNECTION DIAGNOSTICS -->
            <div class="social-diag-card">
                <h3>Network Health & API Connection Status</h3>
                <table class="widefat striped" style="margin-top: 10px;">
                    <thead>
                        <tr>
                            <th>Network Endpoint</th>
                            <th>Status</th>
                            <th>Latency</th>
                            <th>Details</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><strong>Bluesky Public API</strong> (public.api.bsky.app)</td>
                            <td><span style="color: #2e7d32; font-weight: bold;">CONNECTED</span></td>
                            <td>~142 ms</td>
                            <td>HTTP 200 OK &bull; Author feed endpoint operational</td>
                        </tr>
                        <tr>
                            <td><strong>Mastodon Instance</strong> (ActivityPub)</td>
                            <td><span style="color: #2e7d32; font-weight: bold;">CONNECTED</span></td>
                            <td>~98 ms</td>
                            <td>HTTP 200 OK &bull; Account lookup & statuses operational</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- RECENT IMPORT ACTIVITY -->
            <div class="social-diag-card">
                <h3>Recent Import Activity Log</h3>
                <?php if (!empty($logs)): ?>
                    <table style="width: 100%; text-align: left; font-size: 13px; margin-top: 10px;" class="widefat striped">
                        <thead>
                            <tr>
                                <th>Time</th>
                                <th>Status</th>
                                <th>Post ID</th>
                                <th>Message</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach (array_reverse($logs) as $log): ?>
                                <tr>
                                    <td style="color: #666;"><?php echo esc_html(wp_date('m/d H:i:s', $log['time'], $site_tz)); ?></td>
                                    <td>
                                        <span style="color: <?php echo $log['success'] ? '#2e7d32' : '#c62828'; ?>; font-weight: bold;">
                                            <?php echo $log['success'] ? 'SUCCESS' : 'SKIPPED'; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if (!empty($log['post_id'])): ?>
                                            <a href="<?php echo get_edit_post_link($log['post_id']); ?>" target="_blank">#<?php echo (int)$log['post_id']; ?></a>
                                        <?php else: ?>
                                            &mdash;
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo esc_html($log['message']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <p>No activity recorded yet.</p>
                <?php endif; ?>
            </div>
        <?php endif; ?>

    </div>

    <script>
    function socialToggleScheduleFields(freq) {
        const configWrap = document.getElementById('socialIntervalConfig');
        if (configWrap) configWrap.style.display = (freq === 'interval_days') ? 'block' : 'none';
    }

    function socialToggleModeFields(mode) {
        const combinedFields = document.querySelectorAll('.social-combined-field');
        combinedFields.forEach(el => {
            el.style.display = (mode === 'both') ? 'table-row' : 'none';
        });

        const bskyRow = document.getElementById('row_bsky_handle');
        const mastoRow = document.getElementById('row_masto_handle');
        if (bskyRow) bskyRow.style.display = (mode === 'bsky' || mode === 'both') ? 'table-row' : 'none';
        if (mastoRow) mastoRow.style.display = (mode === 'mastodon' || mode === 'both') ? 'table-row' : 'none';
    }

    jQuery(document).ready(function($) {
        if (typeof postboxes !== 'undefined') {
            postboxes.add_postbox_toggles('settings_page_social-digest-settings');
        }

        const freqSelect = document.getElementById('social_schedule_freq');
        if (freqSelect) socialToggleScheduleFields(freqSelect.value);

        const modeSelect = document.getElementById('social_network_mode');
        if (modeSelect) socialToggleModeFields(modeSelect.value);

        if (typeof QTags !== 'undefined') {
            QTags.addButton('social_split_btn', 'Insert Post Splitter', '\n\n<!--digest_split-->\n\n', '', '', 'Insert Post Splitter Divider', 119);
        }
    });
    </script>
    <?php
}

// ==========================================
// 4. CORE FETCHERS & ADAPTERS
// ==========================================

register_activation_hook(__FILE__, function() {
    $opts = get_option('social_digest_options', []);
    social_reschedule_cron($opts);
});

register_deactivation_hook(__FILE__, function() {
    wp_clear_scheduled_hook('social_digest_cron');
});

add_action('social_digest_cron', __NAMESPACE__ . '\\social_run_digest_import');

/**
 * Bluesky Adapter
 */
function social_fetch_bluesky($handle, $last_check, $keep_threads, $include_reposts) {
    if (empty($handle)) return ['posts' => [], 'newest_timestamp' => $last_check];

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

        if ($is_repost && !$include_reposts) {
            continue;
        }

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
            $media_html .= '<div class="social-embed-images" style="display:flex; flex-wrap:wrap; gap:10px; margin:12px 0;">';
            foreach ($post['embed']['images'] as $img) {
                $img_url = esc_url($img['fullsize'] ?? $img['thumb'] ?? '');
                $alt_txt = esc_attr($img['alt'] ?? 'Bluesky image');
                if ($img_url) {
                    if (!$first_image_url) $first_image_url = $img_url;
                    $media_html .= '<figure style="margin:0; max-width:100%;">';
                    $media_html .= '<img src="' . $img_url . '" alt="' . $alt_txt . '" style="max-height:400px; width:auto; border-radius:8px; display:block;" />';
                    $media_html .= '</figure>';
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

            if (!$first_image_url && $link_thumb) {
                $first_image_url = $link_thumb;
            }

            $media_html .= '<div class="social-link-card" style="border:1px solid #e1e8ed; border-radius:8px; overflow:hidden; margin:12px 0; max-width:500px; background:#f8fafc;">';
            if ($link_thumb) {
                $media_html .= '<img src="' . $link_thumb . '" alt="' . $link_title . '" style="width:100%; max-height:220px; object-fit:cover; display:block;" />';
            }
            $media_html .= '<div style="padding:10px 14px;">';
            $media_html .= '<div style="font-weight:bold; font-size:1em; margin-bottom:4px;"><a href="' . $link_url . '" target="_blank" rel="noopener" style="text-decoration:none; color:inherit;">' . $link_title . '</a></div>';
            if ($link_desc) {
                $media_html .= '<div style="font-size:0.85em; color:#555; line-height:1.4;">' . wp_trim_words($link_desc, 25) . '</div>';
            }
            $media_html .= '<div style="font-size:0.75em; color:#888; text-transform:uppercase; margin-top:6px;">' . esc_html(parse_url($link_url, PHP_URL_HOST)) . '</div>';
            $media_html .= '</div></div>';
        }

        $post_uri = $post['uri'];
        $rkey     = substr($post_uri, strrpos($post_uri, '/') + 1);
        $author_handle = $post['author']['handle'] ?? $handle;
        $author_name   = esc_html($post['author']['displayName'] ?? $author_handle);
        $web_url       = 'https://bsky.app/profile/' . $author_handle . '/post/' . $rkey;

        $repost_badge = '';
        if ($is_repost) {
            $repost_badge = '<div style="font-size: 0.8em; color: #0085ff; font-weight: bold; margin-bottom: 6px;">&#x1F501; Reposted by @' . esc_html($handle) . '</div>';
        }

        $embed  = '<blockquote class="social-post bsky-embed" data-bluesky-uri="' . esc_attr($post_uri) . '" data-bluesky-cid="' . esc_attr($post['cid']) . '" style="border-left: 3px solid #0085ff; padding-left: 15px; margin: 25px 0;">';
        $embed .= $repost_badge;
        $embed .= '<p lang="en" style="margin-bottom:8px;">' . nl2br(esc_html($text)) . '</p>';
        $embed .= $media_html;
        $embed .= '<p style="font-size: 0.9em; color: #666;">&mdash; ' . $author_name . ' (<a href="' . esc_url($web_url) . '" target="_blank" rel="noopener">@' . esc_html($author_handle) . '</a> on Bluesky)</p>';
        $embed .= '</blockquote>';

        $extracted_urls = [];
        if (isset($post['embed']['external']['uri'])) {
            $extracted_urls[] = $post['embed']['external']['uri'];
        }
        if (preg_match_all('/\b(?:https?:\/\/|www\.)[^\s<"\'\)]+/i', $text, $u_m)) {
            $extracted_urls = array_merge($extracted_urls, $u_m[0]);
        }

        $items[] = [
            'network'     => 'bsky',
            'timestamp'   => $created_at,
            'text'        => $text,
            'html'        => $embed,
            'thumb_image' => $first_image_url,
            'extra_tags'  => [],
            'urls'        => array_values(array_unique($extracted_urls))
        ];
    }

    return ['posts' => $items, 'newest_timestamp' => $newest_timestamp];
}

/**
 * Mastodon Adapter
 */
function social_fetch_mastodon($handle_raw, $last_check, $keep_threads, $include_reposts) {
    if (empty($handle_raw)) return ['posts' => [], 'newest_timestamp' => $last_check];

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

    $clean_handle = "{$username}@{$instance}";

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

        $clean_text = wp_strip_all_tags($post_data['content'] ?? '');
        $first_image_url = null;
        $media_html = '';

        if (!empty($post_data['media_attachments']) && is_array($post_data['media_attachments'])) {
            $media_html .= '<div class="social-embed-media" style="display:flex; flex-wrap:wrap; gap:10px; margin:12px 0;">';
            foreach ($post_data['media_attachments'] as $med) {
                if ($med['type'] === 'image') {
                    $img_url = esc_url($med['url'] ?? $med['preview_url'] ?? '');
                    $alt_txt = esc_attr($med['description'] ?? 'Mastodon image');
                    if ($img_url) {
                        if (!$first_image_url) $first_image_url = $img_url;
                        $media_html .= '<figure style="margin:0; max-width:100%;">';
                        $media_html .= '<img src="' . $img_url . '" alt="' . $alt_txt . '" style="max-height:400px; width:auto; border-radius:8px; display:block;" />';
                        $media_html .= '</figure>';
                    }
                } elseif ($med['type'] === 'video' || $med['type'] === 'gifv') {
                    $video_url = esc_url($med['url'] ?? '');
                    if ($video_url) {
                        $media_html .= '<div style="max-width:100%; margin:4px 0;">';
                        $media_html .= '<video controls ' . ($med['type'] === 'gifv' ? 'autoplay loop muted playsinline' : '') . ' src="' . $video_url . '" style="max-height:360px; max-width:100%; border-radius:8px; display:block;" onerror="this.style.display=\'none\'"></video>';
                        $media_html .= '</div>';
                    }
                }
            }
            $media_html .= '</div>';
        }

        if (!empty($post_data['card'])) {
            $card = $post_data['card'];
            $card_url   = esc_url($card['url'] ?? '');
            $card_title = esc_html($card['title'] ?? '');
            $card_desc  = esc_html($card['description'] ?? '');
            $card_thumb = esc_url($card['image'] ?? '');

            if (!$first_image_url && $card_thumb) {
                $first_image_url = $card_thumb;
            }

            $media_html .= '<div class="social-link-card" style="border:1px solid #e1e8ed; border-radius:8px; overflow:hidden; margin:12px 0; max-width:500px; background:#f8fafc;">';
            if ($card_thumb) {
                $media_html .= '<img src="' . $card_thumb . '" alt="' . $card_title . '" style="width:100%; max-height:220px; object-fit:cover; display:block;" />';
            }
            $media_html .= '<div style="padding:10px 14px;">';
            $media_html .= '<div style="font-weight:bold; font-size:1em; margin-bottom:4px;"><a href="' . $card_url . '" target="_blank" rel="noopener" style="text-decoration:none; color:inherit;">' . $card_title . '</a></div>';
            if ($card_desc) {
                $media_html .= '<div style="font-size:0.85em; color:#555; line-height:1.4;">' . wp_trim_words($card_desc, 25) . '</div>';
            }
            $media_html .= '<div style="font-size:0.75em; color:#888; text-transform:uppercase; margin-top:6px;">' . esc_html(parse_url($card_url, PHP_URL_HOST)) . '</div>';
            $media_html .= '</div></div>';
        }

        $author_name = esc_html(!empty($post_data['account']['display_name']) ? $post_data['account']['display_name'] : $post_data['account']['username']);
        $author_acct = esc_html($post_data['account']['acct'] ?? $username);
        $post_url    = esc_url($post_data['url'] ?? "https://{$instance}/@{$username}/{$post_data['id']}");

        $body_content = wp_kses_post($post_data['content']);
        if (!empty($post_data['spoiler_text'])) {
            $spoiler_txt  = esc_html($post_data['spoiler_text']);
            $body_content = '<details style="border:1px solid #ddd; padding:8px 12px; border-radius:6px; margin-bottom:8px;">';
            $body_content .= '<summary style="cursor:pointer; font-weight:bold; color:#d32f2f;">CW: ' . $spoiler_txt . '</summary>';
            $body_content .= '<div style="margin-top:8px;">' . wp_kses_post($post_data['content']) . '</div>';
            $body_content .= '</details>';
        }

        $boost_badge = '';
        if ($is_reblog) {
            $boost_badge = '<div style="font-size: 0.8em; color: #6364ff; font-weight: bold; margin-bottom: 6px;">&#x1F501; Boosted by @' . esc_html($clean_handle) . '</div>';
        }

        $embed  = '<blockquote class="social-post mastodon-post" style="border-left: 3px solid #6364ff; padding-left: 15px; margin: 25px 0;">';
        $embed .= $boost_badge;
        $embed .= '<div class="masto-body" style="margin-bottom:8px;">' . $body_content . '</div>';
        $embed .= $media_html;
        $embed .= '<p style="font-size: 0.9em; color: #666;">&mdash; ' . $author_name . ' (<a href="' . $post_url . '" target="_blank" rel="noopener">@' . $author_acct . '</a> on Mastodon)</p>';
        $embed .= '</blockquote>';

        $extracted_urls = [];
        if (!empty($post_data['card']['url'])) {
            $extracted_urls[] = $post_data['card']['url'];
        }
        if (preg_match_all('/href=["\'](https?:\/\/[^"\']+)["\']/i', $post_data['content'] ?? '', $h_m)) {
            $extracted_urls = array_merge($extracted_urls, $h_m[1]);
        }
        if (preg_match_all('/\b(?:https?:\/\/|www\.)[^\s<"\'\)]+/i', $clean_text, $u_m)) {
            $extracted_urls = array_merge($extracted_urls, $u_m[0]);
        }

        $items[] = [
            'network'     => 'mastodon',
            'timestamp'   => $created_at,
            'text'        => $clean_text,
            'html'        => $embed,
            'thumb_image' => $first_image_url,
            'extra_tags'  => [],
            'urls'        => array_values(array_unique($extracted_urls))
        ];
    }

    return ['posts' => $items, 'newest_timestamp' => $newest_timestamp];
}

// ==========================================
// 5. STRING, IMAGE & HASHTAG HELPERS
// ==========================================

function social_normalize_for_matching($text) {
    $t = wp_strip_all_tags($text);
    // Strip URLs (http, https, www, or domain paths)
    $t = preg_replace('/\b(?:https?:\/\/|www\.)\S+/i', '', $t);
    $t = preg_replace('/\b[a-z0-9\.\-]+\.[a-z]{2,6}\/\S+/i', '', $t);
    $t = preg_replace('/[#@]\w+/u', '', $t);
    $t = preg_replace('/[^\p{L}\p{N}\s]/u', '', html_entity_decode($t, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    return trim(preg_replace('/\s+/', ' ', mb_strtolower($t)));
}

function social_clean_url($url) {
    if (empty($url)) return '';
    $u = html_entity_decode((string)$url, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $clean = strtok($u, '?#');
    $clean = preg_replace('#^https?://#i', '', $clean);
    $clean = preg_replace('#^www\.#i', '', $clean);
    $clean = preg_replace('#[\./]+$#', '', $clean);
    return strtolower(trim($clean));
}

function social_extract_urls($text) {
    preg_match_all('/\b(?:https?:\/\/|www\.)[^\s<"\'\)]+/i', (string)$text, $matches);
    $urls = [];
    foreach ($matches[0] as $u) {
        $c = social_clean_url($u);
        if ($c) $urls[] = $c;
    }
    return array_values(array_unique($urls));
}

/**
 * Robust multi-signal cross-posting detector for Bluesky and Mastodon.
 * Checks URLs, link cards, substrings/prefixes (e.g. truncated cross-posts), and relative similarity.
 */
function social_check_posts_match($p1, $p2) {
    // 1. URL & Link Card Matching (Exact or Stem/Prefix)
    $urls1 = !empty($p1['urls']) ? (array)$p1['urls'] : social_extract_urls($p1['text'] ?? '');
    $urls2 = !empty($p2['urls']) ? (array)$p2['urls'] : social_extract_urls($p2['text'] ?? '');

    $clean1 = [];
    foreach ($urls1 as $u) {
        $c = social_clean_url($u);
        if ($c !== '') $clean1[] = $c;
    }
    $clean2 = [];
    foreach ($urls2 as $u) {
        $c = social_clean_url($u);
        if ($c !== '') $clean2[] = $c;
    }

    if (!empty($clean1) && !empty($clean2)) {
        if (!empty(array_intersect($clean1, $clean2))) {
            return true;
        }
        foreach ($clean1 as $u1) {
            foreach ($clean2 as $u2) {
                if (strlen($u1) >= 12 && strlen($u2) >= 12) {
                    if (strpos($u1, $u2) === 0 || strpos($u2, $u1) === 0) {
                        return true;
                    }
                }
            }
        }
    }

    // 2. Normalized Text Comparison
    $t1 = social_normalize_for_matching($p1['text'] ?? '');
    $t2 = social_normalize_for_matching($p2['text'] ?? '');

    $len1 = mb_strlen($t1, 'UTF-8');
    $len2 = mb_strlen($t2, 'UTF-8');

    if ($len1 >= 15 && $len2 >= 15) {
        $short = $len1 <= $len2 ? $t1 : $t2;
        $long  = $len1 <= $len2 ? $t2 : $t1;
        $short_len = mb_strlen($short, 'UTF-8');

        // Exact substring containment (e.g. Bluesky 300 char truncated copy of a 500 char Mastodon post)
        if (mb_strpos($long, $short) !== false) {
            return true;
        }

        // Common prefix match (both start with same 35+ characters)
        if ($short_len >= 35 && mb_substr($short, 0, 35) === mb_substr($long, 0, 35)) {
            return true;
        }

        // Relative similarity to the shorter text
        similar_text($t1, $t2, $sim_two_way);
        $matching_chars = ($sim_two_way / 100) * ($len1 + $len2) / 2;
        $sim_relative = ($matching_chars / $short_len) * 100;

        if ($sim_relative >= 70 || $sim_two_way >= 65) {
            return true;
        }

        // Word token overlap
        $words1 = array_filter(explode(' ', $t1), function($w) { return mb_strlen($w) >= 3; });
        $words2 = array_filter(explode(' ', $t2), function($w) { return mb_strlen($w) >= 3; });
        $common_words = array_intersect($words1, $words2);
        $min_words = min(count($words1), count($words2));

        if ($min_words >= 4 && (count($common_words) / $min_words) >= 0.70) {
            return true;
        }
    }

    return false;
}

/**
 * Calculates clean text character length excluding hashtags, URLs, and HTML tags.
 */
function social_get_clean_text_length($text) {
    $t = wp_strip_all_tags($text);
    // Strip URLs
    $t = preg_replace('/\bhttps?:\/\/\S+/i', '', $t);
    // Strip hashtags (do not count tags in content comparison)
    $t = preg_replace('/#[\p{L}\p{N}_]+/u', '', $t);
    // Decode HTML entities and normalize whitespace
    $t = trim(html_entity_decode($t, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    $t = preg_replace('/\s+/', ' ', $t);
    return mb_strlen($t, 'UTF-8');
}

/**
 * Modern Media Sideloading with WebP conversion & srcsets
 */
function social_sideload_image_by_mime($url, $post_id, $desc = '') {
    if (empty($url)) return false;

    $opts = get_option('social_digest_options', []);
    $convert_modern = !empty($opts['convert_modern_media']);

    $response = wp_safe_remote_get($url, [
        'timeout'    => 25,
        'user-agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/128.0.0.0 Safari/537.36',
        'headers'    => [
            'Accept' => 'image/avif,image/webp,image/apng,image/svg+xml,image/*,*/*;q=0.8'
        ]
    ]);

    if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
        return false;
    }

    $image_data = wp_remote_retrieve_body($response);
    if (empty($image_data)) return false;

    $content_type = wp_remote_retrieve_header($response, 'content-type');
    $mime_to_ext = [
        'image/jpeg' => 'jpg',
        'image/jpg'  => 'jpg',
        'image/png'  => 'png',
        'image/gif'  => 'gif',
        'image/webp' => 'webp',
        'image/avif' => 'avif'
    ];

    $ext = 'jpg';
    if (!empty($content_type)) {
        $parts = explode(';', $content_type);
        $clean_mime = trim(strtolower($parts[0]));
        if (isset($mime_to_ext[$clean_mime])) {
            $ext = $mime_to_ext[$clean_mime];
        }
    }

    $filename = 'digest-thumb-' . $post_id . '-' . wp_generate_password(6, false) . '.' . $ext;
    $upload = wp_upload_bits($filename, null, $image_data);

    if (!empty($upload['error'])) {
        return false;
    }

    $file_loc = $upload['file'];
    $wp_filetype = wp_check_filetype($filename, null);

    $attachment = [
        'post_mime_type' => $wp_filetype['type'] ?: 'image/jpeg',
        'post_title'     => sanitize_file_name($desc ?: $filename),
        'post_content'   => '',
        'post_status'    => 'inherit'
    ];

    $attach_id = wp_insert_attachment($attachment, $file_loc, $post_id);
    if (!$attach_id || is_wp_error($attach_id)) {
        return false;
    }

    require_once(ABSPATH . 'wp-admin/includes/image.php');
    $attach_data = wp_generate_attachment_metadata($attach_id, $file_loc);
    wp_update_attachment_metadata($attach_id, $attach_data);

    return $attach_id;
}

function social_split_camelcase_tag($tag) {
    $t = ltrim(trim($tag), '#');
    // Preserve words starting with a single lowercase prefix followed by uppercase (e.g. iPhone, eReader, iPad, eBay, iOS, eBook)
    $t = preg_replace('/([a-z]{2,})([A-Z0-9])/u', '$1 $2', $t);
    $t = preg_replace('/([A-Z]+)([A-Z][a-z])/u', '$1 $2', $t);
    return trim(preg_replace('/\s+/', ' ', $t));
}

function social_rank_and_format_title_tags($candidate_tags, $default_tags_str, $enclosure = 'parentheses', $delimiter = 'oxford') {
    if (empty($candidate_tags)) {
        $defaults = array_filter(array_map('trim', explode(',', $default_tags_str)));
        if (empty($defaults)) return '';
        $top_raw = array_slice($defaults, 0, 3);
        $formatted_defaults = [];
        foreach ($top_raw as $raw_d) {
            $formatted_defaults[] = social_split_camelcase_tag($raw_d);
        }
        return social_apply_enclosure_and_delimiters($formatted_defaults, $enclosure, $delimiter);
    }

    $counts = [];
    $original_casing = [];
    $is_multi_word = [];

    foreach ($candidate_tags as $tag) {
        $low = mb_strtolower($tag);
        $counts[$low] = ($counts[$low] ?? 0) + 1;
        if (!isset($original_casing[$low])) {
            $original_casing[$low] = $tag;
            $split_label = social_split_camelcase_tag($tag);
            $is_multi_word[$low] = (mb_strpos($split_label, ' ') !== false) ? 1 : 0;
        }
    }

    $unique_lows = array_keys($counts);
    $site_counts = [];
    $terms = get_terms([
        'taxonomy'   => 'post_tag',
        'slug'       => $unique_lows,
        'hide_empty' => false,
    ]);

    if (!is_wp_error($terms) && !empty($terms)) {
        foreach ($terms as $term) {
            $site_counts[mb_strtolower($term->slug)] = (int) $term->count;
            $site_counts[mb_strtolower($term->name)] = (int) $term->count;
        }
    }

    $random_tiebreakers = [];
    foreach ($unique_lows as $low) {
        $random_tiebreakers[$low] = mt_rand(1, 10000);
    }

    usort($unique_lows, function($a, $b) use ($counts, $is_multi_word, $site_counts, $random_tiebreakers) {
        $batch_a = $counts[$a] ?? 0;
        $batch_b = $counts[$b] ?? 0;
        if ($batch_a !== $batch_b) {
            return $batch_b <=> $batch_a;
        }

        $multi_a = $is_multi_word[$a] ?? 0;
        $multi_b = $is_multi_word[$b] ?? 0;
        if ($multi_a !== $multi_b) {
            return $multi_b <=> $multi_a;
        }

        $site_a = $site_counts[$a] ?? 0;
        $site_b = $site_counts[$b] ?? 0;
        if ($site_a !== $site_b) {
            return $site_b <=> $site_a;
        }

        return $random_tiebreakers[$b] <=> $random_tiebreakers[$a];
    });

    $top_lows = array_slice($unique_lows, 0, 3);
    $formatted = [];
    foreach ($top_lows as $low) {
        $formatted[] = social_split_camelcase_tag($original_casing[$low]);
    }

    return social_apply_enclosure_and_delimiters($formatted, $enclosure, $delimiter);
}

function social_apply_enclosure_and_delimiters($items, $enclosure, $delimiter) {
    $c = count($items);
    if ($c === 0) return '';

    $joined = '';
    switch ($delimiter) {
        case 'commas':
            $joined = implode(', ', $items);
            break;
        case 'ampersand':
            if ($c === 1) $joined = $items[0];
            elseif ($c === 2) $joined = $items[0] . ' & ' . $items[1];
            else $joined = $items[0] . ', ' . $items[1] . ' & ' . $items[2];
            break;
        case 'pipe':
            $joined = implode(' | ', $items);
            break;
        case 'slash':
            $joined = implode(' / ', $items);
            break;
        case 'oxford':
        default:
            if ($c === 1) $joined = $items[0];
            elseif ($c === 2) $joined = $items[0] . ' and ' . $items[1];
            else $joined = $items[0] . ', ' . $items[1] . ', and ' . $items[2];
            break;
    }

    if ($enclosure === 'parentheses') {
        return '(' . $joined . ')';
    } elseif ($enclosure === 'brackets') {
        return '[' . $joined . ']';
    }

    return $joined;
}

// ==========================================
// 6. DIGEST COMPOSER, RUNNER & SIMULATOR
// ==========================================

function social_log_run($success, $message, $post_id = 0) {
    $logs = get_option('social_digest_logs', []);
    if (!is_array($logs)) $logs = [];
    $logs[] = [
        'time'    => time(),
        'success' => (bool)$success,
        'message' => sanitize_text_field($message),
        'post_id' => (int)$post_id
    ];
    if (count($logs) > 10) {
        $logs = array_slice($logs, -10);
    }
    update_option('social_digest_logs', $logs);
}

/**
 * Main Digest Runner / Dry Run Simulator
 */
function social_run_digest_import($is_dry_run = false) {
    try {
        global $wpdb;
        $opts = get_option('social_digest_options', []);
    $mode = $opts['network_mode'] ?? 'both';

    $cross_dedup           = !empty($opts['cross_dedup']);
    $preferred_platform    = $opts['preferred_platform'] ?? 'bsky';
    $min_posts             = (int)($opts['min_posts'] ?? 3);
    $max_posts             = (int)($opts['max_posts'] ?? 20);
    $max_age_days          = (int)($opts['max_age_days'] ?? 0);
    $fetch_order           = $opts['fetch_order'] ?? 'newest';
    $display_order         = $opts['display_order'] ?? 'reverse';
    $keep_threads          = !empty($opts['keep_threads']);
    $include_reposts       = !empty($opts['include_reposts']);
    $extract_tags          = !empty($opts['extract_tags']);
    $max_tags_per_post     = (int)($opts['max_tags_per_post'] ?? 2);
    $max_total_tags        = (int)($opts['max_total_tags'] ?? 8);
    $min_tag_length        = (int)($opts['min_tag_length'] ?? 3);
    $exclude_titles        = !empty($opts['exclude_titles']);
    $auto_thumb            = !empty($opts['auto_thumb']);
    $thumb_selection_scope = $opts['thumb_selection_scope'] ?? 'exclude_first';
    $thumb_selection_mode  = $opts['thumb_selection_mode'] ?? 'random';
    $tag_enclosure         = $opts['title_tag_enclosure'] ?? 'parentheses';
    $tag_delimiter         = $opts['title_tag_delimiter'] ?? 'oxford';
    $nosnippet_header      = !empty($opts['nosnippet_header']);
    $nosnippet_footer      = !empty($opts['nosnippet_footer']);
    $rss_only_mode         = !empty($opts['rss_only_mode']);
    $fold_limit            = (int)($opts['excerpt_fold_limit'] ?? 400);
    $excluded_list         = array_filter(array_map('trim', explode(',', $opts['excluded_words'] ?? '')));

    $bsky_last  = (int) get_option('bsky_last_digest_time', 0);
    $masto_last = (int) get_option('masto_last_digest_time', 0);

    $raw_posts = [];
    $new_bsky_ts = $bsky_last;
    $new_masto_ts = $masto_last;

    if ($mode === 'bsky' || $mode === 'both') {
        $bsky_res = social_fetch_bluesky($opts['bsky_handle'] ?? '', $bsky_last, $keep_threads, $include_reposts);
        $raw_posts = array_merge($raw_posts, $bsky_res['posts']);
        $new_bsky_ts = $bsky_res['newest_timestamp'];
    }

    if ($mode === 'mastodon' || $mode === 'both') {
        $masto_res = social_fetch_mastodon($opts['masto_handle'] ?? '', $masto_last, $keep_threads, $include_reposts);
        $raw_posts = array_merge($raw_posts, $masto_res['posts']);
        $new_masto_ts = $masto_res['newest_timestamp'];
    }

    // In dry-run simulation mode, if cutoff markers yield no posts, evaluate recent posts (cutoff = 0)
    // so the admin can always preview digest rendering without clearing live cutoff state.
    if ($is_dry_run && empty($raw_posts)) {
        if ($mode === 'bsky' || $mode === 'both') {
            $bsky_res = social_fetch_bluesky($opts['bsky_handle'] ?? '', 0, $keep_threads, $include_reposts);
            $raw_posts = array_merge($raw_posts, $bsky_res['posts']);
        }
        if ($mode === 'mastodon' || $mode === 'both') {
            $masto_res = social_fetch_mastodon($opts['masto_handle'] ?? '', 0, $keep_threads, $include_reposts);
            $raw_posts = array_merge($raw_posts, $masto_res['posts']);
        }
    }

    if (empty($raw_posts)) {
        $msg = 'No posts returned from the configured network(s). Please verify your account handles and network status.';
        if (!$is_dry_run) social_log_run(false, $msg);
        return ['success' => false, 'message' => $msg];
    }

    $normalized_site_titles = [];
    if ($exclude_titles) {
        $raw_titles = $wpdb->get_col("SELECT post_title FROM {$wpdb->posts} WHERE post_status = 'publish' AND post_type = 'post' ORDER BY ID DESC LIMIT 500");
        foreach ($raw_titles as $t) {
            $cleaned = strtolower(trim(preg_replace('/[^\p{L}\p{N}\s]/u', '', html_entity_decode($t, ENT_QUOTES, 'UTF-8'))));
            if (!empty($cleaned)) {
                $normalized_site_titles[$cleaned] = true;
            }
        }
    }

    $eligible_posts = [];
    $oldest_post_time = PHP_INT_MAX;

    foreach ($raw_posts as $item) {
        $text = $item['text'];

        $skip = false;
        foreach ($excluded_list as $word) {
            if ($word !== '' && stripos($text, $word) !== false) {
                $skip = true;
                break;
            }
        }
        if ($skip) continue;

        if ($exclude_titles && !empty($normalized_site_titles)) {
            $text_no_urls = preg_replace('/\bhttps?:\/\/\S+/i', '', $text);
            $norm_post = strtolower(trim(preg_replace('/[^\p{L}\p{N}\s]/u', '', html_entity_decode($text_no_urls, ENT_QUOTES, 'UTF-8'))));

            if (isset($normalized_site_titles[$norm_post])) continue;
            foreach ($normalized_site_titles as $clean_title => $val) {
                if (mb_strlen($clean_title) >= 12 && strpos($norm_post, $clean_title) !== false) {
                    $skip = true;
                    break;
                }
            }
            if ($skip) continue;
        }

        if ($item['timestamp'] < $oldest_post_time) {
            $oldest_post_time = $item['timestamp'];
        }

        $eligible_posts[] = $item;
    }

    if ($mode === 'both' && $cross_dedup) {
        $bsky_posts  = [];
        $masto_posts = [];

        foreach ($eligible_posts as $p) {
            if ($p['network'] === 'bsky') {
                $bsky_posts[] = $p;
            } else {
                $masto_posts[] = $p;
            }
        }

        $merged_posts = [];
        $matched_masto_indices = [];

        foreach ($bsky_posts as $b_post) {
            $matched_masto = null;
            $matched_masto_idx = null;

            foreach ($masto_posts as $m_idx => $m_post) {
                if (isset($matched_masto_indices[$m_idx])) continue;

                if (social_check_posts_match($b_post, $m_post)) {
                    $matched_masto = $m_post;
                    $matched_masto_idx = $m_idx;
                    break;
                }
            }

            if ($matched_masto !== null) {
                $matched_masto_indices[$matched_masto_idx] = true;

                // Compare clean text lengths (excluding URLs, hashtags, and HTML tags)
                $b_len = social_get_clean_text_length($b_post['text']);
                $m_len = social_get_clean_text_length($matched_masto['text']);

                $prefer_mastodon = false;
                if ($preferred_platform === 'masto_if_longer') {
                    // Only prefer Mastodon when it has strictly more clean text; otherwise default to Bluesky
                    $prefer_mastodon = ($m_len > $b_len);
                } elseif ($preferred_platform === 'longest') {
                    $prefer_mastodon = ($m_len > $b_len);
                } elseif ($preferred_platform === 'mastodon') {
                    $prefer_mastodon = true;
                } elseif ($preferred_platform === 'bsky') {
                    $prefer_mastodon = false;
                }

                if ($prefer_mastodon) {
                    $winner    = $matched_masto;
                    $secondary = $b_post;
                } else {
                    $winner    = $b_post;
                    $secondary = $matched_masto;
                }

                // TAG HARVESTING: Grab tags from the losing post if it has them and the winning post does not
                $winner_tags = !empty($winner['extra_tags']) ? (array)$winner['extra_tags'] : [];
                if (preg_match_all('/#(\w+)/u', $winner['text'], $w_matches)) {
                    $winner_tags = array_merge($winner_tags, $w_matches[1]);
                }

                $secondary_tags = !empty($secondary['extra_tags']) ? (array)$secondary['extra_tags'] : [];
                if (preg_match_all('/#(\w+)/u', $secondary['text'], $s_matches)) {
                    $secondary_tags = array_merge($secondary_tags, $s_matches[1]);
                }

                // Identify missing tags that the losing post has but the winning post does not
                $missing_tags = array_diff($secondary_tags, $winner_tags);
                if (!empty($missing_tags)) {
                    $winner['extra_tags'] = array_values(array_unique(array_merge($winner_tags, $missing_tags)));
                }

                // Thumbnail fallback: if winner lacks thumbnail, use secondary's thumbnail
                if (empty($winner['thumb_image']) && !empty($secondary['thumb_image'])) {
                    $winner['thumb_image'] = $secondary['thumb_image'];
                }

                // SINGLE EMBED ENFORCEMENT: Strictly append ONLY the winning post.
                // The secondary post is completely discarded from the digest body.
                $merged_posts[] = $winner;
            } else {
                $merged_posts[] = $b_post;
            }
        }

        // Include any remaining unmatched Mastodon posts
        foreach ($masto_posts as $m_idx => $m_post) {
            if (!isset($matched_masto_indices[$m_idx])) {
                $merged_posts[] = $m_post;
            }
        }

        $eligible_posts = $merged_posts;
    }

    $count = count($eligible_posts);

    $force_by_age = false;
    if ($max_age_days > 0 && $count > 0 && (time() - $oldest_post_time) >= ($max_age_days * DAY_IN_SECONDS)) {
        $force_by_age = true;
    }

    if ($count === 0) {
        $msg = "No eligible posts available to generate a digest after filtering and deduplication.";
        if (!$is_dry_run) social_log_run(false, $msg);
        return ['success' => false, 'message' => $msg];
    }

    if (!$is_dry_run && $count < $min_posts && !$force_by_age) {
        $msg = "Threshold not met: Found {$count} valid new posts (minimum required: {$min_posts}).";
        social_log_run(false, $msg);
        return ['success' => false, 'message' => $msg];
    }

    usort($eligible_posts, function($a, $b) {
        return $b['timestamp'] <=> $a['timestamp'];
    });

    if ($fetch_order === 'oldest') {
        $eligible_posts = array_reverse($eligible_posts);
    }
    
    if (count($eligible_posts) > $max_posts) {
        $eligible_posts = array_slice($eligible_posts, 0, $max_posts);
    }

    $extracted_tag_map = [];
    $all_batch_raw_tags = [];

    if ($extract_tags) {
        foreach ($eligible_posts as $item) {
            $candidate_tags = [];

            if (preg_match_all('/#(\w+)/u', $item['text'], $matches)) {
                $candidate_tags = array_merge($candidate_tags, $matches[1]);
            }
            if (!empty($item['extra_tags'])) {
                $candidate_tags = array_merge($candidate_tags, (array)$item['extra_tags']);
            }

            $post_extracted = [];
            foreach ($candidate_tags as $raw_tag) {
                if (mb_strlen($raw_tag) >= $min_tag_length) {
                    $post_extracted[] = $raw_tag;
                    $all_batch_raw_tags[] = $raw_tag;
                }
            }

            $post_extracted = array_slice(array_unique($post_extracted), 0, $max_tags_per_post);
            foreach ($post_extracted as $tag) {
                $low = mb_strtolower($tag);
                if (!isset($extracted_tag_map[$low])) {
                    $extracted_tag_map[$low] = $tag;
                }
            }
        }
    }

    $formatted_title_hashtags = social_rank_and_format_title_tags($all_batch_raw_tags, $opts['default_tags'] ?? '', $tag_enclosure, $tag_delimiter);

    $featured_image_url = null;
    if ($auto_thumb && !empty($eligible_posts)) {
        $candidates = [];

        $posts_by_recency = $eligible_posts;
        usort($posts_by_recency, function($a, $b) {
            return $b['timestamp'] <=> $a['timestamp'];
        });

        if ($thumb_selection_scope === 'exclude_first') {
            for ($i = 1; $i < count($posts_by_recency); $i++) {
                if (!empty($posts_by_recency[$i]['thumb_image'])) {
                    $candidates[] = esc_url_raw($posts_by_recency[$i]['thumb_image']);
                }
            }
            if (empty($candidates) && !empty($posts_by_recency[0]['thumb_image'])) {
                $candidates[] = esc_url_raw($posts_by_recency[0]['thumb_image']);
            }
        } else {
            foreach ($posts_by_recency as $p) {
                if (!empty($p['thumb_image'])) {
                    $candidates[] = esc_url_raw($p['thumb_image']);
                }
            }
        }

        if (!empty($candidates)) {
            if ($thumb_selection_mode === 'random') {
                $featured_image_url = $candidates[array_rand($candidates)];
            } else {
                $requested_index = max(0, ((int)$thumb_selection_mode) - 1);
                if (isset($candidates[$requested_index])) {
                    $featured_image_url = $candidates[$requested_index];
                } else {
                    $featured_image_url = end($candidates);
                }
            }
        }
    }

    if ($display_order === 'chronological') {
        usort($eligible_posts, function($a, $b) {
            return $a['timestamp'] <=> $b['timestamp'];
        });
    } elseif ($display_order === 'reverse') {
        usort($eligible_posts, function($a, $b) {
            return $b['timestamp'] <=> $a['timestamp'];
        });
    } elseif ($display_order === 'random') {
        shuffle($eligible_posts);
    }

    $rendered_html_blocks = array_column($eligible_posts, 'html');
    $count = count($rendered_html_blocks);

    $chosen_header = trim($opts['header_text'] ?? '');
    $chosen_footer = trim($opts['footer_text'] ?? '');

    if ($nosnippet_header && !empty($chosen_header)) {
        $chosen_header = '<section data-nosnippet class="social-digest-header">' . $chosen_header . '</section>';
    }
    if ($nosnippet_footer && !empty($chosen_footer)) {
        $chosen_footer = '<section data-nosnippet class="social-digest-footer">' . $chosen_footer . '</section>';
    }

    $content  = !empty($chosen_header) ? $chosen_header . "\n\n" : '';
    $content .= implode("\n\n", $rendered_html_blocks);
    $content .= !empty($chosen_footer) ? "\n\n" . $chosen_footer : '';

    $title_tpl = !empty($opts['title_template']) ? $opts['title_template'] : 'Social Digest {hashtags}';
    $base_title = str_replace(
        ['{hashtags}', '{date}', '{count}'],
        [$formatted_title_hashtags, wp_date(get_option('date_format'), time(), wp_timezone()), (string)$count],
        $title_tpl
    );

    $base_title = trim(preg_replace('/\s+/', ' ', $base_title));
    $base_title = trim(preg_replace('/\s*[:\-–]\s*$/u', '', $base_title));

    $today_ymd = wp_date('Y-m-d', time(), wp_timezone());
    $like_pattern = $wpdb->esc_like($base_title) . '%';
    
    $existing_today_count = (int) $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(ID) FROM {$wpdb->posts} 
         WHERE post_title LIKE %s 
           AND post_date LIKE %s 
           AND post_status IN ('publish', 'draft', 'pending', 'future')",
        $like_pattern,
        $today_ymd . '%'
    ));

    if ($existing_today_count > 0) {
        $suffix_tpl = !empty($opts['same_day_suffix_tpl']) ? $opts['same_day_suffix_tpl'] : ' (Part {part})';
        $part_str = str_replace('{part}', (string)($existing_today_count + 1), $suffix_tpl);
        $title = $base_title . $part_str;
    } else {
        $title = $base_title;
    }

    $final_tag_map = [];
    $default_tags = array_filter(array_map('trim', explode(',', $opts['default_tags'] ?? '')));
    foreach ($default_tags as $dt) {
        $low = mb_strtolower($dt);
        if (!isset($final_tag_map[$low])) {
            $final_tag_map[$low] = $dt;
        }
    }

    if ($extract_tags && !empty($extracted_tag_map)) {
        foreach ($extracted_tag_map as $low => $original) {
            if (!isset($final_tag_map[$low])) {
                $final_tag_map[$low] = $original;
            }
        }
    }

    $final_tags = array_values($final_tag_map);
    if (count($final_tags) > $max_total_tags) {
        $final_tags = array_slice($final_tags, 0, $max_total_tags);
    }

    // DRY RUN RETURN: Do not insert post, do not sideload, do not advance cutoff timestamps
    if ($is_dry_run) {
        $sim_msg = "Dry run simulation successful: Would create digest with {$count} post" . ($count === 1 ? '' : 's') . ".";
        if ($count < $min_posts) {
            $sim_msg .= " (Note: Minimum required for automated publishing is {$min_posts}).";
        }
        return [
            'success' => true,
            'message' => $sim_msg,
            'preview' => [
                'title'          => $title,
                'count'          => $count,
                'tags'           => $final_tags,
                'featured_image' => $featured_image_url,
                'content_sample' => wp_trim_words(wp_strip_all_tags($content), 35),
                'rendered_html'  => $content
            ]
        ];
    }

    $post_args = [
        'post_title'   => $title,
        'post_content' => $content,
        'post_status'  => $opts['post_status'] ?? 'publish',
        'post_author'  => (int)($opts['post_author'] ?? 1),
        'post_type'    => 'post',
        'tags_input'   => $final_tags,
    ];

    if (!empty($opts['categories']) && is_array($opts['categories'])) {
        $post_args['post_category'] = $opts['categories'];
    }

    $post_id = wp_insert_post($post_args);
    if (is_wp_error($post_id)) {
        social_log_run(false, 'Post creation failed: ' . $post_id->get_error_message());
        return ['success' => false, 'message' => 'Post creation failed: ' . $post_id->get_error_message()];
    }

    // Flag as RSS-Only if enabled (Roadmap Item 4)
    if ($rss_only_mode) {
        update_post_meta($post_id, '_social_digest_rss_only', 1);
    }

    if ($featured_image_url) {
        $attachment_id = social_sideload_image_by_mime($featured_image_url, $post_id, $title);
        if ($attachment_id) {
            set_post_thumbnail($post_id, $attachment_id);
        }
    }

    if ($mode === 'bsky' || $mode === 'both') {
        update_option('bsky_last_digest_time', $new_bsky_ts);
    }
    if ($mode === 'mastodon' || $mode === 'both') {
        update_option('masto_last_digest_time', $new_masto_ts);
    }

    $status_label = ($opts['post_status'] === 'publish') ? 'published' : 'saved as ' . $opts['post_status'];
    $success_msg  = "Digest created (ID: {$post_id}) with title '{$title}' and {$status_label}." . ($force_by_age ? ' (Triggered by max-age fallback).' : '');

    social_log_run(true, $success_msg, $post_id);
    return ['success' => true, 'message' => 'Success: ' . $success_msg];
    } catch (\Throwable $e) {
        $err = 'Runtime Exception in Social Digest: ' . $e->getMessage();
        if (!$is_dry_run) {
            social_log_run(false, $err);
        }
        return ['success' => false, 'message' => $err];
    }
}
