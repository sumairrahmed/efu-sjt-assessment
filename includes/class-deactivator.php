<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class EFU_SJT_Deactivator {

	public static function deactivate() {
		flush_rewrite_rules();
	}
}
