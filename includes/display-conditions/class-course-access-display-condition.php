<?php
namespace SoulSites\Display_Conditions;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use ElementorPro\Modules\DisplayConditions\Conditions\Base\Condition_Base;

/**
 * Display Condition: Has Course Access
 * Zeigt ein Element wenn der Benutzer Zugang zum aktuellen Kurs hat.
 * Funktioniert auf Kurs-, Lektions- und Thema-Seiten.
 */
class Course_Has_Access_Display_Condition extends Condition_Base {

    public function get_name(): string {
        return 'learndash_course_has_access';
    }

    public function get_label(): string {
        return esc_html__( 'LearnDash: Has Course Access', 'soulsites-learndash' );
    }

    public function get_group(): string {
        return 'general';
    }

    public function check( $args ): bool {
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
    }

    private function get_current_course_id( int $post_id ): int {
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
 * Display Condition: No Course Access
 * Zeigt ein Element wenn der Benutzer KEINEN Zugang zum aktuellen Kurs hat.
 */
class Course_No_Access_Display_Condition extends Condition_Base {

    public function get_name(): string {
        return 'learndash_course_no_access';
    }

    public function get_label(): string {
        return esc_html__( 'LearnDash: No Course Access', 'soulsites-learndash' );
    }

    public function get_group(): string {
        return 'general';
    }

    public function check( $args ): bool {
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
    }

    private function get_current_course_id( int $post_id ): int {
        if ( get_post_type( $post_id ) === 'sfwd-courses' ) {
            return $post_id;
        }

        if ( function_exists( 'learndash_get_course_id' ) ) {
            return (int) learndash_get_course_id( $post_id );
        }

        return 0;
    }
}
