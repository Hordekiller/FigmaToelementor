<div align="center">

# Figma to Elementor

**Convert Figma designs into editable Elementor templates — programmatically, faithfully, at scale.**

[![WordPress](https://img.shields.io/badge/WordPress-6.6%2B-blue?logo=wordpress)](https://wordpress.org)
[![Elementor](https://img.shields.io/badge/Elementor-3.27%2B-92003B?logo=elementor)](https://elementor.com)
[![PHP](https://img.shields.io/badge/PHP-8.0%2B-777BB4?logo=php)](https://php.net)
[![License](https://img.shields.io/badge/license-GPLv2%2B-green)](LICENSE)
[![Release](https://img.shields.io/github/v/release/Hordekiller/FigmaToelementor)](https://github.com/Hordekiller/FigmaToelementor/releases)
[![CI](https://github.com/Hordekiller/FigmaToelementor/actions/workflows/ci.yml/badge.svg)](https://github.com/Hordekiller/FigmaToelementor/actions/workflows/ci.yml)
[![PRs Welcome](https://img.shields.io/badge/PRs-welcome-brightgreen)](https://github.com/Hordekiller/FigmaToelementor/pulls)

</div>

---

## Overview

Figma to Elementor bridges the gap between design and development. It fetches Figma frames via the REST API, translates visual properties — colors, typography, spacing, borders, shadows, gradients — into native Elementor JSON, and saves them as fully editable templates.

```
1. Paste a Figma URL        →  auto-extract file key
2. Browse frames             →  preview thumbnails
3. Click Import              →  converts to Elementor template
4. Edit with Elementor       →  every section independently editable
```

---

## Requirements

| Requirement | Version |
|---|---|
| WordPress | 6.6+ |
| PHP | 8.0+ (8.2+ recommended) |
| Elementor | 3.27+ (Free) |
| Elementor Pro | 3.27+ (optional — needed for Dynamic Tags) |
| Figma | any plan (PAT required) |

---

## Features

| Area | Detail |
|---|---|
| **Import** | One-click Figma URL → Elementor template; auto-extract file key, frame browser, section review |
| **Style mapping** | Colors (solid/gradient), borders (linked & per-side), shadows (drop & inner), opacity, typography |
| **Container layout** | Auto-layout → Flexbox container; padding, gap, cross-axis alignment, absolute positioning |
| **Smart widgets** | FAQ → Accordion, Gallery → Basic Gallery, Carousel → Image Carousel |
| **Component detection** | 18 component types from layer names (English + Persian), CSS class auto-tagging |
| **Images** | Background fills, auto-download & WordPress attachment, batch chunking (5 at a time) |
| **Dynamic Tags** | Elementor Pro tags for Figma text & field values |
| **Global styles** | Figma styles → Elementor Global Colors & Fonts |
| **Security** | PAT encrypted at rest via AUTH_KEY-derived AES-256-GCM (AEAD); backward-compatible with legacy CBC |
| **Caching** | Configurable per-data-type TTL via `hello_figma_cache_ttl` filter; manual flush; sampled metrics (1:20) |
| **Logging** | Structured PSR-3-style Logger; dashboard "Recent Activity" tail; `hello_figma_logged` action hook |
| **Localization** | 10 languages (see below) |
| **Static analysis** | PHPStan Level 6 (0 errors, baseline only for `missingType.iterableValue`) |

---

## Languages

| Locale | Language | File |
|---|---|---|
| `fa_IR` | فارسی (Persian) — RTL | `languages/hello-figma-fa_IR.po` |
| `en_US` | English (US) | `languages/hello-figma-en_US.po` |
| `de_DE` | Deutsch | `languages/hello-figma-de_DE.po` |
| `es_ES` | Español | `languages/hello-figma-es_ES.po` |
| `fr_FR` | Français | `languages/hello-figma-fr_FR.po` |
| `it_IT` | Italiano | `languages/hello-figma-it_IT.po` |
| `nl_NL` | Nederlands | `languages/hello-figma-nl_NL.po` |
| `pt_BR` | Português (Brasil) | `languages/hello-figma-pt_BR.po` |
| `sv_SE` | Svenska | `languages/hello-figma-sv_SE.po` |
| `tr_TR` | Türkçe | `languages/hello-figma-tr_TR.po` |

All `.po` files are ready for translation via any `.po` editor (Poedit, GlotPress). Compiled `.mo` files are generated on build.

---

## Installation

### 1. Get a Figma PAT

**Settings → Account → Personal Access Tokens** in Figma → generate new token → copy.

### 2. Install the plugin

**Option A — Dashboard:** Download from [Releases](https://github.com/Hordekiller/FigmaToelementor/releases) → **Plugins → Add New → Upload Plugin** → activate.

**Option B — CLI:**

```bash
cd wp-content/plugins/
git clone https://github.com/Hordekiller/FigmaToelementor.git hello-elementor-figma-sync
```

Activate from **Plugins**.

### 3. Configure

**Figma Sync → Settings** → paste PAT → Save.

### 4. Import

**Figma Sync → Dashboard** → paste a Figma URL → **Load Frames** → select a frame → **Import** → **Edit with Elementor**.

---

## Project Status

### ✅ Done (v1.3.1)

| Module | What was delivered |
|---|---|
| **Elementor_Renderer refactor** | Monolithic `convert_node()` split into 7 single-responsibility classes: `NodeFilter`, `TypeResolver`, `StyleExtractor`, `LayoutExtractor`, `WidgetConverters`, `Positioning`, `JsonNormalizer`. Original public API unchanged. |
| **AJAX hardening** | All 23 `wp_send_json_error` calls carry a stable `code` string. `format` enum-restricted (`post`/`json`). `overrides` limited to 10 KB / depth 5. |
| **Crypto upgrade** | AES-256-CBC → AES-256-GCM (AEAD), `v2:` prefix, backward-compatible with legacy tokens. `upgrade_token_crypto()` for manual migration. |
| **Image flow control** | Downloads chunked in groups of 5; progress callback throttled to ≤1 call/second. |
| **Cache metrics** | Sampled hit/miss/set logging (1:20 default). Separate TTLs per data type (file, nodes, styles, images, etc.). |
| **Logger resilience** | `error_log()` fallback for ERROR/CRITICAL. `hello_figma_logged` action hook. Dashboard "Recent Activity Log" panel. |
| **Positioning warnings** | Rotated nodes in absolute-position context logged as WARNING with node_id, name, rotation value. |
| **JSON contract stabilization** | `JsonNormalizer::normalize_template()` + `validate_template()` called before every `save_template`. Template-level critical errors produce `WP_Error`; element-level warnings are logged. |
| **Snapshot testing** | 10 golden scenarios. CLI runner at `tests/snapshot-test.php` with update mode (`--update`). **Note:** golden files are self-generated on first run — snapshots prove stability, not correctness. |
| **Unit tests** | 97 tests, 0 failures across 5 suites: helpers (26 → 40), type resolver (24), widget converters (21), normalizer (16), snapshot (10). All standalone PHP CLI (no PHPUnit dependency). |
| **PHPStan Level 6** | 0 errors (after suppression via baseline — `missingType.iterableValue` entries remain in baseline). |
| **Gradient detection** | Linear (`GRADIENT_LINEAR`), radial, angular, diamond all detected. Unsupported types mapped to linear fallback; unknown paint types logged as WARNING. |
| **RTL correctness** | `map_align()` outputs `flex-start`/`flex-end` — CSS logical properties respect `dir` attribute automatically. 3 dedicated test cases. |

### ⏳ Incomplete / Needs Work

| Area | Gap | Help wanted |
|---|---|---|
| **Admin refactor** | AJAX handlers still live inside `Admin` class (single ~1500-line file). Should be split into separate endpoint classes with dependency injection. | Break `Admin` into `class-admin-ajax.php`, `class-admin-handlers.php` or similar. |
| **Cache metrics UI** | Metrics are logged but not displayed in the admin. A dashboard widget showing hit ratio (chart or bar) would help users tune TTLs. | Add a `Cache_Stats` class that reads logged metrics; render in Dashboard. |
| **Manual QA in Elementor** | Backgrounds, shadows, borders, typography, flex auto-layout, and structural widgets (accordion/carousel/gallery) have NOT been visually verified inside the Elementor editor. The conversion pipeline produces valid JSON that passes `validate_template`, but visual fidelity is unconfirmed. | Import a real Figma design and inspect each Elementor tab (Style → Layout → Advanced). File issues with before/after screenshots. |
| **Bulk import** | Frames are imported one at a time. No "select all" / queue mechanism. | Extend `ajax_convert` (or add a new endpoint) to accept multiple node IDs. |
| **Figma Variables → CSS custom properties** | No sync of Figma local variables / variable collections to CSS custom properties or Elementor Global Colors. | Reverse-engineer the Figma Variables API (GET /v1/files/:key/variables) and map to `:root` custom properties or Elementor Global Colors. |
| **Two-way sync** | Changes made in Elementor are not pushed back to Figma. Requires a Figma plugin for write access. | Far-term; needs Figma Plugin API development. |
| **Responsive breakpoints** | Breakpoints not derived from Figma variants. Elementor output uses only `desktop` by default. | Map Figma component variant sets to Elementor `_responsive` settings. |
| **WordPress.org directory** | Plugin not yet submitted to WordPress Plugin Directory. Requires final review of all GPL compliance headers. | Run through [Plugin Directory guidelines](https://developer.wordpress.org/plugins/wordpress-org/detailed-plugin-guidelines/); add `readme.txt` in canonical format (done); submit. |

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
├── admin/                        # Dashboard UI
│   ├── css/admin.css
│   ├── js/admin.js
│   ├── js/editor.js              # Elementor editor integration
│   └── views/                    # PHP templates (dashboard, settings, etc.)
├── dynamic-tags/                 # Elementor Pro dynamic tags (Figma text/field)
├── includes/                     # Core engine
│   ├── class-plugin.php          # Bootstrap / entry point
│   ├── class-elementor-renderer.php   # Orchestrator: Figma node tree → Elementor JSON
│   ├── class-figma-api.php            # Figma REST client + crypto + caching
│   ├── class-template-manager.php     # Template CRUD (save, export, delete)
│   ├── class-style-sync.php           # Figma styles → Elementor Global Colors/Fonts
│   ├── class-admin.php                # AJAX handlers (23 endpoints)
│   ├── class-asset-manager.php        # Script/style registration
│   ├── class-compatibility.php        # Environment checks
│   ├── class-image-handler.php        # Image download + attachment + chunking
│   ├── class-logger.php               # PSR-3-style structured logger
│   ├── class-nodefilter.php           # Visibility filter (→ should_render)
│   ├── class-typeresolver.php         # Figma type → Elementor elType/widgetType
│   ├── class-styleextractor.php       # Background, border, shadow, radius, typography
│   ├── class-layoutextractor.php      # Container layout, flex sizing, dimensions
│   ├── class-widgetconverters.php     # Carousel/Accordion/Gallery detection + build
│   ├── class-positioning.php          # Absolute positioning + rotation warning
│   └── class-jsonnormalizer.php       # JSON contract: normalize + validate
├── widgets/                      # Custom Elementor widgets
├── languages/                    # 10 translations (.po)
│   ├── hello-figma-fa_IR.po
│   ├── hello-figma-de_DE.po
│   ├── hello-figma-en_US.po
│   └── ...
├── tests/                        # CLI test suites (no PHPUnit)
│   ├── helpers-test.php          # 40 tests
│   ├── type-resolver-test.php    # 24 tests
│   ├── widget-converters-test.php # 21 tests
│   ├── json-normalizer-test.php  # 21 tests
│   ├── snapshot-test.php         # 10 golden scenarios
│   ├── mock-figma-api.php        # MockFigmaAPI for snapshot tests
│   ├── wordpress-stubs.php       # Minimal WP function stubs
│   └── run-all.sh                # Runner (exit 1 on any failure)
├── .github/
│   ├── CONTRIBUTING.md
│   └── workflows/ci.yml          # PHPCS + PHPStan on push/PR
├── phpcs.xml
├── phpstan.neon
├── phpstan-baseline.neon
├── hello-elementor-figma-sync.php  # Plugin entry
├── uninstall.php
├── LICENSE
├── README.md
└── readme.txt
```

---

## Architecture

The conversion pipeline follows a strict single-responsibility chain:

```
Figma REST API
     │
     ▼
Figma_API (HTTP client + cache + crypto)
     │
     ▼
Elementor_Renderer (orchestrator)
     ├── NodeFilter          → skip invisible/zero-opacity nodes
     ├── TypeResolver        → Figma node type → [elType, widgetType]
     ├── WidgetConverters    → try_build_carousel/accordion/gallery
     ├── StyleExtractor      → background, border, shadow, radius, typography
     ├── LayoutExtractor     → container padding/gap/sizing
     ├── Positioning         → absolute position → Elementor position:absolute
     ├── collapse_empty_wrappers, maybe_transfer_element_id
     └── JsonNormalizer      → normalize + validate final JSON
                           
Template_Manager (save/export/delete) ← JsonNormalizer guards writes
```

Each extractor class is stateless — it receives a `stdClass $settings` and returns modifications. No side effects, no shared state.

---

## Testing

All tests run as standalone PHP CLI files (no PHPUnit, no Composer dependency):

```bash
# Run all 5 suites (97 tests)
bash tests/run-all.sh

# Run a specific suite
php tests/helpers-test.php
php tests/type-resolver-test.php
php tests/widget-converters-test.php
php tests/json-normalizer-test.php

# Snapshot tests
php tests/snapshot-test.php          # verify
php tests/snapshot-test.php --update # overwrite golden files
```

Snapshot golden files live at `project_audit/snapshots/golden/` (gitignored — generated locally). Scenarios define mock Figma nodes in `project_audit/snapshots/scenarios/`.

**Note:** Snapshot tests validate *stability* (output hasn't changed since golden was recorded), not *correctness* — golden files are self-generated on first run from the very code under test. Always pair snapshot verification with unit tests that assert specific values.

---

## Known Limitations

| Limitation | Impact | Status |
|---|---|---|
| **No bulk import** | Frames imported one at a time | Planned |
| **Radial/angular/diamond gradients approximated** | Elementor only supports linear natively; unsupported types are mapped to closest equivalent | Known |
| **Rotated absolute-positioned nodes skipped** | Nodes with rotation > 0.01° in absolute-position context excluded (requires full relativeTransform matrix) | Logged as WARNING |
| **No Figma Variables → CSS custom properties** | Figma local variables not synced to `:root` or Elementor Global Colors | Planned |
| **No two-way sync** | Changes in Elementor cannot push back to Figma | Future |
| **No responsive breakpoints from Figma** | Output uses only `desktop` breakpoint by default | Future |
| **Admin class not refactored** | All 23 AJAX handlers in a single monolithic class | Open issue |
| **Cache metrics not in UI** | Hits/misses logged but no admin widget displays them | Open issue |
| **No visual QA in Elementor editor** | Conversion JSON passes structural validation but visual fidelity not yet confirmed | Open issue |
| **Not on WordPress.org** | Install via GitHub Releases only | In review |

---

## Contributing

We welcome PRs — especially for the items listed in [Project Status](#-incomplete--needs-work) above.

```bash
git clone https://github.com/Hordekiller/FigmaToelementor.git
cd FigmaToelementor
git checkout -b feature/your-feature

# Make changes, then verify:
php tests/run-all.sh

# If you add/modify PHP, check static analysis:
vendor/bin/phpstan analyse --level=6

# Submit a PR
git commit -m "feat: your change"
git push origin feature/your-feature
```

Read [CONTRIBUTING.md](.github/CONTRIBUTING.md) for full details.

---

## License

**GNU General Public License v2.0 or later** — see [LICENSE](LICENSE).

| Permitted | Prohibited |
|---|---|
| Use on any WordPress site | Distribute without source code |
| Modify for your own needs | Incorporate into proprietary software |
| Share copies with full license | Remove or change the license |
| Fork and improve (must stay GPL) | Misrepresent authorship |

All contributions are licensed under GPLv2+. Copyright 2026.

---

<div align="center">

[Report Bug](https://github.com/Hordekiller/FigmaToelementor/issues) · [Request Feature](https://github.com/Hordekiller/FigmaToelementor/issues) · [Contribute](https://github.com/Hordekiller/FigmaToelementor/pulls)

</div>
