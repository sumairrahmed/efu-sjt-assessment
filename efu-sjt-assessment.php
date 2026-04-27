<?php
/**
 * Plugin Name: EFU SJT Assessment
 * Plugin URI:  https://trout.digital/
 * Description: HOD Leadership Situational Judgment Assessment for EFU Life.
 * Version:     1.1.3
 * Author:      Sumair Ahmed | Trout Digital
 * License:     GPL-2.0+
 * Text Domain: efu-sjt-assessment
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'EFU_SJT_VERSION',    '1.1.3' );
define( 'EFU_SJT_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'EFU_SJT_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'EFU_SJT_TABLE',      'efu_sjt_submissions' );

foreach ( [
	'class-activator',
	'class-deactivator',
	'class-submission',
	'class-scorer',
	'class-rest-api',
	'class-google-sheets',
] as $file ) {
	require_once EFU_SJT_PLUGIN_DIR . 'includes/' . $file . '.php';
}

if ( is_admin() ) {
	require_once EFU_SJT_PLUGIN_DIR . 'admin/class-admin.php';
	EFU_SJT_Admin::init();
}

require_once EFU_SJT_PLUGIN_DIR . 'frontend/class-shortcode.php';
EFU_SJT_Shortcode::init();

add_action( 'rest_api_init', [ 'EFU_SJT_REST_API', 'register_routes' ] );

register_activation_hook(   __FILE__, [ 'EFU_SJT_Activator',   'activate'   ] );
register_deactivation_hook( __FILE__, [ 'EFU_SJT_Deactivator', 'deactivate' ] );
