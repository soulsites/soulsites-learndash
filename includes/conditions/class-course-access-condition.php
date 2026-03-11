<?php
namespace SoulSites\Conditions;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Course Access Condition
 *
 * Übergeordnete Bedingung für den Kurs-Zugriffsstatus.
 * Funktioniert auf Kurs-, Lektion- und Thema-Seiten.
 */
class Course_Access_Condition extends \ElementorPro\Modules\ThemeBuilder\Conditions\Condition_Base {

    public static function get_type() {
        return 'general';
    }

    public function get_name() {
        return 'course_access';
    }

    public function get_label() {
        return esc_html__( 'Course Access', 'soulsites-learndash' );
    }

    public function register_sub_conditions() {
        $this->register_sub_condition( new Course_Has_Access_Condition() );
        $this->register_sub_condition( new Course_No_Access_Condition() );
    }

    public function check( $args ) {
        return true;
    }
}

/**
 * Course Has Access Sub-Condition
 *
 * Prüft ob der eingeloggte Nutzer Zugriff auf den aktuellen Kurs hat.
 * Funktioniert auf Kurs-Seiten (sfwd-courses) sowie auf Lektionen
 * und Themen (sfwd-lesson, sfwd-topic) via learndash_get_course_id().
 */
class Course_Has_Access_Condition extends \ElementorPro\Modules\ThemeBuilder\Conditions\Condition_Base {

    public static function get_type() {
        return 'course_access';
    }

    public function get_name() {
        return 'course_has_access';
    }

    public function get_label() {
        return esc_html__( 'Has Access to Current Course', 'soulsites-learndash' );
    }

    public function check( $args ) {
        try {
            if ( ! is_user_logged_in() ) {
                return false;
            }

            $post_id = get_the_ID();
            if ( ! $post_id ) {
                return false;
            }

            $course_id = $this->get_current_course_id( $post_id );
            if ( ! $course_id ) {
                return false;
            }

            if ( ! function_exists( 'sfwd_lms_has_access' ) ) {
                return false;
            }

            return (bool) sfwd_lms_has_access( $course_id, get_current_user_id() );
        } catch ( \Exception $e ) {
            return false;
        }
    }

    /**
     * Ermittelt die Kurs-ID anhand der aktuellen Post-ID.
     * Unterstützt Kurse, Lektionen und Themen.
     */
    private function get_current_course_id( $post_id ) {
        if ( get_post_type( $post_id ) === 'sfwd-courses' ) {
            return $post_id;
        }

        if ( function_exists( 'learndash_get_course_id' ) ) {
            return (int) learndash_get_course_id( $post_id );
        }

        return 0;
    }
}

/**
 * Course No Access Sub-Condition
 *
 * Prüft ob der Nutzer KEINEN Zugriff auf den aktuellen Kurs hat
 * (nicht eingeloggt oder nicht eingeschrieben).
 */
class Course_No_Access_Condition extends \ElementorPro\Modules\ThemeBuilder\Conditions\Condition_Base {

    public static function get_type() {
        return 'course_access';
    }

    public function get_name() {
        return 'course_no_access';
    }

    public function get_label() {
        return esc_html__( 'No Access to Current Course', 'soulsites-learndash' );
    }

    public function check( $args ) {
        try {
            $post_id = get_the_ID();
            if ( ! $post_id ) {
                return false;
            }

            $course_id = $this->get_current_course_id( $post_id );
            if ( ! $course_id ) {
                return false;
            }

            if ( ! is_user_logged_in() ) {
                return true;
            }

            if ( ! function_exists( 'sfwd_lms_has_access' ) ) {
                return true;
            }

            return ! sfwd_lms_has_access( $course_id, get_current_user_id() );
        } catch ( \Exception $e ) {
            return false;
        }
    }

    /**
     * Ermittelt die Kurs-ID anhand der aktuellen Post-ID.
     */
    private function get_current_course_id( $post_id ) {
        if ( get_post_type( $post_id ) === 'sfwd-courses' ) {
            return $post_id;
        }

        if ( function_exists( 'learndash_get_course_id' ) ) {
            return (int) learndash_get_course_id( $post_id );
        }

        return 0;
    }
}
