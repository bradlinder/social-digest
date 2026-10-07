<?php
namespace SocialDigest;

if (!defined('ABSPATH')) exit;

// ==========================================
// HASHTAGS, TAXONOMY & TITLE OVERRIDES
// ==========================================

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
 * Normalizes line endings in tag override strings and repairs any corrupted 'rn' newline artifacts.
 *
 * @param string $str Raw tag overrides string.
 * @return string Cleaned string with standard \n line breaks.
 */
function social_normalize_overrides_newlines($str) {
    if (!is_string($str) || $str === '') return '';
    
    // Convert standard Windows and Mac line breaks
    $str = str_replace(["\r\n", "\r"], "\n", $str);
    
    // Repair corrupted strings where \r\n was stripped to literal 'rn'
    if (strpos($str, "\n") === false && strpos($str, 'rn') !== false) {
        $str = preg_replace('/(?<=[a-zA-Z0-9*=\-_#])rn(?=[a-zA-Z0-9*=\-_#])/u', "\n", $str);
        $str = str_replace(['rnrnrn', 'rnrn', 'rn'], "\n", $str);
    }
    
    // Handle literal escaped newline text
    $str = str_replace(['\\r\\n', '\\n', '\\r'], "\n", $str);
    
    return $str;
}

/**
 * Deduplicates custom tag title override rules case-sensitively,
 * preserving comments and clean one-rule-per-line formatting.
 *
 * @param string $str Raw custom overrides string.
 * @return string Deduplicated overrides string.
 */
function social_dedupe_override_rules($str) {
    $str = social_normalize_overrides_newlines($str);
    if (empty($str) || trim((string)$str) === '') {
        return '';
    }

    $lines = preg_split('/[\r\n]+/', trim((string)$str));
    $clean_lines = [];
    $seen_keys = [];

    foreach ($lines as $line) {
        $trimmed = trim($line);
        if ($trimmed === '') continue;
        if (strpos($trimmed, '#') === 0) {
            $clean_lines[] = $trimmed;
            continue;
        }

        $key = str_replace([' ', '_', '-'], '', $trimmed);
        if (strpos($trimmed, '=') !== false) {
            list($k) = explode('=', $trimmed, 2);
            $key = str_replace([' ', '_', '-'], '', ltrim(trim($k), '#'));
        }

        if (!isset($seen_keys[$key])) {
            $seen_keys[$key] = true;
            $clean_lines[] = $trimmed;
        }
    }

    return implode("\n", $clean_lines);
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

    // Check user's custom overrides (e.g. "rawtag=Formatted Name", "MINISFORUM*")
    if (empty($custom_overrides_str)) {
        $opts = get_option('social_digest_options', []);
        $custom_overrides_str = $opts['title_tag_custom_overrides'] ?? '';
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

    // If not matched by explicit or wildcard user overrides, apply universal syntax splitting:
    // 1. Two or more consecutive underscores represent a hyphen (e.g. #Wi__Fi -> Wi-Fi)
    $t = preg_replace('/_{2,}/u', '-', $t);
    // 2. A single underscore represents a space (e.g. #AI_PC -> AI PC)
    $t = str_replace('_', ' ', $t);
    $t = trim($t, "- \t\n\r\0\x0B");

    // 3. Universal CamelCase transitions (e.g. "AcerLaptop" -> "Acer Laptop", "Android17QPR" -> "Android 17 QPR")
    $t = preg_replace('/([a-z0-9])([A-Z])/u', '$1 $2', $t);
    $t = preg_replace('/([A-Z]{2,})([A-Z][a-z])/u', '$1 $2', $t);
    // Single uppercase letter prefix before Titlecase word (e.g. "GSuite" -> "G Suite", "XScreen" -> "X Screen")
    $t = preg_replace('/(?:\b|^)([A-Z])([A-Z][a-z]+)/u', '$1 $2', $t);

    // 4. Letter-number boundary splitting (e.g. "QPR2" -> "QPR 2", "gen7" -> "gen 7")
    $t = preg_replace('/([a-zA-Z]+)([0-9]+)/u', '$1 $2', $t);
    $t = preg_replace('/([0-9]+)([a-zA-Z]+)/u', '$1 $2', $t);

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

        $token_upper = mb_strtoupper($token);
        if (in_array($token_upper, $upper_acronyms, true)) {
            $processed_phrases[] = $token_upper;
        } elseif (preg_match('/^[A-Z0-9\-\.]/u', $token) && !ctype_lower($token) && !ctype_upper($token)) {
            // Preserve mixed capitalization if present
            $processed_phrases[] = $token;
        } else {
            $processed_phrases[] = mb_convert_case($token, MB_CASE_TITLE, "UTF-8");
        }
    }

    return trim(implode(' ', $processed_phrases));
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
        if (preg_match_all('/\b([A-Z][a-zA-Z0-9]{3,})\b/u', $s_matches)) {
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
