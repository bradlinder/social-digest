=== Social Digest ===
Contributors: wp-plugin-studio
Tags: bluesky, mastodon, digest, social media, curation, automation, staging, webp
Requires at least: 6.0
Tested up to: 6.7
Requires PHP: 7.4
Stable tag: 5.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Automated digest builder for Bluesky and Mastodon with tabbed admin workflows, staging queue, dry-run simulation, media optimization (WebP/AVIF), local asset caching, and RSS-only syndication.

== Description ==

Social Digest is a WordPress plugin that automates the aggregation and publication of your decentralized social updates from Bluesky (AT Protocol) and Mastodon (ActivityPub) into publication-ready WordPress digest articles.

= Key Features =

* **Tabbed Admin Interface**: Clear workflow separation between Settings, Editorial Staging Queue, and Actions & Diagnostics.
* **Editorial Staging Queue**: Review draft digests prior to publishing, attach inline custom author commentary, pin lead stories, or reorder updates.
* **Dry-Run / Simulation Preview**: Test imports instantly without creating posts, sideloading media, or advancing timestamps.
* **Media Optimization & Storage Hygiene**: Automatic WebP/AVIF format conversion, thumbnail compression, responsive image srcset generation, and local asset/avatar caching.
* **RSS-Only Syndication Mode**: Distribute digests exclusively to RSS feeds and newsletter subscribers without cluttering the main blog stream.
* **Multi-Network Ingestion**: Native support for Bluesky handles/DIDs and Mastodon profile URLs.
* **Smart Deduplication**: Identifies cross-posted updates across platforms, deduplicating post text while aggregating media attachments and hashtags.
* **Featured Image Sideloading**: Automatically detects candidate images in social posts, downloads and sideloads them to the WordPress Media Library, and sets them as the article's Featured Image.
* **Taxonomy & Hashtag Extraction**: Extracts hashtags from imported social posts, formats them with customizable delimiter/enclosure styles, and converts them into WordPress tags.
* **Rich Header & Footer Framing**: Custom TinyMCE editor with `<!--digest_split-->` visual button for introductory notes, author commentary, and footer disclaimers.
* **Granular Filtering & Moderation**: Filter out sponsored/ad posts, toggle boosts/reposts, preserve self-reply conversation threads, and exclude posts matching existing WordPress headlines.
* **Flexible Automated Scheduling**: Runs on customizable WP-Cron intervals (hourly, every 6/12 hours, or every N days at a set time).

== Installation ==

1. Upload the `social-digest` folder to the `/wp-content/plugins/` directory (or install via the WordPress Plugin uploader).
2. Activate the plugin through the 'Plugins' screen in WordPress.
3. Navigate to **Settings > Social Digest** in your WordPress admin dashboard to configure your social handles, scheduling, and formatting preferences.

== Changelog ==

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
