=== Social Digest ===
Contributors: wp-plugin-studio
Tags: bluesky, mastodon, digest, social media, curation, automation
Requires at least: 6.0
Tested up to: 6.7
Requires PHP: 7.4
Stable tag: 4.7.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Automated digest builder for Bluesky and Mastodon with cross-posting deduplication, media sideloading, smart taxonomy tagging, and custom header/footer editors.

== Description ==

Social Digest is a WordPress plugin that automates the aggregation and publication of your decentralized social updates from Bluesky (AT Protocol) and Mastodon (ActivityPub) into publication-ready WordPress digest articles.

= Key Features =

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

= 4.7.0 =
* Initial public release with unified header/footer TinyMCE editor, custom visual splitter, and comprehensive Bluesky + Mastodon deduplication pipeline.
