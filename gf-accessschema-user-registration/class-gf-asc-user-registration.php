<?php
/**
 * Gravity Forms AccessSchema User Registration Feed Add-On.
 *
 * @package GF_ASC_User_Registration
 * @since   1.0.0
 */

defined( 'ABSPATH' ) || die();

GFForms::include_feed_addon_framework();

/**
 * Feed Add-On for creating WordPress users with AccessSchema role assignment.
 *
 * @since 1.0.0
 *
 * @see GFFeedAddOn
 */
class GF_ASC_User_Registration extends GFFeedAddOn {

	/**
	 * Add-on version.
	 *
	 * @var string
	 */
	protected $_version = GF_ASC_UR_VERSION;

	/**
	 * Minimum Gravity Forms version required.
	 *
	 * @var string
	 */
	protected $_min_gravityforms_version = '2.5';

	/**
	 * Plugin slug.
	 *
	 * @var string
	 */
	protected $_slug = 'gf-accessschema-user-registration';

	/**
	 * Main plugin file path relative to plugins folder.
	 *
	 * @var string
	 */
	protected $_path = 'gf-accessschema-user-registration/gf-accessschema-user-registration.php';

	/**
	 * Full path to this class file.
	 *
	 * @var string
	 */
	protected $_full_path = __FILE__;

	/**
	 * Plugin URL.
	 *
	 * @var string
	 */
	protected $_url = 'https://github.com/One-World-By-Night/gf-accessschema-user-registration';

	/**
	 * Plugin title.
	 *
	 * @var string
	 */
	protected $_title = 'AccessSchema User Registration';

	/**
	 * Short title for menus.
	 *
	 * @var string
	 */
	protected $_short_title = 'ASC Registration';

	/**
	 * Only process one feed per submission.
	 *
	 * @var bool
	 */
	protected $_single_feed_submission = true;

	/**
	 * Enable async feed processing for Gravity Flow compatibility.
	 *
	 * @var bool
	 */
	protected $_async_feed_processing = true;

	/**
	 * Singleton instance.
	 *
	 * @var GF_ASC_User_Registration|null
	 */
	private static $_instance = null;

	/**
	 * Get singleton instance.
	 *
	 * @since 1.0.0
	 *
	 * @return GF_ASC_User_Registration
	 */
	public static function get_instance() {
		if ( null === self::$_instance ) {
			self::$_instance = new self();
		}
		return self::$_instance;
	}

	/**
	 * Initialize the add-on.
	 *
	 * @since 1.0.0
	 */
	public function init() {
		parent::init();

		// Register the AJAX handler for role search.
		add_action( 'wp_ajax_gf_asc_search_roles', array( $this, 'ajax_search_roles' ) );

		// Register the AJAX handler for manual feed processing.
		add_action( 'wp_ajax_gf_asc_process_entry', array( $this, 'ajax_process_entry' ) );

		// Add "Create Account" panel on entry detail page (main content, below notes).
		add_action( 'gform_entry_detail', array( $this, 'render_entry_meta_box' ), 10, 2 );
	}

	/**
	 * Return the plugin's icon for the plugin/form settings menu.
	 *
	 * @since 1.0.0
	 *
	 * @return string
	 */
	public function get_menu_icon() {
		return 'dashicons-admin-users';
	}

	// -------------------------------------------------------------------------
	// Scripts & Styles
	// -------------------------------------------------------------------------

	/**
	 * Enqueue scripts for the add-on.
	 *
	 * @since 1.0.0
	 *
	 * @return array
	 */
	public function scripts() {
		$scripts = array(
			array(
				'handle'  => 'gf_asc_select2',
				'src'     => $this->get_base_url() . '/assets/js/select2.min.js',
				'version' => $this->_version,
				'deps'    => array( 'jquery' ),
				'enqueue' => array(
					array( $this, 'should_enqueue_assets' ),
				),
			),
			array(
				'handle'  => 'gf_asc_roles_field',
				'src'     => $this->get_base_url() . '/assets/js/asc-roles-field.js',
				'version' => $this->_version,
				'deps'    => array( 'jquery', 'gf_asc_select2' ),
				'enqueue' => array(
					array( $this, 'should_enqueue_assets' ),
				),
				'strings' => array(
					'ajaxurl'     => admin_url( 'admin-ajax.php' ),
					'nonce'       => wp_create_nonce( 'gf_asc_search_roles' ),
					'placeholder' => esc_html__( 'Search AccessSchema roles...', 'gf-asc-user-registration' ),
				),
			),
		);

		return array_merge( parent::scripts(), $scripts );
	}

	/**
	 * Enqueue styles for the add-on.
	 *
	 * @since 1.0.0
	 *
	 * @return array
	 */
	public function styles() {
		$styles = array(
			array(
				'handle'  => 'gf_asc_select2_css',
				'src'     => $this->get_base_url() . '/assets/css/select2.min.css',
				'version' => $this->_version,
				'enqueue' => array(
					array( $this, 'should_enqueue_assets' ),
				),
			),
			array(
				'handle'  => 'gf_asc_roles_field_css',
				'src'     => $this->get_base_url() . '/assets/css/asc-roles-field.css',
				'version' => $this->_version,
				'enqueue' => array(
					array( $this, 'should_enqueue_assets' ),
				),
			),
		);

		return array_merge( parent::styles(), $styles );
	}

