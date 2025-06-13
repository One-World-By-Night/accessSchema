<?php
// File: includes/core/role-tree.php
// @version 1.6.0
// Author: greghacke

defined( 'ABSPATH' ) || exit;

/* Register roles hierarchically under a group and subkey.
 *
 * @param string $group   Top-level group name (e.g. 'Chronicles').
 * @param string $sub     Sub-level under group (e.g. 'MCKN').
 * @param array  $roles   Final role keys (e.g. ['CM', 'HST', 'Player']).
 */
function accessSchema_register_roles( $group, $sub, $roles ) {
    global $wpdb;
    $table = $wpdb->prefix . 'access_roles';

    $group   = sanitize_text_field( $group );
    $sub     = sanitize_text_field( $sub );
    $roles   = array_map( 'sanitize_text_field', $roles );

    // Insert or get group-level ID
    $group_id = accessSchema_get_or_create_role_node( $group );

    // Insert or get subkey under group
    $sub_id = accessSchema_get_or_create_role_node( $sub, $group_id );

    // Insert roles under sub
    foreach ( $roles as $role ) {
        accessSchema_get_or_create_role_node( $role, $sub_id );
    }
}

function accessSchema_register_path(array $segments) {
    global $wpdb;
    $table = $wpdb->prefix . 'access_roles';

    $parent_id = null;
    $accumulated_path = '';

    foreach ($segments as $i => $name) {
        $name = trim($name);
        if ($name === '') {
            return;
        }

        $accumulated_path = $accumulated_path === '' ? $name : $accumulated_path . '/' . $name;

        // Check for existing node
        if (is_null($parent_id)) {
            $existing = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$table} WHERE name = %s AND parent_id IS NULL",
                $name
            ));
        } else {
            $existing = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$table} WHERE name = %s AND parent_id = %d",
                $name,
                $parent_id
            ));
        }

        if ($existing) {
            $parent_id = (int) $existing;
            continue;
        }

        $wpdb->insert($table, [
            'parent_id' => $parent_id,
            'name'      => $name,
            'full_path' => $accumulated_path,
        ], ['%d', '%s', '%s']);

        $parent_id = $wpdb->insert_id;
    }
}

/* Insert or retrieve role ID by name and optional parent.
 *
 * @param string   $name       Role name (e.g. 'CM', 'Chronicles').
 * @param int|null $parent_id  Parent role ID or null.
 * @return int                 ID of the role node.
 */
function accessSchema_get_or_create_role_node( $name, $parent_id = null ) {
    global $wpdb;
    $table = $wpdb->prefix . 'access_roles';

    $name = sanitize_text_field( $name );

    $existing_id = $wpdb->get_var( $wpdb->prepare(
        "SELECT id FROM $table WHERE name = %s AND parent_id " . ( is_null($parent_id) ? "IS NULL" : "= %d" ), // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        ...(is_null($parent_id) ? [$name] : [$name, $parent_id])
    ));

    if ( $existing_id ) {
        return (int) $existing_id;
    }

    // Compute full path
    $full_path = $name;
    if ( ! is_null( $parent_id ) ) {
        $parent_path = $wpdb->get_var( $wpdb->prepare(
            "SELECT full_path FROM $table WHERE id = %d",
            $parent_id
        ));
        if ( $parent_path ) {
            $full_path = $parent_path . '/' . $name;
        }
    }

    $wpdb->insert( $table, [
        'name'       => $name,
        'parent_id'  => $parent_id,
        'full_path'  => $full_path,
        'created_at' => current_time( 'mysql' ),
    ], [ '%s', is_null($parent_id) ? 'NULL' : '%d', '%s', '%s' ]);

    return (int) $wpdb->insert_id;
}

/* Check if a role path exists in the registered roles.
 *
 * @param string $role_path e.g. 'Chronicles/MCKN/HST'
 * @return bool True if the role exists, false otherwise.
 */
function accessSchema_role_exists( $role_path ) {
    global $wpdb;
    $table = $wpdb->prefix . 'access_roles';

    $parts = explode( '/', trim( $role_path ) );
    if ( count( $parts ) < 1 ) {
        return false;
    }

    $parent_id = null;
    foreach ( $parts as $part ) {
        $part = sanitize_text_field( $part );
        $id = $wpdb->get_var( $wpdb->prepare(
            "SELECT id FROM $table WHERE name = %s AND " . ( is_null($parent_id) ? "parent_id IS NULL" : "parent_id = %d" ), // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
            ...(is_null($parent_id) ? [$part] : [$part, $parent_id])
        ));
        if ( ! $id ) {
            return false;
        }
        $parent_id = $id;
    }

    return true;
}