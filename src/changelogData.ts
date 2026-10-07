import readmeTextCode from '../readme.txt?raw';

export interface ReleaseEntry {
  version: string;
  tag?: string;
  date?: string;
  isLatest?: boolean;
  isBeta?: boolean;
  highlights: string[];
}

function parseReleases(readme: string): ReleaseEntry[] {
  const sections = readme.split(/^= ([\d.]+) =/m);
  const releases: ReleaseEntry[] = [];
  
  for (let i = 1; i < sections.length; i += 2) {
    const version = sections[i].trim();
    const body = (sections[i + 1] || '').trim();
    const lines = body.split('\n');
    const highlights: string[] = [];
    
    for (const rawLine of lines) {
      const line = rawLine.trim();
      if (!line) continue;
      // Match markdown list item or numeric item
      const match = line.match(/^(?:\d+\.\s*|[\*\-]\s*)(.+)$/);
      if (match) {
        const text = match[1].trim();
        // Skip release category titles ending with colon (e.g. "* Feature Name:")
        if (!text.endsWith(':')) {
          highlights.push(text);
        }
      }
    }
    
    releases.push({
      version,
      tag: 'v' + version,
      isLatest: releases.length === 0,
      isBeta: releases.length === 1,
      highlights: highlights.length > 0 ? highlights : [body.split('\n')[0] || `Release ${version}`]
    });
  }
  
  return releases;
}

export const CHANGELOG_DATA: ReleaseEntry[] = parseReleases(readmeTextCode);

const latestVersion = CHANGELOG_DATA[0]?.version || '5.8.18';
const previousVersion = CHANGELOG_DATA[1]?.tag || 'v5.8.17';

export const PLUGIN_META = {
  name: 'Social Digest for WordPress',
  version: latestVersion,
  requiresWP: '6.0+',
  testedUpTo: '7.1.1',
  requiresPHP: '7.4+',
  license: 'GPLv2 or later',
  author: 'Brad Linder',
  githubRepo: 'BradLinder/social-digest',
  releaseZip: 'social-digest.zip',
  rollbackTarget: previousVersion,
};