	/**
	 * Determine if assets should be enqueued on the current page.
	 *
	 * @since 1.0.0
	 *
	 * @return bool
	 */
	public function should_enqueue_assets() {
		// Enqueue on entry detail, form editor, and form settings pages.
		if ( function_exists( 'rgget' ) ) {
			$page = rgget( 'page' );
			$view = rgget( 'view' );
			if ( 'gf_entries' === $page && 'entry' === $view ) {
				return true;
			}
			if ( 'gf_edit_forms' === $page ) {
				return true;
			}
		}

		// Enqueue on Gravity Flow inbox/status pages.
		if ( class_exists( 'Gravity_Flow' ) ) {
			$gflow_page = rgget( 'page' );
			if ( 'gravityflow-inbox' === $gflow_page || 'gravityflow-status' === $gflow_page ) {
				return true;
			}
		}

		return false;
	}

	// -------------------------------------------------------------------------
	// Feed Settings
	// -------------------------------------------------------------------------

	/**
	 * Define the fields for configuring a feed.
	 *
	 * @since 1.0.0
	 *
	 * @return array
	 */
	public function feed_settings_fields() {

		$fields = array();

		// Section 1: Feed name.
		$fields['feed_settings'] = array(
			'title'  => esc_html__( 'Feed Settings', 'gf-asc-user-registration' ),
			'fields' => array(
				array(
					'name'     => 'feedName',
					'label'    => esc_html__( 'Name', 'gf-asc-user-registration' ),
					'type'     => 'text',
					'required' => true,
					'class'    => 'medium',
					'tooltip'  => esc_html__( 'Enter a name to identify this feed.', 'gf-asc-user-registration' ),
				),
			),
		);

		// Section 2: User field mappings.
		$fields['user_settings'] = array(
			'title'       => esc_html__( 'User Settings', 'gf-asc-user-registration' ),
			'description' => esc_html__( 'Map each setting to the appropriate form field. For fields where the applicant provides multiple choices (e.g., username preferences), map to the admin-only "Assigned" field where the reviewer enters the final value.', 'gf-asc-user-registration' ),
			'fields'      => array(
				array(
					'name'     => 'username',
					'label'    => esc_html__( 'Username', 'gf-asc-user-registration' ),
					'type'     => 'field_select',
					'required' => true,
					'class'    => 'medium',
					'tooltip'  => esc_html__( 'Map to the admin-only "Assigned Username" field where the reviewer enters the final username.', 'gf-asc-user-registration' ),
				),
				array(
					'name'     => 'email',
					'label'    => esc_html__( 'Email Address', 'gf-asc-user-registration' ),
					'type'     => 'field_select',
					'required' => true,
					'class'    => 'medium',
					'args'     => array(
						'input_types' => array( 'email' ),
					),
					'tooltip'  => esc_html__( 'Map to the applicant\'s email field.', 'gf-asc-user-registration' ),
				),
				array(
					'name'    => 'first_name',
					'label'   => esc_html__( 'First Name', 'gf-asc-user-registration' ),
					'type'    => 'field_select',
					'class'   => 'medium',
					'tooltip' => esc_html__( 'Map to the applicant\'s first name field (e.g., Verification Name - First).', 'gf-asc-user-registration' ),
				),
				array(
					'name'    => 'last_name',
					'label'   => esc_html__( 'Last Name', 'gf-asc-user-registration' ),
					'type'    => 'field_select',
					'class'   => 'medium',
					'tooltip' => esc_html__( 'Map to the applicant\'s last name field (e.g., Verification Name - Last).', 'gf-asc-user-registration' ),
				),
				array(
					'name'    => 'player_id',
					'label'   => esc_html__( 'Player ID', 'gf-asc-user-registration' ),
					'type'    => 'field_select',
					'class'   => 'medium',
					'tooltip' => esc_html__( 'Map to the admin-only "Assigned Player ID" field where the reviewer enters the final Player ID.', 'gf-asc-user-registration' ),
				),
				array(
					'name'          => 'role',
					'label'         => esc_html__( 'WordPress Role', 'gf-asc-user-registration' ),
					'type'          => 'select',
					'required'      => true,
					'class'         => 'medium',
					'choices'       => $this->get_wp_roles_as_choices(),
					'default_value' => 'subscriber',
					'tooltip'       => esc_html__( 'Select the WordPress role to assign to the new user.', 'gf-asc-user-registration' ),
				),
				array(
					'name'          => 'password_mode',
					'label'         => esc_html__( 'Password', 'gf-asc-user-registration' ),
					'type'          => 'select',
					'class'         => 'medium',
					'default_value' => 'email_link',
					'choices'       => array(
						array(
							'label' => esc_html__( 'Auto-generate & send set-password email', 'gf-asc-user-registration' ),
							'value' => 'email_link',
						),
						array(
							'label' => esc_html__( 'Auto-generate (no email)', 'gf-asc-user-registration' ),
							'value' => 'auto_silent',
						),
					),
					'tooltip'       => esc_html__( 'How the user password should be handled.', 'gf-asc-user-registration' ),
				),
			),
		);

		// Section 3: Reference fields (applicant preferences shown in the Create Account panel).
		$fields['reference_fields'] = array(
			'title'       => esc_html__( 'Reference Fields (optional)', 'gf-asc-user-registration' ),
			'description' => esc_html__( 'Map the applicant-facing fields so their preferences appear in the Create Account panel for the reviewer.', 'gf-asc-user-registration' ),
			'fields'      => array(
				array(
					'name'    => 'preferred_usernames_field',
					'label'   => esc_html__( 'Username Preferences', 'gf-asc-user-registration' ),
					'type'    => 'field_select',
					'class'   => 'medium',
					'tooltip' => esc_html__( 'The field where the applicant lists their preferred usernames.', 'gf-asc-user-registration' ),
				),
				array(
					'name'    => 'preferred_playerids_field',
					'label'   => esc_html__( 'Player ID Preferences', 'gf-asc-user-registration' ),
					'type'    => 'field_select',
					'class'   => 'medium',
					'tooltip' => esc_html__( 'The field where the applicant lists their preferred Player IDs.', 'gf-asc-user-registration' ),
				),
			),
		);

		// Section 4: AccessSchema roles.
		$fields['asc_roles'] = array(
			'title'  => esc_html__( 'AccessSchema Roles', 'gf-asc-user-registration' ),
			'fields' => array(
				array(
					'name'    => 'asc_roles_field',
					'label'   => esc_html__( 'ASC Roles Field', 'gf-asc-user-registration' ),
					'type'    => 'field_select',
					'class'   => 'medium',
					'args'    => array(
						'input_types' => array( 'asc_roles' ),
					),
					'tooltip' => esc_html__( 'Select the AccessSchema Roles field on this form. Add one via the form editor under Advanced Fields.', 'gf-asc-user-registration' ),
				),
			),
		);

		// Section 5: Notification settings.
		$fields['notification_settings'] = array(
			'title'  => esc_html__( 'Notification', 'gf-asc-user-registration' ),
			'fields' => array(
				array(
					'name'          => 'send_notification',
					'label'         => esc_html__( 'Send notification email', 'gf-asc-user-registration' ),
					'type'          => 'checkbox',
					'choices'       => array(
						array(
							'label'         => esc_html__( 'Send the new user a welcome email with a set-password link.', 'gf-asc-user-registration' ),
							'name'          => 'send_notification',
							'default_value' => 1,
						),
					),
				),
			),
		);

		// Section 5: Conditional logic.
		$fields['conditional_logic'] = array(
			'title'  => esc_html__( 'Conditional Logic', 'gf-asc-user-registration' ),
			'fields' => array(
				array(
					'name'    => 'feed_condition',
					'label'   => esc_html__( 'Condition', 'gf-asc-user-registration' ),
					'type'    => 'feed_condition',
					'tooltip' => esc_html__( 'Only process this feed when the condition is met.', 'gf-asc-user-registration' ),
				),
			),
		);

		return $fields;
	}

