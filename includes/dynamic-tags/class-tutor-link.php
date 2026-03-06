<?php
namespace SoulSites\Dynamic_Tags;

use Elementor\Core\DynamicTags\Tag;
use Elementor\Modules\DynamicTags\Module as TagsModule;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Tutor Link Dynamic Tag
 * Gibt den Permalink zur Tutoren-Seite zurück (URL Category)
 */
class Tutor_Link extends Tag {

    public function get_name() {
        return 'tutor-link';
    }

    public function get_title() {
        return esc_html__( 'Tutor Link', 'soulsites-learndash' );
    }

    public function get_group() {
        return 'learndash';
    }

    public function get_categories() {
        return [ TagsModule::URL_CATEGORY ];
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
            $url   = get_permalink( $tutor->ID );

            if ( empty( $url ) ) {
                return;
            }

            echo esc_url( $url );
        } catch ( \Exception $e ) {
            return;
        }
    }
}
