=== Social Digest ===
Contributors: BradLinder, wp-plugin-studio
Tags: bluesky, mastodon, digest, social media, curation, automation, staging, webp
Requires at least: 6.0
Tested up to: 7.1.1
Requires PHP: 7.4
Stable tag: 5.7.8
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Automated digest builder for Bluesky and Mastodon with tabbed admin workflows, interactive next-run workbench, dry-run preview, media optimization (WebP/AVIF), and local asset caching.

== Description ==
Social Digest is a WordPress plugin that automates the aggregation and publication of your decentralized social updates from Bluesky (AT Protocol) and Mastodon (ActivityPub) into publication-ready WordPress digest articles.

== Changelog ==

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