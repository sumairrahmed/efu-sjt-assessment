<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class EFU_SJT_Shortcode {

	private static bool $enqueued = false;

	public static function init() {
		add_shortcode( 'efu_sjt_assessment', [ __CLASS__, 'render' ] );
		add_action( 'wp_footer', [ __CLASS__, 'maybe_enqueue' ] );
	}

	public static function render( $atts ): string {
		self::$enqueued = true;

		$assessment = EFU_SJT_Scorer::load_assessment();
		if ( ! $assessment ) {
			return '<p class="efu-error">Assessment configuration not found. Please contact the site administrator.</p>';
		}

		$nonce    = wp_create_nonce( 'efu_sjt_submit' );
		$rest_url = esc_url( rest_url( 'efu-sjt/v1/submit' ) );

		ob_start();
		?>
<div class="efu-sjt-wrap" id="efu-sjt-root"
	data-nonce="<?php echo esc_attr( $nonce ); ?>"
	data-rest="<?php echo esc_attr( $rest_url ); ?>">
	<div id="efu-sjt-progress-bar-wrap">
		<div id="efu-sjt-progress-bar"></div>
	</div>
	<div id="efu-sjt-step-dots"></div>
	<div id="efu-sjt-app"></div>
</div>
		<?php
		return ob_get_clean();
	}

	public static function maybe_enqueue() {
		if ( ! self::$enqueued ) return;

		$assessment = EFU_SJT_Scorer::load_assessment();

		wp_enqueue_style(
			'efu-poppins',
			'https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap',
			[],
			null
		);

		wp_enqueue_style(
			'efu-sjt-frontend',
			EFU_SJT_PLUGIN_URL . 'assets/css/frontend.css',
			[ 'efu-poppins' ],
			EFU_SJT_VERSION
		);

		wp_enqueue_script(
			'efu-sjt-quiz',
			EFU_SJT_PLUGIN_URL . 'assets/js/frontend-quiz.js',
			[],
			EFU_SJT_VERSION,
			true
		);

		$saved_logo = get_option( 'efu_sjt_logo_url', '' );
		$logo_url   = $saved_logo ?: EFU_SJT_PLUGIN_URL . 'assets/img/efu-logo.png';

		wp_localize_script( 'efu-sjt-quiz', 'efuSJT', [
			'assessment'   => $assessment,
			'restUrl'      => rest_url( 'efu-sjt/v1/submit' ),
			'draftUrl'     => rest_url( 'efu-sjt/v1/draft' ),
			'nonce'        => wp_create_nonce( 'wp_rest' ),
			'logoUrl'      => $logo_url,
			'pillarsCount' => count( $assessment['pillars'] ?? [] ),
		] );
	}
}
