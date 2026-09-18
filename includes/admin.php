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
            'convert_modern_media'   => 1,
            'cache_local_assets'     => 1,
            'generate_srcsets'       => 1,
            'enable_staging_queue'   => 0,
            'rss_only_mode'          => 0,
            'output_gutenberg_blocks'=> 1,
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
            'title_template'               => 'Social Digest {hashtags}',
            'title_tag_enclosure'          => 'parentheses',
            'title_tag_delimiter'          => 'oxford',
            'title_tag_max_count'          => 3,
            'title_tag_selection_strategy' => 'first',
            'learn_site_vocabulary'        => 1,
            'vocabulary_scan_frequency'    => '7_days',
            'vocabulary_post_scan_limit'   => 150,
            'title_tag_custom_overrides'   => '',
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

    if (isset($_POST['sd53_reindex_vocab']) && check_admin_referer('sd53_workbench_action', 'sd53_nonce')) {
        $dict = social_flush_site_vocabulary_cache();
        $count = count($dict);
        add_settings_error('sd53', 'vocab', sprintf('Site vocabulary index refreshed successfully! %d terms indexed from your published post titles, tags, and categories.', $count), 'updated');
    }
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

    $output['convert_modern_media'] = !empty($input['convert_modern_media']) ? 1 : 0;
    $output['cache_local_assets']   = !empty($input['cache_local_assets']) ? 1 : 0;
    $output['generate_srcsets']     = !empty($input['generate_srcsets']) ? 1 : 0;
    $output['sideload_all_media']   = !empty($input['sideload_all_media']) ? 1 : 0;
    $allowed_dark_modes             = ['auto', 'light', 'dark'];
    $output['dark_mode_mode']       = in_array($input['dark_mode_mode'] ?? '', $allowed_dark_modes, true) ? $input['dark_mode_mode'] : 'auto';

    $output['enable_staging_queue'] = !empty($input['enable_staging_queue']) ? 1 : 0;
    $output['rss_only_mode']        = !empty($input['rss_only_mode']) ? 1 : 0;
    $output['output_gutenberg_blocks'] = !empty($input['output_gutenberg_blocks']) ? 1 : 0;
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
    $output['collapse_threads']    = !empty($input['collapse_threads']) ? 1 : 0;
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

    $output['learn_site_vocabulary']     = !empty($input['learn_site_vocabulary']) ? 1 : 0;
    $allowed_frequencies                 = ['24_hours', '7_days', '30_days'];
    $output['vocabulary_scan_frequency'] = in_array($input['vocabulary_scan_frequency'] ?? '', $allowed_frequencies, true) ? $input['vocabulary_scan_frequency'] : '7_days';
    $raw_limit                           = (int)($input['vocabulary_post_scan_limit'] ?? 150);
    $output['vocabulary_post_scan_limit']= in_array($raw_limit, [50, 100, 150, 300, 500, 1000, -1], true) ? $raw_limit : 150;
    $output['title_tag_custom_overrides']= sanitize_textarea_field($input['title_tag_custom_overrides'] ?? '');

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
                                                <th>Conversion &amp; Cache</th>
                                                <td>
                                                    <label><input type="checkbox" name="social_digest_options[convert_modern_media]" value="1" <?php checked($opts['convert_modern_media'] ?? 1, 1); ?> /> Convert images to WebP / AVIF</label><br>
                                                    <label><input type="checkbox" name="social_digest_options[cache_local_assets]" value="1" <?php checked($opts['cache_local_assets'] ?? 1, 1); ?> /> Cache remote avatars &amp; cards locally</label><br>
                                                    <label><input type="checkbox" name="social_digest_options[generate_srcsets]" value="1" <?php checked($opts['generate_srcsets'] ?? 1, 1); ?> /> Generate standard responsive srcset sizes</label><br>
                                                    <label><input type="checkbox" name="social_digest_options[sideload_all_media]" value="1" <?php checked($opts['sideload_all_media'] ?? 0, 1); ?> /> <strong>Sideload all embedded post media into WordPress Media Library</strong></label>
                                                    <p class="description">Automatically downloads and imports all embedded update images directly into the local Media Library when publishing or drafting, protecting against external link rot and server outages.</p>
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
                                                    <input name="social_digest_options[same_day_suffix_tpl]" type="text" value="<?php echo esc_attr($opts['same_day_suffix_tpl'] ?? ' (Part {part})'); ?>" class="regular-text" style="width: 220px;" placeholder=" (Part {part})" />
                                                    <p class="description">Appended to post titles if multiple digests are published on the same calendar day. Use <code>{part}</code> for part number.</p>
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
                                                <th>Site Vocabulary Engine</th>
                                                <td>
                                                    <div style="padding: 12px; background: #f6f7f7; border: 1px solid #ccd0d4; border-radius: 4px; max-width: 550px;">
                                                        <div style="margin-bottom: 6px;">
                                                            <label><input type="checkbox" name="social_digest_options[learn_site_vocabulary]" value="1" <?php checked(!isset($opts['learn_site_vocabulary']) || !empty($opts['learn_site_vocabulary'])); ?>> Automatically learn vocabulary from published WordPress post titles, tags &amp; categories</label>
                                                        </div>
                                                        <div style="display: flex; gap: 20px; flex-wrap: wrap; margin-bottom: 8px;">
                                                            <div>
                                                                <label style="display:inline-block; width: 120px; font-size: 12px;">Scan Frequency:</label>
                                                                <select name="social_digest_options[vocabulary_scan_frequency]" style="font-size: 12px;">
                                                                    <option value="24_hours" <?php selected($opts['vocabulary_scan_frequency'] ?? '', '24_hours'); ?>>Every 24 Hours</option>
                                                                    <option value="7_days" <?php selected($opts['vocabulary_scan_frequency'] ?? '7_days', '7_days'); ?>>Every 7 Days (Recommended)</option>
                                                                    <option value="30_days" <?php selected($opts['vocabulary_scan_frequency'] ?? '', '30_days'); ?>>Every 30 Days</option>
                                                                </select>
                                                            </div>
                                                            <div>
                                                                <?php $scan_limit_val = (int)($opts['vocabulary_post_scan_limit'] ?? 150); ?>
                                                                <label style="display:inline-block; width: 120px; font-size: 12px;">Post Scan Depth:</label>
                                                                <select name="social_digest_options[vocabulary_post_scan_limit]" id="sd_vocab_scan_limit_select" style="font-size: 12px;" onchange="sdToggleVocabWarning(this.value)">
                                                                    <option value="50" <?php selected($scan_limit_val, 50); ?>>50 Posts (Light &amp; Fast)</option>
                                                                    <option value="150" <?php selected($scan_limit_val, 150); ?>>150 Posts (Recommended Baseline)</option>
                                                                    <option value="300" <?php selected($scan_limit_val, 300); ?>>300 Posts (Deep Coverage)</option>
                                                                    <option value="500" <?php selected($scan_limit_val, 500); ?>>500 Posts (Extended Archive)</option>
                                                                    <option value="1000" <?php selected($scan_limit_val, 1000); ?>>1,000 Posts (Heavy Archive)</option>
                                                                    <option value="-1" <?php selected($scan_limit_val, -1); ?>>All Published Posts (Full Site History)</option>
                                                                </select>
                                                            </div>
                                                        </div>
                                                        <div id="sd_vocab_scan_warning" style="display: <?php echo ($scan_limit_val > 300 || $scan_limit_val === -1) ? 'block' : 'none'; ?>; background: #fff8e5; border: 1px solid #f0c36d; color: #8a6d3b; padding: 8px 12px; border-radius: 4px; font-size: 11px; margin-bottom: 8px;">
                                                            <strong>⚠️ Large Archive Scan Warning:</strong> Indexing <span id="sd_scan_num_label"><?php echo $scan_limit_val === -1 ? 'all published' : $scan_limit_val; ?></span> posts scans your database for product titles &amp; taxonomies. Re-indexing large archives (>300 posts) may temporarily increase memory usage.
                                                        </div>
                                                        <?php 
                                                        $current_vocab = social_get_site_vocabulary_dictionary();
                                                        $vocab_count = count($current_vocab);
                                                        ?>
                                                        <div style="background: #fff; border: 1px solid #dcdcde; padding: 8px 12px; border-radius: 4px; font-size: 11px; margin-bottom: 6px;">
                                                            <strong>Vocabulary Cache Status:</strong> Currently indexed <strong><?php echo $vocab_count; ?></strong> brand &amp; topic terms from your site content.
                                                            <button type="submit" name="sd53_reindex_vocab" class="button button-small" style="margin-left: 10px;">Re-index Vocabulary Now</button>
                                                        </div>
                                                        <p class="description" style="margin-top: 2px; font-size: 11px;">Extracts proper nouns and product terms from your site so all-lowercase social hashtags (e.g. <code>#eliteminipc</code>) map automatically to your site's exact terminology.</p>
                                                    </div>
                                                </td>
                                            </tr>
                                            <tr>
                                                <th>Custom Tag Title Overrides</th>
                                                <td>
                                                    <div style="max-width: 550px;">
                                                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px;">
                                                            <label style="font-weight:600;">Tag Override Rules:</label>
                                                            <div>
                                                                <button type="button" class="button button-small" onclick="sdExportCustomOverrides()" title="Export custom overrides">📥 Export (.txt)</button>
                                                                <button type="button" class="button button-small" onclick="document.getElementById('sd_import_overrides_file').click()" title="Import custom overrides">📤 Import (.txt)</button>
                                                                <input type="file" id="sd_import_overrides_file" accept=".txt,.csv" style="display:none;" onchange="sdImportCustomOverrides(this)">
                                                            </div>
                                                        </div>
                                                        <textarea id="sd_title_tag_custom_overrides" name="social_digest_options[title_tag_custom_overrides]" rows="3" class="large-text" style="font-family: monospace; font-size: 12px;" placeholder="SnapdragonX=Snapdragon X, MINISFORUM*, GEEKOM*, NVIDIA* (one per line or comma-separated)"><?php echo esc_textarea($opts['title_tag_custom_overrides'] ?? ''); ?></textarea>
                                                        <p class="description" style="margin-top: 2px; font-size: 11px;">Optional escape hatch to manually map specific raw tags to custom formatted titles (e.g. <code>rawtag=Formatted Name</code> or wildcard prefix <code>MINISFORUM*</code> so <code>#MINISFORUMS5</code> becomes <code>MINISFORUM S5</code>). Use 📥 Export / 📤 Import to save or load backups as <code>.txt</code> files.</p>
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
                                                </td>
                                            </tr>
                                            <tr>
                                                <th>Global Closing Footer HTML</th>
                                                <td>
                                                    <textarea name="social_digest_options[footer_text]" rows="3" class="large-text" placeholder="&lt;hr&gt;&lt;p&gt;Follow us directly on social media for real-time updates!&lt;/p&gt;"><?php echo esc_textarea($opts['footer_text'] ?? '<hr><p>Follow us directly on social media for real-time updates!</p>'); ?></textarea>
                                                    <p class="description">Default HTML content appended at the bottom of every generated digest post.</p>
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
                                function sdToggleVocabWarning(val) {
                                    var box = document.getElementById('sd_vocab_scan_warning');
                                    var label = document.getElementById('sd_scan_num_label');
                                    var num = parseInt(val, 10);
                                    if (num > 300 || num === -1) {
                                        box.style.display = 'block';
                                        label.textContent = (num === -1) ? 'all published' : num;
                                    } else {
                                        box.style.display = 'none';
                                    }
                                }

                                function sdExportCustomOverrides() {
                                    var textarea = document.getElementById('sd_title_tag_custom_overrides');
                                    var content = textarea ? textarea.value.trim() : '';
                                    if (!content) {
                                        alert('There are no custom tag overrides to export.');
                                        return;
                                    }
                                    var blob = new Blob([content], { type: 'text/plain;charset=utf-8' });
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
                                        var importedText = e.target.result;
                                        var textarea = document.getElementById('sd_title_tag_custom_overrides');
                                        if (textarea) {
                                            if (textarea.value.trim() !== '') {
                                                if (confirm('Do you want to append the imported overrides to your existing list?

Click OK to Append, or Cancel to Replace existing overrides.')) {
                                                    textarea.value = textarea.value.trim() + '
' + importedText.trim();
                                                } else {
                                                    textarea.value = importedText.trim();
                                                }
                                            } else {
                                                textarea.value = importedText.trim();
                                            }
                                            alert('Import successful! Remember to click "Save Changes" at the bottom of the page to apply.');
                                        }
                                    };
                                    reader.readAsText(file);
                                    input.value = '';
                                }
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
                                        First-lines excerpt is currently disabled. Enable it under <a href="<?php echo esc_url(admin_url('edit.php?page=social-digest-settings&tab=settings#social_box_content')); ?>" style="color:#0284c7;">Settings &rarr; Publishing, Tags &amp; Article Framing</a>.
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
