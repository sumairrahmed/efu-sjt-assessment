<?php
if ( ! defined( 'ABSPATH' ) ) exit;
if ( ! current_user_can( 'manage_options' ) ) return;

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

class EFU_SJT_Submissions_Table extends WP_List_Table {

	private array $filters;

	public function __construct( array $filters ) {
		parent::__construct( [
			'singular' => 'submission',
			'plural'   => 'submissions',
			'ajax'     => false,
		] );
		$this->filters = $filters;
	}

	public function get_columns(): array {
		return [
			'cb'            => '<input type="checkbox">',
			'name'          => 'Name',
			'email'         => 'Email',
			'age'           => 'Age',
			'gender'        => 'Gender',
			'department'    => 'Department',
			'overall_score' => 'Score',
			'overall_level' => 'Level',
			'submitted_at'  => 'Submitted',
			'actions'       => 'Actions',
		];
	}

	public function get_sortable_columns(): array {
		return [
			'name'          => [ 'name', false ],
			'overall_score' => [ 'overall_score', false ],
			'overall_level' => [ 'overall_level', false ],
			'submitted_at'  => [ 'submitted_at', true ],
		];
	}

	protected function column_cb( $item ): string {
		return '<input type="checkbox" name="submission_ids[]" value="' . esc_attr( $item->id ) . '">';
	}

	protected function column_name( $item ): string {
		return '<strong>' . esc_html( $item->name ) . '</strong>';
	}

	protected function column_email( $item ): string {
		return esc_html( $item->email );
	}

	protected function column_age( $item ): string {
		return esc_html( $item->age );
	}

	protected function column_gender( $item ): string {
		return esc_html( $item->gender );
	}

	protected function column_department( $item ): string {
		return esc_html( $item->department );
	}

	protected function column_overall_score( $item ): string {
		return '<span class="efu-score">' . esc_html( number_format( $item->overall_score, 2 ) ) . '</span>';
	}

	protected function column_overall_level( $item ): string {
		$class_map = [
			'Developing'  => 'level-developing',
			'Proficient'  => 'level-proficient',
			'Advanced'    => 'level-advanced',
			'Role Model'  => 'level-rolemodel',
		];
		$class = $class_map[ $item->overall_level ] ?? '';
		return '<span class="efu-level-badge ' . esc_attr( $class ) . '">' . esc_html( $item->overall_level ) . '</span>';
	}

	protected function column_submitted_at( $item ): string {
		return esc_html( wp_date( 'd M Y, H:i', strtotime( $item->submitted_at ) ) );
	}

	protected function column_actions( $item ): string {
		$delete_url = wp_nonce_url(
			admin_url( 'admin-ajax.php?action=efu_sjt_delete_submission&id=' . $item->id ),
			'efu_sjt_admin',
			'nonce'
		);
		return '<a class="efu-btn-view button button-small" data-id="' . esc_attr( $item->id ) . '" href="#">View</a> '
			. '<a class="efu-btn-delete button button-small button-link-delete" data-id="' . esc_attr( $item->id ) . '" href="#">Delete</a>';
	}

	protected function column_default( $item, $column_name ) {
		return esc_html( $item->$column_name ?? '' );
	}

	public function prepare_items() {
		$per_page     = 20;
		$current_page = $this->get_pagenum();

		$result = EFU_SJT_Submission::get_all( array_merge( $this->filters, [
			'per_page' => $per_page,
			'page'     => $current_page,
			'orderby'  => sanitize_key( $_GET['orderby'] ?? 'submitted_at' ),
			'order'    => sanitize_key( $_GET['order'] ?? 'DESC' ),
		] ) );

		$this->items = $result['rows'];
		$this->set_pagination_args( [
			'total_items' => $result['total'],
			'per_page'    => $per_page,
			'total_pages' => ceil( $result['total'] / $per_page ),
		] );

		$this->_column_headers = [ $this->get_columns(), [], $this->get_sortable_columns() ];
	}
}

$filters = [
	'date_from'  => sanitize_text_field( $_GET['date_from'] ?? '' ),
	'date_to'    => sanitize_text_field( $_GET['date_to'] ?? '' ),
	'gender'     => sanitize_text_field( $_GET['gender'] ?? '' ),
	'department' => sanitize_text_field( $_GET['department'] ?? '' ),
	'level'      => sanitize_text_field( $_GET['level'] ?? '' ),
];

$table = new EFU_SJT_Submissions_Table( $filters );
$table->prepare_items();

$stats     = EFU_SJT_Submission::get_stats();
$admin_nonce = wp_create_nonce( 'efu_sjt_admin' );

