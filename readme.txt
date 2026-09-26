=== Social Digest ===
Contributors: BradLinder, wp-plugin-studio
Tags: bluesky, mastodon, digest, social media, curation, automation, staging, webp
Requires at least: 6.0
Tested up to: 7.1.1
Requires PHP: 7.4
Stable tag: 5.7.42
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Automated digest builder for Bluesky and Mastodon with tabbed admin workflows, interactive next-run workbench, dry-run preview, media optimization (WebP/AVIF with quality sliders), and local asset caching.

== Description ==
Social Digest is a WordPress plugin that automates the aggregation and publication of your decentralized social updates from Bluesky (AT Protocol) and Mastodon (ActivityPub) into publication-ready WordPress digest articles.

== Changelog ==

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