<?php
namespace SoulSites\Query;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Course Purchase Query Filter
 *
 * Filtert LearnDash-Kurse im Elementor-Loop-Widget anhand des Kaufstatus.
 *
 * Verwendung: Query-ID des Loop-Widgets auf einen der folgenden Werte setzen:
 *   - purchased_courses     → zeigt nur Kurse, in die der Nutzer eingeschrieben ist
 *   - not_purchased_courses → zeigt nur Kurse, in die der Nutzer NICHT eingeschrieben ist
 */
class Course_Purchase_Query {

    /** @var array */
    private static $course_cache = [];

    public function __construct() {
        if ( $this->is_elementor_editor() ) {
            return;
        }

        add_action( 'elementor/query/purchased_courses',     [ $this, 'filter_purchased' ],     10, 2 );
        add_action( 'elementor/query/not_purchased_courses', [ $this, 'filter_not_purchased' ], 10, 2 );
    }

    /** Nur eingeschriebene Kurse anzeigen. */
    public function filter_purchased( $query, $widget = null ) {
        $this->apply_filter( $query, 'purchased' );
    }

    /** Nur nicht-eingeschriebene Kurse anzeigen. */
    public function filter_not_purchased( $query, $widget = null ) {
        $this->apply_filter( $query, 'not_purchased' );
    }

    /**
     * Wendet den Filter auf die WP_Query an.
     *
     * @param \WP_Query $query
     * @param string    $filter_type 'purchased' | 'not_purchased'
     */
    private function apply_filter( $query, $filter_type ) {
        if ( ! defined( 'LEARNDASH_VERSION' ) || ! $query instanceof \WP_Query ) {
            return;
        }

        $user_id = get_current_user_id();

        if ( ! $user_id ) {
            // Nicht eingeloggte Nutzer sehen keine gekauften Kurse.
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
     * @param string $filter_type
     * @return array|null
     */
    private function get_filtered_courses( $user_id, $filter_type ) {
        $enrolled = $this->get_cached_courses( $user_id, 'enrolled' );

        switch ( $filter_type ) {
            case 'purchased':
                return $enrolled;

            case 'not_purchased':
                $all = $this->get_cached_courses( $user_id, 'all' );
                return array_values( array_diff( $all, $enrolled ) );

            default:
                return null;
        }
    }

    /**
     * @param int    $user_id
     * @param string $type 'all' | 'enrolled'
     * @return array
     */
    private function get_cached_courses( $user_id, $type ) {
        $key = $type . '_' . $user_id;

        if ( isset( self::$course_cache[ $key ] ) ) {
            return self::$course_cache[ $key ];
        }

        $courses = [];

        try {
            if ( $type === 'all' ) {
                $courses = get_posts( [
                    'post_type'              => 'sfwd-courses',
                    'posts_per_page'         => 500,
                    'fields'                 => 'ids',
                    'post_status'            => 'publish',
                    'no_found_rows'          => true,
                    'update_post_meta_cache' => false,
                    'update_post_term_cache' => false,
                ] );
            } elseif ( $type === 'enrolled' && function_exists( 'learndash_user_get_enrolled_courses' ) ) {
                $courses = learndash_user_get_enrolled_courses( $user_id );
                if ( ! is_array( $courses ) ) {
                    $courses = [];
                }
            }
        } catch ( \Exception $e ) {
            $courses = [];
        }

        self::$course_cache[ $key ] = $courses;

        return $courses;
    }

    private function is_elementor_editor() {
        if ( ! class_exists( '\Elementor\Plugin' ) ) {
            return false;
        }

        if ( isset( $_GET['elementor-preview'] ) ) {
            return true;
        }

        if ( isset( $_GET['action'] ) && $_GET['action'] === 'elementor' ) {
            return true;
        }

        try {
            if ( \Elementor\Plugin::$instance->editor->is_edit_mode() ) {
                return true;
            }
            if ( \Elementor\Plugin::$instance->preview->is_preview_mode() ) {
                return true;
            }
        } catch ( \Exception $e ) {
            return false;
        }

        return false;
    }
}
