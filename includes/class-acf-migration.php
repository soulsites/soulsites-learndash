<?php
/**
 * ACF Date Migration
 *
 * @package SoulSites_LearnDash
 */

namespace SoulSites;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles AJAX-driven migration of ACF text fields to ACF date fields.
 */
class ACF_Migration {

	/** @var ACF_Migration */
	private static $instance = null;

	/** @return ACF_Migration */
	public static function get_instance() {
		if ( self::$instance === null ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'wp_ajax_soulsites_acf_migrate_dates', [ $this, 'handle_migration' ] );
	}

	// -------------------------------------------------------------------------
	// AJAX handler
	// -------------------------------------------------------------------------

	/**
	 * Run the migration and return JSON results.
	 */
	public function handle_migration() {
		check_ajax_referer( 'soulsites_acf_migrate_dates', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => __( 'Keine Berechtigung.', 'soulsites-learndash' ) ], 403 );
		}

		$source_field = isset( $_POST['source_field'] ) ? sanitize_text_field( wp_unslash( $_POST['source_field'] ) ) : '';
		$target_field = isset( $_POST['target_field'] ) ? sanitize_text_field( wp_unslash( $_POST['target_field'] ) ) : '';
		$post_type    = isset( $_POST['post_type'] )    ? sanitize_text_field( wp_unslash( $_POST['post_type'] ) )    : 'sfwd-courses';
		$dry_run      = ! empty( $_POST['dry_run'] );

		if ( empty( $source_field ) || empty( $target_field ) ) {
			wp_send_json_error( [ 'message' => __( 'Bitte Quell- und Zielfeld auswählen.', 'soulsites-learndash' ) ] );
		}

		if ( $source_field === $target_field ) {
			wp_send_json_error( [ 'message' => __( 'Quell- und Zielfeld dürfen nicht identisch sein.', 'soulsites-learndash' ) ] );
		}

		$allowed_types = [ 'sfwd-courses', 'sfwd-lessons', 'sfwd-topic', 'sfwd-quiz', 'sfwd-certificates', 'post', 'page' ];
		if ( ! in_array( $post_type, $allowed_types, true ) ) {
			wp_send_json_error( [ 'message' => __( 'Ungültiger Post-Typ.', 'soulsites-learndash' ) ] );
		}

		$post_ids = get_posts( [
			'post_type'      => $post_type,
			'posts_per_page' => -1,
			'post_status'    => 'any',
			'fields'         => 'ids',
		] );

		$count_success  = 0;
		$count_skipped  = 0;
		$failed_details = [];

		foreach ( $post_ids as $post_id ) {
			$raw = get_post_meta( $post_id, $source_field, true );

			if ( $raw === '' || $raw === false ) {
				++$count_skipped;
				continue;
			}

			$timestamp = $this->parse_german_date( (string) $raw );

			if ( $timestamp !== false && $timestamp > 0 ) {
				if ( ! $dry_run ) {
					$acf_value = gmdate( 'Ymd', $timestamp );
					if ( function_exists( 'update_field' ) ) {
						update_field( $target_field, $acf_value, $post_id );
					} else {
						update_post_meta( $post_id, $target_field, $acf_value );
					}
				}
				++$count_success;
			} else {
				$failed_details[] = [
					'id'    => $post_id,
					'title' => get_the_title( $post_id ),
					'value' => $raw,
				];
			}
		}

		wp_send_json_success( [
			'success'        => $count_success,
			'skipped'        => $count_skipped,
			'failed'         => count( $failed_details ),
			'failed_details' => $failed_details,
			'dry_run'        => $dry_run,
		] );
	}

	// -------------------------------------------------------------------------
	// Date parser
	// -------------------------------------------------------------------------

	/**
	 * Try to parse a German (or English fallback) date string.
	 *
	 * Handles:
	 *  - dd.mm.yyyy  /  dd.mm.yy
	 *  - "30. Mai 2026", "30.Mai.2026"
	 *  - Weekday prefixes ("Montag, 30.05.2026")
	 *  - En/em dashes, extra commas
	 *  - English strtotime as last resort
	 *
	 * @param string $value Raw string from the database.
	 * @return int|false Unix timestamp, or false if unparseable.
	 */
	private function parse_german_date( $value ) {
		// --- Step 1: strip noise ---
		$weekdays = [
			'Montag', 'Dienstag', 'Mittwoch', 'Donnerstag', 'Freitag', 'Samstag', 'Sonntag',
			'Mon', 'Die', 'Mit', 'Don', 'Fre', 'Sam', 'Son',
			'Mo', 'Di', 'Mi', 'Do', 'Fr', 'Sa', 'So',
		];
		$cleaned = str_ireplace( $weekdays, '', $value );
		$cleaned = str_replace( [ '–', '—', '−', ',', ';' ], ' ', $cleaned );
		$cleaned = preg_replace( '/\s+/', ' ', $cleaned );
		$cleaned = trim( $cleaned );

		// --- Step 2: numeric dd.mm.yyyy / dd.mm.yy ---
		if ( preg_match( '/(\d{1,2})\.(\d{1,2})\.(\d{2,4})/', $cleaned, $m ) ) {
			$year = strlen( $m[3] ) === 2 ? '20' . $m[3] : $m[3];
			$ts   = mktime( 0, 0, 0, (int) $m[2], (int) $m[1], (int) $year );
			return ( $ts !== false && $ts > 0 ) ? $ts : false;
		}

		// --- Step 3: German month names ---
		$months = [
			'januar'    => 1,  'februar'  => 2,  'märz'     => 3,  'maerz'     => 3,
			'april'     => 4,  'mai'      => 5,  'juni'     => 6,  'juli'       => 7,
			'august'    => 8,  'september'=> 9,  'oktober'  => 10, 'november'  => 11,
			'dezember'  => 12,
			'jan'       => 1,  'feb'      => 2,  'mrz'      => 3,  'apr'        => 4,
			'jun'       => 6,  'jul'      => 7,  'aug'      => 8,  'sep'        => 9,
			'okt'       => 10, 'nov'      => 11, 'dez'      => 12,
		];

		$lower = mb_strtolower( $cleaned, 'UTF-8' );

		// Sort by length desc to avoid partial matches ("sep" matching inside "september")
		$names = array_keys( $months );
		usort( $names, static function ( $a, $b ) { return strlen( $b ) - strlen( $a ); } );

		foreach ( $names as $name ) {
			if ( mb_strpos( $lower, $name ) === false ) {
				continue;
			}

			$month_num = $months[ $name ];
			$escaped   = preg_quote( $name, '/' );

			// "30. Mai 2026" or "30.Mai.2026" or "30 mai 2026"
			if ( preg_match( '/(\d{1,2})\s*\.?\s*' . $escaped . '\s*\.?\s*(\d{2,4})/i', $lower, $m ) ) {
				$year = strlen( $m[2] ) === 2 ? '20' . $m[2] : $m[2];
				$ts   = mktime( 0, 0, 0, $month_num, (int) $m[1], (int) $year );
				return ( $ts !== false && $ts > 0 ) ? $ts : false;
			}

			break; // Found the month name but couldn't extract numbers → fall through
		}

		// --- Step 4: English strtotime fallback ---
		$ts = strtotime( $cleaned );
		return ( $ts !== false && $ts > 0 ) ? $ts : false;
	}

	// -------------------------------------------------------------------------
	// Static helper used by Settings_Page
	// -------------------------------------------------------------------------

	/**
	 * Return all ACF fields grouped by field group, skipping complex field types.
	 *
	 * @return array  [ 'Group Title' => [ ['name'=>, 'label'=>, 'type'=>], … ], … ]
	 */
	public static function get_all_fields() {
		if ( ! function_exists( 'acf_get_field_groups' ) || ! function_exists( 'acf_get_fields' ) ) {
			return [];
		}

		$skip_types = [ 'repeater', 'flexible_content', 'group', 'clone', 'gallery', 'accordion', 'tab', 'message' ];
		$groups     = acf_get_field_groups();
		$result     = [];

		foreach ( $groups as $group ) {
			$fields = acf_get_fields( $group['key'] );
			if ( empty( $fields ) ) {
				continue;
			}

			foreach ( $fields as $field ) {
				if ( in_array( $field['type'], $skip_types, true ) ) {
					continue;
				}
				$result[ $group['title'] ][] = [
					'name'  => $field['name'],
					'label' => $field['label'],
					'type'  => $field['type'],
				];
			}
		}

		return $result;
	}
}
