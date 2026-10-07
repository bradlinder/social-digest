=== Social Digest ===
Contributors: BradLinder, wp-plugin-studio
Tags: bluesky, mastodon, digest, social media, curation, automation, staging, webp
Requires at least: 6.0
Tested up to: 7.1.1
Requires PHP: 7.4
Stable tag: 5.8.22
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Automated digest builder for Bluesky and Mastodon with tabbed admin workflows, interactive next-run workbench, dry-run preview, stock media sideloading, and local asset caching.

== Description ==
Social Digest is a WordPress plugin that automates the aggregation and publication of your decentralized social updates from Bluesky (AT Protocol) and Mastodon (ActivityPub) into publication-ready WordPress digest articles.

== Changelog ==

= 5.8.22 =
* In-Flow Expandable Scheduling Drawer (Drop-Down Method):
  1. Expandable Drop-Down Drawer: Replaced the absolute floating popover with an in-flow expandable drop-down drawer directly inside the Prepare Next Digest Run workbench box.
  2. Immune to Element Obstruction: Because the drawer expands in normal document flow, the postbox naturally expands in height and pushes the Articles for Next Run box and all lower elements downward, making it physically impossible for any lower element to cover or obscure it.
  3. Responsive Controls & Timezone Details: Features date/time picker, site timezone context, quick-offset buttons (+1 Hour, Tomorrow 9am), explicit confirmation, and cancel/close controls that wrap gracefully across mobile and desktop screens.
  4. Clean State & Keyboard Dismissal: Toggles cleanly on the "Schedule for Later" button, auto-focuses the datetime input on open, and can be instantly dismissed via Cancel, Close, or the Escape key.

= 5.8.21 =
* Scheduling Popover Stacking Context & Z-Index Fix:
  1. Stacking Context Elevation: Added explicit positioned stacking context and z-index elevation to the Prepare Next Digest Run workbench box (`#social_wb_box_quick_actions`) so the popover dropdown renders cleanly over sibling postbox cards instead of being obscured.
  2. Dynamic Z-Index Activation: Dynamically elevates the container's z-index to 1000 whenever the scheduling popover is open, resetting gracefully upon outside click or Escape key dismissal.
  3. Seamless Overflow Rendering: Ensured `.inside` container styles preserve `overflow: visible` so popover borders, datetime controls, and confirmation buttons remain fully accessible.

= 5.8.20 =
* Workbench Toolbar Streamlining & Text-Only Button Clean-up:
  1. Horizontal Publication Layout: Positioned WordPress post publication actions (Save as Draft, Schedule for Later, Publish Immediately) inline alongside the preview and reset buttons rather than in a separate container beneath them.
  2. Clean Text-Only Buttons: Removed all dashicons and graphical icons from the workbench buttons, returning to clean, native text labels across all actions.
  3. Reduced Toolbar Clutter: Removed redundant descriptive helper text above the action row to keep the workbench compact and focused.
  4. Project Rule Enforcement: Added a permanent engineering directive in AGENTS.md and GEMINI.md never to introduce unsolicited icons or graphics to buttons and UI controls.

= 5.8.19 =
* WordPress Publication Action Bar Harmonization & Interactive Scheduling Popover:
  1. Harmonized Post Creation Toolbar: Cleanly separated staging actions (Fetch, Save Staged Changes, Discard/Reset) from actual WordPress publication actions (Save as Draft, Schedule for Later, Publish Immediately) into a dedicated, visually unified action container.
  2. Interactive Scheduling Popover: Replaced the permanently visible inline datetime picker with a clean, toggleable popover flyout. Displays active WordPress site timezone, date/time picker, quick-offset shortcuts (+1 Hour, Tomorrow 9am), explicit confirmation, and automatic click-away/Escape dismissal.
  3. Consistent Button Weights & Visual Alignment: Standardized button heights, alignment, and dashicons across all three publication actions, eliminating awkward wrapping and visual clutter.

= 5.8.18 =
* Architectural Modularization, Dedicated Domain Submodules & Frontend Asset Decoupling:
  1. Helper Utilities Domain Decomposition: Split the monolithic `helpers.php` (previously ~1,700 lines) into dedicated, single-responsibility submodules: `cards.php` (OpenGraph scraping and link card builders), `tags.php` (custom title override rules, camel-case splitting, frequency weighting, and tag ranking), and `media.php` (image sideloading, MIME validation, srcset generation, and duplicate avatar cleanup), reducing `helpers.php` to a clean, focused general utility module.
  2. Frontend Asset Extraction & Cache-Busting Enqueuing: Decoupled ~500 lines of inline styles and scripts from `social-digest.php` into static, dedicated asset files: `assets/css/embed.css` (native card styling, responsive layout, and dark mode rules), `assets/js/smart-theme.js` (dynamic theme luminance and theme change adapter), `assets/js/deep-links.js` (mobile app deep-linking), and `assets/js/gallery-lightbox.js` (gallery link harmonization). All assets are now enqueued via standard `wp_enqueue_style` and `wp_enqueue_script` hooks with automatic version-based cache busting.
  3. Dynamic AST Syntax Verification: Upgraded `scripts/verify-all.cjs` to dynamically discover and validate all PHP files in root and `includes/`, guaranteeing zero AST syntax errors across all existing and new submodules.
  4. Streamlined Preview Hub & Automatic Changelog Parser: Replaced ~1,000 lines of manually duplicated changelog arrays in `src/changelogData.ts` with an automated runtime parser that extracts release history directly from `readme.txt`, ensuring 100% sync fidelity and saving tokens on every future point release.

= 5.8.17 =
* Scheduled Publishing from Actions & Preview & Drag-and-Drop Manual Post Reordering:
  1. Scheduled Publishing from Actions & Preview: Added full future-publication scheduling directly from the Actions & Preview Workbench (in addition to "Save as Draft" and "Publish Immediately"). Includes a site-timezone datetime picker, WordPress `future` post status handling, automated cron scheduling (`publish_future_post`), and sideloaded media/featured thumbnail preservation.
  2. Drag-and-Drop Manual Post Reordering: Added interactive jQuery UI drag-and-drop sequencing for candidate update cards on the Actions & Preview page. Features dedicated grab handles with live dynamic ordering badges (`#1`, `#2`, etc.) that preserve custom editorial story sequences when saving, scheduling, drafting, or publishing digests.
  3. Seamless Cutoff & Staging State Integrity: Preserves scheduled date/time selections in workbench state, accurately advances cutoff timestamps upon future post creation to prevent duplicate ingestion, and provides direct "Edit Scheduled Post in WordPress" quick links in admin notices.

= 5.8.16 =
* Custom Lightbox Removal, Responsive Lightbox & Gallery Integration & Native WordPress Lightbox Fallback:
  1. Complete Removal of Custom Lightbox Code: Removed all custom modal JavaScript, custom modal overlay DOM elements, custom touch/keyboard listeners, and inline modal styling. Eliminates conflicts with site lightbox plugins and resolves the issue where gallery clicks failed to respond on mobile Android Chrome.
  2. Native Respect for Responsive Lightbox & Gallery: Gallery and update images in Social Digest posts are rendered with standard semantic links, unique gallery keys (`data-rel="lightbox-gallery-..."`), and `rl-gallery-link` classes. When Responsive Lightbox & Gallery (or any third-party lightbox plugin) is installed, Social Digest defers to it completely, ensuring images open with the exact same effects and configurations (Swipebox, prettyPhoto, Fancybox, etc.) as all other galleries on your website.
  3. Native WordPress Lightbox Fallback: If no third-party lightbox plugin is installed on the site, Social Digest automatically falls back to the native WordPress lightbox (Interactivity API, WP 6.4+), enqueuing core image lightbox view scripts and registering the native WordPress overlay in the footer.
  4. Direct File Fallback for Classic Environments: If neither a lightbox plugin nor native WordPress lightbox is present (e.g. older WordPress versions), image clicks gracefully navigate to the full-resolution image file.
* Responsive Lightbox & Gallery Conditional Loading Bypass & Direct prettyPhoto Binding:
  1. Responsive Lightbox Conditional Loading Bypass: When Responsive Lightbox (dFactory) has "Conditional Loading" active, it checks raw post_content for image links and skips enqueuing its scripts on posts with custom blocks or unlinked images. Social Digest now hooks at priority 5 and 20 in wp_enqueue_scripts to ensure Responsive Lightbox's assets (including prettyPhoto/Swipebox, CSS, and rlArgs) are never suppressed on digest posts.
  2. Direct prettyPhoto and Swipebox Initialization: Enhanced frontend script to directly bind prettyPhoto and Swipebox with exact Responsive Lightbox attributes (rl-gallery-link, data-rl_title, data-rl_caption) and re-trigger doResponsiveLightbox, guaranteeing the site's native lightbox handles all clicks rather than any custom fallback modal.

