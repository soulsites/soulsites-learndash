<?php
namespace SoulSites\Dynamic_Tags;

use Elementor\Core\DynamicTags\Tag;
use Elementor\Modules\DynamicTags\Module as TagsModule;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Tutor Course Categories Dynamic Tag
 * Gibt die Kategorien des Kurses aus einem ACF-Taxonomie-Feld zurück.
 * ACF-Feldname: dein_taxonomie_feldname – bitte im Plugin anpassen.
 */
class Tutor_Course_Categories extends Tag {

    public function get_name() {
        return 'tutor-course-categories';
    }

    public function get_title() {
        return esc_html__( 'Kurs-Kategorien', 'soulsites-learndash' );
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

            $terms = get_field( 'dein_taxonomie_feldname', $course_id );

            if ( empty( $terms ) || ! is_array( $terms ) ) {
                return;
            }

            $labels = array_map( fn( $term ) => esc_html( $term->name ), $terms );
            echo implode( ', ', $labels );
        } catch ( \Exception $e ) {
            return;
        }
    }
}
