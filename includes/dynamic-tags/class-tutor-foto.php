<?php
namespace SoulSites\Dynamic_Tags;

use Elementor\Core\DynamicTags\Data_Tag;
use Elementor\Modules\DynamicTags\Module as TagsModule;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Tutor Foto Dynamic Tag
 * Gibt das ACF-Bild-Feld 'profilbild' des Tutors zurück (Image Category)
 * Verwendet Data_Tag, da Elementor für IMAGE_CATEGORY strukturierte Daten erwartet
 */
class Tutor_Foto extends Data_Tag {

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

    public function get_value( array $options = [] ) {
        try {
            $course_id = get_the_ID();

            if ( ! $course_id || ! function_exists( 'get_field' ) ) {
                return [];
            }

            $tutors = get_field( 'tutor', $course_id );

            if ( empty( $tutors ) || ! is_array( $tutors ) ) {
                return [];
            }

            $tutor = $tutors[0];
            $foto  = get_field( 'profilbild', $tutor->ID );

            if ( ! empty( $foto ) ) {
                if ( is_array( $foto ) ) {
                    // ACF Rückgabeformat: Array
                    $image_id  = absint( $foto['ID'] ?? $foto['id'] ?? 0 );
                    $image_url = $foto['url'] ?? '';
                } elseif ( is_object( $foto ) && isset( $foto->ID ) ) {
                    // ACF Rückgabeformat: WP_Post Objekt
                    $image_id  = absint( $foto->ID );
                    $image_url = wp_get_attachment_url( $image_id );
                } elseif ( is_numeric( $foto ) ) {
                    // ACF Rückgabeformat: ID
                    $image_id  = absint( $foto );
                    $image_url = wp_get_attachment_url( $image_id );
                } elseif ( is_string( $foto ) ) {
                    // ACF Rückgabeformat: URL
                    $image_id  = 0;
                    $image_url = $foto;
                }

                if ( ! empty( $image_url ) ) {
                    return [
                        'id'  => $image_id,
                        'url' => $image_url,
                    ];
                }
            }

            // Fallback: WordPress Beitragsbild des Tutors
            $thumbnail_id = get_post_thumbnail_id( $tutor->ID );
            if ( $thumbnail_id ) {
                return [
                    'id'  => $thumbnail_id,
                    'url' => get_the_post_thumbnail_url( $tutor->ID, 'full' ),
                ];
            }

            return [];
        } catch ( \Exception $e ) {
            return [];
        }
    }
}
