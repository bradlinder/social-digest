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
