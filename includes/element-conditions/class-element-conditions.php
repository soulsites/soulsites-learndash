<?php
/**
 * Element Conditions
 * Fügt jedem Elementor-Element einen "Erweiterte Bedingungen"-Abschnitt hinzu,
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
 */
class Element_Conditions {

	/**
	 * Plugin instance
	 *
	 * @var Element_Conditions
	 */
	private static $instance = null;

	/**
	 * Tracks element names for which our section was already added.
	 * Prevents duplicate sections when multiple trigger-section IDs exist in one element.
	 *
	 * @var array<string, bool>
	 */
	private $processed_elements = [];

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
		// Controls zu allen Elementtypen hinzufügen.
		// Wir nutzen den generischen Hook und prüfen intern, nach welchem Abschnitt
		// wir unsere Sektion einfügen sollen – robust gegenüber verschiedenen
		// Elementor-/Elementor-Pro-Versionen.
		add_action( 'elementor/element/after_section_end', [ $this, 'maybe_add_controls' ], 10, 3 );

		// Frontend-Sichtbarkeit steuern.
		foreach ( [ 'widget', 'section', 'column', 'container' ] as $type ) {
			add_action( "elementor/frontend/{$type}/before_render", [ $this, 'before_render' ] );
		}
	}

	/**
	 * Adds the "Erweiterte Bedingungen" section after the last Advanced-tab section.
	 *
	 * The hook fires once per ended section for every element type. We accept multiple
	 * possible "last section" IDs to be compatible across Elementor / Elementor Pro
	 * versions. A per-element-name flag prevents duplicate sections.
	 *
	 * Known last-section IDs (in descending priority):
	 *   _section_custom_css_pro  – Elementor Pro: Custom CSS (most versions)
	 *   _section_custom_css      – Elementor Pro: Custom CSS (alternate ID)
	 *   _section_render_visible  – some older Elementor versions
	 *   _section_responsive      – Elementor free: Responsive (last free section)
	 *   section_effects          – Motion Effects (fallback if nothing else matches)
	 *
	 * @param \Elementor\Element_Base $element   Element instance.
	 * @param string                  $section_id Section that just ended.
	 * @param array                   $args       Additional arguments (unused).
	 */
	public function maybe_add_controls( $element, $section_id, $args ) {
		// Kandidaten für "letzter Abschnitt im Advanced-Tab", absteigend priorisiert.
		$trigger_sections = [
			'_section_custom_css_pro',
			'_section_custom_css',
			'_section_render_visible',
			'_section_responsive',
			'section_effects',
		];

		if ( ! in_array( $section_id, $trigger_sections, true ) ) {
			return;
		}

		// Nur einmal pro Elementtyp (Name) ausführen.
		$element_name = $element->get_name();
		if ( isset( $this->processed_elements[ $element_name ] ) ) {
			return;
		}
		$this->processed_elements[ $element_name ] = true;

		$this->add_controls( $element );
	}

	/**
	 * Registers the "Erweiterte Bedingungen" section on the given element.
	 *
	 * @param \Elementor\Element_Base $element Element instance.
	 */
	private function add_controls( $element ) {
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
					'mp_has_membership'   => esc_html__( 'MemberPress: Aktive Mitgliedschaft', 'soulsites-learndash' ),
					'mp_no_membership'    => esc_html__( 'MemberPress: Keine Mitgliedschaft', 'soulsites-learndash' ),
					'mp_has_specific'     => esc_html__( 'MemberPress: In bestimmter Mitgliedschaft', 'soulsites-learndash' ),
					'mp_not_specific'     => esc_html__( 'MemberPress: Nicht in bestimmter Mitgliedschaft', 'soulsites-learndash' ),
				],
			]
		);

		$element->add_control(
			'ss_condition_course_id',
			[
				'label'       => esc_html__( 'Kurs-ID (optional)', 'soulsites-learndash' ),
				'description' => esc_html__( 'Leer lassen = aktueller Kurs wird automatisch erkannt. Explizite ID eintragen wie beim Shortcode course_id="38414".', 'soulsites-learndash' ),
				'type'        => \Elementor\Controls_Manager::NUMBER,
				'min'         => 1,
				'condition'   => [
					'ss_condition_type' => [ 'course_enrolled', 'course_not_enrolled' ],
				],
			]
		);

		$element->add_control(
			'ss_condition_membership_id',
			[
				'label'       => esc_html__( 'Mitgliedschaft (ID)', 'soulsites-learndash' ),
				'description' => esc_html__( 'Die WordPress-Post-ID der MemberPress-Mitgliedschaft.', 'soulsites-learndash' ),
				'type'        => \Elementor\Controls_Manager::NUMBER,
				'min'         => 1,
				'condition'   => [
					'ss_condition_type' => [ 'mp_has_specific', 'mp_not_specific' ],
				],
			]
		);

		$element->end_controls_section();
	}

	/**
	 * Before an element renders on the frontend: hide it if the condition is not met.
	 *
	 * In edit mode every element is always rendered. On the frontend a CSS
	 * `display:none !important` is added to the wrapper when the condition evaluates
	 * to false.
	 *
	 * @param \Elementor\Element_Base $element Current element instance.
	 */
	public function before_render( $element ) {
		// Im Elementor-Editor und Preview-Modus immer sichtbar lassen.
		if ( \Elementor\Plugin::$instance->editor->is_edit_mode() ) {
			return;
		}
		if ( \Elementor\Plugin::$instance->preview &&
			 \Elementor\Plugin::$instance->preview->is_preview_mode() ) {
			return;
		}

		$condition = $element->get_settings_for_display( 'ss_condition_type' );

		if ( empty( $condition ) ) {
			return;
		}

		$args = [
			'course_id'     => (int) $element->get_settings_for_display( 'ss_condition_course_id' ),
			'membership_id' => (int) $element->get_settings_for_display( 'ss_condition_membership_id' ),
		];

		$result = $this->check_condition( $condition, $args );
		$debug  = $this->collect_debug_info( $element->get_id(), $condition, $args, $result );

		// Debug-Ausgabe in die JS-Konsole (entfernen wenn nicht mehr benötigt).
		echo '<script>console.log("[SS-Conditions] element_id=' . esc_js( $element->get_id() ) . '", ' . wp_json_encode( $debug ) . ');</script>';

		if ( ! $result ) {
			$element->add_render_attribute( '_wrapper', 'style', 'display:none !important;' );
		}
	}

	// -------------------------------------------------------------------------
	// Private helpers
	// -------------------------------------------------------------------------

	/**
	 * Collects debug information for JS console output.
	 *
	 * @param string $element_id Elementor element ID.
	 * @param string $condition  Condition key selected in the editor.
	 * @param array  $args       Additional args (course_id override, membership_id).
	 * @param bool   $result     Final condition result.
	 * @return array
	 */
	private function collect_debug_info( $element_id, $condition, $args, $result ) {
		$post_id   = get_the_ID();
		$post_type = $post_id ? get_post_type( $post_id ) : 'unknown';
		$user_id   = get_current_user_id();

		$info = [
			'element_id'      => $element_id,
			'condition'       => $condition,
			'post_id'         => $post_id,
			'post_type'       => $post_type,
			'user_logged_in'  => is_user_logged_in(),
			'user_id'         => $user_id,
			'final_result'    => $result,
			'element_hidden'  => ! $result,
		];

		if ( in_array( $condition, [ 'course_enrolled', 'course_not_enrolled' ], true ) ) {
			$override_id = $args['course_id'] ?? 0;

			// Kurs-ID auflösen (gleiche Logik wie check_course_access).
			if ( $override_id > 0 ) {
				$course_id = $override_id;
				$id_source = 'explicit';
			} elseif ( $post_id && $post_type === 'sfwd-courses' ) {
				$course_id = $post_id;
				$id_source = 'post_is_course';
			} elseif ( $post_id && function_exists( 'learndash_get_course_id' ) ) {
				$course_id = (int) learndash_get_course_id( $post_id );
				$id_source = 'learndash_get_course_id';
			} else {
				$course_id = 0;
				$id_source = 'not_found';
			}

			$sfwd_result   = null;
			$filter_result = null;

			if ( $course_id && is_user_logged_in() && function_exists( 'sfwd_lms_has_access' ) ) {
				$sfwd_result = (bool) sfwd_lms_has_access( $course_id, $user_id );
				$filter_result = (bool) apply_filters(
					'learndash_student_shortcode_view_content',
					$sfwd_result,
					[ 'course_id' => $course_id, 'user_id' => $user_id, 'content' => '' ]
				);
			}

			$info['course_id_override']    = $override_id;
			$info['course_id_resolved']    = $course_id;
			$info['course_id_source']      = $id_source;
			$info['sfwd_lms_has_access']   = $sfwd_result;
			$info['after_filter']          = $filter_result;
			$info['sfwd_function_exists']  = function_exists( 'sfwd_lms_has_access' );
		}

		if ( in_array( $condition, [ 'mp_has_membership', 'mp_no_membership', 'mp_has_specific', 'mp_not_specific' ], true ) ) {
			$info['mepruser_class_exists'] = class_exists( 'MeprUser' );
			$info['membership_id_arg']     = $args['membership_id'] ?? 0;
		}

		return $info;
	}

	/**
	 * Evaluate a condition string and return whether it is met.
	 *
	 * @param string $condition One of the option values registered in add_controls().
	 * @param array  $args      Optional extra data (e.g. 'membership_id').
	 * @return bool
	 */
	private function check_condition( $condition, $args = [] ) {
		switch ( $condition ) {
			case 'logged_in':
				return is_user_logged_in();

			case 'logged_out':
				return ! is_user_logged_in();

			case 'course_enrolled':
				return $this->check_course_access( true, $args['course_id'] ?? 0 );

			case 'course_not_enrolled':
				return $this->check_course_access( false, $args['course_id'] ?? 0 );

			case 'mp_has_membership':
				return $this->check_memberpress_membership( true );

			case 'mp_no_membership':
				return $this->check_memberpress_membership( false );

			case 'mp_has_specific':
				return $this->check_memberpress_specific( true, $args['membership_id'] ?? 0 );

			case 'mp_not_specific':
				return $this->check_memberpress_specific( false, $args['membership_id'] ?? 0 );

			default:
				return true;
		}
	}

	/**
	 * Check whether the current user has access to a course.
	 *
	 * Mirrors the behaviour of LearnDash's own [student course_id="X"] shortcode:
	 * calls sfwd_lms_has_access() with the resolved course ID, which handles all
	 * access models (direct enrollment, WooCommerce, MemberPress with auto-enroll).
	 *
	 * Course ID resolution order:
	 *  1. Explicit $override_course_id (set via the "Kurs-ID" field in the editor).
	 *  2. Current post is a course (`sfwd-courses`) → use get_the_ID().
	 *  3. Current post is a lesson/topic → learndash_get_course_id().
	 *  4. Nothing found → condition evaluates to false.
	 *
	 * @param bool $should_have_access TRUE = "Course enrolled", FALSE = "Course not enrolled".
	 * @param int  $override_course_id Optional explicit course ID (0 = auto-detect).
	 * @return bool
	 */
	private function check_course_access( $should_have_access, $override_course_id = 0 ) {
		try {
			if ( ! function_exists( 'sfwd_lms_has_access' ) ) {
				return false;
			}

			// --- Kurs-ID ermitteln ---
			$course_id = 0;

			if ( $override_course_id > 0 ) {
				// Explizite ID aus dem Editor-Feld (entspricht course_id="X" im Shortcode).
				$course_id = $override_course_id;
			} else {
				// Auto-Detect: aktuellen Post ermitteln.
				$post_id = get_the_ID();
				if ( ! $post_id ) {
					return false;
				}

				if ( get_post_type( $post_id ) === 'sfwd-courses' ) {
					$course_id = $post_id;
				} elseif ( function_exists( 'learndash_get_course_id' ) ) {
					$course_id = (int) learndash_get_course_id( $post_id );
				}
			}

			if ( ! $course_id ) {
				return false;
			}

			// --- Nicht eingeloggte Nutzer sind nie eingeschrieben ---
			if ( ! is_user_logged_in() ) {
				// "not enrolled" trifft zu, "enrolled" trifft nicht zu.
				return ! $should_have_access;
			}

			$user_id = get_current_user_id();

			// --- Zugriffsprüfung identisch zum [student]-Shortcode ---
			$has_access = (bool) sfwd_lms_has_access( $course_id, $user_id );

			// Filter anwenden – exakt wie der [student]-Shortcode (seit LearnDash 4.4.0).
			// MemberPress und andere Integrationen können hier den Zugang überschreiben.
			$has_access = (bool) apply_filters(
				'learndash_student_shortcode_view_content',
				$has_access,
				[
					'course_id' => $course_id,
					'user_id'   => $user_id,
					'content'   => '',
				]
			);

			return $should_have_access ? $has_access : ! $has_access;

		} catch ( \Throwable $e ) {
			return false;
		}
	}

	/**
	 * Check whether the current user has any active MemberPress membership.
	 *
	 * Returns false if MemberPress is not installed or the user is not logged in
	 * and $should_have is true.
	 *
	 * @param bool $should_have TRUE = check "has membership", FALSE = check "has no membership".
	 * @return bool
	 */
	private function check_memberpress_membership( $should_have ) {
		if ( ! class_exists( 'MeprUser' ) ) {
			return false;
		}

		if ( ! is_user_logged_in() ) {
			return ! $should_have;
		}

		try {
			$mepr_user = new \MeprUser( get_current_user_id() );
			$has       = ! empty( $mepr_user->active_memberships() );
			return $should_have ? $has : ! $has;
		} catch ( \Throwable $e ) {
			return false;
		}
	}

	/**
	 * Check whether the current user is a member of a specific MemberPress membership.
	 *
	 * @param bool $should_have   TRUE = check "is member", FALSE = check "is not member".
	 * @param int  $membership_id Post ID of the MemberPress membership product.
	 * @return bool
	 */
	private function check_memberpress_specific( $should_have, $membership_id ) {
		if ( ! class_exists( 'MeprUser' ) || ! $membership_id ) {
			return false;
		}

		if ( ! is_user_logged_in() ) {
			return ! $should_have;
		}

		try {
			$mepr_user = new \MeprUser( get_current_user_id() );
			$has       = (bool) $mepr_user->is_already_subscribed_to( $membership_id );
			return $should_have ? $has : ! $has;
		} catch ( \Throwable $e ) {
			return false;
		}
	}
}
