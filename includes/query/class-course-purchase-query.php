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
 *   1. Loop-Grid/Loop-Carousel mit Source = LearnDash Kurse anlegen.
 *   2. Im Query-Abschnitt das Dropdown "LearnDash Kurs-Filter" wählen.
 *   3. Optional: Query-ID kann beliebig sein, sie wird nicht ausgewertet.
 *
 * Technischer Hinweis:
 * Elementor Pros `elementor/query/{id}`-Hook ist beim Loop-Builder-Widget
 * unzuverlässig (Timing/Reihenfolge je nach Version). Stattdessen wird der
 * WordPress-Core-Hook `pre_get_posts` genutzt, der garantiert vor jeder
 * WP_Query feuert. Über `elementor/element/before_render` wird vorher die
 * Filter-Absicht aus den Widget-Settings gesetzt.
 */
class Course_Purchase_Query {

    /** @var array Pro Request gecachte Kurslisten */
    private static $course_cache = [];

    /** @var string Aktiver Filter ('purchased' | 'not_purchased' | '') */
    private static $active_filter = '';

    /** @var bool Ist der pre_get_posts-Handler aktuell registriert? */
    private static $hook_attached = false;

    public function __construct() {
        // Filter-Absicht VOR der Query setzen / wieder aufräumen.
        add_action( 'elementor/element/before_render', [ $this, 'before_render' ], 1, 1 );
        add_action( 'elementor/element/after_render',  [ $this, 'after_render' ], 999, 1 );
    }

    /**
     * Beim Render eines Loop-Widgets prüfen, ob unser Filter konfiguriert ist,
     * und den pre_get_posts-Hook scharfschalten.
     *
     * @param \Elementor\Element_Base $element
     */
    public function before_render( $element ) {
        if ( ! $this->is_target_widget( $element ) ) {
            return;
        }

        try {
            $settings = $element->get_settings_for_display();
        } catch ( \Throwable $e ) {
            return;
        }

        $filter_type = isset( $settings['course_purchase_filter'] ) ? $settings['course_purchase_filter'] : '';
        if ( empty( $filter_type ) ) {
            return;
        }

        self::$active_filter = $filter_type;

        if ( ! self::$hook_attached ) {
            add_action( 'pre_get_posts', [ $this, 'apply_filter_to_query' ], 999 );
            self::$hook_attached = true;
        }
    }

    /**
     * Nach dem Render: alles aufräumen, damit nachfolgende Widgets/Queries
     * nicht versehentlich beeinflusst werden.
     *
     * @param \Elementor\Element_Base $element
     */
    public function after_render( $element ) {
        if ( ! $this->is_target_widget( $element ) ) {
            return;
        }

        self::$active_filter = '';
        if ( self::$hook_attached ) {
            remove_action( 'pre_get_posts', [ $this, 'apply_filter_to_query' ], 999 );
            self::$hook_attached = false;
        }
    }

    /**
     * Modifiziert die WP_Query bevor sie ausgeführt wird.
     * One-shot: greift nur beim ersten passenden Kurs-Query nach Aktivierung.
     *
     * @param \WP_Query $query
     */
    public function apply_filter_to_query( $query ) {
        if ( empty( self::$active_filter ) ) {
            return;
        }

        if ( ! $query instanceof \WP_Query ) {
            return;
        }

        // Nur LearnDash-Kurs-Queries betreffen.
        if ( ! $this->is_courses_query( $query ) ) {
            return;
        }

        if ( ! defined( 'LEARNDASH_VERSION' ) ) {
            return;
        }

        // Lokale Kopie ziehen und Static SOFORT zurücksetzen, damit unsere
        // eigenen internen get_posts()-Aufrufe (z.B. für 'all_courses') nicht
        // rekursiv erneut diesen Filter triggern.
        $filter_type           = self::$active_filter;
        self::$active_filter   = '';

        $user_id = get_current_user_id();

        if ( ! $user_id ) {
            if ( $filter_type === 'purchased' ) {
                $query->set( 'post__in', [ 0 ] );
                $query->set( 'posts_per_page', 0 );
            }
            return;
        }

        $filtered = $this->get_filtered_courses( $user_id, $filter_type );
        if ( $filtered === null ) {
            return;
        }

        // Falls das Widget bereits eine post__in-Liste vorgegeben hat,
        // schneiden wir uns mit dieser, um nicht mehr Kurse zu zeigen
        // als der Nutzer ohnehin sehen würde.
        $existing = $query->get( 'post__in' );
        if ( ! empty( $existing ) && is_array( $existing ) ) {
            $filtered = array_values( array_intersect(
                array_map( 'intval', $existing ),
                $filtered
            ) );
        }

        if ( empty( $filtered ) ) {
            // Leeres Ergebnis erzwingen.
            $query->set( 'post__in', [ 0 ] );
        } else {
            $query->set( 'post__in', $filtered );
        }
    }

    /**
     * @param mixed $element
     * @return bool
     */
    private function is_target_widget( $element ) {
        if ( ! $element || ! is_object( $element ) ) {
            return false;
        }
        if ( ! method_exists( $element, 'get_name' ) ) {
            return false;
        }
        return in_array( $element->get_name(), [ 'loop-grid', 'loop-carousel' ], true );
    }

    /**
     * Prüft ob die Query LearnDash-Kurse abruft.
     *
     * @param \WP_Query $query
     * @return bool
     */
    private function is_courses_query( $query ) {
        $post_type = $query->get( 'post_type' );

        if ( $post_type === 'sfwd-courses' ) {
            return true;
        }
        if ( is_array( $post_type ) && in_array( 'sfwd-courses', $post_type, true ) ) {
            return true;
        }
        return false;
    }

    /**
     * @param int    $user_id
     * @param string $filter_type
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
     * Alle Kurse, in die der Nutzer eingeschrieben ist.
     * Nutzt die offizielle LearnDash-Funktion (deckt direkte Einschreibung
     * UND WooCommerce-Käufe ab, da WooCommerce über ld_update_course_access()
     * dieselben User-Meta-Schlüssel setzt).
     *
     * @param int $user_id
     * @return int[]
     */
    private function get_enrolled_courses( $user_id ) {
        $cache_key = 'enrolled_' . $user_id;
        if ( isset( self::$course_cache[ $cache_key ] ) ) {
            return self::$course_cache[ $cache_key ];
        }

        $enrolled = [];

        try {
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
     * @return int[]
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
                'suppress_filters'       => true,
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
     * Filter-Dropdown im Elementor Query-Abschnitt registrieren.
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
                        ''              => esc_html__( 'Keine Filterung', 'soulsites-learndash' ),
                        'purchased'     => esc_html__( 'Nur eingeschriebene Kurse', 'soulsites-learndash' ),
                        'not_purchased' => esc_html__( 'Nur nicht eingeschriebene Kurse', 'soulsites-learndash' ),
                    ],
                    'description' => esc_html__( 'Filtert die LearnDash-Kurse nach dem Einschreibungsstatus des eingeloggten Nutzers. Voraussetzung: Source = LearnDash Kurse.', 'soulsites-learndash' ),
                    'separator'   => 'before',
                ]
            );
        } catch ( \Throwable $e ) {
            return;
        }
    }
}
