<?php
/**
 * Peentify-woocommerce Uninstall
 *
 * Uninstalling Peentify deletes all options.
 *
 * @package peentify-woocommerce
 * @since 1.0.0
 */

/** Check if we are uninstalling. */
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

/** Delete options. */
delete_option( 'peentify_status' );
delete_option( 'peentify_api_key' );
delete_option( 'peentify_api_secret' );
delete_option( 'peentify_main_url' );