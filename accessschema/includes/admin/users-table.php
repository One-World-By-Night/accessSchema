<?php
/**
 * File: includes/admin/users-table.php
 *
 * Adds ASC role column and filter to the WP Admin Users table.
 *
 * @version 2.1.1
 * Author: greghacke
 */

defined( 'ABSPATH' ) || exit;

/**
 * Add the ASC Roles column to the Users table.
 *
 * @since 2.0.3
 *
 * @param string[] $columns Existing column definitions.
 * @return string[] Modified column definitions.
 */
function accessSchema_add_users_column( $columns ) {
	$columns['accessschema_asc_roles'] = __( 'ASC Roles', 'accessschema' );
	return $columns;
}
add_filter( 'manage_users_columns', 'accessSchema_add_users_column' );

/**
 * Render the ASC Roles column content for each user row.
 *
 * Uses batch-loaded role data to avoid N+1 queries.
 *
 * @since 2.0.3
 *
 * @param string $output      The existing column output.
 * @param string $column_name The column being rendered.
 * @param int    $user_id     The user ID for the current row.
 * @return string The column HTML output.
 */
function accessSchema_render_users_column( $output, $column_name, $user_id ) {
	if ( 'accessschema_asc_roles' !== $column_name ) {
		return $output;
	}

	static $roles_cache = null;

	if ( null === $roles_cache ) {
		$roles_cache = accessSchema_batch_get_user_roles_for_list();
	}

	$user_roles = isset( $roles_cache[ $user_id ] ) ? $roles_cache[ $user_id ] : array();

	if ( empty( $user_roles ) ) {
		return '<span class="accessSchema-no-roles">' . esc_html__( 'None', 'accessschema' ) . '</span>';
	}

	// Group roles by top-level category (first path segment).
	$grouped = array();
	foreach ( $user_roles as $role_path ) {
		$parts    = explode( '/', $role_path );
		$category = $parts[0];

		if ( count( $parts ) > 1 ) {
			$remainder = implode( '/', array_slice( $parts, 1 ) );
		} else {
			$remainder = '';
		}

		if ( ! isset( $grouped[ $category ] ) ) {
			$grouped[ $category ] = array();
		}
		$grouped[ $category ][] = array(
			'full_path' => $role_path,
			'display'   => $remainder,
		);
	}

	$html = '<div class="accessSchema-role-list">';
	$cat_index = 0;
	foreach ( $grouped as $category => $roles ) {
		$color_class = 'accessSchema-cat-' . ( $cat_index % 5 );

		$html .= sprintf(
			'<div class="accessSchema-role-group %s">',
			esc_attr( $color_class )
		);
		$html .= sprintf(
			'<span class="accessSchema-role-category">%s</span>',
			esc_html( $category )
		);

		foreach ( $roles as $role ) {
			if ( '' === $role['display'] ) {
				// Single-segment role — already shown as category name.
				continue;
			}
			$html .= sprintf(
				'<span class="accessSchema-role-path-item" title="%s">%s</span>',
				esc_attr( $role['full_path'] ),
				esc_html( $role['display'] )
			);
		}

		$html .= '</div>';
		++$cat_index;
	}
	$html .= '</div>';

	return $html;
}
add_filter( 'manage_users_custom_column', 'accessSchema_render_users_column', 10, 3 );

/**
 * Batch-load all active user-role assignments in a single query.
 *
 * Returns an associative array keyed by user_id, each containing
 * an array of full_path strings.
 *
 * @since 2.0.3
 *
 * @return array<int, string[]> User roles grouped by user ID.
 */
function accessSchema_batch_get_user_roles_for_list() {
	global $wpdb;
	$roles_table      = $wpdb->prefix . 'accessSchema_roles';
	$user_roles_table = $wpdb->prefix . 'accessSchema_user_roles';

	$results = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT ur.user_id, r.full_path
			 FROM {$user_roles_table} ur
			 JOIN {$roles_table} r ON ur.role_id = r.id
			 WHERE ur.is_active = 1
			 AND r.is_active = 1
			 AND (ur.expires_at IS NULL OR ur.expires_at > %s)
			 ORDER BY ur.user_id, r.full_path",
			current_time( 'mysql' )
		),
		ARRAY_A
	);

	$grouped = array();
	foreach ( $results as $row ) {
		$uid = (int) $row['user_id'];
		if ( ! isset( $grouped[ $uid ] ) ) {
			$grouped[ $uid ] = array();
		}
		$grouped[ $uid ][] = $row['full_path'];
	}

	return $grouped;
}

