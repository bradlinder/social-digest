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
  version: '5.4',
  requiresWP: '6.0+',
  testedUpTo: '6.7',
  requiresPHP: '7.4+',
  license: 'GPLv2 or later',
  author: 'Brad Linder',
  githubRepo: 'BradLinder/social-digest',
  releaseZip: 'social-digest.zip',
  rollbackTarget: 'v5.3.5_beta',
};

export const CHANGELOG_DATA: ReleaseEntry[] = [
  {
    version: '5.4',
    tag: 'v5.4',
    isLatest: true,
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
