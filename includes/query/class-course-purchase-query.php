<?php
namespace SoulSites\Query;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Course Purchase Query Filter
 *
 * Filtert LearnDash Kurse in Elementor Loop Widgets basierend auf dem Kaufstatus
 */
class Course_Purchase_Query {

    /**
     * Cache für Kurs-IDs
     *
     * @var array
     */
    private static $course_cache = [];

    /**
     * Constructor
     */
    public function __construct() {
        // Nur initialisieren wenn nicht im Elementor Editor
        if ( $this->is_elementor_editor() ) {
            return;
        }

        // Hook für Elementor Query Filter (Elementor Pro Loop Grid/Carousel mit Query ID)
        add_action( 'elementor/query/course_purchase_filter', [ $this, 'filter_by_purchase_status' ], 10, 2 );
    }

    /**
     * Prüft ob wir uns im Elementor Editor befinden
     *
     * @return bool
     */
    private function is_elementor_editor() {
        // Prüfe ob Elementor geladen ist
        if ( ! class_exists( '\Elementor\Plugin' ) ) {
            return false;
        }

        // Prüfe verschiedene Editor-Modi
        if ( isset( $_GET['elementor-preview'] ) ) {
            return true;
        }

        if ( isset( $_GET['action'] ) && $_GET['action'] === 'elementor' ) {
            return true;
        }

        // Prüfe ob Elementor Editor aktiv ist
        try {
            if ( \Elementor\Plugin::$instance &&
                 \Elementor\Plugin::$instance->editor &&
                 \Elementor\Plugin::$instance->editor->is_edit_mode() ) {
                return true;
            }

            // Prüfe auch Preview-Modus
            if ( \Elementor\Plugin::$instance &&
                 \Elementor\Plugin::$instance->preview &&
                 \Elementor\Plugin::$instance->preview->is_preview_mode() ) {
                return true;
            }
        } catch ( \Exception $e ) {
            // Bei Fehler sicher sein und false zurückgeben
            return false;
        }

        return false;
    }

    /**
     * Holt gecachte Kurs-IDs für einen Benutzer
     *
     * @param int $user_id
     * @param string $type 'all', 'enrolled'
     * @return array
     */
    private function get_cached_courses( $user_id, $type ) {
        $cache_key = $type . '_' . $user_id;

        if ( isset( self::$course_cache[ $cache_key ] ) ) {
            return self::$course_cache[ $cache_key ];
        }

        $courses = [];

        try {
            if ( $type === 'all' ) {
                $courses = get_posts( [
                    'post_type' => 'sfwd-courses',
                    'posts_per_page' => 500, // Limit für Performance
                    'fields' => 'ids',
                    'post_status' => 'publish',
                    'no_found_rows' => true, // Performance-Optimierung
                    'update_post_meta_cache' => false,
                    'update_post_term_cache' => false,
                ] );
            } elseif ( $type === 'enrolled' && function_exists( 'learndash_user_get_enrolled_courses' ) ) {
                $courses = learndash_user_get_enrolled_courses( $user_id );

                // Sicherstellen, dass es ein Array ist
                if ( ! is_array( $courses ) ) {
                    $courses = [];
                }
            }
        } catch ( \Exception $e ) {
            $courses = [];
        }

        self::$course_cache[ $cache_key ] = $courses;

        return $courses;
    }

    /**
     * Fügt Custom Query Controls zu Elementor Loop Widgets hinzu
     */
    public function filter_by_purchase_status( $query ) {
        // Prüfe ob LearnDash aktiv ist
        if ( ! defined( 'LEARNDASH_VERSION' ) ) {
            return;
        }

        // Sicherheitsprüfung für Query-Objekt
        if ( ! $query || ! is_object( $query ) || ! method_exists( $query, 'get_query_vars' ) ) {
            return;
        }

        try {
            $settings = $query->get_query_vars();

            // Prüfe ob Filter aktiviert ist
            if ( ! isset( $settings['course_purchase_filter'] ) || $settings['course_purchase_filter'] === '' ) {
                return;
            }

            $filter_type = $settings['course_purchase_filter'];
            $user_id = get_current_user_id();

            // Wenn Benutzer nicht eingeloggt ist
            if ( ! $user_id ) {
                if ( $filter_type === 'purchased' ) {
                    // Keine Kurse anzeigen, wenn nach gekauften gefiltert wird
                    $query->set( 'post__in', [ 0 ] );
                }
                return;
            }

            $filtered_courses = $this->get_filtered_courses( $user_id, $filter_type );

            if ( $filtered_courses === null ) {
                return;
            }

            // Wenn keine Kurse gefunden wurden, leeres Ergebnis zurückgeben
            if ( empty( $filtered_courses ) ) {
                $query->set( 'post__in', [ 0 ] );
            } else {
                $query->set( 'post__in', $filtered_courses );
            }
        } catch ( \Exception $e ) {
            // Bei Fehler nichts tun - Query läuft normal weiter
            return;
        }
    }

    /**
     * Holt gefilterte Kurse basierend auf Filter-Typ
     *
     * @param int $user_id
     * @param string $filter_type
     * @return array|null
     */
    private function get_filtered_courses( $user_id, $filter_type ) {
        $enrolled_courses = $this->get_cached_courses( $user_id, 'enrolled' );

        switch ( $filter_type ) {
            case 'purchased':
                // Nur gekaufte/eingeschriebene Kurse
                return $enrolled_courses;

            case 'not_purchased':
                // Nur nicht gekaufte Kurse
                $all_courses = $this->get_cached_courses( $user_id, 'all' );
                return array_values( array_diff( $all_courses, $enrolled_courses ) );

            default:
                return null;
        }
    }

    /**
     * Registriert Query Controls für Elementor Widgets
     */
    public static function register_controls( $widget ) {
        // Sicherheitsprüfung
        if ( ! $widget || ! is_object( $widget ) ) {
            return;
        }

        // Prüfe ob Elementor Controls Manager verfügbar ist
        if ( ! class_exists( '\Elementor\Controls_Manager' ) ) {
            return;
        }

        try {
            $widget->add_control(
                'course_purchase_filter',
                [
                    'label' => esc_html__( 'LearnDash Kurs-Filter', 'soulsites-learndash' ),
                    'type' => \Elementor\Controls_Manager::SELECT,
                    'default' => '',
                    'options' => [
                        '' => esc_html__( 'Keine Filterung', 'soulsites-learndash' ),
                        'purchased' => esc_html__( 'Nur gekaufte Kurse', 'soulsites-learndash' ),
                        'not_purchased' => esc_html__( 'Nur nicht gekaufte Kurse', 'soulsites-learndash' ),
                    ],
                    'description' => esc_html__( 'Filtert die angezeigten LearnDash Kurse basierend auf dem Kaufstatus des aktuellen Benutzers.', 'soulsites-learndash' ),
                    'separator' => 'before',
                ]
            );
        } catch ( \Exception $e ) {
            // Bei Fehler nichts tun
            return;
        }
    }
}
