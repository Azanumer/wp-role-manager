<?php
/**
 * Uninstall WP Role Manager.
 * Removes the custom roles this plugin created (tracked in wrm_custom_roles)
 * and the tracking option itself. Built-in roles are never touched.
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

$custom = get_option( 'wrm_custom_roles', array() );
if ( is_array( $custom ) ) {
	foreach ( $custom as $slug ) {
		remove_role( sanitize_key( $slug ) );
	}
}
delete_option( 'wrm_custom_roles' );
