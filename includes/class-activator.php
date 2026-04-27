<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class EFU_SJT_Activator {

	public static function activate() {
		self::create_table();
		self::seed_assessment_option();
		flush_rewrite_rules();
	}

	private static function create_table() {
		global $wpdb;

		$table   = $wpdb->prefix . EFU_SJT_TABLE;
		$charset = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE IF NOT EXISTS {$table} (
			id               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			name             VARCHAR(150)    NOT NULL DEFAULT '',
			email            VARCHAR(150)    NOT NULL DEFAULT '',
			age              TINYINT UNSIGNED NOT NULL DEFAULT 0,
			gender           VARCHAR(30)     NOT NULL DEFAULT '',
			department       VARCHAR(150)    NOT NULL DEFAULT '',
			responses        LONGTEXT        NOT NULL,
			scores           LONGTEXT        NOT NULL,
			pillar_scores    LONGTEXT        NOT NULL,
			overall_score    DECIMAL(4,2)    NOT NULL DEFAULT 0.00,
			overall_level    VARCHAR(20)     NOT NULL DEFAULT '',
			submitted_at     DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY email (email),
			KEY submitted_at (submitted_at)
		) {$charset};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );

		update_option( 'efu_sjt_db_version', '1.0.0' );
	}

	private static function seed_assessment_option() {
		$json_path = EFU_SJT_PLUGIN_DIR . 'data/assessment.json';
		if ( file_exists( $json_path ) && ! get_option( 'efu_sjt_assessment_data' ) ) {
			$data = file_get_contents( $json_path );
			update_option( 'efu_sjt_assessment_data', $data, false );
		}
	}
}
