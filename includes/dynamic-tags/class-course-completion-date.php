<?php
namespace SoulSites\Dynamic_Tags;

use Elementor\Core\DynamicTags\Tag;
use Elementor\Modules\DynamicTags\Module as TagsModule;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Course Completion Date Dynamic Tag
 * Zeigt das Abschlussdatum des Kurses an
 */
class Course_Completion_Date extends Tag {

    /**
     * Get tag name
     */
    public function get_name() {
        return 'course-completion-date';
    }

    /**
     * Get tag title
     */
    public function get_title() {
        return esc_html__( 'Kurs Abschlussdatum', 'soulsites-learndash' );
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
            'date_format',
            [
                'label' => esc_html__( 'Datumsformat', 'soulsites-learndash' ),
                'type' => \Elementor\Controls_Manager::SELECT,
                'options' => [
                    'd.m.Y' => esc_html__( 'TT.MM.JJJJ (z.B. 15.01.2024)', 'soulsites-learndash' ),
                    'd. F Y' => esc_html__( 'TT. Monat JJJJ (z.B. 15. Januar 2024)', 'soulsites-learndash' ),
                    'j. F Y' => esc_html__( 'T. Monat JJJJ (z.B. 15. Januar 2024)', 'soulsites-learndash' ),
                    'F j, Y' => esc_html__( 'Monat T, JJJJ (z.B. January 15, 2024)', 'soulsites-learndash' ),
                    'Y-m-d' => esc_html__( 'JJJJ-MM-TT (z.B. 2024-01-15)', 'soulsites-learndash' ),
                ],
                'default' => 'd.m.Y',
            ]
        );

        $this->add_control(
            'not_completed_text',
            [
                'label' => esc_html__( 'Text wenn nicht abgeschlossen', 'soulsites-learndash' ),
                'type' => \Elementor\Controls_Manager::TEXT,
                'default' => esc_html__( 'Noch nicht abgeschlossen', 'soulsites-learndash' ),
            ]
        );

        $this->add_control(
            'not_enrolled_text',
            [
                'label' => esc_html__( 'Text wenn nicht eingeschrieben', 'soulsites-learndash' ),
                'type' => \Elementor\Controls_Manager::TEXT,
                'default' => '',
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
            return;
        }

        $user_id = get_current_user_id();

        // Check if user has access
        if ( ! sfwd_lms_has_access( $course_id, $user_id ) ) {
            echo esc_html( $settings['not_enrolled_text'] );
            return;
        }

        // Get completion timestamp
        $completed = learndash_user_get_course_completed_date( $user_id, $course_id );

        if ( empty( $completed ) ) {
            echo esc_html( $settings['not_completed_text'] );
            return;
        }

        // Format date
        $date_format = $settings['date_format'];
        echo esc_html( date_i18n( $date_format, $completed ) );
    }
}
