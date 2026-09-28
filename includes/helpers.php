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
    $display_domain = esc_html(strtolower(preg_replace('/^www\./i', '', $card_domain ?: parse_url($card_url, PHP_URL_HOST))));

    $html = '<a href="' . $card_url . '" target="_blank" rel="noopener" class="social-link-card" style="display:block; text-decoration:none; color:inherit; border:1px solid #e2e8f0; border-radius:8px; overflow:hidden; margin:12px 0; max-width:500px; background:#f8fafc; box-shadow:0 1px 3px rgba(0,0,0,0.04); cursor:pointer;">';
    if ($card_thumb) {
        $html .= '<img class="social-card-thumb" src="' . $card_thumb . '" alt="' . esc_attr($card_title) . '" style="width:100%; max-height:220px; object-fit:cover; display:block; border:none;" />';
    }
    $html .= '<span class="social-link-card-body" style="display:block; padding:10px 14px;">';
    $html .= '<span class="social-link-card-title" style="display:block; font-weight:700; font-size:14px; margin-bottom:4px; color:#0f172a; line-height:1.35;">' . $display_title . '</span>';
    if ($display_desc) {
        $html .= '<span class="social-link-card-desc" style="display:block; font-weight:400; font-size:12.5px; color:#475569; line-height:1.45; margin-bottom:6px;">' . $display_desc . '</span>';
    }
    if ($display_domain) {
        $html .= '<span class="social-link-card-domain" data-nosnippet style="display:flex; align-items:center; gap:5px; font-size:12px; color:#64748b; font-weight:400; margin-top:4px;"><span style="font-size:11px; opacity:0.8;">🔗</span> ' . $display_domain . '</span>';
    }
    $html .= '</span>';
    $html .= '</a>';
    return $html;
}

function social_clean_body_text($raw_text, $has_card = false, $card_url = '', $links_map = []) {
    if (empty($raw_text) || !is_scalar($raw_text)) return '';
    $raw_text = html_entity_decode((string)$raw_text, ENT_QUOTES | ENT_HTML5, 'UTF-8');

    // Strip hashtags (protecting numeric entities like &#39;)
    $text = preg_replace('/(?<![&\w])#(?!\d+;)(?:[\p{L}\p{N}_]*[\p{L}_][\p{L}\p{N}_\-]*)/u', '', $raw_text);
    $text = preg_replace('/(\w)&;(\w)/', "$1'$2", $text);

    // If a preview card is present, strip trailing redundant URL (scheme or domain/path) that leads to the card
    if ($has_card) {
        $text = preg_replace('/(?:\s+|[\r\n]+|\s*[:\-–]\s*)(?:https?:\/\/|www\.|[a-zA-Z0-9\-]+\.[a-zA-Z]{2,}\/)[^\s<"\'\)]*(?:\.\.\.)?\s*$/iu', '', $text);
        $text = preg_replace('/\s*[:\-–]\s*$/u', '.', $text);
    }

    // Now escape the text before inserting clickable HTML links
    $escaped = esc_html(trim($text));

    // Convert mapped URLs (from Bluesky facets or parsed URLs) to placeholders first
    $placeholders = [];
    if (!empty($links_map) && is_array($links_map)) {
        $idx = 0;
        foreach ($links_map as $disp => $full_url) {
            if (empty($disp) || empty($full_url)) continue;
            $disp_esc = esc_html($disp);
            if (stripos($escaped, $disp_esc) !== false) {
                $ph = "___SD_LINK_PH_{$idx}___";
                $placeholders[$ph] = '<a href="' . esc_url($full_url) . '" target="_blank" rel="noopener">' . $disp_esc . '</a>';
                $escaped = str_replace($disp_esc, $ph, $escaped);
                $idx++;
            }
        }
    }

    // Link any remaining bare http(s):// or www. URLs that weren't converted yet
    $escaped = preg_replace_callback('/\b(https?:\/\/[^\s<"\'\)]+|www\.[^\s<"\'\)]+)/i', function($m) {
        $u = $m[1];
        $href = (strpos($u, 'www.') === 0) ? 'https://' . $u : $u;
        return '<a href="' . esc_url($href) . '" target="_blank" rel="noopener">' . $u . '</a>';
    }, $escaped);

    // Restore placeholders
    if (!empty($placeholders)) {
        $escaped = str_replace(array_keys($placeholders), array_values($placeholders), $escaped);
    }

    return nl2br(trim($escaped));
}

function social_clean_mastodon_html($html, $has_card = false, $card_url = '') {
    if (empty($html) || !is_scalar($html)) return '';
    $html = (string)$html;

    // Strip hashtag anchor elements
    $html = preg_replace('/<a[^>]*class=["\'][^"\']*hashtag[^"\']*["\'][^>]*>.*?<\/a>/isu', '', $html);
    // Strip plain-text hashtags (strictly requiring negative lookbehind for & and requiring non-digit characters to protect &#39; and &#8217;)
    $html = preg_replace('/(?<![&\w])#(?!\d+;)(?:[\p{L}\p{N}_]*[\p{L}_][\p{L}\p{N}_\-]*)/u', '', $html);

    // Repair any damaged entities (e.g. they&;re -> they're)
    $html = preg_replace('/(\w)&;(\w)/', "$1'$2", $html);

    if ($has_card) {
        // Strip trailing anchor link if it's the last element before closing </p>
        $html = preg_replace('/(?:\s*[:\-–]\s*|\s*)<a[^>]+href=["\'][^"\']*["\'][^>]*>.*?<\/a>\s*(<\/p>\s*)$/isu', '$1', $html);
    }

    // Add target="_blank" and rel="noopener" to remaining links cleanly
    $html = preg_replace('/<a\s+(?![^>]*\btarget=)([^>]+)>/i', '<a target="_blank" rel="noopener" $1>', $html);

    // Clean up empty paragraphs or dangling breaks
    $html = preg_replace('/<p>\s*(?:<br\s*\/?>|&nbsp;|\s)*<\/p>/i', '', $html);

    return wp_kses_post(trim($html));
}

