<?php
if ( ! defined( 'ABSPATH' ) ) exit;
if ( ! current_user_can( 'manage_options' ) ) return;

$config    = get_option( 'efu_sjt_sheets_config', [] );
$has_sa    = ! empty( get_option( 'efu_sjt_sheets_service_account' ) );
$last_sync = get_option( 'efu_sjt_sheets_last_sync', '' );
$sheet_count = $has_sa ? EFU_SJT_Google_Sheets::get_sheet_row_count() : -1;

$available_fields = EFU_SJT_Google_Sheets::available_fields_with_labels();

// Resolve saved columns (supports both new 'columns' and legacy 'field_order' formats)
if ( ! empty( $config['columns'] ) && is_array( $config['columns'] ) ) {
	$saved_columns = $config['columns'];
} elseif ( ! empty( $config['field_order'] ) && is_array( $config['field_order'] ) ) {
	$saved_columns = array_map( fn( $f ) => [
		'field'  => $f,
		'header' => $available_fields[ $f ] ?? ucwords( str_replace( '_', ' ', $f ) ),
	], $config['field_order'] );
} else {
	$defaults      = [ 'id', 'name', 'email', 'age', 'gender', 'department', 'overall_score', 'overall_level', 'submitted_at' ];
	$saved_columns = array_map( fn( $f ) => [
		'field'  => $f,
		'header' => $available_fields[ $f ] ?? ucwords( str_replace( '_', ' ', $f ) ),
	], $defaults );
}

$nonce = wp_create_nonce( 'efu_sjt_sheets' );
?>
<div class="wrap efu-admin-wrap">
	<div class="efu-admin-header">
		<h1>Google Sheets Integration</h1>
	</div>

	<?php if ( $last_sync ) : ?>
	<div class="efu-sheets-status notice notice-info inline">
		<p>
			<strong>Last sync:</strong> <?php echo esc_html( wp_date( 'd M Y, H:i', strtotime( $last_sync ) ) ); ?>
			<?php if ( $sheet_count >= 0 ) : ?>
				&nbsp;&bull;&nbsp; <strong><?php echo intval( $sheet_count ); ?></strong> rows in sheet
			<?php endif; ?>
		</p>
	</div>
	<?php endif; ?>

	<div id="efu-sheets-message" class="notice" style="display:none;"></div>

	<form id="efu-sheets-form" class="efu-sheets-form">
		<input type="hidden" name="nonce" value="<?php echo esc_attr( $nonce ); ?>">
		<input type="hidden" id="efu-columns-json" name="columns_json" value="<?php echo esc_attr( wp_json_encode( $saved_columns ) ); ?>">

		<table class="form-table">
			<tr>
				<th><label for="sheet_id">Spreadsheet ID</label></th>
				<td>
					<input type="text" id="sheet_id" name="sheet_id" class="regular-text"
						value="<?php echo esc_attr( $config['sheet_id'] ?? '' ); ?>"
						placeholder="1BxiMVs0XRA5nFMdKvBdBZjgmUUqptlbs74OgVE2upms">
					<p class="description">Found in the Google Sheets URL: .../spreadsheets/d/<strong>SHEET_ID</strong>/edit</p>
				</td>
			</tr>
			<tr>
				<th><label for="sheet_name">Tab Name</label></th>
				<td>
					<input type="text" id="sheet_name" name="sheet_name" class="regular-text"
						value="<?php echo esc_attr( $config['sheet_name'] ?? 'Sheet1' ); ?>">
				</td>
			</tr>
			<tr>
				<th>Service Account JSON</th>
				<td>
					<?php if ( $has_sa ) : ?>
					<div class="efu-sa-status efu-sa-ok">&#10003; Service account key is saved. Upload a new file to replace it.</div>
					<?php else : ?>
					<div class="efu-sa-status efu-sa-empty">No service account key saved.</div>
					<?php endif; ?>
					<textarea id="service_account_json" name="service_account_json" rows="8"
						class="large-text code" placeholder='{"type":"service_account","project_id":"...","private_key":"...","client_email":"..."}'></textarea>
					<p class="description">Paste the full contents of your Google service account JSON key file. <strong>It will be stored encrypted and never displayed again.</strong></p>
				</td>
			</tr>
			<tr>
				<th>Auto-sync New Submissions</th>
				<td>
					<label>
						<input type="checkbox" name="auto_sync" value="1" <?php checked( ! empty( $config['auto_sync'] ) ); ?>>
						Automatically push each new submission to Google Sheets
					</label>
				</td>
			</tr>
			<tr>
				<th>Sheet Columns</th>
				<td>
					<p class="description" style="margin-bottom:12px;">Add, remove, and reorder columns. Choose what data each column sends and customise its header name in the sheet.</p>
					<div id="efu-col-list" class="efu-col-list"></div>
					<button type="button" id="efu-add-col" class="button" style="margin-top:6px;">+ Add Column</button>
				</td>
			</tr>
		</table>

		<p class="submit">
			<button type="submit" class="button button-primary">Save Settings</button>
		</p>
	</form>

	<hr class="efu-section-divider">
	<h2>Sheet Actions</h2>
	<div class="efu-sheets-actions">
		<button id="efu-push-headers" class="button button-secondary">&#9776; Push Header Row</button>
		<button id="efu-sync-all" class="button button-secondary">&#9650; Sync All Submissions</button>
	</div>
