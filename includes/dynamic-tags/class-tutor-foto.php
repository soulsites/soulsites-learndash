<?php
namespace SoulSites\Dynamic_Tags;

use Elementor\Core\DynamicTags\Tag;
use Elementor\Modules\DynamicTags\Module as TagsModule;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Tutor Foto Dynamic Tag
 * Gibt das ACF-Bild-Feld 'name' des Tutors zurück (Image Category)
 * ACF-Feld 'name' muss als "Bild-Objekt" (Image Array) konfiguriert sein
 */
class Tutor_Foto extends Tag {

    public function get_name() {
        return 'tutor-foto';
    }

    public function get_title() {
        return esc_html__( 'Tutor Foto', 'soulsites-learndash' );
    }

    public function get_group() {
        return 'learndash';
    }

    public function get_categories() {
        return [ TagsModule::IMAGE_CATEGORY ];
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
            $foto  = get_field( 'profilbild', $tutor->ID );

            if ( empty( $foto ) ) {
                // Fallback: WordPress Beitragsbild
                $thumbnail_id = get_post_thumbnail_id( $tutor->ID );
                if ( ! $thumbnail_id ) {
                    return;
                }
                echo wp_json_encode( [
                    'id'  => $thumbnail_id,
                    'url' => get_the_post_thumbnail_url( $tutor->ID, 'full' ),
                ] );
                return;
            }

            if ( is_array( $foto ) ) {
                // ACF Bildformat: Array
                echo wp_json_encode( [
                    'id'  => $foto['ID'] ?? 0,
                    'url' => $foto['url'] ?? '',
                ] );
            } elseif ( is_numeric( $foto ) ) {
                // ACF Bildformat: ID
                echo wp_json_encode( [
                    'id'  => (int) $foto,
                    'url' => wp_get_attachment_url( (int) $foto ),
                ] );
            } elseif ( is_string( $foto ) ) {
                // ACF Bildformat: URL
                echo wp_json_encode( [
                    'id'  => 0,
                    'url' => $foto,
                ] );
            }
        } catch ( \Exception $e ) {
            return;
        }
    }
}
