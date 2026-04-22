<?php
namespace SoulSites\Dynamic_Tags;

use Elementor\Core\DynamicTags\Tag;
use Elementor\Modules\DynamicTags\Module as TagsModule;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Woo Course ACF Text Dynamic Tag
 *
 * Liest ein ACF-Textfeld vom LearnDash-Kurs, der mit dem aktuellen
 * WooCommerce-Produkt verknüpft ist (meta: _related_course).
 * Funktioniert auch direkt auf Kurs-, Lektions- und Themenseiten.
 */
class Woo_Course_ACF_Text extends Tag {

    public function get_name() {
        return 'woo-course-acf-text';
    }

    public function get_title() {
        return esc_html__( 'Kurs ACF-Textfeld (Produkt)', 'soulsites-learndash' );
    }

    public function get_group() {
        return 'learndash';
    }

    public function get_categories() {
        return [ TagsModule::TEXT_CATEGORY ];
    }

    protected function register_controls() {
        $this->add_control(
            'field_name',
            [
                'label'       => esc_html__( 'ACF Feldname', 'soulsites-learndash' ),
                'type'        => \Elementor\Controls_Manager::TEXT,
                'placeholder' => esc_html__( 'z.B. kurzbeschreibung', 'soulsites-learndash' ),
                'description' => esc_html__( 'Name (Slug) des ACF-Felds am LearnDash-Kurs.', 'soulsites-learndash' ),
            ]
        );

        $this->add_control(
            'allow_html',
            [
                'label'        => esc_html__( 'HTML erlauben', 'soulsites-learndash' ),
                'type'         => \Elementor\Controls_Manager::SWITCHER,
                'label_on'     => esc_html__( 'Ja', 'soulsites-learndash' ),
                'label_off'    => esc_html__( 'Nein', 'soulsites-learndash' ),
                'return_value' => 'yes',
                'default'      => '',
                'description'  => esc_html__( 'Aktivieren für WYSIWYG-Felder oder Felder mit HTML-Formatierung.', 'soulsites-learndash' ),
            ]
        );

        $this->add_control(
            'fallback',
            [
                'label'       => esc_html__( 'Fallback-Text', 'soulsites-learndash' ),
                'type'        => \Elementor\Controls_Manager::TEXT,
                'default'     => '',
                'description' => esc_html__( 'Wird angezeigt wenn das ACF-Feld leer ist. Leer lassen für keine Ausgabe.', 'soulsites-learndash' ),
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

    public function render() {
        try {
            $settings   = $this->get_settings();
            $field_name = trim( $settings['field_name'] ?? '' );

            if ( empty( $field_name ) || ! function_exists( 'get_field' ) ) {
                echo esc_html( $settings['fallback'] ?? '' );
                return;
            }

            $course_id = $this->resolve_course_id();

            if ( ! $course_id ) {
                echo esc_html( $settings['fallback'] ?? '' );
                return;
            }

            $value = get_field( $field_name, $course_id );

            if ( $value === null || $value === false || $value === '' ) {
                echo esc_html( $settings['fallback'] ?? '' );
                return;
            }

            if ( $settings['allow_html'] === 'yes' ) {
                echo wp_kses_post( $value );
            } else {
                echo esc_html( $value );
            }
        } catch ( \Exception $e ) {
            return;
        }
    }
}
