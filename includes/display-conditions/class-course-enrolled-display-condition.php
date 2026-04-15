<?php
namespace SoulSites\Display_Conditions;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Guard: base class must exist before we can define our classes.
// The hook 'elementor/display_conditions/register' fires from within Elementor Pro's
// DisplayConditions module, so the base class should already be autoloaded at this point.
// If not (e.g. wrong version or module not loaded), we skip silently.
if ( ! class_exists( '\ElementorPro\Modules\DisplayConditions\Conditions\Base\Condition_Base' ) ) {
    return;
}

if ( ! function_exists( __NAMESPACE__ . '\soulsites_get_display_condition_course_id' ) ) :
/**
 * Gemeinsame Hilfsfunktion: Kurs-ID aus aktuellem Post ermitteln.
 * Unterstützt LearnDash-Kursseiten (sfwd-courses, Lektionen, Themen)
 * sowie WooCommerce-Produktseiten mit verknüpftem LearnDash-Kurs.
 *
 * @return int  Kurs-ID oder 0, wenn kein Kurs ermittelbar.
 */
function soulsites_get_display_condition_course_id(): int {
    $post_id = (int) get_the_ID();
    if ( ! $post_id ) {
        return 0;
    }

    // LearnDash-Kursseite (sfwd-courses)
    if ( get_post_type( $post_id ) === 'sfwd-courses' ) {
        return $post_id;
    }

    // LearnDash-Lektionen / Themen / Quizze → übergeordneten Kurs ermitteln.
    if ( function_exists( 'learndash_get_post_types' ) && function_exists( 'learndash_get_course_id' ) ) {
        if ( in_array( get_post_type( $post_id ), learndash_get_post_types( 'course' ), true ) ) {
            return (int) learndash_get_course_id( $post_id );
        }
    }

    // WooCommerce-Produktseite: verknüpften LearnDash-Kurs ermitteln.
    // LearnDash WooCommerce Integration speichert Kurs-IDs im Meta '_related_course'.
    if ( 'product' === get_post_type( $post_id ) ) {
        $related = get_post_meta( $post_id, '_related_course', true );
        if ( ! empty( $related ) && is_array( $related ) ) {
            return (int) reset( $related );
        }
    }

    return 0;
}
endif;

/**
 * Display Condition: Course Is Enrolled
 * Zeigt ein Element nur wenn der Benutzer im aktuellen Kurs eingeschrieben ist.
 */
class Course_Is_Enrolled_Display_Condition extends \ElementorPro\Modules\DisplayConditions\Conditions\Base\Condition_Base {

    public function get_name() {
        return 'learndash_course_is_enrolled';
    }

    public function get_label() {
        return esc_html__( 'LearnDash: Course Enrolled', 'soulsites-learndash' );
    }

    public function get_group() {
        return 'general';
    }

    public function get_options() {
        return [];
    }

    public function check( $args ): bool {
        if ( ! is_user_logged_in() ) {
            return false;
        }

        $course_id = soulsites_get_display_condition_course_id();
        if ( ! $course_id ) {
            return false;
        }

        if ( ! function_exists( 'sfwd_lms_has_access' ) ) {
            return false;
        }

        return (bool) sfwd_lms_has_access( $course_id, get_current_user_id() );
    }
}

/**
 * Display Condition: Course Not Enrolled
 * Zeigt ein Element nur wenn der Benutzer NICHT im aktuellen Kurs eingeschrieben ist.
 */
class Course_Not_Enrolled_Display_Condition extends \ElementorPro\Modules\DisplayConditions\Conditions\Base\Condition_Base {

    public function get_name() {
        return 'learndash_course_not_enrolled';
    }

    public function get_label() {
        return esc_html__( 'LearnDash: Course Not Enrolled', 'soulsites-learndash' );
    }

    public function get_group() {
        return 'general';
    }

    public function get_options() {
        return [];
    }

    public function check( $args ): bool {
        $course_id = soulsites_get_display_condition_course_id();
        if ( ! $course_id ) {
            return false;
        }

        if ( ! is_user_logged_in() ) {
            return true;
        }

        if ( ! function_exists( 'sfwd_lms_has_access' ) ) {
            return true;
        }

        return ! (bool) sfwd_lms_has_access( $course_id, get_current_user_id() );
    }
}
