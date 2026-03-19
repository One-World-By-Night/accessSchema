<?php
/**
 * accessSchema Rules Admin UI
 *
 * Admin page for managing grant/assignment rules.
 */

defined( 'ABSPATH' ) || exit;

add_action( 'admin_menu', 'accessSchema_register_rules_menu' );

function accessSchema_register_rules_menu() {
	add_users_page(
		__( 'Access Schema Rules', 'accessschema' ),
		__( 'accessSchema Rules', 'accessschema' ),
		'manage_access_schema',
		'accessSchema-rules',
		'accessSchema_render_rules_page'
	);
}

/**
 * Handle form submissions for rules.
 */
add_action( 'admin_init', 'accessSchema_handle_rules_actions' );

function accessSchema_handle_rules_actions() {
	if ( ! current_user_can( 'manage_access_schema' ) ) {
		return;
	}

	global $wpdb;
	$table = $wpdb->prefix . 'accessSchema_rules';

	// Add rule
	if ( isset( $_POST['accessSchema_add_rule'] ) && check_admin_referer( 'accessSchema_rules_nonce' ) ) {
		$type    = sanitize_text_field( $_POST['rule_type'] ?? 'exclusion' );
		$trigger = sanitize_text_field( $_POST['trigger_pattern'] ?? '' );
		$target  = sanitize_text_field( $_POST['target_pattern'] ?? '' );
		$desc    = sanitize_text_field( $_POST['description'] ?? '' );

		if ( $trigger && $target ) {
			$wpdb->insert( $table, [
				'rule_type'       => $type,
				'trigger_pattern' => $trigger,
				'target_pattern'  => $target,
				'description'     => $desc,
				'is_active'       => 1,
			], [ '%s', '%s', '%s', '%s', '%d' ] );

			wp_cache_delete( 'active_rules', 'accessSchema' );

			wp_safe_redirect( add_query_arg( 'msg', 'added', wp_get_referer() ?: admin_url( 'users.php?page=accessSchema-rules' ) ) );
			exit;
		}
	}

	// Toggle active/inactive
	if ( isset( $_GET['toggle_rule'] ) && check_admin_referer( 'toggle_rule_' . $_GET['toggle_rule'] ) ) {
		$rule_id = absint( $_GET['toggle_rule'] );
		$current = $wpdb->get_var( $wpdb->prepare( "SELECT is_active FROM {$table} WHERE id = %d", $rule_id ) );
		if ( $current !== null ) {
			$wpdb->update( $table, [ 'is_active' => $current ? 0 : 1 ], [ 'id' => $rule_id ], [ '%d' ], [ '%d' ] );
			wp_cache_delete( 'active_rules', 'accessSchema' );
		}
		wp_safe_redirect( remove_query_arg( [ 'toggle_rule', '_wpnonce' ], wp_get_referer() ?: admin_url( 'users.php?page=accessSchema-rules' ) ) );
		exit;
	}

	// Delete rule
	if ( isset( $_GET['delete_rule'] ) && check_admin_referer( 'delete_rule_' . $_GET['delete_rule'] ) ) {
		$rule_id = absint( $_GET['delete_rule'] );
		$wpdb->delete( $table, [ 'id' => $rule_id ], [ '%d' ] );
		wp_cache_delete( 'active_rules', 'accessSchema' );
		wp_safe_redirect( remove_query_arg( [ 'delete_rule', '_wpnonce' ], wp_get_referer() ?: admin_url( 'users.php?page=accessSchema-rules' ) ) );
		exit;
	}
}

