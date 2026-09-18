<?php
namespace SocialDigest;

if (!defined('ABSPATH')) exit;

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
        $repost_html = '<div class="social-repost-banner" data-nosnippet style="font-size:12px; font-weight:700; color:#0284c7; margin-bottom:10px; display:flex; align-items:center; gap:5px;">&#x1F501; Reposted by @' . $repost_user . '</div>';
    }

    // Build the Native Card HTML
    $html = '<div class="social-post social-card" style="border:1px solid #e2e8f0; border-left:4px solid #0284c7; border-radius:12px; padding:18px; margin:26px auto; background:#ffffff; box-shadow:0 2px 6px rgba(0,0,0,0.04); max-width:600px; box-sizing:border-box; font-family:-apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, Helvetica, Arial, sans-serif;">';
    if ($repost_html) {
        $html .= $repost_html;
    }

    // Card Header (Avatar + Name + Inline Handles with Follow Links)
    $html .= '<div class="social-card-header" data-nosnippet style="display:flex; align-items:center; gap:12px; margin-bottom:14px; padding-bottom:12px; border-bottom:1px solid #f1f5f9;">';
    if ($avatar_url) {
        $html .= '<img src="' . esc_url($avatar_url) . '" alt="' . esc_attr($author_name) . '" class="social-avatar" style="width:44px; height:44px; border-radius:50%; object-fit:cover; border:1px solid #e2e8f0; flex-shrink:0;" />';
    }
    $html .= '<div style="min-width:0; line-height:1.35; flex:1;">';
    $html .= '<div class="social-author-name" style="font-weight:700; font-size:15px; color:#0f172a; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">' . $author_name . '</div>';
    $html .= '<div class="social-author-handles" style="font-size:13px; color:#64748b; display:flex; flex-wrap:wrap; align-items:center; column-gap:8px; row-gap:2px;">' . implode('<span class="social-identity-sep" style="color:#cbd5e1; margin:0 2px;">·</span>', $social_identities) . '</div>';
    $html .= '</div>';
    $html .= '</div>';

    // Body content
    $html .= '<div class="social-card-body" style="font-size:15px; line-height:1.6; color:#1e293b; margin-bottom:14px; overflow-wrap:anywhere; word-break:break-word;">';
    if (!empty($item['body_html'])) {
        $body_text = $item['body_html'];
    } else {
        $has_card = !empty($item['media_html']) && (strpos($item['media_html'], 'social-link-card') !== false);
        $body_text = social_clean_body_text($item['text'] ?? '', $has_card);
    }
    $html .= $body_text;
    $html .= '</div>';

    $media_content = $item['media_html'] ?? '';
    // In posts with both link preview cards and image galleries/videos,
    // ensure the link preview card always displays above the gallery
    if ($media_content !== '' && strpos($media_content, 'social-link-card') !== false) {
        $first_embed_pos = false;
        foreach (['social-embed-images', 'social-embed-media', 'social-embed-video', 'social-embed-gifv'] as $embed_class) {
            $pos = strpos($media_content, $embed_class);
            if ($pos !== false && ($first_embed_pos === false || $pos < $first_embed_pos)) {
                $first_embed_pos = $pos;
            }
        }
        $card_pos = strpos($media_content, 'social-link-card');
        if ($first_embed_pos !== false && $card_pos !== false && $card_pos > $first_embed_pos) {
            if (preg_match('/(<div class="social-link-card"[^>]*>.*?<\/div>\s*<\/div>)/s', $media_content, $m)) {
                $card_snippet = $m[1];
                $gallery_snippet = trim(str_replace($card_snippet, '', $media_content));
                $media_content = $card_snippet . $gallery_snippet;
            }
        }
    }

    if (!empty($media_content)) {
        $html .= $media_content;
    }

    // Card Footer (Date on left + Interactive Platform badges on right)
    $html .= '<div class="social-card-footer" data-nosnippet style="display:flex; flex-wrap:wrap; align-items:center; justify-content:space-between; gap:10px; margin-top:14px; padding-top:12px; border-top:1px solid #f1f5f9; font-size:13px; color:#64748b;">';
    $html .= '<div class="social-timestamp" style="display:flex; align-items:center;">';
    $html .= '<span>' . esc_html($formatted_date) . '</span>';
    $html .= '</div>';

    // Platform action badges with live engagement counters and smart mobile deep-linking
    $enable_deep_links = !isset($opts['mobile_deep_links']) || !empty($opts['mobile_deep_links']);
    $badges = [];
    if ($has_bsky) {
        $bsky_data = $links['bsky'];
        $b_url = esc_url($bsky_data['url'] ?? '');
        $b_likes = absint($bsky_data['likes'] ?? 0);
        $like_str = ($b_likes > 0) ? ' <span class="social-stat-pill" style="font-size:11px; background:#eff6ff; color:#1d4ed8; padding:1px 6px; border-radius:9999px; margin-left:3px;">❤️ ' . $b_likes . '</span>' : '';
        $b_deep_attr = '';
        if ($enable_deep_links && preg_match('/bsky\.app\/profile\/([^\/]+)\/post\/([^\/]+)/i', $b_url, $bm)) {
            $b_deep_url = 'bsky://profile/' . $bm[1] . '/post/' . $bm[2];
            $b_deep_attr = ' data-app-url="' . esc_attr($b_deep_url) . '"';
        }
        $badges[] = '<a href="' . $b_url . '"' . $b_deep_attr . ' target="_blank" rel="noopener" class="social-badge bsky-badge" style="display:inline-flex; align-items:center; gap:5px; padding:4px 10px; border-radius:6px; background:#f0f9ff; color:#0284c7; text-decoration:none; border:1px solid #bae6fd; font-weight:600; font-size:12px;" title="View on Bluesky">🦋 Bluesky' . $like_str . ' ↗</a>';
    }
    if ($has_masto) {
        $masto_data = $links['mastodon'];
        $m_url = esc_url($masto_data['url'] ?? '');
        $m_favs = absint($masto_data['favs'] ?? 0);
        $m_boosts = absint($masto_data['boosts'] ?? 0);
        $m_stats = [];
        if ($m_favs > 0) $m_stats[] = '⭐ ' . $m_favs;
        if ($m_boosts > 0) $m_stats[] = '🔁 ' . $m_boosts;
        $m_stat_str = $m_stats ? ' <span class="social-stat-pill" style="font-size:11px; background:#faf5ff; color:#6b21a8; padding:1px 6px; border-radius:9999px; margin-left:3px;">' . implode(' · ', $m_stats) . '</span>' : '';
        $m_deep_attr = '';
        if ($enable_deep_links && !empty($m_url)) {
            $m_deep_url = 'mastodon://' . preg_replace('/^https?:\/\//i', '', $m_url);
            $m_deep_attr = ' data-app-url="' . esc_attr($m_deep_url) . '"';
        }
        $badges[] = '<a href="' . $m_url . '"' . $m_deep_attr . ' target="_blank" rel="noopener" class="social-badge masto-badge" style="display:inline-flex; align-items:center; gap:5px; padding:4px 10px; border-radius:6px; background:#faf5ff; color:#7e22ce; text-decoration:none; border:1px solid #e9d5ff; font-weight:600; font-size:12px;" title="View on Mastodon">🐘 Mastodon' . $m_stat_str . ' ↗</a>';
    }

    if ($badges) {
        $html .= '<div class="social-platform-badges" style="display:flex; flex-wrap:wrap; align-items:center; gap:6px;">' . implode('', $badges) . '</div>';
    }

    $html .= '</div>';
    $html .= '</div>';

    return $html;
}

