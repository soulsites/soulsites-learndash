<?php
namespace SoulSites\Widgets;

use Elementor\Widget_Base;
use Elementor\Controls_Manager;
use Elementor\Group_Control_Typography;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Course Progress Bar Widget
 * Zeigt eine visuelle Fortschrittsanzeige des aktuellen Kurses an
 */
class Course_Progress_Bar extends Widget_Base {

    /**
     * Get widget name
     */
    public function get_name() {
        return 'soulsites-course-progress-bar';
    }

    /**
     * Get widget title
     */
    public function get_title() {
        return esc_html__( 'Kurs Fortschrittsanzeige', 'soulsites-learndash' );
    }

    /**
     * Get widget icon
     */
    public function get_icon() {
        return 'eicon-skill-bar';
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
        return [ 'learndash', 'kurs', 'fortschritt', 'progress', 'bar' ];
    }

    /**
     * Register widget controls
     */
    protected function register_controls() {

        // Content Section
        $this->start_controls_section(
            'section_content',
            [
                'label' => esc_html__( 'Inhalt', 'soulsites-learndash' ),
                'tab'   => Controls_Manager::TAB_CONTENT,
            ]
        );

        $this->add_control(
            'show_percentage_text',
            [
                'label'        => esc_html__( 'Prozentzahl anzeigen', 'soulsites-learndash' ),
                'type'         => Controls_Manager::SWITCHER,
                'label_on'     => esc_html__( 'Ja', 'soulsites-learndash' ),
                'label_off'    => esc_html__( 'Nein', 'soulsites-learndash' ),
                'return_value' => 'yes',
                'default'      => 'yes',
            ]
        );

        $this->add_control(
            'percentage_position',
            [
                'label'     => esc_html__( 'Position der Prozentzahl', 'soulsites-learndash' ),
                'type'      => Controls_Manager::SELECT,
                'default'   => 'inside',
                'options'   => [
                    'inside'  => esc_html__( 'Im Balken', 'soulsites-learndash' ),
                    'above'   => esc_html__( 'Oberhalb', 'soulsites-learndash' ),
                    'below'   => esc_html__( 'Unterhalb', 'soulsites-learndash' ),
                ],
                'condition' => [
                    'show_percentage_text' => 'yes',
                ],
            ]
        );

        $this->add_control(
            'show_label',
            [
                'label'        => esc_html__( 'Label anzeigen', 'soulsites-learndash' ),
                'type'         => Controls_Manager::SWITCHER,
                'label_on'     => esc_html__( 'Ja', 'soulsites-learndash' ),
                'label_off'    => esc_html__( 'Nein', 'soulsites-learndash' ),
                'return_value' => 'yes',
                'default'      => '',
            ]
        );

        $this->add_control(
            'label_text',
            [
                'label'     => esc_html__( 'Label Text', 'soulsites-learndash' ),
                'type'      => Controls_Manager::TEXT,
                'default'   => esc_html__( 'Kursfortschritt', 'soulsites-learndash' ),
                'condition' => [
                    'show_label' => 'yes',
                ],
            ]
        );

        $this->add_control(
            'not_enrolled_text',
            [
                'label'   => esc_html__( 'Text wenn nicht eingeschrieben', 'soulsites-learndash' ),
                'type'    => Controls_Manager::TEXT,
                'default' => esc_html__( 'Nicht eingeschrieben', 'soulsites-learndash' ),
            ]
        );

        $this->add_control(
            'hide_not_enrolled',
            [
                'label'        => esc_html__( 'Ausblenden wenn nicht eingeschrieben', 'soulsites-learndash' ),
                'type'         => Controls_Manager::SWITCHER,
                'label_on'     => esc_html__( 'Ja', 'soulsites-learndash' ),
                'label_off'    => esc_html__( 'Nein', 'soulsites-learndash' ),
                'return_value' => 'yes',
                'default'      => '',
            ]
        );

        $this->end_controls_section();

        // Style Section - Bar
        $this->start_controls_section(
            'section_style_bar',
            [
                'label' => esc_html__( 'Fortschrittsbalken', 'soulsites-learndash' ),
                'tab'   => Controls_Manager::TAB_STYLE,
            ]
        );

        $this->add_control(
            'bar_height',
            [
                'label'      => esc_html__( 'Höhe', 'soulsites-learndash' ),
                'type'       => Controls_Manager::SLIDER,
                'size_units' => [ 'px', 'em' ],
                'range'      => [
                    'px' => [
                        'min' => 5,
                        'max' => 100,
                    ],
                ],
                'default'    => [
                    'size' => 20,
                    'unit' => 'px',
                ],
                'selectors'  => [
                    '{{WRAPPER}} .soulsites-progress-bar-wrapper' => 'height: {{SIZE}}{{UNIT}};',
                ],
            ]
        );

        $this->add_control(
            'bar_border_radius',
            [
                'label'      => esc_html__( 'Eckenradius', 'soulsites-learndash' ),
                'type'       => Controls_Manager::SLIDER,
                'size_units' => [ 'px' ],
                'range'      => [
                    'px' => [
                        'min' => 0,
                        'max' => 50,
                    ],
                ],
                'default'    => [
                    'size' => 10,
                    'unit' => 'px',
                ],
                'selectors'  => [
                    '{{WRAPPER}} .soulsites-progress-bar-wrapper' => 'border-radius: {{SIZE}}{{UNIT}};',
                    '{{WRAPPER}} .soulsites-progress-bar-fill'    => 'border-radius: {{SIZE}}{{UNIT}};',
                ],
            ]
        );

        $this->add_control(
            'bar_bg_color',
            [
                'label'     => esc_html__( 'Hintergrundfarbe', 'soulsites-learndash' ),
                'type'      => Controls_Manager::COLOR,
                'default'   => '#e0e0e0',
                'selectors' => [
                    '{{WRAPPER}} .soulsites-progress-bar-wrapper' => 'background-color: {{VALUE}};',
                ],
            ]
        );

        $this->add_control(
            'bar_fill_color',
            [
                'label'     => esc_html__( 'Fortschrittsfarbe', 'soulsites-learndash' ),
                'type'      => Controls_Manager::COLOR,
                'default'   => '#4caf50',
                'selectors' => [
                    '{{WRAPPER}} .soulsites-progress-bar-fill' => 'background-color: {{VALUE}};',
                ],
            ]
        );

        $this->end_controls_section();

        // Style Section - Text
        $this->start_controls_section(
            'section_style_text',
            [
                'label' => esc_html__( 'Text', 'soulsites-learndash' ),
                'tab'   => Controls_Manager::TAB_STYLE,
            ]
        );

        $this->add_control(
            'percentage_color',
            [
                'label'     => esc_html__( 'Prozentzahl Farbe', 'soulsites-learndash' ),
                'type'      => Controls_Manager::COLOR,
                'default'   => '#ffffff',
                'selectors' => [
                    '{{WRAPPER}} .soulsites-progress-bar-percentage' => 'color: {{VALUE}};',
                ],
                'condition' => [
                    'show_percentage_text' => 'yes',
                ],
            ]
        );

        $this->add_group_control(
            Group_Control_Typography::get_type(),
            [
                'name'      => 'percentage_typography',
                'label'     => esc_html__( 'Prozentzahl Typografie', 'soulsites-learndash' ),
                'selector'  => '{{WRAPPER}} .soulsites-progress-bar-percentage',
                'condition' => [
                    'show_percentage_text' => 'yes',
                ],
            ]
        );

        $this->add_control(
            'label_color',
            [
                'label'     => esc_html__( 'Label Farbe', 'soulsites-learndash' ),
                'type'      => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .soulsites-progress-bar-label' => 'color: {{VALUE}};',
                ],
                'condition' => [
                    'show_label' => 'yes',
                ],
                'separator' => 'before',
            ]
        );

