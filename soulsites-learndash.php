<?php
/**
 * Plugin Name: SoulSites LearnDash for Elementor
 * Plugin URI: https://soulsites.de
 * Description: Erweitert Elementor mit LearnDash Dynamic Tags und Display Conditions für Login Status und Kurs-Kaufstatus.
 * Version: 1.2.0
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
define( 'SOULSITES_LEARNDASH_VERSION', '1.2.0' );
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
        if ( ! defined( 'ELEMENTOR_PRO_VERSION' ) ) {
            add_action( 'admin_notices', [ $this, 'admin_notice_missing_elementor_pro' ] );
            return;
        }

        // Check if LearnDash is installed and activated
        if ( ! defined( 'LEARNDASH_VERSION' ) ) {
            add_action( 'admin_notices', [ $this, 'admin_notice_missing_learndash' ] );
            return;
        }

        // Jede Dateigruppe wird erst im jeweiligen Elementor-Hook geladen,
        // damit Elementors Autoloader alle Basisklassen bereits bereitstellt.
        add_action( 'elementor/theme/register_conditions', [ $this, 'register_conditions' ], 10, 1 );
        add_action( 'elementor/dynamic_tags/register', [ $this, 'register_dynamic_tags' ], 10, 1 );
        add_action( 'elementor/widgets/register', [ $this, 'register_widgets' ], 10, 1 );
        add_action( 'elementor/init', [ $this, 'init_query_filters' ], 10 );
    }

    /**
     * Register Display Conditions
     * Lädt die Condition-Klassen erst hier, damit ElementorPro\Condition_Base verfügbar ist.
     */
    public function register_conditions( $conditions_manager ) {
        if ( ! $conditions_manager || ! is_object( $conditions_manager ) ) {
            return;
        }

        try {
            // Dateien erst jetzt laden – Elementor Pro ist zu diesem Zeitpunkt vollständig initialisiert
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

            $general_condition = $conditions_manager->get_condition( 'general' );

            if ( ! $general_condition || ! is_object( $general_condition ) ) {
                return;
            }

            if ( class_exists( 'SoulSites\Conditions\Login_Status_Condition' ) ) {
                $general_condition->register_sub_condition(
                    new SoulSites\Conditions\Login_Status_Condition()
                );
            }

            if ( class_exists( 'SoulSites\Conditions\Course_Enrolled_Condition' ) ) {
                $general_condition->register_sub_condition(
                    new SoulSites\Conditions\Course_Enrolled_Condition()
                );
            }
        } catch ( \Exception $e ) {
            return;
        }
    }

    /**
     * Register Dynamic Tags
     * Lädt die Tag-Klassen erst hier, damit Elementor\Core\DynamicTags\Tag verfügbar ist.
     */
    public function register_dynamic_tags( $dynamic_tags_manager ) {
        if ( ! $dynamic_tags_manager || ! is_object( $dynamic_tags_manager ) ) {
            return;
        }

        try {
            // Dateien erst jetzt laden – Elementors DynamicTags-Modul ist bereit
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

            $dynamic_tags_manager->register_group( 'learndash', [
                'title' => esc_html__( 'LearnDash', 'soulsites-learndash' ),
            ] );

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
            return;
        }
    }

    /**
     * Register Elementor Widgets
     * Lädt die Widget-Klassen erst hier, damit Elementor\Widget_Base verfügbar ist.
     */
    public function register_widgets( $widgets_manager ) {
        if ( ! $widgets_manager || ! is_object( $widgets_manager ) ) {
            return;
        }

        try {
            // Dateien erst jetzt laden – Elementor\Widget_Base ist garantiert verfügbar
            $widget_files = [
                'class-course-progress-bar.php',
                'class-course-content.php',
            ];
            foreach ( $widget_files as $file ) {
                $filepath = SOULSITES_LEARNDASH_PATH . 'includes/widgets/' . $file;
                if ( file_exists( $filepath ) ) {
                    require_once $filepath;
                }
            }

            $widget_classes = [
                'SoulSites\Widgets\Course_Progress_Bar',
                'SoulSites\Widgets\Course_Content',
            ];

            foreach ( $widget_classes as $class ) {
                if ( class_exists( $class ) ) {
                    $widgets_manager->register( new $class() );
                }
            }
        } catch ( \Exception $e ) {
            return;
        }
    }

    /**
     * Initialize Query Filters
     */
    public function init_query_filters() {
        try {
            $query_file = SOULSITES_LEARNDASH_PATH . 'includes/query/class-course-purchase-query.php';
            if ( file_exists( $query_file ) ) {
                require_once $query_file;
            }

            if ( ! class_exists( 'SoulSites\Query\Course_Purchase_Query' ) ) {
                return;
            }

            new SoulSites\Query\Course_Purchase_Query();

            add_action( 'elementor/element/loop-grid/section_query/before_section_end', [ $this, 'add_query_controls' ], 10, 2 );
            add_action( 'elementor/element/loop-carousel/section_query/before_section_end', [ $this, 'add_query_controls' ], 10, 2 );
        } catch ( \Exception $e ) {
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
        echo '<div class="notice notice-warning is-dismissible"><p>' . wp_kses_post( $message ) . '</p></div>';
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
        echo '<div class="notice notice-warning is-dismissible"><p>' . wp_kses_post( $message ) . '</p></div>';
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
        echo '<div class="notice notice-warning is-dismissible"><p>' . wp_kses_post( $message ) . '</p></div>';
    }
}

// Initialize plugin
SoulSites_LearnDash_Elementor::get_instance();
