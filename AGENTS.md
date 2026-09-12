# AI Coding Assistant Project Instructions

## Critical Preview Requirement
The live preview of this project **must always resemble an authentic, active WordPress site with the latest version of the Social Digest plugin installed and activated**.

Whenever building, modifying, or repairing the frontend React application (`/src/App.tsx`, etc.):
1. **Always maintain the full live WordPress simulation**:
   - The authentic WordPress top Admin Bar (with site identity, live status, preview switches, and user profile badges).
   - The dark WordPress admin sidebar (`#1d2327`) with active menu states.
   - The complete WordPress settings canvas (`#f0f0f1`), native form controls, and meta-boxes (postboxes) that match all fields, hooks, and operational controls defined in `social-digest.php`.
   - The live front-end published post preview mode showing rendered social embeds, sideloaded media, tag pills, headers, and disclaimers.
2. **Do not replace the WordPress simulation with generic landing pages, promotional placeholders, or incomplete mockups**.
3. **Keep `social-digest.php` and the frontend UI in sync**:
   - Any options or functionality added to `social-digest.php` must be reflected in the settings UI meta-boxes and front-end preview.

## Versioning & Release Policy
- **Mandatory Point Release Updates**: Each time changes or functional updates are made to the core application or plugin (`social-digest.php`, core ingestion/deduplication engine, UI settings, or storage routines), the version number MUST be updated with a point release (e.g., `5.1.0` -> `5.1.1` or `5.2.0`), unless the user explicitly tells you which version number to assign.
- **Files to Synchronize on Every Version Bump**:
  1. `social-digest.php` (Plugin header `Version: x.y.z` and `SOCIAL_DIGEST_VERSION` constant if defined)
  2. `readme.txt` (`Stable tag: x.y.z` and add entry to `== Changelog ==`)
  3. `package.json` (`"version": "x.y.z"`)
  4. Frontend UI simulation display badges (WordPress footer/plugin info in `/src/App.tsx`)

