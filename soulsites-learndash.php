<?php
/**
 * Plugin Name: SoulSites LearnDash for Elementor
 * Plugin URI: https://soulsites.de
 * Description: Erweitert Elementor mit LearnDash Dynamic Tags und Display Conditions für Login Status und Kurs-Kaufstatus.
 * Version: 1.1.0
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
define( 'SOULSITES_LEARNDASH_VERSION', '1.1.0' );
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
        add_action( 'plugins_loaded', [ $this, 'init' ], 20 );
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

        // Register Elementor components with proper hooks
        add_action( 'elementor/theme/register_conditions', [ $this, 'register_conditions' ], 10, 1 );
        add_action( 'elementor/dynamic_tags/register', [ $this, 'register_dynamic_tags' ], 10, 1 );

        // Initialize Query Filters (delayed to ensure Elementor is fully loaded)
        add_action( 'elementor/init', [ $this, 'init_query_filters' ], 10 );
    }

    /**
     * Include required files
     */
    private function includes() {
        // Display Conditions
        $condition_files = [
            'class-login-status-condition.php',
            'class-logged-in-condition.php',
            'class-logged-out-condition.php',
            'class-course-enrolled-condition.php',
        ];

        foreach ( $condition_files as $file ) {
            $filepath = SOULSITES_LEARNDASH_PATH . 'includes/conditions/' . $file;
            if ( file_exists( $filepath ) ) {
                require_once $filepath;
            }
        }

        // Dynamic Tags
        $tag_files = [
            'class-course-purchase-status.php',
            'class-course-price.php',
            'class-course-enrollment-status.php',
            'class-course-progress.php',
            'class-course-completion-date.php',
        ];

        foreach ( $tag_files as $file ) {
            $filepath = SOULSITES_LEARNDASH_PATH . 'includes/dynamic-tags/' . $file;
            if ( file_exists( $filepath ) ) {
                require_once $filepath;
            }
        }

        // Query Filters
        $query_file = SOULSITES_LEARNDASH_PATH . 'includes/query/class-course-purchase-query.php';
        if ( file_exists( $query_file ) ) {
            require_once $query_file;
        }
    }

    /**
     * Register Display Conditions
     */
    public function register_conditions( $conditions_manager ) {
        // Sicherheitsprüfung
        if ( ! $conditions_manager || ! is_object( $conditions_manager ) ) {
            return;
        }

        try {
            $general_condition = $conditions_manager->get_condition( 'general' );

            if ( ! $general_condition || ! is_object( $general_condition ) ) {
                return;
            }

            // Login Status Conditions
            if ( class_exists( 'SoulSites\Conditions\Login_Status_Condition' ) ) {
                $general_condition->register_sub_condition(
                    new SoulSites\Conditions\Login_Status_Condition()
                );
            }

            // Course Enrollment Condition
            if ( class_exists( 'SoulSites\Conditions\Course_Enrolled_Condition' ) ) {
                $general_condition->register_sub_condition(
                    new SoulSites\Conditions\Course_Enrolled_Condition()
                );
            }
        } catch ( \Exception $e ) {
            // Bei Fehler nichts tun - verhindert Editor-Crash
            return;
        }
    }

    /**
     * Register Dynamic Tags
     */
    public function register_dynamic_tags( $dynamic_tags_manager ) {
        // Sicherheitsprüfung
        if ( ! $dynamic_tags_manager || ! is_object( $dynamic_tags_manager ) ) {
            return;
        }

        try {
            // Create LearnDash group
            $dynamic_tags_manager->register_group( 'learndash', [
                'title' => esc_html__( 'LearnDash', 'soulsites-learndash' )
            ] );

            // Register tags with class existence check
            $tag_classes = [
                'SoulSites\Dynamic_Tags\Course_Purchase_Status',
                'SoulSites\Dynamic_Tags\Course_Price',
                'SoulSites\Dynamic_Tags\Course_Enrollment_Status',
                'SoulSites\Dynamic_Tags\Course_Progress',
                'SoulSites\Dynamic_Tags\Course_Completion_Date',
            ];

            foreach ( $tag_classes as $class ) {
                if ( class_exists( $class ) ) {
                    $dynamic_tags_manager->register( new $class() );
                }
            }
        } catch ( \Exception $e ) {
            // Bei Fehler nichts tun - verhindert Editor-Crash
            return;
        }
    }

    /**
     * Initialize Query Filters
     */
    public function init_query_filters() {
        if ( ! class_exists( 'SoulSites\Query\Course_Purchase_Query' ) ) {
            return;
        }

        try {
            // Initialize Course Purchase Query Filter (Handler prüft selbst ob Editor)
            new SoulSites\Query\Course_Purchase_Query();

            // Add controls to Loop widgets - muss immer registriert werden für Editor-Panel
            add_action( 'elementor/element/loop-grid/section_query/before_section_end', [ $this, 'add_query_controls' ], 10, 2 );
            add_action( 'elementor/element/loop-carousel/section_query/before_section_end', [ $this, 'add_query_controls' ], 10, 2 );
        } catch ( \Exception $e ) {
            // Bei Fehler nichts tun
            return;
        }
    }

    /**
     * Add Query Controls to Loop Widgets
     */
    public function add_query_controls( $element, $args ) {
        if ( ! class_exists( 'SoulSites\Query\Course_Purchase_Query' ) ) {
            return;
        }

        try {
            SoulSites\Query\Course_Purchase_Query::register_controls( $element );
        } catch ( \Exception $e ) {
            // Bei Fehler nichts tun
            return;
        }
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
