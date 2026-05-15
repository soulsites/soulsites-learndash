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
 * Reads ACF fields that have "auto_tag_course" enabled in their field settings
 * and syncs their values as taxonomy terms on sfwd-courses posts.
 *
 * Runs both live (AJAX on field change) and on save (acf/save_post).
 * Previously auto-tagged terms are tracked in post meta so manually added
 * terms are never removed.
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
		// Add custom settings to every ACF field (in the field group editor).
		add_action( 'acf/render_field_settings', [ $this, 'render_acf_field_settings' ] );

		// Sync tags after ACF saves field values.
		add_action( 'acf/save_post', [ $this, 'sync_tags_on_save' ], 20 );

		// AJAX endpoint for live update while editing a course.
		add_action( 'wp_ajax_soulsites_auto_tag_course', [ $this, 'ajax_sync_field_tags' ] );

		// Enqueue live-update script on course edit screens.
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_scripts' ] );
	}

	// -------------------------------------------------------------------------
	// ACF field settings UI
	// -------------------------------------------------------------------------

	/**
	 * Append "Auto-Tag" toggle and taxonomy selector to every ACF field's
	 * settings tab in the field group editor.
	 *
	 * ACF stores any value rendered via acf_render_field_setting() as part of
	 * the field definition – no extra save hook needed.
	 *
	 * @param array $field ACF field array.
	 */
	public function render_acf_field_settings( array $field ): void {
		acf_render_field_setting( $field, [
			'label'         => __( 'Auto-Tag für Kurse', 'soulsites-learndash' ),
			'instructions'  => __( 'Werte dieses Feldes automatisch als Tags am Kurs anlegen (live + beim Speichern).', 'soulsites-learndash' ),
			'type'          => 'true_false',
			'name'          => 'auto_tag_course',
			'ui'            => 1,
			'default_value' => 0,
		] );

		acf_render_field_setting( $field, [
			'label'        => __( 'Auto-Tag Taxonomie', 'soulsites-learndash' ),
			'instructions' => __( 'Slug der Taxonomie, in der die Tags angelegt werden. Standard: ld_course_tag', 'soulsites-learndash' ),
			'type'         => 'text',
			'name'         => 'auto_tag_course_taxonomy',
			'placeholder'  => 'ld_course_tag',
			'conditional_logic' => [
				[
					[
						'field'    => 'auto_tag_course',
						'operator' => '==',
						'value'    => '1',
					],
				],
			],
		] );
	}

	// -------------------------------------------------------------------------
	// Config: scan ACF field groups for enabled fields
	// -------------------------------------------------------------------------

	/**
	 * Return all ACF fields (on sfwd-courses) that have auto_tag_course enabled.
	 * Pass $post_id to respect per-post field-group location rules.
	 *
	 * @param  int $post_id  Course post ID (0 = scan all groups for the post type).
	 * @return array<int, array{field: string, key: string, taxonomy: string}>
	 */
	public static function get_field_config( int $post_id = 0 ): array {
		if ( ! function_exists( 'acf_get_field_groups' ) || ! function_exists( 'acf_get_fields' ) ) {
			return [];
		}

		$args = $post_id
			? [ 'post_id' => $post_id ]
			: [ 'post_type' => 'sfwd-courses' ];

		$groups = acf_get_field_groups( $args );
		$config = [];

		foreach ( $groups as $group ) {
			$fields = acf_get_fields( $group['key'] );
			if ( ! is_array( $fields ) ) {
				continue;
			}
			foreach ( $fields as $field ) {
				if ( empty( $field['auto_tag_course'] ) ) {
					continue;
				}
				$taxonomy = ( ! empty( $field['auto_tag_course_taxonomy'] ) )
					? sanitize_key( $field['auto_tag_course_taxonomy'] )
					: 'ld_course_tag';

				$config[] = [
					'field'    => $field['name'],
					'key'      => $field['key'],
					'taxonomy' => $taxonomy,
				];
			}
		}

		return $config;
	}

	// -------------------------------------------------------------------------
	// Save hook
	// -------------------------------------------------------------------------

	/**
	 * Sync tags after ACF writes all field values (priority 20).
	 * Iterates the submitted ACF field keys so only visible fields are processed.
	 *
	 * @param int|string $post_id
	 */
	public function sync_tags_on_save( $post_id ) {
		if ( ! Settings_Page::get_option( 'auto_tag_enabled' ) ) {
			return;
		}

		$post_id = absint( $post_id );
		if ( get_post_type( $post_id ) !== 'sfwd-courses' ) {
			return;
		}

		// Scan field groups registered for this post to find auto-tag-enabled fields.
		// Using get_field_config() is more reliable than iterating $_POST['acf'] and
		// calling acf_get_field(), which does not always expose custom field settings.
		$field_config = self::get_field_config( $post_id );
		if ( empty( $field_config ) ) {
			return;
		}

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
	 */
	public function ajax_sync_field_tags() {
		check_ajax_referer( 'soulsites_auto_tag_nonce', 'nonce' );

		if ( ! Settings_Page::get_option( 'auto_tag_enabled' ) ) {
			wp_send_json_error( 'Feature disabled' );
		}

		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( 'Unauthorized', 403 );
		}

		$post_id   = isset( $_POST['post_id'] )  ? absint( $_POST['post_id'] )           : 0;
		$field_key = isset( $_POST['field_key'] ) ? sanitize_text_field( wp_unslash( $_POST['field_key'] ) ) : '';
		$taxonomy  = isset( $_POST['taxonomy'] )  ? sanitize_key( $_POST['taxonomy'] )    : 'ld_course_tag';

		if ( ! $post_id || ! $field_key ) {
			wp_send_json_error( 'Missing parameters' );
		}

		if ( get_post_type( $post_id ) !== 'sfwd-courses' ) {
			wp_send_json_error( 'Not a course' );
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			wp_send_json_error( 'Unauthorized', 403 );
		}

		// Verify the field actually has auto_tag_course enabled.
		$field = acf_get_field( $field_key );
		if ( ! $field || empty( $field['auto_tag_course'] ) ) {
			wp_send_json_error( 'Field not configured for auto-tagging' );
		}

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- normalized below
		$raw_value  = isset( $_POST['value'] ) ? wp_unslash( $_POST['value'] ) : null;
		$term_names = array_unique( array_filter( $this->normalize_value( $raw_value ) ) );

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
	 * @param string[] $term_names Human-readable names (created if absent).
	 * @param string   $taxonomy   Taxonomy slug.
	 */
	private function set_auto_terms( int $post_id, array $term_names, string $taxonomy ): void {
		if ( ! taxonomy_exists( $taxonomy ) ) {
			return;
		}

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

		$meta_key    = self::META_PREFIX . $taxonomy;
		$prev_ids    = get_post_meta( $post_id, $meta_key, true );
		$prev_ids    = is_array( $prev_ids ) ? array_map( 'intval', $prev_ids ) : [];

		$current_ids = wp_get_object_terms( $post_id, $taxonomy, [ 'fields' => 'ids' ] );
		$current_ids = is_wp_error( $current_ids ) ? [] : array_map( 'intval', $current_ids );

		// Preserve manually added terms (those not in the previous auto-managed set).
		$manual_ids = array_diff( $current_ids, $prev_ids );
		$final_ids  = array_values( array_unique( array_merge( $manual_ids, $new_ids ) ) );

		wp_set_object_terms( $post_id, $final_ids, $taxonomy, false );
		update_post_meta( $post_id, $meta_key, $new_ids );
	}

	// -------------------------------------------------------------------------
	// Value normalization
	// -------------------------------------------------------------------------

	/**
	 * Normalize any ACF value or raw JS-submitted value into an array of
	 * plain strings suitable as taxonomy term names.
	 *
	 * @param  mixed $value
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
					// ACF select/checkbox choice: prefer label, fall back to value.
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
	 * Load the live-update script on course edit screens when at least one
	 * field in the current post's field groups has auto_tag_course enabled.
	 *
	 * @param string $hook Current admin page hook.
	 */
	public function enqueue_admin_scripts( string $hook ): void {
		if ( ! in_array( $hook, [ 'post.php', 'post-new.php' ], true ) ) {
			return;
		}

		$screen = get_current_screen();
		if ( ! $screen || $screen->post_type !== 'sfwd-courses' ) {
			return;
		}

		$post_id      = absint( get_the_ID() );
		$field_config = self::get_field_config( $post_id );

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
				'postId'      => $post_id,
				'fieldConfig' => $field_config,
				'i18n'        => [
					'tagged' => __( 'Tags gesetzt:', 'soulsites-learndash' ),
				],
			]
		);
	}
}
