<?php
if ( ! defined( 'ABSPATH' ) ) exit;
if ( ! current_user_can( 'manage_options' ) ) return;

$assessment = EFU_SJT_Scorer::load_assessment();
if ( ! $assessment ) {
	echo '<div class="wrap"><p class="notice notice-error">Assessment data not found.</p></div>';
	return;
}

$level_labels = [ 1 => 'Developing', 2 => 'Proficient', 3 => 'Advanced', 4 => 'Role Model' ];
?>
<div class="wrap efu-admin-wrap">
	<div class="efu-admin-header">
		<h1>Assessment Questions Editor</h1>
		<button id="efu-save-all" class="button button-primary efu-save-btn">&#10003; Save All Changes</button>
	</div>
	<div id="efu-save-status" class="efu-save-status" style="display:none;"></div>

	<div id="efu-questions-root" data-nonce="<?php echo esc_attr( wp_create_nonce( 'efu_sjt_questions' ) ); ?>">
	<?php foreach ( $assessment['pillars'] as $pi => $pillar ) : ?>
		<div class="efu-pillar-block" data-pillar="<?php echo esc_attr( $pi ); ?>">
			<div class="efu-pillar-header">
				<h2><?php echo esc_html( $pillar['label'] ); ?></h2>
				<span class="efu-pillar-id"><?php echo esc_html( $pillar['id'] ); ?></span>
			</div>

			<?php foreach ( $pillar['competencies'] as $ci => $comp ) : ?>
			<div class="efu-competency-block" data-comp="<?php echo esc_attr( $ci ); ?>">
				<div class="efu-comp-header">
					<h3 class="efu-comp-label" contenteditable="true"
						data-path="pillars.<?php echo $pi; ?>.competencies.<?php echo $ci; ?>.label"><?php echo esc_html( $comp['label'] ); ?></h3>
				</div>

				<?php foreach ( $comp['items'] as $ii => $item ) : ?>
				<div class="efu-scenario-card" data-item="<?php echo esc_attr( $ii ); ?>">
					<div class="efu-scenario-meta">
						<span class="efu-scenario-id">ID: <?php echo esc_html( $item['id'] ); ?></span>
						<?php if ( count( $comp['items'] ) > 2 ) : ?>
						<button class="efu-remove-scenario button button-small button-link-delete"
							data-path="pillars.<?php echo $pi; ?>.competencies.<?php echo $ci; ?>.items.<?php echo $ii; ?>">
							&times; Remove Scenario
						</button>
						<?php else : ?>
						<span class="efu-min-warning" title="Minimum 2 scenarios per competency">&#9888; Minimum reached</span>
						<?php endif; ?>
					</div>

					<div class="efu-scenario-text-wrap">
						<label>Scenario</label>
						<div class="efu-editable efu-scenario-text" contenteditable="true"
							data-path="pillars.<?php echo $pi; ?>.competencies.<?php echo $ci; ?>.items.<?php echo $ii; ?>.scenario"><?php echo esc_html( $item['scenario'] ); ?></div>
					</div>

					<div class="efu-options-grid">
						<?php foreach ( $item['options'] as $oi => $option ) : ?>
						<div class="efu-option-row" data-option="<?php echo esc_attr( $oi ); ?>">
							<span class="efu-option-badge"><?php echo esc_html( $option['letter'] ); ?></span>
							<div class="efu-editable efu-option-text" contenteditable="true"
								data-path="pillars.<?php echo $pi; ?>.competencies.<?php echo $ci; ?>.items.<?php echo $ii; ?>.options.<?php echo $oi; ?>.text"><?php echo esc_html( $option['text'] ); ?></div>
							<select class="efu-option-level"
								data-path="pillars.<?php echo $pi; ?>.competencies.<?php echo $ci; ?>.items.<?php echo $ii; ?>.options.<?php echo $oi; ?>.level">
								<?php foreach ( $level_labels as $lv => $ll ) : ?>
								<option value="<?php echo $lv; ?>" <?php selected( $option['level'], $lv ); ?>>L<?php echo $lv; ?> — <?php echo esc_html( $ll ); ?></option>
								<?php endforeach; ?>
							</select>
						</div>
						<?php endforeach; ?>
					</div>
				</div>
				<?php endforeach; ?>

				<button class="efu-add-scenario button button-secondary"
					data-pillar="<?php echo $pi; ?>" data-comp="<?php echo $ci; ?>">
					+ Add Scenario
				</button>
			</div>
			<?php endforeach; ?>
		</div>
	<?php endforeach; ?>
	</div>
</div>
