# Social Digest for WordPress

[![Version](https://img.shields.io/badge/version-4.7.0-blue.svg)](https://github.com)
[![WordPress](https://img.shields.io/badge/WordPress-6.0%2B-blue.svg)](https://wordpress.org)
[![PHP](https://img.shields.io/badge/PHP-7.4%2B-indigo.svg)](https://php.net)
[![License: GPL v2+](https://img.shields.io/badge/License-GPLv2%2B-green.svg)](https://www.gnu.org/licenses/gpl-2.0.html)

**Social Digest** is an automated content curation and publishing plugin for WordPress. It aggregates your updates across decentralized social networks (**Bluesky** and **Mastodon**) and compiles them into structured, publication-ready WordPress digest articles.

---

## 🌟 Key Features

### 1. Multi-Network Social Ingestion & Deduplication
- **Bluesky (AT Protocol)**: Connects directly via public AT Protocol endpoints using your handle or DID.
- **Mastodon (ActivityPub)**: Connects to any Mastodon or Fediverse instance via public RSS/API feeds.
- **Smart Cross-Posting Deduplication**: Automatically detects identical posts shared to both Bluesky and Mastodon, embeds the primary platform version, and combines media and hashtags without duplicate entries.

### 2. Automated Media Sideloading
- **Featured Image Sideloading**: Scans social posts for attached images, downloads and imports them into the WordPress Media Library, and sets them as the article's Featured Image.
- **Candidate Pool & Selection Rules**: Select first, second, third, or random candidate images, with options to prioritize media from subsequent posts to avoid repeating thumbnails.

### 3. Smart Taxonomy & Dynamic Titles
- **Dynamic Title Templates**: Tokenized title formatting (e.g., `Social Digest {hashtags} (Part {part})`).
- **Hashtag Extraction & Enclosure**: Harvests hashtags from posts, applies character length thresholds, and formats them using configurable delimiters (Oxford commas, semicolons, standard commas) and enclosure wrappers (parentheses, brackets, or none).
- **Automated Tag Assignment**: Automatically converts social hashtags into native WordPress post tags with minimum length and maximum count caps.

### 4. Granular Filtering & Thread Handling
- **Keyword & Ad Blocklists**: Exclude sponsored posts or updates containing specific keywords (e.g., `#ad`, `#sponsored`).
- **Boosts & Reposts**: Toggle whether shared/reposted content appears in the digest with attribution badges.
- **Self-Reply Threads**: Preserves multi-part self-reply threads in chronological order.
- **Headline Mirror Filter**: Automatically prevents importing social posts that mirror existing WordPress post titles.

### 5. Content Framing & Formatting
- **Visual Header/Footer Editor**: Unified TinyMCE visual editor with a dedicated **Insert Post Splitter** (`<!--digest_split-->`) button to position introductions above and disclaimers below the aggregated feed.
- **SEO & Search Visibility**: Optional `data-nosnippet` tagging on embedded social cards to keep search engine snippets focused on your original commentary.

### 6. Automated Scheduling & Publishing
- **Cron Intervals**: Hourly, twice-daily, daily, or every *X* days at a specified time.
- **Minimum Volume Threshold**: Requires a configurable minimum number of items before creating a digest, with an optional maximum age fallback.
- **Publish Status**: Automatically publish immediately, or save as *Draft* / *Pending Review*.

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
   - Navigate to **Settings > Social Digest** in your WordPress dashboard to enter your Bluesky/Mastodon accounts and customize your digest settings.

---

## ⚙️ Configuration Overview

| Section | Key Settings |
|---|---|
| **Social Accounts** | Bluesky handle/DID, Mastodon profile URL, Primary platform priority for cross-posts |
| **Ingestion Rules** | Minimum post threshold, Max post age, Filter replies/boosts, Exclude headline mirrors |
| **Title & Taxonomy** | Title format tokens, Hashtag delimiters, Tag extraction limits, Category & Author assignment |
| **Featured Media** | Image sideloading toggle, Selection order (1st, 2nd, random), Candidate pool offset |
| **Framing & Content** | Introduction header, Footer disclaimer, `<!--digest_split-->` placement, `data-nosnippet` |
| **Automation Schedule**| Execution frequency (Hourly, 6h, 12h, Daily, Custom days), Target run time |

---

## 📁 Repository Structure

```text
social-digest/
├── social-digest.php             # Main plugin bootstrap and orchestration file
├── readme.txt                    # WordPress.org plugin standard readme
├── README.md                     # GitHub documentation and user guide
├── roadmap.txt                   # Planned future features and enhancements
└── assets/
    └── js/
        └── social-digest-editor.js # TinyMCE editor plugin for digest split markers
```

---

## 🗺️ Roadmap

See [`roadmap.txt`](./roadmap.txt) for planned features, including:
- **Tabbed Settings & Actions UI**: Split the admin interface into clean "Settings" and "Actions / Diagnostics" views using standard WordPress navigation tabs.
- **Editorial Staging Queue**: Approval queue for previewing, reordering, and annotating draft items before publishing.
- **Per-Post Pinning**: Pin highlight updates to the top of the digest.
- **Media Optimization**: Auto-conversion to `.webp` and responsive `srcset` generation.
- **RSS-Only Syndication**: Dedicated feed channel for newsletter distribution without creating front-end blog posts.
- **Simulation / Dry-Run**: One-click preview of pending imports without modifying the database.

---

## 📄 License

This project is licensed under the **GNU General Public License v2.0 or later** (GPLv2+).
See the [GNU General Public License](https://www.gnu.org/licenses/gpl-2.0.html) for details.

---

## 🖥️ Live WordPress Environment Simulation Note

The interactive application preview in this workspace is configured to always resemble a live, fully functional WordPress installation with the latest version of **Social Digest** installed and activated. Any modifications or rebuilding of the interface must maintain this full WordPress admin fidelity and live front-end article view.

