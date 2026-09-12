# Gemini / Coding Agent Guidelines

## Preview & Live Environment Persistence
- The project preview must always represent a live, active WordPress 6.x site with the Social Digest plugin installed and running.
- Ensure both the full WordPress Admin Settings interface and the Live Front-End Digest View are accessible and fully functional.
- Maintain exact visual and functional fidelity with standard WordPress admin tables, postboxes, tabs, buttons, and form controls.

## Versioning & Release Policy
- **Point Release on Every Core Change**: Each time the core app or plugin (`social-digest.php`, core algorithms, UI settings, or data flow) is modified, update the version number with a point release (e.g., `5.1.0` -> `5.1.1` or `5.2.0`), unless the user explicitly specifies a specific version number.
- Always keep `social-digest.php`, `readme.txt`, `package.json`, and frontend version indicators in sync.