= 5.8.14 =
* Responsive Lightbox Restoration, Semantic Figure Anchors & Event Re-Triggering:
  1. Semantic Figure Anchor Wrapping: Restructured gallery image rendering to nest the clickable lightbox anchor directly inside the semantic `<figure>` tag around the `<img>` element (`<figure><a ...><img .../></a></figure>`). Resolves an issue where block-level `<figure>` elements placed inside inline `<a>` tags could be stripped or broken by WordPress KSES or block content filters.
  2. Native Responsive Lightbox Event Re-Triggering: Explicitly triggers Responsive Lightbox (dFactory)'s `doResponsiveLightbox` event upon retroactive image discovery and page initialization. Ensures existing site-wide lightboxes (including Swipebox) immediately bind to and handle all gallery images without relying solely on initial document-ready hooks.
  3. Sideloaded Full-Resolution URL Harmonization: Synchronized anchor `href` replacements during media sideloading to map both thumbnail and full-size remote CDN variants to local WordPress Media Library attachments.
  4. Backward Compatibility for Legacy Posts: Injects zoom-enabled lightbox links inside `<figure>` containers for older or previously published digest articles where images were saved unlinked.

= 5.8.13 =
* Retroactive Lightbox Auto-Discovery & High-Resolution Image Upgrades:
  1. Universal Retroactive Lightbox for Legacy Posts: Enhanced the frontend lightbox script to automatically scan for unlinked gallery images (`.social-embed-images img`, `.social-embed-media img`) within all social digest cards. Automatically enhances embedded images from historical or previously published digest articles created before v5.8.1, enabling full modal viewing, zoom cursor styling, and swipe/keyboard navigation without having to re-publish or edit old articles.
  2. Automatic High-Resolution Source Discovery: Resolves the highest-resolution image source available from responsive `srcset` and `currentSrc` attributes, ensuring readers view crisp full-size photos in the modal instead of downscaled thumbnail previews.
  3. Per-Post Gallery Grouping: Automatically links multi-image gallery sets per social card so readers can browse multi-photo updates with arrows and swipe gestures.

= 5.8.12 =
* Clean Modern Social Post Card Styling:
  1. Left Accent Border Removal: Removed the 4px colored accent border (`#0284c7` / `#38bdf8`) from the left side of social post cards in published WordPress posts, giving entries a clean, balanced, and uniform 1px border on all sides.
  2. Drop Shadow Removal: Removed the card drop shadow (`box-shadow: none`) so social post entries blend seamlessly and natively into any WordPress theme or layout without heavy glowing or dark drop-shadow edges.

= 5.8.11 =
* Dual Save Settings Buttons & Poststuff Container Markup Repair:
  1. Dual Save Settings Buttons: Added a matching primary "Save Settings" button directly to the top bar of the Settings tab alongside the drag-and-drop indicator, allowing instant saves from both the top and bottom of the page without scrolling.
  2. Poststuff Container DOM Repair: Properly closed WordPress `#poststuff` and `.metabox-holder` wrapper elements before rendering the bottom submit button to ensure consistent visibility across all viewport heights.

= 5.8.10 =
* Case-Sensitive Tag Deduplication & Pure User-Managed Override Rules:
  1. Case-Sensitive Deduplication: Updated the deduplication engine (`social_dedupe_override_rules` in PHP and `sdDedupeLines` in admin JavaScript) to be strictly case-sensitive. This allows administrators to preserve distinct casing rules (e.g. differentiating between `XPS*` and `xps*` or specialized acronyms) while cleanly eliminating exact duplicate entries.
  2. Complete Elimination of Baseline Injections: Fully removed hardcoded baseline list injections and forced mergers so the Custom Tag Title Overrides box contains solely user-entered rules.

= 5.8.9 =
* Line Break Normalization, Snapshot Corruption Auto-Repair & Wildcard Reference Clarification:
  1. Automatic Line Break Normalization & Repair: Resolved an issue where escaped carriage return / newline delimiters (`\r\n`) in snapshots or form submissions could degrade into literal `rn` text strings. Added automatic self-healing newline reconstruction (`social_normalize_overrides_newlines` and `sdCleanOverrideString`) across both PHP and client JavaScript to seamlessly repair corrupted backups and restore clean, one-rule-per-line formatting.
  2. Duplicate Rule Filtering: Automatically deduplicates repeated adjacent rules during baseline merging.
  3. Wildcard Notation Reference: Documented the purpose of the asterisk (`*`) wildcard prefix (e.g. `MINISFORUM*`, `XPS*`) which enables automatic model-number splitting for uppercase acronyms and brand families.

= 5.8.8 =
* Zero-Hidden-Rules Architecture, User Customizations Preservation & Snapshot Resilience:
  1. Complete Baseline Exposure Without Hidden Rules: Fully exposed all 33 built-in technology brand rules (Adreno, Chromebook, Coreboot, Dell XPS, Dimensity, E-ink, Elite Mini PC, F-Droid, MediaTek, Nintendo Switch, Raspberry Pi, Samsung Galaxy Tab, Snapdragon X, Steam Deck, ThinkBook, U-Boot, XPS, etc.) directly in the Custom Tag Title Overrides box. Zero terms are hidden in internal fallbacks.
  2. Absolute Preservation of User Customizations: Guaranteed that custom tag title overrides entered by administrators are never removed, overwritten, or cleared during version upgrades or settings saves. Existing custom rules are prioritized and cleanly merged with baseline rules during migration.
  3. Multi-Tier Snapshot Persistence & Recovery: Hardened options sanitization scope in settings saving to ensure backup snapshots are never cleared by form submissions. Added standalone redundant database backup storage (`social_digest_overrides_snapshot`) and automatic browser `localStorage` recovery so snapshots remain restorable across updates.
  4. Real-Time Active Line Count & Text-Only Formatting Hints: Added immediate active rule counter initialization on page load and verified text-only hashtag shorthand notes under Hashtag Auto-Tagging.

= 5.8.7 =
* Automatic Built-In Baseline Terms Population & Existing Install Migration:
  1. Resolved Baseline Rules Display on Existing Installs: Fixed an option presence condition in the administrative settings view where sites with an existing, previously empty `title_tag_custom_overrides` database setting failed to render the default rules.
  2. Automatic Auto-Population: If the custom overrides setting is empty, the textarea now automatically pre-populates all 28 built-in technology brand rules (Chromebook, Coreboot, Dell XPS, Dimensity, Elite Mini PC, F-Droid, MediaTek, Nintendo Switch, Raspberry Pi, Steam Deck, etc.) making them immediately visible, searchable, sortable, and editable.
  3. Automatic Database Upgrade Migration: Added an automatic migration during `admin_init` that populates previously empty override options directly into the WordPress options table.

= 5.8.6 =
* Social Hashtag Underscore & Hyphen Shorthand Convention:
  1. Single Underscore as Space: Single underscores in hashtags are now automatically interpreted as spaces in generated digest titles, taxonomy tags, and slugs (e.g. `#AI_PC` -> `AI PC`, `#Mini_PC` -> `Mini PC`), without requiring custom override rules.
  2. Double Underscore as Hyphen: Consecutive underscores are interpreted as hyphens (e.g. `#Wi__Fi` -> `Wi-Fi`, `#F__Droid` -> `F-Droid`), enabling full punctuation expression across platforms that only allow underscores in hashtags.
  3. Text-Only Admin Reference Hint: Added an unobtrusive, text-only formatting note directly under "Hashtag Auto-Tagging" in Settings explaining the single-underscore and double-underscore conventions.

= 5.8.5 =
* On-Site Tag Overrides Backup & Restore Snapshot Tool:
  1. Instant On-Site Backup Snapshots: Added a dedicated "Backup Snapshot" button positioned directly beside the Export and Import actions in the Custom Tag Title Overrides tool. Captures an immediate, on-site backup of all active rules, active rule counts, and timestamp.
  2. Direct Database & Local Fallback Persistence: Snapshots are committed immediately to the WordPress database (`social_digest_options['overrides_snapshot']`) via an administrative AJAX endpoint (`wp_ajax_sd_save_snapshot`) with nonce verification and capability protection, with local staging fallback on standard settings save.
  3. One-Click Safe Restore: Added a "Restore Snapshot" button that displays the exact backup timestamp and rule count in its tooltip, prompts for user confirmation, replaces the textarea contents, updates line counts, and triggers reactive change events.
  4. Dynamic Status & Indicator Display: Displays live visual confirmations when snapshots are captured or restored, alongside an inline indicator showing the last saved backup time and rule count.

= 5.8.4 =
* Streamlined Tag Overrides Engine, Built-In Baseline Visibility, Search & Sorting:
  1. Retired Dynamic Vocabulary Database Scraper: Removed automated post title and taxonomy database scanning to eliminate self-referential automated headline loops, database queries, and background memory spikes.
  2. Fully Visible & Editable Built-In Baseline Terms: All 28+ built-in tech brand terms (Chromebook, Coreboot, Dell XPS, Dimensity, Elite Mini PC, F-Droid, MediaTek, Nintendo Switch, Raspberry Pi, Steam Deck, etc.) are now pre-populated directly into the Custom Tag Title Overrides box where they are completely visible, editable, and customizable by publishers.
  3. Real-Time Search & Match Navigation: Added a lightweight, zero-dependency search bar above the tag overrides textarea. Highlights matches in real time, scrolls directly to matching rules, and supports Enter/Next match navigation with counter display (e.g. 1/3).
  4. Alphabetical & Recency Sorting: Added one-click sort actions including "Sort A→Z", "Sort Z→A", "Reverse Order" (to view/invert by recency), and "Reset Baseline" to restore default rules at any time.

