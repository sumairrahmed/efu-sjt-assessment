<?php
if ( ! defined( 'ABSPATH' ) ) exit;
if ( ! current_user_can( 'manage_options' ) ) return;

$logo_url = get_option( 'efu_sjt_logo_url', '' );
$nonce    = wp_create_nonce( 'efu_sjt_settings' );
?>
<div class="wrap efu-admin-wrap">
	<div class="efu-admin-header">
		<h1>Assessment Settings</h1>
	</div>

	<div id="efu-settings-msg" class="notice" style="display:none;"></div>

	<table class="form-table efu-settings-table">
		<tr>
			<th scope="row"><label>Welcome Screen Logo</label></th>
			<td>
				<div class="efu-logo-picker">
					<div class="efu-logo-preview-wrap" id="efu-logo-preview-wrap"
						style="<?php echo $logo_url ? '' : 'display:none;'; ?>">
						<img id="efu-logo-preview"
							src="<?php echo esc_url( $logo_url ); ?>"
							alt="Logo preview">
					</div>
					<input type="hidden" id="efu-logo-url" value="<?php echo esc_attr( $logo_url ); ?>">
					<div class="efu-logo-actions">
						<button type="button" id="efu-logo-select" class="button">
							<?php echo $logo_url ? 'Change Image' : 'Select from Media Library'; ?>
						</button>
						<button type="button" id="efu-logo-remove" class="button button-link-delete"
							style="<?php echo $logo_url ? '' : 'display:none;'; ?>">
							Remove
						</button>
					</div>
					<p class="description" style="margin-top:8px;">
						Displayed on the assessment welcome screen and thank-you page. Recommended: PNG with transparent background, height ~80px.
					</p>
				</div>
			</td>
		</tr>
	</table>

	<p class="submit">
		<button id="efu-save-settings" class="button button-primary">Save Settings</button>
	</p>
</div>

<style>
.efu-logo-preview-wrap {
	margin-bottom: 12px;
}
.efu-logo-preview-wrap img {
	max-height: 80px;
	max-width: 300px;
	display: block;
	border: 1px solid #e8edf1;
	border-radius: 8px;
	padding: 8px;
	background: #f0f5f8;
}
.efu-logo-actions {
	display: flex;
	gap: 8px;
	align-items: center;
}
</style>

<script>
(function () {
	const nonce   = <?php echo wp_json_encode( $nonce ); ?>;
	const ajaxUrl = <?php echo wp_json_encode( admin_url( 'admin-ajax.php' ) ); ?>;
	const msg     = document.getElementById('efu-settings-msg');

	function showMsg(text, isError) {
		msg.className      = 'notice ' + (isError ? 'notice-error' : 'notice-success');
		msg.innerHTML      = '<p>' + text + '</p>';
		msg.style.display  = 'block';
		setTimeout(() => { msg.style.display = 'none'; }, 4000);
	}

	// ── Media picker ──────────────────────────────────
	let mediaFrame;

	document.getElementById('efu-logo-select').addEventListener('click', () => {
		if (!mediaFrame) {
			mediaFrame = wp.media({
				title:    'Select Assessment Logo',
				button:   { text: 'Use this image' },
				multiple: false,
				library:  { type: 'image' },
			});

			mediaFrame.on('select', () => {
				const att     = mediaFrame.state().get('selection').first().toJSON();
				const url     = att.sizes?.medium?.url || att.url;
				const urlFull = att.url;

				document.getElementById('efu-logo-url').value = urlFull;

				const preview = document.getElementById('efu-logo-preview');
				const wrap    = document.getElementById('efu-logo-preview-wrap');
				preview.src         = url;
				wrap.style.display  = '';

				const selectBtn = document.getElementById('efu-logo-select');
				selectBtn.textContent = 'Change Image';

				document.getElementById('efu-logo-remove').style.display = '';
			});
		}
		mediaFrame.open();
	});

	document.getElementById('efu-logo-remove').addEventListener('click', () => {
		document.getElementById('efu-logo-url').value        = '';
		document.getElementById('efu-logo-preview').src      = '';
		document.getElementById('efu-logo-preview-wrap').style.display = 'none';
		document.getElementById('efu-logo-remove').style.display       = 'none';
		document.getElementById('efu-logo-select').textContent         = 'Select from Media Library';
	});

	// ── Save ──────────────────────────────────────────
	document.getElementById('efu-save-settings').addEventListener('click', async () => {
		const logoUrl = document.getElementById('efu-logo-url').value;
		const btn     = document.getElementById('efu-save-settings');
		btn.disabled  = true;

		const r = await fetch(ajaxUrl, {
			method:  'POST',
			headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
			body:    'action=efu_sjt_save_settings&nonce=' + nonce + '&logo_url=' + encodeURIComponent(logoUrl),
		});
		const d = await r.json();
		showMsg(d.success ? '✓ ' + d.data : '✗ ' + d.data, !d.success);
		btn.disabled = false;
	});
})();
</script>
