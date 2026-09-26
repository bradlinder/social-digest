const fs = require('fs');
const crypto = require('crypto');
const phpParser = require('php-parser');

const helpers = fs.readFileSync('includes/helpers.php');
const apiClients = fs.readFileSync('includes/api-clients.php');
const feedBuilder = fs.readFileSync('includes/feed-builder.php');
const admin = fs.readFileSync('includes/admin.php');

const hashHelpers = crypto.createHash('md5').update(helpers).digest('hex');
const hashApiClients = crypto.createHash('md5').update(apiClients).digest('hex');
const hashFeedBuilder = crypto.createHash('md5').update(feedBuilder).digest('hex');
const hashAdmin = crypto.createHash('md5').update(admin).digest('hex');

const b64Helpers = helpers.toString('base64');
const b64ApiClients = apiClients.toString('base64');
const b64FeedBuilder = feedBuilder.toString('base64');
const b64Admin = admin.toString('base64');

let sd = fs.readFileSync('social-digest.php', 'utf8');

// Update version in header and constant
sd = sd.replace(/Version:\s*5\.7\.\d+/g, 'Version: 5.7.42');
sd = sd.replace(/define\('SOCIAL_DIGEST_VERSION',\s*'5\.7\.\d+'\);/g, "define('SOCIAL_DIGEST_VERSION', '5.7.42');");

// Consolidate redundant admin_footer hooks if present
const oldFooterHooks = `add_action('admin_footer', function() {
    $screen = function_exists('get_current_screen') ? get_current_screen() : null;
    if ($screen && strpos($screen->id, 'social-digest') !== false) {
        social_digest_render_smart_theme_script();
    }
});
add_action('admin_footer', function() {
    $screen = function_exists('get_current_screen') ? get_current_screen() : null;
    if ($screen && strpos($screen->id, 'social-digest') !== false) {
        social_digest_render_smart_deep_links_script();
    }
});`;

const newFooterHook = `add_action('admin_footer', function() {
    $screen = function_exists('get_current_screen') ? get_current_screen() : null;
    if ($screen && strpos($screen->id, 'social-digest') !== false) {
        social_digest_render_smart_theme_script();
        social_digest_render_smart_deep_links_script();
    }
});`;

if (sd.includes(oldFooterHooks)) {
  sd = sd.replace(oldFooterHooks, newFooterHook);
}

// Find Section 4
const sec4Index = sd.indexOf('// ==========================================\n// 4. CORE MODULE LOADERS');
if (sec4Index === -1) {
  throw new Error('Section 4 not found');
}

const beforeSec4 = sd.substring(0, sec4Index);

const newSec4 = `// ==========================================
// 4. CORE MODULE LOADERS & SELF-HEALING FALLBACKS
// ==========================================

$includes_path = SOCIAL_DIGEST_PATH . 'includes/';
$module_files  = [
    'helpers'      => $includes_path . 'helpers.php',
    'api-clients'  => $includes_path . 'api-clients.php',
    'feed-builder' => $includes_path . 'feed-builder.php',
    'admin'        => $includes_path . 'admin.php',
];

$module_hashes = [
    'helpers'      => '${hashHelpers}',
    'api-clients'  => '${hashApiClients}',
    'feed-builder' => '${hashFeedBuilder}',
    'admin'        => '${hashAdmin}',
];

$outdated_modules = [];
foreach ($module_files as $key => $file) {
    if (!file_exists($file)) {
        $outdated_modules[] = $key;
    } elseif (is_readable($file) && isset($module_hashes[$key])) {
        if (md5_file($file) !== $module_hashes[$key]) {
            $outdated_modules[] = $key;
        }
    }
}

// Self-healing check: If the user updated only social-digest.php and the /includes/ directory
// is missing or outdated on disk, automatically provision or update the modular files.
if (!empty($outdated_modules)) {
    if (!file_exists($includes_path)) {
        if (function_exists('wp_mkdir_p')) {
            wp_mkdir_p($includes_path);
        } else {
            @mkdir($includes_path, 0755, true);
        }
    }

    if (is_dir($includes_path) && is_writable($includes_path)) {
        $embedded_modules = social_digest_get_embedded_modules();
        foreach ($outdated_modules as $mod_key) {
            if (isset($embedded_modules[$mod_key]) && (!file_exists($module_files[$mod_key]) || is_writable($module_files[$mod_key]))) {
                @file_put_contents($module_files[$mod_key], $embedded_modules[$mod_key]);
            }
        }
    }
}

// Load modules with graceful execution
if (file_exists($module_files['helpers'])) {
    require_once $module_files['helpers'];
}
if (file_exists($module_files['api-clients'])) {
    require_once $module_files['api-clients'];
}
if (file_exists($module_files['feed-builder'])) {
    require_once $module_files['feed-builder'];
}
if (file_exists($module_files['admin'])) {
    require_once $module_files['admin'];
}

// Safety check: if modules could neither be found nor provisioned to disk (read-only filesystem without /includes/)
if (!function_exists(__NAMESPACE__ . '\\\\social_workbench_state')) {
    add_action('admin_notices', function() {
        if (!current_user_can('manage_options')) return;
        echo '<div class=\"notice notice-error is-dismissible\"><p><strong>Social Digest Error:</strong> The required <code>/includes/</code> folder is missing or cannot be created because the filesystem is read-only. Please upload the complete Social Digest plugin package to <code>' . esc_html(SOCIAL_DIGEST_PATH) . '</code> or grant write permissions to the plugin directory.</p></div>';
    });
}

/**
 * Embedded module source data for self-healing automatic directory provisioning.
 */
function social_digest_get_embedded_modules() {
    static $modules = null;
    if ($modules !== null) {
        return $modules;
    }
    $modules = [
        'helpers'      => base64_decode('${b64Helpers}'),
        'api-clients'  => base64_decode('${b64ApiClients}'),
        'feed-builder' => base64_decode('${b64FeedBuilder}'),
        'admin'        => base64_decode('${b64Admin}'),
    ];
    return $modules;
}
`;

const updatedContent = beforeSec4 + newSec4;
fs.writeFileSync('social-digest.php', updatedContent, 'utf8');
console.log('social-digest.php written successfully for v5.7.42. Size:', fs.statSync('social-digest.php').size);

// Validate with php-parser
const parser = new phpParser.Engine({
  parser: { extractDoc: false, php7: true },
  ast: { withPositions: true }
});

try {
  parser.parseCode(updatedContent, 'social-digest.php');
  console.log('AST syntax validation PASSED for social-digest.php! No syntax errors.');
} catch(err) {
  console.error('AST syntax validation FAILED:', err.message);
  process.exit(1);
}
