=== accessSchema ===
Contributors: greghacke
Tags: roles, access control, permissions, REST API, audit log
Requires at least: 6.0
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 2.1.2
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Manage role-based access hierarchies with logging, constraints, and REST API control. Part of the OWBN Chronicle infrastructure.

== Description ==

This plugin enables:

* Hierarchical access roles (e.g., Chronicles/MCKN/HST)
* Dynamic registration of role groups and paths
* Role assignment/removal with in-app audit logs
* ASC Roles column and wildcard filters on the WP Admin Users table
* REST API access for integration with external tools
* Log-level control with DEBUG/INFO/WARN/ERROR filtering

== Installation ==

1. Copy to `wp-content/plugins/accessSchema`
2. Activate via WP Admin
3. Add `ACCESS_SCHEMA_API_KEY` and `ACCESS_SCHEMA_LOG_LEVEL` to your `wp-config.php`

== Changelog ==

= 2.1.2 =
* Client: added function_exists() guard on accessSchema_client_render_grouped_roles() to prevent fatal on duplicate load

= 2.1.1 =
* Grouped role display — roles now grouped by top-level category (Chronicle, Coordinator) with color-coded borders
* Category headers with visual distinction instead of abbreviated flat list
* Client: grouped role display matching server layout with flush/refresh controls
* Client: local-mode column hidden (server column handles display)
* Client: local-mode refresh fixed to route through local post instead of remote API
* Client: added missing local-mode functions for roles-by-email and check-access
* Client: JSON params fixed for local-mode server function calls
* Client: graceful fallback when server plugin not active

= 2.1.0 =
* ASC Roles column on WP Admin Users table with color-coded badges
* Select2-powered role filter dropdown and wildcard pattern input
* Supports * (single segment) and ** (multi-segment) pattern matching
* PHP 7.4 compatibility fix in client library

= 2.0.0 =
* Complete security hardening and PHPCS compliance
* AJAX handlers, edit modal, batch validation, CSV export
* Client library cleanup and distributable package

= 1.0.0 =
* Initial release

== Upgrade Notice ==

= 1.0.0 =
Initial beta for accessSchema with role registration and REST access.


= 1.0.1 =
Client agent tools

= 1.2.1 =
Client agent group caching

= 2.0.0 =
Complete refactor

= 2.0.2 =
Added roles/all api

= 2.0.4 =
PHP 7.4 compliance, version sync across server and client

= 2.1.0 =
ASC Roles column and filters on WP Admin Users table. PHP 7.4 compatibility fix.

= 2.1.2 =
Client bugfix: function_exists() guard to prevent fatal on duplicate load.

= 2.1.1 =
Grouped role display in Users table for better differentiation.
