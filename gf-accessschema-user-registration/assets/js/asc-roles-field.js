/**
 * AccessSchema Roles field — Select2 initialization.
 *
 * Initializes Select2 multi-select on .gf-asc-roles-select elements,
 * loading roles via AJAX from the AccessSchema server.
 *
 * @package GF_ASC_User_Registration
 * @since   1.0.0
 */

( function( $ ) {
	'use strict';

	/**
	 * Initialize Select2 on all AccessSchema Roles fields.
	 */
	function initASCRolesSelect2() {
		$( '.gf-asc-roles-select' ).each( function() {
			var $select = $( this );

			// Skip if already initialized.
			if ( $select.hasClass( 'select2-hidden-accessible' ) ) {
				return;
			}

			var fieldId = $select.data( 'field-id' );
			var $hidden = $select.closest( '.gf-asc-roles-wrap' ).find( '.gf-asc-roles-value' );

			// Get localized strings from GF's script enqueue.
			var strings = window.gf_asc_roles_field_strings || {};

			$select.select2( {
				ajax: {
					url: strings.ajaxurl || window.ajaxurl,
					dataType: 'json',
					delay: 250,
					data: function( params ) {
						return {
							action: 'gf_asc_search_roles',
							nonce: strings.nonce || '',
							q: params.term || '',
							page: params.page || 1
						};
					},
					processResults: function( data, params ) {
						params.page = params.page || 1;
						return {
							results: data.results || [],
							pagination: {
								more: data.pagination && data.pagination.more
							}
						};
					},
					cache: true
				},
				multiple: true,
				placeholder: strings.placeholder || 'Search AccessSchema roles...',
				allowClear: true,
				minimumInputLength: 0,
				width: '100%'
			} );

			// Sync Select2 changes back to the hidden input.
			$select.on( 'change', function() {
				var selected = $select.val();
				var csv = selected ? selected.join( ', ' ) : '';
				$hidden.val( csv );
			} );
		} );
	}

	// Initialize on DOM ready.
	$( document ).ready( function() {
		initASCRolesSelect2();
	} );

	// Re-initialize after GF AJAX loads (entry detail, Gravity Flow).
	$( document ).on( 'gform_post_render', function() {
		initASCRolesSelect2();
	} );

	// Re-initialize when Gravity Flow loads entry detail via AJAX.
	$( document ).ajaxComplete( function( event, xhr, settings ) {
		if ( settings && settings.data && typeof settings.data === 'string' ) {
			if ( settings.data.indexOf( 'gravityflow' ) !== -1 ||
			     settings.data.indexOf( 'gf_entries' ) !== -1 ) {
				setTimeout( initASCRolesSelect2, 200 );
			}
		}
	} );

} )( jQuery );
