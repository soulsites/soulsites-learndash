<?php
/**
 * Plugin Name: SoulSites LearnDash for Elementor
 * Plugin URI: https://soulsites.de
 * Description: Erweitert Elementor mit LearnDash Dynamic Tags und Display Conditions für Login Status und Kurs-Kaufstatus.
 * Version: 1.0.0
 * Author: Christian Wedel
 * Author URI: https://soulsites.de
 * Text Domain: soulsites-learndash
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

// Define plugin constants
define( 'SOULSITES_LEARNDASH_VERSION', '1.0.0' );
define( 'SOULSITES_LEARNDASH_FILE', __FILE__ );
define( 'SOULSITES_LEARNDASH_PATH', plugin_dir_path( __FILE__ ) );
define( 'SOULSITES_LEARNDASH_URL', plugin_dir_url( __FILE__ ) );

/**
 * Main Plugin Class
 */
final class SoulSites_LearnDash_Elementor {

    /**
     * Plugin instance
     *
     * @var SoulSites_LearnDash_Elementor
     */
    private static $instance = null;

    /**
     * Get plugin instance
     *
     * @return SoulSites_LearnDash_Elementor
     */
    public static function get_instance() {
        if ( self::$instance === null ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    private function __construct() {
        add_action( 'plugins_loaded', [ $this, 'init' ] );
    }

    /**
     * Initialize plugin
     */
    public function init() {
        // Check if Elementor is installed and activated
        if ( ! did_action( 'elementor/loaded' ) ) {
            add_action( 'admin_notices', [ $this, 'admin_notice_missing_elementor' ] );
            return;
        }

        // Check if Elementor Pro is installed and activated
        if ( ! function_exists( 'elementor_pro_load_plugin' ) ) {
            add_action( 'admin_notices', [ $this, 'admin_notice_missing_elementor_pro' ] );
            return;
        }

        // Check if LearnDash is installed and activated
        if ( ! defined( 'LEARNDASH_VERSION' ) ) {
            add_action( 'admin_notices', [ $this, 'admin_notice_missing_learndash' ] );
            return;
        }

        // Load plugin files
        $this->includes();

        // Register Elementor components
        add_action( 'elementor/theme/register_conditions', [ $this, 'register_conditions' ] );
        add_action( 'elementor/dynamic_tags/register', [ $this, 'register_dynamic_tags' ] );
    }

    /**
     * Include required files
     */
    private function includes() {
        // Display Conditions
        require_once SOULSITES_LEARNDASH_PATH . 'includes/conditions/class-login-status-condition.php';
        require_once SOULSITES_LEARNDASH_PATH . 'includes/conditions/class-logged-in-condition.php';
        require_once SOULSITES_LEARNDASH_PATH . 'includes/conditions/class-logged-out-condition.php';
        require_once SOULSITES_LEARNDASH_PATH . 'includes/conditions/class-course-enrolled-condition.php';

        // Dynamic Tags
        require_once SOULSITES_LEARNDASH_PATH . 'includes/dynamic-tags/class-course-purchase-status.php';
        require_once SOULSITES_LEARNDASH_PATH . 'includes/dynamic-tags/class-course-price.php';
        require_once SOULSITES_LEARNDASH_PATH . 'includes/dynamic-tags/class-course-enrollment-status.php';
        require_once SOULSITES_LEARNDASH_PATH . 'includes/dynamic-tags/class-course-progress.php';
        require_once SOULSITES_LEARNDASH_PATH . 'includes/dynamic-tags/class-course-completion-date.php';
    }

    /**
     * Register Display Conditions
     */
    public function register_conditions( $conditions_manager ) {
        // Login Status Conditions
        $conditions_manager->get_condition( 'general' )->register_sub_condition(
            new SoulSites\Conditions\Login_Status_Condition()
        );

        // Course Enrollment Condition
        $conditions_manager->get_condition( 'general' )->register_sub_condition(
            new SoulSites\Conditions\Course_Enrolled_Condition()
        );
    }

    /**
     * Register Dynamic Tags
     */
    public function register_dynamic_tags( $dynamic_tags_manager ) {
        // Create LearnDash group
        $dynamic_tags_manager->register_group( 'learndash', [
            'title' => esc_html__( 'LearnDash', 'soulsites-learndash' )
        ] );

        // Register tags
        $dynamic_tags_manager->register( new SoulSites\Dynamic_Tags\Course_Purchase_Status() );
        $dynamic_tags_manager->register( new SoulSites\Dynamic_Tags\Course_Price() );
        $dynamic_tags_manager->register( new SoulSites\Dynamic_Tags\Course_Enrollment_Status() );
        $dynamic_tags_manager->register( new SoulSites\Dynamic_Tags\Course_Progress() );
        $dynamic_tags_manager->register( new SoulSites\Dynamic_Tags\Course_Completion_Date() );
    }

    /**
     * Admin notice for missing Elementor
     */
    public function admin_notice_missing_elementor() {
        $message = sprintf(
            esc_html__( '"%1$s" benötigt "%2$s" um zu funktionieren.', 'soulsites-learndash' ),
            '<strong>' . esc_html__( 'SoulSites LearnDash for Elementor', 'soulsites-learndash' ) . '</strong>',
            '<strong>' . esc_html__( 'Elementor', 'soulsites-learndash' ) . '</strong>'
        );
        printf( '<div class="notice notice-warning is-dismissible"><p>%1$s</p></div>', $message );
    }

    /**
     * Admin notice for missing Elementor Pro
     */
    public function admin_notice_missing_elementor_pro() {
        $message = sprintf(
            esc_html__( '"%1$s" benötigt "%2$s" um zu funktionieren.', 'soulsites-learndash' ),
            '<strong>' . esc_html__( 'SoulSites LearnDash for Elementor', 'soulsites-learndash' ) . '</strong>',
            '<strong>' . esc_html__( 'Elementor Pro', 'soulsites-learndash' ) . '</strong>'
        );
        printf( '<div class="notice notice-warning is-dismissible"><p>%1$s</p></div>', $message );
    }

    /**
     * Admin notice for missing LearnDash
     */
    public function admin_notice_missing_learndash() {
        $message = sprintf(
            esc_html__( '"%1$s" benötigt "%2$s" um zu funktionieren.', 'soulsites-learndash' ),
            '<strong>' . esc_html__( 'SoulSites LearnDash for Elementor', 'soulsites-learndash' ) . '</strong>',
            '<strong>' . esc_html__( 'LearnDash LMS', 'soulsites-learndash' ) . '</strong>'
        );
        printf( '<div class="notice notice-warning is-dismissible"><p>%1$s</p></div>', $message );
    }
}

// Initialize plugin
SoulSites_LearnDash_Elementor::get_instance();
