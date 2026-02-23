<?php
namespace SoulSites\Dynamic_Tags;

use Elementor\Core\DynamicTags\Tag;
use Elementor\Modules\DynamicTags\Module as TagsModule;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Course Price Dynamic Tag
 * Zeigt den Kurspreis an, wenn der Benutzer nicht eingeschrieben ist
 */
class Course_Price extends Tag {

    /**
     * Get tag name
     */
    public function get_name() {
        return 'course-price';
    }

    /**
     * Get tag title
     */
    public function get_title() {
        return esc_html__( 'Kurs Preis', 'soulsites-learndash' );
    }

    /**
     * Get tag group
     */
    public function get_group() {
        return 'learndash';
    }

    /**
     * Get tag categories
     */
    public function get_categories() {
        return [ TagsModule::TEXT_CATEGORY ];
    }

    /**
     * Register controls
     */
    protected function register_controls() {
        $this->add_control(
            'show_currency',
            [
                'label' => esc_html__( 'Währungssymbol anzeigen', 'soulsites-learndash' ),
                'type' => \Elementor\Controls_Manager::SWITCHER,
                'label_on' => esc_html__( 'Ja', 'soulsites-learndash' ),
                'label_off' => esc_html__( 'Nein', 'soulsites-learndash' ),
                'return_value' => 'yes',
                'default' => 'yes',
            ]
        );

        $this->add_control(
            'free_text',
            [
                'label' => esc_html__( 'Text für kostenlose Kurse', 'soulsites-learndash' ),
                'type' => \Elementor\Controls_Manager::TEXT,
                'default' => esc_html__( 'Kostenlos', 'soulsites-learndash' ),
            ]
        );

        $this->add_control(
            'enrolled_text',
            [
                'label' => esc_html__( 'Text wenn bereits gekauft', 'soulsites-learndash' ),
                'type' => \Elementor\Controls_Manager::TEXT,
                'default' => '',
                'description' => esc_html__( 'Leer lassen um nichts anzuzeigen', 'soulsites-learndash' ),
            ]
        );
    }

    /**
     * Render tag
     */
    public function render() {
        try {
            $course_id = get_the_ID();

            // Check if it's a course
            if ( ! $course_id || get_post_type( $course_id ) !== 'sfwd-courses' ) {
                return;
            }

            $settings = $this->get_settings();

            // Check if user is already enrolled
            if ( is_user_logged_in() && function_exists( 'sfwd_lms_has_access' ) ) {
                $user_id = get_current_user_id();
                if ( sfwd_lms_has_access( $course_id, $user_id ) ) {
                    echo esc_html( $settings['enrolled_text'] );
                    return;
                }
            }

            // Check if LearnDash function exists
            if ( ! function_exists( 'learndash_get_setting' ) ) {
                echo esc_html( $settings['free_text'] );
                return;
            }

            // Get course price
            $course_price_type = learndash_get_setting( $course_id, 'course_price_type' );

            if ( $course_price_type === 'open' || $course_price_type === 'free' ) {
                echo esc_html( $settings['free_text'] );
                return;
            }

            $course_price = learndash_get_setting( $course_id, 'course_price' );

            if ( empty( $course_price ) ) {
                echo esc_html( $settings['free_text'] );
                return;
            }

            // Format price
            $price_numeric  = number_format( (float) $course_price, 2, ',', '.' );
            $price_formatted = $price_numeric;

            if ( $settings['show_currency'] === 'yes' && function_exists( 'learndash_get_currency_symbol' ) ) {
                $currency_symbol  = learndash_get_currency_symbol();
                $price_formatted  = $currency_symbol . ' ' . $price_numeric;
            }

            echo esc_html( $price_formatted );
        } catch ( \Exception $e ) {
            // Bei Fehler nichts ausgeben
            return;
        }
    }
}
