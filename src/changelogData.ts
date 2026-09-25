export interface ReleaseEntry {
  version: string;
  tag?: string;
  date?: string;
  isLatest?: boolean;
  isBeta?: boolean;
  highlights: string[];
}

export const PLUGIN_META = {
  name: 'Social Digest for WordPress',
  version: '5.7.33',
  requiresWP: '6.0+',
  testedUpTo: '7.1.1',
  requiresPHP: '7.4+',
  license: 'GPLv2 or later',
  author: 'Brad Linder',
  githubRepo: 'BradLinder/social-digest',
  releaseZip: 'social-digest.zip',
  rollbackTarget: 'v5.7.32',
};

export const CHANGELOG_DATA: ReleaseEntry[] = [
  {
    version: '5.7.33',
    tag: 'v5.7.33',
    isLatest: true,
    highlights: [
      'Single-Letter Prefix CamelCase Splitting: Enhanced CamelCase parsing with word-boundary single-capital prefix splitting (e.g. #FDroid -> F Droid, #GSuite -> G Suite, #XScreen -> X Screen) without disrupting uppercase acronyms like AI or PC.',
      'Native Hyphenated Brand Formatting: Added automatic precision formatting for hyphenated tech brands, ensuring #FDroid, #fdroid, and #F_Droid cleanly render as F-Droid, and #EInk renders as E-Ink.',
      'Baseline Vocabulary Enrichment: Added F-Droid, E-Ink, U-Boot, and Coreboot to the baseline vocabulary dictionary for instant zero-config recognition.'
    ]
  },
  {
    version: '5.7.32',
    tag: 'v5.7.32',
    isLatest: false,
    highlights: [
      'Granular WebP & AVIF Compression Quality Sliders: Added fine-grained image compression quality sliders (60-100%) for converted WebP and AVIF assets in Media Optimization & Storage Hygiene settings.',
      'WordPress Image Editor Integration: Upgraded image sideloading and conversion to use WordPress image editor with user-defined WebP and AVIF quality settings.'
    ]
  },
  {
    version: '5.7.31',
    tag: 'v5.7.31',
    isLatest: false,
    highlights: [
      'CamelCase Tag Preservation: Extracted author-authored CamelCase hashtags (#TripleScreenLaptop) directly from Mastodon post HTML links and spans, preventing Mastodon API lowercase normalization from overriding casing.',
      'Casing-Aware Tag Deduplication: Implemented social_dedupe_cased_tags() to strictly prefer mixed-case/CamelCase hashtags across candidate collection, cross-network merging, and WordPress title generation.',
      'Title Split Accuracy: Guaranteed multi-word hashtags like #TripleScreenLaptop, #DuraBook, and #RuggedLaptop format with proper spacing in post titles.'
    ]
  },
  {
    version: '5.7.30',
    tag: 'v5.7.30',
    highlights: [
      'Auto-Sync & Self-Healing: Added active MD5 integrity checks between installed files in /includes/ and the current plugin version, auto-updating outdated module files on disk on execution.',
      'Framing Overrides UI: Separate Dedicated Lead-In Header TinyMCE editor and Closing Footer HTML field with 1-click formatting buttons (B, I, Link, HR, BR).',
      'No Split Marker: Completely removed legacy <!--digest_split--> divider workflows.',
      'Tagging & Splitting: Complete CamelCase and acronym word splitting for all imported post tags.'
    ]
  },
  {
    version: '5.7.29',
    tag: 'v5.7.29',
    highlights: [
      'Tagging & Splitting: Applied CamelCase & acronym word splitting to all imported WordPress post tags (e.g., #AcerGooglebook14 -> "Acer Googlebook 14", #DellXPSGooglebook -> "Dell XPS Googlebook", #Android17QPR2 -> "Android 17 QPR 2", #MediaTekDimensityCXC10Max -> "MediaTek Dimensity CX C10 Max").',
      'All Hashtags Ingestion: All hashtags associated with included posts are now imported as WordPress tags, while only the primary/first hashtag per post is considered for post titles.',
      'Workbench Overrides UI: Replaced unified split editor with separate Header TinyMCE and Footer HTML fields equipped with quick format toolbar buttons (Bold, Italic, Link, HR, BR).',
      'Context-Aware Framing Checkbox: Automatically enables the temporary header/footer override checkbox when text is entered into either override field.'
    ]
  },
  {
    version: '5.7.28',
    tag: 'v5.7.28',
    highlights: [
      'UI & Settings Refactoring: Reorganized long Settings tab into 4 distinct, collapsible, drag-and-drop postboxes.',
      'Feature & UI Controls: Added controls for all previously hidden settings including Ingestion Fetch Order, Default Tags, Hashtag Auto-Tagging parameters, Same-Day Suffix Template, and Article Framing Header/Footer HTML.'
    ]
  },
  {
    version: '5.7.27',
    tag: 'v5.7.27',
    isLatest: true,
    highlights: [
      'Formatting: Bold Bullet Sentence Divider: Sentence divider bullet option in post excerpts now renders in bold font (<b>•</b>).',
      'Feature: Trailing Ellipsis Option: Added setting to append "..." to automatically generated post excerpts.',
      'Feature: Wildcard (*) Prefix Matching in Custom Overrides: Support wildcard rules like MINISFORUM* so #MINISFORUMS5 becomes MINISFORUM S5.'
    ]
  },
  {
    version: '5.7.26',
    tag: 'v5.7.26',
    isLatest: false,
    highlights: [
      'Feature: Custom Tag Title Overrides Import & Export (.txt files): Instant one-click export and file import for tag override lists.',
      'Feature: Configurable Vocabulary Post Scan Depth: Customize scan limits (50, 150, 300, 500, 1000, Full Site) with dynamic warning callouts for large archives.'
    ]
  },
  {
    version: '5.7.25',
    tag: 'v5.7.25',
    isLatest: false,
    highlights: [
      'Bug Fix: Galaxy Tab S-Series Model Designation: Hashtags like #SamsungGalaxyTabS12 now strictly format as "Samsung Galaxy Tab S12" (or "Tab S12").',
      'Bug Fix: Snapdragon X-Series Processor Designation: Snapdragon X-series tags format as "Snapdragon X2" without extra space before the series number.',
      'Enhancement: Post-Processing Regex Cleanup: Added safety guards to prevent site taxonomy terms containing "Tabs" from corrupting model designation numbers.'
    ]
  },
  {
    version: '5.7.24',
    tag: 'v5.7.24',
    isLatest: false,
    highlights: [
      'Bug Fix: CamelCase Hashtag Preservation: Corrected model number splitting for tags like #SamsungGalaxyTabS12 to preserve "Tab S12" without corruption from unspaced dictionary entries.',
      'Bug Fix: Single Hashtag Per Social Post Enforcement: Eliminated featured post tag double-counting during title candidate assembly.',
      'Feature: Fuzzy Topic Deduplication: Added social_are_tags_fuzzy_duplicates() to reject duplicate/overlapping topic phrases from the digest title.'
    ]
  },
  {
    version: '5.7.23',
    tag: 'v5.7.23',
    isLatest: false,
    highlights: [
      'Feature: Organic Site Vocabulary Engine: Learns product names, brands, and terms directly from published local WordPress post titles, tags, and categories.',
      'Enhancement: Transient Caching: Caches vocabulary index in a high-speed WordPress transient with configurable scan frequencies (24h, 7d, 30d).',
      'Feature: Admin Re-index Controls: Added "Re-index Site Vocabulary Now" button and status indicator under Tag Formatting settings.'
    ]
  },
  {
    version: '5.7.22',
    tag: 'v5.7.22',
    isLatest: false,
    highlights: [
      'Feature: Zero-Config Automated Title Tag Engine: Combines CamelCase regexes, letter-number transitions, and built-in tech word segmentation.',
      'Feature: Optional Custom Tag Overrides: Added a settings textarea under Hashtag Formatting Rules allowing optional manual tag mappings (rawtag=Formatted Name).'
    ]
  },
  {
    version: '5.7.21',
    tag: 'v5.7.21',
    isLatest: false,
    highlights: [
      'Fix: Chip Series & Form Factor Segmentation: Added specific rules and compound dictionary mappings for Snapdragon X (Snapdragonx 2 -> Snapdragon X2) and form factors (Eliteminipc -> Elite Mini PC, minipc -> Mini PC).',
      'Enhancement: Preserved Acronyms: Retained uppercase formatting for model designations (X, X1, X2, X3, PC, PCS, S12, QN10).',
      'Fix: Workbench Refresh Trigger: Workbench auto-invalidates drafts containing concatenated model terms (eliteminipc, snapdragonx).'
    ]
  },
  {
    version: '5.7.20',
    tag: 'v5.7.20',
    isLatest: false,
    highlights: [
      'Fix: Recursive Compound Tag Segmentation: Added recursive compound word segmentation (thinkbookplusgen -> ThinkBook Plus Gen, autotwist -> Auto Twist, steamframe -> Steam Frame, samsunggalaxytabs -> Samsung Galaxy Tabs).',
      'Fix: Letter-Number Boundary Splitting: Correctly splits attached numbers from word boundaries (thinkbookplusgen 7autotwist -> ThinkBook Plus Gen 7 Auto Twist, minimalphone 2 -> Minimal Phone 2).',
      'Enhancement: Title Case Capitalization & Acronyms: Enforced strict Title Case capitalization across all title tags while preserving tech acronyms (VR, AI, PC, OLED, 5G, S12).',
      'Fix: Workbench Draft Invalidation: Automatically re-generates staged workbench drafts if uncapitalized lowercase tags are detected.'
    ]
  },
  {
    version: '5.7.19',
    tag: 'v5.7.19',
    isLatest: false,
    highlights: [
      'Fix: Title Case Capitalization: Applied strict Title Case capitalization to all hashtags and topic keywords formatted for WordPress post titles (steamframe -> Steam Frame, minimalphone 2 -> Minimal Phone 2, samsunggalaxytabs 12 -> Samsung Galaxy Tabs 12).',
      'Enhancement: Word Boundary & Compound Splitting: Added intelligent splitting for compound tech terms (steamframe -> Steam Frame, minimalphone -> Minimal Phone, samsunggalaxytabs -> Samsung Galaxy Tabs) and letter-number transitions (tabs12 -> Tabs 12).',
      'Enhancement: Preserved Acronyms: Maintained uppercase formatting for tech acronyms, model codes, and hardware terms (e.g. AI, PC, VR, OLED, 5G, S12).'
    ]
  },
  {
    version: '5.7.18',
    tag: 'v5.7.18',
    isLatest: false,
    highlights: [
      'Fix: Auto-Refresh Stale Drafts: Workbench state now tracks versioning. Staged candidate drafts created on prior releases automatically re-generate on load, removing stale markup and broken title formatting instantly.',
      'Enhancement: Topic Keyword Extraction: When posts do not contain explicit #hashtags, key proper nouns and topic words (e.g. Samsung, Valve, Asus, Lenovo) are extracted to populate {hashtags} in post titles.',
      'Fix: Redundant Trailing Link Sanitation: Domain-style trailing links (winfuture.de/..., 9to5google.com/...) above embedded preview cards are automatically stripped, and inline a tags pass through wp_kses_post cleanly without raw HTML markup.'
    ]
  },
  {
    version: '5.7.17',
    tag: 'v5.7.17',
    isLatest: false,
    highlights: [
      'Fix: Tokenized URL Replacement: Fixed double-nested href attribute parsing where Bluesky facet links were re-processed by plain-URL regexes, ensuring rendered post links remain clean and active without printing raw HTML markup.',
      'Fix: Cross-Platform Hashtag Extraction: Fixed Mastodon API tag harvesting so Mastodon hashtags are populated into extra_tags before body cleaning, allowing Mastodon tags to populate title templates even when Bluesky posts have no hashtags.'
    ]
  },
  {
    version: '5.7.16',
    tag: 'v5.7.16',
    isLatest: false,
    highlights: [
      'Fix & Enhancement: Leading Separator Sanitation for Post Titles: Enhanced post title formatting to automatically trim leading punctuation (such as `: `, `- `, or `| `) when `{hashtags}` resolves to an empty string because no social posts in the batch contained hashtags and no default tags were set. Ensures templates like `{hashtags}: Liliputing News Roundup` render cleanly as `Liliputing News Roundup`.',
    ]
  },
  {
    version: '5.7.15',
    tag: 'v5.7.15',
    isLatest: false,
    highlights: [
      'Feature: Customizable WordPress Post Title Input in Next-Run Workbench: Added an editable WordPress Post Title input box (custom_title) located prominently at the top of the "Actions & preview" (Next-Run Workbench) tab above social candidate posts. Populates with the auto-generated headline template by default, allowing users to freely edit or replace the title before saving, drafting, or publishing.',
    ]
  },
  {
    version: '5.7.14',
    tag: 'v5.7.14',
    isLatest: false,
    highlights: [
      'Feature: Automated W3 Total Cache (W3TC) & Multi-Cache Purge Engine: Added explicit cache-purging integration (social_purge_site_caches()) for W3 Total Cache (w3tc_flush_posts() / w3tc_pgcache_flush()), WP Super Cache, WP Rocket, LiteSpeed Cache, WP Fastest Cache, SG Optimizer, and core WordPress post caches (clean_post_cache). Automatically purges post pages, homepage, archives, and RSS feeds whenever a digest is created or updated.',
    ]
  },
  {
    version: '5.7.13',
    tag: 'v5.7.13',
    isLatest: false,
    highlights: [
      'UI/UX & Layout: WP Media Settings Integration for Multi-Image Galleries: Multi-image post galleries now dynamically query the site\'s default WordPress thumbnail dimensions (get_option(\'thumbnail_size_w\') and get_option(\'thumbnail_size_h\')). Renders gallery images as clean, thumbnail-sized inline blocks using the user\'s configured Media Settings (defaulting to 150x150), while single-image embeds preserve full 400px–450px max dimensions.',
    ]
  },
  {
    version: '5.7.12',
    tag: 'v5.7.12',
    isLatest: false,
    highlights: [
      'UI/UX & Layout: Compact Thumbnail-Sized Media Galleries & Single Images: Optimized multi-image post galleries and single image/video embeds to render as compact, thumbnail-sized previews (120px to 220px height constraints). Displays multi-image galleries in clean single-row grids (up to 4 thumbnails side by side), preventing individual social posts from taking up excessive vertical screen space in published digest articles.',
    ]
  },
  {
    version: '5.7.11',
    tag: 'v5.7.11',
    isLatest: false,
    highlights: [
      'Feature: Configurable Title Hashtag Selection Strategy & Max Count: Added settings under Title Template to choose how hashtags are picked for WordPress post titles (First Hashtag in Post, Taxonomy Popularity/Frequency, or Random) and configurable max title tags (1 to 5 tags). Selecting the first hashtag ensures the leading tag is prioritized for the title while all harvested hashtags remain assigned to the post taxonomy.',
    ]
  },
  {
    version: '5.7.10',
    tag: 'v5.7.10',
    isLatest: false,
    highlights: [
      'UI/UX & Design: Native Bluesky Embed Card Hierarchy: Realigned link preview card layout and typography with native Bluesky card design standards. Headlines are bold with refined, proportionate sizing (14px), card body descriptions use clean normal font weight (400, 12.5px), and the source domain is positioned below the description at the bottom with a link indicator.',
    ]
  },
  {
    version: '5.7.9',
    tag: 'v5.7.9',
    isLatest: false,
    highlights: [
      'UI/UX & Readability: Pure White High-Contrast Dark Mode Typography: Overhauled all dark mode text selectors to render in crisp, pure white (#ffffff) for post content, paragraphs, author titles, reply threads, and link preview descriptions, eliminating any hard-to-read dark gray or black text on dark backgrounds.',
      'Fix: Prevent False-Positive Dark Mode on Light Websites: Removed OS-level prefers-color-scheme overrides that forced social cards to display as dark boxes on white website backgrounds. Cards now match the website\'s actual visual canvas and stay in clean light mode unless a dark website theme is actively detected or explicitly forced.'
    ]
  },
  {
    version: '5.7.8',
    tag: 'v5.7.8',
    isLatest: false,
    highlights: [
      'UI/UX & Readability: Enhanced Dark Mode Contrast & Typography: Overhauled the embedded native social card dark mode color system with crisp Slate-50 (#f8fafc) author titles, high-contrast Slate-100 (#f1f5f9) post copy, vibrant Sky-380 (#38bdf8) links and preview titles, and subtle Slate-800 card borders, significantly improving readability on dark backgrounds.',
      'Feature: Smart Website & Browser Theme Detection: Added dynamic theme intelligence that prioritizes the active website or theme color preference over conflicting system-level OS settings. Automatically inspects document root/body theme classes and computed background luminance (getComputedStyle), ensuring light-themed websites render light-mode cards even if the user\'s OS or browser is set to dark mode (and vice versa). Features live dynamic adaptation via MutationObserver when users toggle site dark mode switches.'
    ]
  },
  {
    version: '5.7.7',
    tag: 'v5.7.7',
    isLatest: false,
    highlights: [
      'Architecture: Modular Codebase with Zero-Downtime Self-Healing: Restructured plugin into clean, dedicated modules (includes/helpers.php, includes/api-clients.php, includes/feed-builder.php, and includes/admin.php). Built-in self-healing provisioner in social-digest.php automatically creates the /includes/ directory and restores module files on the fly if installed on a server that only received social-digest.php, guaranteeing zero downtime and complete backwards compatibility.',
      'Fix: TinyMCE Editor Splitter Inline Fallback: Added an inline TinyMCE plugin fallback in admin_footer to guarantee the "Insert Post Splitter" toolbar button functions properly even if external JS assets are missing from disk.'
    ]
  },
  {
    version: '5.7.6',
    tag: 'v5.7.6',
    isLatest: false,
    highlights: [
      'Hotfix: Emergency Standalone Consolidation: Consolidated core helper functions, API clients, feed builders, and admin UI views directly into social-digest.php to immediately eliminate fatal missing file errors (require_once includes/helpers.php: Failed to open stream) on environments performing single-file updates.'
    ]
  },
  {
    version: '5.7.5',
    tag: 'v5.7.5',
    isLatest: false,
    highlights: [
      'Critical Fix: PHP 8.5 / PHP 8.1+ Compatibility & Outage Prevention: Resolved an uncaught Fatal error: Class "SocialDigest\\DateTimeImmutable" not found in social-digest.php. Fully qualified \\DateTimeImmutable with leading backslash to prevent namespace lookup failure during cron scheduling, settings saves, and plugin activation.',
      'Fix: PHP 8.1+ TypeError Prevention on WordPress Filters: Added defensive type guards to kses_allowed_protocols, plugin_action_links, and TinyMCE button filters (mce_buttons, mce_external_plugins) to safely handle null or non-array inputs from 3rd-party themes or plugins.',
      'Reliability: Query Filter Object Guards: Hardened pre_get_posts filter to verify query object existence and method availability, immediately bypassing query modifications when RSS-only mode is inactive.',
      'Hardening: Admin Bar Hook Defensive Typing: Removed strict object typehint on admin_bar_menu action to prevent fatal TypeErrors if an admin bar wrapper or null is dispatched.'
    ]
  },
  {
    version: '5.7.4',
    tag: 'v5.7.4',
    isLatest: false,
    highlights: [
      'Image Galleries Positioned Below Link Preview Cards: Reordered media embed rendering so that posts containing both an article preview card and image galleries/video attachments render the gallery below the link preview card instead of above it, with automatic normalization for staged items.',
      'Streamlined Smart Mobile Deep-Linking: Replaced separate, cluttered "📲 App" footer buttons with intelligent direct link handling on primary 🦋 Bluesky and 🐘 Mastodon badges (data-app-url). Tapping on mobile opens installed apps, or falls back to web browser tabs if uninstalled; desktop clicks remain native web links.'
    ]
  },
  {
    version: '5.7.3',
    tag: 'v5.7.3',
    isLatest: false,
    highlights: [
      'Critical Fix: PHP 7.4 Compatibility & Outage Prevention: Replaced PHP 8.0+ match() expressions with backwards-compatible array mappings. In previous builds, servers running PHP 7.4 or earlier suffered a fatal parse error on startup, causing a 500 white-screen site outage.',
      'Fix: PHP 8.1+ Type Safety & Fediverse Attribution: Hardened wp_head Fediverse creator attribution tag formatting to use wp_parse_url() and guard against null post content and boolean return types, preventing PHP 8.1+ TypeErrors.',
      'WordPress 7.1.1 & PHP 7.4-8.4 Compatibility: Completed comprehensive audit of all hooks, query filters, and callbacks across WordPress 6.0 through 7.1.1 and PHP 7.4 through 8.4.',
      'Media Sideloading Safety Guards: Ensured WordPress admin image, file, and media libraries are safely verified and loaded before invoking attachment metadata generators in cron/background contexts.',
      'Query Filter Object Guards: Hardened pre_get_posts filter to verify object and method validity before applying RSS-only query alterations.'
    ]
  },
  {
    version: '5.7.2',
    tag: 'v5.7.2',
    isLatest: false,
    highlights: [
      'Dark Mode Adaptability: Embedded social cards support OS dark mode (prefers-color-scheme) and dark WordPress parent theme classes (.dark, .dark-theme, [data-theme="dark"], body.dark-mode) with a dedicated mode switch.',
      'All-Media Sideloading: Sideload all embedded post images directly into the local WordPress Media Library, updating post HTML to local URLs and protecting against external link rot.',
      'Batch Tag Popularity Transients: Added 5-minute transient caching for term lookups (social_digest_tag_weights), reducing taxonomy load and prioritizing established tags.',
      'Automated Schedule Collision Warning: Prominent warning banner in the Workbench when a background cron schedule is active while uncommitted manual drafts exist.',
      'Refresh Confirmation Dialog: Added safety prompt to "Fetch / Refresh Next Run" to prevent accidental data loss of staged exclusions, pinned lead stories, and notes.'
    ]
  },
  {
    version: '5.7.1',
    tag: 'v5.7.1',
    isLatest: false,
    highlights: [
      'Gutenberg Block Editor Support: Output digest articles using native WordPress block comment structures (<!-- wp:core/html -->) to avoid "Attempt Block Recovery" warnings when editing digests.',
      'Native Video & GIF Embeds: Added rich video preview cards with play badges for Bluesky video embeds and native looping muted video players for Mastodon video/GIFv attachments.',
      'Fediverse Attribution: Injects <meta name="fediverse:creator" content="@user@instance.social"> into published digest posts for author attribution across Mastodon 4.3+ and Threads.',
      'Network Resilience & Backoff: Implemented safe HTTP requests with exponential backoff (1s, 2s) for transient errors (429/503) and 24-hour stale cache fallback snapshots.',
      'Advanced Content Exclusions: Upgraded blacklist filtering to seamlessly support whole hashtags (#ad), full regular expressions (/pattern/i), and plain keywords.'
    ]
  },
  {
    version: '5.7.0',
    tag: 'v5.7.0',
    isLatest: false,
    highlights: [
      'Architectural Modularization: Refactored monolithic 2,900+ line codebase into clean, dedicated modules under `/includes/` while preserving 100% backwards compatibility.',
      'Helpers Module (`includes/helpers.php`): Extracted OpenGraph metadata scrapers, image sideloaders, WebP/AVIF format converters, text sanitizers, and excerpt generators.',
      'API Clients Module (`includes/api-clients.php`): Isolated Bluesky and Mastodon API fetchers and the native social post card renderer.',
      'Feed Builder Engine (`includes/feed-builder.php`): Decoupled next-run candidate aggregation, thread unrolling, preview calculation, and WordPress post publishing.',
      'Admin & Workbench Module (`includes/admin.php`): Modularized administrative menu registration, tabbed interfaces, settings sanitization, and drag-and-drop sortable workbench.',
      'Lightweight Bootstrap (`social-digest.php`): Streamlined entrypoint down to ~240 lines managing core lifecycle hooks, cron schedules, asset enqueuing, and module loading.'
    ]
  },
  {
    version: '5.6.10',
    tag: 'v5.6.10',
    isLatest: false,
    highlights: [
      'Protocol Whitelisting: Added bsky and mastodon to kses_allowed_protocols so WordPress esc_url() preserves mobile deep links.',
      'Thread & Thumbnail Order: Fixed timestamp sorting so thread collapsing runs prior to post truncation and newest-first order is preserved for thumbnail exclusion.',
      'SSRF Protection: Replaced wp_remote_get() with wp_safe_remote_get() across OpenGraph image and link card scrapers to block internal host requests.',
      'Touch Accessibility: Prevented touch events on image ALT badges from accidentally opening parent gallery links on mobile.'
    ]
  },
  {
    version: '5.6.9',
    tag: 'v5.6.9',
    isLatest: false,
    highlights: [
      'Automated Social Thread Collapsing: Grouped multi-post author threads into single unified cards with a "View full thread" expand toggle.',
      'Accessibility ALT Badges: Added interactive overlay "ALT" badges on embedded images displaying author descriptions in browser tooltips.',
      'Native Mobile Deep-Linking: Added bsky:// and mastodon:// app deep links with dedicated 📲 App badges in card footers.'
    ]
  },
  {
    version: '5.6.8',
    tag: 'v5.6.8',
    isLatest: false,
    highlights: [
      'Amended image gallery previews on Bluesky and Mastodon so clicking any thumbnail opens the original post in a new tab.',
      'Allows readers to view full-resolution multi-photo posts directly on the source platform without importing large images to WordPress.',
      'Added descriptive hover tooltips (`View full gallery on Bluesky` / `View full gallery on Mastodon`) for seamless UX.'
    ]
  },
  {
    version: '5.6.7',
    tag: 'v5.6.7',
    isLatest: false,
    highlights: [
      'Added automated excerpt builder constructing WordPress post excerpts from the first line or sentence of each social post entry.',
      'Added customizable excerpt delimiter setting (` // `, ` ... `, `. `, ` — `, ` • `, or custom text) with configurable item count limits.',
      'Added live Digest Excerpt Inspection box to the Next-Run Workbench for real-time validation prior to publishing.',
      'Integrated OpenGraph metadata scraper fallback for both Bluesky and Mastodon, guaranteeing clickable rich link cards (title, summary, image, domain) even when native API embeds omit them.',
      'Made entire preview cards clickable directly through to source articles with proper security attributes (`target="_blank" rel="noopener"`).',
      'Hidden visible hashtags from post body text and removed redundant trailing URLs when an interactive preview card is present.',
      'Ensured raw fallback links remain styled and clickable when no card preview is available.'
    ]
  },
  {
    version: '5.6.6',
    tag: 'v5.6.6',
    isLatest: false,
    highlights: [
      'Added `data-nosnippet` tags to link card domains and providers so search engines do not extract them as part of the post snippet.',
      'Extracted the root domain for Bluesky link cards and display it as the provider fallback.'
    ]
  },
  {
    version: '5.6.5',
    tag: 'v5.6.5',
    isLatest: false,
    highlights: [
      'Fixed search snippet and RSS excerpt bloat: Explicitly generates a clean, plain-text `post_excerpt` stripped of social metadata to ensure WordPress excerpts and SEO meta descriptions remain readable.',
      'Added `data-nosnippet` tags to social card headers, footers, and repost banners to prevent them from bleeding into Google Search results.'
    ]
  },
  {
    version: '5.6.4',
    tag: 'v5.6.4',
    isLatest: false,
    highlights: [
      'Multiple images within a single post are now displayed as a grid gallery instead of sequential large images.',
      'Automatically prioritizes hashtags from the selected featured image post for the generated digest title.'
    ]
  },
  {
    version: '5.6.3',
    tag: 'v5.6.3',
    isLatest: false,
    highlights: [
      'Changed HTML structure: Converted the outer social card container from `blockquote` to `div` to resolve conflicts with aggressive WordPress theme styles or embed scripts (like Newspack) that were hiding or distorting native blocks.'
    ]
  },
  {
    version: '5.6.2',
    tag: 'v5.6.2',
    isLatest: false,
    highlights: [
      'Inline Follow Links & Bluesky Embed Symmetry: Redesigned the native card header to match the official Bluesky embed aesthetic. Handles and follow links are now paired directly inline (@handle · Follow), eliminating separate pill buttons on the far right.',
      'Multi-Network Follow Associations: On cross-posted entries, each platform handle is paired directly with its own dedicated follow link in brand-coordinated styling (@user.bsky.social · Follow · @user@instance · Follow).',
      'Full Mastodon Handle Formatting: Formatted author handles to cleanly include the instance domain (e.g. @bradlinder@fosstodon.org) when local instance APIs omit the domain host.',
      'Cleaner Footer Timestamp: Removed the calendar icon from the card footer, providing a clean, uncluttered publication timestamp.',
      'Responsive Header Flow: Preserved side-by-side author avatar and identity text across all screen sizes without awkward mobile column collapse.'
    ]
  },
  {
    version: '5.6.1',
    tag: 'v5.6.1',
    isLatest: false,
    highlights: [
      'Save as Draft Option in Workbench: Added a "Save as Draft" button alongside publish in the Next-Run Workbench, generating WordPress drafts with full preview tags and categories for editorial review before publishing.',
      'Posts Menu Integration: Moved primary plugin management under Posts -> Social Digest (edit.php), establishing Social Digest as a native content drafting workflow.',
      'Default Tab to Actions & Preview: Navigating to Social Digest via the Posts menu or toolbar shortcuts now defaults directly to the Actions & Preview Workbench instead of Settings.',
      'WordPress Admin Bar Quick-Access Shortcut: Added a direct quick-action node to the top WordPress Admin Bar / Toolbar for fast navigation to Workbench and Settings from any page.',
      'Direct Post Editor Links in Admin Notices: Added clickable "Edit Post" and "Edit Draft in WordPress" links inside success notices upon creating or publishing a digest.'
    ]
  },
  {
    version: '5.6.0',
    tag: 'v5.6.0',
    isLatest: false,
    highlights: [
      'Native Multi-Network Social Post Rendering: Replaced 3rd-party remote script widgets (embed.js) with native local HTML/CSS card rendering for both Bluesky and Mastodon, remaining completely visible even with ad/script blockers active.',
      'Unified Header & Direct Follow Action: Header includes author avatar, display name, handle links, and direct "+ Follow" action buttons for the active platform(s).',
      'Display-Only Engagement Counts: Displays live like count (Bluesky) and favorite/boost count (Mastodon) with direct links back to the source post to interact directly.',
      'Dual-Platform Back-Links on Deduplicated Posts: Cross-posted entries that match on both Bluesky and Mastodon display back-links and engagement counters for both networks.',
      'Platform Link Scope Enforcement: Selecting "Bluesky Only" or "Mastodon Only" strictly confines follow links and action badges exclusively to the chosen platform.',
      'Avatar Platform Preference Setting: Added configuration dropdown in Settings allowing administrators to select which profile avatar to display (Automatic, Prefer Bluesky, Prefer Mastodon, or Hidden).'
    ]
  },
  {
    version: '5.5.4',
    tag: 'v5.5.4',
    isLatest: false,
    highlights: [
      'Mastodon Link Card Parity & OpenGraph Scraper: Added link card parsing (`card` status object) and cached OpenGraph image scraper fallback (`social_get_og_image_cached()`), ensuring Mastodon posts with external links render with image preview cards and populate post thumbnails.',
      'Explicit Featured Image Selector in Workbench: Added "Set as Featured Image" radio control with candidate thumbnail image previews to the Next-Run Workbench, allowing administrators to choose any post image as the digest featured image.'
    ]
  },
  {
    version: '5.5.3',
    tag: 'v5.5.3',
    isLatest: false,
    highlights: [
      'Restored Social Embed CSS Harmonization: Re-applied front-end card framing CSS for Mastodon and Bluesky embeds (600px max-width, overflow wrapping, matching borders, padding, and subtle shadows).',
      'Unified Visual Styling: Ensures both Bluesky and Mastodon post embeds render with visual symmetry across published single posts.'
    ]
  },
  {
    version: '5.5.2',
    tag: 'v5.5.2',
    isLatest: false,
    highlights: [
      'Resolved Mastodon Embed Content & Handle Parser Bug: Fixed missing $body_content, $author_acct, and $clean_handle variable assignments in social_fetch_mastodon().',
      'Restored Complete Embed Rendering: Ensures Mastodon post text and author handles (@username) render inside the blockquote card iframe and staging workbench.'
    ]
  },
  {
    version: '5.5.1',
    tag: 'v5.5.1',
    isLatest: false,
    highlights: [
      'Reverted Social Embed CSS: Removed front-end injected stylesheet rules for social embeds per user request.',
      'Random Article Ordering Option: Preserved "Random Order" setting in General Settings alongside Reverse Chronological and Chronological options.',
      'Strict Cutoff Persistence & Nonce Audit: Preserved strict cutoff advancement upon successful publication and verified consistent admin nonce security.'
    ]
  },
  {
    version: '5.5',
    tag: 'v5.5',
    isLatest: false,
    highlights: [
      'Random Article Ordering Option: Added "Random Order" setting in General Settings alongside Reverse Chronological and Chronological options.',
      'Preserved Image Selection Rules: Image selection (including "exclude most recent post" rule) strictly evaluates chronological order regardless of article display sequence.',
      'Strict Cutoff Persistence & Nonce Audit: Verified cutoffs strictly advance inside social_publish_workbench_run() upon successful publication and verified consistent admin nonce security.'
    ]
  },
  {
    version: '5.4.3',
    tag: 'v5.4.3',
    isLatest: false,
    highlights: [
      'Resolved Workbench Empty-Queue Cutoff Bug: Removed historical fallback logic in candidate prefetching so setting cutoffs to "Right Now" strictly yields an empty queue when no newer posts exist.',
      'Self-Syndication Ingestion Filter: Automatic domain-matching filter ignores social posts linking back to the host WordPress domain, preventing auto-syndicated digests/posts from re-importing.',
      'Feed Rules Setting: Added "Exclude self-syndicated posts" toggle in General Settings (enabled by default) for full editorial control.',
      'Cutoff Marker Synchronization: Setting or clearing cutoffs immediately resets staged drafts so stale workbench items never linger.'
    ]
  },
  {
    version: '5.4.2',
    tag: 'v5.4.2',
    isLatest: false,
    highlights: [
      'Added "Set Cutoff to Right Now": Instantly marks all existing posts as seen with a single click, ensuring only posts published after this moment are ingested.',
      'Added Custom Cutoff Date & Time picker: Select any custom historical or future date/time boundary for digest candidate evaluation.',
      'Enhanced Cutoff Diagnostics: Added detailed admin notices showing the exact localized time applied for both Bluesky and Mastodon.'
    ]
  },
  {
    version: '5.4.1',
    tag: 'v5.4.1',
    isLatest: false,
    highlights: [
      'CPU & Memory Spike Mitigation: Added temporary memory elevation (wp_raise_memory_limit) during thumbnail sideloading and digest publishing.',
      'Thumbnail Generation Throttling: Restricted intermediate image size generation during sideloading to standard sizes, avoiding multi-size resizing storms.',
      'Remote Image Download Guard: Implemented a 12MB ceiling on remote image downloads to prevent malformed or oversized assets from spiking RAM.',
      'Date-Range Backlog Bound: Enforced max_age_days cutoff boundary on initial and zero-cutoff candidate fetches to prevent heavy historical backlogs.'
    ]
  },
  {
    version: '5.4',
    tag: 'v5.4',
    isLatest: false,
    highlights: [
      'Persist Cutoff Timestamps strictly on publication success, preventing unpublished candidate fetches from advancing cutoffs.',
      'Fixed admin nonce action verification mismatches for manual automated imports and cutoff marker resets.',
      'Added Active Schedule Warning banner in the Workbench when automated cron is running alongside staged candidate drafts.',
      'Added "Disabled (Manual Workbench Curation Only)" schedule frequency option under General Settings.',
      'Added protective confirmation dialog to "Fetch / Refresh Next Run" when staged candidates exist.',
      'Harmonized network_mode fallback default to "both" across settings registration and UI selects.',
      'Removed obsolete transient cleanup dead code from workbench reset routine.'
    ]
  },
  {
    version: '5.3.5_beta',
    tag: 'v5.3.5_beta',
    isBeta: true,
    highlights: [
      'Pre-roadmap stabilization checkpoint and durable backup release snapshot.',
      'Restored and enhanced title template settings with hashtag enclosure and delimiter customization.',
      'Full state snapshot archived to .backups/v5.3.5_beta/ and tagged in Git.'
    ]
  },
  {
    version: '5.3.4',
    tag: 'v5.3.4',
    highlights: [
      'Upgraded to Next-Run Editorial Workbench state-driven architecture.',
      'Unified settings page layout with responsive collapsible postboxes.',
      'Added live server-side simulation POST handlers for all publishing and curation actions.'
    ]
  },
  {
    version: '5.3.3',
    tag: 'v5.3.3',
    highlights: [
      'Implemented live WordPress server-side POST operational handlers for all publishing actions on the Actions page.',
      'Supported temporary override settings and direct publication pipeline.'
    ]
  },
  {
    version: '5.3.2',
    tag: 'v5.3.2',
    highlights: [
      'Resolved tab redirection issue by prioritizing General Settings as primary tab.',
      'Unified staging queue features, split/stacked view toggles, and media sideloading controls.'
    ]
  },
  {
    version: '5.3.0',
    tag: 'v5.3.0',
    highlights: [
      'Integrated interactive Next-Run Editorial Workbench directly into the plugin core.',
      'Added cross-network deduplication engine (longer post vs preferred network).'
    ]
  }
];
