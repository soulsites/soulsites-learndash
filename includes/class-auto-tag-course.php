<?php
/**
 * Auto-Tag Course from ACF Fields
 *
 * @package SoulSites_LearnDash
 */

namespace SoulSites;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Reads configured ACF fields on sfwd-courses and syncs their values as
 * taxonomy terms. Runs both live (via AJAX on field change) and on save
 * (via acf/save_post). Previously auto-tagged terms are tracked in post
 * meta so manually added terms are never removed.
 */
class Auto_Tag_Course {

	/** Post-meta key prefix for tracking auto-managed terms per taxonomy. */
	const META_PREFIX = '_soulsites_auto_tags_';

	private static $instance = null;

	public static function get_instance() {
		if ( self::$instance === null ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'acf/save_post', [ $this, 'sync_tags_on_save' ], 20 );
		add_action( 'wp_ajax_soulsites_auto_tag_course', [ $this, 'ajax_sync_field_tags' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_scripts' ] );
	}

	// -------------------------------------------------------------------------
	// Config helpers
	// -------------------------------------------------------------------------

	/**
	 * Parse the textarea config into an array of [ field => string, taxonomy => string ].
	 * Each non-empty line: "fieldname" or "fieldname:taxonomy_slug"
	 * Lines starting with # are comments.
	 *
	 * @return array<int, array{field: string, taxonomy: string}>
	 */
	public static function get_field_config(): array {
		$raw = Settings_Page::get_option( 'auto_tag_fields', '' );
		if ( empty( $raw ) ) {
			return [];
		}

		$config = [];
		foreach ( explode( "\n", $raw ) as $line ) {
			$line = trim( $line );
			if ( $line === '' || $line[0] === '#' ) {
				continue;
			}
			$parts    = array_map( 'trim', explode( ':', $line, 2 ) );
			$field    = $parts[0];
			$taxonomy = ( isset( $parts[1] ) && $parts[1] !== '' ) ? $parts[1] : 'ld_course_tag';

			if ( $field !== '' ) {
				$config[] = [ 'field' => $field, 'taxonomy' => $taxonomy ];
			}
		}
		return $config;
	}

	// -------------------------------------------------------------------------
	// Save hook
	// -------------------------------------------------------------------------

	/**
	 * Sync tags after ACF writes all field values (priority 20).
	 *
	 * @param int|string $post_id
	 */
	public function sync_tags_on_save( $post_id ) {
		$post_id = absint( $post_id );
		if ( get_post_type( $post_id ) !== 'sfwd-courses' ) {
			return;
		}

		$field_config = self::get_field_config();
		if ( empty( $field_config ) ) {
			return;
		}

		// Group by taxonomy so we can handle multiple fields → same taxonomy correctly.
		$by_taxonomy = [];
		foreach ( $field_config as $cfg ) {
			$value      = get_field( $cfg['field'], $post_id );
			$term_names = $this->normalize_value( $value );
			$tax        = $cfg['taxonomy'];

			if ( ! isset( $by_taxonomy[ $tax ] ) ) {
				$by_taxonomy[ $tax ] = [];
			}
			$by_taxonomy[ $tax ] = array_merge( $by_taxonomy[ $tax ], $term_names );
		}

		foreach ( $by_taxonomy as $taxonomy => $term_names ) {
			$this->set_auto_terms( $post_id, array_unique( array_filter( $term_names ) ), $taxonomy );
		}
	}

	// -------------------------------------------------------------------------
	// AJAX (live update)
	// -------------------------------------------------------------------------

	/**
	 * AJAX endpoint called by admin-auto-tag.js immediately after a field changes.
	 * Receives raw field values from JS (before the post is saved).
	 */
	public function ajax_sync_field_tags() {
		check_ajax_referer( 'soulsites_auto_tag_nonce', 'nonce' );

		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( 'Unauthorized', 403 );
		}

		$post_id  = isset( $_POST['post_id'] )  ? absint( $_POST['post_id'] )  : 0;
		$field    = isset( $_POST['field'] )    ? sanitize_key( $_POST['field'] )    : '';
		$taxonomy = isset( $_POST['taxonomy'] ) ? sanitize_key( $_POST['taxonomy'] ) : 'ld_course_tag';

		if ( ! $post_id || ! $field ) {
			wp_send_json_error( 'Missing parameters' );
		}

		if ( get_post_type( $post_id ) !== 'sfwd-courses' ) {
			wp_send_json_error( 'Not a course' );
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			wp_send_json_error( 'Unauthorized', 403 );
		}

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- normalized below
		$raw_value = isset( $_POST['value'] ) ? wp_unslash( $_POST['value'] ) : null;

		$term_names = $this->normalize_value( $raw_value );
		$term_names = array_unique( array_filter( $term_names ) );

		$this->set_auto_terms( $post_id, $term_names, $taxonomy );

		wp_send_json_success( [
			'terms'    => array_values( $term_names ),
			'taxonomy' => $taxonomy,
		] );
	}

	// -------------------------------------------------------------------------
	// Term management
	// -------------------------------------------------------------------------

	/**
	 * Create missing terms, then replace the auto-managed set for $taxonomy on
	 * $post_id without touching manually added terms.
	 *
	 * @param int      $post_id
	 * @param string[] $term_names Human-readable names (will be created if absent).
	 * @param string   $taxonomy   Taxonomy slug.
	 */
	private function set_auto_terms( int $post_id, array $term_names, string $taxonomy ): void {
		if ( ! taxonomy_exists( $taxonomy ) ) {
			return;
		}

		// Ensure all desired terms exist and collect IDs.
		$new_ids = [];
		foreach ( $term_names as $name ) {
			$name = trim( $name );
			if ( $name === '' ) {
				continue;
			}
			$term = term_exists( $name, $taxonomy );
			if ( ! $term ) {
				$term = wp_insert_term( $name, $taxonomy );
			}
			if ( ! is_wp_error( $term ) ) {
				$new_ids[] = (int) ( is_array( $term ) ? $term['term_id'] : $term );
			}
		}

		// Determine which term IDs were previously auto-managed.
		$meta_key    = self::META_PREFIX . $taxonomy;
		$prev_ids    = get_post_meta( $post_id, $meta_key, true );
		$prev_ids    = is_array( $prev_ids ) ? array_map( 'intval', $prev_ids ) : [];

		// Current terms on the post.
		$current_ids = wp_get_object_terms( $post_id, $taxonomy, [ 'fields' => 'ids' ] );
		if ( is_wp_error( $current_ids ) ) {
			$current_ids = [];
		}
		$current_ids = array_map( 'intval', $current_ids );

		// Keep terms that were NOT previously auto-managed (= manually added).
		$manual_ids = array_diff( $current_ids, $prev_ids );

		// Final set = manual terms + newly auto-tagged terms.
		$final_ids = array_values( array_unique( array_merge( $manual_ids, $new_ids ) ) );

		wp_set_object_terms( $post_id, $final_ids, $taxonomy, false );

		// Persist the new auto-managed list so the next save can subtract them correctly.
		update_post_meta( $post_id, $meta_key, $new_ids );
	}

	// -------------------------------------------------------------------------
	// Value normalization
	// -------------------------------------------------------------------------

	/**
	 * Normalise any ACF value (or raw JS-submitted value) into an array of
	 * plain string names suitable for use as taxonomy term names.
	 *
	 * Handles: scalar, comma-separated string, array of scalars, array of
	 * ACF choice arrays (value/label), WP_Term objects, WP_Post objects.
	 *
	 * @param mixed $value
	 * @return string[]
	 */
	private function normalize_value( $value ): array {
		if ( $value === null || $value === '' || $value === false ) {
			return [];
		}

		if ( is_string( $value ) ) {
			return array_filter( array_map( 'trim', explode( ',', $value ) ) );
		}

		if ( is_int( $value ) || is_float( $value ) ) {
			return [ (string) $value ];
		}

		if ( is_array( $value ) ) {
			$names = [];
			foreach ( $value as $item ) {
				if ( is_string( $item ) || is_numeric( $item ) ) {
					$v = trim( (string) $item );
					if ( $v !== '' ) {
						$names[] = $v;
					}
				} elseif ( is_array( $item ) ) {
					// ACF select/checkbox choice: prefer 'label', fall back to 'value'.
					$v = trim( (string) ( $item['label'] ?? $item['value'] ?? '' ) );
					if ( $v !== '' ) {
						$names[] = $v;
					}
				} elseif ( $item instanceof \WP_Term ) {
					$names[] = $item->name;
				} elseif ( $item instanceof \WP_Post ) {
					$names[] = $item->post_title;
				}
			}
			return array_filter( $names );
		}

		return [];
	}

	// -------------------------------------------------------------------------
	// Script enqueueing
	// -------------------------------------------------------------------------

	/**
	 * Load the live-update script only on sfwd-courses edit screens when the
	 * feature is enabled and at least one field is configured.
	 *
	 * @param string $hook Current admin page hook.
	 */
	public function enqueue_admin_scripts( $hook ) {
		if ( ! in_array( $hook, [ 'post.php', 'post-new.php' ], true ) ) {
			return;
		}

		$screen = get_current_screen();
		if ( ! $screen || $screen->post_type !== 'sfwd-courses' ) {
			return;
		}

		if ( ! Settings_Page::get_option( 'auto_tag_enabled' ) ) {
			return;
		}

		$field_config = self::get_field_config();
		if ( empty( $field_config ) ) {
			return;
		}

		wp_enqueue_script(
			'soulsites-auto-tag-course',
			SOULSITES_LEARNDASH_URL . 'assets/js/admin-auto-tag.js',
			[ 'acf-input', 'jquery' ],
			SOULSITES_LEARNDASH_VERSION,
			true
		);

		wp_localize_script(
			'soulsites-auto-tag-course',
			'SoulsitesAutoTag',
			[
				'ajaxUrl'     => admin_url( 'admin-ajax.php' ),
				'nonce'       => wp_create_nonce( 'soulsites_auto_tag_nonce' ),
				'postId'      => get_the_ID(),
				'fieldConfig' => $field_config,
				'i18n'        => [
					'tagged' => __( 'Tags gesetzt:', 'soulsites-learndash' ),
				],
			]
		);
	}
}
