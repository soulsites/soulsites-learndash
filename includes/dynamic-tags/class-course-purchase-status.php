<?php
namespace SoulSites\Dynamic_Tags;

use Elementor\Core\DynamicTags\Tag;
use Elementor\Modules\DynamicTags\Module as TagsModule;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Course Purchase Status Dynamic Tag
 * Zeigt an, ob der Kurs gekauft wurde oder nicht
 */
class Course_Purchase_Status extends Tag {

    /**
     * Get tag name
     */
    public function get_name() {
        return 'course-purchase-status';
    }

    /**
     * Get tag title
     */
    public function get_title() {
        return esc_html__( 'Kurs Kaufstatus', 'soulsites-learndash' );
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
                'label' => esc_html__( 'Text wenn gekauft', 'soulsites-learndash' ),
                'type' => \Elementor\Controls_Manager::TEXT,
                'default' => esc_html__( 'Bereits gekauft', 'soulsites-learndash' ),
            ]
        );

        $this->add_control(
            'not_enrolled_text',
            [
                'label' => esc_html__( 'Text wenn nicht gekauft', 'soulsites-learndash' ),
                'type' => \Elementor\Controls_Manager::TEXT,
                'default' => esc_html__( 'Noch nicht gekauft', 'soulsites-learndash' ),
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
        $enrolled_text = $settings['enrolled_text'];
        $not_enrolled_text = $settings['not_enrolled_text'];

        // Check if user is logged in and enrolled
        if ( is_user_logged_in() ) {
            $user_id = get_current_user_id();
            $has_access = sfwd_lms_has_access( $course_id, $user_id );

            echo $has_access ? esc_html( $enrolled_text ) : esc_html( $not_enrolled_text );
        } else {
            echo esc_html( $not_enrolled_text );
        }
    }
}
