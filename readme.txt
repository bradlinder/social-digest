=== Social Digest ===
Contributors: BradLinder, wp-plugin-studio
Tags: bluesky, mastodon, digest, social media, curation, automation, staging, webp
Requires at least: 6.0
Tested up to: 6.7
Requires PHP: 7.4
Stable tag: 5.2.2
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Automated digest builder for Bluesky and Mastodon with tabbed admin workflows, staging queue, dry-run simulation, media optimization (WebP/AVIF), local asset caching, and RSS-only syndication.

== Description ==

Social Digest is a WordPress plugin that automates the aggregation and publication of your decentralized social updates from Bluesky (AT Protocol) and Mastodon (ActivityPub) into publication-ready WordPress digest articles.

== Key Features ==

* **Tabbed Admin Interface**: Clear workflow separation between Settings, Editorial Staging Queue, and Actions & Diagnostics.
* **Editorial Staging Queue**: Review draft digests prior to publishing, attach inline custom author commentary, pin lead stories, or reorder updates.
* **Dry-Run / Simulation Preview**: Test imports instantly without creating posts, sideloading media, or advancing timestamps.
* **Media Optimization & Storage Hygiene**: Automatic WebP/AVIF format conversion, thumbnail compression, responsive image srcset generation, and local asset/avatar caching.
* **RSS-Only Syndication Mode**: Distribute digests exclusively to RSS feeds and newsletter subscribers without cluttering the main blog stream.
* **Multi-Network Ingestion**: Native support for Bluesky handles/DIDs and Mastodon profile URLs.
* **Smart Deduplication & Resolution Strategies**: Identifies cross-posted updates across platforms with configurable strategies ("Prefer Mastodon if longer, otherwise Bluesky", "Longest text wins", "Always prefer Bluesky", "Always prefer Mastodon"). Strictly embeds ONLY the winning post and completely discards the duplicate.
* **Featured Image Sideloading**: Automatically detects candidate images in social posts, downloads and sideloads them to the WordPress Media Library, and sets them as the article's Featured Image.
* **Taxonomy & Hashtag Extraction**: Extracts hashtags from imported social posts, formats them with customizable delimiter/enclosure styles, and converts them into WordPress tags.
* **Rich Header & Footer Framing**: Custom TinyMCE editor with `<!--digest_split-->` visual button for introductory notes, author commentary, and footer disclaimers.
* **Granular Filtering & Moderation**: Filter out sponsored/ad posts, toggle boosts/reposts, preserve self-reply conversation threads, and exclude posts matching existing WordPress headlines.
* **Flexible Automated Scheduling**: Runs on customizable WP-Cron intervals (hourly, every 6/12 hours, or every N days at a set time).

== Installation ==

1. Upload the `social-digest` folder to the `/wp-content/plugins/` directory (or install via the WordPress Plugin uploader).
2. Activate the plugin through the 'Plugins' screen in WordPress.
3. Navigate to **Settings > Social Digest** in your WordPress admin dashboard to configure your social handles, scheduling, and formatting preferences.

== Frequently Asked Questions ==

= How does cross-post deduplication work? =
When you post the same link or update to both Bluesky and Mastodon, the plugin detects the match via multi-signal inspection (exact or stem URL matching from link cards and text, common prefix/substring containment, and relative text similarity). Under the default "Prefer Mastodon if longer, otherwise Bluesky" strategy, it compares clean character lengths (excluding URLs and hashtags). Only the winning post is embedded in the WordPress article body. If the winning post lacks hashtags that the losing post has, those missing tags are harvested to enrich the WordPress post taxonomy.

= Can I review digests before they are published live? =
Yes. You can set the publication status to "Draft" or enable the Editorial Staging Queue in the settings. The Staging Queue tab lets you view pending updates, reorder them, attach custom author notes, pin a story to the top, and publish manually when ready.

