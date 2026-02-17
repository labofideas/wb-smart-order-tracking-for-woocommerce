<?php
/**
 * Uninstall cleanup for WB Smart Order Tracking for WooCommerce.
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

$wbsot_options = array(
	'wbsot_version',
	'wbsot_enabled',
	'wbsot_enable_my_account',
	'wbsot_enable_emails',
	'wbsot_enable_public_tracking',
	'wbsot_public_tracking_rate_limit',
	'wbsot_enable_csv_import',
	'wbsot_csv_strict_mode',
	'wbsot_csv_allowed_statuses',
	'wbsot_allow_multiple',
	'wbsot_enabled_carriers',
	'wbsot_aftership_enabled',
	'wbsot_aftership_api_key',
	'wbsot_aftership_base_url',
	'wbsot_aftership_live_requests',
	'wbsot_shiprocket_enabled',
	'wbsot_shiprocket_api_token',
	'wbsot_shiprocket_base_url',
	'wbsot_shiprocket_live_requests',
	'wbsot_sync_interval',
	'wbsot_sync_batch_size',
	'wbsot_provider_test_results',
	'wbsot_security_events',
);

foreach ( $wbsot_options as $wbsot_option ) {
	delete_option( $wbsot_option );
	delete_site_option( $wbsot_option );
}

wp_clear_scheduled_hook( 'wbsot_status_sync_event' );
