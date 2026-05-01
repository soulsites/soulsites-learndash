<?php
namespace SoulSites\Query;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Course Purchase Query Filter
 *
 * Filtert LearnDash-Kurse im Elementor-Loop-Widget basierend auf dem
 * Einschreibungsstatus des eingeloggten Nutzers.
 *
 * Verwendung:
 *   1. Query-ID des Loop-Widgets auf "course_purchase_filter" setzen.
 *   2. Im Query-Abschnitt das Dropdown "LearnDash Kurs-Filter" auswählen.
 *
 * Technischer Hinweis: Elementor übergibt dem Query-Hook kein reines WP_Query-
 * Objekt und der Widget-Parameter ist nicht zuverlässig. Daher wird – wie beim
 * ACF-Filter – ein before_render-Hook genutzt, um die Widget-Settings vor dem
 * Query-Hook abzugreifen.
 */
class Course_Purchase_Query {

    /** @var array Kurs-ID-Cache pro Request */
    private static $course_cache = [];

    /** @var array|null Zwischengespeicherte Widget-Settings aus before_render */
    private static $pending_settings = null;

    public function __construct() {
        // before_render feuert bevor query_posts() und damit vor dem Query-Hook.
        // So können wir die Widget-Settings sicher abgreifen, unabhängig davon
        // welche Argumente Elementor an den Query-Hook übergibt.
        add_action( 'elementor/element/before_render', [ $this, 'capture_settings' ], 1, 1 );
        add_action( 'elementor/query/course_purchase_filter', [ $this, 'filter_by_purchase_status' ], 10, 2 );
    }

    /**
     * Greift die Filter-Settings des Loop-Widgets ab, bevor der Query-Hook feuert.
     *
     * @param \Elementor\Element_Base $element
     */
    public function capture_settings( $element ) {
        if ( ! $element || ! is_object( $element ) || ! method_exists( $element, 'get_name' ) ) {
            return;
        }

        if ( ! in_array( $element->get_name(), [ 'loop-grid', 'loop-carousel' ], true ) ) {
            return;
        }

        if ( ! method_exists( $element, 'get_settings_for_display' ) ) {
            return;
        }

        try {
            $settings = $element->get_settings_for_display();
        } catch ( \Throwable $e ) {
            return;
        }

        $query_id = isset( $settings['query_id'] ) ? $settings['query_id'] : '';
        if ( $query_id !== 'course_purchase_filter' ) {
            return;
        }

        self::$pending_settings = $settings;
    }

    /**
     * Wendet den Kauf-Filter auf die Query an.
     *
     * @param object      $query  Query-Objekt (kein reines WP_Query – nur is_object() prüfen).
     * @param object|null $widget Widget-Instanz (als Fallback für Settings).
     */
    public function filter_by_purchase_status( $query, $widget = null ) {
        if ( ! defined( 'LEARNDASH_VERSION' ) ) {
            return;
        }

        if ( ! $query || ! is_object( $query ) ) {
            return;
        }

        // Reihenfolge: before_render-Settings (zuverlässigste Quelle)
        // → Widget-Arg als Fallback
        if ( self::$pending_settings !== null ) {
            $settings               = self::$pending_settings;
            self::$pending_settings = null;
        } elseif ( $widget && is_object( $widget ) && method_exists( $widget, 'get_settings_for_display' ) ) {
            $settings = $widget->get_settings_for_display();
        } else {
            return;
        }

        $filter_type = isset( $settings['course_purchase_filter'] ) ? $settings['course_purchase_filter'] : '';

        if ( empty( $filter_type ) ) {
            return;
        }

        $user_id = get_current_user_id();

        if ( ! $user_id ) {
            if ( $filter_type === 'purchased' ) {
                $query->set( 'post__in', [ 0 ] );
            }
            return;
        }

        $filtered = $this->get_filtered_courses( $user_id, $filter_type );

        if ( $filtered === null ) {
            return;
        }

        $query->set( 'post__in', empty( $filtered ) ? [ 0 ] : $filtered );
    }

