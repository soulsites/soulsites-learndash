<?php
namespace SoulSites\Conditions;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Logged In Condition
 */
class Logged_In_Condition extends \ElementorPro\Modules\ThemeBuilder\Conditions\Condition_Base {

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
        return 'logged_in';
    }

    /**
     * Get condition label
     */
    public function get_label() {
        return esc_html__( 'Logged In', 'soulsites-learndash' );
    }

    /**
     * Check condition
     */
    public function check( $args ) {
        return is_user_logged_in();
    }
}
