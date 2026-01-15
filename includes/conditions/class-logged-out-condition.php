<?php
namespace SoulSites\Conditions;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Logged Out Condition
 */
class Logged_Out_Condition extends \ElementorPro\Modules\ThemeBuilder\Conditions\Condition_Base {

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
        return 'logged_out';
    }

    /**
     * Get condition label
     */
    public function get_label() {
        return esc_html__( 'Logged Out', 'soulsites-learndash' );
    }

    /**
     * Check condition
     */
    public function check( $args ) {
        return ! is_user_logged_in();
    }
}
