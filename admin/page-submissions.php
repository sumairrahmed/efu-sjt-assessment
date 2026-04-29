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
		return '<strong style="color:#144864;">' . esc_html( $item->name ) . '</strong>';
	}

	protected function column_email( $item ): string {
		return '<span style="color:#4a6070;font-size:0.83rem;">' . esc_html( $item->email ) . '</span>';
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
		return '<span style="color:#4a6070;font-size:0.82rem;">' . esc_html( wp_date( 'd M Y, H:i', strtotime( $item->submitted_at ) ) ) . '</span>';
	}

	protected function column_actions( $item ): string {
		return '<a class="efu-btn-view efu-icon-btn" data-id="' . esc_attr( $item->id ) . '" href="#" title="View" aria-label="View submission">'
				. '<span class="dashicons dashicons-visibility"></span></a>'
			. '<a class="efu-btn-delete efu-icon-btn" data-id="' . esc_attr( $item->id ) . '" href="#" title="Delete" aria-label="Delete submission">'
				. '<span class="dashicons dashicons-trash"></span></a>';
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

$stats   = EFU_SJT_Submission::get_stats();
$summary = EFU_SJT_Submission::get_summary_stats();

// Derive top level
$top_level       = '';
$top_level_class = '';
if ( ! empty( $stats['levels'] ) ) {
	$max = 0;
	foreach ( $stats['levels'] as $lv ) {
		if ( (int) $lv->cnt > $max ) {
			$max       = (int) $lv->cnt;
			$top_level = $lv->overall_level;
		}
	}
	$card_class_map  = [
		'Developing' => 'efu-stat-accent',
		'Proficient' => '',
		'Advanced'   => 'efu-stat-green',
		'Role Model' => 'efu-stat-orange',
	];
	$top_level_class = $card_class_map[ $top_level ] ?? '';
}

$admin_nonce   = wp_create_nonce( 'efu_sjt_admin' );
$export_params = array_merge( $filters, [
	'action' => 'efu_sjt_export_csv',
	'nonce'  => $admin_nonce,
] );
$export_url = admin_url( 'admin-ajax.php?' . http_build_query( $export_params ) );
?>
<div class="wrap efu-admin-wrap">

	<!-- ── Header ─────────────────────────────────────── -->
	<div class="efu-admin-header">
		<div class="efu-brand-mark">
			<span class="efu-mark-efu">EFU</span>
			<span class="efu-mark-life">LIFE</span>
		</div>
		<div class="efu-header-text">
			<h1>HOD Assessment</h1>
			<p class="efu-header-sub">Submissions &amp; Analytics Dashboard</p>
		</div>
	</div>

	<!-- ── Stat cards ─────────────────────────────────── -->
	<div class="efu-stat-cards">
		<div class="efu-stat-card">
			<div class="efu-stat-label">Total Submissions</div>
			<div class="efu-stat-value"><?php echo esc_html( number_format( $summary['total'] ) ); ?></div>
		</div>
		<div class="efu-stat-card efu-stat-green">
			<div class="efu-stat-label">Average Score</div>
			<div class="efu-stat-value"><?php echo $summary['total'] > 0 ? esc_html( number_format( $summary['avg_score'], 2 ) ) : '&mdash;'; ?></div>
		</div>
		<div class="efu-stat-card efu-stat-accent">
			<div class="efu-stat-label">This Month</div>
			<div class="efu-stat-value"><?php echo esc_html( number_format( $summary['this_month'] ) ); ?></div>
		</div>
		<div class="efu-stat-card <?php echo esc_attr( $top_level_class ); ?>">
			<div class="efu-stat-label">Top Level</div>
			<div class="efu-stat-value efu-stat-level-val"><?php echo $top_level ? esc_html( $top_level ) : '&mdash;'; ?></div>
		</div>
	</div>

	<!-- ── Filter bar ─────────────────────────────────── -->
	<form method="get" class="efu-filter-bar">
		<input type="hidden" name="page" value="efu-sjt-assessment">
		<div class="efu-filter-row">
			<label>From<input type="date" name="date_from" value="<?php echo esc_attr( $filters['date_from'] ); ?>"></label>
			<label>To<input type="date" name="date_to" value="<?php echo esc_attr( $filters['date_to'] ); ?>"></label>
			<label>Gender
				<select name="gender">
					<option value="">All</option>
					<?php foreach ( [ 'Male', 'Female', 'Prefer not to say' ] as $g ) : ?>
						<option value="<?php echo esc_attr( $g ); ?>" <?php selected( $filters['gender'], $g ); ?>><?php echo esc_html( $g ); ?></option>
					<?php endforeach; ?>
				</select>
			</label>
			<label>Department<input type="text" name="department" value="<?php echo esc_attr( $filters['department'] ); ?>" placeholder="Search&hellip;"></label>
			<label>Level
				<select name="level">
					<option value="">All</option>
					<?php foreach ( [ 'Developing', 'Proficient', 'Advanced', 'Role Model' ] as $lv ) : ?>
						<option value="<?php echo esc_attr( $lv ); ?>" <?php selected( $filters['level'], $lv ); ?>><?php echo esc_html( $lv ); ?></option>
					<?php endforeach; ?>
				</select>
			</label>
			<div class="efu-filter-actions">
				<button type="submit" class="button button-primary">Filter</button>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=efu-sjt-assessment' ) ); ?>" class="button">Reset</a>
				<a href="<?php echo esc_url( $export_url ); ?>" class="button efu-export-btn">&#x2193;&nbsp;Export CSV</a>
			</div>
		</div>
	</form>

	<!-- ── Modal ──────────────────────────────────────── -->
	<div id="efu-submission-modal" class="efu-modal" style="display:none;">
		<div class="efu-modal-overlay"></div>
		<div class="efu-modal-box">
			<button class="efu-modal-close" aria-label="Close">&times;</button>
			<div id="efu-modal-content"></div>
		</div>
	</div>

	<!-- ── Table ──────────────────────────────────────── -->
	<form method="post" class="efu-table-wrap">
		<?php wp_nonce_field( 'efu_sjt_bulk', 'efu_bulk_nonce' ); ?>
		<?php $table->display(); ?>
	</form>

	<!-- ── Analytics section ──────────────────────────── -->
	<div class="efu-section-header">
		<div class="efu-section-accent-bar"></div>
		<div>
			<h2 class="efu-section-title">Analytics Overview</h2>
			<p class="efu-section-subtitle">Aggregate insights across all submissions</p>
		</div>
	</div>

	<div class="efu-charts-grid">
		<div class="efu-chart-card">
			<div class="efu-chart-card-header">
				<span class="efu-chart-card-title">Average Pillar Scores</span>
			</div>
			<div id="chartPillar"></div>
		</div>
		<div class="efu-chart-card">
			<div class="efu-chart-card-header">
				<span class="efu-chart-card-title">Level Distribution</span>
			</div>
			<div id="chartLevels"></div>
		</div>
		<div class="efu-chart-card efu-chart-full">
			<div class="efu-chart-card-header">
				<span class="efu-chart-card-title">Submissions &mdash; Last 30 Days</span>
			</div>
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
			fetch(ajaxurl, {
				method: 'POST',
				headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
				body: 'action=efu_sjt_delete_submission&nonce=' + nonce + '&id=' + btn.dataset.id,
			}).then(r => r.json()).then(d => {
				if (d.success) btn.closest('tr').remove();
				else alert('Error: ' + d.data);
			});
		});
	});

	document.querySelectorAll('.efu-btn-view').forEach(btn => {
		btn.addEventListener('click', e => {
			e.preventDefault();
			document.getElementById('efu-modal-content').innerHTML = '<div class="efu-modal-loading"><div class="efu-modal-spinner"></div></div>';
			document.getElementById('efu-submission-modal').style.display = 'flex';
			fetch('<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>?action=efu_sjt_get_submission&nonce=' + nonce + '&id=' + btn.dataset.id)
				.then(r => r.json())
				.then(d => {
					if (d.success) document.getElementById('efu-modal-content').innerHTML = d.data.html;
					else document.getElementById('efu-modal-content').innerHTML = '<p style="padding:32px;color:#c00;text-align:center;">Could not load submission.</p>';
				});
		});
	});

	const closeModal = () => { document.getElementById('efu-submission-modal').style.display = 'none'; };
	document.querySelector('.efu-modal-close')?.addEventListener('click', closeModal);
	document.querySelector('.efu-modal-overlay')?.addEventListener('click', closeModal);
	document.addEventListener('keydown', e => { if (e.key === 'Escape') closeModal(); });
})();
</script>
