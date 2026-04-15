<?php
namespace SoulSites\Display_Conditions;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Guard: base class must exist before we can define our classes.
if ( ! class_exists( '\ElementorPro\Modules\DisplayConditions\Conditions\Base\Condition_Base' ) ) {
    return;
}

// Hilfsfunktion: Kurs-ID ermitteln (mit Guard, falls die Enrolled-Datei nicht geladen wurde).
if ( ! function_exists( __NAMESPACE__ . '\soulsites_get_display_condition_course_id' ) ) {
    /**
     * Kurs-ID aus aktuellem Post ermitteln.
     * Unterstützt LearnDash-Kursseiten sowie WooCommerce-Produktseiten.
     *
     * @return int Kurs-ID oder 0.
     */
    function soulsites_get_display_condition_course_id(): int {
        $post_id = (int) get_the_ID();
        if ( ! $post_id ) {
            return 0;
        }
        if ( get_post_type( $post_id ) === 'sfwd-courses' ) {
            return $post_id;
        }
        if ( function_exists( 'learndash_get_post_types' ) && function_exists( 'learndash_get_course_id' ) ) {
            if ( in_array( get_post_type( $post_id ), learndash_get_post_types( 'course' ), true ) ) {
                return (int) learndash_get_course_id( $post_id );
            }
        }
        if ( 'product' === get_post_type( $post_id ) ) {
            $related = get_post_meta( $post_id, '_related_course', true );
            if ( ! empty( $related ) && is_array( $related ) ) {
                return (int) reset( $related );
            }
        }
        return 0;
    }
}

/**
 * Display Condition: Has Course Access
 * Zeigt ein Element wenn der Benutzer Zugang zum aktuellen Kurs hat.
 * Funktioniert auf Kurs-, Lektions- und Thema-Seiten.
 */
class Course_Has_Access_Display_Condition extends \ElementorPro\Modules\DisplayConditions\Conditions\Base\Condition_Base {

    public function get_name() {
        return 'learndash_course_has_access';
    }

    public function get_label() {
        return esc_html__( 'LearnDash: Has Course Access', 'soulsites-learndash' );
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
 * Display Condition: No Course Access
 * Zeigt ein Element wenn der Benutzer KEINEN Zugang zum aktuellen Kurs hat.
 */
class Course_No_Access_Display_Condition extends \ElementorPro\Modules\DisplayConditions\Conditions\Base\Condition_Base {

    public function get_name() {
        return 'learndash_course_no_access';
    }

    public function get_label() {
        return esc_html__( 'LearnDash: No Course Access', 'soulsites-learndash' );
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
