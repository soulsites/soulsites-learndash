<?php
namespace SoulSites\Conditions;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Course Enrolled Condition
 */
class Course_Enrolled_Condition extends \ElementorPro\Modules\ThemeBuilder\Conditions\Condition_Base {

    /**
     * Get condition type
     */
    public static function get_type() {
        return 'general';
    }

    /**
     * Get condition name
     */
    public function get_name() {
        return 'course_enrolled';
    }

    /**
     * Get condition label
     */
    public function get_label() {
        return esc_html__( 'Course Enrolled', 'soulsites-learndash' );
    }

    /**
     * Register sub-conditions
     */
    public function register_sub_conditions() {
        $this->register_sub_condition( new Course_Is_Enrolled_Condition() );
        $this->register_sub_condition( new Course_Not_Enrolled_Condition() );
    }

    /**
     * Check condition
     */
    public function check( $args ) {
        return true;
    }
}

/**
 * Course Is Enrolled Sub-Condition
 */
class Course_Is_Enrolled_Condition extends \ElementorPro\Modules\ThemeBuilder\Conditions\Condition_Base {

    /**
     * Get condition type
     */
    public static function get_type() {
        return 'general';
    }

    /**
     * Get condition name
     */
    public function get_name() {
        return 'course_is_enrolled';
    }

    /**
     * Get condition label
     */
    public function get_label() {
        return esc_html__( 'Is Enrolled', 'soulsites-learndash' );
    }

    /**
     * Check condition
     */
    public function check( $args ) {
        if ( ! is_user_logged_in() ) {
            return false;
        }

        $course_id = get_the_ID();
        if ( get_post_type( $course_id ) !== 'sfwd-courses' ) {
            return false;
        }

        $user_id = get_current_user_id();
        return sfwd_lms_has_access( $course_id, $user_id );
    }
}

/**
 * Course Not Enrolled Sub-Condition
 */
class Course_Not_Enrolled_Condition extends \ElementorPro\Modules\ThemeBuilder\Conditions\Condition_Base {

    /**
     * Get condition type
     */
    public static function get_type() {
        return 'general';
    }

    /**
     * Get condition name
     */
    public function get_name() {
        return 'course_not_enrolled';
    }

    /**
     * Get condition label
     */
    public function get_label() {
        return esc_html__( 'Not Enrolled', 'soulsites-learndash' );
    }

    /**
     * Check condition
     */
    public function check( $args ) {
        $course_id = get_the_ID();
        if ( get_post_type( $course_id ) !== 'sfwd-courses' ) {
            return true;
        }

        if ( ! is_user_logged_in() ) {
            return true;
        }

        $user_id = get_current_user_id();
        return ! sfwd_lms_has_access( $course_id, $user_id );
    }
}
