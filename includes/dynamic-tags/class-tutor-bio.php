<?php
namespace SoulSites\Dynamic_Tags;

use Elementor\Core\DynamicTags\Tag;
use Elementor\Modules\DynamicTags\Module as TagsModule;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Tutor Bio Dynamic Tag
 * Gibt den Kurztext aus dem ACF-Feld 'bio_kurz' des Tutors zurück
 */
class Tutor_Bio extends Tag {

    public function get_name() {
        return 'tutor-bio';
    }

    public function get_title() {
        return esc_html__( 'Tutor Bio', 'soulsites-learndash' );
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

            $tutor    = $tutors[0];
            $bio_kurz = get_field( 'bio_kurz', $tutor->ID );

            if ( empty( $bio_kurz ) ) {
                return;
            }

            echo wp_kses_post( $bio_kurz );
        } catch ( \Exception $e ) {
            return;
        }
    }
}