/**
 * Render the ASC role filter UI above the Users table.
 *
 * Outputs a Select2-powered dropdown of all role paths and a text
 * input for wildcard pattern matching.
 *
 * @since 2.0.3
 *
 * @param string $which The position of the filter ('top' or 'bottom').
 * @return void
 */
function accessSchema_users_filter_ui( $which ) {
	if ( 'top' !== $which ) {
		return;
	}

	global $wpdb;
	$roles_table = $wpdb->prefix . 'accessSchema_roles';

	$all_roles = $wpdb->get_results(
		"SELECT full_path FROM {$roles_table}
		 WHERE is_active = 1
		 ORDER BY full_path",
		ARRAY_A
	);

	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Filter parameter.
	$current_role = isset( $_GET['asc_role'] ) ? sanitize_text_field( wp_unslash( $_GET['asc_role'] ) ) : '';
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Filter parameter.
	$current_pattern = isset( $_GET['asc_pattern'] ) ? sanitize_text_field( wp_unslash( $_GET['asc_pattern'] ) ) : '';

	?>
	<label class="screen-reader-text" for="asc_role">
		<?php esc_html_e( 'Filter by ASC role', 'accessschema' ); ?>
	</label>
	<select name="asc_role" id="asc_role" style="min-width: 250px;">
		<option value=""><?php esc_html_e( 'All ASC Roles', 'accessschema' ); ?></option>
		<option value="__none__" <?php selected( $current_role, '__none__' ); ?>>
			<?php esc_html_e( '— No ASC Roles —', 'accessschema' ); ?>
		</option>
		<?php foreach ( $all_roles as $role ) : ?>
			<option value="<?php echo esc_attr( $role['full_path'] ); ?>"
				<?php selected( $current_role, $role['full_path'] ); ?>>
				<?php echo esc_html( $role['full_path'] ); ?>
			</option>
		<?php endforeach; ?>
	</select>

	<label class="screen-reader-text" for="asc_pattern">
		<?php esc_html_e( 'Filter by ASC role pattern', 'accessschema' ); ?>
	</label>
	<input type="text" name="asc_pattern" id="asc_pattern"
		value="<?php echo esc_attr( $current_pattern ); ?>"
		placeholder="<?php esc_attr_e( 'Role pattern (e.g. chronicles/*/hst)', 'accessschema' ); ?>"
		class="accessSchema-pattern-input">
	<?php
}
add_action( 'restrict_manage_users', 'accessSchema_users_filter_ui' );

/**
 * Modify the Users query to filter by ASC role or pattern.
 *
 * Hooks into pre_user_query to add JOINs and WHERE clauses against
 * the accessSchema role tables.
 *
 * @since 2.0.3
 *
 * @param WP_User_Query $query The user query object.
 * @return void
 */
function accessSchema_filter_users_by_role( $query ) {
	if ( ! is_admin() ) {
		return;
	}

	global $pagenow;
	if ( 'users.php' !== $pagenow ) {
		return;
	}

	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Filter parameter.
	$role_filter = isset( $_GET['asc_role'] ) ? sanitize_text_field( wp_unslash( $_GET['asc_role'] ) ) : '';
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Filter parameter.
	$pattern_filter = isset( $_GET['asc_pattern'] ) ? sanitize_text_field( wp_unslash( $_GET['asc_pattern'] ) ) : '';

	if ( empty( $role_filter ) && empty( $pattern_filter ) ) {
		return;
	}

	global $wpdb;
	$roles_table      = $wpdb->prefix . 'accessSchema_roles';
	$user_roles_table = $wpdb->prefix . 'accessSchema_user_roles';

	// Handle "No ASC Roles" filter.
	if ( '__none__' === $role_filter ) {
		$query->query_where .= $wpdb->prepare(
			" AND {$wpdb->users}.ID NOT IN (
				SELECT DISTINCT asc_none_ur.user_id
				FROM {$user_roles_table} asc_none_ur
				JOIN {$roles_table} asc_none_r ON asc_none_ur.role_id = asc_none_r.id
				WHERE asc_none_ur.is_active = 1
				AND asc_none_r.is_active = 1
				AND (asc_none_ur.expires_at IS NULL OR asc_none_ur.expires_at > %s)
			)",
			current_time( 'mysql' )
		);
		return;
	}

	// Add JOINs to ASC tables.
	$query->query_from .= " INNER JOIN {$user_roles_table} asc_ur ON {$wpdb->users}.ID = asc_ur.user_id";
	$query->query_from .= " INNER JOIN {$roles_table} asc_r ON asc_ur.role_id = asc_r.id";

	$query->query_where .= ' AND asc_ur.is_active = 1 AND asc_r.is_active = 1';
	$query->query_where .= $wpdb->prepare(
		' AND (asc_ur.expires_at IS NULL OR asc_ur.expires_at > %s)',
		current_time( 'mysql' )
	);

	// Filter by specific role (includes descendants).
	if ( ! empty( $role_filter ) ) {
		$query->query_where .= $wpdb->prepare(
			' AND (asc_r.full_path = %s OR asc_r.full_path LIKE %s)',
			$role_filter,
			$wpdb->esc_like( $role_filter ) . '/%'
		);
	}

	// Filter by wildcard pattern.
	if ( ! empty( $pattern_filter ) ) {
		$sql_condition = accessSchema_pattern_to_sql_regexp( $pattern_filter );
		if ( $sql_condition ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Prepared inside accessSchema_pattern_to_sql_regexp.
			$query->query_where .= ' AND ' . $sql_condition;
		}
	}

	// Ensure DISTINCT to prevent duplicate rows from multiple role matches.
	if ( false === strpos( $query->query_fields, 'DISTINCT' ) ) {
		$query->query_fields = 'DISTINCT ' . $query->query_fields;
	}
}
add_action( 'pre_user_query', 'accessSchema_filter_users_by_role' );

