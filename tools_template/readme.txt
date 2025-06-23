=== Bylaw Clause Manager ===
Contributors: owbnwebcoord, greghacke
Tags: bylaws, nested content, legal clauses, custom post types
Requires at least: 6.0
Tested up to: 6.8
Stable tag: 2.0.0
Requires PHP: 7.4
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Manage and display nested bylaws or hierarchical clauses with filtering, tooltips, and Select2-enhanced editing.

== Description ==

**Bylaw Clause Manager** is a custom WordPress plugin that allows organizations like OWbN to manage a complex, hierarchical set of bylaws in a structured, modular, and trackable way. Each clause is independently editable, tagged for filtering, version-controlled, and rendered in a readable nested format — just like a legal document, but with modern digital accessibility.

This plugin is purpose-built for domains where clauses need to be tracked individually, filtered by thematic relevance (e.g., "Anarch", "Caitiff"), and presented with full contextual hierarchy for readability and transparency.

Note: This plugin version has no reliance on ACF

== How It Works ==

=== 1. Custom Post Type: `bylaw_clause` ===

Each bylaw clause is a WordPress post with structured fields:
- **Section ID** (`3`)
- **Post Title** (recommended: use a machine-readable version of the Section ID, e.g., `2_g_i_3`)
- **Content** (rich text body of the clause)
- **Parent Clause** (for nesting and hierarchy, e.g., `2.g.i`)
- **Sort Order** (inferred from Section ID, used for display)
- **Tags** (e.g., `anarch`, `caitiff`, `always`)
- **Vote Date** (e.g., *March 10, 2024*)
- **Vote Reference** (e.g., *1000001*)
- **Vote URL** (optional link to vote record or minutes)
- **Bylaw Group** (e.g., `character`, `coordinator`, `council`)

=== 2. Recursive Rendering ===

The `[render_bylaws group="character"]` shortcode:
- Renders nested clauses by parent-child relationships
- Applies visual indentation
- Adds metadata (`data-id`, `data-parent`) for filtering
- Anchors each clause via its Section ID (`#clause-2-g-i-3`)
- Adds a tooltip if vote metadata is present

=== 3. Vote Metadata Tooltips ===

If vote information exists:
- A 📜 icon appears with hover tooltip
- Includes:
  - **Vote Date**
  - **Reference Number**
  - **View Details** link (if URL is set)

Example:  
`Vote Date: March 10, 2024 | Reference: 1000001 | View Details`

=== 4. Tag-Based Filtering with Select2 ===

- Multi-select dropdown using Select2
- Filters by tag (e.g., `anarch`, `caitiff`)
- Includes a "Clear Filters" button
- Ensures:
  - Tagged clauses are shown
  - Ancestor clauses are preserved for context
  - Clauses tagged `always` remain visible

=== 5. Print / Export Support ===

- Button above clause display: **Print / Export PDF**
- Respects active filters (only visible clauses print)

=== 6. Enhanced Admin Interface ===

The `Bylaw Clause` post type dashboard features:
- **Custom Columns**: Bylaw Group, Parent Clause
- **Sorting**: Bylaw Group and Parent Clause
- **Filters**: Dropdown by Bylaw Group
- **Select2 UI**: For parent clause selection
- **Quick Edit**:
  - Inline edits for Bylaw Group and Parent Clause
  - Dynamic filtering of options based on group
  - Section ID + preview visible in dropdown

== Real Example: OWbN Character Bylaws ==

From https://www.owbn.net/bylaws/character, this plugin can model:

    2. Character Creation  
      g. Vampire Characters must have a clearly defined Sect…  
        i. Anarch (Anarch Coordinator Controlled)  
          3. Caitiff  

Plugin Setup:

| Section ID | Post Title | Content Preview                          | Tags             | Parent    |
|------------|------------|------------------------------------------|------------------|-----------|
| `2`        | `2`        | Character Creation                       | always           | *none*    |
| `g`        | `2_g`      | Vampire Characters must have...          |                  | `2`       |
| `i`        | `2_g_i`    | Anarch (Anarch Coordinator Controlled)   | anarch           | `2.g`     |
| `3`        | `2_g_i_3`  | Caitiff                                  | anarch,caitiff   | `2.g.i`   |

**Tip**: Match the WordPress post title to the machine-readable Section ID (e.g., `2_g_i_3`) for easier referencing and maintenance.

== Changelog ==

= 2.0.0 =
* First public release
* Added shortcode support for recursive rendering
* Added tag filtering with Select2
* Added tooltips for vote data
* Supports custom columns, sorting, filters, and quick edit

== Upgrade Notice ==

== License ==

This plugin is licensed under the GNU General Public License v2.0 or later.