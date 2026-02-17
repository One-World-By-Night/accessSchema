=== Gravity Forms AccessSchema User Registration ===
Contributors: greghacke
Tags: gravity forms, user registration, access control, roles, accessschema
Requires at least: 5.0
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 1.0.2
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Creates WordPress users from Gravity Forms submissions with AccessSchema role assignment.

== Description ==

A Gravity Forms Feed Add-On that creates WordPress user accounts and assigns AccessSchema hierarchical roles from form submissions. Designed for admin-driven account provisioning with Gravity Flow approval workflows.

= Features =

* **Feed Add-On**: Maps form fields to user properties (username, email, name, player ID)
* **Custom GF Field**: AccessSchema Roles field with searchable Select2 multi-select
* **Role Assignment**: Assigns AccessSchema hierarchical role paths on user creation
* **Gravity Flow**: Works with approval workflows — admin selects roles during review
* **Notifications**: Sends new user welcome email with set-password link

= Requirements =

* WordPress 5.0+
* PHP 7.4+
* Gravity Forms 2.5+
* AccessSchema server plugin (active on same site, local mode)
* Gravity Flow (optional, for approval workflows)

= How It Works =

1. Applicant submits a Gravity Forms registration form
2. Admin reviews the submission (optionally via Gravity Flow approval)
3. Admin selects AccessSchema roles using the searchable dropdown
4. On approval, the feed creates the WordPress user and assigns roles
5. User receives a welcome email with a set-password link

== Installation ==

1. Upload the `gf-accessschema-user-registration` folder to `/wp-content/plugins/`
2. Activate the plugin through the Plugins menu
3. Ensure AccessSchema server plugin is active on the same site
4. Edit your Gravity Form and add an "AccessSchema Roles" field (under Advanced Fields)
5. Set the field visibility to "Admin Only"
6. Go to Form Settings > ASC Registration and create a feed
7. Map your form fields to user properties and select the ASC Roles field

== Changelog ==

= 1.0.2 =
* All field IDs now read from feed configuration — zero hardcoded IDs
* Added optional Reference Fields section to feed settings (username/player ID preferences)
* Works with any form regardless of field IDs

= 1.0.1 =
* Added inline Create Account panel on entry detail page
* Admin fills in username, player ID, roles, and WP role directly from entry view
* One-click account creation with AJAX processing
* Select2 styling improvements for role tag display

= 1.0.0 =
* Initial release
* Feed Add-On with user creation and AccessSchema role assignment
* Custom GF field type with Select2 role picker
* Gravity Flow approval workflow support
* New user notification with set-password email link