/**
 * Convert a wildcard role pattern to a MySQL REGEXP condition.
 *
 * Supports * (single path segment) and ** (any number of segments),
 * mirroring the logic in accessSchema_pattern_to_regex().
 *
 * @since 2.0.3
 *
 * @param string $pattern The wildcard pattern (e.g. 'chronicles/ * /hst' or 'coordinators/**').
 * @return string|false The SQL condition string, or false if the pattern is invalid.
 */
function accessSchema_pattern_to_sql_regexp( $pattern ) {
	global $wpdb;

	$pattern = trim( $pattern, '/ ' );

	if ( '' === $pattern ) {
		return false;
	}

	// If no wildcards, use exact match.
	if ( false === strpos( $pattern, '*' ) ) {
		return $wpdb->prepare( 'asc_r.full_path = %s', $pattern );
	}

	// Build MySQL REGEXP: escape special chars, then replace wildcards.
	// MySQL REGEXP uses POSIX-style regex.
	$escaped = preg_quote( $pattern, '' );

	// Replace ** first (before *), then single *.
	$regex = str_replace(
		array( '\\*\\*', '\\*' ),
		array( '.*', '[^/]+' ),
		$escaped
	);

	return $wpdb->prepare( 'asc_r.full_path REGEXP %s', '^' . $regex . '$' );
}

/**
 * Enqueue assets for the Users table page.
 *
 * Loads Select2 for the filter dropdown and the main plugin stylesheet
 * for badge styles.
 *
 * @since 2.0.3
 *
 * @param string $hook The current admin page hook suffix.
 * @return void
 */
function accessSchema_enqueue_users_table_assets( $hook ) {
	if ( 'users.php' !== $hook ) {
		return;
	}

	$base_url = ACCESSSCHEMA_PLUGIN_URL . 'assets/';
	$version  = defined( 'WP_DEBUG' ) && WP_DEBUG ? time() : ACCESSSCHEMA_VERSION;

	// Select2.
	wp_enqueue_style( 'accessSchema-select2', $base_url . 'css/select2.min.css', array(), '4.1.0' );
	wp_enqueue_script( 'accessSchema-select2', $base_url . 'js/select2.min.js', array( 'jquery' ), '4.1.0', true );

	// Plugin styles.
	wp_enqueue_style( 'accessSchema-users-table', $base_url . 'css/accessSchema.css', array( 'accessSchema-select2' ), $version );

	// Initialize Select2 on the filter dropdown.
	wp_add_inline_script(
		'accessSchema-select2',
		'jQuery(document).ready(function($){' .
			'$("#asc_role").select2({' .
				'placeholder: "' . esc_js( __( 'All ASC Roles', 'accessschema' ) ) . '",' .
				'allowClear: true,' .
				'width: "250px"' .
			'});' .
		'});'
	);
}
add_action( 'admin_enqueue_scripts', 'accessSchema_enqueue_users_table_assets' );
