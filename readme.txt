=== Hochzeit Geschenkeliste ===
Contributors: patrickjaboo
Tags: wedding, wishlist, gifts, reservation, shortcode
Requires at least: 6.0
Tested up to: 6.9
Requires PHP: 7.4
Stable tag: 1.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

A WordPress plugin for a wedding gift list with email-verified reservations.

== Description ==

Hochzeit Geschenkeliste lets you display a wedding gift list on a WordPress page and allows guests to reserve gifts.

Features:

* Frontend shortcode `[hochzeit_geschenkeliste]`
* Manage gifts in the WordPress admin
* Reservation workflow with email confirmation
* Guest self-cancellation via email link
* Automatic cleanup of unconfirmed reservations

== Installation ==

1. Upload the plugin folder `hochzeit-geschenkeliste` to `/wp-content/plugins/`.
2. Activate the plugin in WordPress under "Plugins".
3. Create a page and add the shortcode `[hochzeit_geschenkeliste]`.

== Frequently Asked Questions ==

= Which personal data is stored? =

When a reservation is made, the plugin stores the email address, optionally a name, a verification token, and the reservation timestamp.

= Can I export and erase reservation data? =

Yes. The plugin integrates with the WordPress privacy tools for personal data export and erasure.

== Screenshots ==

1. Frontend gift list view
2. Admin gift management
3. Reservation dialog for guests

== Changelog ==

= 1.1.0 =
* Added release metadata (license, requires, domain path)
* Added WordPress.org-compatible `readme.txt`
* Added privacy integration for personal data export/erasure
* Added `uninstall.php`
* Implemented missing AJAX endpoint
* Hardened input sanitization

= 1.0.0 =
* Initial release

== Upgrade Notice ==

= 1.1.0 =
Recommended update for improved privacy integration and publication compliance.
