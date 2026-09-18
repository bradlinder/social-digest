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
  version: '5.6.6',
  requiresWP: '6.0+',
  testedUpTo: '6.7',
  requiresPHP: '7.4+',
  license: 'GPLv2 or later',
  author: 'Brad Linder',
  githubRepo: 'BradLinder/social-digest',
  releaseZip: 'social-digest.zip',
  rollbackTarget: 'v5.6.5',
};

export const CHANGELOG_DATA: ReleaseEntry[] = [
  {
    version: '5.6.6',
    tag: 'v5.6.6',
    isLatest: true,
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
