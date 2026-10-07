<?php
namespace SocialDigest;

if (!defined('ABSPATH')) exit;

// ==========================================
// STRING, URL & CACHE UTILITIES
// ==========================================

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
