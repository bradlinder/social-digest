# Social Digest for WordPress

[![Version](https://img.shields.io/badge/version-5.0.0-blue.svg)](https://github.com)
[![WordPress](https://img.shields.io/badge/WordPress-6.0%2B-blue.svg)](https://wordpress.org)
[![PHP](https://img.shields.io/badge/PHP-7.4%2B-indigo.svg)](https://php.net)
[![License: GPL v2+](https://img.shields.io/badge/License-GPLv2%2B-green.svg)](https://www.gnu.org/licenses/gpl-2.0.html)

**Social Digest** is an automated content curation, media optimization, and publishing plugin for WordPress. It aggregates your updates across decentralized social networks (**Bluesky** and **Mastodon**) and compiles them into structured, publication-ready WordPress digest articles.

---

## 🌟 Key Features

### 1. Tabbed Admin Interface & Workflow Separation
- **Settings Tab**: Centralized, persistent configuration for sources, scheduling, media compression, and article taxonomy.
- **Editorial & Staging Queue Tab**: Dedicated staging environment to preview incoming draft updates, attach custom per-item commentary, and pin lead stories.
- **Actions & Diagnostics Tab**: Operational hub with manual import triggers, cutoff timestamp resets, network connection health checks, and non-destructive dry-run simulations.

### 2. Editorial Workflow, Staging & Post Pinning
- **Digest Staging Queue**: Review pending social posts before final publication.
- **Custom Per-Item Commentary**: Attach custom notes, reactions, or takeaways above individual social embed cards.
- **Lead Story Pinning**: Pin highlight updates to appear at the very top of the digest regardless of chronologic sorting.
- **Inline Excerpt Truncation**: Configurable character fold limit with expander toggle to prevent massive threads from dominating article layouts.

### 3. Media Optimization & WordPress Storage Hygiene
- **Modern Media Conversion**: Converts sideloaded images to `.webp` or `.avif` formats on the fly to save disk storage.
- **Local Asset & Avatar Caching**: Caches remote user avatars and link preview thumbnails locally to avoid broken links if third-party posts are deleted.
- **Responsive `srcset` Sizes**: Generates standard WordPress image derivatives (`medium`, `large`, `medium_large`, `thumbnail`) for fast mobile rendering.

### 4. Newsletter & Syndication Distribution
- **RSS-Only Mode**: Publish digests exclusively to your RSS feed and newsletter subscribers (e.g. Mailchimp, Substack, Brevo) without showing them on your public front-end blog stream.

### 5. Admin Health, Network Diagnostics & Simulation
- **Dry-Run / Simulation Preview**: Test imports instantly without publishing posts, downloading images, or updating timestamp markers.
- **Connection Health Checks**: Instant latency and HTTP status monitoring for Bluesky AT Protocol and Mastodon endpoints.

### 6. Multi-Network Social Ingestion & Deduplication
- **Bluesky (AT Protocol)**: Connects directly via public AT Protocol endpoints using your handle or DID.
- **Mastodon (ActivityPub)**: Connects to any Mastodon or Fediverse instance via public API endpoints.
- **Smart Cross-Posting Deduplication**: Detects identical posts shared to both Bluesky and Mastodon, embeds the primary platform version, and combines media attachments and hashtags.

### 7. Content Framing & Formatting
- **Visual Header/Footer Editor**: Unified TinyMCE visual editor with a dedicated **Insert Post Splitter** (`<!--digest_split-->`) button.
- **SEO & Search Visibility**: Optional `data-nosnippet` tagging on header and footer elements.

---

## 🚀 Installation

1. **Clone or Download**:
   ```bash
   git clone https://github.com/your-username/social-digest.git
   ```
   Or download the `.zip` archive from the Releases page.

2. **Upload to WordPress**:
   - Copy the `social-digest` directory to `/wp-content/plugins/social-digest/`.
   - Alternatively, in the WordPress Admin go to **Plugins > Add New > Upload Plugin** and select the `.zip` file.

3. **Activate**:
   - Go to **Plugins > Installed Plugins** and click **Activate** under **Social Digest**.

4. **Configure**:
   - Navigate to **Settings > Social Digest** in your WordPress dashboard to configure your accounts, staging preferences, and formatting options.

---

## ⚙️ Configuration Overview

| Tab | Section | Key Settings |
|---|---|---|
| **Settings** | **Social Accounts** | Bluesky handle/DID, Mastodon profile URL, Primary platform priority for cross-posts |
| **Settings** | **Ingestion Rules** | Minimum post threshold, Max post age, Filter replies/boosts, Exclude headline mirrors |
| **Settings** | **Media Hygiene** | Auto WebP/AVIF conversion, local avatar caching, responsive `srcset` generation |
| **Settings** | **Featured Media** | Image sideloading toggle, Selection order (1st, 2nd, random), Candidate pool offset |
| **Settings** | **Publishing & Syndication** | Post status, Author, Categories, Title template, Delimiters, RSS-Only mode, Excerpt fold |
| **Settings** | **Tags & Framing** | Header/Footer TinyMCE editor, `<!--digest_split-->` divider, `data-nosnippet` |
| **Staging Queue** | **Editorial Staging** | Approve/exclude updates, attach per-item author notes, pin lead stories |
| **Diagnostics** | **Actions & Simulation** | Run manual import, Dry-run simulation, Clear cutoffs, API connection latency tests |

---

## 📁 Repository Structure

```text
social-digest/
├── social-digest.php             # Main plugin bootstrap and orchestration file (v5.0.0)
├── readme.txt                    # WordPress.org plugin standard readme
├── README.md                     # GitHub documentation and user guide
├── roadmap.txt                   # Completed roadmap features and release log
└── assets/
    └── js/
        └── social-digest-editor.js # TinyMCE editor plugin for digest split markers
```
