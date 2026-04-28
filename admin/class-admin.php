<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class EFU_SJT_Admin {

	public static function init() {
		add_action( 'admin_menu',            [ __CLASS__, 'register_menu' ] );
		add_action( 'admin_enqueue_scripts', [ __CLASS__, 'enqueue_assets' ] );
		add_action( 'wp_ajax_efu_sjt_save_questions',   [ __CLASS__, 'ajax_save_questions' ] );
		add_action( 'wp_ajax_efu_sjt_delete_submission', [ __CLASS__, 'ajax_delete_submission' ] );
		add_action( 'wp_ajax_efu_sjt_export_csv',        [ __CLASS__, 'export_csv' ] );
		add_action( 'wp_ajax_efu_sjt_save_sheets',       [ __CLASS__, 'ajax_save_sheets' ] );
		add_action( 'wp_ajax_efu_sjt_push_headers',      [ __CLASS__, 'ajax_push_headers' ] );
		add_action( 'wp_ajax_efu_sjt_sync_all',          [ __CLASS__, 'ajax_sync_all' ] );
		add_action( 'wp_ajax_efu_sjt_save_settings',      [ __CLASS__, 'ajax_save_settings' ] );
		add_action( 'wp_ajax_efu_sjt_get_submission',    [ __CLASS__, 'ajax_get_submission' ] );
	}

	public static function register_menu() {
		add_menu_page(
			'EFU SJT Assessment',
			'EFU Assessment',
			'manage_options',
			'efu-sjt-assessment',
			[ __CLASS__, 'page_submissions' ],
			'dashicons-awards',
			30
		);
		add_submenu_page(
			'efu-sjt-assessment',
			'Submissions',
			'Submissions',
			'manage_options',
			'efu-sjt-assessment',
			[ __CLASS__, 'page_submissions' ]
		);
		add_submenu_page(
			'efu-sjt-assessment',
			'Questions',
			'Questions',
			'manage_options',
			'efu-sjt-questions',
			[ __CLASS__, 'page_questions' ]
		);
		add_submenu_page(
			'efu-sjt-assessment',
			'Google Sheets',
			'Google Sheets',
			'manage_options',
			'efu-sjt-sheets',
			[ __CLASS__, 'page_sheets' ]
		);
		add_submenu_page(
			'efu-sjt-assessment',
			'Settings',
			'Settings',
			'manage_options',
			'efu-sjt-settings',
			[ __CLASS__, 'page_settings_screen' ]
		);
	}

	public static function enqueue_assets( string $hook ) {
		$efu_hooks = [
			'toplevel_page_efu-sjt-assessment',
			'efu-assessment_page_efu-sjt-questions',
			'efu-assessment_page_efu-sjt-sheets',
			'efu-assessment_page_efu-sjt-settings',
		];
		if ( ! in_array( $hook, $efu_hooks, true ) ) return;

		wp_enqueue_style(
			'efu-poppins',
			'https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap',
			[],
			null
		);

		wp_enqueue_style(
			'efu-sjt-admin',
			EFU_SJT_PLUGIN_URL . 'assets/css/admin.css',
			[ 'efu-poppins' ],
			EFU_SJT_VERSION
		);

		if ( $hook === 'efu-assessment_page_efu-sjt-settings' ) {
			wp_enqueue_media();
		}

		if ( $hook === 'toplevel_page_efu-sjt-assessment' ) {
			wp_enqueue_script(
				'apexcharts',
				'https://cdn.jsdelivr.net/npm/apexcharts@3.49.0/dist/apexcharts.min.js',
				[],
				'3.49.0',
				true
			);
			wp_enqueue_script(
				'efu-sjt-admin-charts',
				EFU_SJT_PLUGIN_URL . 'assets/js/admin-charts.js',
				[ 'apexcharts' ],
				EFU_SJT_VERSION,
				true
			);
			$stats       = EFU_SJT_Submission::get_stats();
			$assessment  = EFU_SJT_Scorer::load_assessment();
			$chart_labels = [];
			if ( $assessment && ! empty( $assessment['pillars'] ) ) {
				foreach ( $assessment['pillars'] as $p ) {
					$chart_labels[ $p['id'] ] = $p['label'];
				}
			}
			$stats['pillar_labels'] = $chart_labels;
			wp_localize_script( 'efu-sjt-admin-charts', 'efuChartData', $stats );
		}

		if ( $hook === 'efu-assessment_page_efu-sjt-questions' ) {
			wp_enqueue_script(
				'efu-sjt-admin-questions',
				EFU_SJT_PLUGIN_URL . 'assets/js/admin-questions.js',
				[],
				EFU_SJT_VERSION,
				true
			);
			wp_localize_script( 'efu-sjt-admin-questions', 'efuAdminVars', [
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'efu_sjt_questions' ),
			] );
		}
	}

	public static function page_submissions() {
		if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Unauthorized' );
		require_once EFU_SJT_PLUGIN_DIR . 'admin/page-submissions.php';
	}

	public static function page_questions() {
		if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Unauthorized' );
		require_once EFU_SJT_PLUGIN_DIR . 'admin/page-questions.php';
	}

	public static function page_sheets() {
		if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Unauthorized' );
		require_once EFU_SJT_PLUGIN_DIR . 'admin/page-sheets.php';
	}

	public static function page_settings_screen() {
		if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Unauthorized' );
		require_once EFU_SJT_PLUGIN_DIR . 'admin/page-settings.php';
	}

	public static function ajax_get_submission() {
		check_ajax_referer( 'efu_sjt_admin', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Unauthorized' );

		$id  = absint( $_REQUEST['id'] ?? 0 );
		$row = EFU_SJT_Submission::get_by_id( $id );

		if ( ! $row ) {
			wp_send_json_error( 'Submission not found.' );
		}

		$pillar_scores     = json_decode( $row->pillar_scores ?? '{}', true ) ?: [];
		$competency_scores = json_decode( $row->scores ?? '{}', true ) ?: [];

		$level_class = [
			'Developing' => 'level-developing',
			'Proficient' => 'level-proficient',
			'Advanced'   => 'level-advanced',
			'Role Model' => 'level-rolemodel',
		];
		$badge_class = $level_class[ $row->overall_level ] ?? '';

		$pillar_labels = [];
		$assessment    = EFU_SJT_Scorer::load_assessment();
		if ( $assessment && ! empty( $assessment['pillars'] ) ) {
			foreach ( $assessment['pillars'] as $p ) {
				$pillar_labels[ $p['id'] ] = $p['label'];
			}
		}

		ob_start();
		?>
		<div class="efu-modal-header">
			<div class="efu-modal-header-left">
				<div class="efu-modal-name"><?php echo esc_html( $row->name ); ?></div>
				<div class="efu-modal-meta">
					<?php echo esc_html( $row->department ); ?> &bull;
					<?php echo esc_html( $row->gender ); ?>, <?php echo esc_html( $row->age ); ?>
				</div>
			</div>
			<div class="efu-modal-header-right">
				<div class="efu-modal-score-num">
					<?php echo esc_html( number_format( $row->overall_score, 2 ) ); ?><span>/4.00</span>
				</div>
				<span class="efu-level-badge <?php echo esc_attr( $badge_class ); ?>">
					<?php echo esc_html( $row->overall_level ); ?>
				</span>
			</div>
		</div>

		<div class="efu-modal-body">
			<div class="efu-modal-info-grid">
				<div>
					<span class="efu-info-label">Email</span>
					<span class="efu-info-val"><?php echo esc_html( $row->email ); ?></span>
				</div>
				<div>
					<span class="efu-info-label">Submitted</span>
					<span class="efu-info-val"><?php echo esc_html( wp_date( 'd M Y, H:i', strtotime( $row->submitted_at ) ) ); ?></span>
				</div>
				<div>
					<span class="efu-info-label">Age</span>
					<span class="efu-info-val"><?php echo esc_html( $row->age ); ?></span>
				</div>
				<div>
					<span class="efu-info-label">Gender</span>
					<span class="efu-info-val"><?php echo esc_html( $row->gender ); ?></span>
				</div>
			</div>

			<?php if ( $pillar_scores ) : ?>
			<div class="efu-modal-section-title">Pillar Scores</div>
			<?php foreach ( $pillar_scores as $pid => $pscore ) :
				$pct    = round( ( $pscore / 4 ) * 100, 1 );
				$plevel = EFU_SJT_Scorer::level_label( (float) $pscore );
				$pclass = $level_class[ $plevel ] ?? '';
			?>
			<div class="efu-modal-pillar">
				<div class="efu-modal-pillar-top">
					<span class="efu-modal-pillar-name">
						<?php echo esc_html( $pillar_labels[ $pid ] ?? ucwords( str_replace( '_', ' ', $pid ) ) ); ?>
					</span>
					<span class="efu-level-badge <?php echo esc_attr( $pclass ); ?>">
						<?php echo esc_html( $plevel ); ?>
					</span>
					<span class="efu-modal-pillar-score"><?php echo esc_html( number_format( $pscore, 2 ) ); ?></span>
				</div>
				<div class="efu-modal-bar-wrap">
					<div class="efu-modal-bar" style="width:<?php echo esc_attr( $pct ); ?>%"></div>
				</div>
			</div>
			<?php endforeach; ?>
			<?php endif; ?>
		</div>
		<?php
		wp_send_json_success( [ 'html' => ob_get_clean() ] );
	}

	public static function ajax_save_settings() {
		check_ajax_referer( 'efu_sjt_settings', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Unauthorized' );

		$logo_url = esc_url_raw( wp_unslash( $_POST['logo_url'] ?? '' ) );
		update_option( 'efu_sjt_logo_url', $logo_url );
		wp_send_json_success( 'Settings saved.' );
	}

	public static function ajax_save_questions() {
		check_ajax_referer( 'efu_sjt_questions', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Unauthorized' );

		$raw = isset( $_POST['assessment'] ) ? wp_unslash( $_POST['assessment'] ) : '';
		$decoded = json_decode( $raw, true );

		if ( ! is_array( $decoded ) ) {
			wp_send_json_error( 'Invalid JSON data.' );
		}

		update_option( 'efu_sjt_assessment_data', wp_json_encode( $decoded ), false );
		wp_send_json_success( 'Questions saved.' );
	}

	public static function ajax_delete_submission() {
		check_ajax_referer( 'efu_sjt_admin', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Unauthorized' );

		$id = absint( $_POST['id'] ?? 0 );
		if ( ! $id ) wp_send_json_error( 'Invalid ID.' );

		EFU_SJT_Submission::delete( $id );
		wp_send_json_success( 'Submission deleted.' );
	}

	public static function export_csv() {
		check_ajax_referer( 'efu_sjt_admin', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Unauthorized' );

		$filters = [
			'date_from'  => sanitize_text_field( $_GET['date_from'] ?? '' ),
			'date_to'    => sanitize_text_field( $_GET['date_to'] ?? '' ),
			'gender'     => sanitize_text_field( $_GET['gender'] ?? '' ),
			'department' => sanitize_text_field( $_GET['department'] ?? '' ),
			'level'      => sanitize_text_field( $_GET['level'] ?? '' ),
		];

		$rows = EFU_SJT_Submission::get_all_for_export( $filters );

		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="efu-sjt-submissions-' . gmdate( 'Y-m-d' ) . '.csv"' );

		$out = fopen( 'php://output', 'w' );
		fputcsv( $out, [ 'ID', 'Name', 'Email', 'Age', 'Gender', 'Department', 'Overall Score', 'Overall Level', 'Submitted At' ] );

		foreach ( $rows as $row ) {
			fputcsv( $out, [
				$row->id,
				$row->name,
				$row->email,
				$row->age,
				$row->gender,
				$row->department,
				$row->overall_score,
				$row->overall_level,
				$row->submitted_at,
			] );
		}
		fclose( $out );
		exit;
	}

	public static function ajax_save_sheets() {
		check_ajax_referer( 'efu_sjt_sheets', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Unauthorized' );

		$columns = [];
		if ( ! empty( $_POST['columns_json'] ) ) {
			$cols_raw = json_decode( wp_unslash( $_POST['columns_json'] ), true );
			if ( is_array( $cols_raw ) ) {
				foreach ( $cols_raw as $col ) {
					if ( ! empty( $col['field'] ) ) {
						$columns[] = [
							'field'  => sanitize_key( $col['field'] ),
							'header' => sanitize_text_field( $col['header'] ?? '' ),
						];
					}
				}
			}
		}

		$config = [
			'sheet_id'   => sanitize_text_field( $_POST['sheet_id'] ?? '' ),
			'sheet_name' => sanitize_text_field( $_POST['sheet_name'] ?? 'Sheet1' ),
			'auto_sync'  => ! empty( $_POST['auto_sync'] ),
			'columns'    => $columns,
		];

		update_option( 'efu_sjt_sheets_config', $config );

		if ( ! empty( $_POST['service_account_json'] ) ) {
			$sa_raw = wp_unslash( $_POST['service_account_json'] );
			$sa     = json_decode( $sa_raw, true );
			if ( is_array( $sa ) && isset( $sa['client_email'], $sa['private_key'] ) ) {
				update_option( 'efu_sjt_sheets_service_account', wp_json_encode( $sa ), false );
			} else {
				wp_send_json_error( 'Invalid service account JSON.' );
			}
		}

		wp_send_json_success( 'Settings saved.' );
	}

	public static function ajax_push_headers() {
		check_ajax_referer( 'efu_sjt_sheets', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Unauthorized' );

		$ok = EFU_SJT_Google_Sheets::push_headers();
		if ( $ok ) {
			wp_send_json_success( 'Headers pushed successfully.' );
		} else {
			wp_send_json_error( EFU_SJT_Google_Sheets::get_last_error() ?: 'Push failed for an unknown reason.' );
		}
	}

	public static function ajax_sync_all() {
		check_ajax_referer( 'efu_sjt_sheets', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Unauthorized' );

		$result = EFU_SJT_Google_Sheets::sync_all();
		if ( $result['pushed'] === 0 && $result['total'] > 0 ) {
			wp_send_json_error( 'Sync failed. ' . EFU_SJT_Google_Sheets::get_last_error() );
		} else {
			wp_send_json_success( "Synced {$result['pushed']} of {$result['total']} submissions." );
		}
	}
}