$export_params = array_merge( $filters, [
	'action' => 'efu_sjt_export_csv',
	'nonce'  => $admin_nonce,
] );
$export_url = admin_url( 'admin-ajax.php?' . http_build_query( $export_params ) );
?>
<div class="wrap efu-admin-wrap">
	<div class="efu-admin-header">
		<img src="<?php echo esc_url( EFU_SJT_PLUGIN_URL . 'assets/img/efu-logo.png' ); ?>" alt="EFU Life" class="efu-admin-logo" onerror="this.style.display='none'">
		<h1>HOD Assessment — Submissions</h1>
	</div>

	<form method="get" class="efu-filter-bar">
		<input type="hidden" name="page" value="efu-sjt-assessment">
		<div class="efu-filter-row">
			<label>From <input type="date" name="date_from" value="<?php echo esc_attr( $filters['date_from'] ); ?>"></label>
			<label>To <input type="date" name="date_to" value="<?php echo esc_attr( $filters['date_to'] ); ?>"></label>
			<label>Gender
				<select name="gender">
					<option value="">All</option>
					<?php foreach ( [ 'Male', 'Female', 'Prefer not to say' ] as $g ) : ?>
						<option value="<?php echo esc_attr( $g ); ?>" <?php selected( $filters['gender'], $g ); ?>><?php echo esc_html( $g ); ?></option>
					<?php endforeach; ?>
				</select>
			</label>
			<label>Department <input type="text" name="department" value="<?php echo esc_attr( $filters['department'] ); ?>" placeholder="Search..."></label>
			<label>Level
				<select name="level">
					<option value="">All</option>
					<?php foreach ( [ 'Developing', 'Proficient', 'Advanced', 'Role Model' ] as $lv ) : ?>
						<option value="<?php echo esc_attr( $lv ); ?>" <?php selected( $filters['level'], $lv ); ?>><?php echo esc_html( $lv ); ?></option>
					<?php endforeach; ?>
				</select>
			</label>
			<button type="submit" class="button button-primary">Filter</button>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=efu-sjt-assessment' ) ); ?>" class="button">Reset</a>
			<a href="<?php echo esc_url( $export_url ); ?>" class="button efu-export-btn">&#x2193; Export CSV</a>
		</div>
	</form>

	<div id="efu-submission-modal" class="efu-modal" style="display:none;">
		<div class="efu-modal-overlay"></div>
		<div class="efu-modal-box">
			<button class="efu-modal-close">&times;</button>
			<div id="efu-modal-content"></div>
		</div>
	</div>

	<form method="post">
		<?php wp_nonce_field( 'efu_sjt_bulk', 'efu_bulk_nonce' ); ?>
		<?php $table->display(); ?>
	</form>

	<hr class="efu-section-divider">
	<h2 class="efu-section-title">Analytics Overview</h2>

	<div class="efu-charts-grid">
		<div class="efu-chart-card">
			<h3>Average Pillar Scores</h3>
			<div id="chartPillar"></div>
		</div>
		<div class="efu-chart-card">
			<h3>Level Distribution</h3>
			<div id="chartLevels"></div>
		</div>
		<div class="efu-chart-card efu-chart-full">
			<h3>Submissions — Last 30 Days</h3>
			<div id="chartDaily"></div>
		</div>
	</div>
</div>

<script>
(function(){
	const nonce = <?php echo wp_json_encode( $admin_nonce ); ?>;

	document.querySelectorAll('.efu-btn-delete').forEach(btn => {
		btn.addEventListener('click', e => {
			e.preventDefault();
			if ( !confirm('Delete this submission? This cannot be undone.') ) return;
			const id = btn.dataset.id;
			fetch(ajaxurl, {
				method: 'POST',
				headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
				body: 'action=efu_sjt_delete_submission&nonce=' + nonce + '&id=' + id,
			}).then(r => r.json()).then(d => {
				if (d.success) btn.closest('tr').remove();
				else alert('Error: ' + d.data);
			});
		});
	});

	document.querySelectorAll('.efu-btn-view').forEach(btn => {
		btn.addEventListener('click', e => {
			e.preventDefault();
			const id = btn.dataset.id;
			document.getElementById('efu-modal-content').innerHTML = '<p>Loading...</p>';
			document.getElementById('efu-submission-modal').style.display = 'flex';
			fetch('<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>?action=efu_sjt_get_submission&nonce=' + nonce + '&id=' + id)
				.then(r => r.json())
				.then(d => {
					if (d.success) document.getElementById('efu-modal-content').innerHTML = d.data.html;
					else document.getElementById('efu-modal-content').innerHTML = '<p>Error loading.</p>';
				});
		});
	});

	document.querySelector('.efu-modal-close')?.addEventListener('click', () => {
		document.getElementById('efu-submission-modal').style.display = 'none';
	});
	document.querySelector('.efu-modal-overlay')?.addEventListener('click', () => {
		document.getElementById('efu-submission-modal').style.display = 'none';
	});
})();
</script>
