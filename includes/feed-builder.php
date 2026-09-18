<?php
/**
 * Social Digest - Feed Builder & Workbench State Engine
 */

namespace SocialDigest;

if (!defined('ABSPATH')) exit;

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

function social_collapse_thread_posts($eligible, $opts) {
    if (isset($opts['collapse_threads']) && empty($opts['collapse_threads'])) {
        return $eligible;
    }
    if (count($eligible) < 2) {
        return $eligible;
    }

    usort($eligible, function($a, $b) {
        return (int)($a['timestamp'] ?? 0) <=> (int)($b['timestamp'] ?? 0);
    });

    $parents = [];
    $threads = [];

    foreach ($eligible as $idx => $item) {
        $author = strtolower(trim($item['author_handle'] ?? ''));
        $post_uri = $item['post_uri'] ?? '';
        $parent_uri = $item['reply_parent_uri'] ?? '';
        $root_uri = $item['reply_root_uri'] ?? '';

        $found_parent = null;
        if ($author !== '' && !empty($parent_uri)) {
            foreach ($parents as $p_idx => $parent_item) {
                if (strtolower(trim($parent_item['author_handle'] ?? '')) === $author) {
                    $p_uri = $parent_item['post_uri'] ?? '';
                    $p_root = $parent_item['reply_root_uri'] ?? '';
                    if ($p_uri === $parent_uri || $p_uri === $root_uri || ($p_root !== '' && $p_root === $root_uri)) {
                        $found_parent = $p_idx;
                        break;
                    }
                }
            }
        }

        if ($found_parent !== null) {
            $threads[$found_parent][] = $item;
        } else {
            $parents[$idx] = $item;
        }
    }

    if (empty($threads)) {
        return $eligible;
    }

    $collapsed = [];
    foreach ($parents as $p_idx => $parent_item) {
        if (!empty($threads[$p_idx])) {
            $child_posts = $threads[$p_idx];
            $child_count = count($child_posts);

            $thread_html = '<details class="social-thread-collapse" data-nosnippet style="margin-top:14px; border-top:1px dashed #cbd5e1; padding-top:10px;">';
            $thread_html .= '<summary style="cursor:pointer; font-weight:700; font-size:13px; color:#0284c7; outline:none; display:inline-flex; align-items:center; gap:6px; user-select:none;">';
            $thread_html .= '<span>🧵 View full thread (' . $child_count . ' follow-up post' . ($child_count > 1 ? 's' : '') . ')</span>';
            $thread_html .= '</summary>';
            $thread_html .= '<div class="social-thread-replies" style="margin-top:10px; padding-left:12px; border-left:3px solid #0284c7; display:flex; flex-direction:column; gap:12px;">';

            foreach ($child_posts as $child) {
                $c_body = !empty($child['body_html']) ? $child['body_html'] : social_clean_body_text($child['text'] ?? '', false);
                $c_media = $child['media_html'] ?? '';
                $c_date = wp_date(get_option('date_format') . ' ' . get_option('time_format'), (int)$child['timestamp'], wp_timezone());

                $thread_html .= '<div class="social-thread-reply-item" style="font-size:14px; line-height:1.5; color:#334155;">';
                $thread_html .= '<div style="font-size:11px; color:#64748b; margin-bottom:4px; font-weight:600;">' . esc_html($c_date) . '</div>';
                $thread_html .= '<div>' . $c_body . '</div>';
                if ($c_media) $thread_html .= '<div style="margin-top:8px;">' . $c_media . '</div>';
                $thread_html .= '</div>';

                $parent_item['text'] .= ' ' . ($child['text'] ?? '');
                if (!empty($child['extra_tags'])) {
                    $parent_item['extra_tags'] = array_values(array_unique(array_merge((array)($parent_item['extra_tags'] ?? []), (array)$child['extra_tags'])));
                }
            }

            $thread_html .= '</div></details>';

            $parent_item['media_html'] = ($parent_item['media_html'] ?? '') . $thread_html;
            $parent_item['html'] = social_render_native_card($parent_item, $opts);
        }

        $collapsed[] = $parent_item;
    }

    return $collapsed;
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
    $excluded_words = $opts['excluded_words'] ?? '';
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
        if (!empty($excluded_words) && social_post_matches_filter($text, $excluded_words)) {
            continue;
        }
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

                // If winner didn't have link card but secondary did, merge media preview
                if (strpos($winner['media_html'] ?? '', 'social-link-card') === false && strpos($secondary['media_html'] ?? '', 'social-link-card') !== false) {
                    $winner['media_html'] = ($winner['media_html'] ?? '') . $secondary['media_html'];
                }

                // Re-render native card HTML with merged dual-platform links and metadata
                $winner['html'] = social_render_native_card($winner, $opts);
            }
            $merged[] = $winner;
        }
        foreach ($masto as $i => $mp) if (!isset($matched[$i])) $merged[] = $mp;
        $eligible = $merged;
    }

    // Group multi-post threads into unified master cards if enabled before slicing count
    $eligible = social_collapse_thread_posts($eligible, $opts);

    // Re-sort newest-first so max_posts slice and chronological thumbnail extraction are strictly accurate
    usort($eligible, function($a, $b) { return (int)($b['timestamp'] ?? 0) <=> (int)($a['timestamp'] ?? 0); });
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
            'full_text' => (string)($item['text'] ?? ''),
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

    $use_blocks = !isset($opts['output_gutenberg_blocks']) || !empty($opts['output_gutenberg_blocks']);

    if ($use_blocks && (function_exists(__NAMESPACE__ . '\\social_wrap_as_gutenberg_block') || function_exists('social_wrap_as_gutenberg_block'))) {
        $block_elements = [];
        if ($header !== '') {
            $block_elements[] = social_wrap_as_gutenberg_block($header, 'core/html');
        }
        foreach ($selected as $s_card) {
            $block_elements[] = social_wrap_as_gutenberg_block($s_card, 'core/html');
        }
        if ($footer !== '') {
            $block_elements[] = social_wrap_as_gutenberg_block($footer, 'core/html');
        }
        $content = implode("\n\n", $block_elements);
    } else {
        $content = ($header !== '' ? $header . "\n\n" : '') . implode("\n\n", $selected) . ($footer !== '' ? "\n\n" . $footer : '');
    }

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

        // Generate post excerpt
        $post_excerpt = '';
        if (!empty($opts['excerpt_first_lines'])) {
            $delim_key = $opts['excerpt_delimiter'] ?? 'slash';
            $custom_delim = $opts['excerpt_custom_delimiter'] ?? ' // ';
            $delim_map = [
                'slash'    => ' // ',
                'ellipsis' => ' ... ',
                'period'   => '. ',
                'dash'     => ' — ',
                'bullet'   => ' • ',
                'custom'   => $custom_delim,
            ];
            $delim = $delim_map[$delim_key] ?? ' // ';
            $max_items = absint($opts['excerpt_max_items'] ?? 0);
            $post_excerpt = social_build_first_lines_excerpt($state['candidates'] ?? [], $delim, $max_items);
        }

        if (empty($post_excerpt)) {
            // Generate standard clean, plain-text excerpt fallback
            $use_override = !empty($state['framing_override_enabled']);
            $header = $use_override ? trim($state['header_override'] ?? '') : trim($opts['header_text'] ?? '');
            $excerpt_text = wp_strip_all_tags($header) . ' ';
            foreach ((array)($state['candidates'] ?? []) as $c) {
                if (!empty($c['excluded'])) continue;
                $excerpt_text .= wp_strip_all_tags($c['full_text'] ?? $c['text'] ?? '') . ' ';
            }
            $post_excerpt = wp_trim_words(trim($excerpt_text), 55, ' [&hellip;]');
        }

        $post_args = [
            'post_title'   => $title,
            'post_content' => $built['content'],
            'post_excerpt' => $post_excerpt,
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

        // Sideload all embedded post media attachments into Media Library if enabled
        if (!empty($opts['sideload_all_media'])) {
            $updated_content = social_sideload_content_media($built['content'], $post_id);
            if ($updated_content !== $built['content']) {
                wp_update_post([
                    'ID'           => $post_id,
                    'post_content' => $updated_content,
                ]);
            }
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
// 8. SCHEDULED RUNNER & DRY-RUN SIMULATOR
// ==========================================

function social_run_digest_import($is_dry_run = false) {
    $state_res = social_fetch_workbench_candidates();
    if (empty($state_res['success'])) return $state_res;
    return social_publish_workbench_run($state_res['state']);
}
