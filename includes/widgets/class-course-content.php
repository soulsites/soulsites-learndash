<?php
namespace SoulSites\Widgets;

use Elementor\Widget_Base;
use Elementor\Controls_Manager;
use Elementor\Group_Control_Typography;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Course Content Widget
 * Gibt nur den reinen Beitragsinhalt des Kurses aus,
 * ohne den LearnDash Fortschrittsbalken und die Lektionsliste
 */
class Course_Content extends Widget_Base {

    /**
     * Get widget name
     */
    public function get_name() {
        return 'soulsites-course-content';
    }

    /**
     * Get widget title
     */
    public function get_title() {
        return esc_html__( 'Kurs Inhalt (Nur Text)', 'soulsites-learndash' );
    }

    /**
     * Get widget icon
     */
    public function get_icon() {
        return 'eicon-post-content';
    }

    /**
     * Get widget categories
     */
    public function get_categories() {
        return [ 'theme-elements' ];
    }

    /**
     * Get widget keywords
     */
    public function get_keywords() {
        return [ 'learndash', 'kurs', 'inhalt', 'content', 'text' ];
    }

    /**
     * Register widget controls
     */
    protected function register_controls() {

        $this->start_controls_section(
            'section_content',
            [
                'label' => esc_html__( 'Inhalt', 'soulsites-learndash' ),
                'tab'   => Controls_Manager::TAB_CONTENT,
            ]
        );

        $this->add_control(
            'content_info',
            [
                'type'            => Controls_Manager::RAW_HTML,
                'raw'             => esc_html__( 'Dieses Widget gibt nur den reinen Textinhalt des Kurses aus - ohne den LearnDash Fortschrittsbalken und die Lektionsliste.', 'soulsites-learndash' ),
                'content_classes' => 'elementor-panel-alert elementor-panel-alert-info',
            ]
        );

        $this->add_control(
            'apply_the_content_filters',
            [
                'label'        => esc_html__( 'WordPress Content-Filter anwenden', 'soulsites-learndash' ),
                'description'  => esc_html__( 'Wendet Standard-WordPress-Filter wie wpautop und Shortcodes an, aber blockiert LearnDash-Filter.', 'soulsites-learndash' ),
                'type'         => Controls_Manager::SWITCHER,
                'label_on'     => esc_html__( 'Ja', 'soulsites-learndash' ),
                'label_off'    => esc_html__( 'Nein', 'soulsites-learndash' ),
                'return_value' => 'yes',
                'default'      => 'yes',
            ]
        );

        $this->end_controls_section();

        // Style Section
        $this->start_controls_section(
            'section_style',
            [
                'label' => esc_html__( 'Inhalt', 'soulsites-learndash' ),
                'tab'   => Controls_Manager::TAB_STYLE,
            ]
        );

        $this->add_control(
            'text_color',
            [
                'label'     => esc_html__( 'Textfarbe', 'soulsites-learndash' ),
                'type'      => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .soulsites-course-content' => 'color: {{VALUE}};',
                ],
            ]
        );

        $this->add_group_control(
            Group_Control_Typography::get_type(),
            [
                'name'     => 'content_typography',
                'label'    => esc_html__( 'Typografie', 'soulsites-learndash' ),
                'selector' => '{{WRAPPER}} .soulsites-course-content',
            ]
        );

        $this->add_responsive_control(
            'text_align',
            [
                'label'     => esc_html__( 'Textausrichtung', 'soulsites-learndash' ),
                'type'      => Controls_Manager::CHOOSE,
                'options'   => [
                    'left'    => [
                        'title' => esc_html__( 'Links', 'soulsites-learndash' ),
                        'icon'  => 'eicon-text-align-left',
                    ],
                    'center'  => [
                        'title' => esc_html__( 'Zentriert', 'soulsites-learndash' ),
                        'icon'  => 'eicon-text-align-center',
                    ],
                    'right'   => [
                        'title' => esc_html__( 'Rechts', 'soulsites-learndash' ),
                        'icon'  => 'eicon-text-align-right',
                    ],
                    'justify' => [
                        'title' => esc_html__( 'Blocksatz', 'soulsites-learndash' ),
                        'icon'  => 'eicon-text-align-justify',
                    ],
                ],
                'selectors' => [
                    '{{WRAPPER}} .soulsites-course-content' => 'text-align: {{VALUE}};',
                ],
            ]
        );

        $this->end_controls_section();
    }

    /**
     * Render widget output
     */
    protected function render() {
        try {
            $post_id = get_the_ID();

            if ( ! $post_id ) {
                return;
            }

            $post = get_post( $post_id );

            if ( ! $post ) {
                return;
            }

            $settings = $this->get_settings_for_display();
            $content  = $post->post_content;

            if ( empty( $content ) ) {
                return;
            }

            if ( $settings['apply_the_content_filters'] === 'yes' ) {
                // Temporarily remove LearnDash content filter to get pure content
                $this->remove_learndash_content_filters();

                // Apply standard WordPress content filters
                $content = apply_filters( 'the_content', $content );

                // Restore LearnDash content filters
                $this->restore_learndash_content_filters();
            } else {
                // Minimal processing: just convert line breaks
                $content = wpautop( $content );
                $content = do_shortcode( $content );
            }

            echo '<div class="soulsites-course-content">';
            echo $content; // Already processed by the_content or wpautop
            echo '</div>';

        } catch ( \Exception $e ) {
            return;
        }
    }

    /**
     * Store removed filters for restoration
     *
     * @var array
     */
    private $removed_filters = [];

    /**
     * Remove LearnDash filters from the_content
     */
    private function remove_learndash_content_filters() {
        global $wp_filter;

        $this->removed_filters = [];

        if ( ! isset( $wp_filter['the_content'] ) ) {
            return;
        }

        // Iterate through all priorities
        foreach ( $wp_filter['the_content']->callbacks as $priority => $callbacks ) {
            foreach ( $callbacks as $key => $callback ) {
                $remove = false;

                if ( is_array( $callback['function'] ) && is_object( $callback['function'][0] ) ) {
                    $class_name = get_class( $callback['function'][0] );
                    // Remove any LearnDash related filters
                    if ( stripos( $class_name, 'learndash' ) !== false
                        || stripos( $class_name, 'sfwd' ) !== false
                        || stripos( $class_name, 'ld_' ) !== false ) {
                        $remove = true;
                    }
                } elseif ( is_string( $callback['function'] ) ) {
                    // Remove any LearnDash related function filters
                    if ( stripos( $callback['function'], 'learndash' ) !== false
                        || stripos( $callback['function'], 'sfwd' ) !== false
                        || stripos( $callback['function'], 'ld_' ) !== false ) {
                        $remove = true;
                    }
                }

                if ( $remove ) {
                    $this->removed_filters[] = [
                        'priority' => $priority,
                        'key'      => $key,
                        'callback' => $callback,
                    ];
                    unset( $wp_filter['the_content']->callbacks[ $priority ][ $key ] );
                }
            }
        }
    }

    /**
     * Restore previously removed LearnDash filters
     */
    private function restore_learndash_content_filters() {
        global $wp_filter;

        if ( empty( $this->removed_filters ) || ! isset( $wp_filter['the_content'] ) ) {
            return;
        }

        foreach ( $this->removed_filters as $filter ) {
            $wp_filter['the_content']->callbacks[ $filter['priority'] ][ $filter['key'] ] = $filter['callback'];
        }

        $this->removed_filters = [];
    }
}