function social_extract_first_line_or_sentence($text) {
    if (empty($text) || !is_scalar($text)) return '';
    $text = (string)$text;
    $t = wp_strip_all_tags($text);
    $t = html_entity_decode($t, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $t = preg_replace('/(?<![&\w])#(?!\d+;)(?:[\p{L}\p{N}_]*[\p{L}_][\p{L}\p{N}_\-]*)/u', '', $t);
    $t = preg_replace('/(\w)&;(\w)/', "$1'$2", $t);
    $t = preg_replace('/\b(?:https?:\/\/|www\.)[^\s<"\'\)]+/i', '', $t);

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

function social_build_first_lines_excerpt($candidates, $delimiter = ' // ', $max_items = 0, $append_ellipsis = false) {
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

    $delim_clean = trim(strip_tags($delimiter));
    $has_html = (strpos($delimiter, '<') !== false);

    $joined = '';
    if ($delim_clean === '.' || $delim_clean === '. ' || $delimiter === '.') {
        $formatted = array_map(function($line) {
            return rtrim($line, " \t\n\r\0\x0B.") . '.';
        }, $lines);
        $joined = implode(' ', $formatted);
    } else {
        $glue = ' ' . $delimiter . ' ';
        $formatted = array_map(function($line) {
            return rtrim($line, " \t\n\r\0\x0B.");
        }, $lines);
        $joined = implode($glue, $formatted);
    }

    if ($append_ellipsis) {
        $joined_trimmed = trim($joined);
        if ($joined_trimmed !== '' && !preg_match('/(?:\.\.\.|…)\s*$/u', $joined_trimmed)) {
            $joined = rtrim($joined_trimmed, '.') . '...';
        }
    }

    return $joined;
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
    $t = html_entity_decode($t, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $t = preg_replace('/(?<![&\w])#(?!\d+;)(?:[\p{L}\p{N}_]*[\p{L}_][\p{L}\p{N}_\-]*)/u', '', $t);
    $t = preg_replace('/(\w)&;(\w)/', "$1'$2", $t);
    return mb_strlen(trim(preg_replace('/\s+/', ' ', $t)), 'UTF-8');
}

function social_sideload_image_by_mime($url, $post_id, $desc = '', $slug = '') {
    if (empty($url)) return false;
    $url = html_entity_decode(trim((string)$url), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    if (!filter_var($url, FILTER_VALIDATE_URL)) return false;

    // Temporary memory elevation for GD/Imagick thumbnail processing
    if (function_exists('wp_raise_memory_limit')) {
        wp_raise_memory_limit('image');
    }

    if (!function_exists('wp_generate_attachment_metadata')) {
        require_once(ABSPATH . 'wp-admin/includes/image.php');
    }
    if (!function_exists('wp_handle_sideload')) {
        require_once(ABSPATH . 'wp-admin/includes/file.php');
    }
    if (!function_exists('media_sideload_image')) {
        require_once(ABSPATH . 'wp-admin/includes/media.php');
    }

    $get_args = [
        'timeout'     => 25,
        'redirection' => 5,
        'user-agent'  => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36 WordPress-SocialDigest/5.7',
        'headers'     => [
            'Accept' => 'image/avif,image/webp,image/apng,image/svg+xml,image/*,*/*;q=0.8',
        ],
    ];

    // Guard: Prevent downloading massive non-thumbnail images (cap at 12MB)
    $response = wp_safe_remote_get($url, $get_args);
    if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
        $response = wp_remote_get($url, $get_args);
    }
    if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) return false;
    
    $headers = wp_remote_retrieve_headers($response);
    $content_length = (int)($headers['content-length'] ?? 0);
    if ($content_length > 12 * 1024 * 1024) {
        return false; // Skip excessive remote assets
    }

    $image_data = wp_remote_retrieve_body($response);
    if (empty($image_data) || strlen($image_data) > 12 * 1024 * 1024) return false;

    $content_type = strtolower((string)($headers['content-type'] ?? ''));
    $ext = '.jpg';
    $mime_type = 'image/jpeg';
    if (strpos($content_type, 'png') !== false) {
        $ext = '.png';
        $mime_type = 'image/png';
    } elseif (strpos($content_type, 'webp') !== false) {
        $ext = '.webp';
        $mime_type = 'image/webp';
    } elseif (strpos($content_type, 'gif') !== false) {
        $ext = '.gif';
        $mime_type = 'image/gif';
    } elseif (strpos($content_type, 'avif') !== false) {
        $ext = '.avif';
        $mime_type = 'image/avif';
    }

    // Determine clean, descriptive base file name slug
    $base_slug = '';
    if (!empty($slug)) {
        $base_slug = sanitize_title_with_dashes($slug);
    }
    if (empty($base_slug) && !empty($desc)) {
        $clean_desc_check = strtolower(trim((string)$desc));
        if (!in_array($clean_desc_check, ['digest media asset', 'social digest avatar', 'mastodon image', 'bluesky image'], true)) {
            $base_slug = sanitize_title_with_dashes($desc);
        }
    }

    if (!empty($base_slug)) {
        $base_slug = trim(substr($base_slug, 0, 50), '-');
        $filename  = $base_slug . $ext;
        if (function_exists('wp_upload_dir')) {
            $upload_dir  = wp_upload_dir();
            $target_file = ($upload_dir['path'] ?? '') . '/' . $filename;
            if (file_exists($target_file) && function_exists('wp_unique_filename')) {
                $filename = wp_unique_filename($upload_dir['path'], $filename);
            }
        }
    } else {
        $filename = 'digest-thumb-' . $post_id . '-' . wp_generate_password(6, false) . $ext;
    }

    $upload = wp_upload_bits($filename, null, $image_data);
    if (!empty($upload['error'])) return false;

    // Use human-readable title for attachment post_title in WordPress Media Library
    $clean_title = !empty($desc) ? wp_strip_all_tags($desc) : sanitize_file_name(basename($upload['file']));

    // Avatars are shared site-wide brand assets: set post_parent to 0 (Unattached)
    // so they are permanently protected from post trashing, deletions, and draft restrictions.
    $attach_parent = (strpos($desc, 'Avatar') !== false || strpos($slug, 'avatar-') === 0 || empty($post_id)) ? 0 : $post_id;

    $attach_id = wp_insert_attachment([
        'post_mime_type' => $mime_type,
        'post_title'     => $clean_title,
        'post_status'    => 'inherit'
    ], $upload['file'], $attach_parent);

    if ($attach_id && !is_wp_error($attach_id) && !empty($desc)) {
        update_post_meta($attach_id, '_wp_attachment_image_alt', $clean_title);
    }

    if ($attach_id && !is_wp_error($attach_id)) {
        try {
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
        } catch (\Throwable $e) {
            // If GD or intermediate thumbnail generation encounters an error, retain stock attachment
        }
        return $attach_id;
    }
    return false;
}

/**
 * Extracts contextual image descriptors (human-readable title and filename slug)
 * from digest post markup based on post hashtags, author names, or preview cards.
 *
 * @param string $content Digest HTML content containing post cards.
 * @return array Map of image URLs to array ['desc' => string, 'slug' => string]
 */
function social_extract_media_descriptors_from_content($content) {
    if (empty($content) || !is_string($content)) {
        return [];
    }

    $descriptors = [];
    $custom_overrides = '';
    $opts = get_option('social_digest_options', []);
    if (!empty($opts['tag_custom_overrides'])) {
        $custom_overrides = (string)$opts['tag_custom_overrides'];
    }

    // Split content by social-post card boundaries
    $cards = preg_split('/(?=<div[^>]*class=["\'][^"\']*\bsocial-post\b)/i', $content);

    foreach ($cards as $card) {
        if (strpos($card, 'social-post') === false) {
            continue;
        }

        // 1. Author Name and Handle
        $author_name = '';
        $author_handle = '';
        if (preg_match('/class=["\'][^"\']*social-author-name[^"\']*["\'][^>]*>([^<]+)<\/div>/i', $card, $am)) {
            $author_name = trim(wp_strip_all_tags($am[1]));
        } elseif (preg_match('/class=["\'][^"\']*social-avatar[^"\']*["\'][^>]+alt=["\']([^"\']+)["\']/i', $card, $am)) {
            $author_name = trim(wp_strip_all_tags($am[1]));
        }

        if (preg_match('/class=["\'][^"\']*social-identity[^"\']*["\'][^>]*>.*?@([a-zA-Z0-9_.-]+)/is', $card, $hm)) {
            $author_handle = trim($hm[1]);
        }

        // 2. Extract Hashtags from Card
        $card_tags = [];
        if (preg_match_all('/<a[^>]*class=["\'][^"\']*hashtag[^"\']*["\'][^>]*>(?:#|#?<span>)?(.*?)(?:<\/span>)?<\/a>/isu', $card, $anchor_matches)) {
            foreach ($anchor_matches[1] as $raw_tag) {
                $clean_t = trim(wp_strip_all_tags(ltrim($raw_tag, '#')));
                if ($clean_t !== '' && !is_numeric($clean_t)) {
                    $card_tags[] = $clean_t;
                }
            }
        }
        if (empty($card_tags) && preg_match_all('/(?<![&\w])#([a-zA-Z0-9_\x{0080}-\x{FFFF}]+)/u', $card, $hash_matches)) {
            foreach ($hash_matches[1] as $raw_tag) {
                $clean_t = trim(ltrim($raw_tag, '#'));
                if ($clean_t !== '' && !is_numeric($clean_t)) {
                    $card_tags[] = $clean_t;
                }
            }
        }

        $primary_tag = !empty($card_tags[0]) ? $card_tags[0] : '';
        $formatted_tag = '';
        if ($primary_tag !== '') {
            $formatted_tag = social_split_camelcase_tag($primary_tag, $custom_overrides);
        }

        // 3. Inspect all <img> tags in this card
        if (preg_match_all('/<img[^>]+>/i', $card, $img_matches)) {
            $media_tags = [];
            foreach ($img_matches[0] as $img_tag) {
                if (strpos($img_tag, 'social-avatar') === false && 
                    strpos($img_tag, 'social-card-thumb') === false && 
                    strpos($img_tag, 'social-link-card') === false) {
                    $media_tags[] = $img_tag;
                }
            }
            $total_media = count($media_tags);
            $media_idx = 0;

            foreach ($img_matches[0] as $img_tag) {
                if (!preg_match('/src=["\']([^"\']+)["\']/i', $img_tag, $src_m)) {
                    continue;
                }
                $src_url = trim($src_m[1]);
                if (empty($src_url) || isset($descriptors[$src_url])) {
                    continue;
                }

                $alt_val = '';
                if (preg_match('/alt=["\']([^"\']*)["\']/i', $img_tag, $alt_m)) {
                    $alt_val = trim($alt_m[1]);
                }

                if (strpos($img_tag, 'social-avatar') !== false) {
                    $avatar_user = $author_name ?: ($author_handle ?: 'Social Digest');
                    $descriptors[$src_url] = [
                        'desc' => $avatar_user . ' (Avatar)',
                        'slug' => 'avatar-' . sanitize_title_with_dashes($author_handle ?: ($author_name ?: 'user')),
                    ];
                } elseif (strpos($img_tag, 'social-card-thumb') !== false || strpos($img_tag, 'social-link-card') !== false) {
                    $preview_title = $alt_val ?: 'Link Preview';
                    $descriptors[$src_url] = [
                        'desc' => $preview_title . ' (Preview)',
                        'slug' => 'preview-' . sanitize_title_with_dashes($preview_title),
                    ];
                } else {
                    $media_idx++;
                    $desc_label = '';
                    $base_slug  = '';

                    if ($formatted_tag !== '') {
                        $desc_label = ($total_media > 1) ? "{$formatted_tag} ({$media_idx})" : $formatted_tag;
                        $base_slug  = sanitize_title_with_dashes($formatted_tag);
                        if ($total_media > 1) {
                            $base_slug .= "-{$media_idx}";
                        }
                    } elseif ($alt_val !== '' && !in_array(strtolower($alt_val), ['mastodon image', 'bluesky image', 'digest media asset', 'social digest avatar'], true)) {
                        $clean_alt = wp_strip_all_tags($alt_val);
                        $desc_label = ($total_media > 1) ? "{$clean_alt} ({$media_idx})" : $clean_alt;
                        $base_slug  = sanitize_title_with_dashes($clean_alt);
                        if ($total_media > 1) {
                            $base_slug .= "-{$media_idx}";
                        }
                    } else {
                        $post_owner = $author_name ?: ($author_handle ?: 'digest');
                        $desc_label = ($total_media > 1) ? "Update from {$post_owner} ({$media_idx})" : "Update from {$post_owner}";
                        $base_slug  = sanitize_title_with_dashes($post_owner) . "-media";
                        if ($total_media > 1) {
                            $base_slug .= "-{$media_idx}";
                        }
                    }

                    $descriptors[$src_url] = [
                        'desc' => $desc_label,
                        'slug' => $base_slug,
                    ];
                }
            }
        }
    }

    return $descriptors;
}

/**
 * Sideloads external images referenced in post HTML content directly into the WordPress Media Library.
 * Replaces remote image src URLs with local attachment URLs to protect against link rot and server outages.
 * Supports targeted caching of remote avatars & preview cards, and generates responsive srcset attributes.
 *
 * @param string $content HTML content containing potential remote image references.
 * @param int $post_id WordPress post ID to attach the media items to.
 * @param bool $only_avatars_and_cards When true, only sideloads/caches remote avatars and link preview cards.
 * @param bool $generate_srcsets When true, attaches standard WordPress image classes and generates responsive srcset sizes.
 * @return string Content with remote image URLs replaced with local attachment URLs and responsive markup.
 */
function social_sideload_content_media($content, $post_id, $only_avatars_and_cards = false, $generate_srcsets = true) {
    if (empty($content) || empty($post_id)) {
        return $content;
    }

    $site_url = home_url();
    $site_host = wp_parse_url($site_url, PHP_URL_HOST);

    // Static cache for URLs already processed in this request to prevent duplicate downloads
    static $processed_media_urls = [];

    // Extract contextual media descriptors (hashtags, author handles, preview card titles)
    $media_descriptors = social_extract_media_descriptors_from_content($content);

    // Match all <img> tags to inspect attributes and classes
    if (preg_match_all('/<img[^>]+>/i', $content, $img_matches)) {
        foreach ($img_matches[0] as $img_tag) {
            if (!preg_match('/src=["\']([^"\']+)["\']/i', $img_tag, $src_m)) {
                continue;
            }
            $img_url = $src_m[1];
            $img_url_clean = trim($img_url);
            $parsed_host = wp_parse_url($img_url_clean, PHP_URL_HOST);

            // Skip relative URLs or URLs already hosted on this site
            if (empty($parsed_host) || ($site_host && strcasecmp($parsed_host, $site_host) === 0)) {
                continue;
            }

            // If limited to caching avatars & preview cards, verify context
            if ($only_avatars_and_cards) {
                $is_avatar = (strpos($img_tag, 'social-avatar') !== false);
                $is_card   = (strpos($img_tag, 'social-card-thumb') !== false || strpos($img_tag, 'social-link-card') !== false);
                if (!$is_avatar && !$is_card) {
                    continue;
                }
            }

            $attachment_id = 0;
            $local_url = '';

            $is_avatar = (strpos($img_tag, 'social-avatar') !== false);
            $descriptor = $media_descriptors[$img_url_clean] ?? null;
            if ($descriptor) {
                $desc = $descriptor['desc'];
                $slug = $descriptor['slug'];
            } else {
                $desc = $is_avatar ? 'Social Digest avatar' : 'Digest media asset';
                $slug = '';
            }

            // Check persistent avatar cache if this image is an account avatar
            $avatar_cache = get_option('social_digest_avatar_cache', []);
            if (!is_array($avatar_cache)) {
                $avatar_cache = [];
            }
            $avatar_key = $is_avatar ? (!empty($slug) ? $slug : ('avatar-' . md5($img_url_clean))) : '';

            if ($is_avatar && !empty($avatar_key) && isset($avatar_cache[$avatar_key])) {
                $cached = $avatar_cache[$avatar_key];
                $cached_id = absint($cached['attachment_id'] ?? 0);
                if ($cached_id && get_post_status($cached_id)) {
                    $cached_url = wp_get_attachment_url($cached_id);
                    if ($cached_url) {
                        $cached_remote = $cached['remote_url'] ?? '';
                        // If remote URL is unchanged, reuse existing master avatar with 0 downloads and 0 duplicates
                        if (empty($cached_remote) || $cached_remote === $img_url_clean) {
                            $attachment_id = $cached_id;
                            $local_url     = $cached_url;
                            $processed_media_urls[$img_url_clean] = [
                                'id'  => $attachment_id,
                                'url' => $local_url,
                            ];
                        }
                    }
                }
            }

            if (!$attachment_id) {
                if (isset($processed_media_urls[$img_url_clean])) {
                    $cached_entry = $processed_media_urls[$img_url_clean];
                    if (is_array($cached_entry)) {
                        $attachment_id = $cached_entry['id'];
                        $local_url     = $cached_entry['url'];
                    }
                } else {
                    $target_parent = $is_avatar ? 0 : $post_id;
                    $att_res = social_sideload_image_by_mime($img_url_clean, $target_parent, $desc, $slug);
                    if ($att_res && !is_wp_error($att_res)) {
                        $attachment_id = (int)$att_res;
                        $local_url = wp_get_attachment_url($attachment_id);
                        if ($local_url) {
                            $processed_media_urls[$img_url_clean] = [
                                'id'  => $attachment_id,
                                'url' => $local_url,
                            ];
                            // Update persistent master avatar cache
                            if ($is_avatar && !empty($avatar_key)) {
                                $avatar_cache[$avatar_key] = [
                                    'attachment_id' => $attachment_id,
                                    'local_url'     => $local_url,
                                    'remote_url'    => $img_url_clean,
                                    'updated'       => time(),
                                ];
                                update_option('social_digest_avatar_cache', $avatar_cache);
                            }
                        }
                    } else {
                        $processed_media_urls[$img_url_clean] = false;
                    }
                }
            }

            if ($local_url && $attachment_id) {
                $replacement_tag = $img_tag;
                // Replace remote src with local attachment URL
                $replacement_tag = str_replace($img_url, $local_url, $replacement_tag);

                // Add standard WordPress attachment class if not present
                if (strpos($replacement_tag, 'wp-image-') === false) {
                    if (preg_match('/class=["\']([^"\']*)["\']/i', $replacement_tag, $class_m)) {
                        $new_class_attr = 'class="' . esc_attr(trim($class_m[1] . ' wp-image-' . $attachment_id)) . '"';
                        $replacement_tag = str_replace($class_m[0], $new_class_attr, $replacement_tag);
                    } else {
                        $replacement_tag = str_replace('<img ', '<img class="wp-image-' . $attachment_id . '" ', $replacement_tag);
                    }
                }

                // Add responsive srcset and sizes attributes if enabled
                if ($generate_srcsets && function_exists('wp_image_add_srcset_and_sizes')) {
                    $meta = wp_get_attachment_metadata($attachment_id);
                    if (is_array($meta) && !empty($meta)) {
                        $replacement_tag = wp_image_add_srcset_and_sizes($replacement_tag, $meta, $attachment_id);
                    }
                }

                $content = str_replace($img_tag, $replacement_tag, $content);

                // Update wrapping image/lightbox anchor href if it points to this remote image
                $content = str_replace('href="' . $img_url . '"', 'href="' . $local_url . '"', $content);
            }
        }
    }

    if ($generate_srcsets && function_exists('wp_filter_content_tags')) {
        $content = wp_filter_content_tags($content);
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

/**
 * Returns the built-in baseline tag title override rules as an editable string.
 * Pre-populated into Custom Tag Title Overrides and completely visible and editable by administrators.
 *
 * @return string Default baseline rules, one per line.
 */
function social_get_default_tag_overrides_string() {
    return implode("\n", [
        'Chromebook',
        'Coreboot',
        'DellXPS=Dell XPS',
        'Dimensity',
        'E-ink',
        'EliteMiniPC=Elite Mini PC',
        'FDroid=F-Droid',
        'GEEKOM*',
        'Googlebook',
        'MediaTek=MediaTek',
        'MINISFORUM*',
        'MiniPC=Mini PC',
        'MinimalPhone=Minimal Phone',
        'NintendoSwitch=Nintendo Switch',
        'NVIDIA*',
        'OpenClaw',
        'PocketBook',
        'Qualcomm',
        'RaspberryPi=Raspberry Pi',
        'SamsungGalaxy=Samsung Galaxy',
        'SamsungGalaxyTab=Samsung Galaxy Tab',
        'SnapdragonX=Snapdragon X',
        'SteamDeck=Steam Deck',
        'SteamFrame=Steam Frame',
        'SteamOS=SteamOS',
        'ThinkBook*',
        'U-Boot',
        'XPS*',
    ]);
}

/**
 * Returns static dictionary map of built-in baseline terms for fallback compatibility.
 * Zero database queries or background archive scanning.
 *
 * @return array Normalized key to formatted brand name map.
 */
function social_get_site_vocabulary_dictionary() {
    return [
        'samsunggalaxytab'  => 'Samsung Galaxy Tab',
        'samsunggalaxy'     => 'Samsung Galaxy',
        'snapdragonx2'      => 'Snapdragon X2',
        'snapdragonx1'      => 'Snapdragon X1',
        'snapdragonx'       => 'Snapdragon X',
        'snapdragon'        => 'Snapdragon',
        'mediatek'          => 'MediaTek',
        'dellxps'           => 'Dell XPS',
        'xps'               => 'XPS',
        'googlebook'        => 'Googlebook',
        'chromebook'        => 'Chromebook',
        'pocketbook'        => 'PocketBook',
        'eliteminipc'       => 'Elite Mini PC',
        'minipc'            => 'Mini PC',
        'minipcs'           => 'Mini PCs',
        'thinkbookplusgen'  => 'ThinkBook Plus Gen',
        'thinkbookplus'     => 'ThinkBook Plus',
        'thinkbook'         => 'ThinkBook',
        'minimalphone'      => 'Minimal Phone',
        'steamframe'        => 'Steam Frame',
        'steamdeck'         => 'Steam Deck',
        'steamos'           => 'SteamOS',
        'nintendoswitch'    => 'Nintendo Switch',
        'raspberrypi'       => 'Raspberry Pi',
        'openclaw'          => 'OpenClaw',
        'dimensity'         => 'Dimensity',
        'qualcomm'          => 'Qualcomm',
        'adreno'            => 'Adreno',
        'fdroid'            => 'F-Droid',
        'eink'              => 'E-ink',
        'uboot'             => 'U-Boot',
        'coreboot'          => 'Coreboot',
    ];
}

/**
 * Flush the site vocabulary cache (retained for backward compatibility).
 */
function social_flush_site_vocabulary_cache() {
    delete_transient('social_digest_site_vocab_cache');
    return social_get_site_vocabulary_dictionary();
}

/**
 * Deduplicate a list of tags case-insensitively, strictly preferring versions
 * with CamelCase/uppercase casing over all-lowercase duplicates.
 */
function social_dedupe_cased_tags($tags) {
    if (!is_array($tags)) {
        return [];
    }
    $map = [];
    foreach ($tags as $tag) {
        $tag = trim(ltrim((string)$tag, '#'));
        if ($tag === '') continue;
        $key = mb_strtolower($tag);
        if (!isset($map[$key])) {
            $map[$key] = $tag;
        } else {
            // Count uppercase characters to prefer CamelCase over lowercase
            $existing_caps = preg_match_all('/[A-Z]/u', $map[$key]);
            $new_caps      = preg_match_all('/[A-Z]/u', $tag);
            if ($new_caps > $existing_caps) {
                $map[$key] = $tag;
            }
        }
    }
    return array_values($map);
}

function social_split_camelcase_tag($tag, $custom_overrides_str = '') {
    if (!is_scalar($tag)) return '';
    $t = ltrim(trim((string)$tag), '#');
    if ($t === '') return '';

    // Check custom overrides (e.g. "rawtag=Formatted Name", "MINISFORUM*", or baseline terms)
    if (empty($custom_overrides_str)) {
        $opts = get_option('social_digest_options', []);
        $custom_overrides_str = $opts['title_tag_custom_overrides'] ?? '';
        if (empty($custom_overrides_str)) {
            $custom_overrides_str = social_get_default_tag_overrides_string();
        }
    }

    if (!empty($custom_overrides_str)) {
        $lines = preg_split('/[\r\n,]+/', $custom_overrides_str);
        
        // Pass 1: Exact matches
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || strpos($line, '#') === 0) continue;
            if (strpos($line, '=') !== false) {
                list($k, $v) = explode('=', $line, 2);
                $k = ltrim(trim($k), '#');
                $v = trim($v);
                if (substr($k, -1) === '*') continue; // Skip wildcard rules in exact pass
                if ($k !== '' && $v !== '' && mb_strtolower(str_replace([' ', '_', '-'], '', $t)) === mb_strtolower(str_replace([' ', '_', '-'], '', $k))) {
                    return $v;
                }
            } else {
                // Support standalone exact terms without '=' (e.g. "F-Droid", "MediaTek")
                if (substr($line, -1) !== '*') {
                    $k = ltrim($line, '#');
                    $v = $line;
                    if ($k !== '' && mb_strtolower(str_replace([' ', '_', '-'], '', $t)) === mb_strtolower(str_replace([' ', '_', '-'], '', $k))) {
                        return $v;
                    }
                }
            }
        }

        // Pass 2: Wildcard prefix matches (e.g. "MINISFORUM*", "SnapdragonX*=Snapdragon X")
        $t_no_space = str_replace([' ', '_', '-'], '', $t);
        $t_lower_no_space = mb_strtolower($t_no_space);

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '') continue;

            $prefix_raw = '';
            $formatted_prefix = '';

            if (strpos($line, '=') !== false) {
                list($k, $v) = explode('=', $line, 2);
                $k = ltrim(trim($k), '#');
                $v = trim($v);
                if (substr($k, -1) === '*') {
                    $prefix_raw = rtrim($k, '*');
                    $formatted_prefix = ($v !== '') ? $v : $prefix_raw;
                }
            } elseif (substr($line, -1) === '*') {
                $k = ltrim(trim($line), '#');
                $prefix_raw = rtrim($k, '*');
                $formatted_prefix = $prefix_raw;
            }

            if ($prefix_raw !== '') {
                $prefix_key = mb_strtolower(str_replace([' ', '_', '-'], '', $prefix_raw));
                if ($prefix_key !== '' && strpos($t_lower_no_space, $prefix_key) === 0) {
                    // Extract suffix after the matched prefix
                    $suffix_len = strlen($prefix_key);
                    $raw_suffix = substr($t_no_space, $suffix_len);

                    if ($raw_suffix === '') {
                        return $formatted_prefix;
                    }

                    // Format suffix (CamelCase and letter-number boundary splitting)
                    $suffix_formatted = preg_replace('/([a-z]{2,})([A-Z0-9])/u', '$1 $2', $raw_suffix);
                    $suffix_formatted = preg_replace('/([a-zA-Z]{2,})([0-9]+)/u', '$1 $2', $suffix_formatted);
                    $suffix_formatted = preg_replace('/([0-9]+)([a-zA-Z]{2,})/u', '$1 $2', $suffix_formatted);

                    $sub_words = explode(' ', $suffix_formatted);
                    $processed_sub = [];
                    foreach ($sub_words as $sw) {
                        $sw = trim($sw);
                        if ($sw === '') continue;
                        if (strlen($sw) <= 3 || is_numeric($sw) || preg_match('/^[A-Z0-9]+$/', $sw)) {
                            $processed_sub[] = mb_strtoupper($sw);
                        } else {
                            $processed_sub[] = mb_convert_case($sw, MB_CASE_TITLE, "UTF-8");
                        }
                    }
                    $final_suffix = implode(' ', $processed_sub);
                    return trim($formatted_prefix . ' ' . $final_suffix);
                }
            }
        }
    }

    // 1. Explicit Samsung Galaxy Tab S-Series pattern matching (e.g. "SamsungGalaxyTabS12", "samsunggalaxytabs12", "TabS12")
    if (preg_match('/(?i)samsung\s*galaxy\s*tab\s*s\s*(\d+)/u', $t, $m)) {
        $t = preg_replace('/(?i)samsung\s*galaxy\s*tab\s*s\s*\d+/u', 'Samsung Galaxy Tab S' . $m[1], $t);
    } elseif (preg_match('/(?i)samsung\s*galaxy\s*tabs\s*(\d+)/u', $t, $m)) {
        $t = preg_replace('/(?i)samsung\s*galaxy\s*tabs\s*\d+/u', 'Samsung Galaxy Tab S' . $m[1], $t);
    } elseif (preg_match('/(?i)galaxy\s*tabs\s*(\d+)/u', $t, $m)) {
        $t = preg_replace('/(?i)galaxy\s*tabs\s*\d+/u', 'Galaxy Tab S' . $m[1], $t);
    } elseif (preg_match('/(?i)tab\s*s\s*(\d+)/u', $t, $m)) {
        $t = preg_replace('/(?i)tab\s*s\s*\d+/u', 'Tab S' . $m[1], $t);
    }

    // 2. Explicit Snapdragon X-Series processor pattern matching (e.g. "SnapdragonX2", "snapdragonx2", "SnapdragonX2Elite")
    if (preg_match('/(?i)snapdragon\s*x\s*(\d+)/u', $t, $m)) {
        $t = preg_replace('/(?i)snapdragon\s*x\s*\d+/u', 'Snapdragon X' . $m[1], $t);
    } elseif (preg_match('/(?i)snapdragon\s*x(\d+)/u', $t, $m)) {
        $t = preg_replace('/(?i)snapdragon\s*x\d+/u', 'Snapdragon X' . $m[1], $t);
    }

    // Get dynamic vocabulary dictionary learned from site content + baseline terms
    $compound_map = social_get_site_vocabulary_dictionary();

    // Treat underscores as hyphens (social platforms like Mastodon do not allow hyphens in hashtags, so underscores represent hyphens)
    $t = preg_replace('/_+/u', '-', $t);
    $t = trim($t, "- \t\n\r\0\x0B");

    // CamelCase transitions (e.g. "AcerGooglebook" -> "Acer Googlebook", "Android17QPR" -> "Android 17 QPR")
    $t = preg_replace('/([a-z0-9])([A-Z])/u', '$1 $2', $t);
    $t = preg_replace('/([A-Z]{2,})([A-Z][a-z])/u', '$1 $2', $t);
    // Single uppercase letter prefix before Titlecase word (e.g. "FDroid" -> "F Droid", "GSuite" -> "G Suite", "XScreen" -> "X Screen")
    $t = preg_replace('/(?:\b|^)([A-Z])([A-Z][a-z]+)/u', '$1 $2', $t);

    // Letter-number boundary splitting (e.g. "Googlebook14" -> "Googlebook 14", "QPR2" -> "QPR 2", "gen7" -> "gen 7")
    $t = preg_replace('/([a-zA-Z]+)([0-9]+)/u', '$1 $2', $t);
    $t = preg_replace('/([0-9]+)([a-zA-Z]+)/u', '$1 $2', $t);

    // Specific model/chip designation regex splits
    $t = preg_replace('/(elite)(mini)(pc)/i', '$1 $2 $3', $t);
    $t = preg_replace('/(mini)(pc|pcs)/i', '$1 $2', $t);

    $segment_word = function($w) use (&$segment_word, $compound_map) {
        $clean = mb_strtolower(trim($w));
        if ($clean === '' || is_numeric($clean)) return $w;

        if (isset($compound_map[$clean])) {
            return $compound_map[$clean];
        }

        // Try greedy prefix matching against compound map keys
        foreach ($compound_map as $k => $v) {
            if (strlen($k) >= 3 && strpos($clean, $k) === 0 && strlen($clean) > strlen($k)) {
                $rest = substr($clean, strlen($k));
                $seg_rest = $segment_word($rest);
                return $v . ' ' . $seg_rest;
            }
        }

        return $w;
    };

    $raw_tokens = explode(' ', $t);
    $processed_phrases = [];

    $upper_acronyms = [
        'AI', 'PC', 'PCS', 'VR', 'X', 'X1', 'X2', 'X3', '3D', '2K', '4K', '8K',
        '5G', '4G', 'US', 'UK', 'EU', 'OLED', 'AMOLED', 'RAM', 'CPU', 'GPU',
        'S10', 'S11', 'S12', 'S24', 'QN10', 'OS', 'UI', 'HD', 'QPR', 'QPR1',
        'QPR2', 'QPR3', 'CX', 'XPS', 'GTX', 'RTX', 'RX', 'SOC', 'NPU', 'SSD'
    ];

    foreach ($raw_tokens as $token) {
        $token = trim($token);
        if ($token === '') continue;

        $segmented = $segment_word($token);
        $sub_words = explode(' ', $segmented);

        foreach ($sub_words as $sw) {
            $sw = trim($sw);
            if ($sw === '') continue;

            $sw_upper = mb_strtoupper($sw);
            if (in_array($sw_upper, $upper_acronyms, true)) {
                $processed_phrases[] = $sw_upper;
            } elseif (preg_match('/^[A-Z0-9\-\.]/u', $sw) && !ctype_lower($sw) && !ctype_upper($sw)) {
                // Preserve exact mixed capitalization if provided by site vocabulary term (e.g. MediaTek, ThinkBook)
                $processed_phrases[] = $sw;
            } else {
                $processed_phrases[] = mb_convert_case($sw, MB_CASE_TITLE, "UTF-8");
            }
        }
    }

    $res = trim(implode(' ', $processed_phrases));
    // Final precision fixes for model designations
    $res = preg_replace('/\bSamsung Galaxy Tabs (\d+)\b/i', 'Samsung Galaxy Tab S$1', $res);
    $res = preg_replace('/\bGalaxy Tabs (\d+)\b/i', 'Galaxy Tab S$1', $res);
    $res = preg_replace('/\bSnapdragon X (\d+)\b/i', 'Snapdragon X$1', $res);
    $res = preg_replace('/\bMedia\s*Tek\b/i', 'MediaTek', $res);
    $res = preg_replace('/\bF\s+Droid\b/i', 'F-Droid', $res);
    $res = preg_replace('/\bE\s+Ink\b/i', 'E-Ink', $res);

    return $res;
}