    /**
     * @param int    $user_id
     * @param string $filter_type 'purchased' | 'not_purchased'
     * @return array|null
     */
    private function get_filtered_courses( $user_id, $filter_type ) {
        $enrolled = $this->get_enrolled_courses( $user_id );

        switch ( $filter_type ) {
            case 'purchased':
                return $enrolled;

            case 'not_purchased':
                $all = $this->get_all_courses();
                return array_values( array_diff( $all, $enrolled ) );

            default:
                return null;
        }
    }

    /**
     * Alle eingeschriebenen Kurse des Nutzers.
     * Nutzt die LearnDash-4.x-Product-API falls verfügbar, sonst
     * learndash_user_get_enrolled_courses() als Bulk-Abfrage.
     *
     * @param int $user_id
     * @return array
     */
    private function get_enrolled_courses( $user_id ) {
        $cache_key = 'enrolled_' . $user_id;
        if ( isset( self::$course_cache[ $cache_key ] ) ) {
            return self::$course_cache[ $cache_key ];
        }

        $enrolled = [];

        try {
            // Primär: LearnDash-Bulk-Funktion (effizient, eine DB-Abfrage)
            if ( function_exists( 'learndash_user_get_enrolled_courses' ) ) {
                $result = learndash_user_get_enrolled_courses( $user_id, [] );
                if ( is_array( $result ) ) {
                    $enrolled = array_map( 'intval', $result );
                }
            }
        } catch ( \Throwable $e ) {
            $enrolled = [];
        }

        self::$course_cache[ $cache_key ] = $enrolled;
        return $enrolled;
    }

    /**
     * Alle veröffentlichten Kurse (für not_purchased-Filter).
     *
     * @return array
     */
    private function get_all_courses() {
        $cache_key = 'all_courses';
        if ( isset( self::$course_cache[ $cache_key ] ) ) {
            return self::$course_cache[ $cache_key ];
        }

        $courses = [];
        try {
            $result = get_posts( [
                'post_type'              => 'sfwd-courses',
                'posts_per_page'         => 500,
                'fields'                 => 'ids',
                'post_status'            => 'publish',
                'no_found_rows'          => true,
                'update_post_meta_cache' => false,
                'update_post_term_cache' => false,
            ] );
            if ( is_array( $result ) ) {
                $courses = array_map( 'intval', $result );
            }
        } catch ( \Throwable $e ) {
            $courses = [];
        }

        self::$course_cache[ $cache_key ] = $courses;
        return $courses;
    }

    /**
     * Registriert das Filter-Dropdown im Elementor Query-Abschnitt.
     *
     * @param \Elementor\Widget_Base $widget
     */
    public static function register_controls( $widget ) {
        if ( ! $widget || ! is_object( $widget ) ) {
            return;
        }

        if ( ! class_exists( '\Elementor\Controls_Manager' ) ) {
            return;
        }

        try {
            $widget->add_control(
                'course_purchase_filter',
                [
                    'label'       => esc_html__( 'LearnDash Kurs-Filter', 'soulsites-learndash' ),
                    'type'        => \Elementor\Controls_Manager::SELECT,
                    'default'     => '',
                    'options'     => [
                        ''             => esc_html__( 'Keine Filterung', 'soulsites-learndash' ),
                        'purchased'    => esc_html__( 'Nur eingeschriebene Kurse', 'soulsites-learndash' ),
                        'not_purchased' => esc_html__( 'Nur nicht eingeschriebene Kurse', 'soulsites-learndash' ),
                    ],
                    'description' => esc_html__( 'Setzt Query-ID auf "course_purchase_filter". Zeigt nur Kurse des eingeloggten Nutzers basierend auf dem Einschreibungsstatus.', 'soulsites-learndash' ),
                    'separator'   => 'before',
                ]
            );
        } catch ( \Throwable $e ) {
            return;
        }
    }
}
