<?php
namespace SoulSites\Query;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * ACF Course Filter Query
 *
 * Fügt Elementor Loop-Widgets einen Filter hinzu, mit dem LearnDash Kurse
 * nach beliebigen ACF-Feldern gefiltert werden können.
 * Voraussetzung: Query ID am Loop-Widget auf "acf_course_filter" setzen.
 */
class ACF_Course_Filter_Query {

	/**
	 * Feldtypen, die für eine einfache Meta-Abfrage nicht geeignet sind.
	 *
	 * @var string[]
	 */
	private static $skip_types = [
		'repeater',
		'flexible_content',
		'group',
		'clone',
		'gallery',
		'tab',
		'accordion',
		'message',
	];

	/**
	 * Constructor
	 */
	public function __construct() {
		add_action( 'elementor/query/acf_course_filter', [ $this, 'apply_filter' ], 10, 1 );
	}

	/**
	 * Wendet den ACF-Feldfilter auf die WP_Query an.
	 *
	 * @param object $query Elementor Query-Objekt.
	 */
	public function apply_filter( $query ) {
		if ( ! defined( 'LEARNDASH_VERSION' ) ) {
			return;
		}

		if ( ! $query || ! is_object( $query ) || ! method_exists( $query, 'get_query_vars' ) ) {
			return;
		}

		try {
			$settings   = $query->get_query_vars();
			$field_name = isset( $settings['acf_filter_field'] ) ? $settings['acf_filter_field'] : '';
			$compare    = isset( $settings['acf_filter_compare'] ) ? $settings['acf_filter_compare'] : '=';
			$value      = isset( $settings['acf_filter_value'] ) ? $settings['acf_filter_value'] : '';

			if ( empty( $field_name ) ) {
				return;
			}

			$needs_value = ! in_array( $compare, [ 'EXISTS', 'NOT EXISTS' ], true );
			if ( $needs_value && $value === '' ) {
				return;
			}

			$meta_entry = [
				'key'     => sanitize_key( $field_name ),
				'compare' => $compare,
			];

			if ( $needs_value ) {
				$meta_entry['value'] = $value;
			}

			$query->set( 'meta_query', [ $meta_entry ] );

		} catch ( \Exception $e ) {
			return;
		}
	}

	/**
	 * Registriert die Query-Controls im Elementor-Editor.
	 *
	 * @param \Elementor\Widget_Base $widget Das Loop-Widget.
	 */
	public static function register_controls( $widget ) {
		if ( ! $widget || ! is_object( $widget ) ) {
			return;
		}

		if ( ! class_exists( '\Elementor\Controls_Manager' ) ) {
			return;
		}

		try {
			$field_options = self::get_acf_course_fields();

			$widget->add_control(
				'acf_filter_heading',
				[
					'label'     => esc_html__( 'ACF Kursfelder Filter', 'soulsites-learndash' ),
					'type'      => \Elementor\Controls_Manager::HEADING,
					'separator' => 'before',
				]
			);

			$placeholder = empty( $field_options )
				? esc_html__( '— Kein ACF-Feld gefunden —', 'soulsites-learndash' )
				: esc_html__( '— Kein Filter —', 'soulsites-learndash' );

			$widget->add_control(
				'acf_filter_field',
				[
					'label'       => esc_html__( 'ACF Feld', 'soulsites-learndash' ),
					'type'        => \Elementor\Controls_Manager::SELECT,
					'default'     => '',
					'options'     => [ '' => $placeholder ] + $field_options,
					'description' => esc_html__( 'Wähle ein ACF-Feld, nach dem die Kurse gefiltert werden sollen. Setze dazu die Query ID des Widgets auf "acf_course_filter".', 'soulsites-learndash' ),
				]
			);

			$widget->add_control(
				'acf_filter_compare',
				[
					'label'     => esc_html__( 'Vergleich', 'soulsites-learndash' ),
					'type'      => \Elementor\Controls_Manager::SELECT,
					'default'   => '=',
					'options'   => [
						'='          => esc_html__( '= (Gleich)', 'soulsites-learndash' ),
						'!='         => esc_html__( '!= (Ungleich)', 'soulsites-learndash' ),
						'LIKE'       => esc_html__( 'LIKE (Enthält)', 'soulsites-learndash' ),
						'NOT LIKE'   => esc_html__( 'NOT LIKE (Enthält nicht)', 'soulsites-learndash' ),
						'>'          => esc_html__( '> (Größer als)', 'soulsites-learndash' ),
						'>='         => esc_html__('>= (Größer oder gleich)', 'soulsites-learndash' ),
						'<'          => esc_html__( '< (Kleiner als)', 'soulsites-learndash' ),
						'<='         => esc_html__( '<= (Kleiner oder gleich)', 'soulsites-learndash' ),
						'EXISTS'     => esc_html__( 'EXISTS (Feld vorhanden)', 'soulsites-learndash' ),
						'NOT EXISTS' => esc_html__( 'NOT EXISTS (Feld nicht vorhanden)', 'soulsites-learndash' ),
					],
					'condition' => [ 'acf_filter_field!' => '' ],
				]
			);

			$widget->add_control(
				'acf_filter_value',
				[
					'label'       => esc_html__( 'Feldwert', 'soulsites-learndash' ),
					'type'        => \Elementor\Controls_Manager::TEXT,
					'default'     => '',
					'placeholder' => esc_html__( 'z.B. ja, 1, aktiv …', 'soulsites-learndash' ),
					'condition'   => [
						'acf_filter_field!'   => '',
						'acf_filter_compare!' => [ 'EXISTS', 'NOT EXISTS' ],
					],
				]
			);

		} catch ( \Exception $e ) {
			return;
		}
	}

	/**
	 * Gibt alle für Meta-Abfragen geeigneten ACF-Felder der sfwd-courses zurück.
	 *
	 * @return array  field_name => "Gruppe: Feldbezeichnung"
	 */
	private static function get_acf_course_fields() {
		if ( ! function_exists( 'acf_get_field_groups' ) || ! function_exists( 'acf_get_fields' ) ) {
			return [];
		}

		try {
			$groups  = acf_get_field_groups( [ 'post_type' => 'sfwd-courses' ] );
			$options = [];

			foreach ( $groups as $group ) {
				$fields = acf_get_fields( $group['key'] );
				if ( ! $fields ) {
					continue;
				}

				foreach ( $fields as $field ) {
					if ( in_array( $field['type'], self::$skip_types, true ) ) {
						continue;
					}
					$options[ $field['name'] ] = $group['title'] . ': ' . $field['label'];
				}
			}

			return $options;

		} catch ( \Exception $e ) {
			return [];
		}
	}
}
