<?php
namespace SoulSites\Dynamic_Tags;

use Elementor\Core\DynamicTags\Tag;
use Elementor\Modules\DynamicTags\Module as TagsModule;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Course Progress Dynamic Tag
 * Zeigt den Kursfortschritt in Prozent an
 */
class Course_Progress extends Tag {

    /**
     * Get tag name
     */
    public function get_name() {
        return 'course-progress';
    }

    /**
     * Get tag title
     */
    public function get_title() {
        return esc_html__( 'Kurs Fortschritt', 'soulsites-learndash' );
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
        return [ TagsModule::TEXT_CATEGORY, TagsModule::NUMBER_CATEGORY ];
    }

    /**
     * Register controls
     */
    protected function register_controls() {
        $this->add_control(
            'show_percentage',
            [
                'label' => esc_html__( 'Prozentzeichen anzeigen', 'soulsites-learndash' ),
                'type' => \Elementor\Controls_Manager::SWITCHER,
                'label_on' => esc_html__( 'Ja', 'soulsites-learndash' ),
                'label_off' => esc_html__( 'Nein', 'soulsites-learndash' ),
                'return_value' => 'yes',
                'default' => 'yes',
            ]
        );

        $this->add_control(
            'not_enrolled_text',
            [
                'label' => esc_html__( 'Text wenn nicht eingeschrieben', 'soulsites-learndash' ),
                'type' => \Elementor\Controls_Manager::TEXT,
                'default' => '0',
            ]
        );
    }

    /**
     * Render tag
     */
    public function render() {
        $course_id = get_the_ID();

        // Check if it's a course
        if ( get_post_type( $course_id ) !== 'sfwd-courses' ) {
            return;
        }

        $settings = $this->get_settings();

        if ( ! is_user_logged_in() ) {
            echo esc_html( $settings['not_enrolled_text'] );
            if ( $settings['show_percentage'] === 'yes' ) {
                echo '%';
            }
            return;
        }

        $user_id = get_current_user_id();

        // Check if user has access
        if ( ! sfwd_lms_has_access( $course_id, $user_id ) ) {
            echo esc_html( $settings['not_enrolled_text'] );
            if ( $settings['show_percentage'] === 'yes' ) {
                echo '%';
            }
            return;
        }

        // Get course progress
        $progress_percentage = learndash_course_progress( [
            'user_id'   => $user_id,
            'course_id' => $course_id,
            'array'     => true
        ] );

        $percentage = isset( $progress_percentage['percentage'] ) ? intval( $progress_percentage['percentage'] ) : 0;

        echo esc_html( $percentage );

        if ( $settings['show_percentage'] === 'yes' ) {
            echo '%';
        }
    }
}
