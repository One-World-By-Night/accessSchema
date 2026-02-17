<?php
/**
 * Custom Gravity Forms field: AccessSchema Roles (Select2 multi-select).
 *
 * @package GF_ASC_User_Registration
 * @since   1.0.0
 */

defined( 'ABSPATH' ) || die();

/**
 * AccessSchema Roles field for Gravity Forms.
 *
 * Renders as a Select2 multi-select in admin entry views and Gravity Flow
 * approval steps. Stores selected role paths as a comma-separated string.
 *
 * @since 1.0.0
 *
 * @see GF_Field
 */
class GF_Field_ASC_Roles extends GF_Field {

	/**
	 * Field type identifier.
	 *
	 * @var string
	 */
	public $type = 'asc_roles';

	/**
	 * Return the field title for the form editor.
	 *
	 * @since 1.0.0
	 *
	 * @return string
	 */
	public function get_form_editor_field_title() {
		return esc_html__( 'AccessSchema Roles', 'gf-asc-user-registration' );
	}

	/**
	 * Return the field description for the form editor.
	 *
	 * @since 1.0.0
	 *
	 * @return string
	 */
	public function get_form_editor_field_description() {
		return esc_html__( 'A searchable multi-select for AccessSchema role paths. Set visibility to Admin Only for use during Gravity Flow approval.', 'gf-asc-user-registration' );
	}

	/**
	 * Assign the field to the Advanced Fields group in the form editor.
	 *
	 * @since 1.0.0
	 *
	 * @return array
	 */
	public function get_form_editor_button() {
		return array(
			'group' => 'advanced_fields',
			'text'  => $this->get_form_editor_field_title(),
		);
	}

	/**
	 * Define which standard field settings are available.
	 *
	 * @since 1.0.0
	 *
	 * @return array
	 */
	public function get_form_editor_field_settings() {
		return array(
			'label_setting',
			'description_setting',
			'admin_label_setting',
			'visibility_setting',
			'css_class_setting',
			'conditional_logic_field_setting',
		);
	}

	/**
	 * Field is supported by conditional logic.
	 *
	 * @since 1.0.0
	 *
	 * @return bool
	 */
	public function is_conditional_logic_supported() {
		return true;
	}

	/**
	 * Render the field input on the frontend form.
	 *
	 * For public-facing forms, this renders as a hidden/read-only field.
	 * The actual Select2 UI is rendered only in admin contexts.
	 *
	 * @since 1.0.0
	 *
	 * @param array  $form  The current form.
	 * @param string $value The field value.
	 * @param array  $entry The current entry.
	 *
	 * @return string HTML markup.
	 */
	public function get_field_input( $form, $value = '', $entry = null ) {

		$id        = (int) $this->id;
		$form_id   = absint( $form['id'] );
		$field_id  = "input_{$form_id}_{$id}";
		$is_admin  = is_admin();
		$is_entry  = $this->is_entry_detail() || $this->is_form_editor();
		$disabled  = $this->is_form_editor() ? 'disabled="disabled"' : '';

		$safe_value = esc_attr( $value );

		// In admin entry detail or Gravity Flow, render the Select2 interface.
		if ( $is_admin && $is_entry && ! $this->is_form_editor() ) {
			return $this->render_admin_select2( $field_id, $id, $value );
		}

		// In the form editor, show a placeholder.
		if ( $this->is_form_editor() ) {
			return '<div class="ginput_container ginput_container_asc_roles">'
				. '<div style="padding:10px;background:#f0f0f1;border:1px solid #c3c4c7;border-radius:4px;color:#50575e;font-size:13px;">'
				. esc_html__( 'AccessSchema Roles — Select2 multi-select (rendered in admin entry view)', 'gf-asc-user-registration' )
				. '</div></div>';
		}

		// On the public form (if not admin-only), render as a hidden field.
		return sprintf(
			'<div class="ginput_container ginput_container_asc_roles">'
			. '<input type="hidden" name="input_%d" id="%s" value="%s" %s />'
			. '</div>',
			$id,
			esc_attr( $field_id ),
			$safe_value,
			$disabled
		);
	}

	/**
	 * Render the Select2 multi-select for admin entry editing.
	 *
	 * @since 1.0.0
	 *
	 * @param string $field_id The HTML element ID.
	 * @param int    $id       The GF field ID.
	 * @param string $value    Current comma-separated role paths.
	 *
	 * @return string HTML markup.
	 */
	private function render_admin_select2( $field_id, $id, $value ) {

		$selected_roles = array();
		if ( ! empty( $value ) ) {
			$selected_roles = array_map( 'trim', explode( ',', $value ) );
			$selected_roles = array_filter( $selected_roles );
		}

		$html  = '<div class="ginput_container ginput_container_asc_roles gf-asc-roles-wrap">';

		// Hidden input that stores the actual value (comma-separated).
		$html .= sprintf(
			'<input type="hidden" name="input_%d" id="%s" value="%s" class="gf-asc-roles-value" />',
			$id,
			esc_attr( $field_id ),
			esc_attr( $value )
		);

		// Select2 element.
		$html .= sprintf(
			'<select class="gf-asc-roles-select" data-field-id="%d" multiple="multiple" style="width:100%%;">',
			$id
		);

		// Pre-populate selected options.
		foreach ( $selected_roles as $role_path ) {
			$html .= sprintf(
				'<option value="%s" selected="selected">%s</option>',
				esc_attr( $role_path ),
				esc_html( $role_path )
			);
		}

		$html .= '</select>';
		$html .= '</div>';

		return $html;
	}

