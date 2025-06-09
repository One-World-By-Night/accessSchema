=== accessSchema ===
Contributors: greghacke
Tags: roles, access control, permissions, REST API, audit log
Requires at least: 6.0
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Manage role-based access hierarchies with logging, constraints, and REST API control. Part of the OWBN Chronicle infrastructure.

== Description ==

This plugin enables:

* Hierarchical access roles (e.g., Chronicles/MCKN/HST)
* Dynamic registration of role groups and paths
* Role assignment/removal with in-app audit logs
* REST API access for integration with external tools
* Log-level control with DEBUG/INFO/WARN/ERROR filtering

== Installation ==

1. Copy to `wp-content/plugins/accessSchema`
2. Activate via WP Admin
3. Add `ACCESS_SCHEMA_API_KEY` and `ACCESS_SCHEMA_LOG_LEVEL` to your `wp-config.php`

== Changelog ==

= 1.0.0 =
* Initial release

== Upgrade Notice ==

= 1.0.0 =
Initial beta for accessSchema with role registration and REST access.