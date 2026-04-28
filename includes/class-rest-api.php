<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class EFU_SJT_REST_API {

	public static function register_routes() {
		register_rest_route( 'efu-sjt/v1', '/submit', [
			'methods'             => WP_REST_Server::CREATABLE,
			'callback'            => [ __CLASS__, 'handle_submit' ],
			'permission_callback' => '__return_true',
			'args'                => self::submit_args(),
		] );

		register_rest_route( 'efu-sjt/v1', '/draft', [
			'methods'             => WP_REST_Server::CREATABLE,
			'callback'            => [ __CLASS__, 'handle_save_draft' ],
			'permission_callback' => '__return_true',
		] );

		register_rest_route( 'efu-sjt/v1', '/draft/(?P<token>[a-zA-Z0-9_\-]{10,80})', [
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ __CLASS__, 'handle_load_draft' ],
				'permission_callback' => '__return_true',
			],
			[
				'methods'             => WP_REST_Server::DELETABLE,
				'callback'            => [ __CLASS__, 'handle_delete_draft' ],
				'permission_callback' => '__return_true',
			],
		] );
	}

	public static function handle_save_draft( WP_REST_Request $request ): WP_REST_Response {
		$token = sanitize_key( $request->get_param( 'token' ) ?? '' );
		if ( ! $token || strlen( $token ) < 10 ) {
			return new WP_REST_Response( [ 'success' => false ], 400 );
		}

		$step     = absint( $request->get_param( 'step' ) ?? 0 );
		$raw_user = (array) ( $request->get_param( 'userData' ) ?? [] );
		$userData = [
			'name'       => sanitize_text_field( $raw_user['name']       ?? '' ),
			'email'      => sanitize_email(      $raw_user['email']      ?? '' ),
			'age'        => absint(              $raw_user['age']        ?? 0  ),
			'gender'     => sanitize_text_field( $raw_user['gender']     ?? '' ),
			'department' => sanitize_text_field( $raw_user['department'] ?? '' ),
		];

		$responses = [];
		foreach ( (array) ( $request->get_param( 'responses' ) ?? [] ) as $k => $v ) {
			$clean = sanitize_key( $k );
			if ( $clean ) $responses[ $clean ] = absint( $v );
		}

		$data = [
			'token'     => $token,
			'step'      => $step,
			'userData'  => $userData,
			'responses' => $responses,
			'savedAt'   => current_time( 'mysql' ),
		];

		set_transient( 'efu_sjt_d_' . $token, $data, 30 * DAY_IN_SECONDS );
		return new WP_REST_Response( [ 'success' => true ], 200 );
	}

	public static function handle_load_draft( WP_REST_Request $request ): WP_REST_Response {
		$token = sanitize_key( $request->get_param( 'token' ) ?? '' );
		$data  = $token ? get_transient( 'efu_sjt_d_' . $token ) : false;

		if ( ! $data ) {
			return new WP_REST_Response( [ 'success' => false, 'message' => 'No saved session found.' ], 404 );
		}

		return new WP_REST_Response( [ 'success' => true, 'data' => $data ], 200 );
	}

	public static function handle_delete_draft( WP_REST_Request $request ): WP_REST_Response {
		$token = sanitize_key( $request->get_param( 'token' ) ?? '' );
		if ( $token ) delete_transient( 'efu_sjt_d_' . $token );
		return new WP_REST_Response( [ 'success' => true ], 200 );
	}

	private static function submit_args(): array {
		return [
			'name'       => [
				'required'          => true,
				'sanitize_callback' => 'sanitize_text_field',
				'validate_callback' => fn( $v ) => ! empty( trim( $v ) ) && mb_strlen( $v ) <= 150,
			],
			'email'      => [
				'required'          => true,
				'sanitize_callback' => 'sanitize_email',
				'validate_callback' => fn( $v ) => is_email( $v ),
			],
			'age'        => [
				'required'          => true,
				'sanitize_callback' => 'absint',
				'validate_callback' => fn( $v ) => is_numeric( $v ) && $v >= 18 && $v <= 70,
			],
			'gender'     => [
				'required'          => true,
				'sanitize_callback' => 'sanitize_text_field',
				'validate_callback' => fn( $v ) => in_array( $v, [ 'Male', 'Female', 'Prefer not to say' ], true ),
			],
			'department' => [
				'required'          => true,
				'sanitize_callback' => 'sanitize_text_field',
				'validate_callback' => fn( $v ) => ! empty( trim( $v ) ) && mb_strlen( $v ) <= 150,
			],
			'responses'  => [
				'required'          => true,
				'validate_callback' => fn( $v ) => self::validate_responses( $v ),
			],
		];
	}

	private static function validate_responses( $value ): bool {
		if ( is_string( $value ) ) {
			$value = json_decode( $value, true );
		}
		if ( ! is_array( $value ) || empty( $value ) ) {
			return false;
		}
		foreach ( $value as $k => $v ) {
			if ( ! preg_match( '/^[\w]+_[A-D]$/', $k ) ) {
				return false;
			}
			if ( ! is_numeric( $v ) || intval( $v ) < 0 || intval( $v ) > 10 ) {
				return false;
			}
		}
		return true;
	}

	public static function handle_submit( WP_REST_Request $request ): WP_REST_Response {
		$email = sanitize_email( $request->get_param( 'email' ) );

		if ( EFU_SJT_Submission::email_submitted_recently( $email ) ) {
			return new WP_REST_Response(
				[ 'success' => false, 'message' => 'You have already submitted an assessment in the last 24 hours. Please try again later.' ],
				429
			);
		}

		$raw_responses = $request->get_param( 'responses' );
		if ( is_string( $raw_responses ) ) {
			$raw_responses = json_decode( $raw_responses, true );
		}

		$responses = [];
		foreach ( (array) $raw_responses as $k => $v ) {
			$responses[ sanitize_key( $k ) ] = absint( $v );
		}

		$result = EFU_SJT_Scorer::score( $responses );
		if ( ! $result ) {
			return new WP_REST_Response(
				[ 'success' => false, 'message' => 'Unable to score assessment. Please contact support.' ],
				500
			);
		}

		$insert_data = [
			'name'          => $request->get_param( 'name' ),
			'email'         => $email,
			'age'           => absint( $request->get_param( 'age' ) ),
			'gender'        => $request->get_param( 'gender' ),
			'department'    => $request->get_param( 'department' ),
			'responses'     => $responses,
			'scores'        => $result['scores'],
			'pillar_scores' => $result['pillar_scores'],
			'overall_score' => $result['overall_score'],
			'overall_level' => $result['overall_level'],
		];

		$id = EFU_SJT_Submission::insert( $insert_data );
		if ( ! $id ) {
			return new WP_REST_Response(
				[ 'success' => false, 'message' => 'Unable to save your submission. Please try again.' ],
				500
			);
		}

		$config = get_option( 'efu_sjt_sheets_config', [] );
		if ( ! empty( $config['auto_sync'] ) ) {
			$row = EFU_SJT_Submission::get_by_id( $id );
			if ( $row ) {
				EFU_SJT_Google_Sheets::push_row( $row );
			}
		}

		self::send_results_email(
			$insert_data['name'],
			$email,
			$result['overall_score'],
			$result['overall_level']
		);

		return new WP_REST_Response(
			[
				'success'       => true,
				'message'       => 'Your assessment has been submitted successfully.',
				'name'          => $insert_data['name'],
				'overall_score' => $result['overall_score'],
				'overall_level' => $result['overall_level'],
				'pillar_scores' => $result['pillar_scores'],
			],
			200
		);
	}

	private static function send_results_email( string $name, string $email, float $score, string $level ): void {
		$subject       = 'Your HOD Leadership Assessment Results — EFU Life';
		$score_str     = number_format( $score, 2 );
		$score_percent = round( max( 0, min( 100, ( ( $score - 1 ) / 3 ) * 100 ) ), 1 );
		$year          = gmdate( 'Y' );

		$scale_levels = [
			'Developing' => '1.0 &ndash; 1.9',
			'Proficient' => '2.0 &ndash; 2.6',
			'Advanced'   => '2.7 &ndash; 3.3',
			'Role Model' => '3.4 &ndash; 4.0',
		];

		$assessment        = EFU_SJT_Scorer::load_assessment();
		$level_descriptions = $assessment['meta']['level_descriptions'] ?? [];

		$desc_items = '';
		foreach ( $level_descriptions[ $level ] ?? [] as $bullet ) {
			$desc_items .= '<li style="font-size:13px;color:#4a6070;line-height:1.65;padding:4px 0 4px 16px;position:relative;">'
				. '<span style="position:absolute;left:0;color:#8aa0ae;">&ndash;</span>'
				. esc_html( $bullet )
				. '</li>';
		}

		$scale_rows = '';
		foreach ( $scale_levels as $lvl => $range ) {
			$is_active    = $lvl === $level;
			$item_bg      = $is_active ? 'background-color:#144864;border-color:#144864;' : '';
			$label_color  = $is_active ? 'color:#ffffff;' : 'color:#144864;';
			$range_color  = $is_active ? 'color:rgba(255,255,255,0.55);' : 'color:#9aacb8;';
			$scale_rows  .= '
        <td style="width:25%;padding:0 3px;">
          <div style="text-align:center;padding:9px 4px;border-radius:8px;border:1.5px solid #e4eaee;' . $item_bg . '">
            <span style="display:block;font-size:9px;font-weight:700;margin-bottom:2px;' . $label_color . '">' . esc_html( $lvl ) . '</span>
            <span style="display:block;font-size:8px;' . $range_color . '">' . $range . '</span>
          </div>
        </td>';
		}

		$html = '<!DOCTYPE html>
<html lang="en" xmlns="http://www.w3.org/1999/xhtml">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <meta http-equiv="X-UA-Compatible" content="IE=edge" />
  <title>EFU Life &mdash; Your Assessment Result</title>
  <style>
    * { margin:0; padding:0; box-sizing:border-box; }
    body,table,td,a { -webkit-text-size-adjust:100%; -ms-text-size-adjust:100%; }
    table,td { mso-table-lspace:0pt; mso-table-rspace:0pt; }
    img { border:0; outline:none; text-decoration:none; display:block; }
    body { width:100%!important; background-color:#f0f4f7; font-family:Arial,sans-serif; }
    @media only screen and (max-width:600px) {
      .email-wrap { padding:20px 12px!important; }
      .header-pad,
      .body-pad,
      .footer-pad { padding-left:24px!important; padding-right:24px!important; }
      .score-number { font-size:52px!important; }
    }
  </style>
</head>
<body style="background-color:#f0f4f7;margin:0;padding:0;">
<div class="email-wrap" style="background-color:#f0f4f7;padding:40px 20px;">
<table width="100%" cellpadding="0" cellspacing="0" border="0">
<tr><td align="center">
<table class="email-container" width="560" cellpadding="0" cellspacing="0" border="0" style="max-width:560px;width:100%;">

  <!-- HEADER -->
  <tr>
    <td class="header-pad" style="background-color:#144864;border-radius:16px 16px 0 0;padding:36px 48px 32px;text-align:center;">
      <div style="font-size:26px;font-weight:800;color:#ffffff;letter-spacing:3px;line-height:1;">EFU</div>
      <div style="font-size:10px;font-weight:400;color:rgba(255,255,255,0.5);letter-spacing:5px;text-transform:uppercase;margin-top:3px;">Life Assurance</div>
      <div style="width:32px;height:2px;background-color:#d90e78;margin:20px auto 18px;border-radius:2px;"></div>
      <div style="font-size:11px;color:rgba(255,255,255,0.45);letter-spacing:3px;text-transform:uppercase;">HOD Leadership Assessment</div>
    </td>
  </tr>

  <!-- BODY -->
  <tr>
    <td class="body-pad" style="background-color:#ffffff;padding:40px 48px;">

      <p style="font-size:15px;color:#4a6070;line-height:1.7;margin-bottom:32px;">
        Dear <strong style="color:#144864;">' . esc_html( $name ) . '</strong>,<br><br>
        Thank you for completing the HOD Situational Judgment Assessment.
        Here is a summary of your overall leadership score.
      </p>

      <!-- Score block -->
      <table width="100%" cellpadding="0" cellspacing="0" border="0" style="background:linear-gradient(140deg,#144864 0%,#1c6a88 100%);border-radius:14px;margin-bottom:12px;">
        <tr>
          <td style="padding:36px 28px 32px;text-align:center;">
            <div style="font-size:10px;font-weight:700;color:rgba(255,255,255,0.45);letter-spacing:3px;text-transform:uppercase;margin-bottom:14px;">Overall Leadership Score</div>
            <div class="score-number" style="font-size:68px;font-weight:800;color:#ffffff;line-height:1;letter-spacing:-2px;">
              ' . $score_str . '<span style="font-size:22px;font-weight:400;color:rgba(255,255,255,0.4);vertical-align:super;margin-left:2px;">/4.0</span>
            </div>
            <!-- Score bar -->
            <table width="100%" cellpadding="0" cellspacing="0" border="0" style="margin:22px auto 18px;max-width:300px;">
              <tr>
                <td style="background:rgba(255,255,255,0.13);border-radius:100px;height:5px;padding:0;">
                  <div style="width:' . $score_percent . '%;height:5px;border-radius:100px;background:linear-gradient(90deg,#d90e78,#ff5cad);"></div>
                </td>
              </tr>
            </table>
            <div style="display:inline-block;background-color:#d90e78;color:#ffffff;font-size:11px;font-weight:700;letter-spacing:2.5px;text-transform:uppercase;padding:8px 24px;border-radius:100px;">' . esc_html( $level ) . '</div>
          </td>
        </tr>
      </table>

      <!-- Scale -->
      <div style="margin:28px 0 36px;">
        <div style="font-size:10px;font-weight:700;color:#144864;letter-spacing:3px;text-transform:uppercase;margin-bottom:12px;">Where you stand</div>
        <table width="100%" cellpadding="0" cellspacing="0" border="0">
          <tr>' . $scale_rows . '</tr>
        </table>
      </div>

      <!-- Level description -->
      <div style="margin:0 0 32px;background:#f9fafb;border-left:4px solid ' . $color . ';border-radius:0 8px 8px 0;padding:18px 22px;">
        <div style="font-size:10px;font-weight:700;color:#8aa0ae;text-transform:uppercase;letter-spacing:3px;margin-bottom:12px;">What This Level Means</div>
        <ul style="margin:0;padding:0;list-style:none;">' . $desc_items . '</ul>
      </div>

      <p style="font-size:14px;color:#4a6070;line-height:1.7;">
        Warm regards,<br>
        <strong style="color:#144864;">EFU Life &mdash; HR &amp; Organizational Development</strong>
      </p>

    </td>
  </tr>

  <!-- FOOTER -->
  <tr>
    <td class="footer-pad" style="background-color:#0f3549;border-radius:0 0 16px 16px;padding:24px 48px;text-align:center;">
      <p style="font-size:11px;color:rgba(255,255,255,0.35);line-height:1.7;">
        Developed by <a href="https://funverks.com" style="color:rgba(255,255,255,0.45);text-decoration:none;">Funverks</a> &nbsp;&middot;&nbsp;
        &copy; ' . $year . ' Funverks. All rights reserved.<br>
        This email was sent to <a href="mailto:' . esc_attr( $email ) . '" style="color:rgba(255,255,255,0.45);text-decoration:none;">' . esc_html( $email ) . '</a>
      </p>
    </td>
  </tr>

</table>
</td></tr>
</table>
</div>
</body>
</html>';

		$headers = [
			'Content-Type: text/html; charset=UTF-8',
			'From: EFU Life Assessment <' . get_option( 'admin_email' ) . '>',
		];

		wp_mail( $email, $subject, $html, $headers );
	}
}
