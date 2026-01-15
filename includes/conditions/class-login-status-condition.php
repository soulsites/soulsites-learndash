<?php
namespace SoulSites\Conditions;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Login Status Condition
 */
class Login_Status_Condition extends \ElementorPro\Modules\ThemeBuilder\Conditions\Condition_Base {

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
        return 'login_status';
    }

    /**
     * Get condition label
     */
    public function get_label() {
        return esc_html__( 'Login Status', 'soulsites-learndash' );
    }

    /**
     * Register sub-conditions
     */
    public function register_sub_conditions() {
        $this->register_sub_condition( new Logged_In_Condition() );
        $this->register_sub_condition( new Logged_Out_Condition() );
    }

    /**
     * Check condition
     */
    public function check( $args ) {
        return true;
    }
}