	/**
	 * Define the feed list columns.
	 *
	 * @since 1.0.0
	 *
	 * @return array
	 */
	public function feed_list_columns() {
		return array(
			'feedName' => esc_html__( 'Name', 'gf-asc-user-registration' ),
		);
	}

	/**
	 * Get WordPress roles formatted as choices for a select field.
	 *
	 * @since 1.0.0
	 *
	 * @return array
	 */
	private function get_wp_roles_as_choices() {
		$choices = array();

		if ( ! function_exists( 'get_editable_roles' ) ) {
			require_once ABSPATH . 'wp-admin/includes/user.php';
		}

		$roles = get_editable_roles();
		foreach ( $roles as $role_slug => $role_data ) {
			$choices[] = array(
				'label' => $role_data['name'],
				'value' => $role_slug,
			);
		}

		return $choices;
	}

	// -------------------------------------------------------------------------
	// Feed Processing
	// -------------------------------------------------------------------------

	/**
	 * Process the feed: create a WordPress user and assign AccessSchema roles.
	 *
	 * @since 1.0.0
	 *
	 * @param array $feed  The feed configuration.
	 * @param array $entry The current entry.
	 * @param array $form  The current form.
	 *
	 * @return array|WP_Error The entry array on success, WP_Error on failure.
	 */
	public function process_feed( $feed, $entry, $form ) {

		$this->log_debug( __METHOD__ . '(): Starting feed processing.' );

		$meta = rgar( $feed, 'meta' );

		// 1. Extract mapped values.
		$username   = $this->get_field_value( $form, $entry, rgar( $meta, 'username' ) );
		$email      = $this->get_field_value( $form, $entry, rgar( $meta, 'email' ) );
		$first_name = $this->get_field_value( $form, $entry, rgar( $meta, 'first_name' ) );
		$last_name  = $this->get_field_value( $form, $entry, rgar( $meta, 'last_name' ) );
		$player_id  = $this->get_field_value( $form, $entry, rgar( $meta, 'player_id' ) );
		$wp_role    = rgar( $meta, 'role', 'subscriber' );

		// Clean up the username.
		$username = sanitize_user( trim( $username ), true );
		$email    = sanitize_email( trim( $email ) );

		// 2. Validate required fields.
		if ( empty( $username ) ) {
			$this->add_feed_error( esc_html__( 'Username is empty.', 'gf-asc-user-registration' ), $feed, $entry, $form );
			return new WP_Error( 'empty_username', 'Username is empty.' );
		}

		if ( empty( $email ) || ! is_email( $email ) ) {
			$this->add_feed_error( esc_html__( 'Email is empty or invalid.', 'gf-asc-user-registration' ), $feed, $entry, $form );
			return new WP_Error( 'invalid_email', 'Email is empty or invalid.' );
		}

		if ( username_exists( $username ) ) {
			$this->add_feed_error(
				sprintf( esc_html__( 'Username "%s" already exists.', 'gf-asc-user-registration' ), $username ),
				$feed, $entry, $form
			);
			return new WP_Error( 'username_exists', sprintf( 'Username "%s" already exists.', $username ) );
		}

		if ( email_exists( $email ) ) {
			$this->add_feed_error(
				sprintf( esc_html__( 'Email "%s" already exists.', 'gf-asc-user-registration' ), $email ),
				$feed, $entry, $form
			);
			return new WP_Error( 'email_exists', sprintf( 'Email "%s" already exists.', $email ) );
		}

		// 3. Generate password.
		$password = wp_generate_password( 24, true, true );

		// 4. Create the WordPress user.
		$this->log_debug( __METHOD__ . sprintf( '(): Creating user "%s" with email "%s".', $username, $email ) );

		$user_id = wp_insert_user(
			array(
				'user_login' => $username,
				'user_email' => $email,
				'user_pass'  => $password,
				'first_name' => sanitize_text_field( $first_name ),
				'last_name'  => sanitize_text_field( $last_name ),
				'role'       => $wp_role,
			)
		);

		if ( is_wp_error( $user_id ) ) {
			$this->add_feed_error(
				sprintf( esc_html__( 'Failed to create user: %s', 'gf-asc-user-registration' ), $user_id->get_error_message() ),
				$feed, $entry, $form
			);
			return $user_id;
		}

		$this->log_debug( __METHOD__ . sprintf( '(): User created with ID %d.', $user_id ) );

		// 5. Set player ID as user meta.
		if ( ! empty( $player_id ) ) {
			update_user_meta( $user_id, 'player_id', sanitize_text_field( $player_id ) );
		}

		// 6. Assign AccessSchema roles.
		$asc_roles_assigned = $this->assign_asc_roles( $user_id, $feed, $entry, $form );

		// 7. Send notification.
		$send_notification = rgar( $meta, 'send_notification' );
		$password_mode     = rgar( $meta, 'password_mode', 'email_link' );

		if ( $send_notification && 'email_link' === $password_mode ) {
			wp_new_user_notification( $user_id, null, 'user' );
			$this->log_debug( __METHOD__ . '(): Sent new user notification email.' );
		}

		// 8. Fire custom action.
		do_action( 'gf_asc_user_registered', $user_id, $asc_roles_assigned, $feed, $entry );

		// 9. Add note to entry.
		$role_count = count( $asc_roles_assigned );
		$this->add_note(
			rgar( $entry, 'id' ),
			sprintf(
				esc_html__( 'User created (ID: %1$d, login: %2$s) with %3$d AccessSchema role(s).', 'gf-asc-user-registration' ),
				$user_id,
				$username,
				$role_count
			),
			'success'
		);

		// 10. Associate entry with the new user.
		if ( ! rgar( $entry, 'created_by' ) ) {
			GFAPI::update_entry_property( $entry['id'], 'created_by', $user_id );
			$entry['created_by'] = $user_id;
		}

		return $entry;
	}

