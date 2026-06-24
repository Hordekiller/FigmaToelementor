<div align="center">

# Figma to Elementor

**Convert Figma designs into Elementor templates — instantly.**

[![WordPress](https://img.shields.io/badge/WordPress-6.6%2B-blue?logo=wordpress)](https://wordpress.org)
[![Elementor](https://img.shields.io/badge/Elementor-3.27%2B-92003B?logo=elementor)](https://elementor.com)
[![PHP](https://img.shields.io/badge/PHP-8.0%2B-777BB4?logo=php)](https://php.net)
[![License](https://img.shields.io/badge/license-GPLv3-green)](LICENSE)
[![PRs Welcome](https://img.shields.io/badge/PRs-welcome-brightgreen)](https://github.com/Hordekiller/FigmaToelementor/pulls)

**Stop rebuilding designs from scratch. Import them directly.**

</div>

## Overview

Figma to Elementor bridges the gap between design and development. It fetches your Figma frames via the REST API, translates every visual property — colors, typography, spacing, borders, shadows, gradients — into native Elementor JSON, and saves them as editable Elementor templates.

No manual duplication. No pixel-guessing. Just design → publish.

### How It Works

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
| **One-click Import** | Paste any Figma URL — the plugin extracts the file key automatically |
| **Frame Browser** | See all frames in your file with live previews before importing |
| **Full Style Mapping** | Colors, typography, borders, gradients, shadows, opacity — all converted |
| **Component Breakdown** | Each section (header, hero, footer, etc.) becomes an independent Elementor container |
| **Container-native** | Built for Elementor Flexbox Containers — not outdated sections |
| **Typography Preserved** | Font family, size, weight, line height, letter spacing, transforms |
| **Gradient Support** | Linear gradients with accurate color stops |
| **Image Mapping** | Background images from Figma fills |
| **Style Sync** | Sync Figma color styles and typography to Elementor Global Colors/Fonts |
| **Editor Integration** | Import directly inside the Elementor editor via `$e.run()` commands |
| **Persian Support** | Full Persian (Farsi) localization included |

---

## Requirements

| Requirement | Version |
|---|---|
| WordPress | 6.6 or higher |
| PHP | 8.0 or higher |
| Elementor | 3.27 or higher (Free) |
| Elementor Pro | 3.27 or higher (Recommended) |
| Figma Account | Any plan (PAT required for API access) |

---

## Installation

### 1. Get a Figma Personal Access Token

1. Go to **Settings → Account → Personal Access Tokens** in Figma
2. Click **Generate new token**
3. Give it a name (e.g., `Elementor Sync`) and copy the token

### 2. Install the Plugin

**Option A: WordPress Dashboard**
1. Download the latest release from [Releases](https://github.com/Hordekiller/FigmaToelementor/releases)
2. Go to **Plugins → Add New → Upload Plugin** in WordPress
3. Upload the zip and activate

**Option B: Manual**
```bash
cd wp-content/plugins/
git clone https://github.com/Hordekiller/FigmaToelementor.git hello-elementor-figma-sync
```
Then activate from **Plugins** in WordPress.

### 3. Configure

1. Go to **Figma Sync → Settings**
2. Paste your Figma Personal Access Token
3. Save

### 4. Import Your First Design

1. Go to **Figma Sync → Dashboard**
2. Paste a Figma URL (e.g., `https://www.figma.com/file/abc123/MyDesign`)
3. Click **Load Frames**
4. Select a frame and click **Import**
5. Click **Edit with Elementor** to tweak the result

---

## Quick Start (Developer)

```php
// Get the renderer service
$renderer = HelloFigma\Plugin::instance()->get_renderer();

// Convert a Figma file to Elementor JSON
$template = $renderer->convert_file('your-file-key', 'node-id-here');

// Save as an Elementor template
$manager = HelloFigma\Plugin::instance()->get_template_manager();
$post_id = $manager->save_template($template, 'My Import', 'your-file-key');
```

Or directly inside the Elementor editor (browser console):

```js
// Fetch and insert via AJAX
await helloFigmaEditor.importFromFigma('file-key', 'node-id');

// Or pass JSON directly
$e.run('hello-figma/import-template', {
    template: { title: 'Hero', content: [{ elType: 'container', ... }] }
});
```

---

## Project Structure

```
FigmaToelementor/
├── admin/                    # Admin UI
│   ├── css/admin.css         # Dashboard styles
│   ├── js/admin.js           # Dashboard frontend
│   ├── js/editor.js          # Elementor editor integration
│   └── views/                # PHP templates
│       ├── dashboard.php
│       ├── settings.php
│       ├── style-sync.php
│       └── templates.php
├── dynamic-tags/             # Elementor dynamic tags
│   ├── class-figma-field.php
│   └── class-figma-text.php
├── includes/                 # Core plugin logic
│   ├── class-admin.php       # Admin AJAX handlers
│   ├── class-asset-manager.php
│   ├── class-compatibility.php
│   ├── class-elementor-renderer.php  # Figma → Elementor conversion engine
│   ├── class-figma-api.php   # Figma REST API client
│   ├── class-image-handler.php
│   ├── class-plugin.php      # Plugin bootstrap
│   ├── class-style-sync.php  # Figma style → Elementor globals
│   └── class-template-manager.php
├── languages/                # Translations
│   └── hello-figma-fa_IR.po
├── widgets/                  # Custom Elementor widgets
│   ├── class-figma-button.php
│   ├── class-figma-container.php
│   ├── class-figma-heading.php
│   ├── class-figma-icon-box.php
│   ├── class-figma-image.php
│   └── class-figma-section.php
├── hello-elementor-figma-sync.php   # Plugin entry point
├── readme.txt                # WordPress plugin readme
└── README.md                 # This file
```

---

## Architecture

The plugin follows a service-oriented architecture:

```
┌─────────────────────┐
│   Figma REST API    │  ◄── Figma_API (HTTP client with caching)
└────────┬────────────┘
         ▼
┌─────────────────────┐
│ Elementor_Renderer  │  ◄── Converts Figma node tree → Elementor JSON
│  - Node mapping     │       (elType, widgetType, settings, elements)
│  - Style extraction │
│  - Template wrapper │
└────────┬────────────┘
         ▼
┌─────────────────────┐
│ Template_Manager    │  ◄── Saves to wp_posts as elementor_library
│  - CRUD operations  │
│  - Export/Import    │
└────────┬────────────┘
         ▼
┌─────────────────────┐
│  Elementor Editor   │  ◄── Opens the template for editing
└─────────────────────┘
```

---

## Contributing

Contributions are welcome! Here's how you can help:

1. **Fork** the repo
2. **Create** a feature branch (`git checkout -b feature/amazing`)
3. **Commit** your changes (`git commit -m 'feat: add amazing feature'`)
4. **Push** to the branch (`git push origin feature/amazing`)
5. **Open a Pull Request**

### Development Setup

```bash
# Clone the repo
git clone https://github.com/Hordekiller/FigmaToelementor.git
cd FigmaToelementor

# For WordPress development, symlink into your plugins directory
ln -s $(pwd) /path/to/wp-content/plugins/hello-elementor-figma-sync
```

### Coding Standards

- PHP: [PSR-12](https://www.php-fig.org/psr/psr-12/) + [WordPress Coding Standards](https://developer.wordpress.org/coding-standards/wordpress-coding-standards/php/)
- JavaScript: [ES6+](https://262.ecma-international.org/)
- Namespace: `HelloFigma\*`
- File naming: `class-{name}.php`

---

## Roadmap

- [ ] **Figma Variables Support** — Import design tokens as CSS custom properties
- [ ] **Auto-layout → Elementor Flexbox** — Better Figma Auto Layout mapping
- [ ] **Component Library** — Create reusable Elementor components from Figma components
- [ ] **Two-way Sync** — Push Elementor changes back to Figma
- [ ] **Batch Import** — Import multiple frames at once
- [ ] **Responsive Breakpoints** — Figma variants → Elementor responsive settings
- [ ] **Plugin Store** — Publish on WordPress Plugin Directory

---

## License

GNU General Public License v3.0 — see [LICENSE](LICENSE) for details.

---

<div align="center">

**Made with ❤️ for the WordPress + Elementor community**

[Report Bug](https://github.com/Hordekiller/FigmaToelementor/issues) · [Request Feature](https://github.com/Hordekiller/FigmaToelementor/issues) · [Contribute](https://github.com/Hordekiller/FigmaToelementor/pulls)

</div>
