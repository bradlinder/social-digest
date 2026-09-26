# Gemini / Coding Agent Guidelines

## Preview & Live Environment Persistence
- The project preview displays a lightweight, token-efficient **Plugin Release & Changelog Hub**.
- Shows current active version, git tag status, backup snapshots, and the full version history from `readme.txt`.
- Keeps the focus on maintaining and hardening `social-digest.php`.

## Versioning & Release Policy
- **Point Release on Core Change**: Each time `social-digest.php` or core plugin functionality is modified, update the version number with a point release (e.g., `5.4` -> `5.4.1`), unless explicitly instructed otherwise.
- Always keep `social-digest.php`, `readme.txt`, `package.json`, and the release hub badge in sync.

## Scope, Stability & Anti-Bloat Discipline
- **Strict Scope Control**: Make sure that any changes do not break existing functionality of the plugin or introduce new code or features that were not asked for.
- **Clarification First**: Let the user know and request clarification if you need more details on any ambiguous requirement before beginning code execution.
- **Minimal Surface-Area Modifications**: Keep changes strictly targeted to the specific request. Avoid opportunistic refactoring, unsolicited feature additions, or rewriting stable working code.
- **AST Syntax Verification**: Always run `node scripts/verify-all.cjs` after editing any PHP files to ensure zero fatal syntax or parsing errors.
- **Option Sanitization Defense**: When modifying admin forms or options in `includes/admin.php`, verify that unsubmitted or omitted fields in `$_POST` never inadvertently overwrite existing settings or erase defaults. Checkbox options must preserve stored states or supply explicit defaults.
- **Zero Embedded / Base64 Bloat**: Never introduce base64 duplication, inline copies of files from `includes/`, or runtime filesystem code generators into `social-digest.php`. The plugin strictly uses standard WordPress `require_once` module loading from disk.
- **WordPress Core Standards**: Prefer native WordPress APIs (`wp_kses_post()`, `wp_strip_all_tags()`, `absint()`, `sanitize_text_field()`, `wp_remote_get()`, `wp_safe_remote_get()`) rather than creating custom abstractions or introducing heavy external dependencies.
- **Graceful Fallbacks Over Hard Failures**: Auxiliary operations (such as thumbnail sideloading, tag extraction, or external API embed resolution) must be wrapped in defensive exception and validity guards so non-critical issues never interrupt primary digest generation or post publication.

## Roadmap Tracking
- Refer to `roadmap.txt` (specifically "Immediate Recommended Next Steps") when planning upcoming work or responding to inquiries about what to build next.


