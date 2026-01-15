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
     * Constructor
     */
    public function __construct() {
        // Hook für Elementor Query Filter
        add_action( 'elementor/query/course_purchase_filter', [ $this, 'filter_by_purchase_status' ], 10, 2 );

        // Filter für Loop Widget Query Controls hinzufügen
        add_filter( 'elementor_pro/query/query_args', [ $this, 'add_query_args' ], 10, 2 );
    }

    /**
     * Fügt Custom Query Controls zu Elementor Loop Widgets hinzu
     */
    public function filter_by_purchase_status( $query ) {
        // Prüfe ob LearnDash aktiv ist
        if ( ! defined( 'LEARNDASH_VERSION' ) ) {
            return;
        }

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

        // Hole alle LearnDash Kurse
        $all_courses = get_posts( [
            'post_type' => 'sfwd-courses',
            'posts_per_page' => -1,
            'fields' => 'ids',
        ] );

        // Hole alle vom Benutzer gekauften/eingeschriebenen Kurse
        $enrolled_courses = learndash_user_get_enrolled_courses( $user_id );

        $filtered_courses = [];

        switch ( $filter_type ) {
            case 'purchased':
                // Nur gekaufte/eingeschriebene Kurse
                $filtered_courses = $enrolled_courses;
                break;

            case 'not_purchased':
                // Nur nicht gekaufte Kurse
                $filtered_courses = array_diff( $all_courses, $enrolled_courses );
                break;

            default:
                return;
        }

        // Wenn keine Kurse gefunden wurden, leeres Ergebnis zurückgeben
        if ( empty( $filtered_courses ) ) {
            $query->set( 'post__in', [ 0 ] );
        } else {
            $query->set( 'post__in', $filtered_courses );
        }
    }

    /**
     * Fügt Query Args basierend auf Meta-Einstellungen hinzu
     */
    public function add_query_args( $query_args, $widget ) {
        // Prüfe ob Widget Settings vorhanden sind
        $settings = $widget->get_settings();

        if ( ! isset( $settings['course_purchase_filter'] ) || $settings['course_purchase_filter'] === '' ) {
            return $query_args;
        }

        $filter_type = $settings['course_purchase_filter'];
        $user_id = get_current_user_id();

        // Wenn Benutzer nicht eingeloggt ist
        if ( ! $user_id ) {
            if ( $filter_type === 'purchased' ) {
                // Keine Kurse anzeigen
                $query_args['post__in'] = [ 0 ];
            }
            return $query_args;
        }

        // Nur für LearnDash Kurse anwenden
        if ( ! isset( $query_args['post_type'] ) || $query_args['post_type'] !== 'sfwd-courses' ) {
            return $query_args;
        }

        // Hole alle LearnDash Kurse
        $all_courses = get_posts( [
            'post_type' => 'sfwd-courses',
            'posts_per_page' => -1,
            'fields' => 'ids',
        ] );

        // Hole alle vom Benutzer gekauften/eingeschriebenen Kurse
        $enrolled_courses = learndash_user_get_enrolled_courses( $user_id );

        $filtered_courses = [];

        switch ( $filter_type ) {
            case 'purchased':
                // Nur gekaufte/eingeschriebene Kurse
                $filtered_courses = $enrolled_courses;
                break;

            case 'not_purchased':
                // Nur nicht gekaufte Kurse
                $filtered_courses = array_diff( $all_courses, $enrolled_courses );
                break;

            default:
                return $query_args;
        }

        // Wenn keine Kurse gefunden wurden, leeres Ergebnis zurückgeben
        if ( empty( $filtered_courses ) ) {
            $query_args['post__in'] = [ 0 ];
        } else {
            $query_args['post__in'] = $filtered_courses;
        }

        return $query_args;
    }

    /**
     * Registriert Query Controls für Elementor Widgets
     */
    public static function register_controls( $widget ) {
        $widget->start_controls_section(
            'section_course_purchase_filter',
            [
                'label' => esc_html__( 'LearnDash Kurs-Filter', 'soulsites-learndash' ),
                'tab' => \Elementor\Controls_Manager::TAB_CONTENT,
            ]
        );

        $widget->add_control(
            'course_purchase_filter',
            [
                'label' => esc_html__( 'Kurse nach Kaufstatus filtern', 'soulsites-learndash' ),
                'type' => \Elementor\Controls_Manager::SELECT,
                'default' => '',
                'options' => [
                    '' => esc_html__( 'Keine Filterung', 'soulsites-learndash' ),
                    'purchased' => esc_html__( 'Nur gekaufte Kurse', 'soulsites-learndash' ),
                    'not_purchased' => esc_html__( 'Nur nicht gekaufte Kurse', 'soulsites-learndash' ),
                ],
                'description' => esc_html__( 'Filtert die angezeigten LearnDash Kurse basierend auf dem Kaufstatus des aktuellen Benutzers.', 'soulsites-learndash' ),
            ]
        );

        $widget->end_controls_section();
    }
}
