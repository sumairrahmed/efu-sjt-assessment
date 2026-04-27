<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class EFU_SJT_Google_Sheets {

	private static string $last_error = '';

	public static function get_last_error(): string {
		return self::$last_error;
	}

	private static function fail( string $message ): void {
		self::$last_error = $message;
	}

	private static function get_config(): array {
		return get_option( 'efu_sjt_sheets_config', [] );
	}

	private static function get_service_account(): ?array {
		$raw = get_option( 'efu_sjt_sheets_service_account', '' );
		if ( ! $raw ) return null;
		$sa = json_decode( $raw, true );
		return is_array( $sa ) ? $sa : null;
	}

	private static function get_access_token(): ?string {
		$sa = self::get_service_account();
		if ( ! $sa || empty( $sa['private_key'] ) || empty( $sa['client_email'] ) ) {
			self::fail( 'No service account credentials found. Paste your service account JSON and click Save Settings first.' );
			return null;
		}

		$cached = get_transient( 'efu_sjt_gsheet_token' );
		if ( $cached ) return $cached;

		$now     = time();
		$header  = self::base64url_encode( wp_json_encode( [ 'alg' => 'RS256', 'typ' => 'JWT' ] ) );
		$payload = self::base64url_encode( wp_json_encode( [
			'iss'   => $sa['client_email'],
			'scope' => 'https://www.googleapis.com/auth/spreadsheets',
			'aud'   => 'https://oauth2.googleapis.com/token',
			'iat'   => $now,
			'exp'   => $now + 3600,
		] ) );

		$signing_input = $header . '.' . $payload;
		$private_key   = openssl_pkey_get_private( $sa['private_key'] );
		if ( ! $private_key ) {
			self::fail( 'Could not parse the private key from your service account JSON. Make sure you pasted the complete, unmodified file content.' );
			return null;
		}

		$signature = '';
		if ( ! openssl_sign( $signing_input, $signature, $private_key, 'sha256WithRSAEncryption' ) ) {
			self::fail( 'JWT signing failed. OpenSSL error: ' . openssl_error_string() );
			return null;
		}
		$jwt = $signing_input . '.' . self::base64url_encode( $signature );

		$response = wp_remote_post( 'https://oauth2.googleapis.com/token', [
			'timeout' => 15,
			'body'    => [
				'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
				'assertion'  => $jwt,
			],
		] );

		if ( is_wp_error( $response ) ) {
			self::fail( 'HTTP request to Google failed: ' . $response->get_error_message() );
			return null;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( empty( $body['access_token'] ) ) {
			$google_err = $body['error_description'] ?? $body['error'] ?? 'Unknown error';
			self::fail( 'Google token exchange failed: ' . $google_err );
			return null;
		}

		set_transient( 'efu_sjt_gsheet_token', $body['access_token'], 3500 );
		return $body['access_token'];
	}

	private static function base64url_encode( string $data ): string {
		return rtrim( strtr( base64_encode( $data ), '+/', '-_' ), '=' );
	}

	public static function push_row( object $row ): bool {
		$token = self::get_access_token();
		if ( ! $token ) return false;

		$config = self::get_config();
		if ( empty( $config['sheet_id'] ) ) {
			self::fail( 'Sheet ID is not configured.' );
			return false;
		}

		$sheet_name = ! empty( $config['sheet_name'] ) ? $config['sheet_name'] : 'Sheet1';
		$columns    = self::get_columns( $config );
		$values     = self::row_to_values( $row, array_column( $columns, 'field' ) );

		$url = sprintf(
			'https://sheets.googleapis.com/v4/spreadsheets/%s/values/%s:append?valueInputOption=USER_ENTERED',
			rawurlencode( $config['sheet_id'] ),
			rawurlencode( $sheet_name )
		);

		$response = wp_remote_post( $url, [
			'headers' => [
				'Authorization' => 'Bearer ' . $token,
				'Content-Type'  => 'application/json',
			],
			'body' => wp_json_encode( [ 'values' => [ $values ] ] ),
		] );

		if ( is_wp_error( $response ) ) {
			self::fail( 'HTTP error appending row: ' . $response->get_error_message() );
			return false;
		}

		$code = wp_remote_retrieve_response_code( $response );
		if ( $code !== 200 ) {
			$api_err = json_decode( wp_remote_retrieve_body( $response ), true );
			$msg     = $api_err['error']['message'] ?? wp_remote_retrieve_response_message( $response );

			if ( $code === 403 ) {
				$sa    = self::get_service_account();
				$email = $sa['client_email'] ?? 'your service account email';
				self::fail( "Permission denied (HTTP 403). Share the Google Sheet with \"{$email}\" as an Editor." );
			} else {
				self::fail( "Sheets API returned HTTP {$code}: {$msg}" );
			}
			return false;
		}

		update_option( 'efu_sjt_sheets_last_sync', current_time( 'mysql' ) );
		return true;
	}

	public static function push_headers(): bool {
		$token = self::get_access_token();
		if ( ! $token ) return false;

		$config = self::get_config();
		if ( empty( $config['sheet_id'] ) ) {
			self::fail( 'Sheet ID is not configured.' );
			return false;
		}

		$sheet_name = ! empty( $config['sheet_name'] ) ? $config['sheet_name'] : 'Sheet1';
		$columns    = self::get_columns( $config );
		$headers    = array_column( $columns, 'header' );

		$url = sprintf(
			'https://sheets.googleapis.com/v4/spreadsheets/%s/values/%s!A1?valueInputOption=USER_ENTERED',
			rawurlencode( $config['sheet_id'] ),
			rawurlencode( $sheet_name )
		);

		$response = wp_remote_request( $url, [
			'method'  => 'PUT',
			'headers' => [
				'Authorization' => 'Bearer ' . $token,
				'Content-Type'  => 'application/json',
			],
			'body' => wp_json_encode( [ 'values' => [ $headers ] ] ),
		] );

		if ( is_wp_error( $response ) ) {
			self::fail( 'HTTP error writing headers: ' . $response->get_error_message() );
			return false;
		}

		$code = wp_remote_retrieve_response_code( $response );
		if ( $code !== 200 ) {
			$api_err = json_decode( wp_remote_retrieve_body( $response ), true );
			$msg     = $api_err['error']['message'] ?? wp_remote_retrieve_response_message( $response );

			if ( $code === 403 ) {
				$sa    = self::get_service_account();
				$email = $sa['client_email'] ?? 'your service account email';
				self::fail( "Permission denied (HTTP 403). Share the Google Sheet with \"{$email}\" as an Editor, then retry." );
			} else {
				self::fail( "Sheets API returned HTTP {$code}: {$msg}" );
			}
			return false;
		}

		return true;
	}

	public static function sync_all(): array {
		$all    = EFU_SJT_Submission::get_all_for_export();
		$pushed = 0;

		foreach ( $all as $row ) {
			if ( self::push_row( $row ) ) {
				$pushed++;
			}
		}

		return [ 'pushed' => $pushed, 'total' => count( $all ) ];
	}

	public static function get_sheet_row_count(): int {
		$token = self::get_access_token();
		if ( ! $token ) return -1;

		$config = self::get_config();
		if ( empty( $config['sheet_id'] ) ) return -1;

		$sheet_name = ! empty( $config['sheet_name'] ) ? $config['sheet_name'] : 'Sheet1';
		$url = sprintf(
			'https://sheets.googleapis.com/v4/spreadsheets/%s/values/%s',
			rawurlencode( $config['sheet_id'] ),
			rawurlencode( $sheet_name )
		);

		$response = wp_remote_get( $url, [
			'headers' => [ 'Authorization' => 'Bearer ' . $token ],
		] );

		if ( is_wp_error( $response ) ) return -1;

		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		return isset( $body['values'] ) ? count( $body['values'] ) : 0;
	}

	public static function available_fields_with_labels(): array {
		return [
			'id'                            => 'ID',
			'name'                          => 'Full Name',
			'email'                         => 'Email',
			'age'                           => 'Age',
			'gender'                        => 'Gender',
			'department'                    => 'Department',
			'submitted_at'                  => 'Submitted At',
			'overall_score'                 => 'Overall Score',
			'overall_level'                 => 'Overall Level',
			'pillar_scores'                 => 'All Pillar Scores (combined)',
			'pillar_growth_strategy'        => 'Pillar: Growth & Strategy',
			'pillar_customer_market_focus'  => 'Pillar: Customer & Market Focus',
			'pillar_people_culture'         => 'Pillar: People & Culture',
			'pillar_operational_excellence' => 'Pillar: Operational Excellence',
			'pillar_innovation_change'      => 'Pillar: Innovation & Change',
		];
	}

	public static function available_fields(): array {
		return array_keys( self::available_fields_with_labels() );
	}

	private static function get_columns( array $config ): array {
		$labels = self::available_fields_with_labels();

		if ( ! empty( $config['columns'] ) && is_array( $config['columns'] ) ) {
			return $config['columns'];
		}

		$fields = ! empty( $config['field_order'] ) && is_array( $config['field_order'] )
			? $config['field_order']
			: [ 'id', 'name', 'email', 'age', 'gender', 'department', 'overall_score', 'overall_level', 'submitted_at' ];

		return array_map( fn( $f ) => [
			'field'  => $f,
			'header' => $labels[ $f ] ?? ucwords( str_replace( '_', ' ', $f ) ),
		], $fields );
	}

	private static function row_to_values( object $row, array $fields ): array {
		$pillar_scores = json_decode( $row->pillar_scores ?? '{}', true ) ?: [];
		$values = [];

		foreach ( $fields as $field ) {
			if ( strpos( $field, 'pillar_' ) === 0 ) {
				$pillar_id = substr( $field, 7 );
				if ( $pillar_id === 'scores' ) {
					$values[] = implode( ' | ', array_map(
						fn( $k, $v ) => "$k: " . number_format( (float) $v, 2 ),
						array_keys( $pillar_scores ),
						array_values( $pillar_scores )
					) );
				} else {
					$values[] = isset( $pillar_scores[ $pillar_id ] )
						? number_format( (float) $pillar_scores[ $pillar_id ], 2 )
						: '';
				}
			} else {
				$values[] = $row->$field ?? '';
			}
		}

		return $values;
	}
}