function social_extract_topic_keywords_from_posts($items) {
    if (empty($items) || !is_array($items)) return [];

    $stop_words = [
        'the','a','an','and','or','but','if','then','else','when','at','by','from','for','with','about','against','between','into','through','during','before','after','above','below','to','up','down','in','out','on','off','over','under','again','further','once','here','there','where','why','how','all','any','both','each','few','more','most','other','some','such','no','nor','not','only','own','same','so','than','too','very','s','t','can','will','just','don','should','now','according','leaked','details','line','premium','tablets','include','both','chips','displays','support','open','source','tool','lets','developers','port','games','specifically','designed','bring','including','meta','quest','first','mini','processor','graphics','announced','june','available','now','laptop','key','feature','motorized','swivel','hinge','rotate','face','video','calls','fold','keyboard','tablet','usage','also','price','tag','unsurprising','disappointing','since','covers','follow','last','year','trades','faster','expected','ship','december','news','roundup'
    ];

    $phrases = [];

    foreach ($items as $item) {
        $text = $item['full_text'] ?? $item['text'] ?? '';
        if (!$text) continue;

        // Find Capitalized Multi-word Proper Nouns (e.g. "Samsung Galaxy Tab", "Steam Frame", "Qualcomm Snapdragon", "Lenovo ThinkBook", "Minimal Phone")
        if (preg_match_all('/\b([A-Z][a-zA-Z0-9\+\-\.]{2,} (?:\w+ ){0,2}[A-Z0-9][a-zA-Z0-9\+\-\.]*)\b/u', $text, $matches)) {
            foreach ($matches[1] as $m) {
                $m = trim($m);
                $words = explode(' ', $m);
                $first_lower = mb_strtolower($words[0]);
                if (in_array($first_lower, ['the', 'this', 'that', 'with', 'from', 'first', 'according'])) {
                    array_shift($words);
                    $m = implode(' ', $words);
                }
                if ($m && mb_strlen($m) >= 3 && !in_array(mb_strtolower($m), $stop_words)) {
                    $phrases[] = $m;
                }
            }
        }

        // Single Capitalized Brand/Product Words if needed
        if (preg_match_all('/\b([A-Z][a-zA-Z0-9]{3,})\b/u', $text, $s_matches)) {
            foreach ($s_matches[1] as $sm) {
                if (!in_array(mb_strtolower($sm), $stop_words)) {
                    $phrases[] = $sm;
                }
            }
        }
    }

    return array_values(array_unique($phrases));
}

