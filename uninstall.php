<?php
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

global $wpdb;

$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}efu_sjt_submissions" );

delete_option( 'efu_sjt_assessment_data' );
delete_option( 'efu_sjt_sheets_config' );
delete_option( 'efu_sjt_sheets_service_account' );
delete_option( 'efu_sjt_sheets_last_sync' );
delete_option( 'efu_sjt_db_version' );
