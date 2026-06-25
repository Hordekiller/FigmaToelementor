<div align="center">

# Figma to Elementor

**Convert Figma designs into Elementor templates — instantly.**

[![WordPress](https://img.shields.io/badge/WordPress-6.6%2B-blue?logo=wordpress)](https://wordpress.org)
[![Elementor](https://img.shields.io/badge/Elementor-3.27%2B-92003B?logo=elementor)](https://elementor.com)
[![PHP](https://img.shields.io/badge/PHP-8.0%2B-777BB4?logo=php)](https://php.net)
[![License](https://img.shields.io/badge/license-GPLv2%2B-green)](LICENSE)
[![Release](https://img.shields.io/github/v/release/Hordekiller/FigmaToelementor)](https://github.com/Hordekiller/FigmaToelementor/releases)
[![CI](https://github.com/Hordekiller/FigmaToelementor/actions/workflows/ci.yml/badge.svg)](https://github.com/Hordekiller/FigmaToelementor/actions/workflows/ci.yml)
[![PRs Welcome](https://img.shields.io/badge/PRs-welcome-brightgreen)](https://github.com/Hordekiller/FigmaToelementor/pulls)

**Stop rebuilding designs from scratch. Import them directly.**

</div>

---

## Overview

Figma to Elementor bridges the gap between design and development. It fetches your Figma frames via the REST API, translates every visual property — colors, typography, spacing, borders, shadows, gradients — into native Elementor JSON, and saves them as editable Elementor templates.

No manual duplication. No pixel-guessing. Just **design → publish**.

```
1. Paste a Figma URL        →  Auto-extracts the file key
2. Browse available frames   →  Preview thumbnails for each
3. Click Import              →  Converts to Elementor template
4. Edit with Elementor       →  Each section independently editable
```

---

## Features

| Feature | Description |
|---|---|
| **One-click Import** | Paste any Figma URL — file key extracted automatically |
| **Frame Browser** | Browse frames with live preview thumbnails |
| **Component Breakdown** | Each section (header, hero, footer) → independent Elementor container |
| **Full Style Mapping** | Colors, borders, gradients, shadows, opacity — all preserved |
| **Typography** | Font family, size, weight, line height, letter spacing, transforms |
| **Container-native** | Built for Elementor Flexbox Containers |
| **Gradient Support** | Linear gradients with accurate color stops |
| **Image Mapping** | Background images from Figma fills |
| **Style Sync** | Figma colors/typography → Elementor Global Colors/Fonts |
| **Editor Integration** | Import via `$e.run()` inside Elementor editor |
| **URL Parsing** | Full Figma URLs accepted — no need to extract file key manually |
| **RTL Support** | Full Persian (Farsi) localization included |

---

## Requirements

| Requirement | Version |
|---|---|
| WordPress | 6.6+ |
| PHP | 8.0+ |
| Elementor | 3.27+ (Free) |
| Elementor Pro | 3.27+ (Recommended) |
| Figma | Any plan (PAT required) |

---

## Installation

### 1. Get a Figma Personal Access Token

1. Go to **Settings → Account → Personal Access Tokens** in Figma
2. Click **Generate new token**
3. Name it (e.g., `Elementor Sync`) and copy the token

### 2. Install the Plugin

**Option A: WordPress Dashboard**
1. Download from [Releases](https://github.com/Hordekiller/FigmaToelementor/releases)
2. **Plugins → Add New → Upload Plugin** → upload zip → activate

**Option B: CLI**
```bash
cd wp-content/plugins/
git clone https://github.com/Hordekiller/FigmaToelementor.git hello-elementor-figma-sync
```
Activate from **Plugins** in WordPress admin.

### 3. Configure

1. Go to **Figma Sync → Settings**
2. Paste your Figma PAT → **Save**

### 4. Import Your First Design

1. Go to **Figma Sync → Dashboard**
2. Paste a Figma URL like `https://www.figma.com/file/abc123/MyDesign`
3. Click **Load Frames**
4. Select a frame → **Import**
5. **Edit with Elementor** to refine the result

---

## Quick Start (Developer)

```php
$renderer = HelloFigma\Plugin::instance()->get_renderer();
$template = $renderer->convert_file('file-key', 'node-id');

$manager = HelloFigma\Plugin::instance()->get_template_manager();
$post_id = $manager->save_template($template, 'My Import', 'file-key');
```

In Elementor editor (browser console):

```js
await helloFigmaEditor.importFromFigma('file-key', 'node-id');

$e.run('hello-figma/import-template', {
    template: { title: 'Hero', content: [{ elType: 'container', ... }] }
});
```

---

## Project Structure

```
FigmaToelementor/
├── admin/                     # Dashboard UI
│   ├── css/admin.css
│   ├── js/admin.js
│   ├── js/editor.js           # Elementor editor integration
│   └── views/                 # PHP templates
├── dynamic-tags/              # Elementor dynamic tags
├── includes/                  # Core engine
│   ├── class-elementor-renderer.php   # Figma → Elementor converter
│   ├── class-figma-api.php            # Figma REST client
│   ├── class-template-manager.php     # Template CRUD
│   ├── class-style-sync.php           # Global style sync
│   ├── class-admin.php                # AJAX handlers
│   ├── class-asset-manager.php
│   ├── class-compatibility.php
│   ├── class-image-handler.php
│   ├── class-logger.php               # Structured logging
│   └── class-plugin.php               # Bootstrap
├── widgets/                   # Custom Elementor widgets
├── dynamic-tags/              # Elementor Pro dynamic tags
├── languages/                 # Persian (Farsi) translation
├── .github/
│   ├── CONTRIBUTING.md        # Contribution guide
│   └── workflows/ci.yml       # GitHub Actions (PHPCS + PHPStan)
├── phpcs.xml                  # PHP CodeSniffer config
├── phpstan.neon               # PHPStan static analysis config
├── hello-elementor-figma-sync.php     # Entry point
├── LICENSE
├── README.md
└── readme.txt                 # WordPress plugin readme
```

---

## Architecture

```
┌─────────────────────┐
│   Figma REST API    │  ◄── Figma_API (HTTP + caching)
└────────┬────────────┘
         ▼
┌─────────────────────┐
│ Elementor_Renderer  │  ◄── Figma node tree → Elementor JSON
│  - Node mapping     │       (elType, widgetType, settings)
│  - Style extraction │
│  - Template wrapper │
└────────┬────────────┘
         ▼
┌─────────────────────┐
│ Template_Manager    │  ◄── wp_posts as elementor_library
│  - CRUD             │
│  - Export/Import    │
└────────┬────────────┘
         ▼
┌─────────────────────┐
│  Elementor Editor   │  ◄── Edit & publish
└─────────────────────┘
```

---

## Contributing

**All development happens on this repository.** No separate distributions, rebranded forks, or standalone versions. Contribute here so everyone benefits.

Read [CONTRIBUTING.md](.github/CONTRIBUTING.md) for the full guide.

```bash
git clone https://github.com/Hordekiller/FigmaToelementor.git
cd FigmaToelementor
git checkout -b feature/your-feature
# code → commit → push → PR
```

---

## License

**GNU General Public License v2.0 or later** — see [LICENSE](LICENSE).

| ✅ You may | ❌ You may NOT |
|---|---|
| Use on any WordPress site | Distribute without source code |
| Modify for your own needs | Incorporate into proprietary software |
| Share copies with license | Create standalone forks or rebranded versions |

All contributions are licensed under the same GPLv2+ terms. Copyright 2026.

---

## Roadmap

### ✅ Completed (v1.0.1)
- [x] **Token security** — PAT no longer leaked in HTML source
- [x] **Rate limit handling** — Retry-After support for 429 responses
- [x] **Token expiry notices** — 90-day Figma PAT policy notifications
- [x] **Clear cache UI** — Button in settings to flush Figma API transients
- [x] **Connection status** — Token validation indicator on settings page
- [x] **Auto-layout improvements** — Correct column_gap/row_gap mapping
- [x] **CI pipeline** — GitHub Actions with PHPCS + PHPStan

### 🔜 Up Next
- [ ] Figma Variables → CSS custom properties
- [ ] Figma Variables sync to Elementor Global Colors
- [ ] Component Library from Figma components
- [ ] Two-way sync (Elementor → Figma)
- [ ] Batch import multiple frames
- [ ] Responsive breakpoints from Figma variants
- [ ] WordPress Plugin Directory release

---

<div align="center">

**Made with ❤️ for the WordPress + Elementor community**

[Report Bug](https://github.com/Hordekiller/FigmaToelementor/issues) · [Request Feature](https://github.com/Hordekiller/FigmaToelementor/issues) · [Contribute](https://github.com/Hordekiller/FigmaToelementor/pulls)

</div>
