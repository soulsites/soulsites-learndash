<?php
namespace SoulSites\Dynamic_Tags;

use Elementor\Core\DynamicTags\Data_Tag;
use Elementor\Modules\DynamicTags\Module as TagsModule;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Woo Course Thumbnail Dynamic Tag
 *
 * Gibt das WordPress-Beitragsbild (Featured Image) des LearnDash-Kurses zurück,
 * der mit dem aktuellen WooCommerce-Produkt verknüpft ist (meta: _related_course).
 * Funktioniert auch direkt auf Kurs-, Lektions- und Themenseiten.
 */
class Woo_Course_Thumbnail extends Data_Tag {

    public function get_name() {
        return 'woo-course-thumbnail';
    }

    public function get_title() {
        return esc_html__( 'Kurs Beitragsbild (Produkt)', 'soulsites-learndash' );
    }

    public function get_group() {
        return 'learndash';
    }

    public function get_categories() {
        return [ TagsModule::IMAGE_CATEGORY ];
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
            $course_id = $this->resolve_course_id();
            if ( ! $course_id ) {
                return [];
            }

            $thumbnail_id = get_post_thumbnail_id( $course_id );
            if ( ! $thumbnail_id ) {
                return [];
            }

            $image_url = get_the_post_thumbnail_url( $course_id, 'full' );
            if ( ! $image_url ) {
                return [];
            }

            return [
                'id'  => (int) $thumbnail_id,
                'url' => $image_url,
            ];
        } catch ( \Exception $e ) {
            return [];
        }
    }
}
