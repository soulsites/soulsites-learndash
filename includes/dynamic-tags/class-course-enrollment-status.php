<?php
namespace SoulSites\Dynamic_Tags;

use Elementor\Core\DynamicTags\Tag;
use Elementor\Modules\DynamicTags\Module as TagsModule;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Course Enrollment Status Dynamic Tag
 * Zeigt den Einschreibungsstatus an
 */
class Course_Enrollment_Status extends Tag {

    /**
     * Get tag name
     */
    public function get_name() {
        return 'course-enrollment-status';
    }

    /**
     * Get tag title
     */
    public function get_title() {
        return esc_html__( 'Kurs Einschreibungsstatus', 'soulsites-learndash' );
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
            'enrolled_text',
            [
                'label' => esc_html__( 'Text wenn eingeschrieben', 'soulsites-learndash' ),
                'type' => \Elementor\Controls_Manager::TEXT,
                'default' => esc_html__( 'Eingeschrieben', 'soulsites-learndash' ),
            ]
        );

        $this->add_control(
            'not_enrolled_text',
            [
                'label' => esc_html__( 'Text wenn nicht eingeschrieben', 'soulsites-learndash' ),
                'type' => \Elementor\Controls_Manager::TEXT,
                'default' => esc_html__( 'Nicht eingeschrieben', 'soulsites-learndash' ),
            ]
        );

        $this->add_control(
            'not_logged_in_text',
            [
                'label' => esc_html__( 'Text wenn nicht eingeloggt', 'soulsites-learndash' ),
                'type' => \Elementor\Controls_Manager::TEXT,
                'default' => esc_html__( 'Bitte einloggen', 'soulsites-learndash' ),
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

            if ( ! is_user_logged_in() ) {
                echo esc_html( $settings['not_logged_in_text'] );
                return;
            }

            $user_id = get_current_user_id();

            // Check if LearnDash function exists
            if ( ! function_exists( 'sfwd_lms_has_access' ) ) {
                echo esc_html( $settings['not_enrolled_text'] );
                return;
            }

            $has_access = sfwd_lms_has_access( $course_id, $user_id );

            echo $has_access ? esc_html( $settings['enrolled_text'] ) : esc_html( $settings['not_enrolled_text'] );
        } catch ( \Exception $e ) {
            // Bei Fehler nichts ausgeben
            return;
        }
    }
}
