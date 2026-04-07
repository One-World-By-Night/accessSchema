# accessSchema

The permission system for One World by Night. Controls who can do what across all OWBN websites.

## What It Does

accessSchema is a hierarchical role-based access control (RBAC) system for WordPress. It manages roles like `chronicle/mckn/storyteller` or `coordinator/vampire/coordinator` in a tree structure. One central server (sso.owbn.net) holds all roles. Every other OWBN site checks permissions against it.

## What's In This Repo

| Directory | Purpose | Version |
|-----------|---------|---------|
| `accessschema/` | Server plugin — role management, REST API, audit logging, admin UI | 2.5.0 |
| `accessschema/accessschema-client/` | Embeddable client — lets other plugins check roles remotely or locally | 2.5.0 |
| `gf-accessschema-user-registration/` | Gravity Forms addon — creates WordPress users with roles pre-assigned | 1.2.0 |

## Architecture

- **Server** runs on sso.owbn.net. Single source of truth for all roles.
- **Client** lives in owbn-core (includes/accessschema/), which is installed on every OWBN site. Talks to the server via REST API (remote mode), reads the local DB (local mode), or falls back to WordPress capabilities (disabled mode).
- **Capability mapping** translates role paths into WordPress caps per-site. Each site defines its own map. See CAPABILITY-MAP.md.

## Role Structure

Roles are slash-delimited paths organized in three tiers:

- chronicle/{slug}/{position} — Chronicle staff (cm, hst, staff)
- coordinator/{slug}/{position} — Genre/clan coordinators and sub-coordinators
- exec/{office}/{position} — Executive team (hc, ahc1, ahc2, archivist, web)

Parent roles inherit access to children. A check against chronicle/mckn matches chronicle/mckn/hst.

## Deployment

| Site | What's Installed |
|------|-----------------|
| sso.owbn.net | Server plugin + GF addon |
| All other OWBN sites | Client (in owbn-core, installed on every site) |

## Technical Reference

- Server plugin docs: accessschema/README.md
- Version history: accessschema/VERSION_HISTORY.md
- Capability map: CAPABILITY-MAP.md

## Requirements

- WordPress 5.0+, PHP 7.4+, MySQL 5.6+

## License

GPL-2.0-or-later
