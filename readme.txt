=== Figma to Elementor ===
Contributors: Hordekiller
Tags: figma, elementor, hello-elementor, figma to elementor, template converter, design to wp, figma import
Requires at least: 6.6
Tested up to: 6.9
Requires PHP: 8.0
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Convert Figma designs directly into editable Elementor templates. Full style preservation — colors, typography, gradients, shadows, and more.

== Description ==

**Figma to Elementor** bridges the gap between design and development. It fetches your Figma frames via the REST API, translates every visual property into native Elementor JSON, and saves them as editable templates.

= Key Features =

* **One-Click Import** — Paste any Figma URL, auto-extracts the file key.
* **Full Style Mapping** — Colors, typography, borders, gradients, shadows, opacity — all preserved.
* **Component Breakdown** — Each section becomes an independently editable Elementor container.
* **Container-native** — Built for Elementor Flexbox Containers.
* **Typography Preserved** — Font family, size, weight, line height, letter spacing, transforms.
* **Gradient Support** — Linear gradients with accurate color stops.
* **Global Style Sync** — Sync Figma color and typography styles to Elementor global settings.
* **Editor Integration** — Import directly inside Elementor editor via `$e.run()` commands.
* **Template Library** — Save, manage, export, and re-use imported templates.
* **RTL Support** — Full Persian/Arabic language support.

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