function accessSchema_render_rules_page() {
	if ( ! current_user_can( 'manage_access_schema' ) ) {
		wp_die( esc_html__( 'Insufficient permissions', 'accessschema' ) );
	}

	global $wpdb;
	$table = $wpdb->prefix . 'accessSchema_rules';
	$rules = $wpdb->get_results( "SELECT * FROM {$table} ORDER BY is_active DESC, id ASC", ARRAY_A );
	if ( ! is_array( $rules ) ) {
		$rules = [];
	}

	$msg = sanitize_text_field( $_GET['msg'] ?? '' );
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'Access Schema Rules', 'accessschema' ); ?></h1>

		<?php if ( $msg === 'added' ) : ?>
			<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Rule added.', 'accessschema' ); ?></p></div>
		<?php endif; ?>

		<div class="notice notice-info">
			<p>
				<?php esc_html_e( 'Rules are evaluated when a role is granted. They do NOT apply to revokes.', 'accessschema' ); ?><br>
				<?php esc_html_e( 'Wildcards: * matches one segment, ** matches any depth.', 'accessschema' ); ?>
			</p>
		</div>

		<h2><?php esc_html_e( 'Add Rule', 'accessschema' ); ?></h2>

		<form method="post">
			<?php wp_nonce_field( 'accessSchema_rules_nonce' ); ?>

			<table class="form-table">
				<tr>
					<th scope="row"><label for="rule_type"><?php esc_html_e( 'Type', 'accessschema' ); ?></label></th>
					<td>
						<select name="rule_type" id="rule_type">
							<option value="exclusion"><?php esc_html_e( 'Exclusion — "If granted A, must NOT already hold B"', 'accessschema' ); ?></option>
						</select>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="trigger_pattern"><?php esc_html_e( 'Trigger Pattern', 'accessschema' ); ?></label></th>
					<td>
						<input type="text" name="trigger_pattern" id="trigger_pattern" class="regular-text" required
							placeholder="chronicle/*/cm" />
						<p class="description"><?php esc_html_e( 'The role being granted must match this pattern for the rule to fire.', 'accessschema' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="target_pattern"><?php esc_html_e( 'Conflict Pattern', 'accessschema' ); ?></label></th>
					<td>
						<input type="text" name="target_pattern" id="target_pattern" class="regular-text" required
							placeholder="chronicle/*/cm" />
						<p class="description"><?php esc_html_e( 'If the user already holds a role matching this, the grant is denied.', 'accessschema' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="description"><?php esc_html_e( 'Description', 'accessschema' ); ?></label></th>
					<td>
						<input type="text" name="description" id="description" class="large-text"
							placeholder="A user can only be CM of one chronicle at a time" />
					</td>
				</tr>
			</table>

			<p class="submit">
				<button type="submit" name="accessSchema_add_rule" class="button button-primary">
					<?php esc_html_e( 'Add Rule', 'accessschema' ); ?>
				</button>
			</p>
		</form>

		<hr>

		<h2><?php esc_html_e( 'Active Rules', 'accessschema' ); ?></h2>

		<?php if ( empty( $rules ) ) : ?>
			<p><?php esc_html_e( 'No rules configured.', 'accessschema' ); ?></p>
		<?php else : ?>
			<table class="widefat fixed striped">
				<thead>
					<tr>
						<th style="width:40px;"><?php esc_html_e( 'ID', 'accessschema' ); ?></th>
						<th style="width:80px;"><?php esc_html_e( 'Type', 'accessschema' ); ?></th>
						<th><?php esc_html_e( 'Trigger', 'accessschema' ); ?></th>
						<th><?php esc_html_e( 'Conflict', 'accessschema' ); ?></th>
						<th><?php esc_html_e( 'Description', 'accessschema' ); ?></th>
						<th style="width:60px;"><?php esc_html_e( 'Active', 'accessschema' ); ?></th>
						<th style="width:120px;"><?php esc_html_e( 'Actions', 'accessschema' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $rules as $rule ) : ?>
						<tr<?php echo $rule['is_active'] ? '' : ' style="opacity:0.5;"'; ?>>
							<td><?php echo esc_html( $rule['id'] ); ?></td>
							<td><?php echo esc_html( $rule['rule_type'] ); ?></td>
							<td><code><?php echo esc_html( $rule['trigger_pattern'] ); ?></code></td>
							<td><code><?php echo esc_html( $rule['target_pattern'] ); ?></code></td>
							<td><?php echo esc_html( $rule['description'] ); ?></td>
							<td><?php echo $rule['is_active'] ? '&#9989;' : '&#10060;'; ?></td>
							<td>
								<a href="<?php echo esc_url( wp_nonce_url(
									add_query_arg( 'toggle_rule', $rule['id'] ),
									'toggle_rule_' . $rule['id']
								) ); ?>"><?php echo $rule['is_active']
									? esc_html__( 'Disable', 'accessschema' )
									: esc_html__( 'Enable', 'accessschema' ); ?></a>
								|
								<a href="<?php echo esc_url( wp_nonce_url(
									add_query_arg( 'delete_rule', $rule['id'] ),
									'delete_rule_' . $rule['id']
								) ); ?>" onclick="return confirm('Delete this rule?');"
									style="color:#a00;"><?php esc_html_e( 'Delete', 'accessschema' ); ?></a>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif; ?>
	</div>
	<?php
}