        $this->add_group_control(
            Group_Control_Typography::get_type(),
            [
                'name'      => 'label_typography',
                'label'     => esc_html__( 'Label Typografie', 'soulsites-learndash' ),
                'selector'  => '{{WRAPPER}} .soulsites-progress-bar-label',
                'condition' => [
                    'show_label' => 'yes',
                ],
            ]
        );

        $this->add_control(
            'not_enrolled_color',
            [
                'label'     => esc_html__( 'Nicht-eingeschrieben Text Farbe', 'soulsites-learndash' ),
                'type'      => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .soulsites-progress-not-enrolled' => 'color: {{VALUE}};',
                ],
                'separator' => 'before',
            ]
        );

        $this->end_controls_section();
    }

    /**
     * Render widget output
     */
    protected function render() {
        try {
            $settings  = $this->get_settings_for_display();
            $course_id = get_the_ID();

            // Check if it's a course
            if ( ! $course_id || get_post_type( $course_id ) !== 'sfwd-courses' ) {
                if ( \Elementor\Plugin::$instance->editor->is_edit_mode() ) {
                    echo '<div class="soulsites-progress-bar-container">';
                    if ( $settings['show_label'] === 'yes' && ! empty( $settings['label_text'] ) ) {
                        echo '<div class="soulsites-progress-bar-label">' . esc_html( $settings['label_text'] ) . '</div>';
                    }
                    if ( $settings['show_percentage_text'] === 'yes' && $settings['percentage_position'] === 'above' ) {
                        echo '<div class="soulsites-progress-bar-percentage">65%</div>';
                    }
                    echo '<div class="soulsites-progress-bar-wrapper" role="progressbar" aria-valuenow="65" aria-valuemin="0" aria-valuemax="100">';
                    echo '<div class="soulsites-progress-bar-fill" style="width: 65%;">';
                    if ( $settings['show_percentage_text'] === 'yes' && $settings['percentage_position'] === 'inside' ) {
                        echo '<span class="soulsites-progress-bar-percentage">65%</span>';
                    }
                    echo '</div></div>';
                    if ( $settings['show_percentage_text'] === 'yes' && $settings['percentage_position'] === 'below' ) {
                        echo '<div class="soulsites-progress-bar-percentage">65%</div>';
                    }
                    echo '</div>';
                }
                return;
            }

            $user_id     = get_current_user_id();
            $is_enrolled = false;
            $percentage  = 0;

            if ( $user_id && function_exists( 'sfwd_lms_has_access' ) ) {
                $is_enrolled = sfwd_lms_has_access( $course_id, $user_id );
            }

            if ( $is_enrolled && function_exists( 'learndash_course_progress' ) ) {
                $progress = learndash_course_progress( [
                    'user_id'   => $user_id,
                    'course_id' => $course_id,
                    'array'     => true,
                ] );
                $percentage = isset( $progress['percentage'] ) ? intval( $progress['percentage'] ) : 0;
            }

            // Not enrolled handling
            if ( ! $is_enrolled ) {
                if ( $settings['hide_not_enrolled'] === 'yes' ) {
                    return;
                }
                echo '<div class="soulsites-progress-not-enrolled">';
                echo esc_html( $settings['not_enrolled_text'] );
                echo '</div>';
                return;
            }

            // Render progress bar
            echo '<div class="soulsites-progress-bar-container">';

            // Label
            if ( $settings['show_label'] === 'yes' && ! empty( $settings['label_text'] ) ) {
                echo '<div class="soulsites-progress-bar-label">' . esc_html( $settings['label_text'] ) . '</div>';
            }

            // Percentage above
            if ( $settings['show_percentage_text'] === 'yes' && $settings['percentage_position'] === 'above' ) {
                echo '<div class="soulsites-progress-bar-percentage">' . esc_html( $percentage ) . '%</div>';
            }

            // Bar
            echo '<div class="soulsites-progress-bar-wrapper" role="progressbar" aria-valuenow="' . esc_attr( $percentage ) . '" aria-valuemin="0" aria-valuemax="100" aria-label="' . esc_attr__( 'Kursfortschritt', 'soulsites-learndash' ) . '">';
            echo '<div class="soulsites-progress-bar-fill" style="width: ' . esc_attr( $percentage ) . '%;">';

            // Percentage inside bar
            if ( $settings['show_percentage_text'] === 'yes' && $settings['percentage_position'] === 'inside' ) {
                echo '<span class="soulsites-progress-bar-percentage">' . esc_html( $percentage ) . '%</span>';
            }

            echo '</div>'; // .soulsites-progress-bar-fill
            echo '</div>'; // .soulsites-progress-bar-wrapper

            // Percentage below
            if ( $settings['show_percentage_text'] === 'yes' && $settings['percentage_position'] === 'below' ) {
                echo '<div class="soulsites-progress-bar-percentage">' . esc_html( $percentage ) . '%</div>';
            }

            echo '</div>'; // .soulsites-progress-bar-container

        } catch ( \Exception $e ) {
            return;
        }
    }
}
