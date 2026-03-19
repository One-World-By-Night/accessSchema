<?php
/**
 * accessSchema Rules Engine
 *
 * Evaluates rules before granting roles. Hooks into the existing
 * accessSchema_validate_role_assignment filter.
 *
 * Rule types:
 *   - exclusion: "If you hold A, you cannot also hold B"
 *   - approval:  "Granting roles under this path requires approval" (future)
 */

defined( 'ABSPATH' ) || exit;

/**
 * Create the rules table.
 * Called from accessSchema_create_tables() in activation.php.
 */
function accessSchema_create_rules_table() {
	global $wpdb;

	$charset_collate = $wpdb->get_charset_collate();
	require_once ABSPATH . 'wp-admin/includes/upgrade.php';

	$table = $wpdb->prefix . 'accessSchema_rules';
	$sql   = "CREATE TABLE {$table} (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		rule_type varchar(32) NOT NULL DEFAULT 'exclusion',
		trigger_pattern varchar(500) NOT NULL,
		target_pattern varchar(500) NOT NULL,
		description varchar(500) DEFAULT '',
		is_active tinyint(1) DEFAULT 1,
		created_at datetime DEFAULT CURRENT_TIMESTAMP,
		PRIMARY KEY  (id),
		KEY idx_rule_type (rule_type),
		KEY idx_is_active (is_active)
	) {$charset_collate}";

	dbDelta( $sql );

	return ! $wpdb->last_error;
}

/**
 * Get all active rules.
 *
 * @return array
 */
function accessSchema_get_active_rules() {
	global $wpdb;
	$table = $wpdb->prefix . 'accessSchema_rules';

	$cached = wp_cache_get( 'active_rules', 'accessSchema' );
	if ( false !== $cached ) {
		return $cached;
	}

	// phpcs:ignore WordPress.DB.DirectDatabaseQuery
	$rules = $wpdb->get_results(
		"SELECT * FROM {$table} WHERE is_active = 1 ORDER BY id ASC",
		ARRAY_A
	);

	if ( ! is_array( $rules ) ) {
		$rules = [];
	}

	wp_cache_set( 'active_rules', $rules, 'accessSchema', 3600 );
	return $rules;
}

/**
 * Check if a role path matches a pattern.
 *
 * Patterns:
 *   - Exact match: "coordinator/sabbat/coordinator"
 *   - Single wildcard: "chronicle/*/cm" matches "chronicle/kony/cm"
 *   - Double wildcard: "player/under-da/**" matches "player/under-da/anything/deep"
 *
 * @param string $pattern The rule pattern.
 * @param string $path    The role path to check.
 * @return bool
 */
function accessSchema_pattern_matches( string $pattern, string $path ): bool {
	// Exact match
	if ( $pattern === $path ) {
		return true;
	}

	$pattern_parts = explode( '/', $pattern );
	$path_parts    = explode( '/', $path );

	return accessSchema_match_parts( $pattern_parts, $path_parts, 0, 0 );
}

/**
 * Recursive pattern matching for path segments.
 */
function accessSchema_match_parts( array $pattern, array $path, int $pi, int $qi ): bool {
	// Both exhausted = match
	if ( $pi >= count( $pattern ) && $qi >= count( $path ) ) {
		return true;
	}

	// Pattern exhausted but path has more = no match
	if ( $pi >= count( $pattern ) ) {
		return false;
	}

	$seg = $pattern[ $pi ];

	// ** matches zero or more remaining segments
	if ( $seg === '**' ) {
		// If ** is last, matches everything remaining
		if ( $pi === count( $pattern ) - 1 ) {
			return true;
		}
		// Try matching ** against 0..N segments
		for ( $skip = $qi; $skip <= count( $path ); $skip++ ) {
			if ( accessSchema_match_parts( $pattern, $path, $pi + 1, $skip ) ) {
				return true;
			}
		}
		return false;
	}

	// Path exhausted but pattern has more (non-**) = no match
	if ( $qi >= count( $path ) ) {
		return false;
	}

	// * matches exactly one segment
	if ( $seg === '*' ) {
		return accessSchema_match_parts( $pattern, $path, $pi + 1, $qi + 1 );
	}

	// Literal match
	if ( $seg === $path[ $qi ] ) {
		return accessSchema_match_parts( $pattern, $path, $pi + 1, $qi + 1 );
	}

	return false;
}

/**
 * Evaluate all active rules against a proposed role assignment.
 *
 * @param int      $user_id        The user being assigned the role.
 * @param string   $new_role       The role path being granted.
 * @param string[] $existing_roles The user's current role paths.
 * @return array   [ 'allowed' => bool, 'reason' => string, 'rule_id' => int|null ]
 */
function accessSchema_evaluate_rules( int $user_id, string $new_role, array $existing_roles ): array {
	$rules = accessSchema_get_active_rules();

	foreach ( $rules as $rule ) {
		$type    = $rule['rule_type'];
		$trigger = $rule['trigger_pattern'];
		$target  = $rule['target_pattern'];

		if ( $type === 'exclusion' ) {
			// "If the new role matches trigger_pattern, user must NOT already hold target_pattern"
			if ( ! accessSchema_pattern_matches( $trigger, $new_role ) ) {
				continue;
			}

			foreach ( $existing_roles as $existing ) {
				// Skip if existing role is the same as the new role (re-grant)
				if ( $existing === $new_role ) {
					continue;
				}
				if ( accessSchema_pattern_matches( $target, $existing ) ) {
					$desc = $rule['description'] ?: "Conflicts with existing role: {$existing}";
					accessSchema_log_audit(
						$user_id,
						'rule_blocked',
						$new_role,
						"Rule #{$rule['id']}: {$desc} (held: {$existing})"
					);
					return [
						'allowed' => false,
						'reason'  => $desc,
						'rule_id' => (int) $rule['id'],
					];
				}
			}
		}
	}

	return [ 'allowed' => true, 'reason' => '', 'rule_id' => null ];
}

/**
 * Filter hook: validate role assignment against rules engine.
 *
 * Hooks into the existing accessSchema_validate_role_assignment filter.
 *
 * @param bool     $valid          Current validity.
 * @param int      $user_id        The user ID.
 * @param string   $new_role       The role path being assigned.
 * @param string[] $existing_roles The user's current roles.
 * @return bool
 */
function accessSchema_rules_filter( $valid, $user_id, $new_role, $existing_roles ) {
	if ( ! $valid ) {
		return false; // Already blocked by another filter
	}

	$result = accessSchema_evaluate_rules( $user_id, $new_role, $existing_roles );

	if ( ! $result['allowed'] ) {
		// Store the denial reason in a transient so the grant endpoint can include it
		set_transient(
			'accessSchema_grant_denial_' . $user_id . '_' . md5( $new_role ),
			$result,
			60
		);
		return false;
	}

	return true;
}
add_filter( 'accessSchema_validate_role_assignment', 'accessSchema_rules_filter', 10, 4 );

/**
 * Log an audit event.
 * Wrapper that checks if the main audit function exists.
 */
function accessSchema_log_audit( int $user_id, string $action, string $role_path, string $context = '' ) {
	if ( function_exists( 'accessSchema_log_event' ) ) {
		accessSchema_log_event( $user_id, $action, $role_path, $context );
	}
}
