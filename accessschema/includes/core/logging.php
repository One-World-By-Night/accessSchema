<?php
// File: includes/core/logging.php
// @version 1.6.0
// Author: greghacke

defined( 'ABSPATH' ) || exit;

// Setting default log level
function accessSchema_get_log_level() {
    $level = get_option('accessschema_log_level', 'INFO');
    return apply_filters('access_schema_log_level', $level);
}

/* Get the priority of a log level.
 *
 * @param string $level The log level (e.g. 'DEBUG', 'INFO', 'WARN', 'ERROR').
 * @return int Priority of the log level, lower is more important.
 */
function accessSchema_log_level_priority( $level ) {
    $levels = [
        'DEBUG' => 0,
        'INFO'  => 1,
        'WARN'  => 2,
        'ERROR' => 3,
    ];
    return $levels[ strtoupper($level) ] ?? 1;
}

/* Log an accessSchema event to the audit table.
 *
 * @param int         $user_id      The user affected (subject).
 * @param string      $action       Action name (e.g. 'access_granted', 'role_added').
 * @param string      $role_path    The full role path (e.g. 'Chronicles/KONY/HST').
 * @param array|null  $context      Additional context (e.g. route, IP), optional.
 * @param int|null    $performed_by The user who initiated the action (default: current user).
 *
 * @return bool True on success, false on failure.
 */
function accessSchema_log_event( $user_id, $action, $role_path, $context = null, $performed_by = null, $level = 'INFO' ) {
    $current_level = accessSchema_get_log_level();
    if ( accessSchema_log_level_priority( $level ) < accessSchema_log_level_priority( $current_level ) ) {
        return false;
    }

    global $wpdb;
    $table_name = $wpdb->prefix . 'access_audit_log';
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Safe, prepared insert to custom audit log table
    $result = $wpdb->insert(
        $table_name,
        [
            'user_id'      => $user_id,
            'action'       => sanitize_text_field( $action ),
            'role_path'    => sanitize_text_field( $role_path ),
            'context'      => is_null( $context ) ? null : maybe_serialize( $context ),
            'performed_by' => $performed_by ?? get_current_user_id(),
            'created_at'   => current_time( 'mysql' ),
        ],
        [ '%d', '%s', '%s', '%s', '%d', '%s' ]
    );

    return $result !== false;
}