= 5.8.3 =
* Tag Overrides Export/Import Restoration, Vocabulary Export & Syntax Guide:
  1. Restored Export & Import Functionality: Resolved a fatal JavaScript string syntax error caused by unescaped multiline confirmations in the settings page. Both the "Export (.txt)" and "Import (.txt)" tools now work reliably across all modern browsers.
  2. Dedicated Site Vocabulary Export: Added an "Export Vocabulary (.txt)" button in the Site Vocabulary Engine box. Publishers can export their indexed brand and product terms into an independent text file without inflating or polluting their editorial tag overrides.
  3. Comprehensive Override Syntax Guide: Added a clear formatting reference guide directly beneath the Custom Tag Title Overrides box, documenting exact mappings (`rawtag=Formatted Title`), wildcard prefixes (`PREFIX*` and `PREFIX*=Formatted Prefix`), exact brand casing (`BRAND*`, `BrandName`), and comma/line-break separation rules.
  4. Comment Preservation & Form State Detection: Exported files now include clean informational headers (`# Social Digest Export`), the import parser automatically filters out comments, and importing dynamically triggers form change detection with inline success confirmations.

= 5.8.2 =
* Master Avatar Deduplication, Unattached Parenting & Duplicate Cleanup Tool:
  1. Persistent Master Avatar Deduplication: Ingested author avatars are downloaded and cached once into the WordPress Media Library, then reused across all future digest editions. A persistent account-keyed cache (`social_digest_avatar_cache`) verifies existing attachment validity and remote image URLs, completely eliminating redundant duplicate avatar downloads.
  2. Unattached Media Attachment Architecture: Configured sideloaded avatar attachments with `post_parent = 0` (unattached). Because avatars represent global author identities rather than single-edition content, unattached parenting ensures that deleting, trashing, or drafting individual digest posts will never delete, break, or orphan shared author avatars.
  3. Media Library Duplicate Avatar Cleanup Tool: Added an administrative maintenance tool under Settings > Media Optimization > Avatar Storage & Hygiene. Scans the Media Library for legacy duplicate avatar attachments, identifies the latest version as the unattached master, updates all published digest post references in the database to the master URL, and permanently deletes redundant duplicate files and attachments from disk.

= 5.8.1 =
* Gallery Image Lightbox & Responsive Lightbox Integration:
  1. On-Site Lightbox Integration: Embedded gallery and update images now open in an on-site lightbox modal, allowing visitors to inspect full-resolution photos without disrupting their reading experience or navigating away from your website.
  2. Native Responsive Lightbox Compatibility: Automatically delegates to your site's existing lightbox plugin (including Responsive Lightbox by dFactory, Swipebox, prettyPhoto, Fancybox, and WordPress core lightboxes). Images in the same social update share unique gallery group keys (`data-rel="lightbox-gallery-..."`) so readers can swipe and arrow-navigate between multi-image sets.
  3. Standalone Zero-Dependency Fallback Viewer: If no third-party lightbox plugin is installed, Social Digest activates a lightweight (~1.5 KB), pure vanilla JS modal viewer complete with full-screen dark backdrop, ALT captions, image counters (e.g. 2 / 4), keyboard navigation (`ESC`, arrow keys), and a discreet "View Post ↗" link.
  4. Configurable Click Action Setting: Added a "Gallery Image Click" selector under Settings > Media Optimization offering Lightbox (default), Direct Image File, Original Social Post (legacy), or None (unlinked).
  5. Sideloaded Media Link Harmonization: When images are cached into the local Media Library, both the `<img>` source and the surrounding lightbox anchor `href` are seamlessly synchronized to the local high-resolution attachment URL.

= 5.8 =
* Unified Clickable Social Snippet Cards:
  1. Full Snippet Clickability: Made the entire preview card area clickable to open the linked article in a new tab. Clicking anywhere in the snippet—the preview image thumbnail, the headline title, the snippet description text, or the source domain link—now seamlessly navigates readers to the destination page.
  2. Native HTML5 & WordPress KSES / wpautop Protection: Restructured the social link card from nested block `<div>` elements into a semantic `<a class="social-link-card">` wrapper containing inline `<span style="display:block">` elements. This completely prevents WordPress filters (`wpautop`, `force_balance_tags`, `balanceTags`, and KSES) from prematurely closing anchor tags after images or splitting card paragraphs.
  3. Interactive Micro-Interactions & Hover Polish: Added subtle elevation, card border transitions, and headline title underline/accent color shifts on hover, matching the intuitive feel of native Bluesky social cards.
  4. Isolated Social Post Body: Cleanly preserved the social update text above the snippet widget in its own container so clicks on post commentary remain non-navigating.

= 5.7.53 =
* Configurable Default State for Unified Thread Cards:
  1. Default Thread Card State Option: Added an admin setting under Feed Rules (`thread_default_state`) allowing publishers to choose whether unified multi-post author thread cards render collapsed or expanded by default.
  2. Native HTML5 `<details open>` Markup: When set to "Expanded by default", thread cards render with the native HTML5 `open` attribute so all follow-up replies are immediately visible on page load while retaining the interactive `<summary>` toggle for readers to collapse. When set to "Collapsed by default", follow-up replies remain neatly tucked behind the clickable "🧵 View full thread" toggle.
  3. Dynamic Summary Labels: Automatically adjusts the summary heading between "🧵 Full thread" when expanded and "🧵 View full thread" when collapsed, with the total follow-up post count clearly displayed.

= 5.7.52 =
* Descriptive Media Naming & Pre-Publication Asset Attachment Order:
  1. Contextual Media Labels & File Names: Replaced generic "Digest media asset" attachment labels and random hash file names with descriptive titles and filenames derived from post hashtags, author names, or link preview cards. Sideloaded media files now use human-readable slugs (e.g. `onyx-boox-picco-1.jpeg`, `pine-64.png`, `f-droid.png`, `avatar-bradlinder.png`) and clean Media Library labels (e.g. `Onyx BOOX Picco (1)`).
  2. Pre-Publication Featured Image & Media Attachment: Fixed an issue where digest posts were published to WordPress prior to sideloading media and attaching featured images. Posts are now initially staged as drafts while content media and featured images are sideloaded and attached, transitioning to published status only after all assets and responsive srcsets are complete. This guarantees feed readers, RSS subscribers, and syndication webhooks receive fully formed articles with their thumbnails on the very first publish notification.

= 5.7.51 =
* Optional Same-Day Suffix Disabling & Descriptive Language:
  1. Blank Disabling Behavior: Ensured that leaving the Same-Day Suffix setting (`same_day_suffix_tpl`) blank or empty completely disables same-day title suffixes, allowing multiple digests published on the same calendar day to retain their original base titles without automatic numbering.
  2. Enhanced Variable Support & Spacing: Supported `{part}`, `{count}`, and `{number}` placeholders with clean spacing preservation.
  3. Descriptive Admin Copy: Updated the settings description in the admin UI to clearly indicate that leaving the field blank disables the suffix, and documented the available template variables and formatting examples.

= 5.7.50 =
* Ingestion Fetch Order Implementation & Sequential Backlog Traversal:
  1. Active Ingestion Ordering: Connected the "Ingestion Fetch Order" setting (`fetch_order`) into the candidate slicing engine in `feed-builder.php`. When set to "Oldest Posts First", candidate articles are sorted forward in chronological order prior to enforcing the maximum post limit (`max_posts`).
  2. Bounded Backlog Progression: When ingesting oldest posts first, updated the recorded cutoffs to the maximum timestamp among the ingested batch rather than the newest API entry, allowing administrators with large post backlogs to process sequential digest editions forward in time without skipping unimported posts.

= 5.7.49 =
* Postbox Collapse Double-Toggle Fix, Orphaned Settings Cleanup & Media Caching Activation:
  1. Postbox Toggle Arrow Event Isolation: Fixed an issue where clicking the handlediv arrow button failed to minimize/collapse postbox widgets because click events bubbled up to the widget header bar and triggered a second immediate toggle. Isolated the handlediv click event with `e.stopPropagation()` and added defensive CSS `:not(.postbox-header)` visibility rules.
  2. Local Asset Caching & Responsive Srcset Activation: Wired the previously inactive "Cache remote avatars & cards locally" and "Generate standard responsive srcset sizes" settings into the publication and media sideloading pipeline. Remote avatars and link card preview thumbnails are now cached locally in the Media Library, and sideloaded images receive responsive srcset and sizes attributes.
  3. Orphaned Settings Cleanup: Removed obsolete legacy settings (`enable_staging_queue` and `excerpt_fold_limit`) from options registration and sanitization.
  4. Options Schema Harmonization: Added all active admin settings to the plugin's default options registry to guarantee robust initialization and defense against omitted values.

= 5.7.48 =
* Restored Admin Postbox Widget Expand, Collapse, and Drag-and-Drop Reordering:
  1. Script Enqueue Hook Resolution: Fixed an issue where `postbox` and `jquery-ui-sortable` scripts were only enqueued on `settings_page_social-digest-settings`, leaving them unqueued when visiting via the primary Posts submenu (`posts_page_social-digest-settings`) or the Admin Bar shortcut.
  2. Toggle Indicator & Collapse Styles: Added explicit CSS rules for `.postbox .handlediv`, `.postbox .handlediv .toggle-indicator::before` (Dashicons up/down indicators), and `.postbox.closed .inside { display: none !important; }` so panels collapse cleanly.
  3. Interactive Drag & Click Handlers: Added unified click handlers on both the toggle arrows and the widget header bars, with interactive exclusion guards and grabbing drag handles.
  4. Local Storage State Persistence: Collapsed/expanded widget states and custom drag-and-drop sort orders are now remembered across page reloads.

