=== Figma to Elementor ===
Contributors: Hordekiller
Tags: figma, elementor, hello-elementor, figma to elementor, template converter, design to wp, figma import
Requires at least: 6.6
Tested up to: 6.9
Requires PHP: 8.0
Stable tag: 1.3.3
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

= 1.3.3 =
* Fix: Front-end fatal — is_plugin_active() guard added for non-admin page loads
* Fix: Linear gradient detection — GRADIENT_LINEAR case added (was silently dropped)
* Fix: Security — esc_url() on image src, add_link_attributes() for custom link
* Fix: Security — SSRF host allowlist + image-only MIME restriction on downloads
* Fix: Security — current_user_can('manage_options') added to admin handlers
* Fix: Security — .htaccess deny-all added to log directory
* Fix: to_object list repeater corruption — sequential arrays preserved as arrays
* Fix: BOOLEAN_OPERATION/STAR/POLYGON mapped to icon (not image) — SVG export
* Fix: Per-side stroke weight — reads flat strokeTopWeight/strokeLeftWeight etc.
* Fix: Partial padding — individual sides applied, missing sides default to 0
* Fix: Social icons 'x' matcher — word-boundary regex instead of bare str_contains
* Fix: Stat parser — EU ambiguous formats return null, negative signs handled
* Fix: Dead ternary in extract_button_settings — split into proper fallback logic
* Fix: PHPCS line length warnings resolved
* Fix: PHPStan nullCoalesce.offset in image-handler.php resolved
* Tests: 29 new regression tests for above fixes (PHPStan 0 errors, 126/126 green)

= 1.3.1 =
* New: Elementor_Renderer refactored into 7 single-responsibility classes (NodeFilter, TypeResolver, StyleExtractor, LayoutExtractor, WidgetConverters, Positioning, JsonNormalizer)
* New: JsonNormalizer with normalize + validate on every save_template (critical errors → WP_Error, warnings → log)
* New: Snapshot testing — 6 golden scenarios, 83 total tests across 5 suites
* New: AES-256-GCM crypto upgrade (v2: prefix, backward-compatible CBC fallback)
* New: Cache metrics with per-type TTL, sampled logging (1:20), dedicated cache_get/cache_set wrappers
* New: Image download chunking (groups of 5) + progress throttling (≤1 call/sec)
* New: 9 additional language .po files (total 10: fa_IR, en_US, de_DE, es_ES, fr_FR, it_IT, nl_NL, pt_BR, sv_SE, tr_TR)
* New: "Recent Activity Log" section in Dashboard
* Enhancement: All 23 AJAX error responses include a stable `code` string for programmatic handling
* Enhancement: Logger error_log() fallback for ERROR/CRITICAL + `hello_figma_logged` action hook
* Enhancement: Rotated absolute-positioned nodes logged as WARNING with node_id/name/rotation
* Enhancement: AJAX input hardening — format enum validation, overrides size limit (10KB) and depth limit (5)
* Enhancement: 3 RTL test cases for map_align (CSS logical properties flex-start/flex-end)
* Fix: PHPStan Level 6 — 0 errors (baseline only for missingType.iterableValue)
* Fix: `save_template` uses `$validation['ok']` instead of fragile str_contains heuristic
* Fix: `$run_id` typo in Logger resolved
* Fix: `should_position_absolute()` renamed to `node_needs_absolute_positioning()`
* Fix: 16 fixture entries for TypeResolver (12 mapping rules, 4 edge cases)
* Docs: README.md overhaul — new Project Status table (done/incomplete/help-wanted), full language list, updated architecture diagram, honest known limitations

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
