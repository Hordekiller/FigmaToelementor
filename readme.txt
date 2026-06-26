=== Figma to Elementor ===
Contributors: Hordekiller
Tags: figma, elementor, hello-elementor, figma to elementor, template converter, design to wp, figma import
Requires at least: 6.6
Tested up to: 6.9
Requires PHP: 8.0
Stable tag: 1.3.1
Copyright: 2026 Hordekiller
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Convert Figma designs directly into editable Elementor templates. Full style preservation — colors, typography, gradients, shadows, and more.

== Description ==

**Figma to Elementor** bridges the gap between design and development. It fetches your Figma frames via the REST API, translates every visual property into native Elementor JSON, and saves them as editable templates.

= Key Features =

* **One-Click Import** — Paste any Figma URL — auto-extracts file key, loads frames.
* **Frame Browser** — Browse frames with live preview thumbnails.
* **Section Review** — Review detected sections before import.
* **Smart Widget Conversion** — FAQ→Accordion, Gallery→Basic Gallery, Carousel→Image Carousel.
* **Component Detection** — Auto-detects 18 component types from layer names (English + Persian).
* **Full Style Mapping** — Colors, borders, gradients, shadows, opacity, typography — all preserved.
* **Absolute Positioning** — Figma absolute positioning preserved in Elementor.
* **Gradient Support** — Linear and radial gradients with accurate color stops.
* **Image Handling** — Background fills, auto-download and attachment.
* **Global Style Sync** — Figma styles → Elementor Global Colors/Fonts.
* **Token Security** — PAT encrypted at rest via AUTH_KEY-derived AES-256.
* **Progress Feedback** — Real-time import progress bar.
* **Per-side Borders** — Individual top/right/bottom/left stroke widths.
* **Dynamic Tags** — Elementor Pro dynamic tags for Figma text/fields.
* **Cache Management** — Configurable TTL with manual flush.
* **RTL Support** — Full Persian (Farsi) localization included.

= How It Works =

1. Install and activate the plugin (Elementor required).
2. Get your Figma Personal Access Token from Figma Settings.
3. Enter your Figma file key (from the Figma URL).
4. Click "Convert" — your design becomes an Elementor template.
5. Edit with Elementor and publish.

== Installation ==

1. Download the latest release or clone: `git clone https://github.com/Hordekiller/FigmaToelementor.git`
2. Rename the folder to `hello-elementor-figma-sync` and upload to `/wp-content/plugins/`.
3. Activate the plugin through the Plugins menu in WordPress.
4. Go to the new "Figma Sync" menu in your admin dashboard.
5. Enter your Figma Personal Access Token in Settings.
6. Paste a Figma URL and start converting!

== Known Limitations ==

* **No bulk import** — Frames are imported one at a time. Batch select is planned.
* **Radial/angular/diamond gradients approximated** — Elementor only supports linear gradients natively; unsupported types are mapped to the closest equivalent.
* **Rotated absolute-positioned nodes skipped** — Nodes with rotation > 0.01° in an absolute-position context are excluded.
* **No Figma Variables → CSS custom properties** — Figma Variables sync is planned for a future release.
* **No two-way sync** — Changes made in Elementor cannot be pushed back to Figma.
* **No responsive breakpoints from Figma** — Breakpoints are not derived from Figma variants.
* **Not on WordPress.org Plugin Directory** — Install via GitHub Releases until directory submission is complete.

== Frequently Asked Questions ==

= Does this work with Elementor Free? =

Yes, core functionality works with Elementor Free. Elementor Pro is required for Dynamic Tags and Pro-specific widgets.

= Do I need a Figma plugin? =

No. This plugin uses the Figma REST API directly. You only need a Personal Access Token from your Figma account settings.

= Is Hello Elementor theme required? =

No, but the plugin is optimized for Hello Elementor and leverages its hooks for best performance.

= Can I edit the converted templates? =

Yes. All imported templates are fully editable in Elementor — every widget, style, and setting.

= Does it support Persian/Arabic? =

Yes. Full RTL support with Persian translation included.

== Changelog ==

= 1.3.1 =
* Fix: PHPStan baseline regenerated (154→100 errors, all missingType.iterableValue)
* Fix: Added proper WordPress/Elementor stubs for static analysis
* Fix: Removed blanket PHPStan ignores — all root causes addressed
* Fix: `wp_insert_post` now passes `$wp_error=true` for proper error handling
* Fix: `$run_id` typo in Logger resolved
* Fix: `should_position_absolute()` renamed to `node_needs_absolute_positioning()`
* Docs: README.md and readme.txt accuracy pass (license table, features, known limitations)

= 1.3.0 =
* New: Absolute positioning — Figma absoluteBoundingBox converted to Elementor position:absolute
* New: FAQ → Accordion widget conversion with Q&A pair detection
* New: Gallery → Basic Gallery widget conversion with image resolution
* New: Slider/Carousel → Image Carousel widget conversion (≥70% image fill threshold)
* New: Section Review screen — preview frames with detected component types before import
* New: Progress bar with real-time status updates during import
* New: Per-side stroke/border width mapping (top/right/bottom/left)
* New: Dynamic Tags for Elementor Pro (Figma text + field values)
* Enhancement: Token encrypted at rest using AUTH_KEY-derived AES-256
* Enhancement: Structured logging via centralized Logger class
* Enhancement: Cache TTL configurable via hello_figma_cache_ttl filter
* Enhancement: Manual cache flush button in admin settings
* Enhancement: Image auto-download and attachment via Image_Handler
* Enhancement: Radial gradient type detection (mapped to Elementor radial)
* Enhancement: Angular/diamond gradient types mapped to linear fallback
* Enhancement: Asset_Manager for centralized script/style registration
* Enhancement: Compatibility checks for plugin environment
* Docs: Updated README.md features and added Known Limitations
* Lint: phpcs.xml + phpstan.neon configuration files

= 1.2.1 =
* Fix: Uninstall cleaner — properly removes plugin options on uninstall
* Chore: Version constant synced across all files

= 1.2.0 =
* New: Component detection from layer names — 18 patterns (English + Persian)
* New: CSS class auto-tagging based on detected component type (figma-detected-{type})
* Enhancement: Conservative carousel detection (≥70% image fill threshold)
* Enhancement: Depth-limited recursive image search for nested Figma groups

= 1.1.0 =
* New: Component detection from layer names — 18 patterns (English + Persian)
* New: CSS class auto-tagging based on detected component type (figma-detected-{type})
* New: Slider/Carousel structural conversion — auto-converts to Elementor Image Carousel widget
* Enhancement: Conservative detection algorithm (≥70% image fill threshold for carousel)
* Enhancement: Depth-limited recursive image search for nested Figma groups

= 1.0.1 =
* Security: Token no longer exposed in HTML source on settings page
* Security: Added token validation test on settings save
* Enhancement: Retry-After header handling for Figma API rate limits
* Enhancement: Rate limit budget tracking via X-RateLimit-Remaining headers
* Enhancement: Token expiry notification (90-day PAT policy)
* Enhancement: Clear Figma cache button in settings
* Enhancement: Connection status indicator on settings page
* Enhancement: Improved Auto Layout mapping (row_gap vs column_gap)
* Enhancement: Added PHPCS and PHPStan configuration files
* Enhancement: Added GitHub Actions CI workflow

= 1.0.0 =
* Initial release
* One-click Figma to Elementor conversion
* 6 custom Elementor widgets
* Global style sync (colors + typography)
* Image auto-upload
* Template management library
* Elementor Pro Dynamic Tags
* Admin dashboard with AJAX
* Full RTL and Persian support
