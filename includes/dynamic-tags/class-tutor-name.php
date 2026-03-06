<?php
namespace SoulSites\Dynamic_Tags;

use Elementor\Core\DynamicTags\Tag;
use Elementor\Modules\DynamicTags\Module as TagsModule;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Tutor Name Dynamic Tag
 * Gibt den Namen des Tutors aus dem ACF-Relationship-Feld 'tutor' zurück
 */
class Tutor_Name extends Tag {

    public function get_name() {
        return 'tutor-name';
    }

    public function get_title() {
        return esc_html__( 'Tutor Name', 'soulsites-learndash' );
    }

    public function get_group() {
        return 'learndash';
    }

    public function get_categories() {
        return [ TagsModule::TEXT_CATEGORY ];
    }

    public function render() {
        try {
            $course_id = get_the_ID();

            if ( ! $course_id || ! function_exists( 'get_field' ) ) {
                return;
            }

            $tutors = get_field( 'tutor', $course_id );

            if ( empty( $tutors ) || ! is_array( $tutors ) ) {
                return;
            }

            $tutor = $tutors[0];

            $name = get_field( 'name', $tutor->ID );

            if ( empty( $name ) ) {
                // Fallback auf post_title
                $name = $tutor->post_title;
            }

            echo esc_html( $name );
        } catch ( \Exception $e ) {
            return;
        }
    }
}