	/**
	 * Assign AccessSchema roles from the form entry.
	 *
	 * @since 1.0.0
	 *
	 * @param int   $user_id The new WordPress user ID.
	 * @param array $feed    The feed configuration.
	 * @param array $entry   The current entry.
	 * @param array $form    The current form.
	 *
	 * @return string[] Array of successfully assigned role paths.
	 */
	private function assign_asc_roles( $user_id, $feed, $entry, $form ) {

		$assigned = array();

		// Check that AccessSchema is available.
		if ( ! function_exists( 'accessSchema_add_role' ) ) {
			$this->log_error( __METHOD__ . '(): AccessSchema server plugin is not active. Cannot assign roles.' );
			$this->add_feed_error(
				esc_html__( 'AccessSchema server plugin is not active. Roles were not assigned.', 'gf-asc-user-registration' ),
				$feed, $entry, $form
			);
			return $assigned;
		}

		$meta           = rgar( $feed, 'meta' );
		$roles_field_id = rgar( $meta, 'asc_roles_field' );

		if ( empty( $roles_field_id ) ) {
			$this->log_debug( __METHOD__ . '(): No ASC roles field configured in feed.' );
			return $assigned;
		}

		$roles_value = rgar( $entry, $roles_field_id );

		if ( empty( $roles_value ) ) {
			$this->log_debug( __METHOD__ . '(): ASC roles field is empty in entry.' );
			return $assigned;
		}

		// Parse the comma-separated role paths.
		$role_paths = array_map( 'trim', explode( ',', $roles_value ) );
		$role_paths = array_filter( $role_paths );

		$performed_by = get_current_user_id();

		foreach ( $role_paths as $role_path ) {
			$role_path = sanitize_text_field( $role_path );

			if ( function_exists( 'accessSchema_role_exists' ) && ! accessSchema_role_exists( $role_path ) ) {
				$this->log_error( __METHOD__ . sprintf( '(): Role path "%s" does not exist. Skipping.', $role_path ) );
				continue;
			}

			$result = accessSchema_add_role( $user_id, $role_path, $performed_by );

			if ( $result ) {
				$assigned[] = $role_path;
				$this->log_debug( __METHOD__ . sprintf( '(): Assigned role "%s" to user %d.', $role_path, $user_id ) );
			} else {
				$this->log_error( __METHOD__ . sprintf( '(): Failed to assign role "%s" to user %d.', $role_path, $user_id ) );
			}
		}

		return $assigned;
	}

