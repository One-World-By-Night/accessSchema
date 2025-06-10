<?php
// File: includes/external-access/client.php
// @version 1.0.5
// Author: greghacke
// Description: Calls an external WordPress site's accessSchema API from *another* WordPress instance.

defined( 'ABSPATH' ) || exit;

/**
 * Remote config: external site URL and API key.
 */
define( 'REMOTE_ACCESS_SCHEMA_URL', 'https://example.com/wp-json/access-schema/v1' );
define( 'REMOTE_ACCESS_SCHEMA_KEY', 'your-shared-api-key-here' );

/**
 * Send a POST request to the accessSchema API endpoint.
 *
 * @param string $endpoint The API endpoint path (e.g., 'roles', 'grant', 'revoke').
 * @param array $body JSON body parameters.
 * @return array|WP_Error Response array or error.
 */
function accessSchema_remote_post( $endpoint, array $body ) {
    $url = trailingslashit( REMOTE_ACCESS_SCHEMA_URL ) . ltrim( $endpoint, '/' );

    $response = wp_remote_post( $url, [
        'headers' => [
            'Content-Type'  => 'application/json',
            'x-api-key'     => REMOTE_ACCESS_SCHEMA_KEY,
        ],
        'body'    => wp_json_encode( $body ),
        'timeout' => 10,
    ] );

    if ( is_wp_error( $response ) ) {
        return $response;
    }

    $status = wp_remote_retrieve_response_code( $response );
    $data   = json_decode( wp_remote_retrieve_body( $response ), true );

    if ( $status !== 200 && $status !== 201 ) {
        return new WP_Error( 'api_error', 'Remote API returned HTTP ' . $status, $data );
    }

    return $data;
}

/**
 * Example: Get remote roles for a user by email.
 *
 * @param string $email
 * @return array|WP_Error
 */
function accessSchema_remote_get_roles_by_email( $email ) {
    return accessSchema_remote_post( 'roles', [ 'email' => sanitize_email( $email ) ] );
}

/**
 * Example: Grant a role to a user on the remote system.
 *
 * @param string $email
 * @param string $role_path
 * @return array|WP_Error
 */
function accessSchema_remote_grant_role( $email, $role_path ) {
    return accessSchema_remote_post( 'grant', [
        'email'     => sanitize_email( $email ),
        'role_path' => sanitize_text_field( $role_path ),
    ] );
}

/**
 * Example: Check if user has role or descendant.
 *
 * @param string $email
 * @param string $role_path
 * @param bool $include_children
 * @return bool|WP_Error
 */
function accessSchema_remote_check_access( $email, $role_path, $include_children = true ) {
    $response = accessSchema_remote_post( 'check', [
        'email'             => sanitize_email( $email ),
        'role_path'         => sanitize_text_field( $role_path ),
        'include_children'  => $include_children,
    ] );

    if ( is_wp_error( $response ) ) {
        return $response;
    }

    return ! empty( $response['granted'] );
}