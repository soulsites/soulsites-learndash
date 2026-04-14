<?php
namespace SoulSites\Query;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Tutor Courses Query Filter
 *
 * Filtert LearnDash Kurse in Elementor Loop Widgets nach dem zugeordneten Tutor.
 * Die Query ID in Elementor lautet: tutor_courses
 *
 * Voraussetzung: ACF Relationship-Feld am Kurs-Beitrag (sfwd-courses), das die
 * Tutor-Post-ID speichert. Den Feldnamen in der Konstante SOULSITES_TUTOR_FIELD
 * anpassen oder als Filter überschreiben.
 */
class Tutor_Courses_Query {

    /**
     * ACF-Feldname am Kurs, der die Tutor-Post-ID speichert.
     * Kann per Filter überschrieben werden:
     *   add_filter( 'soulsites_tutor_courses_field_name', fn() => 'mein_feld' );
     *
     * @var string
     */
    const DEFAULT_FIELD_NAME = 'tutor';

    /**
     * Constructor
     */
    public function __construct() {
        add_action( 'elementor/query/tutor_courses', [ $this, 'set_query' ] );
    }

    /**
     * Setzt die WP_Query-Parameter für den Elementor Loop.
     *
     * @param \ElementorPro\Modules\QueryControl\Classes\Elementor_Post_Query $query
     */
    public function set_query( $query ) {
        $tutor_id = get_the_ID();

        if ( ! $tutor_id ) {
            return;
        }

        $field_name = apply_filters( 'soulsites_tutor_courses_field_name', self::DEFAULT_FIELD_NAME );

        $query->set( 'post_type', 'sfwd-courses' );
        $query->set( 'meta_query', [
            [
                'key'     => $field_name,
                'value'   => '"' . $tutor_id . '"',
                'compare' => 'LIKE',
            ],
        ] );
    }
}