= What image formats are supported for sideloaded media? =
The plugin downloads attached images and converts them to `.webp` or `.avif` formats (if supported by your server's GD or Imagick extension), while generating standard WordPress responsive image sizes (`medium`, `large`, `thumbnail`).

= What license is this plugin distributed under? =
Social Digest is licensed under the GNU General Public License v2.0 or later (GPLv2+).

== Changelog ==

= 5.2.2 =
* Integrated drag-and-drop moveable postbox widgets and layout switcher natively into PHP-rendered WordPress admin interface (`social-digest.php`).
* Re-architected Editorial & Staging Queue and Actions & Diagnostics tabs into standard WordPress postbox widgets with draggable headers and Up/Down arrow reordering controls.
* Added Multi-Pane View toggle switcher in Staging Queue for side-by-side split view and single-column stacked view.
* Removed redundant framing box in Staging workspace in favor of the unified Live Article Header & Footer WYSIWYG editor.

= 5.2.0 =
* Added drag-and-drop moveable widgets (postboxes) to the Editorial & Staging Queue and Actions & Diagnostics tabs, matching standard WordPress admin postbox interaction patterns.
* Streamlined the Editorial & Staging Queue by removing the redundant custom framing box in favor of the unified Live Article Header & Footer WYSIWYG editor.
* Added a Multi-Pane View mode toggle to the Editorial Staging Queue workspace, enabling side-by-side split pane inspection (e.g. Live Article Preview on one side and Queue items/Framing controls on the other).

= 5.1.9 =
* Added item-level post exclusion checkboxes to the Editorial Staging Queue and Live Article Preview, allowing editors to skip specific social updates from the next manually created digest post.
* Implemented the Feed Progression Cutoff Rule (High-Water Mark): publishing a digest automatically advances the feed timestamp cursor past all posts in the batch, guaranteeing that excluded older items (e.g. items #9 and #10) are never re-ingested in subsequent automated roundups.

= 5.1.8 =
* Converted Article Header & Footer framing controls into an interactive WYSIWYG rich text editor with quick formatting buttons (Bold, Italic, Link, Bullet List, Blockquote) and visual Post Splitter (`<!--digest_split-->`).
* Integrated Header & Footer WYSIWYG editor directly into the Editorial Staging Queue workspace with live preview synchronization and conditional footer rendering when no divider is present.

= 5.1.7 =
* Added Live Staged Article Preview to the Editorial & Staging Queue workspace: editors can now inspect the compiled WordPress blog post mockup (including custom editorial takeaways, story pinning, header/footer framing, and media embeds) in real time while managing staged queue items.

= 5.1.6 =
* Refined title hashtag camelCase formatting: hashtags with a leading single lowercase letter followed by uppercase letters (e.g. #iPhone, #eReader, #iPad, #eBay, #iOS, #eBook) are now kept intact rather than erroneously splitting into separate words ("i Phone", "e Reader").

= 5.1.5 =
* Hardened Dry-Run Simulation and background ingestion against PHP runtime exceptions: wrapped runner and operational triggers in top-level Throwable handlers with actionable admin error notices.
* Fixed callback compatibility in cross-post URL normalizer and title tag formatting routines.
* Added deep schema null-safety validation across Bluesky feed items and Mastodon API payload records.

= 5.1.4 =
* Resolved PHP 8 critical error when executing Dry-Run Simulation: fixed array offset access on null simulation preview structures and hardened simulation error rendering.
* Enhanced Dry-Run simulation engine to evaluate recent posts without requiring manual cutoff resets, ensuring immediate preview generation even after recent live runs.
* Refined dry-run threshold validation to render transient blog post mockups regardless of minimum post counts while clearly flagging live production thresholds.

= 5.1.3 =
* Resolved cross-post detection failure for truncated posts and link cards by implementing multi-signal matching (URL stem containment, common prefix/substring matching, and relative text similarity).
* Strictly enforced single-embed output: only the winning post is embedded in the WordPress post body; the secondary post is completely excluded.
* Added intelligent tag harvesting: if the winning post lacks hashtags that the losing post possessed, those missing tags are harvested to enrich the WordPress post taxonomy.

= 5.1.2 =
* Corrected duplicate resolution to strictly use ONLY the winning post (embed, media, and tags) and discard the non-selected post without secondary metadata blending.
* Enhanced URL normalization and cross-platform matching to detect shared links across cards, embeds, and HTML anchors.

= 5.1.1 =
* Documentation and licensing overhaul: verified GPLv2+ compliance, updated plugin author URIs, and expanded FAQ entries.
* Documented configurable Duplicate Resolution Strategies in readme.txt and project documentation.

= 5.1.0 =
* Added Duplicate Resolution Strategies: "Prefer Mastodon if longer, otherwise Bluesky", "Longest text wins", and network-specific overrides with clean text length calculation (excluding hashtags and URLs).
* Integrated interactive Editorial Staging Workbench with real-time commentary framing, story pinning, and manual early publishing.
* Added live Ephemeral Dry-Run Simulation with post structure validation, cross-post deduplication inspector, and thumbnail extraction preview.
* Unified WordPress administration simulation and live preview rendering for v5.1.

= 5.0.0 =
* Added Tabbed Admin Navigation dividing Settings, Staging Queue, and Diagnostics.
* Added Editorial Staging Queue with custom per-item author commentary and post pinning.
* Added Dry-Run Simulation Preview tool to test imports non-destructively.
* Added Media Optimization: automatic WebP/AVIF conversion, local avatar caching, and responsive thumbnail srcsets.
* Added RSS-Only Syndication Mode for newsletter-exclusive distribution.
* Added Inline Excerpt Fold setting to prevent long threads from dominating article layouts.

= 4.7.0 =
* Initial public release with unified header/footer TinyMCE editor, custom visual splitter, and comprehensive Bluesky + Mastodon deduplication pipeline.
