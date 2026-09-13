# AI Coding Assistant Project Instructions

## Preview Requirement: Plugin Release & Changelog Hub
The live preview of this project displays a lightweight, high-speed **Plugin Release & Changelog Hub**:
- Displays current active version, release health, git tags, and backup snapshots.
- Renders the parsed `== Changelog ==` from `readme.txt` with formatted releases.
- Eliminates heavy frontend state duplication and simulation code to conserve tokens and keep context concise.
- Keeps focus and context on `social-digest.php` and real WordPress plugin development.

## Versioning & Release Policy
- **Point Release Updates**: When updates are made to the core plugin (`social-digest.php`), update the version number with a point release (e.g., `5.4` -> `5.4.1`), unless explicitly instructed otherwise.
- **Files to Synchronize on Every Version Bump**:
  1. `social-digest.php` (Plugin header `Version: x.y.z`)
  2. `readme.txt` (`Stable tag: x.y.z` and add entry to `== Changelog ==`)
  3. `package.json` (`"version": "x.y.z"`)
  4. Display badge in `/src/App.tsx`