= 5.7.47 =
* Granular Google Search Snippet Visibility Controls for Temporary Framing Overrides:
  1. Temporary Header Search Snippet Toggle: Added a settings toggle under "Article Framing & Lead-In / Footer Templates" allowing custom lead-in headers entered on the Actions workbench for a specific digest edition to be visible to Google Search snippets (by omitting the `data-nosnippet` attribute).
  2. Strict Fallback Protection: When the toggle is off, or when enabled but no custom header text is provided (or if the box is empty / whitespace only), default headers remain fully protected with `data-nosnippet` to avoid boilerplate snippet pollution.
  3. Independent Temporary Footer Snippet Toggle: Added an independent companion toggle for closing footer overrides on the Actions workbench, allowing publishers to selectively expose custom footers to search snippets while protecting standard closing boilerplate.
  4. Workbench Status Indicators: Added contextual visual badges directly on the Actions workbench framing panel so editors can immediately verify when search snippet visibility is active for temporary overrides.

= 5.7.46 =
* Character Entity Protection & Apostrophe Ingestion Fix:
  1. Resolved Apostrophe Mangling (`&;re` / `&;s` / `&;t`): Fixed an issue where numeric HTML character entities (such as `&#39;`, `&#039;`, and `&#8217;` for apostrophes) were mistakenly matched by hashtag stripping regexes (`/#.../`) because of the leading `#` symbol. Added negative lookbehind `(?<![&\w])` and non-digit hashtag pattern validation across all cleaner and extraction functions.
  2. Entity Pre-Decoding & Retroactive Repair: Added HTML entity pre-decoding on post bodies and automatic entity repair regexes (`(\w)&;(\w)` -> `$1'$2`) so any posts or candidate drafts with previously mangled contractions are seamlessly restored to clean apostrophes (`they're`, `it's`, `don't`).
  3. Immediate Cutoff State Locking: Moved cutoff timestamp advancement and workbench state cleanup to execute immediately upon post/draft insertion in WordPress, ensuring cutoffs advance even if subsequent media sideloading tasks are delayed.

= 5.7.45 =
* Removal of Self-Healing Base64 Payloads & Clean Architecture Restitution:
  1. Base64 & Self-Healing Payload Purge: Permanently removed ~345 KB of embedded base64 module payloads, auto-provisioning filesystem writes, and MD5 verification blocks from `social-digest.php`.
  2. Massive Footprint Reduction: `social-digest.php` is now 91.3% smaller (reduced from 377.8 KB down to 32.0 KB), and the downloadable release zip is 52% smaller (reduced from 160.6 KB to 76.8 KB).
  3. Standard WordPress Module Architecture: Converted module loading to standard WordPress direct `require_once` statements with a clean admin notice if `includes/` is ever missing, eliminating memory parsing overhead and false-positive flags from host security scanners.

= 5.7.44 =
* Native Stock Image Ingestion & Elimination of Image Compression Settings:
  1. Removed Image Re-Compression & Quality Sliders: Completely removed the experimental WebP/AVIF format conversion options and compression quality sliders (`convert_modern_media`, `webp_quality`, and `avif_quality`), restoring clean, predictable stock media behavior.
  2. Native Stock Image Sideloading: The plugin now imports and attaches the stock original remote image file directly into the WordPress Media Library without invoking `WP_Image_Editor` or GD/Imagick re-encoding, permanently eliminating `Call to undefined method WP_Image_Editor_GD::get_output_mime_types()` and image driver discrepancies.
  3. Resilient Featured Image Sideloading: Wrapped intermediate attachment metadata generation and candidate thumbnail selection in defensive exception guards so digest publication always completes smoothly, even if third-party image plugins or server drivers encounter issues.

= 5.7.43 =
* Media Sideloading & Featured Image Resolution Hardening:
  1. Resolved Fatal Image Editor Error: Replaced non-existent method call `get_output_mime_types()` on `WP_Image_Editor_GD` with official WordPress core APIs (`$editor->supports_mime_type()` and `wp_image_editor_supports()`).
  2. Exception Safety & Fallback: Wrapped modern media conversions (WebP/AVIF) in a resilient `try/catch` block. If GD or Imagick encounters image driver limitations or unexpected formats, the plugin safely retains the original uploaded image file (JPEG/PNG) and attaches it as the featured image without interrupting digest publishing.

= 5.7.42 =
* Codebase Cleanup & Security Hardening:
  1. Elimination of In-Memory `eval()`: Permanently removed dynamic in-memory `eval()` execution from `social-digest.php`. Replaced with a graceful, actionable WordPress admin notice if the server filesystem is read-only, fully conforming with WordPress security guidelines and preventing flags from hosting security scanners.
  2. Removal of Orphaned Asset: Removed unused legacy stub `assets/js/social-digest-editor.js` left over from the v5.7.28 split editor migration.
  3. Consolidated Admin Footer Hooks: Merged redundant `admin_footer` action hooks in `social-digest.php` into a unified callback, reducing runtime overhead on administrative pages.

= 5.7.41 =
* Complete Distribution Packaging & Self-Healing Module Restitution:
  1. GitHub Release Workflow Distribution Fix: Corrected `.github/workflows/release.yml` to bundle the complete `/includes/` directory into the release distribution zip archive (`social-digest.zip`), ensuring all modules (`admin.php`, `helpers.php`, `feed-builder.php`, `api-clients.php`) are included directly in automated GitHub release downloads.
  2. Syntactically Hardened Self-Healing Core: Re-encoded embedded modular payloads into `social-digest.php` using strict, verified PHP AST syntax and pre-hashed MD5 signatures. If a user installs or updates only `social-digest.php`, the core file automatically provisions and updates `/includes/` on disk without crashes or missing components.

= 5.7.40 =
* Cutoff Synchronization, Featured Image Resilience & Universal Hashtag Parsing:
  1. Guaranteed Cutoff Advancement & Workbench Clearing: Enhanced `social_publish_workbench_run()` to advance cutoffs across all candidate timestamps and clear the workbench queue directly upon creation, ensuring subsequent scheduled cron runs never re-publish previously digested posts.
  2. Thumbnail Pool Sideloading Fallback: Upgraded featured image attachment to loop through candidate thumbnails and raw post markup if the primary image returns 404 or is CDN-restricted, ensuring every digest reliably receives a WordPress featured image.
  3. Universal Mastodon Hashtag Extraction: Added direct extraction from `<a class="hashtag">` tags, singular `/tag/` endpoints, and multi-span formats, ensuring 100% of Mastodon hashtags are preserved as WordPress tags.

= 5.7.39 =
* Critical Error Fix & Loader Modernization:
  1. Resolved Fatal Parse Error: Completely removed legacy self-healing eval loader and malformed base64 block in `social-digest.php` that contained unparseable syntax, causing an immediate PHP fatal error / WordPress critical crash on all PHP versions (including PHP 8.5).
  2. Streamlined Standard Module Loading: Replaced eval/md5 checks with direct, clean WordPress `require_once` module loading, eliminating runtime memory overhead and file lock conflicts.

= 5.7.38 =
* PHP Version Compatibility & Critical Error Prevention:
  1. PHP 7.0–8.4 Backward Compatibility: Replaced PHP 7.4+ arrow functions (`fn`) with standard PHP anonymous closures in candidate filtering and tag collection, ensuring 100% compatibility across legacy PHP 7.2/7.3/7.4 hosting environments and eliminating fatal parse/syntax errors.

= 5.7.37 =
* Robust Cutoff High-Water Mark & Feature Regression Hardening:
  1. Cutoff High-Water Mark & Scheduled Run Cutoff Advancement: Guaranteed that publishing and scheduled runs advance cutoffs to the absolute high-water mark of fetched feed responses, preventing previously curated updates from being re-fetched and republished.
  2. Mastodon Hashtag & Media Thumbnail Extraction: Hardened Mastodon status parsing to ensure hashtags from anchor links, spans, plain text, and API metadata are reliably harvested and assigned as WordPress post tags.
  3. Featured Image Resolution: Enhanced candidate thumbnail pools and dynamic fallback extraction to guarantee featured image attachment on all published digests.

= 5.7.36 =
* Robust Cutoff Advancement, Hashtag Ingestion & Underscore-to-Hyphen Formatting:
  1. Cutoff & Duplicate Prevention: Fixed cutoff advancement upon manual post publication and ensured scheduled cron runs (`social_run_digest_import()`) correctly verify that new candidates exist before attempting publication, eliminating duplicate posting of previously curated updates.
  2. Mastodon & Bluesky Full-Text Ingestion: Preserved raw unstripped post content (`full_text`) during API fetching, ensuring hashtag extraction successfully captures all hashtags (including underscore-formatted tags like `#E_ink` and `#F_Droid`) for WordPress tag assignment and post titles.
  3. Underscore-to-Hyphen Tag Conversion & Casing: Enhanced `social_split_camelcase_tag()` and baseline vocabulary dictionary to robustly treat underscores as hyphens and format compound brand names (e.g. `E-ink`, `F-Droid`).

