<?php
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
                    $parent_item['extra_tags'] = social_dedupe_cased_tags(array_merge((array)($parent_item['extra_tags'] ?? []), (array)$child['extra_tags']));
                }
                if (empty($parent_item['thumb_image']) && !empty($child['thumb_image'])) {
                    $parent_item['thumb_image'] = $child['thumb_image'];
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
                $winner_tags = (array)($winner['extra_tags'] ?? []);
                if (preg_match_all('/(?<![&\w])#(?!\d+;)([\p{L}\p{N}_]*[\p{L}_][\p{L}\p{N}_\-]*)/u', $winner['text'] ?? '', $wm)) {
                    $winner_tags = array_merge($winner_tags, $wm[1]);
                }
                $secondary_tags = (array)($secondary['extra_tags'] ?? []);
                if (preg_match_all('/(?<![&\w])#(?!\d+;)([\p{L}\p{N}_]*[\p{L}_][\p{L}\p{N}_\-]*)/u', $secondary['text'] ?? '', $sm)) {
                    $secondary_tags = array_merge($secondary_tags, $sm[1]);
                }
                $winner['extra_tags'] = social_dedupe_cased_tags(array_merge($winner_tags, $secondary_tags));
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
        $thumb_url = esc_url_raw($item['thumb_image'] ?? '');
        if ($thumb_url === '' && !empty($item['media_html'])) {
            if (preg_match('/<img[^>]+src=["\']([^"\']+)["\']/i', $item['media_html'], $img_match)) {
                if (strpos($img_match[0], 'social-avatar') === false) {
                    $thumb_url = esc_url_raw($img_match[1]);
                }
            }
        }
        if ($thumb_url === '' && !empty($item['html'])) {
            if (preg_match('/<img[^>]+src=["\']([^"\']+)["\']/i', $item['html'], $img_match)) {
                if (strpos($img_match[0], 'social-avatar') === false) {
                    $thumb_url = esc_url_raw($img_match[1]);
                }
            }
        }
        $candidates[] = [
            'key' => $key,
            'network' => ($item['network'] ?? '') === 'mastodon' ? 'Mastodon' : 'Bluesky',
            'timestamp' => (int)$item['timestamp'],
            'html' => $html,
            'text' => wp_trim_words(wp_strip_all_tags($item['text'] ?? ''), 55, '…'),
            'full_text' => (string)($item['full_text'] ?? $item['text'] ?? ''),
            'url' => $url,
            'thumb_image' => $thumb_url,
            'extra_tags' => (array)($item['extra_tags'] ?? []),
            'excluded' => false,
            'pinned' => false,
            'commentary' => '',
        ];
    }

    $featured = '';
    $featured_post_tags = [];
    $featured_sel_id = null;
    $auto_thumb_enabled = !isset($opts['auto_thumb']) || !empty($opts['auto_thumb']);
    if ($auto_thumb_enabled) {
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
            $featured_sel_id = $sel['id'] ?? $sel['post_uri'] ?? null;
            $featured_raw_tags = (array)($sel['extra_tags'] ?? []);
            if (preg_match_all('/(?<![&\w])#(?!\d+;)([\p{L}\p{N}_]*[\p{L}_][\p{L}\p{N}_\-]*)/u', ($sel['full_text'] ?? '') . ' ' . ($sel['text'] ?? ''), $tm)) {
                $featured_raw_tags = array_merge($featured_raw_tags, $tm[1]);
            }
            $featured_post_tags = social_dedupe_cased_tags(array_filter($featured_raw_tags, function($t) { return mb_strlen($t) >= $min_tag_length; }));
        }
    }

    $custom_overrides = $opts['title_tag_custom_overrides'] ?? '';
    $all_imported_tags = [];
    $post_tags_list = [];
    
    // Add the featured post primary tag first so it is prioritized for the title
    if (!empty($featured_post_tags)) {
        $first_f = reset($featured_post_tags);
        if ($first_f) {
            $post_tags_list[] = [$first_f];
        }
        foreach ($featured_post_tags as $f_t) {
            $all_imported_tags[] = $f_t;
        }
    }

    foreach ($eligible as $item) {
        $item_raw_tags = (array)($item['extra_tags'] ?? []);
        if (preg_match_all('/(?<![&\w])#(?!\d+;)([\p{L}\p{N}_]*[\p{L}_][\p{L}\p{N}_\-]*)/u', ($item['full_text'] ?? '') . ' ' . ($item['text'] ?? ''), $tm)) {
            $item_raw_tags = array_merge($item_raw_tags, $tm[1]);
        }
        $valid_tags = social_dedupe_cased_tags(array_filter($item_raw_tags, function($t) { return mb_strlen($t) >= $min_tag_length; }));

        // Include all hashtags from every post for WordPress tags
        foreach ($valid_tags as $vt) {
            $all_imported_tags[] = $vt;
        }

        // For post title: skip if already handled via featured post, and take only the first hashtag
        $item_id = $item['id'] ?? $item['post_uri'] ?? null;
        if (!($featured_sel_id !== null && $item_id !== null && $item_id === $featured_sel_id && !empty($featured_post_tags))) {
            if (!empty($valid_tags)) {
                $post_tags_list[] = [$valid_tags[0]]; // Only first hashtag per post for title
            }
        }
    }
    
    // If no explicit hashtags exist across candidate posts, extract key topic terms/proper nouns
    if (empty($post_tags_list)) {
        $extracted_topics = social_extract_topic_keywords_from_posts($candidates);
        if (!empty($extracted_topics)) {
            $post_tags_list[] = $extracted_topics;
        }
    }

    $title_hashtags = social_rank_and_format_title_tags(
        $post_tags_list,
        $opts['default_tags'] ?? '',
        $opts['title_tag_enclosure'] ?? 'parentheses',
        $opts['title_tag_delimiter'] ?? 'oxford',
        $opts['title_tag_max_count'] ?? 3,
        $opts['title_tag_selection_strategy'] ?? 'first',
        $custom_overrides
    );    

    // Format all WordPress tags (capitalization, camelcase splitting, vocabulary matching)
    $tag_map = [];
    foreach (array_filter(array_map('trim', explode(',', $opts['default_tags'] ?? ''))) as $d_tag) {
        $fmt_d = social_split_camelcase_tag($d_tag, $custom_overrides);
        if ($fmt_d !== '') {
            $tag_map[mb_strtolower($fmt_d)] = $fmt_d;
        }
    }
    if (!isset($opts['extract_tags']) || !empty($opts['extract_tags'])) {
        foreach ($all_imported_tags as $raw_tag) {
            $fmt_tag = social_split_camelcase_tag($raw_tag, $custom_overrides);
            if ($fmt_tag !== '') {
                $tag_map[mb_strtolower($fmt_tag)] = $fmt_tag;
            }
        }
    }
    $tags = array_values($tag_map);
    if ($max_total_tags > 0 && count($tags) > $max_total_tags) {
        $tags = array_slice($tags, 0, $max_total_tags);
    }
    $title_tpl = $opts['title_template'] ?? 'Social Digest {hashtags}';
    $title = str_replace(['{hashtags}','{date}','{count}'], [$title_hashtags, wp_date(get_option('date_format'), time(), wp_timezone()), count($candidates)], $title_tpl);
    $title = preg_replace('/\s+/', ' ', $title);
    $title = preg_replace('/^\s*[:\-–|;,]\s*/u', '', $title);
    $title = preg_replace('/\s*[:\-–|;,]\s*$/u', '', $title);
    $title = trim($title);


    $next = [
        'version' => SOCIAL_DIGEST_VERSION,
        'created' => time(),
        'title_override' => $title,
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
    
    // Process custom WordPress Post Title override
    if (isset($input['custom_title'])) {
        $custom_title = sanitize_text_field($input['custom_title']);
        $out['title_override'] = $custom_title;
        $out['preview']['title'] = $custom_title;
    }

    $pinned_key   = sanitize_key($input['pinned_lead_post'] ?? '');
    $featured_sel = sanitize_key($input['selected_featured_post'] ?? '');

    // Process candidate updates first so candidate state is active
    if (!empty($input['candidate']) && is_array($input['candidate'])) {
        foreach ($input['candidate'] as $key => $raw) {
            $key = sanitize_key($key);
            if (!$key || !isset($old_candidates[$key])) continue;
            $base = $old_candidates[$key];
            $base['excluded']   = !empty($raw['excluded']);
            $base['pinned']     = ($key === $pinned_key && empty($base['excluded']));
            $base['commentary'] = sanitize_textarea_field($raw['commentary'] ?? '');
            if (!empty($base['html'])) $base['html'] = preg_replace('/(\w)&;(\w)/', "$1'$2", $base['html']);
            if (!empty($base['text'])) $base['text'] = preg_replace('/(\w)&;(\w)/', "$1'$2", $base['text']);
            if (!empty($base['full_text'])) $base['full_text'] = preg_replace('/(\w)&;(\w)/', "$1'$2", $base['full_text']);
            $out['candidates'][] = $base;
        }
    } else {
        $out['candidates'] = (array)($old['candidates'] ?? []);
    }

    $opts = get_option('social_digest_options', []);

    if ($featured_sel === 'none') {
        $out['featured_image_override_key'] = 'none';
        $out['preview']['featured_image']   = '';
    } elseif ($featured_sel !== '' && $featured_sel !== 'auto' && isset($old_candidates[$featured_sel]) && !empty($old_candidates[$featured_sel]['thumb_image'])) {
        $out['featured_image_override_key'] = $featured_sel;
        $out['preview']['featured_image']   = esc_url_raw($old_candidates[$featured_sel]['thumb_image']);
    } else {
        $out['featured_image_override_key'] = 'auto';
        $active_cands = array_values(array_filter($out['candidates'], function($c) { return empty($c['excluded']); }));
        $scope_cands = $active_cands;
        if (($opts['thumb_selection_scope'] ?? 'exclude_first') === 'exclude_first' && count($scope_cands) > 1) {
            $scope_cands = array_slice($scope_cands, 1);
        }
        $c_thumbs = [];
        foreach ($scope_cands as $ac) {
            if (!empty($ac['thumb_image'])) $c_thumbs[] = $ac['thumb_image'];
        }
        if (empty($c_thumbs)) {
            foreach ($active_cands as $ac) {
                if (!empty($ac['thumb_image'])) $c_thumbs[] = $ac['thumb_image'];
            }
        }
        if (!empty($c_thumbs)) {
            $mode_thumb = $opts['thumb_selection_mode'] ?? 'random';
            if ($mode_thumb === 'random') {
                $out['preview']['featured_image'] = esc_url_raw($c_thumbs[array_rand($c_thumbs)]);
            } else {
                $out['preview']['featured_image'] = esc_url_raw($c_thumbs[min(count($c_thumbs) - 1, max(0, (int)$mode_thumb - 1))]);
            }
        } elseif (empty($out['preview']['featured_image'])) {
            $out['preview']['featured_image'] = '';
        }
    }

    // Always synchronize and refresh preview tags from active included candidates
    $custom_overrides = $opts['title_tag_custom_overrides'] ?? '';
    $min_tag_len = max(1, (int)($opts['min_tag_length'] ?? 3));
    $max_tot_tags = max(1, (int)($opts['max_total_tags'] ?? 8));
    $all_imported_tags = [];
    foreach ($out['candidates'] as $cand) {
        if (!empty($cand['excluded'])) continue;
        $c_tags = (array)($cand['extra_tags'] ?? []);
        if (preg_match_all('/(?<![&\w])#(?!\d+;)([\p{L}\p{N}_]*[\p{L}_][\p{L}\p{N}_\-]*)/u', ($cand['full_text'] ?? '') . ' ' . ($cand['text'] ?? ''), $cm)) {
            $c_tags = array_merge($c_tags, $cm[1]);
        }
        foreach ($c_tags as $ct) {
            $clean_ct = trim(ltrim($ct, '#'));
            if (mb_strlen($clean_ct) >= $min_tag_len) {
                $all_imported_tags[] = $clean_ct;
            }
        }
    }
    $tag_map = [];
    foreach (array_filter(array_map('trim', explode(',', $opts['default_tags'] ?? ''))) as $d_tag) {
        $fmt_d = social_split_camelcase_tag($d_tag, $custom_overrides);
        if ($fmt_d !== '') $tag_map[mb_strtolower($fmt_d)] = $fmt_d;
    }
    if (!isset($opts['extract_tags']) || !empty($opts['extract_tags'])) {
        foreach ($all_imported_tags as $ct) {
            $fmt_t = social_split_camelcase_tag($ct, $custom_overrides);
            if ($fmt_t !== '') $tag_map[mb_strtolower($fmt_t)] = $fmt_t;
        }
    }
    $fresh_tags = array_values($tag_map);
    if ($max_tot_tags > 0 && count($fresh_tags) > $max_tot_tags) {
        $fresh_tags = array_slice($fresh_tags, 0, $max_tot_tags);
    }
    if (!empty($fresh_tags) || empty($out['preview']['tags'])) {
        $out['preview']['tags'] = $fresh_tags;
    }

    $header_override = wp_kses_post(trim($input['workbench_header_override'] ?? ''));
    $footer_override = wp_kses_post(trim($input['workbench_footer_override'] ?? ''));

    $out['header_override'] = $header_override;
    $out['footer_override'] = $footer_override;

    // Checkbox auto-enabled if text exists in header/footer override or explicitly checked
    if (!empty($input['framing_override_enabled']) || $header_override !== '' || $footer_override !== '') {
        $out['framing_override_enabled'] = true;
    } else {
        $out['framing_override_enabled'] = false;
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
        $block = preg_replace('/(\w)&;(\w)/', "$1'$2", $block);
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
    $raw_header_override = (string)($state['header_override'] ?? '');
    $clean_header_text = trim(wp_strip_all_tags(html_entity_decode($raw_header_override, ENT_QUOTES, 'UTF-8')));
    $clean_header_text = str_replace(["\xc2\xa0", '&nbsp;'], '', $clean_header_text);
    $header_is_custom = $use_override && trim($clean_header_text) !== '';

    $raw_footer_override = (string)($state['footer_override'] ?? '');
    $clean_footer_text = trim(wp_strip_all_tags(html_entity_decode($raw_footer_override, ENT_QUOTES, 'UTF-8')));
    $clean_footer_text = str_replace(["\xc2\xa0", '&nbsp;'], '', $clean_footer_text);
    $footer_is_custom = $use_override && trim($clean_footer_text) !== '';

    $header = $use_override ? trim($raw_header_override) : trim($opts['header_text'] ?? '');
    $footer = $use_override ? trim($raw_footer_override) : trim($opts['footer_text'] ?? '');

    $apply_nosnippet_header = !empty($opts['nosnippet_header']);
    if ($apply_nosnippet_header && !empty($opts['allow_snippet_on_custom_header']) && $header_is_custom) {
        $apply_nosnippet_header = false;
    }

    $apply_nosnippet_footer = !empty($opts['nosnippet_footer']);
    if ($apply_nosnippet_footer && !empty($opts['allow_snippet_on_custom_footer']) && $footer_is_custom) {
        $apply_nosnippet_footer = false;
    }

    if ($header !== '') {
        $header = $apply_nosnippet_header
            ? '<section data-nosnippet class="social-digest-header">' . $header . '</section>'
            : '<section class="social-digest-header">' . $header . '</section>';
    }

    if ($footer !== '') {
        $footer = $apply_nosnippet_footer
            ? '<section data-nosnippet class="social-digest-footer">' . $footer . '</section>'
            : '<section class="social-digest-footer">' . $footer . '</section>';
    }

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
        $raw_title = !empty($state['title_override']) ? $state['title_override'] : ($state['preview']['title'] ?? 'Social Digest');
        $title = sanitize_text_field($raw_title);
        $base_title = preg_replace('/\s+/', ' ', $title);
        $base_title = preg_replace('/^\s*[:\-–|]\s*/u', '', $base_title);
        $base_title = preg_replace('/\s*[:\-–|]\s*$/u', '', $base_title);
        $base_title = trim($base_title);
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
                'bullet'   => ' <b>•</b> ',
                'custom'   => $custom_delim,
            ];
            $delim = $delim_map[$delim_key] ?? ' // ';
            $max_items = absint($opts['excerpt_max_items'] ?? 0);
            $append_ellipsis = !empty($opts['excerpt_append_ellipsis']);
            $post_excerpt = social_build_first_lines_excerpt($state['candidates'] ?? [], $delim, $max_items, $append_ellipsis);
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

        $target_tags = array_values(array_map('sanitize_text_field', (array)($state['preview']['tags'] ?? [])));
        if (empty($target_tags)) {
            // Guaranteed fallback: extract and format tags directly from included candidates
            $custom_overrides = $opts['title_tag_custom_overrides'] ?? '';
            $min_tag_len = max(1, (int)($opts['min_tag_length'] ?? 3));
            $max_tot_tags = max(1, (int)($opts['max_total_tags'] ?? 8));
            $extracted_cand_tags = [];
            foreach ((array)($state['candidates'] ?? []) as $cand) {
                if (!empty($cand['excluded'])) continue;
                $c_tags = (array)($cand['extra_tags'] ?? []);
                if (preg_match_all('/(?<![&\w])#(?!\d+;)([\p{L}\p{N}_]*[\p{L}_][\p{L}\p{N}_\-]*)/u', ($cand['full_text'] ?? '') . ' ' . ($cand['text'] ?? ''), $cm)) {
                    $c_tags = array_merge($c_tags, $cm[1]);
                }
                foreach ($c_tags as $ct) {
                    $clean_ct = trim(ltrim($ct, '#'));
                    if (mb_strlen($clean_ct) >= $min_tag_len) {
                        $extracted_cand_tags[] = $clean_ct;
                    }
                }
            }
            $tag_map = [];
            foreach (array_filter(array_map('trim', explode(',', $opts['default_tags'] ?? ''))) as $d_tag) {
                $fmt_d = social_split_camelcase_tag($d_tag, $custom_overrides);
                if ($fmt_d !== '') $tag_map[mb_strtolower($fmt_d)] = $fmt_d;
            }
            if (!isset($opts['extract_tags']) || !empty($opts['extract_tags'])) {
                foreach ($extracted_cand_tags as $ect) {
                    $fmt_t = social_split_camelcase_tag($ect, $custom_overrides);
                    if ($fmt_t !== '') $tag_map[mb_strtolower($fmt_t)] = $fmt_t;
                }
            }
            $target_tags = array_values($tag_map);
            if ($max_tot_tags > 0 && count($target_tags) > $max_tot_tags) {
                $target_tags = array_slice($target_tags, 0, $max_tot_tags);
            }
        }

        $post_args = [
            'post_title'   => $title,
            'post_content' => $built['content'],
            'post_excerpt' => $post_excerpt,
            'post_status'  => $status_to_use,
            'post_author'  => $author_id,
            'post_type'    => 'post',
            'tags_input'   => $target_tags,
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

        // Direct, unconditional taxonomy term assignment
        // Required because wp_insert_post() silently drops 'tags_input' during cron jobs / unauthenticated runs
        if (!empty($target_tags)) {
            wp_set_post_tags($post_id, $target_tags, false);
            wp_set_object_terms($post_id, $target_tags, 'post_tag', false);
        }

        // Advance cutoff timestamps immediately upon successful post insertion (inspecting all candidates including excluded ones)
        $max_bsky_ts = 0;
        $max_masto_ts = 0;

        foreach ((array)($state['candidates'] ?? []) as $cand) {
            $cand_ts = (int)($cand['timestamp'] ?? 0);
            $net = strtolower($cand['network'] ?? '');
            if ($net === 'bluesky' || $net === 'bsky') {
                if ($cand_ts > $max_bsky_ts) $max_bsky_ts = $cand_ts;
            } elseif ($net === 'mastodon') {
                if ($cand_ts > $max_masto_ts) $max_masto_ts = $cand_ts;
            }
        }

        $old_state = social_workbench_state();
        $state_bsky = (int)($state['cutoffs']['bsky'] ?? $old_state['cutoffs']['bsky'] ?? 0);
        $state_masto = (int)($state['cutoffs']['masto'] ?? $old_state['cutoffs']['masto'] ?? 0);

        $new_bsky = max($state_bsky, $max_bsky_ts, (int)get_option('bsky_last_digest_time', 0));
        $new_masto = max($state_masto, $max_masto_ts, (int)get_option('masto_last_digest_time', 0));

        if ($new_bsky > 0) {
            update_option('bsky_last_digest_time', $new_bsky);
        }
        if ($new_masto > 0) {
            update_option('masto_last_digest_time', $new_masto);
        }

        // Cleanly clear workbench state on successful creation to prevent stale re-curation
        social_clear_workbench_state();

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

        $auto_thumb_enabled = !isset($opts['auto_thumb']) || !empty($opts['auto_thumb']);
        $featured_url = trim((string)($state['preview']['featured_image'] ?? ''));
        $override_key = $state['featured_image_override_key'] ?? 'auto';

        $candidates = (array)($state['candidates'] ?? []);
        $active_cands = array_values(array_filter($candidates, function($c) { return empty($c['excluded']); }));
        $ordered_cands = $active_cands;
        if (($opts['thumb_selection_scope'] ?? 'exclude_first') === 'exclude_first' && count($ordered_cands) > 1) {
            $ordered_cands = array_slice($ordered_cands, 1);
        }
        $thumb_pool = [];
        foreach ($ordered_cands as $cand) {
            if (!empty($cand['thumb_image'])) $thumb_pool[] = $cand['thumb_image'];
        }
        if (empty($thumb_pool)) {
            foreach ($active_cands as $cand) {
                if (!empty($cand['thumb_image'])) $thumb_pool[] = $cand['thumb_image'];
            }
        }
        // Fallback: search raw markup of active candidates if thumb_image was empty
        if (empty($thumb_pool)) {
            foreach ($active_cands as $cand) {
                $cand_markup = ($cand['media_html'] ?? '') . ' ' . ($cand['html'] ?? '');
                if (preg_match('/<img[^>]+src=["\']([^"\']+)["\']/i', $cand_markup, $im)) {
                    if (strpos($im[0], 'social-avatar') === false) {
                        $thumb_pool[] = esc_url_raw($im[1]);
                    }
                }
            }
        }

        if (empty($featured_url) && $auto_thumb_enabled && $override_key !== 'none' && !empty($thumb_pool)) {
            $mode_thumb = $opts['thumb_selection_mode'] ?? 'random';
            if ($mode_thumb === 'random') {
                $featured_url = $thumb_pool[array_rand($thumb_pool)];
            } else {
                $featured_url = $thumb_pool[min(count($thumb_pool) - 1, max(0, (int)$mode_thumb - 1))];
            }
        }

        if ($override_key !== 'none') {
            $urls_to_try = [];
            if (!empty($featured_url)) $urls_to_try[] = $featured_url;
            foreach ($thumb_pool as $tp) {
                if (!in_array($tp, $urls_to_try, true)) $urls_to_try[] = $tp;
            }
            foreach ($urls_to_try as $try_url) {
                try {
                    $attachment_id = social_sideload_image_by_mime($try_url, $post_id, $title);
                    if ($attachment_id) {
                        set_post_thumbnail($post_id, $attachment_id);
                        break;
                    }
                } catch (\Throwable $e) {
                    error_log('Social Digest featured image sideload error: ' . $e->getMessage());
                }
            }
        }

        // Purge W3 Total Cache and active page/object caches for the newly published digest
        if (function_exists(__NAMESPACE__ . '\\social_purge_site_caches')) {
            social_purge_site_caches($post_id);
        } elseif (function_exists('social_purge_site_caches')) {
            \social_purge_site_caches($post_id);
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
    
    $state = $state_res['state'] ?? [];
    $active_cands = array_filter((array)($state['candidates'] ?? []), function($c) { return empty($c['excluded']); });
    if (empty($active_cands)) {
        // Advance cutoffs even if active queue is empty so fetched items are cleared from backlog
        $new_bsky = (int)($state['cutoffs']['bsky'] ?? 0);
        $new_masto = (int)($state['cutoffs']['masto'] ?? 0);
        if ($new_bsky > 0) update_option('bsky_last_digest_time', $new_bsky);
        if ($new_masto > 0) update_option('masto_last_digest_time', $new_masto);
        return ['success' => false, 'message' => 'No new active posts found since the active cutoff timestamp. Cutoffs advanced and queue cleared.'];
    }
    return social_publish_workbench_run($state);
}
