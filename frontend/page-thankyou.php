<?php
// This file is loaded as a PHP partial by class-shortcode.php if needed.
// The thank-you state is normally rendered by frontend-quiz.js after a successful submission.
if ( ! defined( 'ABSPATH' ) ) exit;
?>
<div class="efu-thankyou">
	<div class="efu-thankyou-logo">
		<img src="<?php echo esc_url( EFU_SJT_PLUGIN_URL . 'assets/img/efu-logo.png' ); ?>" alt="EFU Life" onerror="this.style.display='none'">
	</div>
	<div class="efu-thankyou-icon">&#10003;</div>
	<h2 class="efu-thankyou-heading">Thank you!</h2>
	<p class="efu-thankyou-msg">
		Your assessment has been submitted successfully.<br>
		Our team will be in touch with your development report.
	</p>
</div>
