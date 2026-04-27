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
			$stats = EFU_SJT_Submission::get_stats();
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

		$pillar_labels = [
			'growth_strategy'        => 'Growth & Strategy',
			'customer_market_focus'  => 'Customer & Market Focus',
			'people_culture'         => 'People & Culture',
			'operational_excellence' => 'Operational Excellence',
			'innovation_change'      => 'Innovation & Change',
		];

		ob_start();
		?>
		<div class="efu-submission-detail">
			<div class="efu-detail-header">
				<h2><?php echo esc_html( $row->name ); ?></h2>
				<span class="efu-level-badge <?php echo esc_attr( $badge_class ); ?>" style="font-size:1rem;padding:5px 14px;">
					<?php echo esc_html( $row->overall_level ); ?> — <?php echo esc_html( number_format( $row->overall_score, 2 ) ); ?> / 4.00
				</span>
			</div>

			<table class="efu-detail-table">
				<tr><td>Email</td><td><?php echo esc_html( $row->email ); ?></td></tr>
				<tr><td>Age</td><td><?php echo esc_html( $row->age ); ?></td></tr>
				<tr><td>Gender</td><td><?php echo esc_html( $row->gender ); ?></td></tr>
				<tr><td>Department</td><td><?php echo esc_html( $row->department ); ?></td></tr>
				<tr><td>Submitted</td><td><?php echo esc_html( wp_date( 'd M Y, H:i', strtotime( $row->submitted_at ) ) ); ?></td></tr>
			</table>

			<?php if ( $pillar_scores ) : ?>
			<h3 style="color:#144864;margin:20px 0 10px;font-size:1rem;">Pillar Scores</h3>
			<table class="efu-detail-table">
				<?php foreach ( $pillar_scores as $pid => $pscore ) : ?>
				<tr>
					<td><?php echo esc_html( $pillar_labels[ $pid ] ?? ucwords( str_replace( '_', ' ', $pid ) ) ); ?></td>
					<td>
						<div class="efu-score-bar-wrap">
							<div class="efu-score-bar" style="width:<?php echo esc_attr( round( ( $pscore / 4 ) * 100, 1 ) ); ?>%"></div>
							<span><?php echo esc_html( number_format( $pscore, 2 ) ); ?></span>
						</div>
					</td>
				</tr>
				<?php endforeach; ?>
			</table>
			<?php endif; ?>
		</div>

		<style>
		.efu-submission-detail { font-family: 'Poppins', sans-serif; }
		.efu-detail-header { display:flex; align-items:center; gap:16px; margin-bottom:16px; flex-wrap:wrap; }
		.efu-detail-header h2 { margin:0; color:#144864; font-size:1.2rem; }
		.efu-detail-table { width:100%; border-collapse:collapse; margin-bottom:8px; }
		.efu-detail-table td { padding:8px 10px; border-bottom:1px solid #e8edf1; font-size:0.88rem; }
		.efu-detail-table td:first-child { font-weight:600; color:#144864; width:130px; }
		.efu-score-bar-wrap { display:flex; align-items:center; gap:10px; }
		.efu-score-bar-wrap > div { flex:1; height:8px; background:#e8edf1; border-radius:4px; overflow:hidden; }
		.efu-score-bar { height:100%; background:#144864; border-radius:4px; }
		.efu-score-bar-wrap span { font-weight:600; color:#144864; font-size:0.85rem; width:34px; text-align:right; flex-shrink:0; }
		</style>
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