= 5.7.35 =
* Taxonomy & Media Hardening: Guaranteed Post Tag Assignment & Featured Image Resolution:
  1. Unconditional WordPress Tag Assignment: Fixed an issue where WordPress core `wp_insert_post()` silently dropped the `tags_input` argument when executed under automated WP-Cron or unauthenticated background execution (due to internal `current_user_can('assign_terms')` checks). Added direct, unconditional `wp_set_post_tags()` and `wp_set_object_terms()` execution following post insertion.
  2. Robust Auto-Thumbnail Extraction: Fixed `auto_thumb` default setting fallback (`!isset($opts['auto_thumb']) || !empty($opts['auto_thumb'])`), preventing auto-thumbnail selection from being disabled on unmigrated option records.
  3. Dynamic Thumbnail & Tag Fallback: Added automatic fallback extraction for both featured images and taxonomy tags directly from candidate updates during publish/drafting, ensuring neither thumbnails nor tags are lost even if preview state was empty.
  4. Enhanced Bluesky Quote-Post Media: Added support for `app.bsky.embed.recordWithMedia` in Bluesky, properly extracting images, videos, and link cards attached to quoted posts.
  5. Sideloading Network Resilience: Added standard browser user-agent, accept headers, and automatic file extension detection (.png, .webp, .avif, .gif, .jpg) to `social_sideload_image_by_mime()`, preventing 403 Forbidden rejections from remote CDNs.

= 5.7.34 =
* Tagging & Title Formatting: Underscore-to-Hyphen Hashtag Conversion:
  1. Mastodon & ActivityPub Platform Adaptation: Mastodon and related social networks forbid hyphens in hashtags (breaking e.g. `#F-Droid` into `#F`), forcing authors to use underscores. The hashtag-to-title parser now automatically treats underscores as hyphens (`#F_Droid` -> `F-Droid`, `#E_ink` -> `E-ink`, `#Wi_Fi` -> `Wi-Fi`, `#U_Boot` -> `U-Boot`).
  2. Author Casing Preservation: Preserves the exact casing chosen across hyphen boundaries (e.g. `#E_ink` reliably converts to `E-ink` while `#F_Droid` converts to `F-Droid`).
  3. Custom Override & Prefix Rule Normalization: Custom tag override rules now match hyphenated and underscore variations interchangeably.

= 5.7.33 =
* Tagging & Title Formatting: Single-Letter Prefix CamelCase Splitting & Hyphenated Brand Recognition:
  1. Enhanced CamelCase splitting with `\b([A-Z])([A-Z][a-z]+)` to properly parse single-capital prefixes (e.g. `#FDroid` -> `F Droid`, `#GSuite` -> `G Suite`, `#XScreen` -> `X Screen`) without disrupting uppercase acronyms like `AI`, `PC`, or `OLED`.
  2. Native Hyphenated Brand Support: Added built-in precision formatting rules for hyphenated entities (`#FDroid`, `#fdroid`, and `#F_Droid` format cleanly as `F-Droid`; `#EInk` formats as `E-Ink`).
  3. Baseline Vocabulary Enrichment: Added `F-Droid`, `E-Ink`, `U-Boot`, and `Coreboot` to the plugin's baseline vocabulary dictionary for instant zero-configuration recognition.

= 5.7.32 =
* Storage Hygiene & Media Optimization: Granular WebP and AVIF Compression Quality Sliders:
  1. Added fine-grained image compression quality sliders (60-100%) for converted WebP and AVIF assets in Media Optimization & Storage Hygiene settings.
  2. Upgraded image sideloading and conversion to use WordPress image editor with user-defined WebP and AVIF quality settings.

= 5.7.31 =
* Tagging & Parsing: Mastodon & Bluesky CamelCase Preservation & Case-Aware Deduplication:
  1. Fixed an issue where Mastodon's REST/ActivityPub API normalized tag objects to lowercase (`triplescreenlaptop`), which previously shadowed and discarded original CamelCase hashtag casing from author post content (`#TripleScreenLaptop`).
  2. Enhanced Mastodon and Bluesky ingestion to extract CamelCase tags directly from post body HTML anchor links, span wrappers, and plain text before merging with API metadata.
  3. Added `social_dedupe_cased_tags()` to ensure CamelCase/uppercase casing is strictly preserved across thread unrolling, cross-network deduplication, candidate tag aggregation, and WordPress post title generation.