function social_fetch_bluesky($handle, $last_check, $keep_threads, $include_reposts) {
    if (empty($handle)) return ['posts' => [], 'newest_timestamp' => $last_check];

    $opts = get_option('social_digest_options', []);
    $api_url = 'https://public.api.bsky.app/xrpc/app.bsky.feed.getAuthorFeed?actor=' . urlencode($handle) . '&limit=50';
    $cache_key = 'bsky_feed_' . $handle;
    $response = social_safe_remote_get_with_backoff($api_url, ['timeout' => 20], 2, $cache_key);
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
        $post_uri = $post['uri'];
        $rkey     = substr($post_uri, strrpos($post_uri, '/') + 1);
        $author_handle = $post['author']['handle'] ?? $handle;
        $author_name   = esc_html($post['author']['displayName'] ?? $author_handle);
        $author_avatar = esc_url_raw($post['author']['avatar'] ?? '');
        $author_profile_url = 'https://bsky.app/profile/' . $author_handle;
        $web_url       = 'https://bsky.app/profile/' . $author_handle . '/post/' . $rkey;

        $first_image_url = null;
        $gallery_html = '';
        $card_html = '';

        if (isset($post['embed']['images']) && is_array($post['embed']['images'])) {
            $images = $post['embed']['images'];
            $num_imgs = count($images);
            if ($num_imgs > 1) {
                $cols = min($num_imgs, 4);
                $gallery_html .= '<div class="social-embed-images" style="display:grid; grid-template-columns: repeat(' . $cols . ', 1fr); gap:8px; margin:10px 0;">';
            } else {
                $gallery_html .= '<div class="social-embed-images" style="margin:10px 0;">';
            }
            foreach ($images as $img) {
                $img_url = esc_url($img['thumb'] ?? $img['fullsize'] ?? '');
                $alt_raw = trim($img['alt'] ?? '');
                $alt_txt = esc_attr($alt_raw ?: 'Bluesky image');
                $alt_badge = '';
                if ($alt_raw !== '' && strtolower($alt_raw) !== 'bluesky image') {
                    $alt_badge = '<span data-nosnippet class="social-alt-badge" onclick="event.preventDefault(); event.stopPropagation(); alert(this.getAttribute(\'title\'));" style="position:absolute; bottom:6px; left:6px; background:rgba(15,23,42,0.85); color:#ffffff; font-size:10px; font-weight:700; padding:2px 5px; border-radius:4px; letter-spacing:0.5px; backdrop-filter:blur(4px); box-shadow:0 1px 3px rgba(0,0,0,0.3); z-index:3; pointer-events:auto; cursor:help;" title="ALT: ' . $alt_txt . '">ALT</span>';
                }
                if ($img_url) {
                    if (!$first_image_url) $first_image_url = $img_url;
                    $title_attr = ($num_imgs > 1) ? 'View full gallery on Bluesky' : 'View image on Bluesky';
                    $gallery_html .= '<a href="' . esc_url($web_url) . '" target="_blank" rel="noopener" title="' . esc_attr($title_attr) . '" style="display:block; text-decoration:none;">';
                    if ($num_imgs > 1) {
                        $gallery_html .= '<figure style="margin:0; position:relative; overflow:hidden; border-radius:6px; max-height:130px;"><img src="' . $img_url . '" alt="' . $alt_txt . '" style="width:100%; height:130px; max-height:130px; object-fit:cover; border-radius:6px; display:block;" />' . $alt_badge . '</figure>';
                    } else {
                        $gallery_html .= '<figure style="margin:0; position:relative;"><img src="' . $img_url . '" alt="' . $alt_txt . '" style="max-width:100%; max-height:220px; width:auto; border-radius:8px; display:block;" />' . $alt_badge . '</figure>';
                    }
                    $gallery_html .= '</a>';
                }
            }
            $gallery_html .= '</div>';
        }

        // Bluesky Video Embed Support (app.bsky.embed.video)
        if (
            (isset($post['embed']['$type']) && $post['embed']['$type'] === 'app.bsky.embed.video') ||
            isset($post['embed']['playlist'])
        ) {
            $video_thumb = esc_url($post['embed']['thumbnail'] ?? '');
            $alt_raw = trim($post['embed']['alt'] ?? '');
            $alt_txt = esc_attr($alt_raw ?: 'Bluesky video');
            if ($video_thumb && !$first_image_url) {
                $first_image_url = $video_thumb;
            }
            $gallery_html .= '<div class="social-embed-video" style="margin:12px 0; position:relative; border-radius:8px; overflow:hidden; background:#000;">';
            $gallery_html .= '<a href="' . esc_url($web_url) . '" target="_blank" rel="noopener" title="Watch video on Bluesky" style="display:block; position:relative; text-decoration:none;">';
            if ($video_thumb) {
                $gallery_html .= '<img src="' . $video_thumb . '" alt="' . $alt_txt . '" style="width:100%; max-height:220px; object-fit:contain; display:block; background:#0f172a;" />';
            } else {
                $gallery_html .= '<div style="height:180px; width:100%; background:#0f172a; display:flex; align-items:center; justify-content:center;"></div>';
            }
            // Play Button Overlay
            $gallery_html .= '<div class="social-video-play-btn" style="position:absolute; top:50%; left:50%; transform:translate(-50%, -50%); width:56px; height:56px; border-radius:50%; background:rgba(15,23,42,0.85); display:flex; align-items:center; justify-content:center; box-shadow:0 4px 12px rgba(0,0,0,0.5); border:2px solid rgba(255,255,255,0.8);">';
            $gallery_html .= '<span style="color:#ffffff; font-size:22px; margin-left:3px; line-height:1;">▶</span>';
            $gallery_html .= '</div>';
            $gallery_html .= '<span data-nosnippet style="position:absolute; bottom:8px; right:8px; background:rgba(0,0,0,0.75); color:#fff; font-size:11px; font-weight:700; padding:2px 6px; border-radius:4px; letter-spacing:0.5px;">VIDEO ↗</span>';
            $gallery_html .= '</a>';
            $gallery_html .= '</div>';
        }

        $extracted_urls = [];
        $links_map = [];

        // Check facets
        if (!empty($post['record']['facets']) && is_array($post['record']['facets'])) {
            foreach ($post['record']['facets'] as $facet) {
                if (!empty($facet['features']) && is_array($facet['features'])) {
                    foreach ($facet['features'] as $feat) {
                        if (($feat['$type'] ?? '') === 'app.bsky.richtext.facet#link' && !empty($feat['uri'])) {
                            $u = esc_url_raw($feat['uri']);
                            $extracted_urls[] = $u;
                            if (isset($facet['index']['byteStart'], $facet['index']['byteEnd'])) {
                                $start = (int)$facet['index']['byteStart'];
                                $end   = (int)$facet['index']['byteEnd'];
                                $disp_text = substr($text, $start, $end - $start);
                                if ($disp_text) {
                                    $links_map[$disp_text] = $u;
                                }
                            }
                        }
                    }
                }
            }
        }

        if (isset($post['embed']['external']['uri'])) $extracted_urls[] = esc_url_raw($post['embed']['external']['uri']);
        if (preg_match_all('/\b(?:https?:\/\/|www\.)[^\s<"\'\)]+/i', $text, $u_m)) {
            foreach ($u_m[0] as $match_url) {
                $extracted_urls[] = esc_url_raw($match_url);
            }
        }
        $extracted_urls = array_values(array_unique(array_filter($extracted_urls)));

        $has_card = false;
        $primary_card_url = '';

        if (isset($post['embed']['external'])) {
            $ext = $post['embed']['external'];
            $link_url   = esc_url_raw($ext['uri'] ?? '');
            $link_title = $ext['title'] ?? '';
            $link_desc  = $ext['description'] ?? '';
            $link_thumb = esc_url_raw($ext['thumb'] ?? '');

            if (!$link_thumb && $link_url) {
                $link_thumb = social_get_og_image_cached($link_url);
            }

            if (!$first_image_url && $link_thumb) $first_image_url = $link_thumb;

            $domain = parse_url($link_url, PHP_URL_HOST);
            $card_html .= social_render_link_card_html($link_url, $link_title, $link_desc, $link_thumb, $domain);
            $has_card = true;
            $primary_card_url = $link_url;
        } elseif (!empty($extracted_urls[0])) {
            // Fallback: If Bluesky post contained a link without external embed metadata, fetch OG card
            $target_url = $extracted_urls[0];
            $og_card = social_get_og_card_cached($target_url);
            if ($og_card && (!empty($og_card['title']) || !empty($og_card['image']))) {
                if (!$first_image_url && !empty($og_card['image'])) {
                    $first_image_url = $og_card['image'];
                }
                $card_html .= social_render_link_card_html(
                    $target_url,
                    $og_card['title'] ?? '',
                    $og_card['description'] ?? '',
                    $og_card['image'] ?? '',
                    $og_card['domain'] ?? parse_url($target_url, PHP_URL_HOST)
                );
                $has_card = true;
                $primary_card_url = $target_url;
            } elseif (!$first_image_url) {
                $fallback_thumb = social_get_og_image_cached($target_url);
                if ($fallback_thumb) $first_image_url = $fallback_thumb;
            }
        }

        // Preview card comes before image galleries/media
        $media_html = $card_html . $gallery_html;

        $like_count   = absint($post['likeCount'] ?? 0);
        $repost_count = absint($post['repostCount'] ?? 0);

        $body_html = social_clean_body_text($text, $has_card, $primary_card_url, $links_map);

        $item_data = [
            'network'        => 'bsky',
            'timestamp'      => $created_at,
            'text'           => $text,
            'body_html'      => $body_html,
            'author_name'    => $author_name,
            'author_handle'  => $author_handle,
            'author_avatar'  => $author_avatar,
            'media_html'     => $media_html,
            'thumb_image'    => $first_image_url,
            'is_repost'        => $is_repost,
            'repost_user'      => $handle,
            'post_uri'         => $post_uri,
            'reply_root_uri'   => $post['record']['reply']['root']['uri'] ?? $post_uri,
            'reply_parent_uri' => $post['record']['reply']['parent']['uri'] ?? '',
            'platform_links'   => [
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
            'urls'           => $extracted_urls
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
    $res = social_safe_remote_get_with_backoff($lookup_url, ['timeout' => 15], 2, 'masto_lookup_' . $instance . '_' . $username);
    if (is_wp_error($res)) return ['posts' => [], 'newest_timestamp' => $last_check];

    $account = json_decode(wp_remote_retrieve_body($res), true);
    $account_id = $account['id'] ?? null;
    if (!$account_id) return ['posts' => [], 'newest_timestamp' => $last_check];

    $exclude_param = $include_reposts ? 'false' : 'true';
    $statuses_url = "https://{$instance}/api/v1/accounts/{$account_id}/statuses?exclude_reblogs={$exclude_param}&limit=40";
    $cache_key = 'masto_feed_' . $instance . '_' . $account_id;
    $res = social_safe_remote_get_with_backoff($statuses_url, ['timeout' => 20], 2, $cache_key);
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
        $author_name = esc_html(!empty($post_data['account']['display_name']) ? $post_data['account']['display_name'] : $post_data['account']['username']);
        $post_url    = esc_url($post_data['url'] ?? "https://{$instance}/@{$username}/{$post_data['id']}");
        $first_image_url = null;
        $gallery_html = '';
        $card_html = '';

        $fav_count   = absint($post_data['favourites_count'] ?? $post_data['favorites_count'] ?? 0);
        $boost_count = absint($post_data['reblogs_count'] ?? 0);

        $extracted_urls = [];
        if (!empty($post_data['card']['url'])) $extracted_urls[] = $post_data['card']['url'];
        if (preg_match_all('/\b(?:https?:\/\/|www\.)[^\s<"\'\)]+/i', $clean_text, $u_m)) $extracted_urls = array_merge($extracted_urls, $u_m[0]);
        $extracted_urls = array_values(array_unique($extracted_urls));

        if (!empty($post_data['media_attachments']) && is_array($post_data['media_attachments'])) {
            $images = [];
            $videos = [];
            foreach ($post_data['media_attachments'] as $med) {
                if ($med['type'] === 'image') {
                    $images[] = $med;
                } elseif ($med['type'] === 'video' || $med['type'] === 'gifv') {
                    $videos[] = $med;
                }
            }
            if (!empty($images)) {
                $num_imgs = count($images);
                if ($num_imgs > 1) {
                    $cols = min($num_imgs, 4);
                    $gallery_html .= '<div class="social-embed-media" style="display:grid; grid-template-columns: repeat(' . $cols . ', 1fr); gap:8px; margin:10px 0;">';
                } else {
                    $gallery_html .= '<div class="social-embed-media" style="margin:10px 0;">';
                }
                foreach ($images as $med) {
                    $img_url = esc_url($med['preview_url'] ?? $med['url'] ?? '');
                    $alt_raw = trim($med['description'] ?? '');
                    $alt_txt = esc_attr($alt_raw ?: 'Mastodon image');
                    $alt_badge = '';
                    if ($alt_raw !== '' && strtolower($alt_raw) !== 'mastodon image') {
                        $alt_badge = '<span data-nosnippet class="social-alt-badge" onclick="event.preventDefault(); event.stopPropagation(); alert(this.getAttribute(\'title\'));" style="position:absolute; bottom:6px; left:6px; background:rgba(15,23,42,0.85); color:#ffffff; font-size:10px; font-weight:700; padding:2px 5px; border-radius:4px; letter-spacing:0.5px; backdrop-filter:blur(4px); box-shadow:0 1px 3px rgba(0,0,0,0.3); z-index:3; pointer-events:auto; cursor:help;" title="ALT: ' . $alt_txt . '">ALT</span>';
                    }
                    if ($img_url) {
                        if (!$first_image_url) $first_image_url = $img_url;
                        $title_attr = ($num_imgs > 1) ? 'View full gallery on Mastodon' : 'View image on Mastodon';
                        $gallery_html .= '<a href="' . esc_url($post_url) . '" target="_blank" rel="noopener" title="' . esc_attr($title_attr) . '" style="display:block; text-decoration:none;">';
                        if ($num_imgs > 1) {
                            $gallery_html .= '<figure style="margin:0; position:relative; overflow:hidden; border-radius:6px; max-height:130px;"><img src="' . $img_url . '" alt="' . $alt_txt . '" style="width:100%; height:130px; max-height:130px; object-fit:cover; border-radius:6px; display:block;" />' . $alt_badge . '</figure>';
                        } else {
                            $gallery_html .= '<figure style="margin:0; position:relative;"><img src="' . $img_url . '" alt="' . $alt_txt . '" style="max-width:100%; max-height:220px; width:auto; border-radius:8px; display:block;" />' . $alt_badge . '</figure>';
                        }
                        $gallery_html .= '</a>';
                    }
                }
                $gallery_html .= '</div>';
            }

            // Mastodon Video / Animated GIF (gifv) embeds
            if (!empty($videos)) {
                foreach ($videos as $vmed) {
                    $is_gifv = ($vmed['type'] === 'gifv');
                    $v_thumb = esc_url($vmed['preview_url'] ?? '');
                    $v_url   = esc_url($vmed['url'] ?? '');
                    $alt_raw = trim($vmed['description'] ?? '');
                    $alt_txt = esc_attr($alt_raw ?: ($is_gifv ? 'Mastodon animated GIF' : 'Mastodon video'));

                    if ($v_thumb && !$first_image_url) {
                        $first_image_url = $v_thumb;
                    }

                    if ($is_gifv && $v_url) {
                        // Native HTML5 autoplaying looping muted video for GIFV
                        $gallery_html .= '<div class="social-embed-gifv" style="margin:12px 0; position:relative; border-radius:8px; overflow:hidden; background:#0f172a;">';
                        $gallery_html .= '<video src="' . $v_url . '" poster="' . $v_thumb . '" autoplay loop muted playsinline style="width:100%; max-height:220px; object-fit:contain; display:block;"></video>';
                        $gallery_html .= '<span data-nosnippet style="position:absolute; bottom:8px; right:8px; background:rgba(0,0,0,0.75); color:#fff; font-size:10px; font-weight:700; padding:2px 6px; border-radius:4px; letter-spacing:0.5px;">GIF</span>';
                        $gallery_html .= '</div>';
                    } else {
                        // Video with preview poster & play overlay linking to Mastodon
                        $gallery_html .= '<div class="social-embed-video" style="margin:12px 0; position:relative; border-radius:8px; overflow:hidden; background:#000;">';
                        $gallery_html .= '<a href="' . esc_url($post_url) . '" target="_blank" rel="noopener" title="Watch video on Mastodon" style="display:block; position:relative; text-decoration:none;">';
                        if ($v_thumb) {
                            $gallery_html .= '<img src="' . $v_thumb . '" alt="' . $alt_txt . '" style="width:100%; max-height:220px; object-fit:contain; display:block; background:#0f172a;" />';
                        } else {
                            $gallery_html .= '<div style="height:180px; width:100%; background:#0f172a; display:flex; align-items:center; justify-content:center;"></div>';
                        }
                        $gallery_html .= '<div class="social-video-play-btn" style="position:absolute; top:50%; left:50%; transform:translate(-50%, -50%); width:56px; height:56px; border-radius:50%; background:rgba(15,23,42,0.85); display:flex; align-items:center; justify-content:center; box-shadow:0 4px 12px rgba(0,0,0,0.5); border:2px solid rgba(255,255,255,0.8);">';
                        $gallery_html .= '<span style="color:#ffffff; font-size:22px; margin-left:3px; line-height:1;">▶</span>';
                        $gallery_html .= '</div>';
                        $gallery_html .= '<span data-nosnippet style="position:absolute; bottom:8px; right:8px; background:rgba(0,0,0,0.75); color:#fff; font-size:11px; font-weight:700; padding:2px 6px; border-radius:4px; letter-spacing:0.5px;">VIDEO ↗</span>';
                        $gallery_html .= '</a>';
                        $gallery_html .= '</div>';
                    }
                }
            }
        }

        $has_card = false;
        $primary_card_url = '';

        if (!empty($post_data['card']) && is_array($post_data['card'])) {
            $card       = $post_data['card'];
            $card_url   = esc_url_raw($card['url'] ?? '');
            $card_title = $card['title'] ?? '';
            $card_desc  = $card['description'] ?? '';
            $card_thumb = esc_url_raw($card['image'] ?? '');
            $card_prov  = $card['provider_name'] ?? '';

            if (!$card_thumb && $card_url) {
                $card_thumb = social_get_og_image_cached($card_url);
            }

            if (!$first_image_url && $card_thumb) {
                $first_image_url = $card_thumb;
            }

            if ($card_url && ($card_title || $card_thumb)) {
                $domain = parse_url($card_url, PHP_URL_HOST);
                $card_html .= social_render_link_card_html($card_url, $card_title, $card_desc, $card_thumb, $card_prov ?: $domain);
                $has_card = true;
                $primary_card_url = $card_url;
            }
        }

        // Fallback: If Mastodon post has links but no card attached, fetch OG card
        if (!$has_card && !empty($extracted_urls[0])) {
            $target_url = $extracted_urls[0];
            $og_card = social_get_og_card_cached($target_url);
            if ($og_card && (!empty($og_card['title']) || !empty($og_card['image']))) {
                if (!$first_image_url && !empty($og_card['image'])) {
                    $first_image_url = $og_card['image'];
                }
                $card_html .= social_render_link_card_html(
                    $target_url,
                    $og_card['title'] ?? '',
                    $og_card['description'] ?? '',
                    $og_card['image'] ?? '',
                    $og_card['domain'] ?? parse_url($target_url, PHP_URL_HOST)
                );
                $has_card = true;
                $primary_card_url = $target_url;
            } elseif (!$first_image_url) {
                $fallback_thumb = social_get_og_image_cached($target_url);
                if ($fallback_thumb) $first_image_url = $fallback_thumb;
            }
        }

        // Preview card comes before image galleries/media
        $media_html = $card_html . $gallery_html;

        $body_html = social_clean_mastodon_html($body_content, $has_card, $primary_card_url);
        $clean_text = trim(preg_replace('/#[\p{L}\p{N}_]+/u', '', $clean_text));

        $item_data = [
            'network'        => 'mastodon',
            'timestamp'      => $created_at,
            'text'           => $clean_text,
            'body_html'      => $body_html,
            'author_name'    => $author_name,
            'author_handle'  => $full_acct,
            'author_avatar'  => $author_avatar,
            'media_html'     => $media_html,
            'thumb_image'    => $first_image_url,
            'is_repost'        => $is_reblog,
            'repost_user'      => $clean_handle,
            'post_uri'         => $post_data['id'] ?? '',
            'reply_root_uri'   => $post_data['in_reply_to_id'] ?? ($post_data['id'] ?? ''),
            'reply_parent_uri' => $post_data['in_reply_to_id'] ?? '',
            'platform_links'   => [
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
