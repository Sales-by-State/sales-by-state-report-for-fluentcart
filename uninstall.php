<?php
/**
 * Removes the plugin's data when it is deleted.
 *
 * @package SalesByStateReportForFluentCart
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

global $wpdb;

$sbsfc_options = array(
	'sbsfc_db_version',
	'sbsfc_backfill_cursor',
	'sbsfc_year_start',
);

foreach ( $sbsfc_options as $sbsfc_option ) {
	delete_option( $sbsfc_option );
}

if ( is_multisite() ) {
	foreach ( $sbsfc_options as $sbsfc_option ) {
		delete_site_option( $sbsfc_option );
	}
}

// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}sbsfc_order_state" );

if ( function_exists( 'as_unschedule_all_actions' ) ) {
	as_unschedule_all_actions( 'sbsfc_backfill_batch', array(), 'sales-by-state-report-for-fluentcart' );
}
