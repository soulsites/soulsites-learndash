<?php
/**
 * Element Conditions
 * Fügt jedem Elementor-Element einen "Erweiterte Bedingungen"-Tab hinzu,
 * über den einfache Sichtbarkeitsregeln konfiguriert werden können.
 *
 * @package SoulSites_LearnDash
 */

namespace SoulSites\Element_Conditions;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Element_Conditions
 *
 * Registriert einen neuen Abschnitt „Erweiterte Bedingungen" im Advanced-Tab
 * aller Elementor-Elemente (Widgets, Sections, Container, Columns) und
 * blendet Elemente auf dem Frontend aus, wenn die gewählte Bedingung nicht erfüllt ist.
 *
 * Implementierungsdetail Sichtbarkeit:
 * Die Bedingungsprüfung erfolgt serverseitig. Ist eine Bedingung nicht erfüllt,
 * wird dem Wrapper-Element `style="display:none !important;"` hinzugefügt.
 * Im Elementor-Editor bleiben alle Elemente unabhängig von der Bedingung sichtbar,
 * damit das Bearbeiten problemlos möglich ist.
 */
class Element_Conditions {

	/**
	 * Plugin instance
	 *
	 * @var Element_Conditions
	 */
	private static $instance = null;

	/**
	 * Get plugin instance
	 *
	 * @return Element_Conditions
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor – registers all hooks.
	 */
	private function __construct() {
		// Controls zu allen Elementtypen hinzufügen
		add_action( 'elementor/element/after_section_end', [ $this, 'add_controls' ], 10, 3 );

		// Frontend-Sichtbarkeit steuern (Widget, Section, Column, Container)
		foreach ( [ 'widget', 'section', 'column', 'container' ] as $type ) {
			add_action( "elementor/frontend/{$type}/before_render", [ $this, 'before_render' ] );
		}
	}

	/**
	 * Adds the "Erweiterte Bedingungen" section to the Advanced tab of every element.
	 *
	 * The hook `elementor/element/after_section_end` fires once per section per element
	 * type during control registration. We attach our section right after the responsive
	 * visibility section (`_section_render_visible`) which is present in all standard
	 * Elementor element types (widgets via the Common element, sections, containers).
	 *
	 * @param \Elementor\Element_Base $element   Current element instance.
	 * @param string                  $section_id ID of the section that just ended.
	 * @param array                   $args       Additional arguments (unused).
	 */
	public function add_controls( $element, $section_id, $args ) {
		// Wir hängen uns nach der letzten Responsive-Sektion ein.
		if ( '_section_render_visible' !== $section_id ) {
			return;
		}

		$element->start_controls_section(
			'ss_element_conditions_section',
			[
				'label' => esc_html__( 'Erweiterte Bedingungen', 'soulsites-learndash' ),
				'tab'   => \Elementor\Controls_Manager::TAB_ADVANCED,
			]
		);

		$element->add_control(
			'ss_condition_type',
			[
				'label'       => esc_html__( 'Sichtbarkeit', 'soulsites-learndash' ),
				'description' => esc_html__( 'Element nur anzeigen, wenn diese Bedingung erfüllt ist.', 'soulsites-learndash' ),
				'type'        => \Elementor\Controls_Manager::SELECT,
				'default'     => '',
				'options'     => [
					''                    => esc_html__( 'Immer anzeigen', 'soulsites-learndash' ),
					'logged_in'           => esc_html__( 'Logged In', 'soulsites-learndash' ),
					'logged_out'          => esc_html__( 'Logged Out', 'soulsites-learndash' ),
					'course_enrolled'     => esc_html__( 'Course enrolled', 'soulsites-learndash' ),
					'course_not_enrolled' => esc_html__( 'Course not enrolled', 'soulsites-learndash' ),
				],
			]
		);

		$element->end_controls_section();
	}

	/**
	 * Before an element renders on the frontend: hide it if the condition is not met.
	 *
	 * In edit mode every element is always rendered so the designer can work with it.
	 * On the frontend a CSS `display:none !important` is added to the wrapper when the
	 * selected condition evaluates to false.
	 *
	 * @param \Elementor\Element_Base $element Current element instance.
	 */
	public function before_render( $element ) {
		// Im Elementor-Editor immer sichtbar lassen.
		if ( \Elementor\Plugin::$instance->editor->is_edit_mode() ) {
			return;
		}

		$condition = $element->get_settings_for_display( 'ss_condition_type' );

		if ( empty( $condition ) ) {
			return;
		}

		if ( ! $this->check_condition( $condition ) ) {
			$element->add_render_attribute( '_wrapper', 'style', 'display:none !important;' );
		}
	}

	// -------------------------------------------------------------------------
	// Private helpers
	// -------------------------------------------------------------------------

	/**
	 * Evaluate a condition string and return whether it is met.
	 *
	 * @param string $condition One of the option values registered in add_controls().
	 * @return bool
	 */
	private function check_condition( $condition ) {
		switch ( $condition ) {
			case 'logged_in':
				return is_user_logged_in();

			case 'logged_out':
				return ! is_user_logged_in();

			case 'course_enrolled':
				return $this->check_course_access( true );

			case 'course_not_enrolled':
				return $this->check_course_access( false );

			default:
				return true;
		}
	}

	/**
	 * Check whether the current user is enrolled in the contextual course.
	 *
	 * The course context is resolved in the following order:
	 *  1. Current post is a course (`sfwd-courses`).
	 *  2. Current post is a lesson/topic — resolve via `learndash_get_course_id()`.
	 *  3. No course context found → return false (condition not met).
	 *
	 * @param bool $should_be_enrolled TRUE to check "is enrolled", FALSE to check "is not enrolled".
	 * @return bool
	 */
	private function check_course_access( $should_be_enrolled ) {
		if ( ! function_exists( 'sfwd_lms_has_access' ) ) {
			return false;
		}

		$post_id = get_the_ID();
		if ( ! $post_id ) {
			return false;
		}

		// Kurs-ID aus dem aktuellen Kontext ermitteln.
		$course_id = null;

		if ( get_post_type( $post_id ) === 'sfwd-courses' ) {
			$course_id = $post_id;
		} elseif ( function_exists( 'learndash_get_course_id' ) ) {
			$course_id = learndash_get_course_id( $post_id );
		}

		if ( ! $course_id ) {
			return false;
		}

		// Nicht eingeloggte Nutzer sind nie eingeschrieben.
		if ( ! is_user_logged_in() ) {
			return ! $should_be_enrolled;
		}

		$is_enrolled = (bool) sfwd_lms_has_access( $course_id, get_current_user_id() );

		return $should_be_enrolled ? $is_enrolled : ! $is_enrolled;
	}
}