	// -------------------------------------------------------------------------
	// AJAX: Role Search
	// -------------------------------------------------------------------------

	/**
	 * AJAX handler for searching AccessSchema roles (Select2 compatible).
	 *
	 * @since 1.0.0
	 */
	public function ajax_search_roles() {
		check_ajax_referer( 'gf_asc_search_roles', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Unauthorized', 403 );
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- nonce verified above
		$search   = isset( $_GET['q'] ) ? sanitize_text_field( wp_unslash( $_GET['q'] ) ) : '';
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- nonce verified above
		$page     = isset( $_GET['page'] ) ? absint( $_GET['page'] ) : 1;
		$per_page = 50;
		$offset   = ( $page - 1 ) * $per_page;

		global $wpdb;

		$table = $wpdb->prefix . 'accessSchema_roles';

		// Build query.
		$where = 'WHERE is_active = 1 AND full_path IS NOT NULL AND full_path != ""';
		$args  = array();

		if ( ! empty( $search ) ) {
			$where .= ' AND full_path LIKE %s';
			$args[] = '%' . $wpdb->esc_like( $search ) . '%';
		}

		// Get total count.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$total = $wpdb->get_var(
			empty( $args )
				? "SELECT COUNT(*) FROM {$table} {$where}"
				: $wpdb->prepare( "SELECT COUNT(*) FROM {$table} {$where}", $args )
		);

		// Get results.
		$limit_clause = $wpdb->prepare( 'ORDER BY full_path ASC LIMIT %d OFFSET %d', $per_page, $offset );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results(
			empty( $args )
				? "SELECT full_path FROM {$table} {$where} {$limit_clause}"
				: $wpdb->prepare( "SELECT full_path FROM {$table} {$where} {$limit_clause}", $args )
		);

		$results = array();
		if ( $rows ) {
			foreach ( $rows as $row ) {
				$results[] = array(
					'id'   => $row->full_path,
					'text' => $row->full_path,
				);
			}
		}

		wp_send_json(
			array(
				'results'    => $results,
				'pagination' => array(
					'more' => ( $offset + $per_page ) < intval( $total ),
				),
			)
		);
	}

	// -------------------------------------------------------------------------
	// Entry Detail: Create Account Panel
	// -------------------------------------------------------------------------

