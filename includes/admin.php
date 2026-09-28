<?php
namespace SocialDigest;

if (!defined('ABSPATH')) exit;

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
add_action('admin_bar_menu', function($wp_admin_bar) {
    if (!is_object($wp_admin_bar) || !method_exists($wp_admin_bar, 'add_node') || !current_user_can('manage_options')) return;

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
            'fediverse_creator'      => '',
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
            'cache_local_assets'     => 1,
            'generate_srcsets'       => 1,
            'sideload_all_media'     => 0,
            'gallery_click_action'   => 'lightbox',
            'rss_only_mode'          => 0,
            'output_gutenberg_blocks'=> 1,
            'dark_mode_mode'         => 'auto',
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
            'collapse_threads'       => 1,
            'thread_default_state'   => 'collapsed',
            'mobile_deep_links'      => 1,
            'include_reposts'        => 0,
            'exclude_titles'         => 0,
            'exclude_self_syndicated'=> 1,
            'title_template'               => 'Social Digest {hashtags}',
            'title_tag_enclosure'          => 'parentheses',
            'title_tag_delimiter'          => 'oxford',
            'title_tag_max_count'          => 3,
            'title_tag_selection_strategy' => 'first',
            'title_tag_custom_overrides'   => social_get_default_tag_overrides_string(),
            'overrides_snapshot'           => [],
            'same_day_suffix_tpl'    => ' (Part {part})',
            'excluded_words'         => '#ad, sponsored',
            'header_text'            => '<p>Here is what we shared across social channels today:</p>',
            'footer_text'            => '<hr><p>Follow us directly on social media for real-time updates!</p>',
            'excerpt_first_lines'    => 0,
            'excerpt_delimiter'      => 'slash',
            'excerpt_custom_delimiter' => ' // ',
            'excerpt_max_items'      => 0,
            'excerpt_append_ellipsis'=> 0,
            'nosnippet_header'               => 1,
            'nosnippet_footer'               => 1,
            'allow_snippet_on_custom_header' => 0,
            'allow_snippet_on_custom_footer' => 0,
            'wipe_data_on_uninstall'         => 1
        ]
    ]);

    // Ensure built-in baseline terms are populated if custom overrides option is empty or missing
    $stored_opts = get_option('social_digest_options', []);
    if (is_array($stored_opts) && (!isset($stored_opts['title_tag_custom_overrides']) || trim((string)$stored_opts['title_tag_custom_overrides']) === '')) {
        $stored_opts['title_tag_custom_overrides'] = social_get_default_tag_overrides_string();
        update_option('social_digest_options', $stored_opts);
    }

    // Workbench Form Handlers
    if (!current_user_can('manage_options')) return;
    if (empty($_GET['page']) || $_GET['page'] !== 'social-digest-settings') return;

    if (isset($_POST['sd53_fetch']) && check_admin_referer('sd53_workbench_action', 'sd53_nonce')) {
        $result = social_fetch_workbench_candidates();
        add_settings_error('sd53', 'fetch', $result['message'], !empty($result['success']) ? 'updated' : 'error');
    }

    if (isset($_POST['sd53_cleanup_avatars']) && check_admin_referer('sd53_cleanup_avatars_action', 'sd53_cleanup_nonce')) {
        $result = social_cleanup_duplicate_avatars();
        add_settings_error('sd53', 'cleanup_avatars', $result['message'], !empty($result['success']) ? 'updated' : 'notice');
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

// AJAX handler for saving on-site backup snapshot of tag override rules
add_action('wp_ajax_sd_save_snapshot', function() {
    check_ajax_referer('sd_snapshot_action', 'nonce');
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'Unauthorized']);
    }

    $raw_content = isset($_POST['content']) ? (string)$_POST['content'] : '';
    $clean_content = sanitize_textarea_field($raw_content);

    $lines = array_filter(array_map('trim', explode("\n", $clean_content)), function($l) {
        return $l !== '' && strpos($l, '#') !== 0;
    });
    $count = count($lines);
    $time = time();
    $tz = wp_timezone();
    $time_str = wp_date('Y-m-d H:i:s T', $time, $tz);

    $snapshot = [
        'content'  => $clean_content,
        'time'     => $time,
        'time_str' => $time_str,
        'count'    => $count,
    ];

    $opts = get_option('social_digest_options', []);
    if (!is_array($opts)) {
        $opts = [];
    }
    $opts['overrides_snapshot'] = $snapshot;
    update_option('social_digest_options', $opts);

    wp_send_json_success([
        'message'  => "On-site backup snapshot saved to database! ({$count} active rules on {$time_str})",
        'snapshot' => $snapshot,
    ]);
});