/**
 * Detect if two tag phrases represent the same or heavily overlapping topics
 * (e.g. "Samsung Galaxy Tab S12" vs "Samsung Galaxy Tabs 12" or "Samsung Galaxy Tab").
 */
function social_are_tags_fuzzy_duplicates($tag1, $tag2) {
    if (empty($tag1) || empty($tag2)) return false;

    $clean1 = mb_strtolower(trim($tag1));
    $clean2 = mb_strtolower(trim($tag2));
    if ($clean1 === $clean2) return true;

    // Remove non-alphanumeric except spaces
    $a1 = preg_replace('/[^a-z0-9\s]/u', '', $clean1);
    $a2 = preg_replace('/[^a-z0-9\s]/u', '', $clean2);

    // Stemming (trim trailing 's' from words)
    $words1 = array_filter(array_map(fn($w) => rtrim($w, 's'), explode(' ', $a1)));
    $words2 = array_filter(array_map(fn($w) => rtrim($w, 's'), explode(' ', $a2)));

    $str1 = implode(' ', $words1);
    $str2 = implode(' ', $words2);

    if ($str1 === $str2) return true;
    if ($str1 !== '' && $str2 !== '') {
        if (strpos($str1, $str2) !== false || strpos($str2, $str1) !== false) {
            return true;
        }
    }

    $intersect = array_intersect($words1, $words2);
    $min_count = min(count($words1), count($words2));
    if ($min_count > 0 && (count($intersect) / $min_count) >= 0.6) {
        return true;
    }

    return false;
}

