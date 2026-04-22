<?php
namespace SoulSites\Dynamic_Tags;

use Elementor\Core\DynamicTags\Data_Tag;
use Elementor\Modules\DynamicTags\Module as TagsModule;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Woo Course ACF Image Dynamic Tag
 *
 * Liest ein ACF-Bildfeld vom LearnDash-Kurs, der mit dem aktuellen
 * WooCommerce-Produkt verknüpft ist (meta: _related_course).
 * Funktioniert auch direkt auf Kurs-, Lektions- und Themenseiten.
 * Verwendet Data_Tag, da Elementor für IMAGE_CATEGORY strukturierte Daten erwartet.
 */
class Woo_Course_ACF_Image extends Data_Tag {

    public function get_name() {
        return 'woo-course-acf-image';
    }

    public function get_title() {
        return esc_html__( 'Kurs ACF-Bild (Produkt)', 'soulsites-learndash' );
    }

    public function get_group() {
        return 'learndash';
    }

    public function get_categories() {
        return [ TagsModule::IMAGE_CATEGORY ];
    }

    protected function register_controls() {
        $this->add_control(
            'field_name',
            [
                'label'       => esc_html__( 'ACF Feldname', 'soulsites-learndash' ),
                'type'        => \Elementor\Controls_Manager::TEXT,
                'placeholder' => esc_html__( 'z.B. kursbild', 'soulsites-learndash' ),
                'description' => esc_html__( 'Name (Slug) des ACF-Bild-Felds am LearnDash-Kurs.', 'soulsites-learndash' ),
            ]
        );
    }

    /**
     * Ermittelt die Kurs-ID aus dem aktuellen Kontext:
     * WooCommerce-Produkt → verknüpfter Kurs via _related_course
     * Kurs / Lektion / Thema → direkt oder per learndash_get_course_id()
     */
    private function resolve_course_id(): int {
        $post_id = (int) get_the_ID();
        if ( ! $post_id ) {
            return 0;
        }

        $post_type = get_post_type( $post_id );

        if ( $post_type === 'sfwd-courses' ) {
            return $post_id;
        }

        if ( function_exists( 'learndash_get_post_types' ) && function_exists( 'learndash_get_course_id' ) ) {
            if ( in_array( $post_type, learndash_get_post_types( 'course' ), true ) ) {
                return (int) learndash_get_course_id( $post_id );
            }
        }

        if ( $post_type === 'product' ) {
            $related = get_post_meta( $post_id, '_related_course', true );
            if ( ! empty( $related ) && is_array( $related ) ) {
                return (int) reset( $related );
            }
        }

        return 0;
    }

    public function get_value( array $options = [] ) {
        try {
            $settings   = $this->get_settings();
            $field_name = trim( $settings['field_name'] ?? '' );

            if ( empty( $field_name ) || ! function_exists( 'get_field' ) ) {
                return [];
            }

            $course_id = $this->resolve_course_id();
            if ( ! $course_id ) {
                return [];
            }

            $foto = get_field( $field_name, $course_id );

            if ( empty( $foto ) ) {
                return [];
            }

            if ( is_array( $foto ) ) {
                $image_id  = absint( $foto['ID'] ?? $foto['id'] ?? 0 );
                $image_url = $foto['url'] ?? '';
            } elseif ( is_object( $foto ) && isset( $foto->ID ) ) {
                $image_id  = absint( $foto->ID );
                $image_url = (string) wp_get_attachment_url( $image_id );
            } elseif ( is_numeric( $foto ) ) {
                $image_id  = absint( $foto );
                $image_url = (string) wp_get_attachment_url( $image_id );
            } elseif ( is_string( $foto ) ) {
                $image_id  = 0;
                $image_url = $foto;
            } else {
                return [];
            }

            if ( empty( $image_url ) ) {
                return [];
            }

            return [
                'id'  => $image_id,
                'url' => $image_url,
            ];
        } catch ( \Exception $e ) {
            return [];
        }
    }
}
