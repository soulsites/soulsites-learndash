<?php
namespace SoulSites\Query;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Course Tag Filter Query
 *
 * Erweitert Elementor Loop-Widgets um einen Taxonomie-Filter für LearnDash-Kurse.
 * Unterstützt ld_course_tag sowie beliebige andere Taxonomien.
 * Query ID am Loop-Widget auf "course_tag_filter" setzen.
 */
class Course_Tag_Filter_Query {

	/**
	 * Zwischengespeicherte Widget-Settings.
	 *
	 * @var array|null
	 */
	private static $pending_settings = null;

	public function __construct() {
		add_action( 'elementor/element/before_render', [ $this, 'capture_settings' ], 1, 1 );
		add_action( 'elementor/query/course_tag_filter', [ $this, 'apply_filter' ], 10, 2 );
	}

	/**
	 * Greift Settings ab, bevor der Query-Hook feuert.
	 *
	 * @param \Elementor\Element_Base $element
	 */
	public function capture_settings( $element ) {
		if ( ! $element || ! is_object( $element ) || ! method_exists( $element, 'get_name' ) ) {
			return;
		}

		if ( ! in_array( $element->get_name(), [ 'loop-grid', 'loop-carousel' ], true ) ) {
			return;
		}

		if ( ! method_exists( $element, 'get_settings_for_display' ) ) {
			return;
		}

		try {
			$settings = $element->get_settings_for_display();
		} catch ( \Exception $e ) {
			return;
		}

		$query_id = isset( $settings['query_id'] ) ? $settings['query_id'] : '';
		if ( $query_id !== 'course_tag_filter' ) {
			return;
		}

		self::$pending_settings = $settings;
	}

	/**
	 * Wendet den Taxonomie-Filter auf die WP_Query an.
	 *
	 * @param object      $query
	 * @param object|null $widget
	 */
	public function apply_filter( $query, $widget = null ) {
		if ( ! defined( 'LEARNDASH_VERSION' ) ) {
			return;
		}

		if ( ! $query || ! is_object( $query ) ) {
			return;
		}

		try {
			if ( self::$pending_settings !== null ) {
				$settings             = self::$pending_settings;
				self::$pending_settings = null;
			} elseif ( $widget && is_object( $widget ) && method_exists( $widget, 'get_settings_for_display' ) ) {
				$settings = $widget->get_settings_for_display();
			} elseif ( method_exists( $query, 'get_query_vars' ) ) {
				$settings = $query->get_query_vars();
			} else {
				return;
			}

			$taxonomy = isset( $settings['tag_filter_taxonomy'] ) && $settings['tag_filter_taxonomy'] !== ''
				? sanitize_key( $settings['tag_filter_taxonomy'] )
				: 'ld_course_tag';

			$terms_raw = isset( $settings['tag_filter_terms'] ) ? trim( $settings['tag_filter_terms'] ) : '';
			$operator  = isset( $settings['tag_filter_operator'] ) ? $settings['tag_filter_operator'] : 'IN';

			if ( $terms_raw === '' ) {
				return;
			}

			if ( ! taxonomy_exists( $taxonomy ) ) {
				return;
			}

			// Komma-getrennte Slugs oder IDs aufteilen
			$raw_list  = array_filter( array_map( 'trim', explode( ',', $terms_raw ) ) );
			$term_ids  = [];

			foreach ( $raw_list as $slug_or_id ) {
				if ( is_numeric( $slug_or_id ) ) {
					$term_ids[] = (int) $slug_or_id;
				} else {
					$term = get_term_by( 'slug', $slug_or_id, $taxonomy );
					if ( $term && ! is_wp_error( $term ) ) {
						$term_ids[] = (int) $term->term_id;
					}
				}
			}

			if ( empty( $term_ids ) ) {
				$query->set( 'post__in', [ 0 ] );
				return;
			}

			$existing = $query->get( 'tax_query' );
			if ( ! is_array( $existing ) ) {
				$existing = [];
			}

			$existing[] = [
				'taxonomy' => $taxonomy,
				'field'    => 'term_id',
				'terms'    => $term_ids,
				'operator' => $operator,
			];

			$query->set( 'tax_query', $existing );

		} catch ( \Exception $e ) {
			return;
		}
	}

	/**
	 * Registriert die Elementor Controls im Loop-Widget.
	 *
	 * @param \Elementor\Widget_Base $widget
	 */
	public static function register_controls( $widget ) {
		if ( ! $widget || ! is_object( $widget ) ) {
			return;
		}

		if ( ! class_exists( '\Elementor\Controls_Manager' ) ) {
			return;
		}

		try {
			$widget->add_control(
				'tag_filter_heading',
				[
					'label'     => esc_html__( 'Kurs-Tag Filter', 'soulsites-learndash' ),
					'type'      => \Elementor\Controls_Manager::HEADING,
					'separator' => 'before',
				]
			);

			$widget->add_control(
				'tag_filter_taxonomy',
				[
					'label'       => esc_html__( 'Taxonomie', 'soulsites-learndash' ),
					'type'        => \Elementor\Controls_Manager::SELECT,
					'default'     => 'ld_course_tag',
					'options'     => self::get_course_taxonomies(),
					'description' => esc_html__( 'Wähle die Taxonomie, nach der gefiltert werden soll. Setze dazu die Query ID auf "course_tag_filter".', 'soulsites-learndash' ),
				]
			);

			$widget->add_control(
				'tag_filter_terms',
				[
					'label'       => esc_html__( 'Tags / Terme (Slugs oder IDs)', 'soulsites-learndash' ),
					'type'        => \Elementor\Controls_Manager::TEXT,
					'default'     => '',
					'placeholder' => esc_html__( 'z.B. online-kurs, anfaenger oder 12,45', 'soulsites-learndash' ),
					'description' => esc_html__( 'Kommagetrennte Term-Slugs oder Term-IDs.', 'soulsites-learndash' ),
				]
			);

			$widget->add_control(
				'tag_filter_operator',
				[
					'label'   => esc_html__( 'Operator', 'soulsites-learndash' ),
					'type'    => \Elementor\Controls_Manager::SELECT,
					'default' => 'IN',
					'options' => [
						'IN'     => esc_html__( 'IN – Kurs hat mindestens einen der Tags', 'soulsites-learndash' ),
						'NOT IN' => esc_html__( 'NOT IN – Kurs hat keinen der Tags', 'soulsites-learndash' ),
						'AND'    => esc_html__( 'AND – Kurs hat alle Tags', 'soulsites-learndash' ),
					],
					'condition' => [ 'tag_filter_terms!' => '' ],
				]
			);

		} catch ( \Exception $e ) {
			return;
		}
	}

	/**
	 * Gibt alle Taxonomien zurück, die für sfwd-courses registriert sind.
	 *
	 * @return array  slug => Label
	 */
	private static function get_course_taxonomies() {
		$options    = [];
		$taxonomies = get_object_taxonomies( 'sfwd-courses', 'objects' );

		foreach ( $taxonomies as $slug => $tax ) {
			$options[ $slug ] = $tax->label ? $tax->label : $slug;
		}

		// Sicherstellen, dass ld_course_tag immer als erste Option erscheint
		if ( isset( $options['ld_course_tag'] ) ) {
			$tag_label = $options['ld_course_tag'];
			unset( $options['ld_course_tag'] );
			$options = [ 'ld_course_tag' => $tag_label ] + $options;
		}

		if ( empty( $options ) ) {
			$options['ld_course_tag'] = 'Course Tag';
		}

		return $options;
	}
}