= 5.7.30 =
* Core & Distribution: Automated Module File-Sync & Self-Healing Version Integrity:
  1. Enhanced module bootstrapping to detect outdated `/includes/` files on disk (via MD5 signature comparison against the active version's embedded payload) and automatically synchronize/overwrite them with the latest release code.
  2. Ensures that updating or uploading `social-digest.php` immediately activates all new features (including separate Temporary Lead-In Header and Closing Footer controls) even on systems where disk permissions or caching previously prevented file replacements.
  3. Cleaned up legacy split plugin JavaScript assets and updated standalone deployment package.

= 5.7.29 =
* Tagging & Formatting: Full Hashtag Ingestion & Refined CamelCase/Acronym Word Splitting:
  1. Extended CamelCase and acronym word splitting to all imported WordPress post tags (e.g. `#AcerGooglebook14` -> `Acer Googlebook 14`, `#DellXPSGooglebook` -> `Dell XPS Googlebook`, `#Android17QPR2` -> `Android 17 QPR 2`, `#MediaTekDimensityCXC10Max` -> `MediaTek Dimensity CX C10 Max`).
  2. All hashtags from all included posts (such as `#Google`, `#Acer`, `#Dell`) are now retained as WordPress tags, while only the primary/first hashtag per post is considered for post title generation.
* UI & Workflow: Dedicated Header & Footer Override Fields with Quick Formatting Toolbar:
  1. Replaced the single split editor with two separate input fields in the Workbench for Temporary Lead-In Header (rich text via TinyMCE) and Temporary Closing Footer (HTML with 1-click Bold, Italic, Link, HR, and BR formatting buttons).
  2. Permanently eliminated split markers (`<!--digest_split-->`) from publishing flows.
  3. Added real-time auto-enabling for the temporary framing override checkbox whenever text is entered into either override field.
* UI & Settings Refactoring: Reorganized Settings Tab & Added Missing Settings Controls:
  1. Refactored the monolithic 'Publishing, Tags & Article Framing' section into 4 distinct, collapsible, drag-and-drop postboxes.
  2. Added dedicated controls for all previously hidden backend settings, including Ingestion Fetch Order (), Default Post Tags (), Hashtag Auto-Tagging Controls (, , , ), Same-Day Suffix Template (), and Article Framing HTML (, ).

= 5.7.27 =
* Formatting: Bold Bullet Sentence Divider:
  1. Updated the bullet-style sentence divider option for post excerpts to render in bold font (`<b>•</b>`).
* Excerpt Feature: Trailing Ellipsis Option:
  1. Added a setting (`excerpt_append_ellipsis`) to append `...` to the end of automatically generated post excerpts.
* Vocabulary Feature: Wildcard (*) Prefix Matching in Custom Overrides:
  1. Custom Tag Title Overrides now support wildcard prefix rules (`MINISFORUM*`, `GEEKOM*`, `NVIDIA*`, or `SnapdragonX*=Snapdragon X`).
  2. For example, `MINISFORUM*` automatically formats `#MINISFORUMS5` into `MINISFORUM S5` while preserving model designation formatting.

= 5.7.26 =
* Feature: Custom Tag Title Overrides Import & Export (.txt files):
  1. Export (.txt): Added one-click export button to save custom tag overrides as a local `custom-tag-overrides.txt` backup file.
  2. Import (.txt): Added file uploader allowing users to import or append custom tag override mappings from `.txt` or `.csv` files directly in the admin UI.
* Feature & Performance: Configurable Post Scan Depth & Warning Indicators for Vocabulary Engine:
  1. Configurable Post Scan Depth: Added a setting (`vocabulary_post_scan_limit`) with options ranging from 50 to 1,000 posts up to Full Site History.
  2. Performance Warning Callout: Displayed clear guidance and warning callouts when selecting large scan depths (>300 posts or Full Site) to inform users about transient memory and execution tradeoffs.

= 5.7.25 =
* Bug Fix: Galaxy Tab S-Series & Snapdragon X-Series Precision Formatting:
  1. Galaxy Tab S-Series Model Designation: Added explicit pattern matching for Samsung Galaxy Tab S-series model numbers (`#SamsungGalaxyTabS12`, `#samsunggalaxytabs12`, `#TabS12`) to strictly resolve as `Samsung Galaxy Tab S12` (or `Tab S12`) instead of misparsing `tabs` + digits as `Tabs 12`.
  2. Snapdragon X-Series Processor Designation: Updated Snapdragon X-series model resolution (`SnapdragonX2`, `snapdragonx2`) to output unified `Snapdragon X2` without inserting an extraneous space before the series number (`Snapdragon X 2`).
  3. Post-Processing Cleanup: Added regex safety guards in `social_split_camelcase_tag()` to prevent site taxonomy terms containing `Tabs` from corrupting model designation numbers.

= 5.7.24 =
* Bug Fix: CamelCase Hashtag Parsing & Fuzzy Duplicate Tag Elimination:
  1. CamelCase Preservation: Fixed hashtag parsing for model numbers like `#SamsungGalaxyTabS12` so CamelCase letter/number boundaries (`Tab S12`) are split correctly without being corrupted by unspaced baseline vocabulary matches (`Tabs 12`).
  2. Single Hashtag Per Social Post Enforcement: Fixed featured post tag double-counting during title candidate generation to ensure each post contributes at most 1 hashtag to the digest title.
  3. Fuzzy Topic Deduplication: Implemented `social_are_tags_fuzzy_duplicates()` to detect and eliminate duplicate or overlapping topics (e.g. `Samsung Galaxy Tab S12` vs `Samsung Galaxy Tabs 12` or `Samsung Galaxy Tab`) from appearing together in the WordPress post title.

= 5.7.23 =
* Feature & Architecture: Organic Site Vocabulary Learning Engine & Vocabulary Settings Controls:
  1. Organic Vocabulary Extraction: Replaced static dictionaries with a dynamic learning engine (`social_get_site_vocabulary_dictionary()`) that extracts brand names, product terms, and proper nouns directly from your local WordPress site's published post titles, tags, and categories.
  2. Transient Caching & Performance: Caches extracted site vocabulary terms in a high-performance WordPress transient (`social_digest_site_vocab_cache`), causing zero query overhead during social digest generation.
  3. Settings Controls: Added controls under Tag Formatting settings to toggle site vocabulary learning, configure scan frequency (`24 Hours`, `7 Days`, `30 Days`), view current indexed term count, and trigger manual "Re-index Site Vocabulary Now" operations.

= 5.7.22 =
* Feature & Architecture: Zero-Config Automated Title Tag Formatting Engine & Custom Tag Overrides:
  1. Multi-Layer Title Formatting: Combined CamelCase regexes (`[a-z][A-Z]`), letter-number boundary splitting (`[a-z][0-9]`), acronym preservation, and an expanded built-in tech dictionary for zero-config title tag generation.
  2. Optional Custom Tag Overrides Setting: Added a "Custom Tag Title Overrides" textarea in Settings (under Hashtag Formatting Rules) allowing power users to map raw tags to custom phrases (e.g. `rawtag=Formatted Name`).

= 5.7.18 =
* Fix & Enhancement: Auto-Refresh Stale Drafts, Topic Keyword Extraction & Complete Trailing URL Sanitation:
  1. Auto-Refresh Stale Workbench Drafts: Added version tracking to staged workbench drafts. Any existing draft saved on a previous version is automatically refreshed on page load, eliminating stale preview state and ensuring clean output immediately.
  2. Fallback Topic Keyword Extraction: When candidate posts do not contain explicit `#hashtags`, Social Digest now automatically extracts key topic words and proper nouns (such as `Samsung`, `Valve`, `Asus`, `Lenovo`, `Minimal Phone`) to populate `{hashtags}` in post titles.
  3. Clean Separator Sanitation: Enforced trimming of leading colons and separators (`: Liliputing News Roundup` -> `Liliputing News Roundup`) when title tags resolve to empty.
  4. Redundant Link Sanitation: Enhanced trailing URL stripping for posts with embedded preview cards to match scheme-less domain paths (`winfuture.de/...`, `9to5google.com/...`) and removed inline `style` attributes on `<a>` tags so `wp_kses_post()` processes rendered HTML without stripping or printing raw markup.

= 5.7.17 =
* Fix & Enhancement: Tokenized URL Replacement & Cross-Platform Hashtag Extraction:
  1. Tokenized URL Replacement: Resolved an issue where URLs in Bluesky body text converted from facets were re-matched by subsequent URL regex parsers, resulting in double-nested `href` attributes and raw `<a href="...">` code being displayed in post previews. Tokenized placeholder substitution ensures rendered HTML links remain clean and active.
  2. Mastodon Hashtag Extraction: Fixed Mastodon API tag harvesting so hashtags from Mastodon statuses (and API tag payloads) are populated into `extra_tags` before body text cleaning. Allows Mastodon hashtags to populate title templates and WordPress tags even when Bluesky posts contain no hashtags.

= 5.7.16 =
* Fix & Enhancement: Leading Separator Sanitation for Post Titles: Enhanced post title formatting to automatically trim leading punctuation (such as `: `, `- `, or `| `) when `{hashtags}` resolves to an empty string because no social posts in the batch contained hashtags and no default tags were set. Ensures templates like `{hashtags}: Liliputing News Roundup` render cleanly as `Liliputing News Roundup`.

= 5.7.15 =
* Feature: Customizable WordPress Post Title Input in Next-Run Workbench: Added an editable WordPress Post Title input box (`custom_title`) located prominently at the top of the "Actions & preview" (Next-Run Workbench) tab above social candidate posts. Populates with the auto-generated headline template by default, allowing users to freely edit or replace the title before saving, drafting, or publishing.

= 5.7.14 =
* Feature: Automated W3 Total Cache (W3TC) & Multi-Cache Purge Engine: Added explicit cache-purging integration (`social_purge_site_caches()`) for W3 Total Cache (`w3tc_flush_posts()` / `w3tc_pgcache_flush()`), WP Super Cache, WP Rocket, LiteSpeed Cache, WP Fastest Cache, SG Optimizer, and core WordPress post caches (`clean_post_cache`). Automatically purges post pages, homepage, archives, and RSS feeds whenever a digest is created or updated.

= 5.7.13 =
* UI/UX & Layout: WP Media Settings Integration for Multi-Image Galleries: Multi-image post galleries now dynamically query the site's default WordPress thumbnail dimensions (`get_option('thumbnail_size_w')` and `get_option('thumbnail_size_h')`). Renders gallery images as clean, thumbnail-sized inline blocks using the user's configured Media Settings (defaulting to 150x150), while single-image embeds preserve full 400px–450px max dimensions.

= 5.7.12 =
* UI/UX & Layout: Compact Thumbnail-Sized Media Galleries & Single Images: Optimized multi-image post galleries and single image/video embeds to render as compact, thumbnail-sized previews (`120px` to `220px` height constraints). Displays multi-image galleries in clean single-row grids (up to 4 thumbnails side by side), preventing individual social posts from taking up excessive vertical screen space in published digest articles.

= 5.7.11 =
* Feature: Configurable Title Hashtag Selection Strategy & Max Count: Added settings under Title Template to choose how hashtags are picked for WordPress post titles (First Hashtag in Post, Taxonomy Popularity/Frequency, or Random) and configurable max title tags (1 to 5 tags). Selecting the first hashtag ensures the leading tag is prioritized for the title while all harvested hashtags remain assigned to the post taxonomy.

= 5.7.10 =
* UI/UX & Design: Native Bluesky Embed Card Hierarchy: Realigned link preview card layout and typography with native Bluesky card design standards. Headlines are bold with refined, proportionate sizing (`14px`), card body descriptions use clean normal font weight (`400`, `12.5px`), and the source domain is positioned below the description at the bottom with a link indicator.

= 5.7.9 =
* UI/UX & Readability: Pure White High-Contrast Dark Mode Typography: Overhauled all dark mode text selectors to render in crisp, pure white (`#ffffff`) for post content, paragraphs, author titles, reply threads, and link preview descriptions, eliminating any hard-to-read dark gray or black text on dark backgrounds.
* Fix: Prevent False-Positive Dark Mode on Light Websites: Removed OS-level `prefers-color-scheme` overrides that forced social cards to display as dark boxes on white website backgrounds. Cards now match the website's actual visual canvas and stay in clean light mode unless a dark website theme is actively detected or explicitly forced.

= 5.7.8 =
* UI/UX & Readability: Enhanced Dark Mode Contrast & Typography: Overhauled the embedded native social card dark mode color system with crisp Slate-50 (#f8fafc) author titles, high-contrast Slate-100 (#f1f5f9) post copy, vibrant Sky-380 (#38bdf8) links and preview titles, and subtle Slate-800 card borders, significantly improving readability on dark backgrounds.
* Feature: Smart Website & Browser Theme Detection: Added dynamic theme intelligence that prioritizes the active website or theme color preference over conflicting system-level OS settings. Automatically inspects document root/body theme classes and computed background luminance (`getComputedStyle`), ensuring light-themed websites render light-mode cards even if the user's OS or browser is set to dark mode (and vice versa). Features live dynamic adaptation via `MutationObserver` when users toggle site dark mode switches.

= 5.7.7 =
* Architecture: Modular Codebase with Zero-Downtime Self-Healing: Restructured plugin into clean, dedicated modules (`includes/helpers.php`, `includes/api-clients.php`, `includes/feed-builder.php`, and `includes/admin.php`). Built-in self-healing provisioner in `social-digest.php` automatically creates the `/includes/` directory and restores module files on the fly if the plugin is installed or updated on a server that only received `social-digest.php`, guaranteeing zero downtime and complete backwards compatibility across all deployment workflows.
* Fix: TinyMCE Editor Splitter Inline Fallback: Added an inline TinyMCE plugin fallback in `admin_footer` to guarantee the "Insert Post Splitter" toolbar button functions properly even if the external JS asset is not present on disk.

= 5.7.6 =
* Hotfix: Emergency Standalone Consolidation: Consolidated core helper functions, API clients, feed builders, and admin UI views directly into `social-digest.php` to immediately eliminate fatal missing file errors (`require_once includes/helpers.php: Failed to open stream`) on environments performing single-file updates.

= 5.7.5 =
* Critical Fix: PHP 8.5 / PHP 8.1+ Compatibility & Outage Prevention: Resolved an uncaught `Fatal error: Class "SocialDigest\DateTimeImmutable" not found` in `social-digest.php`. The DateTime class in `social_reschedule_cron()` was missing the global root namespace backslash (`\DateTimeImmutable`), which caused PHP to fail looking within the plugin namespace during cron scheduling, settings saves, and activation.
* Fix: PHP 8.1+ TypeError Prevention on WordPress Filters: Added strict defensive type guards to `kses_allowed_protocols`, `plugin_action_links`, and TinyMCE editor button filters (`mce_buttons`, `mce_external_plugins`) to safely return empty arrays or original inputs instead of triggering fatal TypeErrors if a 3rd-party theme or plugin passes `null` or a non-array.
* Reliability: Query Filter Object Guards: Hardened `pre_get_posts` to verify query object existence and method availability before inspecting query vars, and immediately bypass queries if RSS-only mode is inactive.
* Hardening: Admin Bar Hook Defensive Typing: Removed strict object typehint on `admin_bar_menu` action hook to prevent fatal TypeErrors if an admin bar wrapper or null is dispatched.

= 5.7.4 =
* UX & Layout: Image Gallery Positioned Below Link Preview Cards: Reordered media embed rendering so that for posts containing both an article preview card and image galleries/video attachments, the image gallery renders below the link preview card rather than above it. Includes dynamic normalization for previously generated workbench items.
* UX & Mobile: Streamlined Smart Mobile App Deep-Linking: Eliminated redundant, visually cluttered `📲 App` footer buttons. Primary `🦋 Bluesky` and `🐘 Mastodon` action badges now feature smart protocol launching (`data-app-url`): opening the native Bluesky or Mastodon app if installed on mobile, and gracefully falling back to standard web URLs in the browser if not. Desktop clicks remain native web links.

= 5.7.3 =
* Critical Fix: PHP 7.4 Compatibility & Site Outage Prevention: Replaced PHP 8.0+ `match()` expressions in `includes/feed-builder.php` and `includes/admin.php` with backwards-compatible array mappings. In v5.7.1/v5.7.2, servers running PHP 7.4 or earlier crashed with a fatal syntax parse error on file load, causing a 500 white screen site outage.
* Fix: PHP 8.1+ Type Safety & Fediverse Creator Meta Tag: Hardened `wp_head` Fediverse creator attribution tag formatting to use `wp_parse_url()` and guard against `null` post contents and boolean return types, preventing PHP 8.1+ TypeError exceptions.
* Compatibility: Full WordPress 7.1.1 & PHP 7.4-8.4 Validation: Verified all core hooks, functions, and query filters across WordPress 6.0 through 7.1.1 and PHP 7.4 through 8.4 environments.
* Reliability: Media Sideloading Safety Guards: Ensured `wp-admin/includes/image.php`, `file.php`, and `media.php` are conditionally verified before invoking attachment metadata generators in cron/background contexts.
* Reliability: Query Filter Object Guards: Hardened `pre_get_posts` query inspection to verify valid query object and method existence before applying RSS-only post suppression.

= 5.7.2 =
* Feature: Dark Mode Adaptability & Stylesheet Toggle: Embedded social cards now dynamically support OS dark color preferences (`prefers-color-scheme: dark`) and parent theme classes (`.dark`, `.dark-theme`, `[data-theme="dark"]`, `body.dark-mode`) with custom high-contrast dark palette styling and an admin display mode switch.
* Feature: Direct Sideloading for All Embedded Post Media: Expanded media library imports beyond featured images to download and attach all embedded update images directly into the WordPress Media Library, rewriting image URLs to local paths.
* Optimization: Batch Tag Popularity Transients: Added 5-minute WordPress transient caching (`social_digest_tag_weights`) for tag usage lookups, reducing taxonomy query load and prioritizing established tags in digest titles.
* Safety: Automated Schedule Collision Warning: Prominently warns administrators on the Workbench tab when an automated background cron run is approaching while uncommitted manual edits or exclusions exist.
* UX: Refresh Confirmation Dialog: Added safety prompts to the "Fetch / Refresh Next Run" action to prevent accidental loss of customized exclusions, pinned lead stories, and notes.

= 5.7.1 =
* Feature: Gutenberg Block Editor Markup: Outputs digest content wrapped into native WordPress block comments (`<!-- wp:core/html -->`), preventing "Attempt Block Recovery" warnings and allowing effortless visual editing in the modern block editor.
* Feature: Video & Animated GIF Embeds: Added rich video preview cards with play badges for Bluesky (`app.bsky.embed.video`) and native autoplaying, looping, muted `<video>` players for Mastodon animated GIFs (`gifv`) and video attachments.
* Feature: Fediverse Creator Attribution: Injects `<meta name="fediverse:creator" content="@user@instance.social">` into published digest posts to provide author profile attribution on Mastodon 4.3+ and Threads.
* Reliability: Network Fallback & Exponential Backoff: Added intelligent retries with exponential backoff (`1s`, `2s`) for transient API failures (HTTP 429/503) and 24-hour stale cache fallback snapshots.
* Feature: Advanced Content Exclusions: Upgraded blacklist filtering from simple substring matches to support whole hashtags (e.g. `#ad`), full regular expressions (e.g. `/giveaway/i`), and plain keywords.

= 5.7.0 =
* Architectural Modularization: Refactored core codebase from a single monolithic file into a clean, modern WordPress module architecture under `includes/`.
* Lightweight Bootstrap Loader (`social-digest.php`): Streamlined entrypoint down to ~240 lines handling core WordPress lifecycle hooks, cron schedules, protocol whitelisting, and module loading.
* Modular Subsystems:
  - `includes/helpers.php`: String manipulation, OpenGraph metadata scrapers, WebP/AVIF image format conversion, and excerpt generation.
  - `includes/api-clients.php`: Bluesky (AT Protocol) and Mastodon (ActivityPub) API fetchers and native card HTML renderer.
  - `includes/feed-builder.php`: Workbench state machine, candidate aggregation, thread unrolling, and WordPress post publication engine.
  - `includes/admin.php`: Admin menu integration, tabbed settings, sanitization, and interactive drag-and-drop workbench UI.

= 5.6.10 =
* Fix: Allowed Protocols Whitelist: Registered `bsky` and `mastodon` in `kses_allowed_protocols` filter to prevent WordPress core `esc_url()` from stripping `bsky://` and `mastodon://` mobile deep links to empty strings.
* Fix: Chronological Order & Thumbnail Selection: Fixed timestamp ordering so multi-post thread collapsing executes before count truncation and `$chronological_posts` remains sorted newest-first, preventing featured image exclusion errors.
* Security: Hardened OpenGraph Link Scraper: Switched OpenGraph image and card scrapers to `wp_safe_remote_get()` to protect against Server-Side Request Forgery (SSRF) when requesting external URLs.
* Fix: Touch-Safe ALT Badges: Prevented touch events on image accessibility ALT badges from triggering parent gallery links on mobile devices.

= 5.6.9 =
* Feature: Automated Social Thread Collapsing: Multi-post threads authored on Bluesky or Mastodon are now automatically grouped into unified cards featuring an "Expand Thread" toggle rather than flooding the digest with separate cards.
* Feature: Accessibility "ALT" Badges: Added subtle, semi-transparent "ALT" overlay badges on all embedded images. Hovering or clicking reveals the author's image description in a browser-native tooltip.
* Feature: Native Mobile App Deep-Linking: Added native deep-linking support (`bsky://` and `mastodon://`), displaying a dedicated `📲 App` badge in card footers to open posts directly in installed mobile apps.
* Settings: Added configurable toggles for thread collapsing and mobile deep-linking in the Feed Rules settings section.

= 5.6.7 =
* Feature: First-Line Excerpt Automation: Added setting and generator allowing WordPress post excerpts to be built automatically from the first line or sentence of each social post entry, joined by configurable delimiters (` // `, ` ... `, `. `, ` — `, ` • `, or custom string).
* Feature: Next-Run Workbench Excerpt Inspection: Added real-time Digest Excerpt Inspection box in the Workbench to preview the generated excerpt before publishing.
* Fix: Rich Link Card Previews: Enhanced Bluesky and Mastodon link handling with cached OpenGraph metadata scraper fallback (`social_get_og_card_cached`), ensuring all social posts with external links render full clickable preview cards (title, description, domain, and image) even when network embeds omit them.
* Fix: Entire Link Card Clickable: Made the entire preview card clickable (`<a>`) directing visitors to the source article.
* Fix: Clean Body Text: Hidden visible hashtags from post bodies and automatically stripped redundant trailing URLs when an interactive preview card is displayed.
* Fix: Clickable Fallback URLs: Ensured raw links in posts without preview cards remain cleanly formatted and clickable with `target="_blank"` and `rel="noopener"`.

= 5.6.6 =
* Fix: Added `data-nosnippet` tags to link card domains and providers so search engines do not extract them as part of the post snippet.
* Feature: Extract and display the root domain as a provider fallback on Bluesky link cards.

= 5.6.5 =
* Fix: Explicitly generates a clean `post_excerpt` stripped of social metadata to ensure WordPress excerpts and SEO meta descriptions remain readable.
* Fix: Added `data-nosnippet` tags to social card headers, footers, and repost banners to prevent them from bleeding into Google Search snippets.

= 5.6.4 =
* Feature: Multiple images within a single post are now displayed as a grid gallery instead of sequential large images.
* Feature: Automatically prioritizes hashtags from the selected featured image post for the generated digest title.

= 5.6.3 =
* Fix: Changed the HTML element of social cards from `blockquote` to `div` to prevent theme conflicts. This resolves an issue where Newspack or other WordPress themes could accidentally hide the cards via restrictive quote styling or aggressive embed scripts.

= 5.6.2 =
* Inline Follow Links & Bluesky Embed Symmetry: Restyled the native card header to mirror the official Bluesky embed design. Handles and follow links are now paired directly inline (`@handle · Follow`), replacing the separate pill buttons on the right.
* Multi-Network Association: When posts are cross-posted or accounts exist on both Bluesky and Mastodon, each handle is paired directly with its respective follow link in brand-coordinated styling (`@user.bsky.social · Follow · @user@instance · Follow`).
* Full Mastodon Handle Formatting: Ensured Mastodon author handles cleanly include the instance domain (e.g. `@bradlinder@fosstodon.org`) even when instance APIs return local user accounts without the host.
* Cleaner Footer Timestamp: Removed the calendar icon from the lower left corner of the card footer, providing a clean, uncluttered publication timestamp (`M j, Y · g:i A`).
* Responsive Header Flow: Optimized header wrap dynamics for narrow mobile viewports, keeping the avatar and author identity aligned without artificial stacking.

= 5.6.1 =
* Save as Draft Option in Workbench: Added a "Save as Draft" button alongside the publish action in the Next-Run Workbench, allowing editorial review of compiled digests in the WordPress editor before going live.
* Posts Menu Integration: Relocated primary Social Digest management to `Posts -> Social Digest` (`edit.php`), reflecting its core editorial role in creating WordPress posts.
* Default Tab to Actions & Preview: Accessing Social Digest via the Posts menu or toolbar now defaults directly to the Actions & Preview Workbench instead of Settings.
* Top Admin Bar Quick-Access Shortcut: Added a direct quick-action node to the WordPress Admin Bar / Toolbar for instant navigation to the Workbench and Settings from anywhere in the WordPress admin or front end.
* Direct Post Editor Links: Added direct "Edit Post" and "Edit Draft in WordPress" action links in the admin success notices upon creating or publishing a digest.

= 5.6.0 =
* Native Multi-Network Social Post Rendering: Replaced 3rd-party remote script widgets (`embed.js`) with native local HTML/CSS post card rendering for both Bluesky and Mastodon. Posts render consistently and seamlessly for all visitors, independent of client-side script blockers.
* Unified Post Card Architecture: Features user avatar, display name, handle links, and a direct "+ Follow" button in the card header, alongside localized timestamp and platform action badges in the footer.
* Live Social Engagement Metrics: Display-only like (Bluesky) and favorite/boost (Mastodon) counts with direct links back to the source post to interact or like directly on the platform.
* Dual-Platform Back-Links for Deduplicated Cross-Posts: When a post is shared across both Bluesky and Mastodon, the rendered native card features links and engagement badges for both networks.
* Platform Link Restrictions: Selecting "Bluesky Only" or "Mastodon Only" strictly confines follow links and action badges exclusively to the chosen network.
* Avatar Source Preference Setting: Added configuration setting under Sources allowing administrators to choose profile avatar precedence (Automatic, Prefer Bluesky, Prefer Mastodon, or None/Hidden).

= 5.5.4 =
* Mastodon Link Card Parity & Scraper: Added link card parsing (`card` status object) and cached OpenGraph scraper fallback (`social_get_og_image_cached()`) for Mastodon, ensuring posts with external links render with image previews and populate post thumbnails.
* Explicit Featured Image Selection UI: Added "Set as Featured Image" radio control with candidate image thumbnail previews to the Next-Run Workbench, allowing administrators to choose any post image as the digest's featured image.

= 5.5.3 =
* Restored Social Embed CSS Harmonization: Re-introduced front-end card framing CSS for Mastodon and Bluesky embeds (600px max-width, overflow wrapping, matching borders, padding, and subtle shadows) now that the underlying Mastodon content variable parser bug is resolved.

= 5.5.2 =
* Resolved Mastodon Embed Content & Handle Parser Bug: Fixed missing `$body_content`, `$author_acct`, and `$clean_handle` variables in `social_fetch_mastodon()`, ensuring Mastodon post bodies and author handles render correctly inside digest blockquotes.

= 5.5.1 =
* Reverted Social Embed CSS: Removed front-end injected stylesheet rules for social embeds per user request.

= 5.5 =
* Random Article Ordering Option: Added "Random Order" alongside Chronological and Reverse Chronological in General Settings, allowing users to randomize post display sequence in digests while strictly preserving chronological thumbnail exclusion logic.
* Strict Cutoff Persistence & Nonce Audit: Verified strict cutoff advancement upon successful publication run and validated consistent admin nonce verification across all workbench actions.

= 5.4.3 =
* Resolved Workbench Empty-Queue Cutoff Bug: Eliminated the historical fallback in candidate fetching that pulled old posts when no newer posts existed, ensuring that advancing the cutoff to "Right Now" strictly yields an empty queue as expected.
* Self-Syndication Ingestion Filter: Automatically detects and excludes social posts that link back to the host WordPress domain, preventing auto-syndicated articles and digests from re-importing as candidate sources. Added an admin toggle under Feed Rules in General Settings.
* Cutoff State Synchronization: Resetting or advancing cutoffs now cleanly resets staged candidate state to prevent stale drafts from persisting.

= 5.4.2 =
* Added "Set Cutoff to Right Now" quick button: Instantly advances Bluesky and Mastodon cutoffs to the current timestamp with one click, guaranteeing no posts older than this moment will be imported.
* Added Custom Cutoff Date & Time picker: Allows administrators to set an arbitrary historical or future boundary for social ingestion.
* Cutoff feedback enhancements: Detailed admin notices displaying the exact localized timestamp applied.

= 5.4.1 =
* CPU & Memory Spike Mitigation: Added temporary memory elevation (`wp_raise_memory_limit('image')`) during thumbnail sideloading and digest publication.
* Thumbnail Generation Throttling: Restricted intermediate thumbnail generation during sideloading to standard core sizes, preventing heavy 3rd-party theme/plugin multi-size scaling storms.
* Remote Image Download Guard: Implemented a 12MB download ceiling on remote thumbnails to protect against oversized or malformed remote images.
* Date-Range Backlog Bound: Enforced `max_age_days` cutoff constraints on initial and zero-cutoff candidate fetches to prevent heavy historical backlogs from straining server resources.

= 5.4 =
* Persist Cutoff Timestamps strictly on publication success, preventing unpublished workbench candidate fetches from silently advancing cutoffs.
* Fixed admin nonce action verification mismatches for manual automated imports and cutoff marker resets.
* Added Active Schedule Warning banner in the Workbench when automated cron is scheduled and unpublished candidate drafts exist.
* Added "Disabled (Manual Workbench Curation Only)" schedule option under General Settings to prevent background cron overwrites.
* Added protective JavaScript confirmation dialog to "Fetch / Refresh Next Run" when staged candidates exist.
* Harmonized network_mode fallback default to 'both' across settings registration and UI selects.
* Removed obsolete transient cleanup dead code from workbench reset routine.

= 5.3.5_beta =
* Restored and enhanced title template settings with hashtag enclosure and delimiter customization options and variable guide.
* Improved frontend live preview simulation fidelity and synchronicity with WordPress 6.7+.

= 5.3.4 =
* Upgraded to the Next-Run Editorial Workbench state-driven architecture, centralizing digest creation into a modular state machine.
* Unified settings page layout with responsive collapsible postboxes and optimized padding.
* Added live server-side simulation POST handlers for all publishing and curation actions.

= 5.3.3 =
* Implemented live WordPress server-side POST operational handlers for all publishing actions ("Publish Staged Digest Now", "Save Staging Draft", and "Send to Pending Review") on the Actions page, supporting temporary override settings and direct publication.

= 5.3.2 =
* Resolved tab redirection issue by making "General Settings" the default primary tab and placing it before "Actions, Staging & Diagnostics" in admin navigation.
* Unified all staging queue features, split/stacked view toggles, publishing actions ("Publish Staged Digest Now", "Save Staging Draft", "Send to Pending Review", "Clear Queue"), live preview, and header/footer editor directly within the Actions, Staging & Diagnostics tab.
* Adjusted widget header icon layout and padding across all admin postboxes, moving header graphics (clock, image, gear, performance, etc.) closer to the left edge next to drag handles.

= 5.3.0 =
* Integrated interactive Next-Run Editorial Workbench directly into the main plugin core.
* Added Lead Story Pinning option to set an article to top priority for the next publish run.
* Converted all widgets on both Settings and Actions & Preview tabs into standard collapsible, movable postboxes.
* Fully compatible with WordPress 6.7+ and PHP 8.3+.