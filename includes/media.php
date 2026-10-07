<?php
namespace SocialDigest;

if (!defined('ABSPATH')) exit;

// ==========================================
// MEDIA SIDELOADING & ATTACHMENT MANAGEMENT
// ==========================================

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

                // Update wrapping image/lightbox anchor href if it points to this remote image or its fullsize version
                $content = str_replace('href="' . $img_url . '"', 'href="' . $local_url . '"', $content);
                if (strpos($img_url, 'feed_thumbnail') !== false) {
                    $full_remote = str_replace('feed_thumbnail', 'feed_fullsize', $img_url);
                    $content = str_replace('href="' . $full_remote . '"', 'href="' . $local_url . '"', $content);
                }
            }
        }
    }

    if ($generate_srcsets && function_exists('wp_filter_content_tags')) {
        $content = wp_filter_content_tags($content);
    }

    return $content;
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
