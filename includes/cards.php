<?php
namespace SocialDigest;

if (!defined('ABSPATH')) exit;

// ==========================================
// OPENGRAPH & LINK PREVIEW CARDS
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
