<?php
namespace SoulSites\Display_Conditions;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use ElementorPro\Modules\DisplayConditions\Conditions\Base\Condition_Base;

/**
 * Display Condition: Course Is Enrolled
 * Zeigt ein Element nur wenn der Benutzer im aktuellen Kurs eingeschrieben ist.
 */
class Course_Is_Enrolled_Display_Condition extends Condition_Base {

    public function get_name(): string {
        return 'learndash_course_is_enrolled';
    }

    public function get_label(): string {
        return esc_html__( 'LearnDash: Course Enrolled', 'soulsites-learndash' );
    }

    public function get_group(): string {
        return 'general';
    }

    public function check( $args ): bool {
        if ( ! is_user_logged_in() ) {
            return false;
        }

        $course_id = get_the_ID();
        if ( ! $course_id || get_post_type( $course_id ) !== 'sfwd-courses' ) {
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
class Course_Not_Enrolled_Display_Condition extends Condition_Base {

    public function get_name(): string {
        return 'learndash_course_not_enrolled';
    }

    public function get_label(): string {
        return esc_html__( 'LearnDash: Course Not Enrolled', 'soulsites-learndash' );
    }

    public function get_group(): string {
        return 'general';
    }

    public function check( $args ): bool {
        $course_id = get_the_ID();
        if ( ! $course_id || get_post_type( $course_id ) !== 'sfwd-courses' ) {
            return false;
        }

        if ( ! is_user_logged_in() ) {
            return true;
        }

        if ( ! function_exists( 'sfwd_lms_has_access' ) ) {
            return true;
        }

        return ! sfwd_lms_has_access( $course_id, get_current_user_id() );
    }
}
