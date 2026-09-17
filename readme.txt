=== Social Digest ===
Contributors: BradLinder, wp-plugin-studio
Tags: bluesky, mastodon, digest, social media, curation, automation, staging, webp
Requires at least: 6.0
Tested up to: 6.7
Requires PHP: 7.4
Stable tag: 5.6.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Automated digest builder for Bluesky and Mastodon with tabbed admin workflows, interactive next-run workbench, dry-run preview, media optimization (WebP/AVIF), and local asset caching.

== Description ==
Social Digest is a WordPress plugin that automates the aggregation and publication of your decentralized social updates from Bluesky (AT Protocol) and Mastodon (ActivityPub) into publication-ready WordPress digest articles.

== Changelog ==

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