</div>

<script>
(function(){
	const nonce = <?php echo wp_json_encode( $nonce ); ?>;
	const ajax  = <?php echo wp_json_encode( admin_url( 'admin-ajax.php' ) ); ?>;
	const msg   = document.getElementById('efu-sheets-message');
	const list  = document.getElementById('efu-col-list');

	const availableFields = <?php echo wp_json_encode( $available_fields ); ?>;
	const savedColumns    = <?php echo wp_json_encode( $saved_columns ); ?>;

	function showMsg(text, isError) {
		msg.className = 'notice ' + (isError ? 'notice-error' : 'notice-success');
		msg.innerHTML = '<p>' + text + '</p>';
		msg.style.display = 'block';
		setTimeout(() => msg.style.display = 'none', 6000);
	}

	// ── Column builder ─────────────────────────────────
	function buildRow(field, header) {
		const row = document.createElement('div');
		row.className = 'efu-col-row';
		row.setAttribute('draggable', 'true');

		const handle = document.createElement('span');
		handle.className = 'efu-drag-handle';
		handle.title = 'Drag to reorder';
		handle.textContent = '⋮⋮';

		const select = document.createElement('select');
		select.className = 'efu-col-field';
		const blank = document.createElement('option');
		blank.value = '';
		blank.textContent = '— Select field —';
		select.appendChild(blank);
		Object.entries(availableFields).forEach(([key, label]) => {
			const opt = document.createElement('option');
			opt.value = key;
			opt.textContent = label;
			if (key === field) opt.selected = true;
			select.appendChild(opt);
		});

		const input = document.createElement('input');
		input.type = 'text';
		input.className = 'efu-col-header';
		input.placeholder = 'Column header';
		input.value = header || '';

		const removeBtn = document.createElement('button');
		removeBtn.type = 'button';
		removeBtn.className = 'efu-col-remove button button-link-delete';
		removeBtn.textContent = '✕';
		removeBtn.title = 'Remove column';
		removeBtn.addEventListener('click', () => row.remove());

		select.addEventListener('change', () => {
			if (!input.dataset.userEdited) {
				input.value = availableFields[select.value] || '';
			}
		});
		input.addEventListener('input', () => { input.dataset.userEdited = '1'; });

		row.appendChild(handle);
		row.appendChild(select);
		row.appendChild(input);
		row.appendChild(removeBtn);
		return row;
	}

	// Drag-and-drop (delegated on the list)
	let dragging = null;
	list.addEventListener('dragstart', e => {
		dragging = e.target.closest('.efu-col-row');
		if (dragging) { setTimeout(() => dragging.classList.add('dragging'), 0); }
	});
	list.addEventListener('dragend', () => {
		if (dragging) dragging.classList.remove('dragging');
		dragging = null;
	});
	list.addEventListener('dragover', e => {
		e.preventDefault();
		const target = e.target.closest('.efu-col-row');
		if (!target || !dragging || target === dragging) return;
		const rect = target.getBoundingClientRect();
		list.insertBefore(dragging, e.clientY > rect.top + rect.height / 2 ? target.nextSibling : target);
	});

	// Initialize from saved config
	savedColumns.forEach(col => list.appendChild(buildRow(col.field, col.header)));

	document.getElementById('efu-add-col').addEventListener('click', () => {
		list.appendChild(buildRow('', ''));
	});

	// ── Form save ──────────────────────────────────────
	document.getElementById('efu-sheets-form').addEventListener('submit', async e => {
		e.preventDefault();

		const cols = [];
		list.querySelectorAll('.efu-col-row').forEach(row => {
			const field  = row.querySelector('.efu-col-field').value;
			const header = row.querySelector('.efu-col-header').value;
			if (field) cols.push({ field, header: header || field });
		});
		document.getElementById('efu-columns-json').value = JSON.stringify(cols);

		const fd = new FormData(e.target);
		fd.append('action', 'efu_sjt_save_sheets');
		const r = await fetch(ajax, { method: 'POST', body: new URLSearchParams(fd) });
		const d = await r.json();
		showMsg(d.success ? d.data : d.data, !d.success);
	});

	document.getElementById('efu-push-headers').addEventListener('click', async () => {
		const r = await fetch(ajax, {
			method: 'POST',
			headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
			body: 'action=efu_sjt_push_headers&nonce=' + nonce,
		});
		const d = await r.json();
		showMsg(d.success ? d.data : d.data, !d.success);
	});

	document.getElementById('efu-sync-all').addEventListener('click', async () => {
		const btn = document.getElementById('efu-sync-all');
		btn.textContent = 'Syncing…';
		const r = await fetch(ajax, {
			method: 'POST',
			headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
			body: 'action=efu_sjt_sync_all&nonce=' + nonce,
		});
		const d = await r.json();
		btn.textContent = '↑ Sync All Submissions';
		showMsg(d.success ? d.data : d.data, !d.success);
	});
})();
</script>
