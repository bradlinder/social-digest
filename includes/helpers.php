<?php
namespace SocialDigest;

if (!defined('ABSPATH')) exit;

// ==========================================
// 7. STRING, IMAGE & HASHTAG HELPERS
// ==========================================

function social_get_og_image_cached($url) {
    if (empty($url) || !filter_var($url, FILTER_VALIDATE_URL)) return '';
    $transient_key = 'sd_og_img_' . md5($url);
    $cached = get_transient($transient_key);
    if ($cached !== false) return (string)$cached;

    $res = wp_safe_remote_get($url, [
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

function social_get_og_card_cached($url) {
    if (empty($url) || !filter_var($url, FILTER_VALIDATE_URL)) return null;
    $transient_key = 'sd_og_card_' . md5($url);
    $cached = get_transient($transient_key);
    if ($cached !== false && is_array($cached)) {
        return !empty($cached['error']) ? null : $cached;
    }

    $res = wp_safe_remote_get($url, [
        'timeout'     => 4,
        'user-agent'  => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) WordPress-SocialDigest/1.0',
        'redirection' => 3,
    ]);

    if (is_wp_error($res)) {
        set_transient($transient_key, ['error' => true], HOUR_IN_SECONDS * 6);
        return null;
    }

    $body = wp_remote_retrieve_body($res);
    if (empty($body)) {
        set_transient($transient_key, ['error' => true], HOUR_IN_SECONDS * 6);
        return null;
    }

    if (strlen($body) > 204800) {
        $body = substr($body, 0, 204800);
    }

    $title = '';
    if (preg_match('/<meta[^>]+property=["\']og:title["\'][^>]+content=["\']([^"\']+)["\']/i', $body, $m)) {
        $title = $m[1];
    } elseif (preg_match('/<meta[^>]+content=["\']([^"\']+)["\'][^>]+property=["\']og:title["\']/i', $body, $m)) {
        $title = $m[1];
    } elseif (preg_match('/<meta[^>]+name=["\']twitter:title["\'][^>]+content=["\']([^"\']+)["\']/i', $body, $m)) {
        $title = $m[1];
    } elseif (preg_match('/<title[^>]*>(.*?)<\/title>/is', $body, $m)) {
        $title = $m[1];
    }
    $title = trim(html_entity_decode(wp_strip_all_tags($title), ENT_QUOTES | ENT_HTML5, 'UTF-8'));

    $desc = '';
    if (preg_match('/<meta[^>]+property=["\']og:description["\'][^>]+content=["\']([^"\']+)["\']/i', $body, $m)) {
        $desc = $m[1];
    } elseif (preg_match('/<meta[^>]+content=["\']([^"\']+)["\'][^>]+property=["\']og:description["\']/i', $body, $m)) {
        $desc = $m[1];
    } elseif (preg_match('/<meta[^>]+name=["\']description["\'][^>]+content=["\']([^"\']+)["\']/i', $body, $m)) {
        $desc = $m[1];
    } elseif (preg_match('/<meta[^>]+name=["\']twitter:description["\'][^>]+content=["\']([^"\']+)["\']/i', $body, $m)) {
        $desc = $m[1];
    }
    $desc = trim(html_entity_decode(wp_strip_all_tags($desc), ENT_QUOTES | ENT_HTML5, 'UTF-8'));

    $img_url = '';
    if (preg_match('/<meta[^>]+property=["\']og:image["\'][^>]+content=["\']([^"\']+)["\']/i', $body, $m)) {
        $img_url = $m[1];
    } elseif (preg_match('/<meta[^>]+content=["\']([^"\']+)["\'][^>]+property=["\']og:image["\']/i', $body, $m)) {
        $img_url = $m[1];
    } elseif (preg_match('/<meta[^>]+name=["\']twitter:image["\'][^>]+content=["\']([^"\']+)["\']/i', $body, $m)) {
        $img_url = $m[1];
    }

    if ($img_url) {
        $img_url = trim(html_entity_decode($img_url, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        if (strpos($img_url, '//') === 0) {
            $scheme = parse_url($url, PHP_URL_SCHEME) ?: 'https';
            $img_url = $scheme . ':' . $img_url;
        } elseif (strpos($img_url, '/') === 0) {
            $host = parse_url($url, PHP_URL_HOST);
            $scheme = parse_url($url, PHP_URL_SCHEME) ?: 'https';
            $img_url = $scheme . '://' . $host . $img_url;
        }
        $img_url = esc_url_raw($img_url);
    }

    $site_name = '';
    if (preg_match('/<meta[^>]+property=["\']og:site_name["\'][^>]+content=["\']([^"\']+)["\']/i', $body, $m)) {
        $site_name = trim(html_entity_decode($m[1], ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    }

    $domain = parse_url($url, PHP_URL_HOST);
    if ($domain) {
        $domain = preg_replace('/^www\./i', '', $domain);
    }

    if (!$title && !$img_url && !$desc) {
        set_transient($transient_key, ['error' => true], HOUR_IN_SECONDS * 6);
        return null;
    }

    $card = [
        'url'         => $url,
        'title'       => $title,
        'description' => $desc,
        'image'       => $img_url,
        'provider'    => $site_name ?: $domain,
        'domain'      => $domain,
    ];

    set_transient($transient_key, $card, DAY_IN_SECONDS);
    return $card;
}

function social_render_link_card_html($card_url, $card_title, $card_desc, $card_thumb, $card_domain) {
    $card_url = esc_url($card_url);
    $card_thumb = esc_url($card_thumb);
    $display_title = esc_html($card_title ?: ($card_domain ?: $card_url));
    $display_desc  = esc_html($card_desc ? wp_trim_words($card_desc, 25) : '');
    $display_domain = esc_html(strtoupper(preg_replace('/^www\./i', '', $card_domain ?: parse_url($card_url, PHP_URL_HOST))));

    $html = '<div class="social-link-card" style="border:1px solid #e1e8ed; border-radius:8px; overflow:hidden; margin:12px 0; max-width:500px; background:#f8fafc; box-shadow:0 1px 3px rgba(0,0,0,0.05);">';
    $html .= '<a href="' . $card_url . '" target="_blank" rel="noopener" style="text-decoration:none; color:inherit; display:block;">';
    if ($card_thumb) {
        $html .= '<img src="' . $card_thumb . '" alt="' . esc_attr($card_title) . '" style="width:100%; max-height:220px; object-fit:cover; display:block;" />';
    }
    $html .= '<div style="padding:10px 14px;">';
    if ($display_domain) {
        $html .= '<div class="social-link-card-domain" data-nosnippet style="font-size:0.75em; text-transform:uppercase; color:#657786; margin-bottom:3px; font-weight:600; letter-spacing:0.5px;">' . $display_domain . '</div>';
    }
    $html .= '<div class="social-link-card-title" style="font-weight:700; font-size:1em; margin-bottom:4px; color:#0284c7; line-height:1.35;">' . $display_title . '</div>';
    if ($display_desc) {
        $html .= '<div class="social-link-card-desc" style="font-size:0.85em; color:#475569; line-height:1.4;">' . $display_desc . '</div>';
    }
    $html .= '</div>';
    $html .= '</a>';
    $html .= '</div>';
    return $html;
}

function social_clean_body_text($raw_text, $has_card = false, $card_url = '', $links_map = []) {
    if (empty($raw_text)) return '';

    // Strip hashtags
    $text = preg_replace('/#[\p{L}\p{N}_]+/u', '', $raw_text);

    // If a preview card is present, strip trailing redundant URL that leads to the card
    if ($has_card) {
        // Strip trailing URL (including truncated URLs with ellipsis like store.minisforum.com/products/min...)
        $text = preg_replace('/(?:\s+|[\r\n]+|\s*[:\-–]\s*)(?:https?:\/\/|www\.)[^\s<"\'\)]+(?:\.\.\.)?\s*$/iu', '', $text);
        $text = preg_replace('/\s*[:\-–]\s*$/u', '.', $text);
    }

    // Now escape the text before inserting clickable HTML links
    $escaped = esc_html(trim($text));

    // Convert mapped URLs (from Bluesky facets or parsed URLs) to clickable links
    if (!empty($links_map) && is_array($links_map)) {
        foreach ($links_map as $disp => $full_url) {
            $disp_esc = esc_html($disp);
            if ($disp_esc !== '' && stripos($escaped, $disp_esc) !== false) {
                // If it wasn't stripped already
                $link_html = '<a href="' . esc_url($full_url) . '" target="_blank" rel="noopener" style="color:#0284c7; text-decoration:underline;">' . $disp_esc . '</a>';
                $escaped = str_replace($disp_esc, $link_html, $escaped);
            }
        }
    }

    // Link any remaining bare http(s):// or www. URLs that weren't converted yet
    $escaped = preg_replace_callback('/\b(https?:\/\/[^\s<"\'\)]+)/i', function($m) {
        $u = $m[1];
        return '<a href="' . esc_url($u) . '" target="_blank" rel="noopener" style="color:#0284c7; text-decoration:underline;">' . esc_html($u) . '</a>';
    }, $escaped);

    return nl2br(trim($escaped));
}

function social_clean_mastodon_html($html, $has_card = false, $card_url = '') {
    if (empty($html)) return '';

    // Strip hashtag anchor elements
    $html = preg_replace('/<a[^>]*class=["\'][^"\']*hashtag[^"\']*["\'][^>]*>.*?<\/a>/isu', '', $html);
    // Strip plain-text hashtags
    $html = preg_replace('/#[\p{L}\p{N}_]+/u', '', $html);

    if ($has_card) {
        // Strip trailing anchor link if it's the last element before closing </p>
        $html = preg_replace('/(?:\s*[:\-–]\s*|\s*)<a[^>]+href=["\'][^"\']*["\'][^>]*>.*?<\/a>\s*(<\/p>\s*)$/isu', '$1', $html);
    }

    // Add target="_blank" and styling to remaining links
    $html = preg_replace('/<a\s+(?![^>]*\btarget=)([^>]+)>/i', '<a target="_blank" rel="noopener" style="color:#0284c7; text-decoration:underline;" $1>', $html);

    // Clean up empty paragraphs or dangling breaks
    $html = preg_replace('/<p>\s*(?:<br\s*\/?>|&nbsp;|\s)*<\/p>/i', '', $html);

    return wp_kses_post(trim($html));
}

function social_extract_first_line_or_sentence($text) {
    if (empty($text)) return '';
    $t = wp_strip_all_tags($text);
    $t = preg_replace('/#[\p{L}\p{N}_]+/u', '', $t);
    $t = preg_replace('/\b(?:https?:\/\/|www\.)[^\s<"\'\)]+/i', '', $t);
    $t = html_entity_decode($t, ENT_QUOTES | ENT_HTML5, 'UTF-8');

    $lines = preg_split('/\r\n|\r|\n/', $t);
    $first_line = '';
    foreach ($lines as $line) {
        $l = trim(preg_replace('/\s+/', ' ', $line));
        if ($l !== '') {
            $first_line = $l;
            break;
        }
    }
    if ($first_line === '') {
        $first_line = trim(preg_replace('/\s+/', ' ', $t));
    }
    if ($first_line === '') return '';

    // Match first sentence ending with . ! ? or … followed by space and capital/digit/quote or end of line
    if (preg_match('/^(.+?[.!?…])(?:\s+[A-Z0-9"“‘]|\s*$)/u', $first_line, $m)) {
        return trim($m[1]);
    }

    return trim($first_line);
}

function social_build_first_lines_excerpt($candidates, $delimiter = ' // ', $max_items = 0) {
    $lines = [];
    $count = 0;
    foreach ((array)$candidates as $c) {
        if (!empty($c['excluded'])) continue;
        $raw = $c['full_text'] ?? $c['text'] ?? '';
        $first = social_extract_first_line_or_sentence($raw);
        if ($first !== '') {
            $lines[] = $first;
            $count++;
            if ($max_items > 0 && $count >= $max_items) break;
        }
    }
    if (empty($lines)) return '';

    $delim_clean = trim($delimiter);
    if ($delim_clean === '.' || $delim_clean === '. ' || $delimiter === '.') {
        $formatted = array_map(function($line) {
            return rtrim($line, " \t\n\r\0\x0B.") . '.';
        }, $lines);
        return implode(' ', $formatted);
    } else {
        $glue = ' ' . $delim_clean . ' ';
        $formatted = array_map(function($line) use ($delim_clean) {
            if ($delim_clean === '//' || $delim_clean === '...' || $delim_clean === '—' || $delim_clean === '•') {
                return rtrim($line, " \t\n\r\0\x0B.");
            }
            return $line;
        }, $lines);
        return implode($glue, $formatted);
    }
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
            if ($u_host === $site_host || (strlen($u_host) > strlen($site_host) && substr($u_host, -strlen('.' . $site_host)) === '.' . $site_host)) {
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
                if ($u_host === $site_host || (strlen($u_host) > strlen($site_host) && substr($u_host, -strlen('.' . $site_host)) === '.' . $site_host)) {
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
        if (!function_exists('wp_generate_attachment_metadata')) {
            require_once(ABSPATH . 'wp-admin/includes/image.php');
        }
        if (!function_exists('wp_handle_sideload')) {
            require_once(ABSPATH . 'wp-admin/includes/file.php');
        }
        if (!function_exists('media_sideload_image')) {
            require_once(ABSPATH . 'wp-admin/includes/media.php');
        }

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

/**
 * Sideloads all external images referenced in post HTML content directly into the WordPress Media Library.
 * Replaces remote image src URLs with local attachment URLs to protect against link rot and server outages.
 *
 * @param string $content HTML content containing potential remote image references.
 * @param int $post_id WordPress post ID to attach the media items to.
 * @return string Content with remote image URLs replaced with local attachment URLs.
 */
function social_sideload_content_media($content, $post_id) {
    if (empty($content) || empty($post_id)) {
        return $content;
    }

    $site_url = home_url();
    $site_host = wp_parse_url($site_url, PHP_URL_HOST);

    // Static cache for URLs already processed in this request to prevent duplicate downloads
    static $processed_media_urls = [];

    // Find all img tags with src
    if (preg_match_all('/<img[^>]+src=["\']([^"\']+)["\']/i', $content, $matches)) {
        $urls = array_unique($matches[1]);
        foreach ($urls as $img_url) {
            $img_url_clean = trim($img_url);
            $parsed_host = wp_parse_url($img_url_clean, PHP_URL_HOST);

            // Skip relative URLs or URLs already hosted on this site
            if (empty($parsed_host) || ($site_host && strcasecmp($parsed_host, $site_host) === 0)) {
                continue;
            }

            // Check if already sideloaded in this request
            if (isset($processed_media_urls[$img_url_clean])) {
                $local_url = $processed_media_urls[$img_url_clean];
                if ($local_url) {
                    $content = str_replace($img_url, $local_url, $content);
                }
                continue;
            }

            // Sideload the image
            $attachment_id = social_sideload_image_by_mime($img_url_clean, $post_id, 'Digest media asset');
            if ($attachment_id && !is_wp_error($attachment_id)) {
                $local_url = wp_get_attachment_url($attachment_id);
                if ($local_url) {
                    $processed_media_urls[$img_url_clean] = $local_url;
                    $content = str_replace($img_url, $local_url, $content);
                }
            } else {
                $processed_media_urls[$img_url_clean] = false;
            }
        }
    }

    return $content;
}

/**
 * Retrieves cached post tag usage counts using a 5-minute transient.
 * Reduces SQL taxonomy lookup overhead during batch candidate processing and manual workbench tests.
 *
 * @return array Associative array of lowercase tag name => post count.
 */
function social_get_cached_tag_weights() {
    $cache_key = 'social_digest_tag_weights';
    $cached = get_transient($cache_key);
    if ($cached !== false && is_array($cached)) {
        return $cached;
    }

    $terms = get_terms([
        'taxonomy'   => 'post_tag',
        'hide_empty' => false,
        'number'     => 500,
        'orderby'    => 'count',
        'order'      => 'DESC',
    ]);

    $weights = [];
    if (!is_wp_error($terms) && is_array($terms)) {
        foreach ($terms as $term) {
            $weights[mb_strtolower($term->name)] = (int)$term->count;
        }
    }

    set_transient($cache_key, $weights, 300); // 5-minute transient
    return $weights;
}

function social_split_camelcase_tag($tag) {
    $t = ltrim(trim($tag), '#');
    $t = preg_replace('/([a-z]{2,})([A-Z0-9])/u', '$1 $2', $t);
    return trim(preg_replace('/([A-Z]+)([A-Z][a-z])/u', '$1 $2', $t));
}

function social_rank_and_format_title_tags($candidate_tags, $default_tags_str, $enclosure = 'parentheses', $delimiter = 'oxford') {
    $selected_tags = [];
    $seen_normalized = [];
    $tag_weights = social_get_cached_tag_weights();

    if (!empty($candidate_tags)) {
        $is_grouped = false;
        foreach ($candidate_tags as $elem) {
            if (is_array($elem)) {
                $is_grouped = true;
                break;
            }
        }

        if ($is_grouped) {
            // Select at most 1 distinct tag per post, prioritizing higher taxonomy frequency
            foreach ($candidate_tags as $post_tags) {
                if (!is_array($post_tags)) {
                    $post_tags = [$post_tags];
                }

                // Sort tags within the post by cached tag popularity if available
                if (count($post_tags) > 1 && !empty($tag_weights)) {
                    usort($post_tags, function($a, $b) use ($tag_weights) {
                        $w_a = $tag_weights[mb_strtolower(trim(ltrim($a, '#')))] ?? 0;
                        $w_b = $tag_weights[mb_strtolower(trim(ltrim($b, '#')))] ?? 0;
                        return $w_b <=> $w_a;
                    });
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
            // Flat array fallback - sort by popularity if available
            $flat_tags = $candidate_tags;
            if (count($flat_tags) > 1 && !empty($tag_weights)) {
                usort($flat_tags, function($a, $b) use ($tag_weights) {
                    $w_a = $tag_weights[mb_strtolower(trim(ltrim($a, '#')))] ?? 0;
                    $w_b = $tag_weights[mb_strtolower(trim(ltrim($b, '#')))] ?? 0;
                    return $w_b <=> $w_a;
                });
            }

            foreach ($flat_tags as $tag) {
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

/**
 * Evaluates whether a post text matches any exclusion filter patterns.
 * Supports:
 * - Plain keywords / phrases (case-insensitive substring match)
 * - Hashtags (#tag, case-insensitive whole-hashtag or substring match)
 * - Regular expressions (/pattern/i or #pattern#i) with error safety
 *
 * @param string $text Post text content to check.
 * @param array|string $filter_rules Array of rules or raw comma/newline delimited string.
 * @return bool True if excluded/filtered, false otherwise.
 */
function social_post_matches_filter($text, $filter_rules) {
    if (empty($text) || empty($filter_rules)) return false;

    if (!is_array($filter_rules)) {
        // Split by newlines or commas
        $filter_rules = preg_split('/[\r\n,]+/', (string)$filter_rules);
    }

    $clean_text = html_entity_decode((string)$text, ENT_QUOTES | ENT_HTML5, 'UTF-8');

    foreach ($filter_rules as $rule) {
        $rule = trim((string)$rule);
        if ($rule === '') continue;

        // 1. Regular expression check (e.g. /pattern/i or #pattern#i)
        $first_char = substr($rule, 0, 1);
        $last_slash = strrpos($rule, $first_char);
        if (
            ($first_char === '/' || $first_char === '~' || $first_char === '@') &&
            $last_slash > 0 &&
            strlen($rule) >= 3
        ) {
            // Test if it's a valid regex
            $matched = @preg_match($rule, $clean_text);
            if ($matched === 1) {
                return true;
            }
            if ($matched === 0) {
                continue;
            }
            // If regex syntax was malformed, fall through to literal check
        }

        // 2. Hashtag rule check (e.g. #ad, #sponsored, #giveaway)
        if ($first_char === '#') {
            $tag_term = ltrim($rule, '#');
            if ($tag_term !== '') {
                // Check exact hashtag boundary
                $pattern = '/(?:^|\s|[^\p{L}\p{N}_])#' . preg_quote($tag_term, '/') . '(?=$|\s|[^\p{L}\p{N}_])/iu';
                if (@preg_match($pattern, $clean_text)) {
                    return true;
                }
            }
            // Also fall through to case-insensitive substring
            if (stripos($clean_text, $rule) !== false) {
                return true;
            }
            continue;
        }

        // 3. Plain keyword / phrase match (case-insensitive)
        if (stripos($clean_text, $rule) !== false) {
            return true;
        }
    }

    return false;
}

/**
 * Performs a network GET request with exponential backoff and stale-cache fallback.
 * Automatically retries transient network errors / HTTP 429/503 responses.
 *
 * @param string $url Target endpoint URL.
 * @param array  $args wp_remote_get options.
 * @param int    $max_retries Number of attempts (default 2 retries, total 3 attempts).
 * @param string $cache_key Optional transient cache key to store/retrieve stale cache on failure.
 * @return array|WP_Error Response array or WP_Error.
 */
function social_safe_remote_get_with_backoff($url, $args = [], $max_retries = 2, $cache_key = '') {
    $default_args = [
        'timeout'    => 15,
        'user-agent' => 'SocialDigestWordPress/5.7; +' . home_url(),
    ];
    $parsed_args = wp_parse_args($args, $default_args);

    $attempt = 0;
    $response = null;

    while ($attempt <= $max_retries) {
        $attempt++;
        $response = wp_safe_remote_get($url, $parsed_args);

        // Success if not WP_Error and HTTP 200-299
        if (!is_wp_error($response)) {
            $code = (int)wp_remote_retrieve_response_code($response);
            if ($code >= 200 && $code < 300) {
                // If cache key is supplied, refresh 24-hour stale cache snapshot
                if ($cache_key) {
                    set_transient('sd_stale_' . md5($cache_key), wp_remote_retrieve_body($response), DAY_IN_SECONDS);
                }
                return $response;
            }

            // If client error (400, 401, 403, 404) that is NOT rate limit (429), don't retry
            if ($code >= 400 && $code < 500 && $code !== 429) {
                break;
            }
        }

        // Delay with backoff before next attempt: 1s, 2s...
        if ($attempt <= $max_retries) {
            usleep($attempt * 1000000); // 1.0s, 2.0s
        }
    }

    // If all retries failed and stale cache key exists, try serving stale cache
    if ($cache_key) {
        $stale_body = get_transient('sd_stale_' . md5($cache_key));
        if ($stale_body !== false && $stale_body !== '') {
            return [
                'response' => ['code' => 200, 'message' => 'OK (Stale Cache Fallback)'],
                'body'     => $stale_body,
                'headers'  => ['x-social-digest-fallback' => 'stale-cache']
            ];
        }
    }

    return $response;
}

/**
 * Wraps content elements into native Gutenberg HTML blocks (wp:html, wp:paragraph, wp:heading).
 *
 * @param string $html_chunk Raw HTML string to wrap as a block.
 * @param string $block_name Gutenberg block name, defaults to 'core/html'.
 * @return string Gutenberg formatted block comment markup.
 */
function social_wrap_as_gutenberg_block($html_chunk, $block_name = 'core/html') {
    $trimmed = trim((string)$html_chunk);
    if ($trimmed === '') return '';
    return "<!-- wp:{$block_name} -->\n" . $trimmed . "\n<!-- /wp:{$block_name} -->";
}