	/**
	 * Render the "Create Account" panel on the entry detail sidebar.
	 *
	 * Includes inline admin inputs for username, player ID, and role
	 * selection so the admin does not need to edit the GF entry directly.
	 *
	 * @since 1.0.0
	 *
	 * @param array $form  The current form.
	 * @param array $entry The current entry.
	 */
	public function render_entry_meta_box( $form, $entry ) {

		// Only show if there's a feed configured for this form.
		$feeds = $this->get_feeds( $form['id'] );
		if ( empty( $feeds ) ) {
			return;
		}

		$entry_id = rgar( $entry, 'id' );
		$feed     = $feeds[0];
		$meta     = rgar( $feed, 'meta' );

		// Check if this entry was already processed.
		$processed = gform_get_meta( $entry_id, 'gf_asc_user_created' );
		$nonce     = wp_create_nonce( 'gf_asc_process_entry_' . $entry_id );

		// Resolve field IDs from feed configuration — no hardcoding.
		$fid_email              = rgar( $meta, 'email' );
		$fid_first_name         = rgar( $meta, 'first_name' );
		$fid_last_name          = rgar( $meta, 'last_name' );
		$fid_username           = rgar( $meta, 'username' );
		$fid_player_id          = rgar( $meta, 'player_id' );
		$fid_asc_roles          = rgar( $meta, 'asc_roles_field' );
		$fid_pref_usernames     = rgar( $meta, 'preferred_usernames_field' );
		$fid_pref_playerids     = rgar( $meta, 'preferred_playerids_field' );

		// Pull applicant data from entry for reference display.
		$applicant_email     = $fid_email ? $this->get_field_value( $form, $entry, $fid_email ) : '';
		$applicant_first     = $fid_first_name ? $this->get_field_value( $form, $entry, $fid_first_name ) : '';
		$applicant_last      = $fid_last_name ? $this->get_field_value( $form, $entry, $fid_last_name ) : '';
		$applicant_usernames = $fid_pref_usernames ? rgar( $entry, $fid_pref_usernames ) : '';
		$applicant_playerids = $fid_pref_playerids ? rgar( $entry, $fid_pref_playerids ) : '';

		// Pre-fill from entry if admin-only fields already have values.
		$existing_username  = $fid_username ? rgar( $entry, $fid_username ) : '';
		$existing_playerid  = $fid_player_id ? rgar( $entry, $fid_player_id ) : '';
		$existing_roles     = $fid_asc_roles ? rgar( $entry, $fid_asc_roles ) : '';

		?>
		<style>
			/* Inline overrides — must load after any bundled Select2 CSS. */
			#gf-asc-create-account .select2-container--default .select2-selection--multiple .select2-selection__choice {
				background-color: #2271b1 !important;
				color: #fff !important;
				border: none !important;
				border-radius: 3px !important;
				padding: 4px 8px 4px 24px !important;
				margin: 3px 4px 3px 0 !important;
				font-size: 13px !important;
				line-height: 1.3 !important;
				position: relative !important;
				box-shadow: none !important;
				outline: none !important;
			}
			#gf-asc-create-account .select2-container--default .select2-selection--multiple .select2-selection__choice__display {
				color: #fff !important;
				padding: 0 !important;
				cursor: default;
			}
			#gf-asc-create-account .select2-container--default .select2-selection--multiple .select2-selection__choice__remove {
				color: #fff !important;
				background: transparent !important;
				background-color: transparent !important;
				border: none !important;
				border-right: none !important;
				box-shadow: none !important;
				outline: none !important;
				font-size: 16px !important;
				font-weight: 400 !important;
				line-height: 1 !important;
				padding: 0 4px !important;
				margin: 0 !important;
				position: absolute !important;
				left: 2px !important;
				top: 50% !important;
				transform: translateY(-50%) !important;
				opacity: 0.7;
				cursor: pointer;
				border-radius: 0 !important;
				width: auto !important;
				height: auto !important;
			}
			#gf-asc-create-account .select2-container--default .select2-selection--multiple .select2-selection__choice__remove:hover,
			#gf-asc-create-account .select2-container--default .select2-selection--multiple .select2-selection__choice__remove:focus {
				color: #fff !important;
				background: transparent !important;
				background-color: transparent !important;
				border: none !important;
				box-shadow: none !important;
				outline: none !important;
				opacity: 1;
			}
		</style>
		<div id="gf-asc-create-account" style="background:#fff; border:1px solid #c3c4c7; border-radius:4px; margin:20px 0; padding:0;">
			<div style="border-bottom:1px solid #c3c4c7; padding:12px 16px; background:#f6f7f7;">
				<h3 style="margin:0; font-size:14px; font-weight:600;">
					<span class="dashicons dashicons-admin-users" style="margin-right:6px; color:#2271b1;"></span>
					<?php esc_html_e( 'ASC User Registration', 'gf-asc-user-registration' ); ?>
				</h3>
			</div>
			<div style="padding:16px 20px;">
				<?php if ( $processed ) : ?>
					<div style="display:flex; align-items:center; gap:12px;">
						<span class="dashicons dashicons-yes-alt" style="color:#2e7d32; font-size:28px; width:28px; height:28px;"></span>
						<div>
							<p style="margin:0; font-size:14px; color:#2e7d32; font-weight:600;">
								<?php
								printf(
									esc_html__( 'Account created (User ID: %s)', 'gf-asc-user-registration' ),
									esc_html( $processed )
								);
								?>
							</p>
							<p style="margin:4px 0 0;"><a href="<?php echo esc_url( admin_url( 'user-edit.php?user_id=' . intval( $processed ) ) ); ?>"><?php esc_html_e( 'View / Edit user', 'gf-asc-user-registration' ); ?> &rarr;</a></p>
						</div>
					</div>
				<?php else : ?>
					<div style="display:grid; grid-template-columns:1fr 1fr; gap:16px 24px;">

						<?php // -- Left column: applicant reference + username/playerid inputs -- ?>
						<div>
							<?php if ( ! empty( $applicant_usernames ) ) : ?>
								<div style="background:#f0f6fc; border-left:3px solid #2271b1; padding:8px 12px; margin-bottom:12px; font-size:13px; border-radius:0 4px 4px 0;">
									<strong><?php esc_html_e( 'Applicant username preferences:', 'gf-asc-user-registration' ); ?></strong><br>
									<?php echo nl2br( esc_html( $applicant_usernames ) ); ?>
								</div>
							<?php endif; ?>

							<div style="margin-bottom:12px;">
								<label for="gf-asc-username" style="font-weight:600; font-size:13px; display:block; margin-bottom:4px;">
									<?php esc_html_e( 'Assigned Username', 'gf-asc-user-registration' ); ?> <span style="color:#d63638;">*</span>
								</label>
								<input type="text" id="gf-asc-username" value="<?php echo esc_attr( $existing_username ); ?>" placeholder="e.g. jdoe" style="width:100%; font-size:14px; padding:6px 8px;" />
							</div>

							<div style="margin-bottom:12px;">
								<label style="font-weight:600; font-size:13px; display:block; margin-bottom:4px;">
									<?php esc_html_e( 'Email (from applicant)', 'gf-asc-user-registration' ); ?>
								</label>
								<span style="font-size:14px; color:#50575e;"><?php echo esc_html( $applicant_email ); ?></span>
							</div>

							<div style="margin-bottom:12px;">
								<label style="font-weight:600; font-size:13px; display:block; margin-bottom:4px;">
									<?php esc_html_e( 'Name (from applicant)', 'gf-asc-user-registration' ); ?>
								</label>
								<span style="font-size:14px; color:#50575e;"><?php echo esc_html( trim( $applicant_first . ' ' . $applicant_last ) ); ?></span>
							</div>

							<?php if ( ! empty( $applicant_playerids ) ) : ?>
								<div style="background:#f0f6fc; border-left:3px solid #2271b1; padding:8px 12px; margin-bottom:12px; font-size:13px; border-radius:0 4px 4px 0;">
									<strong><?php esc_html_e( 'Applicant Player ID preferences:', 'gf-asc-user-registration' ); ?></strong><br>
									<?php echo nl2br( esc_html( $applicant_playerids ) ); ?>
								</div>
							<?php endif; ?>

							<div style="margin-bottom:12px;">
								<label for="gf-asc-playerid" style="font-weight:600; font-size:13px; display:block; margin-bottom:4px;">
									<?php esc_html_e( 'Assigned Player ID', 'gf-asc-user-registration' ); ?>
								</label>
								<input type="text" id="gf-asc-playerid" value="<?php echo esc_attr( $existing_playerid ); ?>" placeholder="e.g. ABC123456" style="width:100%; font-size:14px; padding:6px 8px;" />
							</div>

							<div>
								<label for="gf-asc-wp-role" style="font-weight:600; font-size:13px; display:block; margin-bottom:4px;">
									<?php esc_html_e( 'WordPress Role', 'gf-asc-user-registration' ); ?>
								</label>
								<select id="gf-asc-wp-role" style="width:100%; font-size:14px; padding:4px 8px;">
									<?php
									$configured_role = rgar( $meta, 'role', 'subscriber' );
									$wp_roles        = $this->get_wp_roles_as_choices();
									foreach ( $wp_roles as $role_choice ) {
										printf(
											'<option value="%s"%s>%s</option>',
											esc_attr( $role_choice['value'] ),
											selected( $role_choice['value'], $configured_role, false ),
											esc_html( $role_choice['label'] )
										);
									}
									?>
								</select>
							</div>
						</div>

						<?php // -- Right column: AccessSchema Roles select -- ?>
						<div>
							<label for="gf-asc-roles-select" style="font-weight:600; font-size:13px; display:block; margin-bottom:4px;">
								<?php esc_html_e( 'AccessSchema Roles', 'gf-asc-user-registration' ); ?>
							</label>
							<select id="gf-asc-roles-select" class="gf-asc-roles-select" multiple="multiple" style="width:100%;">
								<?php
								if ( ! empty( $existing_roles ) ) {
									$role_arr = array_filter( array_map( 'trim', explode( ',', $existing_roles ) ) );
									foreach ( $role_arr as $rp ) {
										printf(
											'<option value="%s" selected="selected">%s</option>',
											esc_attr( $rp ),
											esc_html( $rp )
										);
									}
								}
								?>
							</select>
							<p class="description" style="margin-top:8px;"><?php esc_html_e( 'Search and select the AccessSchema role paths to assign to this user.', 'gf-asc-user-registration' ); ?></p>
						</div>
					</div>

					<?php // -- Bottom bar: button -- ?>
					<div style="margin-top:16px; padding-top:16px; border-top:1px solid #e0e0e0; display:flex; align-items:center; gap:12px;">
						<button type="button" class="button button-primary button-large" id="gf-asc-create-btn"
							data-entry="<?php echo esc_attr( $entry_id ); ?>"
							data-form="<?php echo esc_attr( $form['id'] ); ?>"
							data-nonce="<?php echo esc_attr( $nonce ); ?>">
							<?php esc_html_e( 'Create Account', 'gf-asc-user-registration' ); ?>
						</button>
						<div id="gf-asc-create-result" style="display:none;"></div>
					</div>
				<?php endif; ?>
			</div>
		</div>

		<?php if ( ! $processed ) : ?>
		<script type="text/javascript">
		jQuery(function($) {
			var strings = window.gf_asc_roles_field_strings || {};
			$('#gf-asc-roles-select').select2({
				ajax: {
					url: strings.ajaxurl || window.ajaxurl,
					dataType: 'json',
					delay: 250,
					data: function(params) {
						return {
							action: 'gf_asc_search_roles',
							nonce: strings.nonce || '',
							q: params.term || '',
							page: params.page || 1
						};
					},
					processResults: function(data, params) {
						params.page = params.page || 1;
						return {
							results: data.results || [],
							pagination: { more: data.pagination && data.pagination.more }
						};
					},
					cache: true
				},
				multiple: true,
				placeholder: strings.placeholder || 'Search AccessSchema roles...',
				allowClear: true,
				minimumInputLength: 0,
				width: '100%'
			});

			$('#gf-asc-create-btn').on('click', function() {
				var $btn = $(this);
				var $result = $('#gf-asc-create-result');

				var username = $.trim($('#gf-asc-username').val());
				if (!username) {
					$result.html('<span style="color:#d63638;">Username is required.</span>').show();
					$('#gf-asc-username').focus();
					return;
				}

				var selectedRoles = $('#gf-asc-roles-select').val();
				var rolesCSV = selectedRoles ? selectedRoles.join(', ') : '';

				$btn.prop('disabled', true).text('<?php echo esc_js( __( 'Processing...', 'gf-asc-user-registration' ) ); ?>');
				$result.hide();

				$.post(ajaxurl, {
					action: 'gf_asc_process_entry',
					entry_id: $btn.data('entry'),
					form_id: $btn.data('form'),
					nonce: $btn.data('nonce'),
					username: username,
					player_id: $.trim($('#gf-asc-playerid').val()),
					roles: rolesCSV,
					wp_role: $('#gf-asc-wp-role').val()
				}, function(response) {
					if (response.success) {
						$result.html('<span style="color:#2e7d32;"><span class="dashicons dashicons-yes-alt"></span> <strong>' + response.data.message + '</strong></span>').show();
						$btn.prop('disabled', true).text('<?php echo esc_js( __( 'Account Created', 'gf-asc-user-registration' ) ); ?>');
					} else {
						$result.html('<span style="color:#d63638;"><strong>Error:</strong> ' + response.data + '</span>').show();
						$btn.prop('disabled', false).text('<?php echo esc_js( __( 'Create Account', 'gf-asc-user-registration' ) ); ?>');
					}
				}).fail(function() {
					$result.html('<span style="color:#d63638;">Request failed.</span>').show();
					$btn.prop('disabled', false).text('<?php echo esc_js( __( 'Create Account', 'gf-asc-user-registration' ) ); ?>');
				});
			});
		});
		</script>
		<?php endif; ?>
		<?php
	}

	/**
	 * AJAX handler for manually processing a feed on an existing entry.
	 *
	 * Accepts admin-decision values directly from the meta box POST data,
	 * saves them to the entry, and then runs the feed.
	 *
	 * @since 1.0.0
	 */
	public function ajax_process_entry() {

		$entry_id = isset( $_POST['entry_id'] ) ? absint( $_POST['entry_id'] ) : 0;
		$form_id  = isset( $_POST['form_id'] ) ? absint( $_POST['form_id'] ) : 0;

		check_ajax_referer( 'gf_asc_process_entry_' . $entry_id, 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Unauthorized.' );
		}

		if ( empty( $entry_id ) || empty( $form_id ) ) {
			wp_send_json_error( 'Missing entry or form ID.' );
		}

		// Check if already processed.
		$already = gform_get_meta( $entry_id, 'gf_asc_user_created' );
		if ( $already ) {
			wp_send_json_error( sprintf( 'Account already created (User ID: %d).', $already ) );
		}

		$entry = GFAPI::get_entry( $entry_id );
		if ( is_wp_error( $entry ) ) {
			wp_send_json_error( 'Entry not found.' );
		}

		$form = GFAPI::get_form( $form_id );
		if ( ! $form ) {
			wp_send_json_error( 'Form not found.' );
		}

		// Save admin-decision values from the POST data into the entry.
		$admin_username  = isset( $_POST['username'] ) ? sanitize_user( wp_unslash( $_POST['username'] ), true ) : '';
		$admin_playerid  = isset( $_POST['player_id'] ) ? sanitize_text_field( wp_unslash( $_POST['player_id'] ) ) : '';
		$admin_roles     = isset( $_POST['roles'] ) ? sanitize_text_field( wp_unslash( $_POST['roles'] ) ) : '';
		$admin_wp_role   = isset( $_POST['wp_role'] ) ? sanitize_text_field( wp_unslash( $_POST['wp_role'] ) ) : '';

		if ( empty( $admin_username ) ) {
			wp_send_json_error( 'Username is required.' );
		}

		// Get the feed to resolve configured field IDs.
		$feeds = $this->get_feeds( $form_id );
		if ( empty( $feeds ) ) {
			wp_send_json_error( 'No ASC Registration feed configured for this form.' );
		}

		$feed = $feeds[0];
		$meta = rgar( $feed, 'meta' );

		$fid_username  = rgar( $meta, 'username' );
		$fid_player_id = rgar( $meta, 'player_id' );
		$fid_asc_roles = rgar( $meta, 'asc_roles_field' );

		// Write the admin-decision values into the GF entry using feed-configured field IDs.
		if ( $fid_username ) {
			GFAPI::update_entry_field( $entry_id, $fid_username, $admin_username );
		}
		if ( $fid_player_id ) {
			GFAPI::update_entry_field( $entry_id, $fid_player_id, $admin_playerid );
		}
		if ( $fid_asc_roles ) {
			GFAPI::update_entry_field( $entry_id, $fid_asc_roles, $admin_roles );
		}

		// Re-read entry with updated values.
		$entry = GFAPI::get_entry( $entry_id );

		// Override the WP role if the admin changed it.
		if ( ! empty( $admin_wp_role ) ) {
			$feed['meta']['role'] = $admin_wp_role;
		}

		// Process the feed.
		$result = $this->process_feed( $feed, $entry, $form );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( $result->get_error_message() );
		}

		// Find the user ID from the entry.
		$user_id = rgar( $result, 'created_by' );
		if ( $user_id ) {
			gform_update_meta( $entry_id, 'gf_asc_user_created', $user_id );
		}

		wp_send_json_success(
			array(
				'message' => sprintf( 'User account created successfully (User ID: %d).', $user_id ),
				'user_id' => $user_id,
			)
		);
	}
}