function social_sanitize_settings($input) {
    $output = [];
    $output['network_mode']       = in_array($input['network_mode'] ?? '', ['bsky', 'mastodon', 'both']) ? $input['network_mode'] : 'both';
    $output['avatar_source']      = in_array($input['avatar_source'] ?? '', ['auto', 'bsky', 'mastodon', 'none'], true) ? $input['avatar_source'] : 'auto';
    $output['bsky_handle']        = sanitize_text_field($input['bsky_handle'] ?? '');
    $output['masto_handle']       = sanitize_text_field($input['masto_handle'] ?? '');
    $output['fediverse_creator']  = sanitize_text_field($input['fediverse_creator'] ?? '');
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

    $output['cache_local_assets']   = !empty($input['cache_local_assets']) ? 1 : 0;
    $output['generate_srcsets']     = !empty($input['generate_srcsets']) ? 1 : 0;
    $output['sideload_all_media']   = !empty($input['sideload_all_media']) ? 1 : 0;
    $allowed_gallery_actions        = ['lightbox', 'file', 'social', 'none'];
    $output['gallery_click_action'] = in_array($input['gallery_click_action'] ?? '', $allowed_gallery_actions, true) ? $input['gallery_click_action'] : 'lightbox';
    $allowed_dark_modes             = ['auto', 'light', 'dark'];
    $output['dark_mode_mode']       = in_array($input['dark_mode_mode'] ?? '', $allowed_dark_modes, true) ? $input['dark_mode_mode'] : 'auto';

    $output['rss_only_mode']        = !empty($input['rss_only_mode']) ? 1 : 0;
    $output['output_gutenberg_blocks'] = !empty($input['output_gutenberg_blocks']) ? 1 : 0;

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
    $output['collapse_threads']    = !empty($input['collapse_threads']) ? 1 : 0;
    $allowed_thread_states         = ['collapsed', 'expanded'];
    $output['thread_default_state']= in_array($input['thread_default_state'] ?? '', $allowed_thread_states, true) ? $input['thread_default_state'] : 'collapsed';
    $output['mobile_deep_links']   = !empty($input['mobile_deep_links']) ? 1 : 0;
    $output['include_reposts']     = !empty($input['include_reposts']) ? 1 : 0;
    $output['exclude_titles']      = !empty($input['exclude_titles']) ? 1 : 0;
    $output['exclude_self_syndicated'] = !empty($input['exclude_self_syndicated']) ? 1 : 0;
    $output['title_template']      = sanitize_text_field($input['title_template'] ?? 'Social Digest {hashtags}');
    
    $allowed_enclosures = ['parentheses', 'brackets', 'none'];
    $output['title_tag_enclosure'] = in_array($input['title_tag_enclosure'] ?? '', $allowed_enclosures, true) ? $input['title_tag_enclosure'] : 'parentheses';

    $allowed_delimiters = ['oxford', 'commas', 'ampersand', 'pipe', 'slash'];
    $output['title_tag_delimiter'] = in_array($input['title_tag_delimiter'] ?? '', $allowed_delimiters, true) ? $input['title_tag_delimiter'] : 'oxford';

    $output['title_tag_max_count'] = min(5, max(1, absint($input['title_tag_max_count'] ?? 3)));

    $allowed_strategies = ['first', 'popularity', 'random'];
    $output['title_tag_selection_strategy'] = in_array($input['title_tag_selection_strategy'] ?? '', $allowed_strategies, true) ? $input['title_tag_selection_strategy'] : 'first';

    $output['title_tag_custom_overrides']= sanitize_textarea_field($input['title_tag_custom_overrides'] ?? '');

    if (isset($input['overrides_snapshot'])) {
        $decoded = json_decode(stripslashes((string)$input['overrides_snapshot']), true);
        if (is_array($decoded) && isset($decoded['content'])) {
            $output['overrides_snapshot'] = [
                'content'   => sanitize_textarea_field($decoded['content']),
                'time'      => absint($decoded['time'] ?? time()),
                'time_str'  => sanitize_text_field($decoded['time_str'] ?? ''),
                'count'     => absint($decoded['count'] ?? 0),
            ];
        } else {
            $output['overrides_snapshot'] = $opts['overrides_snapshot'] ?? [];
        }
    } else {
        $output['overrides_snapshot'] = $opts['overrides_snapshot'] ?? [];
    }

    if (isset($input['same_day_suffix_tpl'])) {
        $output['same_day_suffix_tpl'] = sanitize_text_field($input['same_day_suffix_tpl']);
    } else {
        $output['same_day_suffix_tpl'] = $opts['same_day_suffix_tpl'] ?? ' (Part {part})';
    }
    $output['excluded_words']      = sanitize_textarea_field($input['excluded_words'] ?? '');

    if (isset($input['header_text'])) {
        $output['header_text'] = wp_kses_post(trim($input['header_text']));
    } else {
        $output['header_text'] = $opts['header_text'] ?? '<p>Here is what we shared across social channels today:</p>';
    }

    if (isset($input['footer_text'])) {
        $output['footer_text'] = wp_kses_post(trim($input['footer_text']));
    } else {
        $output['footer_text'] = $opts['footer_text'] ?? '<hr><p>Follow us directly on social media for real-time updates!</p>';
    }

    $output['nosnippet_header']               = isset($input['nosnippet_header']) ? (!empty($input['nosnippet_header']) ? 1 : 0) : 1;
    $output['nosnippet_footer']               = isset($input['nosnippet_footer']) ? (!empty($input['nosnippet_footer']) ? 1 : 0) : 1;
    $output['allow_snippet_on_custom_header'] = !empty($input['allow_snippet_on_custom_header']) ? 1 : 0;
    $output['allow_snippet_on_custom_footer'] = !empty($input['allow_snippet_on_custom_footer']) ? 1 : 0;
    $output['wipe_data_on_uninstall']         = !empty($input['wipe_data_on_uninstall']) ? 1 : 0;

    $output['excerpt_first_lines']      = !empty($input['excerpt_first_lines']) ? 1 : 0;
    $output['excerpt_append_ellipsis']  = !empty($input['excerpt_append_ellipsis']) ? 1 : 0;
    $allowed_excerpt_delims             = ['slash', 'ellipsis', 'period', 'dash', 'bullet', 'custom'];
    $output['excerpt_delimiter']        = in_array($input['excerpt_delimiter'] ?? '', $allowed_excerpt_delims, true) ? $input['excerpt_delimiter'] : 'slash';
    $output['excerpt_custom_delimiter'] = sanitize_text_field($input['excerpt_custom_delimiter'] ?? ' // ');
    $output['excerpt_max_items']        = absint($input['excerpt_max_items'] ?? 0);

    social_reschedule_cron($output);

    return $output;
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
            cursor: pointer;
        }
        .postbox.closed .postbox-header {
            border-bottom: none;
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
        .postbox .handlediv {
            background: transparent;
            border: none;
            cursor: pointer;
            width: 32px;
            height: 32px;
            padding: 0;
            margin: -4px -6px -4px 8px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            color: #64748b;
            border-radius: 4px;
            transition: color 0.15s ease, background 0.15s ease;
        }
        .postbox .handlediv:hover,
        .postbox .handlediv:focus {
            color: #0f172a;
            background: #e2e8f0;
            outline: none;
        }
        .postbox .handlediv .toggle-indicator::before {
            content: "\f142";
            display: inline-block;
            font: normal 20px/1 dashicons;
            speak: never;
            -webkit-font-smoothing: antialiased;
            text-decoration: inherit;
        }
        .postbox.closed .handlediv .toggle-indicator::before {
            content: "\f140";
        }
        .postbox.closed .inside,
        .postbox.closed > :not(.postbox-header) {
            display: none !important;
        }
        .postbox .inside { padding: 16px 20px; margin: 0; }
        .social-sortable-placeholder {
            border: 2px dashed #2271b1 !important;
            background: #f0f6fc !important;
            min-height: 50px;
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
                                            <tr>
                                                <th><label for="social_fediverse_creator">Fediverse Attribution</label></th>
                                                <td>
                                                    <input name="social_digest_options[fediverse_creator]" type="text" id="social_fediverse_creator" value="<?php echo esc_attr($opts['fediverse_creator'] ?? ''); ?>" class="regular-text" placeholder="@user@instance.social" />
                                                    <p class="description">Adds <code>&lt;meta name="fediverse:creator" content="@user@instance.social"&gt;</code> to published digest posts. Supported by Mastodon 4.3+ and Threads to attribute author profiles on shared links.</p>
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
                                                <th>Content Exclusions</th>
                                                <td>
                                                    <textarea name="social_digest_options[excluded_words]" rows="3" class="large-text" placeholder="#ad, sponsored, /giveaway/i, #crypto"><?php echo esc_textarea($opts['excluded_words'] ?? ''); ?></textarea>
                                                    <p class="description">Exclude posts matching specific criteria (comma-separated or one per line). Supports <strong>plain keywords</strong> (e.g. <code>sponsored</code>), <strong>hashtags</strong> (e.g. <code>#ad</code>), and <strong>regular expressions</strong> (e.g. <code>/giveaway/i</code> or <code>/\bcontest\b/i</code>).</p>
                                                </td>
                                            </tr>
                                            <tr>
                                                <th>Feed Rules</th>
                                                <td>
                                                    <label><input type="checkbox" name="social_digest_options[include_reposts]" value="1" <?php checked($opts['include_reposts'] ?? 0, 1); ?> /> Include Reposts / Boosts</label><br>
                                                    <label><input type="checkbox" name="social_digest_options[exclude_self_syndicated]" value="1" <?php checked($opts['exclude_self_syndicated'] ?? 1, 1); ?> /> Exclude self-syndicated posts (posts linking back to this WordPress site)</label><br>
                                                    <label><input type="checkbox" name="social_digest_options[exclude_titles]" value="1" <?php checked($opts['exclude_titles'] ?? 0, 1); ?> /> Exclude posts matching existing WordPress headlines</label><br>
                                                    <label><input type="checkbox" name="social_digest_options[keep_threads]" value="1" <?php checked($opts['keep_threads'] ?? 1, 1); ?> /> Include self-replies / threads</label><br>
                                                     <label><input type="checkbox" name="social_digest_options[collapse_threads]" value="1" <?php checked(!isset($opts['collapse_threads']) || !empty($opts['collapse_threads'])); ?> /> Collapse multi-post author threads into single unified cards</label><br>
                                                     <div style="margin: 6px 0 8px 24px;">
                                                         <label style="font-weight:600; font-size:12px; margin-right:6px;">Default Thread State:</label>
                                                         <select name="social_digest_options[thread_default_state]" id="social_thread_default_state">
                                                             <option value="collapsed" <?php selected($opts['thread_default_state'] ?? 'collapsed', 'collapsed'); ?>>Collapsed (readers click to expand follow-up posts)</option>
                                                             <option value="expanded" <?php selected($opts['thread_default_state'] ?? '', 'expanded'); ?>>Expanded (follow-up posts visible by default)</option>
                                                         </select>
                                                         <p class="description" style="margin:2px 0 0 0; font-size:11px;">Controls whether unified thread cards render with follow-up replies initially expanded or collapsed within the post.</p>
                                                     </div>
                                                     <label><input type="checkbox" name="social_digest_options[mobile_deep_links]" value="1" <?php checked(!isset($opts['mobile_deep_links']) || !empty($opts['mobile_deep_links'])); ?> /> Enable smart mobile app deep-linking (opens native Bluesky or Mastodon app if installed; falls back to browser without extra buttons)</label>
                                                </td>
                                            </tr>
                                            <tr>
                                                <th>Ingestion Fetch Order</th>
                                                 <td>
                                                     <select name="social_digest_options[fetch_order]" id="social_fetch_order">
                                                         <option value="newest" <?php selected($opts['fetch_order'] ?? 'newest', 'newest'); ?>>Newest Posts First</option>
                                                         <option value="oldest" <?php selected($opts['fetch_order'] ?? '', 'oldest'); ?>>Oldest Posts First</option>
                                                     </select>
                                                     <p class="description" style="margin-top:4px;">Controls the API fetch traversal sequence when pulling timeline entries from Mastodon and Bluesky servers.</p>
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
                                                <th>Cache &amp; Storage</th>
                                                <td>
                                                    <label><input type="checkbox" name="social_digest_options[cache_local_assets]" value="1" <?php checked($opts['cache_local_assets'] ?? 1, 1); ?> /> Cache remote avatars &amp; cards locally</label><br>
                                                    <label><input type="checkbox" name="social_digest_options[generate_srcsets]" value="1" <?php checked($opts['generate_srcsets'] ?? 1, 1); ?> /> Generate standard responsive srcset sizes</label><br>
                                                    <label><input type="checkbox" name="social_digest_options[sideload_all_media]" value="1" <?php checked($opts['sideload_all_media'] ?? 0, 1); ?> /> <strong>Sideload all embedded post media into WordPress Media Library</strong></label>
                                                    <p class="description">Automatically downloads and imports all embedded update images directly into the local Media Library as stock assets when publishing or drafting, protecting against external link rot and server outages.</p>
                                                </td>
                                            </tr>
                                            <tr>
                                                <th>Gallery Image Click</th>
                                                <td>
                                                    <select name="social_digest_options[gallery_click_action]">
                                                        <option value="lightbox" <?php selected($opts['gallery_click_action'] ?? 'lightbox', 'lightbox'); ?>>Lightbox (Auto-detects Responsive Lightbox / Core Lightbox with standalone fallback)</option>
                                                        <option value="file" <?php selected($opts['gallery_click_action'] ?? '', 'file'); ?>>Direct Image File (plain links to full-resolution image)</option>
                                                        <option value="social" <?php selected($opts['gallery_click_action'] ?? '', 'social'); ?>>Original Social Post (legacy: opens Bluesky / Mastodon post)</option>
                                                        <option value="none" <?php selected($opts['gallery_click_action'] ?? '', 'none'); ?>>None (display only, unlinked)</option>
                                                    </select>
                                                    <p class="description">Controls what happens when readers click an embedded gallery or update image. When set to <strong>Lightbox</strong>, images open seamlessly in your site's active lightbox plugin (such as Responsive Lightbox) with a lightweight zero-dependency on-page fallback viewer if no lightbox plugin is present.</p>
                                                </td>
                                            </tr>
                                            <tr>
                                                <th>Avatar Storage &amp; Hygiene</th>
                                                <td>
                                                    <div style="display:flex; flex-wrap:wrap; align-items:center; gap:8px; margin-bottom:8px;">
                                                        <span class="dashicons dashicons-id" style="color:#0284c7; font-size:18px; width:18px; height:18px;"></span>
                                                        <span><strong>Persistent Master Avatar Deduplication:</strong> Active (Avatars are stored once as unattached master assets and reused across all editions).</span>
                                                    </div>
                                                    <p class="description" style="margin-bottom:12px;">When updates from the same account are ingested, Social Digest automatically reuses the existing cached avatar from the Media Library rather than creating duplicates. Avatars are maintained as unattached assets so deleting individual digest posts will never break shared author images.</p>
                                                    
                                                    <div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:6px; padding:10px 14px; display:inline-flex; align-items:center; gap:12px;">
                                                        <?php wp_nonce_field('sd53_cleanup_avatars_action', 'sd53_cleanup_nonce'); ?>
                                                        <button type="submit" name="sd53_cleanup_avatars" value="1" formaction="<?php echo esc_url(admin_url('edit.php?page=social-digest-settings&tab=settings')); ?>" class="button button-secondary" onclick="return confirm('Scan and purge duplicate avatar files from the Media Library? Any digest posts referencing duplicate avatar URLs will be automatically updated to reference the retained master avatar.');">
                                                            <span class="dashicons dashicons-trash" style="vertical-align:text-top; font-size:16px; width:16px; height:16px; margin-right:4px;"></span> Clean Up Duplicate Avatars
                                                        </button>
                                                        <span style="font-size:12px; color:#64748b;">Deletes older duplicate avatar files from disk and consolidates digests to one master unattached avatar.</span>
                                                    </div>
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
                                <!-- PUBLISHING & OUTPUT FORMAT -->
                                <div class="postbox" id="social_box_publishing" style="border-left: 5px solid #ec4899;">
                                    <div class="postbox-header">
                                        <h2 class="hndle">
                                            <span class="dashicons dashicons-admin-post" style="color:#ec4899; margin-right:4px;"></span>
                                            Publishing &amp; Output Format
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
                                                <th>Block Editor Markup</th>
                                                <td>
                                                    <label><input type="checkbox" name="social_digest_options[output_gutenberg_blocks]" value="1" <?php checked($opts['output_gutenberg_blocks'] ?? 1, 1); ?> /> <strong>Format content as native WordPress Gutenberg blocks</strong></label>
                                                    <p class="description">Wraps digest headers, social post cards, and footers into native <code>&lt;!-- wp:core/html --&gt;</code> and paragraph block structures so published digests can be edited smoothly in the block editor without "Attempt Block Recovery" warnings.</p>
                                                </td>
                                            </tr>
                                            <tr>
                                                <th>Dark Mode Adaptability</th>
                                                <td>
                                                    <select name="social_digest_options[dark_mode_mode]" id="social_dark_mode_mode">
                                                        <option value="auto" <?php selected($opts['dark_mode_mode'] ?? 'auto', 'auto'); ?>>Automatic (Dynamic Website Theme &amp; Preference Matching)</option>
                                                        <option value="light" <?php selected($opts['dark_mode_mode'] ?? '', 'light'); ?>>Force Light Theme</option>
                                                        <option value="dark" <?php selected($opts['dark_mode_mode'] ?? '', 'dark'); ?>>Force High-Contrast Dark Theme</option>
                                                    </select>
                                                    <p class="description">Controls card background and typography contrast. <strong>Automatic</strong> mode intelligently checks active website themes (<code>.dark</code>, <code>[data-theme="dark"]</code>, computed background luminance) and browser/OS preferences, ensuring cards never stay dark on a light website (or vice-versa).</p>
                                                </td>
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
                                                <th>Uninstallation Policy</th>
                                                <td><label><input type="checkbox" name="social_digest_options[wipe_data_on_uninstall]" value="1" <?php checked($opts['wipe_data_on_uninstall'] ?? 1, 1); ?> /> Delete settings on uninstall</label></td>
                                            </tr>
                                        </table>
                                    </div>
                                </div>

                                <!-- POST TITLE & VOCABULARY ENGINE -->
                                <div class="postbox" id="social_box_titles" style="border-left: 5px solid #8b5cf6;">
                                    <div class="postbox-header">
                                        <h2 class="hndle">
                                            <span class="dashicons dashicons-heading" style="color:#8b5cf6; margin-right:4px;"></span>
                                            Post Title &amp; Vocabulary Engine
                                        </h2>
                                        <button type="button" class="handlediv" aria-expanded="true"><span class="toggle-indicator" aria-hidden="true"></span></button>
                                    </div>
                                    <div class="inside">
                                        <table class="form-table">
                                            <tr>
                                                <th>Title Template</th>
                                                <td>
                                                    <input name="social_digest_options[title_template]" type="text" value="<?php echo esc_attr($opts['title_template'] ?? 'Social Digest {hashtags}'); ?>" class="regular-text" />
                                                    <p class="description">Available variables: <code>{hashtags}</code>, <code>{date}</code>, <code>{count}</code></p>
                                                </td>
                                            </tr>
                                            <tr>
                                                <th>Same-Day Suffix</th>
                                                <td>
                                                    <input name="social_digest_options[same_day_suffix_tpl]" type="text" value="<?php echo esc_attr($opts['same_day_suffix_tpl'] ?? ' (Part {part})'); ?>" class="regular-text" style="width: 260px;" placeholder="Leave blank for no suffix" />
                                                    <p class="description">
                                                        Optional. Appended to the post title if multiple digests are published on the same calendar day (e.g., <code> (Part {part})</code>, <code> - Edition {part}</code>, or <code> #{part}</code>).<br />
                                                        <strong>Leave blank to disable:</strong> When left empty, same-day digests will retain the base title without adding a part suffix. Available variables: <code>{part}</code> or <code>{count}</code> for the part number.
                                                    </p>
                                                </td>
                                            </tr>
                                            <tr>
                                                <th>Hashtag Formatting Rules</th>
                                                <td>
                                                    <div style="padding: 12px; background: #f6f7f7; border: 1px solid #ccd0d4; border-radius: 4px; max-width: 550px;">
                                                        <div style="margin-bottom: 8px;">
                                                            <label style="display:inline-block; width: 150px; font-weight:600;">Selection Strategy:</label>
                                                            <select name="social_digest_options[title_tag_selection_strategy]">
                                                                <option value="first" <?php selected($opts['title_tag_selection_strategy'] ?? 'first', 'first'); ?>>First Hashtag in Post</option>
                                                                <option value="popularity" <?php selected($opts['title_tag_selection_strategy'] ?? '', 'popularity'); ?>>Taxonomy Popularity / Frequency</option>
                                                                <option value="random" <?php selected($opts['title_tag_selection_strategy'] ?? '', 'random'); ?>>Random Hashtag in Post</option>
                                                            </select>
                                                        </div>
                                                        <div style="margin-bottom: 8px;">
                                                            <label style="display:inline-block; width: 150px; font-weight:600;">Max Tags in Title:</label>
                                                            <select name="social_digest_options[title_tag_max_count]">
                                                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                                                    <option value="<?php echo $i; ?>" <?php selected((int)($opts['title_tag_max_count'] ?? 3), $i); ?>><?php echo $i; ?> <?php echo $i === 1 ? 'tag' : 'tags'; ?></option>
                                                                <?php endfor; ?>
                                                            </select>
                                                        </div>
                                                        <div style="margin-bottom: 8px;">
                                                            <label style="display:inline-block; width: 150px; font-weight:600;">Enclosure:</label>
                                                            <select name="social_digest_options[title_tag_enclosure]">
                                                                <option value="parentheses" <?php selected($opts['title_tag_enclosure'] ?? 'parentheses', 'parentheses'); ?>>(Tag 1, Tag 2)</option>
                                                                <option value="brackets" <?php selected($opts['title_tag_enclosure'] ?? '', 'brackets'); ?>>[Tag 1, Tag 2]</option>
                                                                <option value="none" <?php selected($opts['title_tag_enclosure'] ?? '', 'none'); ?>>Tag 1, Tag 2 (No Enclosure)</option>
                                                            </select>
                                                        </div>
                                                        <div>
                                                            <label style="display:inline-block; width: 150px; font-weight:600;">Delimiter:</label>
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
                                                <th>Custom Tag Title Overrides</th>
                                                <td>
                                                    <div style="max-width: 620px;">
                                                        <!-- Top Action Bar: Title & Search -->
                                                        <div style="display: flex; flex-wrap: wrap; justify-content: space-between; align-items: center; gap: 8px; margin-bottom: 8px;">
                                                            <div style="display: flex; align-items: center; gap: 6px;">
                                                                <label style="font-weight:600; font-size: 13px;">Tag Override Rules:</label>
                                                                <span style="font-size: 11px; color: #64748b;" id="sd_overrides_line_count"></span>
                                                            </div>
                                                            
                                                            <!-- Lightweight Search Box -->
                                                            <div style="display: flex; align-items: center; gap: 4px; background: #fff; border: 1px solid #ccd0d4; border-radius: 4px; padding: 2px 6px;">
                                                                <span class="dashicons dashicons-search" style="font-size: 16px; width: 16px; height: 16px; color: #64748b; line-height: 1.3;"></span>
                                                                <input type="search" id="sd_overrides_search" placeholder="Find in rules (e.g. XPS)..." style="border: none; background: transparent; font-size: 11px; padding: 1px 4px; width: 140px; outline: none; box-shadow: none;" oninput="sdSearchOverrides(this.value)" onkeydown="if(event.key==='Enter'){event.preventDefault();sdSearchNextOverride();}">
                                                                <span id="sd_search_count" style="font-size: 10px; color: #0284c7; font-weight: 600; min-width: 14px; text-align: center;"></span>
                                                                <button type="button" class="button button-small" style="height: 20px; line-height: 18px; padding: 0 4px; font-size: 10px;" onclick="sdSearchNextOverride()" title="Find Next Match (Enter)">↓</button>
                                                            </div>
                                                        </div>

                                                        <!-- Secondary Action Bar: Sort & Import/Export -->
                                                        <div style="display: flex; flex-wrap: wrap; justify-content: space-between; align-items: center; gap: 6px; margin-bottom: 6px;">
                                                            <div style="display: flex; gap: 4px; flex-wrap: wrap;">
                                                                <button type="button" class="button button-small" onclick="sdSortOverrides('az')" title="Sort rules alphabetically A to Z">🔤 Sort A→Z</button>
                                                                <button type="button" class="button button-small" onclick="sdSortOverrides('za')" title="Sort rules Z to A">🔤 Sort Z→A</button>
                                                                <button type="button" class="button button-small" onclick="sdSortOverrides('reverse')" title="Reverse order of rules (useful for recency / swapping newest to top)">🔄 Reverse Order</button>
                                                                <button type="button" class="button button-small" onclick="sdResetOverrides()" title="Restore the built-in baseline terms">↺ Reset Baseline</button>
                                                            </div>
                                                            <div style="display: flex; gap: 4px; flex-wrap: wrap; align-items: center;">
                                                                <?php 
                                                                $snap = $opts['overrides_snapshot'] ?? []; 
                                                                $has_snap = !empty($snap['content']); 
                                                                ?>
                                                                <button type="button" class="button button-small" onclick="sdSaveSnapshot()" title="Save an instant on-site backup snapshot of your current rules to the WordPress database">💾 Backup Snapshot</button>
                                                                <button type="button" class="button button-small" id="sd_restore_snapshot_btn" onclick="sdRestoreSnapshot()" <?php echo !$has_snap ? 'disabled style="opacity: 0.6;"' : ''; ?> title="<?php echo $has_snap ? esc_attr('Restore on-site backup snapshot saved on ' . ($snap['time_str'] ?? '') . ' (' . ($snap['count'] ?? 0) . ' rules)') : 'No on-site snapshot saved yet'; ?>">↺ Restore Snapshot</button>
                                                                <span id="sd_snapshot_indicator" style="font-size: 11px; color: #64748b;"><?php if ($has_snap && !empty($snap['time_str'])): ?>(Saved: <?php echo esc_html($snap['time_str']); ?> &bull; <?php echo esc_html($snap['count'] ?? 0); ?> rules)<?php endif; ?></span>
                                                                <span style="color:#cbd5e1; margin:0 2px;">|</span>
                                                                <button type="button" class="button button-small" onclick="sdExportCustomOverrides()" title="Export all rules to a .txt backup file">📥 Export (.txt)</button>
                                                                <button type="button" class="button button-small" onclick="document.getElementById('sd_import_overrides_file').click()" title="Import rules from a .txt or .csv file">📤 Import (.txt)</button>
                                                                <input type="file" id="sd_import_overrides_file" accept=".txt,.csv" style="display:none;" onchange="sdImportCustomOverrides(this)">
                                                            </div>
                                                        </div>

                                                        <input type="hidden" id="sd_overrides_snapshot_field" name="social_digest_options[overrides_snapshot]" value="<?php echo esc_attr(json_encode($opts['overrides_snapshot'] ?? [])); ?>">
                                                        <div id="sd_import_status" style="display:none; margin-bottom: 6px; padding: 6px 10px; background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 4px; font-size: 11px; color: #166534; font-weight: 500;"></div>

                                                        <!-- Overrides Textarea -->
                                                        <?php
                                                        $current_overrides = trim((string)($opts['title_tag_custom_overrides'] ?? ''));
                                                        if ($current_overrides === '') {
                                                            $current_overrides = social_get_default_tag_overrides_string();
                                                        }
                                                        ?>
                                                        <textarea id="sd_title_tag_custom_overrides" name="social_digest_options[title_tag_custom_overrides]" rows="14" class="large-text" style="font-family: monospace; font-size: 12px; line-height: 1.5; padding: 8px 10px;" oninput="sdUpdateLineCount()" placeholder="SnapdragonX=Snapdragon X&#10;MINISFORUM*&#10;GEEKOM*&#10;NVIDIA*&#10;MediaTek=MediaTek&#10;FDroid=F-Droid"><?php echo esc_textarea($current_overrides); ?></textarea>
                                                        
                                                        <div style="margin-top: 8px; font-size: 11px; line-height: 1.5; color: #475569; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px; padding: 10px 12px;">
                                                            <div style="font-weight: 600; color: #1e293b; margin-bottom: 4px;">Tag Override Rules &amp; Built-In Baseline:</div>
                                                            <p style="margin: 0 0 6px 0;">All built-in technology terms are pre-populated above, fully visible and editable. You can freely edit, add, or delete any rule.</p>
                                                            <ul style="margin: 0 0 6px 18px; list-style-type: disc;">
                                                                <li><strong>Social Tag Shorthand: Single underscore (<code>_</code>) creates a space (e.g. <code>#AI_PC</code> &rarr; <strong>AI PC</strong>); double underscore (<code>__</code>) creates a hyphen (e.g. <code>#Wi__Fi</code> &rarr; <strong>Wi-Fi</strong>)</li>
                                                                <li><strong>Formatting:</strong> Enter rules <strong>one per line</strong> (recommended) or separated by commas. Leading <code>#</code> symbols are stripped automatically. Comments beginning with <code>#</code> are preserved.</li>
                                                                <li><strong>Exact Mapping (<code>tag=Formatted Title</code>):</strong> Explicitly renames a hashtag to your desired title casing (e.g. <code>SnapdragonX=Snapdragon X</code>, <code>FDroid=F-Droid</code>).</li>
                                                                <li><strong>Wildcard Prefixes (<code>PREFIX*</code>):</strong> Preserves uppercase brand prefixes while splitting trailing model numbers (e.g. <code>MINISFORUM*</code> formats <code>#MINISFORUMS5</code> to <strong>MINISFORUM S5</strong>).</li>
                                                                <li><strong>Exact Brand Casing (<code>BRAND*</code> or <code>BrandName</code>):</strong> Preserves specific casing for acronyms and compound names (e.g. <code>XPS*</code>, <code>NVIDIA*</code>, <code>MediaTek</code>, <code>Chromebook</code>).</li>
                                                            </ul>
                                                            <div style="color: #64748b; font-size: 10.5px; border-top: 1px dashed #cbd5e1; padding-top: 6px; margin-top: 4px; display: flex; justify-content: space-between; flex-wrap: wrap; gap: 4px;">
                                                                <span>💡 <em>Use <strong>🔤 Sort A→Z</strong> to keep terms alphabetized, or <strong>🔄 Reverse Order</strong> to view by recency.</em></span>
                                                                <span><em>Use <strong>📥 Export / 📤 Import</strong> to save backups or sync between sites.</em></span>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </td>
                                            </tr>
                                        </table>
                                    </div>
                                </div>

                                <!-- WORDPRESS TAGS & HASHTAG AUTO-TAGGING -->
                                <div class="postbox" id="social_box_tags" style="border-left: 5px solid #06b6d4;">
                                    <div class="postbox-header">
                                        <h2 class="hndle">
                                            <span class="dashicons dashicons-tag" style="color:#06b6d4; margin-right:4px;"></span>
                                            WordPress Tags &amp; Hashtag Auto-Tagging
                                        </h2>
                                        <button type="button" class="handlediv" aria-expanded="true"><span class="toggle-indicator" aria-hidden="true"></span></button>
                                    </div>
                                    <div class="inside">
                                        <table class="form-table">
                                            <tr>
                                                <th>Default Post Tags</th>
                                                <td>
                                                    <input name="social_digest_options[default_tags]" type="text" value="<?php echo esc_attr($opts['default_tags'] ?? 'Social Digest, Roundup'); ?>" class="regular-text" placeholder="Social Digest, Roundup" />
                                                    <p class="description">Comma-separated default WordPress tags attached to every published digest post.</p>
                                                </td>
                                            </tr>
                                            <tr>
                                                <th>Hashtag Auto-Tagging</th>
                                                <td>
                                                    <label><input type="checkbox" name="social_digest_options[extract_tags]" value="1" <?php checked(!isset($opts['extract_tags']) || !empty($opts['extract_tags'])); ?> /> <strong>Automatically convert social hashtags into WordPress post tags</strong></label>
                                                    <p class="description">Extracts hashtags from included social updates and attaches them as taxonomy tags to the published WordPress post.</p>
                                                    <p class="description" style="margin-top: 4px; color: #475569;">Social Tag Shorthand: Single underscore (<code>_</code>) creates a space (e.g. <code>#AI_PC</code> &rarr; <strong>AI PC</strong>); double underscore (<code>__</code>) creates a hyphen (e.g. <code>#Wi__Fi</code> &rarr; <strong>Wi-Fi</strong>).</p>

                                                    <div style="margin-top: 10px; padding: 12px; background: #f6f7f7; border: 1px solid #ccd0d4; border-radius: 4px; max-width: 500px;">
                                                        <div style="margin-bottom: 8px;">
                                                            <label style="display:inline-block; width: 170px; font-weight:600;">Minimum Character Length:</label>
                                                            <select name="social_digest_options[min_tag_length]">
                                                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                                                    <option value="<?php echo $i; ?>" <?php selected((int)($opts['min_tag_length'] ?? 3), $i); ?>><?php echo $i; ?> <?php echo $i === 1 ? 'character' : 'characters'; ?></option>
                                                                <?php endfor; ?>
                                                            </select>
                                                        </div>
                                                        <div style="margin-bottom: 8px;">
                                                            <label style="display:inline-block; width: 170px; font-weight:600;">Max Tags Per Social Entry:</label>
                                                            <select name="social_digest_options[max_tags_per_post]">
                                                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                                                    <option value="<?php echo $i; ?>" <?php selected((int)($opts['max_tags_per_post'] ?? 2), $i); ?>><?php echo $i; ?> <?php echo $i === 1 ? 'tag' : 'tags'; ?></option>
                                                                <?php endfor; ?>
                                                            </select>
                                                        </div>
                                                        <div>
                                                            <label style="display:inline-block; width: 170px; font-weight:600;">Max Total Tags Per Digest:</label>
                                                            <select name="social_digest_options[max_total_tags]">
                                                                <?php for ($i = 1; $i <= 20; $i++): ?>
                                                                    <option value="<?php echo $i; ?>" <?php selected((int)($opts['max_total_tags'] ?? 8), $i); ?>><?php echo $i; ?> <?php echo $i === 1 ? 'tag' : 'tags'; ?></option>
                                                                <?php endfor; ?>
                                                            </select>
                                                        </div>
                                                    </div>
                                                </td>
                                            </tr>
                                        </table>
                                    </div>
                                </div>

                                <!-- ARTICLE FRAMING & TEMPLATES -->
                                <div class="postbox" id="social_box_framing_templates" style="border-left: 5px solid #10b981;">
                                    <div class="postbox-header">
                                        <h2 class="hndle">
                                            <span class="dashicons dashicons-layout" style="color:#10b981; margin-right:4px;"></span>
                                            Article Framing &amp; Lead-In / Footer Templates
                                        </h2>
                                        <button type="button" class="handlediv" aria-expanded="true"><span class="toggle-indicator" aria-hidden="true"></span></button>
                                    </div>
                                    <div class="inside">
                                        <table class="form-table">
                                            <tr>
                                                <th>Global Lead-In Header HTML</th>
                                                <td>
                                                    <textarea name="social_digest_options[header_text]" rows="3" class="large-text" placeholder="&lt;p&gt;Here is what we shared across social channels today:&lt;/p&gt;"><?php echo esc_textarea($opts['header_text'] ?? '<p>Here is what we shared across social channels today:</p>'); ?></textarea>
                                                    <p class="description">Default HTML content inserted at the top of every generated digest post.</p>
                                                    <div style="margin-top: 8px;">
                                                        <label>
                                                            <input type="checkbox" name="social_digest_options[allow_snippet_on_custom_header]" value="1" <?php checked(!empty($opts['allow_snippet_on_custom_header'])); ?> />
                                                            <strong>Make temporary header overrides visible to Google Search</strong>
                                                        </label>
                                                        <p class="description" style="margin-top:2px;">When enabled, entering a custom header on the Actions workbench for a specific digest will omit the <code>data-nosnippet</code> attribute so Google can display it in search snippets. When disabled, or if no custom header is entered, default headers remain protected from snippets.</p>
                                                    </div>
                                                </td>
                                            </tr>
                                            <tr>
                                                <th>Global Closing Footer HTML</th>
                                                <td>
                                                    <textarea name="social_digest_options[footer_text]" rows="3" class="large-text" placeholder="&lt;hr&gt;&lt;p&gt;Follow us directly on social media for real-time updates!&lt;/p&gt;"><?php echo esc_textarea($opts['footer_text'] ?? '<hr><p>Follow us directly on social media for real-time updates!</p>'); ?></textarea>
                                                    <p class="description">Default HTML content appended at the bottom of every generated digest post.</p>
                                                    <div style="margin-top: 8px;">
                                                        <label>
                                                            <input type="checkbox" name="social_digest_options[allow_snippet_on_custom_footer]" value="1" <?php checked(!empty($opts['allow_snippet_on_custom_footer'])); ?> />
                                                            <strong>Make temporary footer overrides visible to Google Search</strong>
                                                        </label>
                                                        <p class="description" style="margin-top:2px;">When enabled, entering a custom footer on the Actions workbench for a specific digest will omit the <code>data-nosnippet</code> attribute so Google can display it in search snippets. When disabled, or if no custom footer is entered, default footers remain protected from snippets.</p>
                                                    </div>
                                                </td>
                                            </tr>
                                            <tr>
                                                <th>Post Excerpt Generation</th>
                                                <td>
                                                    <label>
                                                        <input type="checkbox" name="social_digest_options[excerpt_first_lines]" id="social_excerpt_first_lines" value="1" <?php checked(!empty($opts['excerpt_first_lines'])); ?> onchange="document.getElementById('social_excerpt_options_wrap').style.display = this.checked ? 'block' : 'none';" />
                                                        <strong>Automatically generate excerpt from the first line of each entry</strong>
                                                    </label>
                                                    <p class="description">Extracts the lead sentence or first line from included social posts to form the excerpt for homepages, archives, and RSS feeds.</p>

                                                    <div id="social_excerpt_options_wrap" style="margin-top: 10px; padding: 12px; background: #f6f7f7; border: 1px solid #ccd0d4; border-radius: 4px; max-width: 500px; display: <?php echo !empty($opts['excerpt_first_lines']) ? 'block' : 'none'; ?>;">
                                                        <div style="margin-bottom: 8px;">
                                                            <label style="display:inline-block; width: 140px; font-weight:600;">Sentence Divider:</label>
                                                            <select name="social_digest_options[excerpt_delimiter]" id="social_excerpt_delimiter" onchange="document.getElementById('social_excerpt_custom_wrap').style.display = (this.value === 'custom') ? 'inline-block' : 'none';">
                                                                <option value="slash" <?php selected($opts['excerpt_delimiter'] ?? 'slash', 'slash'); ?>>Double Slash ( // )</option>
                                                                <option value="ellipsis" <?php selected($opts['excerpt_delimiter'] ?? '', 'ellipsis'); ?>>Ellipsis ( ... )</option>
                                                                <option value="period" <?php selected($opts['excerpt_delimiter'] ?? '', 'period'); ?>>Period / Sentences ( . )</option>
                                                                <option value="dash" <?php selected($opts['excerpt_delimiter'] ?? '', 'dash'); ?>>Em-Dash ( &mdash; )</option>
                                                                <option value="bullet" <?php selected($opts['excerpt_delimiter'] ?? '', 'bullet'); ?>>Bold Bullet ( &bull; )</option>
                                                                <option value="custom" <?php selected($opts['excerpt_delimiter'] ?? '', 'custom'); ?>>Custom Divider...</option>
                                                            </select>
                                                            <span id="social_excerpt_custom_wrap" style="margin-left: 8px; display: <?php echo (($opts['excerpt_delimiter'] ?? '') === 'custom') ? 'inline-block' : 'none'; ?>;">
                                                                <input type="text" name="social_digest_options[excerpt_custom_delimiter]" value="<?php echo esc_attr($opts['excerpt_custom_delimiter'] ?? ' // '); ?>" style="width: 80px;" placeholder=" // " />
                                                            </span>
                                                        </div>
                                                        <div>
                                                            <label style="display:inline-block; width: 140px; font-weight:600;">Entries in Excerpt:</label>
                                                            <select name="social_digest_options[excerpt_max_items]">
                                                                <option value="0" <?php selected($opts['excerpt_max_items'] ?? '0', '0'); ?>>All Included Posts</option>
                                                                <option value="1" <?php selected($opts['excerpt_max_items'] ?? '', '1'); ?>>1 Post (Lead Story only)</option>
                                                                <option value="2" <?php selected($opts['excerpt_max_items'] ?? '', '2'); ?>>First 2 Posts</option>
                                                                <option value="3" <?php selected($opts['excerpt_max_items'] ?? '', '3'); ?>>First 3 Posts</option>
                                                                <option value="4" <?php selected($opts['excerpt_max_items'] ?? '', '4'); ?>>First 4 Posts</option>
                                                                <option value="5" <?php selected($opts['excerpt_max_items'] ?? '', '5'); ?>>First 5 Posts</option>
                                                            </select>
                                                        </div>
                                                        <div style="margin-top: 10px; padding-top: 8px; border-top: 1px dashed #dcdcde;">
                                                            <label style="font-weight: 500; cursor: pointer;">
                                                                <input type="checkbox" name="social_digest_options[excerpt_append_ellipsis]" id="social_excerpt_append_ellipsis" value="1" <?php checked(!empty($opts['excerpt_append_ellipsis'])); ?> />
                                                                Append trailing ellipsis ( ... ) to end of excerpt
                                                            </label>
                                                        </div>
                                                    </div>
                                                </td>
                                            </tr>
                                        </table>
                                    </div>
                                </div>
                                <script>
                                var sdSearchMatches = [];
                                var sdCurrentMatchIdx = -1;
                                var sdDefaultBaselineText = <?php echo json_encode(social_get_default_tag_overrides_string()); ?>;
                                var sdSnapshot = <?php echo json_encode($opts['overrides_snapshot'] ?? null); ?>;
                                var sdSnapshotNonce = <?php echo json_encode(wp_create_nonce('sd_snapshot_action')); ?>;

                                function sdUpdateLineCount() {
                                    var textarea = document.getElementById('sd_title_tag_custom_overrides');
                                    var countEl = document.getElementById('sd_overrides_line_count');
                                    if (!textarea || !countEl) return;
                                    var lines = textarea.value.split('\n').filter(function(l) { return l.trim().length > 0 && l.trim().charAt(0) !== '#'; });
                                    countEl.textContent = '(' + lines.length + ' active rules)';
                                }

                                function sdSearchOverrides(query) {
                                    query = (query || '').trim().toLowerCase();
                                    var textarea = document.getElementById('sd_title_tag_custom_overrides');
                                    var countEl = document.getElementById('sd_search_count');
                                    sdSearchMatches = [];
                                    sdCurrentMatchIdx = -1;

                                    if (!query || !textarea) {
                                        if (countEl) countEl.textContent = '';
                                        return;
                                    }

                                    var text = textarea.value;
                                    var lowerText = text.toLowerCase();
                                    var pos = 0;
                                    while ((pos = lowerText.indexOf(query, pos)) !== -1) {
                                        sdSearchMatches.push({ start: pos, end: pos + query.length });
                                        pos += query.length;
                                    }

                                    if (sdSearchMatches.length > 0) {
                                        sdCurrentMatchIdx = 0;
                                        sdHighlightCurrentMatch();
                                        if (countEl) countEl.textContent = '1/' + sdSearchMatches.length;
                                    } else {
                                        if (countEl) countEl.textContent = '0';
                                    }
                                }

                                function sdSearchNextOverride() {
                                    if (sdSearchMatches.length === 0) return;
                                    sdCurrentMatchIdx = (sdCurrentMatchIdx + 1) % sdSearchMatches.length;
                                    sdHighlightCurrentMatch();
                                    var countEl = document.getElementById('sd_search_count');
                                    if (countEl) countEl.textContent = (sdCurrentMatchIdx + 1) + '/' + sdSearchMatches.length;
                                }

                                function sdHighlightCurrentMatch() {
                                    var textarea = document.getElementById('sd_title_tag_custom_overrides');
                                    if (!textarea || sdCurrentMatchIdx < 0 || sdCurrentMatchIdx >= sdSearchMatches.length) return;
                                    var match = sdSearchMatches[sdCurrentMatchIdx];
                                    textarea.focus();
                                    textarea.setSelectionRange(match.start, match.end);

                                    // Scroll textarea to the line of match
                                    var linesBefore = textarea.value.substring(0, match.start).split('\n').length;
                                    var lineHeight = 18;
                                    textarea.scrollTop = Math.max(0, (linesBefore - 4) * lineHeight);
                                }

                                function sdSortOverrides(direction) {
                                    var textarea = document.getElementById('sd_title_tag_custom_overrides');
                                    if (!textarea) return;
                                    var text = textarea.value;
                                    var lines = text.split(/\r?\n/);
                                    
                                    var comments = [];
                                    var rules = [];
                                    for (var i = 0; i < lines.length; i++) {
                                        var trimmed = lines[i].trim();
                                        if (trimmed.charAt(0) === '#' && rules.length === 0) {
                                            comments.push(lines[i]);
                                        } else if (trimmed.length > 0) {
                                            rules.push(lines[i]);
                                        }
                                    }

                                    if (direction === 'az') {
                                        rules.sort(function(a, b) {
                                            return a.localeCompare(b, undefined, { sensitivity: 'base', numeric: true });
                                        });
                                    } else if (direction === 'za') {
                                        rules.sort(function(a, b) {
                                            return b.localeCompare(a, undefined, { sensitivity: 'base', numeric: true });
                                        });
                                    } else if (direction === 'reverse') {
                                        rules.reverse();
                                    }

                                    var result = (comments.length > 0 ? comments.join('\n') + '\n\n' : '') + rules.join('\n');
                                    textarea.value = result;
                                    sdUpdateLineCount();
                                    try {
                                        textarea.dispatchEvent(new Event('change', { bubbles: true }));
                                    } catch (e) {}
                                }

                                function sdResetOverrides() {
                                    if (!confirm("Reset tag overrides to the built-in baseline rules?\n\nThis will replace any custom rules currently in the box with the default list.")) {
                                        return;
                                    }
                                    var textarea = document.getElementById('sd_title_tag_custom_overrides');
                                    if (textarea) {
                                        textarea.value = sdDefaultBaselineText;
                                        sdUpdateLineCount();
                                        try {
                                            textarea.dispatchEvent(new Event('change', { bubbles: true }));
                                        } catch (e) {}
                                    }
                                }

                                function sdSaveSnapshot() {
                                    var textarea = document.getElementById('sd_title_tag_custom_overrides');
                                    if (!textarea) return;
                                    var content = textarea.value.trim();

                                    var lines = content.split('\n').filter(function(l) { return l.trim().length > 0 && l.trim().charAt(0) !== '#'; });
                                    var ruleCount = lines.length;

                                    if (!content && !confirm("Your tag overrides box is currently empty. Do you want to save an empty backup snapshot?")) {
                                        return;
                                    }

                                    var now = new Date();
                                    var timeStr = now.toLocaleDateString() + ' ' + now.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
                                    var snapshotObj = {
                                        content: textarea.value,
                                        time: Math.floor(now.getTime() / 1000),
                                        time_str: timeStr,
                                        count: ruleCount
                                    };

                                    // Stash in local memory and hidden form field for persistence
                                    sdSnapshot = snapshotObj;
                                    var hiddenField = document.getElementById('sd_overrides_snapshot_field');
                                    if (hiddenField) {
                                        hiddenField.value = JSON.stringify(snapshotObj);
                                    }
                                    try {
                                        localStorage.setItem('sd_overrides_snapshot', JSON.stringify(snapshotObj));
                                    } catch(e) {}

                                    // Update Restore Snapshot button
                                    var restoreBtn = document.getElementById('sd_restore_snapshot_btn');
                                    if (restoreBtn) {
                                        restoreBtn.disabled = false;
                                        restoreBtn.style.opacity = '1';
                                        restoreBtn.title = 'Restore on-site backup snapshot saved on ' + timeStr + ' (' + ruleCount + ' rules)';
                                    }

                                    var indicator = document.getElementById('sd_snapshot_indicator');
                                    if (indicator) {
                                        indicator.innerHTML = '(Saved: ' + timeStr + ' &bull; ' + ruleCount + ' rules)';
                                    }

                                    var statusEl = document.getElementById('sd_import_status');
                                    if (statusEl) {
                                        statusEl.textContent = '⏳ Saving backup snapshot to on-site database...';
                                        statusEl.style.display = 'block';
                                        statusEl.style.background = '#eff6ff';
                                        statusEl.style.borderColor = '#bfdbfe';
                                        statusEl.style.color = '#1e40af';
                                    }

                                    // Persist to server via AJAX immediately
                                    var formData = new FormData();
                                    formData.append('action', 'sd_save_snapshot');
                                    formData.append('nonce', sdSnapshotNonce);
                                    formData.append('content', textarea.value);

                                    fetch(typeof ajaxurl !== 'undefined' ? ajaxurl : '/wp-admin/admin-ajax.php', {
                                        method: 'POST',
                                        body: formData
                                    }).then(function(res) {
                                        return res.json();
                                    }).then(function(data) {
                                        if (data && data.success) {
                                            if (data.data && data.data.snapshot) {
                                                sdSnapshot = data.data.snapshot;
                                                if (hiddenField) hiddenField.value = JSON.stringify(sdSnapshot);
                                                if (indicator) {
                                                    indicator.innerHTML = '(Saved: ' + (sdSnapshot.time_str || timeStr) + ' &bull; ' + (sdSnapshot.count || ruleCount) + ' rules)';
                                                }
                                            }
                                            if (statusEl) {
                                                statusEl.textContent = '✓ On-site backup snapshot saved! (' + ruleCount + ' rules saved at ' + timeStr + ')';
                                                statusEl.style.display = 'block';
                                                statusEl.style.background = '#f0fdf4';
                                                statusEl.style.borderColor = '#bbf7d0';
                                                statusEl.style.color = '#166534';
                                                setTimeout(function() { statusEl.style.display = 'none'; }, 6000);
                                            }
                                        } else {
                                            throw new Error(data && data.data && data.data.message ? data.data.message : 'Save error');
                                        }
                                    }).catch(function() {
                                        if (statusEl) {
                                            statusEl.textContent = '✓ Snapshot saved locally (' + ruleCount + ' rules)! Click "Save Settings" below to persist permanently.';
                                            statusEl.style.display = 'block';
                                            statusEl.style.background = '#fefce8';
                                            statusEl.style.borderColor = '#fef08a';
                                            statusEl.style.color = '#854d0e';
                                            setTimeout(function() { statusEl.style.display = 'none'; }, 7000);
                                        }
                                    });
                                }

                                function sdRestoreSnapshot() {
                                    var snapshot = sdSnapshot;
                                    if (!snapshot || typeof snapshot.content === 'undefined') {
                                        try {
                                            var local = localStorage.getItem('sd_overrides_snapshot');
                                            if (local) snapshot = JSON.parse(local);
                                        } catch(e) {}
                                    }

                                    if (!snapshot || typeof snapshot.content === 'undefined' || snapshot.content === null) {
                                        alert('No on-site backup snapshot found. Click "💾 Backup Snapshot" first to save your current rules.');
                                        return;
                                    }

                                    var dateStr = snapshot.time_str || 'previously saved backup';
                                    var ruleCount = typeof snapshot.count !== 'undefined' ? snapshot.count : 'existing';
                                    var msg = "Restore on-site backup snapshot from " + dateStr + " (" + ruleCount + " rules)?\n\n" +
                                              "This will replace the current contents of your Custom Tag Title Overrides box.";

                                    if (!confirm(msg)) {
                                        return;
                                    }

                                    var textarea = document.getElementById('sd_title_tag_custom_overrides');
                                    if (textarea) {
                                        textarea.value = snapshot.content;
                                        sdUpdateLineCount();
                                        try {
                                            textarea.dispatchEvent(new Event('input', { bubbles: true }));
                                            textarea.dispatchEvent(new Event('change', { bubbles: true }));
                                        } catch(e) {}

                                        var statusEl = document.getElementById('sd_import_status');
                                        if (statusEl) {
                                            statusEl.textContent = '✓ Restored snapshot from ' + dateStr + ' (' + ruleCount + ' rules)! Click "Save Settings" below to apply changes.';
                                            statusEl.style.display = 'block';
                                            statusEl.style.background = '#f0fdf4';
                                            statusEl.style.borderColor = '#bbf7d0';
                                            statusEl.style.color = '#166534';
                                            setTimeout(function() { statusEl.style.display = 'none'; }, 7000);
                                        } else {
                                            alert('Snapshot restored (' + ruleCount + ' rules)! Click "Save Settings" below to apply.');
                                        }
                                    }
                                }

                                function sdExportCustomOverrides() {
                                    var textarea = document.getElementById('sd_title_tag_custom_overrides');
                                    var content = textarea ? textarea.value.trim() : '';
                                    if (!content) {
                                        alert('There are no custom tag overrides to export.');
                                        return;
                                    }
                                    var header = '# Social Digest - Custom Tag Title Overrides Export\n' +
                                                 '# Generated: ' + new Date().toISOString().split('T')[0] + '\n\n';
                                    var blob = new Blob([header + content + '\n'], { type: 'text/plain;charset=utf-8' });
                                    var url = URL.createObjectURL(blob);
                                    var a = document.createElement('a');
                                    a.href = url;
                                    a.download = 'custom-tag-overrides.txt';
                                    document.body.appendChild(a);
                                    a.click();
                                    document.body.removeChild(a);
                                    URL.revokeObjectURL(url);
                                }

                                function sdImportCustomOverrides(input) {
                                    if (!input.files || !input.files[0]) return;
                                    var file = input.files[0];
                                    var reader = new FileReader();
                                    reader.onload = function(e) {
                                        var rawText = e.target.result || '';
                                        var lines = rawText.split(/\r?\n/).filter(function(line) {
                                            var trimmed = line.trim();
                                            return trimmed.length > 0 && trimmed.charAt(0) !== '#';
                                        });
                                        var importedText = lines.join('\n');

                                        var textarea = document.getElementById('sd_title_tag_custom_overrides');
                                        if (!textarea) return;

                                        var currentText = textarea.value.trim();
                                        if (currentText !== '') {
                                            var shouldAppend = confirm(
                                                "Do you want to append the imported overrides to your existing list?\n\n" +
                                                "Click OK to Append to existing rules.\n" +
                                                "Click Cancel to Replace existing rules."
                                            );
                                            if (shouldAppend) {
                                                textarea.value = currentText + '\n' + importedText;
                                            } else {
                                                textarea.value = importedText;
                                            }
                                        } else {
                                            textarea.value = importedText;
                                        }

                                        sdUpdateLineCount();

                                        try {
                                            textarea.dispatchEvent(new Event('change', { bubbles: true }));
                                            textarea.dispatchEvent(new Event('input', { bubbles: true }));
                                        } catch (evtErr) {}

                                        var statusEl = document.getElementById('sd_import_status');
                                        if (statusEl) {
                                            statusEl.textContent = '✓ Successfully imported ' + lines.length + ' rules! Click "Save Settings" below to apply.';
                                            statusEl.style.display = 'block';
                                            setTimeout(function() {
                                                statusEl.style.display = 'none';
                                            }, 7000);
                                        } else {
                                            alert('Import successful (' + lines.length + ' rules loaded)! Click "Save Settings" at the bottom of the page to apply.');
                                        }
                                    };
                                    reader.readAsText(file);
                                    input.value = '';
                                }

                                document.addEventListener('DOMContentLoaded', sdUpdateLineCount);
                                </script>
                                <?php submit_button('Save Settings'); ?>
            </form>

        <?php elseif ($active_tab === 'workbench'): ?>
            <?php 
            $state = social_workbench_state();
            if (!empty($state) && (empty($state['version']) || $state['version'] !== SOCIAL_DIGEST_VERSION || strpos($state['title_override'] ?? '', ': ') === 0 || preg_match('/minipc|snapdragonx|eliteminipc|^[a-z]/i', $state['title_override'] ?? ''))) {
                $auto_refreshed = social_fetch_workbench_candidates();
                if (!empty($auto_refreshed['state'])) {
                    $state = $auto_refreshed['state'];
                }
            }
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

                <div class="meta-box-sortables ui-sortable" id="social_wb_main_sortable">

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
                            <!-- CUSTOMIZABLE WORDPRESS POST TITLE -->
                            <div style="background:#f8fafc; border:1px solid #cbd5e1; border-radius:6px; padding:12px 16px; margin-bottom:16px;">
                                <label for="sd53_custom_title" style="display:block; font-weight:700; font-size:13px; color:#0f172a; margin-bottom:6px;">
                                    <span class="dashicons dashicons-editor-textbox" style="color:#0284c7; vertical-align:text-bottom; margin-right:4px;"></span>
                                    WordPress Post Title:
                                </label>
                                <input type="text" id="sd53_custom_title" name="custom_title" class="large-text" value="<?php echo esc_attr($state['title_override'] ?? $state['preview']['title'] ?? ''); ?>" placeholder="Enter custom WordPress post title..." style="font-size:15px; font-weight:600; padding:8px 12px; border-color:#94a3b8; border-radius:4px; width:100%; box-sizing:border-box;" />
                                <p class="description" style="margin-top:6px; font-size:12px; color:#64748b; margin-bottom:0;">
                                    Shows the auto-generated headline by default. You can manually edit or customize this title before saving, drafting, or publishing.
                                </p>
                            </div>

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
                                Overrides apply only to the next published digest. When enabled, your custom header and footer below will replace the global templates for this run.
                            </p>

                            <?php
                            $wb_header = $state['header_override'] ?? '';
                            $wb_footer = $state['footer_override'] ?? '';
                            $has_override_text = (trim(wp_strip_all_tags($wb_header)) !== '' || trim(wp_strip_all_tags($wb_footer)) !== '');
                            $override_checked = !empty($state['framing_override_enabled']) || $has_override_text;
                            ?>

                            <p style="margin-bottom:15px;">
                                <label>
                                    <input type="checkbox" id="sd_framing_override_enabled" name="framing_override_enabled" value="1" <?php checked($override_checked); ?>>
                                    <strong>Enable temporary header &amp; footer overrides for this run</strong>
                                </label>
                                <span class="description" style="display:block; margin-top:3px; font-size:11px; color:#64748b;">
                                    (Automatically enabled when text is entered below)
                                </span>
                            </p>

                            <div style="margin-bottom: 20px;">
                                <label style="display:block; font-weight:600; margin-bottom:6px; color:#1d2327;">
                                    Temporary Lead-In Header HTML / Rich Text:
                                    <?php if (!empty($opts['allow_snippet_on_custom_header'])): ?>
                                        <span style="font-size:11px; font-weight:normal; color:#059669; margin-left:6px; background:#ecfdf5; border:1px solid #a7f3d0; padding:2px 6px; border-radius:3px;">
                                            <span class="dashicons dashicons-visibility" style="font-size:13px; width:13px; height:13px; vertical-align:-2px;"></span> Google Search Snippets Enabled
                                        </span>
                                    <?php endif; ?>
                                </label>
                                <?php
                                wp_editor($wb_header, 'social_digest_workbench_header_override', [
                                    'textarea_name' => 'workbench_header_override',
                                    'textarea_rows' => 4,
                                    'media_buttons' => false,
                                    'teeny'         => false,
                                    'quicktags'     => [
                                        'buttons' => 'strong,em,link,close'
                                    ]
                                ]);
                                ?>
                            </div>

                            <div>
                                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:6px;">
                                    <label for="sd_workbench_footer_override" style="font-weight:600; color:#1d2327;">
                                        Temporary Closing Footer HTML:
                                        <?php if (!empty($opts['allow_snippet_on_custom_footer'])): ?>
                                            <span style="font-size:11px; font-weight:normal; color:#059669; margin-left:6px; background:#ecfdf5; border:1px solid #a7f3d0; padding:2px 6px; border-radius:3px;">
                                                <span class="dashicons dashicons-visibility" style="font-size:13px; width:13px; height:13px; vertical-align:-2px;"></span> Google Search Snippets Enabled
                                            </span>
                                        <?php endif; ?>
                                    </label>
                                    <div class="sd-quicktags-toolbar" style="display:flex; gap:4px;">
                                        <button type="button" class="button button-small" onclick="sdInsertFooterTag('strong')" title="Bold text"><strong>B</strong></button>
                                        <button type="button" class="button button-small" onclick="sdInsertFooterTag('em')" title="Italic text"><em>I</em></button>
                                        <button type="button" class="button button-small" onclick="sdInsertFooterTag('link')" title="Insert Link">🔗 Link</button>
                                        <button type="button" class="button button-small" onclick="sdInsertFooterText('<hr>\n')" title="Horizontal rule">&mdash; HR</button>
                                        <button type="button" class="button button-small" onclick="sdInsertFooterText('<br>\n')" title="Line break">&ldsh; BR</button>
                                    </div>
                                </div>
                                <textarea id="sd_workbench_footer_override" name="workbench_footer_override" rows="4" class="large-text" style="font-family:monospace; font-size:12px;" placeholder="<hr><p>Follow us directly on social media for real-time updates!</p>"><?php echo esc_textarea($wb_footer); ?></textarea>
                                <p class="description" style="margin-top:3px; font-size:11px;">
                                    Optional closing footer markup for this run. Use the quick format buttons above to insert HTML tags.
                                </p>
                            </div>
                        </div>
                    </div>

                    <!-- EXCERPT & DIGEST PREVIEW -->
                    <?php if ($has_candidates): 
                        $wb_delim_key = $opts['excerpt_delimiter'] ?? 'slash';
                        $wb_custom_delim = $opts['excerpt_custom_delimiter'] ?? ' // ';
                        $wb_delim_map = [
                            'slash'    => ' // ',
                            'ellipsis' => ' ... ',
                            'period'   => '. ',
                            'dash'     => ' — ',
                            'bullet'   => ' <b>•</b> ',
                            'custom'   => $wb_custom_delim,
                        ];
                        $wb_delim = $wb_delim_map[$wb_delim_key] ?? ' // ';
                        $wb_max_items = absint($opts['excerpt_max_items'] ?? 0);
                        $wb_append_ellipsis = !empty($opts['excerpt_append_ellipsis']);
                        $wb_first_lines_excerpt = social_build_first_lines_excerpt($candidates, $wb_delim, $wb_max_items, $wb_append_ellipsis);
                    ?>
                    <div class="postbox" id="social_wb_box_excerpt_preview" style="border-left: 5px solid #10b981;">
                        <div class="postbox-header">
                            <h2 class="hndle">
                                <span class="dashicons dashicons-excerpt-view" style="color:#10b981; margin-right:4px;"></span>
                                Digest Excerpt Inspection
                            </h2>
                            <button type="button" class="handlediv" aria-expanded="true"><span class="toggle-indicator" aria-hidden="true"></span></button>
                        </div>
                        <div class="inside">
                            <p style="margin-top:0; font-size:12px; color:#50575e;">
                                Live preview of the WordPress post excerpt that will be distributed to your homepage, search snippets, archives, and RSS feeds.
                            </p>
                            <div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:6px; padding:12px 14px; font-family:-apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; font-size:14px; line-height:1.6; color:#1e293b;">
                                <?php if (!empty($opts['excerpt_first_lines'])): ?>
                                    <div style="font-size:11px; font-weight:700; text-transform:uppercase; color:#059669; letter-spacing:0.5px; margin-bottom:6px;">
                                        Generated First-Lines Excerpt (Active Divider: <code><?php echo esc_html(trim($wb_delim)); ?></code>)
                                    </div>
                                    <div style="font-style:italic; color:#0f172a;">
                                        &ldquo;<?php echo esc_html($wb_first_lines_excerpt ?: 'No excerpt lines available.'); ?>&rdquo;
                                    </div>
                                <?php else: ?>
                                    <div style="font-size:11px; font-weight:700; text-transform:uppercase; color:#64748b; letter-spacing:0.5px; margin-bottom:6px;">
                                        Standard Excerpt (Auto-generated from post content)
                                    </div>
                                    <div style="color:#475569;">
                                        First-lines excerpt is currently disabled. Enable it under <a href="<?php echo esc_url(admin_url('edit.php?page=social-digest-settings&tab=settings#social_box_framing_templates')); ?>" style="color:#0284c7;">Settings &rarr; Article Framing &amp; Lead-In / Footer Templates</a>.
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>

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
    function sdInsertFooterText(text) {
        var textarea = document.getElementById('sd_workbench_footer_override');
        if (!textarea) return;
        var start = textarea.selectionStart || 0;
        var end = textarea.selectionEnd || 0;
        var val = textarea.value;
        textarea.value = val.substring(0, start) + text + val.substring(end);
        textarea.focus();
        textarea.selectionStart = textarea.selectionEnd = start + text.length;
        sdCheckFramingOverrideAutoState();
    }

    function sdInsertFooterTag(tag) {
        var textarea = document.getElementById('sd_workbench_footer_override');
        if (!textarea) return;
        var start = textarea.selectionStart || 0;
        var end = textarea.selectionEnd || 0;
        var val = textarea.value;
        var selected = val.substring(start, end);

        var replacement = '';
        if (tag === 'strong') {
            replacement = '<strong>' + (selected || 'Bold text') + '</strong>';
        } else if (tag === 'em') {
            replacement = '<em>' + (selected || 'Italic text') + '</em>';
        } else if (tag === 'link') {
            var url = prompt('Enter link URL (e.g. https://example.com):', 'https://');
            if (url) {
                replacement = '<a href="' + url + '">' + (selected || 'Link text') + '</a>';
            } else {
                return;
            }
        }
        if (replacement) {
            textarea.value = val.substring(0, start) + replacement + val.substring(end);
            textarea.focus();
            textarea.selectionStart = textarea.selectionEnd = start + replacement.length;
            sdCheckFramingOverrideAutoState();
        }
    }

    function sdCheckFramingOverrideAutoState() {
        var chk = document.getElementById('sd_framing_override_enabled');
        if (!chk) return;

        var headerText = '';
        if (typeof tinymce !== 'undefined' && tinymce.get('social_digest_workbench_header_override')) {
            var ed = tinymce.get('social_digest_workbench_header_override');
            headerText = ed.getContent({ format: 'text' }).trim();
        } else {
            var headerEl = document.getElementById('social_digest_workbench_header_override');
            if (headerEl) headerText = headerEl.value.replace(/<[^>]*>/g, '').trim();
        }

        var footerEl = document.getElementById('sd_workbench_footer_override');
        var footerText = footerEl ? footerEl.value.replace(/<[^>]*>/g, '').trim() : '';

        if (headerText !== '' || footerText !== '') {
            chk.checked = true;
        } else {
            chk.checked = false;
        }
    }

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
        var pageKey = 'sd_postbox_' + ($('#social_settings_sortable').length ? 'settings' : 'workbench');

        // Restore saved closed/expanded states from localStorage
        try {
            var savedClosed = JSON.parse(localStorage.getItem(pageKey + '_closed') || '[]');
            if (Array.isArray(savedClosed)) {
                savedClosed.forEach(function(boxId) {
                    var $b = $('#' + boxId);
                    if ($b.length) {
                        $b.addClass('closed');
                        $b.find('.handlediv').attr('aria-expanded', 'false');
                    }
                });
            }
        } catch (e) {}

        // Restore saved widget order from localStorage
        try {
            var savedOrder = JSON.parse(localStorage.getItem(pageKey + '_order') || '[]');
            if (Array.isArray(savedOrder) && savedOrder.length) {
                var $container = $('#social_settings_sortable, #social_wb_main_sortable').first();
                if ($container.length) {
                    savedOrder.forEach(function(boxId) {
                        var $el = $('#' + boxId);
                        if ($el.length && $el.parent().is($container)) {
                            $container.append($el);
                        }
                    });
                }
            }
        } catch (e) {}

        function sdPersistClosedPostboxes() {
            try {
                var closedList = [];
                $('.postbox.closed').each(function() {
                    if (this.id) closedList.push(this.id);
                });
                localStorage.setItem(pageKey + '_closed', JSON.stringify(closedList));
            } catch (err) {}
        }

        // Click handler to toggle expand / collapse on handlediv toggle button
        $(document).on('click', '.postbox .handlediv', function(e) {
            e.preventDefault();
            e.stopPropagation();
            var $box = $(this).closest('.postbox');
            $box.toggleClass('closed');
            var isClosed = $box.hasClass('closed');
            $(this).attr('aria-expanded', isClosed ? 'false' : 'true');
            sdPersistClosedPostboxes();
        });

        // Click handler on header bar (excluding interactive controls and handlediv)
        $(document).on('click', '.postbox .postbox-header', function(e) {
            if ($(e.target).closest('.handlediv, input, select, textarea, a, .button, button').length) {
                return;
            }
            var $box = $(this).closest('.postbox');
            $box.toggleClass('closed');
            var isClosed = $box.hasClass('closed');
            $box.find('.handlediv').attr('aria-expanded', isClosed ? 'false' : 'true');
            sdPersistClosedPostboxes();
        });

        // Initialize drag-and-drop sortable
        if ($.fn.sortable) {
            $('#social_settings_sortable, #social_wb_main_sortable').sortable({
                handle: '.postbox-header',
                items: '> .postbox',
                cancel: '.handlediv, input, select, textarea, button, a',
                placeholder: 'social-sortable-placeholder',
                forcePlaceholderSize: true,
                opacity: 0.8,
                cursor: 'grabbing',
                update: function() {
                    try {
                        var order = $(this).sortable('toArray');
                        localStorage.setItem(pageKey + '_order', JSON.stringify(order));
                    } catch (err) {}
                }
            });
        }

        // Real-time listener on Footer textarea
        $('#sd_workbench_footer_override').on('input change keyup', sdCheckFramingOverrideAutoState);

        // Real-time listener on Header TinyMCE editor
        if (typeof tinymce !== 'undefined') {
            tinymce.on('AddEditor', function(e) {
                if (e.editor.id === 'social_digest_workbench_header_override') {
                    e.editor.on('input change keyup SetContent NodeChange', function() {
                        sdCheckFramingOverrideAutoState();
                    });
                }
            });
        }
    });
    </script>
    <?php
}