function social_rank_and_format_title_tags($candidate_tags, $default_tags_str, $enclosure = 'parentheses', $delimiter = 'oxford', $max_tags_count = 3, $strategy = 'first', $custom_overrides_str = '') {
    $max_tags = max(1, min(5, (int)$max_tags_count));
    $selected_tags = [];
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
            // Select at most 1 distinct, non-duplicate tag per social post
            foreach ($candidate_tags as $post_tags) {
                if (!is_array($post_tags)) {
                    $post_tags = [$post_tags];
                }

                $post_tags_copy = array_values($post_tags);

                if ($strategy === 'popularity' && count($post_tags_copy) > 1 && !empty($tag_weights)) {
                    usort($post_tags_copy, function($a, $b) use ($tag_weights) {
                        $w_a = $tag_weights[mb_strtolower(trim(ltrim($a, '#')))] ?? 0;
                        $w_b = $tag_weights[mb_strtolower(trim(ltrim($b, '#')))] ?? 0;
                        return $w_b <=> $w_a;
                    });
                } elseif ($strategy === 'random' && count($post_tags_copy) > 1) {
                    shuffle($post_tags_copy);
                }

                foreach ($post_tags_copy as $tag) {
                    $clean_tag = social_split_camelcase_tag($tag, $custom_overrides_str);
                    if ($clean_tag === '') continue;

                    $is_duplicate = false;
                    foreach ($selected_tags as $existing_tag) {
                        if (social_are_tags_fuzzy_duplicates($existing_tag, $clean_tag)) {
                            $is_duplicate = true;
                            break;
                        }
                    }

                    if (!$is_duplicate) {
                        $selected_tags[] = $clean_tag;
                        break; // Strictly max 1 tag from this post!
                    }
                }
                if (count($selected_tags) >= $max_tags) {
                    break;
                }
            }
        } else {
            // Flat array fallback
            $flat_tags = array_values($candidate_tags);
            if ($strategy === 'popularity' && count($flat_tags) > 1 && !empty($tag_weights)) {
                usort($flat_tags, function($a, $b) use ($tag_weights) {
                    $w_a = $tag_weights[mb_strtolower(trim(ltrim($a, '#')))] ?? 0;
                    $w_b = $tag_weights[mb_strtolower(trim(ltrim($b, '#')))] ?? 0;
                    return $w_b <=> $w_a;
                });
            } elseif ($strategy === 'random' && count($flat_tags) > 1) {
                shuffle($flat_tags);
            }

            foreach ($flat_tags as $tag) {
                $clean_tag = social_split_camelcase_tag($tag, $custom_overrides_str);
                if ($clean_tag === '') continue;

                $is_duplicate = false;
                foreach ($selected_tags as $existing_tag) {
                    if (social_are_tags_fuzzy_duplicates($existing_tag, $clean_tag)) {
                        $is_duplicate = true;
                        break;
                    }
                }

                if (!$is_duplicate) {
                    $selected_tags[] = $clean_tag;
                }
                if (count($selected_tags) >= $max_tags) {
                    break;
                }
            }
        }
    }

    // Fall back to default tags if no post hashtags were found
    if (empty($selected_tags)) {
        $defaults = array_filter(array_map('trim', explode(',', $default_tags_str)));
        foreach ($defaults as $d) {
            $clean_tag = social_split_camelcase_tag($d, $custom_overrides_str);
            if ($clean_tag === '') continue;

            $is_duplicate = false;
            foreach ($selected_tags as $existing_tag) {
                if (social_are_tags_fuzzy_duplicates($existing_tag, $clean_tag)) {
                    $is_duplicate = true;
                    break;
                }
            }

            if (!$is_duplicate) {
                $selected_tags[] = $clean_tag;
            }
            if (count($selected_tags) >= $max_tags) {
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
            if ($c === 1) {
                $joined = $selected_tags[0];
            } elseif ($c === 2) {
                $joined = $selected_tags[0] . ' & ' . $selected_tags[1];
            } else {
                $last = array_pop($selected_tags);
                $joined = implode(', ', $selected_tags) . ' & ' . $last;
            }
            break;
        case 'pipe':
            $joined = implode(' | ', $selected_tags);
            break;
        case 'slash':
            $joined = implode(' / ', $selected_tags);
            break;
        case 'oxford':
        default:
            if ($c === 1) {
                $joined = $selected_tags[0];
            } elseif ($c === 2) {
                $joined = $selected_tags[0] . ' and ' . $selected_tags[1];
            } else {
                $last = array_pop($selected_tags);
                $joined = implode(', ', $selected_tags) . ', and ' . $last;
            }
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

/**
 * Automatically purges site caches across W3 Total Cache (W3TC), WP Super Cache, WP Rocket,
 * LiteSpeed, WP Fastest Cache, SG Optimizer, and core WordPress caches whenever
 * a digest post is published or updated.
 *
 * @param int $post_id Optional post ID to purge specific post cache.
 */
function social_purge_site_caches($post_id = 0) {
    // 1. Core WordPress Post Cache
    if ($post_id > 0) {
        clean_post_cache($post_id);
    }

    // 2. W3 Total Cache (W3TC)
    if (function_exists('w3tc_flush_posts')) {
        w3tc_flush_posts();
    } elseif (function_exists('w3tc_pgcache_flush')) {
        w3tc_pgcache_flush();
    }

    // 3. WP Super Cache
    if (function_exists('wp_cache_clear_cache')) {
        wp_cache_clear_cache();
    }

    // 4. WP Rocket
    if (function_exists('rocket_clean_domain')) {
        rocket_clean_domain();
    }

    // 5. LiteSpeed Cache
    if (has_action('litespeed_purge_all')) {
        do_action('litespeed_purge_all');
    }

    // 6. WP Fastest Cache
    if (has_action('wpfc_clear_all_cache')) {
        do_action('wpfc_clear_all_cache');
    }

    // 7. SG Optimizer (SiteGround)
    if (function_exists('sg_cachepress_purge_cache')) {
        sg_cachepress_purge_cache();
    }
}

/**
 * Scans the WordPress Media Library for duplicate Social Digest avatar attachments.
 * Retains one master unattached avatar per account, updates any digest posts pointing
 * to duplicate avatar URLs, and permanently prunes duplicate attachments and files from disk.
 *
 * @return array ['success' => bool, 'message' => string, 'cleaned_count' => int]
 */
function social_cleanup_duplicate_avatars() {
    if (!current_user_can('manage_options')) {
        return ['success' => false, 'message' => 'Permission denied.'];
    }

    // Query all potential avatar attachments created by Social Digest
    $slug_query = get_posts([
        'post_type'      => 'attachment',
        'post_status'    => 'inherit',
        'posts_per_page' => 300,
        's'              => 'avatar-',
    ]);

    $title_query = get_posts([
        'post_type'      => 'attachment',
        'post_status'    => 'inherit',
        'posts_per_page' => 300,
        's'              => '(Avatar)',
    ]);

    $all_avatars = [];
    foreach (array_merge((array)$slug_query, (array)$title_query) as $att) {
        if (!empty($att->ID)) {
            $all_avatars[$att->ID] = $att;
        }
    }

    if (empty($all_avatars)) {
        return [
            'success'       => true,
            'message'       => 'No duplicate avatar attachments found in Media Library.',
            'cleaned_count' => 0
        ];
    }

    // Group avatars by account identity / base slug
    // e.g. "avatar-liliputing-bsky-social-2" -> base "avatar-liliputing-bsky-social"
    $grouped = [];
    foreach ($all_avatars as $id => $att) {
        $file_path = function_exists('get_attached_file') ? get_attached_file($id) : '';
        $filename  = basename($file_path ?: ($att->guid ?? ''));
        $name_no_ext = preg_replace('/\.[a-zA-Z0-9]+$/', '', $filename);
        $base_key = preg_replace('/-\d+$/', '', $name_no_ext);
        if (empty($base_key) || $base_key === 'avatar') {
            $base_key = sanitize_title_with_dashes($att->post_title);
            $base_key = preg_replace('/-\d+$/', '', $base_key);
        }
        $grouped[$base_key][] = $att;
    }

    $avatar_cache = get_option('social_digest_avatar_cache', []);
    if (!is_array($avatar_cache)) {
        $avatar_cache = [];
    }

    $total_cleaned = 0;

    foreach ($grouped as $base_key => $group_list) {
        if (empty($group_list)) continue;

        // If only 1 avatar exists for this identity, ensure post_parent is 0 (unattached)
        if (count($group_list) === 1) {
            $single_att = $group_list[0];
            if ($single_att->post_parent != 0) {
                wp_update_post([
                    'ID'          => $single_att->ID,
                    'post_parent' => 0,
                ]);
            }
            $avatar_cache[$base_key] = [
                'attachment_id' => $single_att->ID,
                'local_url'     => wp_get_attachment_url($single_att->ID),
                'updated'       => time(),
            ];
            continue;
        }

        // Sort descending by ID to keep the newest attachment as master
        usort($group_list, function($a, $b) {
            return ($b->ID - $a->ID);
        });

        // The master avatar to retain
        $master_att = array_shift($group_list);
        if ($master_att->post_parent != 0) {
            wp_update_post([
                'ID'          => $master_att->ID,
                'post_parent' => 0,
            ]);
        }

        $master_url = wp_get_attachment_url($master_att->ID);
        $avatar_cache[$base_key] = [
            'attachment_id' => $master_att->ID,
            'local_url'     => $master_url,
            'updated'       => time(),
        ];

        // Process duplicates
        foreach ($group_list as $dup_att) {
            $dup_url = wp_get_attachment_url($dup_att->ID);

            // If any posts referenced the duplicate URL, replace it with the master URL
            if ($dup_url && $master_url && $dup_url !== $master_url) {
                global $wpdb;
                if (!empty($wpdb) && !empty($wpdb->posts)) {
                    $wpdb->query($wpdb->prepare(
                        "UPDATE {$wpdb->posts} SET post_content = REPLACE(post_content, %s, %s) WHERE post_content LIKE %s",
                        $dup_url,
                        $master_url,
                        '%' . $wpdb->esc_like($dup_url) . '%'
                    ));
                }
            }

            // Permanently delete duplicate attachment and its files from disk
            wp_delete_attachment($dup_att->ID, true);
            $total_cleaned++;
        }
    }

    update_option('social_digest_avatar_cache', $avatar_cache);

    if ($total_cleaned > 0) {
        return [
            'success'       => true,
            'message'       => sprintf('Cleaned up %d duplicate avatar attachment(s) from the Media Library. Master avatars are now unattached and permanently deduplicated.', $total_cleaned),
            'cleaned_count' => $total_cleaned,
        ];
    }

    return [
        'success'       => true,
        'message'       => 'Avatar storage is healthy. All active avatars are deduplicated and saved as unattached master assets.',
        'cleaned_count' => 0,
    ];
}