	/**
	 * Format the field value for display in entry lists and detail views.
	 *
	 * @since 1.0.0
	 *
	 * @param string|array $value      The field value.
	 * @param int          $entry_id   The entry ID.
	 * @param string       $field_id   The field ID.
	 * @param array        $entry      The entry object.
	 * @param string       $form_id    The form ID.
	 *
	 * @return string
	 */
	public function get_value_entry_list( $value, $entry, $field_id, $columns, $form ) {
		if ( empty( $value ) ) {
			return '<em>' . esc_html__( 'No roles', 'gf-asc-user-registration' ) . '</em>';
		}

		$roles = array_map( 'trim', explode( ',', $value ) );
		$count = count( $roles );

		return sprintf(
			esc_html( _n( '%d role', '%d roles', $count, 'gf-asc-user-registration' ) ),
			$count
		);
	}

	/**
	 * Format the field value for the entry detail page.
	 *
	 * @since 1.0.0
	 *
	 * @param string|array $value    The field value.
	 * @param string       $currency The currency code.
	 * @param bool         $use_text Whether to use text values.
	 * @param string       $format   The format (html or text).
	 *
	 * @return string
	 */
	public function get_value_entry_detail( $value, $currency = '', $use_text = false, $format = 'html', $media = 'screen' ) {
		if ( empty( $value ) ) {
			return '';
		}

		if ( 'text' === $format ) {
			return $value;
		}

		// Render grouped role display (matching AccessSchema users-table style).
		$roles = array_map( 'trim', explode( ',', $value ) );
		$roles = array_filter( $roles );

		if ( empty( $roles ) ) {
			return '';
		}

		return $this->render_grouped_roles( $roles );
	}

	/**
	 * Render roles grouped by top-level category.
	 *
	 * @since 1.0.0
	 *
	 * @param string[] $roles Array of full role path strings.
	 *
	 * @return string HTML output.
	 */
	private function render_grouped_roles( $roles ) {

		$grouped = array();
		foreach ( $roles as $role_path ) {
			$parts    = explode( '/', $role_path );
			$category = $parts[0];
			$remainder = count( $parts ) > 1 ? implode( '/', array_slice( $parts, 1 ) ) : '';

			if ( ! isset( $grouped[ $category ] ) ) {
				$grouped[ $category ] = array();
			}
			$grouped[ $category ][] = array(
				'full_path' => $role_path,
				'display'   => $remainder,
			);
		}

		$colors = array( '#1565c0', '#6a1b9a', '#2e7d32', '#e65100', '#c2185b' );

		$html      = '<div class="gf-asc-role-list">';
		$cat_index = 0;
		foreach ( $grouped as $category => $cat_roles ) {
			$color = $colors[ $cat_index % 5 ];

			$html .= sprintf(
				'<div class="gf-asc-role-group" style="border-left:3px solid %s;padding-left:6px;margin-bottom:3px;">',
				esc_attr( $color )
			);
			$html .= sprintf(
				'<span class="gf-asc-role-category" style="color:%s;">%s</span>',
				esc_attr( $color ),
				esc_html( $category )
			);

			foreach ( $cat_roles as $role ) {
				if ( '' === $role['display'] ) {
					continue;
				}
				$html .= sprintf(
					'<span class="gf-asc-role-item" title="%s">%s</span>',
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

	/**
	 * Sanitize the field value on save.
	 *
	 * @since 1.0.0
	 *
	 * @param string $value      The submitted value.
	 * @param array  $form       The current form.
	 * @param string $input_name The input name.
	 * @param int    $lead_id    The entry ID.
	 * @param array  $lead       The entry.
	 *
	 * @return string Sanitized comma-separated role paths.
	 */
	public function get_value_save_entry( $value, $form, $input_name, $lead_id, $lead ) {
		if ( empty( $value ) ) {
			return '';
		}

		$paths = array_map( 'sanitize_text_field', array_map( 'trim', explode( ',', $value ) ) );
		$paths = array_filter( $paths );

		return implode( ', ', $paths );
	}
}

// Register the field type with Gravity Forms.
GF_Fields::register( new GF_Field_ASC_Roles() );
