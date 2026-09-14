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
  version: '5.5.3',
  requiresWP: '6.0+',
  testedUpTo: '6.7',
  requiresPHP: '7.4+',
  license: 'GPLv2 or later',
  author: 'Brad Linder',
  githubRepo: 'BradLinder/social-digest',
  releaseZip: 'social-digest.zip',
  rollbackTarget: 'v5.5.2',
};

export const CHANGELOG_DATA: ReleaseEntry[] = [
  {
    version: '5.5.3',
    tag: 'v5.5.3',
    isLatest: true,
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
