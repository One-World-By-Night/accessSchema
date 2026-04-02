# AccessSchema Capability Map

How ASC role paths translate to WordPress capabilities across OWBN sites.

## How It Works

Each site stores a `{client_id}_capability_map` option mapping WP capabilities to ASC role patterns. When a user logs in, accessSchema checks their ASC roles against these patterns and dynamically grants/revokes WP capabilities.

`$slug` in patterns is replaced with the entity slug from the CPT post being accessed.

## Council / Chronicles (owbn-chronicle-manager)

| WP Capability | Granted To (ASC Role Patterns) |
|---------------|-------------------------------|
| `edit_posts` | `exec/ahc1/coordinator`, `exec/ahc2/coordinator`, `exec/hc/coordinator` |
| `publish_posts` | `exec/ahc1/coordinator`, `exec/ahc2/coordinator`, `exec/hc/coordinator` |
| `ocm_view_list` | `chronicle/*/hst`, `chronicle/*/cm`, `coordinator/*/coordinator` |
| `edit_owbn_chronicle` | `chronicle/$slug/cm`, `chronicle/$slug/hst` |
| `edit_owbn_coordinator` | `coordinator/$slug/coordinator` |
| `ocm_create_chronicle` | `exec/ahc1/coordinator`, `exec/ahc2/coordinator`, `exec/hc/coordinator` |
| `ocm_create_coordinator` | `exec/ahc1/coordinator`, `exec/ahc2/coordinator`, `exec/hc/coordinator` |

## WP Roles Created by owbn-chronicle-manager

| WP Role | Capabilities |
|---------|-------------|
| `web_team` | All admin caps + all OCM caps |
| `exec_team` | All OCM caps (view, edit, create, delete for chronicles + coordinators) |
| `chron_staff` | `read`, `edit_posts`, `edit_others_posts`, `ocm_view_list`, `ocm_view_chronicle`, `ocm_edit_chronicle`, `edit_owbn_chronicle`, `read_owbn_chronicle` |
| `coord_staff` | `read`, `edit_posts`, `ocm_view_list`, `edit_owbn_coordinator`, `read_owbn_coordinator` |

## Staff Role Mapping (Chronicle CPT)

When chronicle staff fields change, owbn-chronicle-manager syncs ASC roles:

| Chronicle Field | ASC Role Path |
|----------------|---------------|
| `hst_info` (Head Storyteller) | `chronicle/{slug}/hst` |
| `cm_info` (Council Member) | `chronicle/{slug}/cm` |
| `ast_list` (Assistant STs) | `chronicle/{slug}/staff` |

## Staff Role Mapping (Coordinator CPT)

| Coordinator Field | ASC Role Path |
|------------------|---------------|
| `coord_info` (Coordinator) | `coordinator/{slug}/coordinator` or `exec/{slug}/coordinator` |
| `subcoord_list` (Sub-coordinators) | `coordinator/{slug}/sub-coordinator` or `exec/{slug}/staff` |

Administrative coordinators use `exec/` prefix. Genre/Clan coordinators use `coordinator/` prefix.

## Archivist (OAT)

OAT uses ASC roles directly without a capability map. Role checks happen in PHP:

| Action | Required Role Pattern |
|--------|----------------------|
| View own entries | Any authenticated user |
| View chronicle entries | `chronicle/{slug}/hst`, `chronicle/{slug}/cm`, `chronicle/{slug}/staff` |
| View coordinator entries | `coordinator/{slug}/coordinator`, `coordinator/{slug}/sub-coordinator` |
| Archivist (all access) | `exec/archivist/coordinator` |
| Super user (fast-track) | `exec/archivist/coordinator`, `exec/web/coordinator`, `exec/admin/coordinator` |
| Self-approve | `manage_options` or `exec/archivist/coordinator` |
| Character creation gate | Configurable via `oat_character_create_roles` option (Settings > General) |

## SSO (accessSchema server)

SSO is the authority for all roles. Roles are stored per-user and served via OAuth. The capability map on SSO is empty — SSO doesn't need to map roles to WP capabilities locally.
