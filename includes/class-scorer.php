<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class EFU_SJT_Scorer {

	public static function score( array $responses ) {
		$assessment = self::load_assessment();
		if ( ! $assessment ) {
			return false;
		}

		$competency_scores = [];
		$pillar_scores     = [];

		foreach ( $assessment['pillars'] as $pillar ) {
			$pillar_comp_scores = [];

			foreach ( $pillar['competencies'] as $comp ) {
				$weighted_total = 0;
				$max_points     = 0;

				foreach ( $comp['items'] as $item ) {
					$max_points += 10;
					foreach ( $item['options'] as $option ) {
						$key     = $item['id'] . '_' . strtolower( $option['letter'] );
						$pts     = isset( $responses[ $key ] ) ? intval( $responses[ $key ] ) : 0;
						$weighted_total += $pts * intval( $option['level'] );
					}
				}

				$score = $max_points > 0 ? $weighted_total / $max_points : 0;
				$competency_scores[ $comp['id'] ] = round( $score, 4 );
				$pillar_comp_scores[]             = $score;
			}

			$pillar_scores[ $pillar['id'] ] = count( $pillar_comp_scores ) > 0
				? round( array_sum( $pillar_comp_scores ) / count( $pillar_comp_scores ), 4 )
				: 0;
		}

		$overall_score = count( $competency_scores ) > 0
			? array_sum( $competency_scores ) / count( $competency_scores )
			: 0;

		return [
			'scores'        => $competency_scores,
			'pillar_scores' => $pillar_scores,
			'overall_score' => round( $overall_score, 2 ),
			'overall_level' => self::level_label( $overall_score ),
		];
	}

	public static function level_label( float $score ): string {
		if ( $score >= 3.4 ) return 'Role Model';
		if ( $score >= 2.7 ) return 'Advanced';
		if ( $score >= 2.0 ) return 'Proficient';
		return 'Developing';
	}

	public static function load_assessment(): ?array {
		$json_path  = EFU_SJT_PLUGIN_DIR . 'data/assessment.json';
		$file_data  = null;

		if ( file_exists( $json_path ) ) {
			$file_data = json_decode( file_get_contents( $json_path ), true );
		}

		$stored      = get_option( 'efu_sjt_assessment_data' );
		$stored_data = $stored ? json_decode( $stored, true ) : null;

		if ( is_array( $stored_data ) && isset( $stored_data['pillars'] ) ) {
			// If the JSON file has a higher meta.version, update the stored option
			if ( is_array( $file_data ) && isset( $file_data['meta']['version'] ) ) {
				$stored_version = $stored_data['meta']['version'] ?? '0';
				if ( version_compare( $file_data['meta']['version'], $stored_version, '>' ) ) {
					update_option( 'efu_sjt_assessment_data', wp_json_encode( $file_data ), false );
					return $file_data;
				}
			}
			return $stored_data;
		}

		if ( is_array( $file_data ) && isset( $file_data['pillars'] ) ) {
			update_option( 'efu_sjt_assessment_data', wp_json_encode( $file_data ), false );
			return $file_data;
		}

		return null;
	}
}
