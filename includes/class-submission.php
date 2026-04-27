<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class EFU_SJT_Submission {

	private static function table() {
		global $wpdb;
		return $wpdb->prefix . EFU_SJT_TABLE;
	}

	public static function insert( array $data ) {
		global $wpdb;

		$inserted = $wpdb->insert(
			self::table(),
			[
				'name'          => sanitize_text_field( $data['name'] ),
				'email'         => sanitize_email( $data['email'] ),
				'age'           => absint( $data['age'] ),
				'gender'        => sanitize_text_field( $data['gender'] ),
				'department'    => sanitize_text_field( $data['department'] ),
				'responses'     => wp_json_encode( $data['responses'] ),
				'scores'        => wp_json_encode( $data['scores'] ),
				'pillar_scores' => wp_json_encode( $data['pillar_scores'] ),
				'overall_score' => round( floatval( $data['overall_score'] ), 2 ),
				'overall_level' => sanitize_text_field( $data['overall_level'] ),
				'submitted_at'  => current_time( 'mysql' ),
			],
			[ '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%f', '%s', '%s' ]
		);

		return $inserted ? $wpdb->insert_id : false;
	}

	public static function email_submitted_recently( string $email ) {
		global $wpdb;

		$count = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM %i WHERE email = %s AND submitted_at >= %s",
				self::table(),
				sanitize_email( $email ),
				gmdate( 'Y-m-d H:i:s', strtotime( '-24 hours' ) )
			)
		);

		return $count > 0;
	}

	public static function get_all( array $args = [] ) {
		global $wpdb;

		$defaults = [
			'per_page'   => 20,
			'page'       => 1,
			'date_from'  => '',
			'date_to'    => '',
			'gender'     => '',
			'department' => '',
			'level'      => '',
			'orderby'    => 'submitted_at',
			'order'      => 'DESC',
		];
		$args = wp_parse_args( $args, $defaults );

		$where  = [];
		$values = [];

		if ( ! empty( $args['date_from'] ) ) {
			$where[]  = 'submitted_at >= %s';
			$values[] = $args['date_from'] . ' 00:00:00';
		}
		if ( ! empty( $args['date_to'] ) ) {
			$where[]  = 'submitted_at <= %s';
			$values[] = $args['date_to'] . ' 23:59:59';
		}
		if ( ! empty( $args['gender'] ) ) {
			$where[]  = 'gender = %s';
			$values[] = $args['gender'];
		}
		if ( ! empty( $args['department'] ) ) {
			$where[]  = 'department LIKE %s';
			$values[] = '%' . $wpdb->esc_like( $args['department'] ) . '%';
		}
		if ( ! empty( $args['level'] ) ) {
			$where[]  = 'overall_level = %s';
			$values[] = $args['level'];
		}

		$allowed_orderby = [ 'id', 'name', 'email', 'age', 'gender', 'department', 'overall_score', 'overall_level', 'submitted_at' ];
		$orderby         = in_array( $args['orderby'], $allowed_orderby, true ) ? $args['orderby'] : 'submitted_at';
		$order           = strtoupper( $args['order'] ) === 'ASC' ? 'ASC' : 'DESC';

		$where_sql = $where ? 'WHERE ' . implode( ' AND ', $where ) : '';
		$offset    = ( absint( $args['page'] ) - 1 ) * absint( $args['per_page'] );

		$base_query = "SELECT * FROM %i {$where_sql} ORDER BY {$orderby} {$order} LIMIT %d OFFSET %d";
		$count_sql  = "SELECT COUNT(*) FROM %i {$where_sql}";

		$query_vals       = array_merge( [ self::table() ], $values );
		$count_query_vals = array_merge( [ self::table() ], $values );

		$rows  = $wpdb->get_results( $wpdb->prepare( $base_query, array_merge( $query_vals, [ absint( $args['per_page'] ), $offset ] ) ) );
		$total = (int) $wpdb->get_var( $wpdb->prepare( $count_sql, $count_query_vals ) );

		return compact( 'rows', 'total' );
	}

	public static function get_by_id( int $id ) {
		global $wpdb;
		return $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM %i WHERE id = %d", self::table(), $id )
		);
	}

	public static function delete( int $id ) {
		global $wpdb;
		return $wpdb->delete( self::table(), [ 'id' => $id ], [ '%d' ] );
	}

	public static function get_stats() {
		global $wpdb;
		$table = self::table();

		$levels = $wpdb->get_results(
			$wpdb->prepare( "SELECT overall_level, COUNT(*) as cnt FROM %i GROUP BY overall_level", $table )
		);

		$daily = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT DATE(submitted_at) as day, COUNT(*) as cnt FROM %i WHERE submitted_at >= %s GROUP BY DATE(submitted_at) ORDER BY day ASC",
				$table,
				gmdate( 'Y-m-d', strtotime( '-30 days' ) )
			)
		);

		$pillar_rows = $wpdb->get_results(
			$wpdb->prepare( "SELECT pillar_scores FROM %i", $table )
		);

		$pillar_sums  = [];
		$pillar_count = 0;
		foreach ( $pillar_rows as $row ) {
			$ps = json_decode( $row->pillar_scores, true );
			if ( is_array( $ps ) ) {
				foreach ( $ps as $pid => $score ) {
					$pillar_sums[ $pid ] = ( $pillar_sums[ $pid ] ?? 0 ) + floatval( $score );
				}
				$pillar_count++;
			}
		}

		$pillar_averages = [];
		foreach ( $pillar_sums as $pid => $sum ) {
			$pillar_averages[ $pid ] = $pillar_count > 0 ? round( $sum / $pillar_count, 2 ) : 0;
		}

		return [
			'levels'          => $levels,
			'daily'           => $daily,
			'pillar_averages' => $pillar_averages,
		];
	}

	public static function get_summary_stats(): array {
		global $wpdb;
		$table = self::table();

		$total      = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i', $table ) );
		$avg        = $total > 0
			? (float) $wpdb->get_var( $wpdb->prepare( 'SELECT AVG(overall_score) FROM %i', $table ) )
			: 0.0;
		$this_month = (int) $wpdb->get_var( $wpdb->prepare(
			'SELECT COUNT(*) FROM %i WHERE submitted_at >= %s',
			$table,
			gmdate( 'Y-m-01 00:00:00' )
		) );

		return [
			'total'      => $total,
			'avg_score'  => round( $avg, 2 ),
			'this_month' => $this_month,
		];
	}

	public static function get_all_for_export( array $filters = [] ) {
		$args         = array_merge( $filters, [ 'per_page' => 9999, 'page' => 1 ] );
		$result       = self::get_all( $args );
		return $result['rows'];
	}

	public static function get_unsynced() {
		global $wpdb;
		return $wpdb->get_results(
			$wpdb->prepare( "SELECT * FROM %i WHERE synced_to_sheets = 0 ORDER BY id ASC", self::table() )
		);
	}
}
