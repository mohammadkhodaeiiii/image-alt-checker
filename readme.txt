=== Image Alt Checker ===
Contributors: mohammadkhodaei
Tags: accessibility, alt text, seo, images, wcag
Requires at least: 6.8
Tested up to: 6.8
Requires PHP: 8.0
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Scan your content and detect missing, empty, duplicate or low-quality image ALT text to improve accessibility and SEO.

== Description ==

Image Alt Checker scans your posts, pages and custom post types and reports every image whose ALT text is missing, empty, whitespace-only, duplicated, identical to the file name, too long, too short or otherwise suspicious. Good ALT text is essential for screen-reader users and helps search engines understand your images.

The plugin is built for performance and security: it scans in configurable batches, never loads all posts into memory, caches expensive lookups, uses vanilla JavaScript with no jQuery, and ships an entirely native WordPress admin interface.

= Features =

* Detects missing, empty and whitespace-only ALT attributes.
* Detects duplicate ALT text shared across images.
* Detects ALT text that is identical to the image file name.
* Detects ALT text longer than 125 characters or shorter than 3 characters.
* Detects suspicious, auto-generated ALT text (e.g. `IMG_1234`, `untitled`, numeric-only).
* Decorative image detection (role="presentation" / aria-hidden).
* Handles both Media Library and externally hosted images.
* Batch, memory-efficient scanning suitable for large sites.
* Native admin dashboard with cards, a health score and a detailed report.
* Live progress via secure AJAX (start, continue, cancel, clear cache, refresh).
* Settings via the WordPress Settings API.
* Translation ready (POT file included) and RTL compatible.
* Accessible: ARIA progressbar, keyboard friendly, respects prefers-reduced-motion.
* Extensible, service-based architecture ready for a future Pro version.

== Installation ==

1. Upload the `image-alt-checker` folder to the `/wp-content/plugins/` directory, or install the ZIP through Plugins → Add New → Upload Plugin.
2. Activate the plugin through the Plugins screen in WordPress.
3. Open the "Image Alt Checker" menu, configure the Settings, then run a scan from the Scanner page.

== Frequently Asked Questions ==

= Does it require jQuery? =

No. The plugin uses vanilla JavaScript only.

= Does it change my content? =

No. Image Alt Checker only reads and reports. It never modifies your posts or media.

= Will it work on large sites? =

Yes. Scanning runs in configurable batches and uses lightweight queries so memory usage stays low. You can also cap the maximum number of posts per scan.

= Does it send data anywhere? =

No. There is no tracking, no telemetry and no external API. Everything runs on your server.

== Screenshots ==

1. The dashboard with the health score and statistic cards.
2. The scanner page with live progress.
3. The detailed report table.
4. The settings screen.

== Changelog ==

= 1.0.0 =
* Initial release.

== Upgrade Notice ==

= 1.0.0 =
Initial release.
