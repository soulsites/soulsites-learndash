<?php
/**
 * Element Conditions
 *
 * Fügt jedem Elementor-Element einen „Erweiterte Bedingungen"-Abschnitt hinzu.
 * Die Sichtbarkeitslogik spiegelt exakt das Verhalten der LearnDash-Shortcodes
 * [student] und [visitor] wider.
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
 * Verfügbare Bedingungen:
 *   - logged_in           → Nutzer ist eingeloggt
 *   - logged_out          → Nutzer ist ausgeloggt (Besucher)
 *   - course_enrolled     → Nutzer hat Zugang zum Kurs  (wie [student])
 *   - course_not_enrolled → Nutzer hat keinen Zugang    (wie [visitor])
 *
 * Die Kurs-ID wird automatisch aus dem aktuellen Post ermittelt.
 * Optional kann eine explizite ID im Editor eingetragen werden.
 */
class Element_Conditions {

	/** @var Element_Conditions|null */
	private static $instance = null;

	/**
	 * Verhindert, dass ein Abschnitt mehrfach zum gleichen Element hinzugefügt wird
	 * (der Hook `after_section_end` feuert einmal pro beendetem Abschnitt).
	 *
	 * @var array<string, bool>
	 */
	private $processed_elements = [];

	/** @return Element_Conditions */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'elementor/element/after_section_end', [ $this, 'maybe_add_controls' ], 10, 3 );

		foreach ( [ 'widget', 'section', 'column', 'container' ] as $type ) {
			add_action( "elementor/frontend/{$type}/before_render", [ $this, 'before_render' ] );
		}
	}

	// =========================================================================
	// Controls registrieren
	// =========================================================================

	/**
	 * Fügt unseren Abschnitt nach dem letzten Abschnitt im Advanced-Tab ein.
	 * Kompatibel mit Elementor Free und Elementor Pro (verschiedene letzte Abschnitte).
	 *
	 * Primär: bekannte Section-IDs am Ende des Advanced-Tabs.
	 * Fallback: beliebige Advanced-Tab-Sektion via $args['tab'] (zukunftssicher für
	 * neue Elementor-Versionen, die andere Section-IDs verwenden).
	 *
	 * @param \Elementor\Element_Base $element
	 * @param string                  $section_id
	 * @param array                   $args
	 */
	public function maybe_add_controls( $element, $section_id, $args ) {
		// Bekannte Section-IDs am Ende des Advanced-Tabs (je nach Elementor-Version).
		static $trigger_sections = [
			'_section_custom_css_pro',     // Elementor Pro (vor 3.19)
			'_section_custom_css',         // Elementor Free/Pro (3.19+)
			'_section_render_visible',     // Elementor Free (älter)
			'_section_responsive',         // Elementor Free (aktuell)
			'section_effects',             // Motion Effects
			'_section_render_conditions',  // Elementor Pro Display Conditions (3.19+)
			'_section_display_conditions', // Elementor Pro Display Conditions (alternatives ID)
		];

		$is_preferred = in_array( $section_id, $trigger_sections, true );

		// Fallback: falls keine bekannte Sektion zutrifft, aber wir uns im Advanced-Tab
		// befinden – Controls trotzdem registrieren, damit zukünftige Elementor-Versionen
		// mit neuen Section-IDs weiterhin funktionieren.
		$is_advanced_fallback = ! $is_preferred
			&& isset( $args['tab'] )
			&& \Elementor\Controls_Manager::TAB_ADVANCED === $args['tab'];

		if ( ! $is_preferred && ! $is_advanced_fallback ) {
			return;
		}

		// Einmal pro Element-Instanz – nicht pro Typ, damit alle Instanzen ihre
		// eigenen Controls erhalten und gespeicherte Werte nicht verloren gehen.
		$key = $element->get_id() ?: spl_object_hash( $element );
		if ( isset( $this->processed_elements[ $key ] ) ) {
			return;
		}
		$this->processed_elements[ $key ] = true;

		// Try-catch: Falls die Elementor-API unerwartet eine Exception wirft
		// (z. B. bei einem Versions-Mismatch), darf der Editor nicht einfrieren.
		try {
			$this->add_controls( $element );
		} catch ( \Throwable $e ) {
			// Fehlgeschlagen – Controls nicht hinzugefügt, aber Editor läuft weiter.
		}
	}

	/** @param \Elementor\Element_Base $element */
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
					'logged_in'           => esc_html__( 'Eingeloggt', 'soulsites-learndash' ),
					'logged_out'          => esc_html__( 'Ausgeloggt (Besucher)', 'soulsites-learndash' ),
					'course_enrolled'     => esc_html__( 'Kurs: Eingeschrieben (wie [student])', 'soulsites-learndash' ),
					'course_not_enrolled' => esc_html__( 'Kurs: Nicht eingeschrieben (wie [visitor])', 'soulsites-learndash' ),
				],
			]
		);

		$element->add_control(
			'ss_condition_course_id',
			[
				'label'       => esc_html__( 'Kurs-ID (optional)', 'soulsites-learndash' ),
				'description' => esc_html__( 'Leer lassen = aktueller Kurs wird automatisch erkannt. Explizite ID eintragen wie beim Shortcode course_id="123".', 'soulsites-learndash' ),
				'type'        => \Elementor\Controls_Manager::NUMBER,
				'min'         => 1,
				'condition'   => [
					'ss_condition_type' => [ 'course_enrolled', 'course_not_enrolled' ],
				],
			]
		);

		$element->end_controls_section();
	}

	// =========================================================================
	// Frontend-Rendering
	// =========================================================================

	/** @param \Elementor\Element_Base $element */
	public function before_render( $element ) {
		// Im Editor und Preview immer alles anzeigen.
		if ( \Elementor\Plugin::$instance->editor &&
		     \Elementor\Plugin::$instance->editor->is_edit_mode() ) {
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

		$course_id = (int) $element->get_settings_for_display( 'ss_condition_course_id' );

		if ( ! $this->evaluate( $condition, $course_id ) ) {
			$element->add_render_attribute( '_wrapper', 'style', 'display:none !important;' );
		}
	}

	// =========================================================================
	// Bedingungsauswertung
	// =========================================================================

	/**
	 * @param string $condition
	 * @param int    $course_id  Explizite Kurs-ID (0 = auto-detect).
	 * @return bool
	 */
	private function evaluate( $condition, $course_id ) {
		switch ( $condition ) {
			case 'logged_in':
				return is_user_logged_in();

			case 'logged_out':
				return ! is_user_logged_in();

			case 'course_enrolled':
				return $this->student_check( $course_id );

			case 'course_not_enrolled':
				// [visitor]-Logik: Zugang NICHT vorhanden → true.
				return ! $this->student_check( $course_id );

			default:
				return true;
		}
	}

	// =========================================================================
	// [student]-Logik (Kurs-Zugang prüfen)
	// =========================================================================

	/**
	 * Prüft, ob der aktuelle Nutzer Zugang zum Kurs hat.
	 * Spiegelt die Logik des LearnDash [student]-Shortcodes exakt wider.
	 *
	 * @param int $override_course_id  Explizite Kurs-ID aus dem Editor (0 = auto-detect).
	 * @return bool
	 */
	private function student_check( $override_course_id ) {
		// [student]-Shortcode zeigt Inhalt ebenfalls nur für eingeloggte Nutzer.
		if ( ! is_user_logged_in() ) {
			return false;
		}

		$user_id   = get_current_user_id();
		$course_id = $this->resolve_course_id( $override_course_id );

		if ( $course_id > 0 ) {
			// Zugang zu einem bestimmten Kurs prüfen.
			$has_access = $this->user_has_course_access( $course_id, $user_id );

			/**
			 * Allows external integrations (WooCommerce, MemberPress, etc.) to override
			 * the access result for a specific course and user.
			 *
			 * HINWEIS: Wir verwenden hier NICHT learndash_student_shortcode_view_content,
			 * weil dieser LearnDash-Filter für Content-Strings (nicht Booleans) gedacht
			 * ist. Callbacks, die in diesen Filter eingehakt sind, erwarten einen String
			 * als erstes Argument und geben oft einen nicht-leeren String zurück, was bei
			 * (bool)-Cast immer true ergäbe – dadurch würde course_not_enrolled nie zeigen.
			 *
			 * @param bool $has_access Whether the user has access.
			 * @param int  $course_id  The course ID.
			 * @param int  $user_id    The user ID.
			 */
			$has_access = (bool) apply_filters(
				'soulsites_learndash_course_access',
				$has_access,
				$course_id,
				$user_id
			);

			return $has_access;
		}

		// Kein Kurs-Kontext: prüfen ob Nutzer irgendeinen Kurs belegt hat.
		// (Entspricht dem [student]-Shortcode ohne course_id-Attribut.)
		if ( function_exists( 'learndash_user_get_enrolled_courses' ) ) {
			$enrolled = learndash_user_get_enrolled_courses( $user_id, [] );
			return ! empty( $enrolled );
		}

		return false;
	}

	/**
	 * Ermittelt die Kurs-ID – identisch zur Auflösungslogik im [student]-Shortcode.
	 *
	 * Reihenfolge:
	 *  1. Explizite ID aus dem Editor-Feld (wird als Kurs-Post-Type validiert).
	 *  2. Aktueller Post ist ein Kurs-bezogener Post-Type → learndash_get_course_id().
	 *  3. Aktueller Post ist eine WooCommerce-Produktseite → verknüpfter LearnDash-Kurs.
	 *  4. Kein Kontext erkennbar → 0 (kein Kurs).
	 *
	 * @param int $override_course_id
	 * @return int  Aufgelöste Kurs-ID oder 0.
	 */
	private function resolve_course_id( $override_course_id ) {
		if ( $override_course_id > 0 ) {
			// Validieren: muss ein Kurs-Post-Type sein (wie im [student]-Shortcode).
			if ( function_exists( 'learndash_get_post_type_slug' )
			     && learndash_get_post_type_slug( 'course' ) === get_post_type( $override_course_id ) ) {
				return $override_course_id;
			}
			// Ungültige ID → Auto-Detect (gleiche Fallback-Logik wie im Shortcode).
		}

		$post_id = (int) get_the_ID();
		if ( ! $post_id ) {
			return 0;
		}

		// Auto-Detect: gehört der aktuelle Post zu einem LearnDash-Kurs?
		if ( function_exists( 'learndash_get_post_types' ) && function_exists( 'learndash_get_course_id' ) ) {
			if ( in_array( get_post_type( $post_id ), learndash_get_post_types( 'course' ), true ) ) {
				return (int) learndash_get_course_id( $post_id );
			}
		}

		// WooCommerce-Produktseite: verknüpften LearnDash-Kurs ermitteln.
		// LearnDash WooCommerce Integration speichert die Kurs-IDs im Produkt-Meta '_related_course'.
		if ( 'product' === get_post_type( $post_id ) ) {
			$related = get_post_meta( $post_id, '_related_course', true );
			if ( ! empty( $related ) && is_array( $related ) ) {
				return (int) reset( $related );
			}
		}

		return 0;
	}

	/**
	 * Prüft den Kurs-Zugang für einen Nutzer.
	 * Verwendet die moderne Product-API (LearnDash 4.x) falls verfügbar,
	 * sonst Fallback auf sfwd_lms_has_access().
	 *
	 * @param int $course_id
	 * @param int $user_id
	 * @return bool
	 */
	private function user_has_course_access( $course_id, $user_id ) {
		// Moderne API (LearnDash 4.x) – gleich wie im [student]-Shortcode.
		if ( class_exists( '\LearnDash\Core\Models\Product' ) ) {
			try {
				$product = \LearnDash\Core\Models\Product::find( $course_id );
				if ( $product instanceof \LearnDash\Core\Models\Product ) {
					return $product->user_has_access( $user_id )
					       || $product->is_pre_ordered( $user_id );
				}
			} catch ( \Throwable $e ) {
				// Fallthrough zur Legacy-Methode.
			}
		}

		// Legacy-Fallback (LearnDash < 4.x).
		if ( function_exists( 'sfwd_lms_has_access' ) ) {
			return (bool) sfwd_lms_has_access( $course_id, $user_id );
		}

		return false;
	}
}
