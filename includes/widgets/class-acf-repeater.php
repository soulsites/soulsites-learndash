<?php
namespace SoulSites\Widgets;

use Elementor\Widget_Base;
use Elementor\Controls_Manager;
use Elementor\Group_Control_Typography;
use Elementor\Group_Control_Border;
use Elementor\Group_Control_Box_Shadow;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * ACF Repeater Widget
 *
 * Liest ein ACF-Repeater-Feld aus und zeigt dessen Einträge als Liste an.
 * Titel-, Beschreibungs- und Link-Feldschlüssel sind frei konfigurierbar.
 */
class ACF_Repeater extends Widget_Base {

	public function get_name() {
		return 'soulsites-acf-repeater';
	}

	public function get_title() {
		return esc_html__( 'ACF Repeater Liste', 'soulsites-learndash' );
	}

	public function get_icon() {
		return 'eicon-post-list';
	}

	public function get_categories() {
		return [ 'theme-elements' ];
	}

	public function get_keywords() {
		return [ 'acf', 'repeater', 'lektion', 'liste', 'felder', 'custom fields' ];
	}

	protected function register_controls() {

		// =====================================================================
		// INHALT – Repeater-Feld
		// =====================================================================
		$this->start_controls_section(
			'section_repeater',
			[
				'label' => esc_html__( 'Repeater-Feld', 'soulsites-learndash' ),
				'tab'   => Controls_Manager::TAB_CONTENT,
			]
		);

		$this->add_control(
			'acf_notice',
			[
				'type'            => Controls_Manager::RAW_HTML,
				'raw'             => esc_html__( 'Dieses Widget benötigt das Plugin "Advanced Custom Fields" (ACF) und ein Repeater-Feld am aktuellen Beitrag.', 'soulsites-learndash' ),
				'content_classes' => 'elementor-panel-alert elementor-panel-alert-info',
			]
		);

		$this->add_control(
			'repeater_field_name',
			[
				'label'       => esc_html__( 'Repeater-Feldname (ACF-Schlüssel)', 'soulsites-learndash' ),
				'description' => esc_html__( 'Der Name / Schlüssel des ACF-Repeater-Feldes, z. B. "lektionen".', 'soulsites-learndash' ),
				'type'        => Controls_Manager::TEXT,
				'default'     => 'lektionen',
				'placeholder' => 'lektionen',
				'ai'          => [ 'active' => false ],
			]
		);

		$this->add_control(
			'post_id_mode',
			[
				'label'   => esc_html__( 'Beitrag', 'soulsites-learndash' ),
				'type'    => Controls_Manager::SELECT,
				'options' => [
					'current' => esc_html__( 'Aktueller Beitrag', 'soulsites-learndash' ),
					'custom'  => esc_html__( 'Bestimmte Beitrags-ID', 'soulsites-learndash' ),
				],
				'default' => 'current',
			]
		);

		$this->add_control(
			'post_id',
			[
				'label'       => esc_html__( 'Beitrags-ID', 'soulsites-learndash' ),
				'type'        => Controls_Manager::NUMBER,
				'min'         => 1,
				'placeholder' => '42',
				'condition'   => [
					'post_id_mode' => 'custom',
				],
			]
		);

		$this->end_controls_section();

		// =====================================================================
		// INHALT – Felder konfigurieren
		// =====================================================================
		$this->start_controls_section(
			'section_fields',
			[
				'label' => esc_html__( 'Felder konfigurieren', 'soulsites-learndash' ),
				'tab'   => Controls_Manager::TAB_CONTENT,
			]
		);

		// --- Titel ---
		$this->add_control(
			'show_title',
			[
				'label'        => esc_html__( 'Titel anzeigen', 'soulsites-learndash' ),
				'type'         => Controls_Manager::SWITCHER,
				'label_on'     => esc_html__( 'Ja', 'soulsites-learndash' ),
				'label_off'    => esc_html__( 'Nein', 'soulsites-learndash' ),
				'return_value' => 'yes',
				'default'      => 'yes',
			]
		);

		$this->add_control(
			'title_field',
			[
				'label'       => esc_html__( 'Titel-Feldschlüssel', 'soulsites-learndash' ),
				'type'        => Controls_Manager::TEXT,
				'default'     => 'titel',
				'placeholder' => 'titel',
				'ai'          => [ 'active' => false ],
				'condition'   => [ 'show_title' => 'yes' ],
			]
		);

		$this->add_control(
			'title_tag',
			[
				'label'     => esc_html__( 'Titel HTML-Tag', 'soulsites-learndash' ),
				'type'      => Controls_Manager::SELECT,
				'options'   => [
					'h2'  => 'H2',
					'h3'  => 'H3',
					'h4'  => 'H4',
					'h5'  => 'H5',
					'h6'  => 'H6',
					'p'   => 'p',
					'div' => 'div',
				],
				'default'   => 'h3',
				'condition' => [ 'show_title' => 'yes' ],
			]
		);

		$this->add_control(
			'title_divider',
			[
				'type'      => Controls_Manager::DIVIDER,
				'condition' => [ 'show_title' => 'yes' ],
			]
		);

		// --- Beschreibung ---
		$this->add_control(
			'show_description',
			[
				'label'        => esc_html__( 'Beschreibung anzeigen', 'soulsites-learndash' ),
				'type'         => Controls_Manager::SWITCHER,
				'label_on'     => esc_html__( 'Ja', 'soulsites-learndash' ),
				'label_off'    => esc_html__( 'Nein', 'soulsites-learndash' ),
				'return_value' => 'yes',
				'default'      => 'yes',
			]
		);

		$this->add_control(
			'description_field',
			[
				'label'       => esc_html__( 'Beschreibung-Feldschlüssel', 'soulsites-learndash' ),
				'type'        => Controls_Manager::TEXT,
				'default'     => 'beschreibung',
				'placeholder' => 'beschreibung',
				'ai'          => [ 'active' => false ],
				'condition'   => [ 'show_description' => 'yes' ],
			]
		);

		$this->add_control(
			'description_divider',
			[
				'type'      => Controls_Manager::DIVIDER,
				'condition' => [ 'show_description' => 'yes' ],
			]
		);

		// --- Link ---
		$this->add_control(
			'show_link',
			[
				'label'        => esc_html__( 'Link anzeigen', 'soulsites-learndash' ),
				'type'         => Controls_Manager::SWITCHER,
				'label_on'     => esc_html__( 'Ja', 'soulsites-learndash' ),
				'label_off'    => esc_html__( 'Nein', 'soulsites-learndash' ),
				'return_value' => 'yes',
				'default'      => 'yes',
			]
		);

		$this->add_control(
			'link_field',
			[
				'label'       => esc_html__( 'Link-Feldschlüssel (URL)', 'soulsites-learndash' ),
				'type'        => Controls_Manager::TEXT,
				'default'     => 'zoom_link',
				'placeholder' => 'zoom_link',
				'ai'          => [ 'active' => false ],
				'condition'   => [ 'show_link' => 'yes' ],
			]
		);

		$this->add_control(
			'link_text',
			[
				'label'       => esc_html__( 'Link-Beschriftung', 'soulsites-learndash' ),
				'type'        => Controls_Manager::TEXT,
				'default'     => 'Zoom',
				'placeholder' => 'Zoom',
				'condition'   => [ 'show_link' => 'yes' ],
			]
		);

		$this->add_control(
			'link_target',
			[
				'label'     => esc_html__( 'Link öffnen in', 'soulsites-learndash' ),
				'type'      => Controls_Manager::SELECT,
				'options'   => [
					'_blank' => esc_html__( 'Neuem Tab', 'soulsites-learndash' ),
					'_self'  => esc_html__( 'Gleichem Tab', 'soulsites-learndash' ),
				],
				'default'   => '_blank',
				'condition' => [ 'show_link' => 'yes' ],
			]
		);

		$this->end_controls_section();

		// =====================================================================
		// STYLE – Container / Eintrag
		// =====================================================================
		$this->start_controls_section(
			'section_style_item',
			[
				'label' => esc_html__( 'Einträge', 'soulsites-learndash' ),
				'tab'   => Controls_Manager::TAB_STYLE,
			]
		);

		$this->add_responsive_control(
			'item_gap',
			[
				'label'      => esc_html__( 'Abstand zwischen Einträgen', 'soulsites-learndash' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => [ 'px', 'em', 'rem' ],
				'range'      => [ 'px' => [ 'min' => 0, 'max' => 100 ] ],
				'default'    => [ 'unit' => 'px', 'size' => 16 ],
				'selectors'  => [
					'{{WRAPPER}} .soulsites-acf-repeater' => 'gap: {{SIZE}}{{UNIT}};',
				],
			]
		);

		$this->add_responsive_control(
			'item_padding',
			[
				'label'      => esc_html__( 'Innenabstand (Padding)', 'soulsites-learndash' ),
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => [ 'px', 'em', 'rem', '%' ],
				'selectors'  => [
					'{{WRAPPER}} .soulsites-acf-repeater__item' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				],
			]
		);

		$this->add_control(
			'item_background',
			[
				'label'     => esc_html__( 'Hintergrundfarbe', 'soulsites-learndash' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => [
					'{{WRAPPER}} .soulsites-acf-repeater__item' => 'background-color: {{VALUE}};',
				],
			]
		);

		$this->add_group_control(
			Group_Control_Border::get_type(),
			[
				'name'     => 'item_border',
				'selector' => '{{WRAPPER}} .soulsites-acf-repeater__item',
			]
		);

		$this->add_responsive_control(
			'item_border_radius',
			[
				'label'      => esc_html__( 'Rahmen-Radius', 'soulsites-learndash' ),
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => [ 'px', '%', 'em' ],
				'selectors'  => [
					'{{WRAPPER}} .soulsites-acf-repeater__item' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				],
			]
		);

		$this->add_group_control(
			Group_Control_Box_Shadow::get_type(),
			[
				'name'     => 'item_box_shadow',
				'selector' => '{{WRAPPER}} .soulsites-acf-repeater__item',
			]
		);

		$this->end_controls_section();

		// =====================================================================
		// STYLE – Titel
		// =====================================================================
		$this->start_controls_section(
			'section_style_title',
			[
				'label'     => esc_html__( 'Titel', 'soulsites-learndash' ),
				'tab'       => Controls_Manager::TAB_STYLE,
				'condition' => [ 'show_title' => 'yes' ],
			]
		);

		$this->add_control(
			'title_color',
			[
				'label'     => esc_html__( 'Farbe', 'soulsites-learndash' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => [
					'{{WRAPPER}} .soulsites-acf-repeater__title' => 'color: {{VALUE}};',
				],
			]
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			[
				'name'     => 'title_typography',
				'selector' => '{{WRAPPER}} .soulsites-acf-repeater__title',
			]
		);

		$this->add_responsive_control(
			'title_margin',
			[
				'label'      => esc_html__( 'Außenabstand (Margin)', 'soulsites-learndash' ),
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => [ 'px', 'em', 'rem' ],
				'selectors'  => [
					'{{WRAPPER}} .soulsites-acf-repeater__title' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				],
			]
		);

		$this->end_controls_section();

		// =====================================================================
		// STYLE – Beschreibung
		// =====================================================================
		$this->start_controls_section(
			'section_style_description',
			[
				'label'     => esc_html__( 'Beschreibung', 'soulsites-learndash' ),
				'tab'       => Controls_Manager::TAB_STYLE,
				'condition' => [ 'show_description' => 'yes' ],
			]
		);

		$this->add_control(
			'description_color',
			[
				'label'     => esc_html__( 'Farbe', 'soulsites-learndash' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => [
					'{{WRAPPER}} .soulsites-acf-repeater__description' => 'color: {{VALUE}};',
				],
			]
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			[
				'name'     => 'description_typography',
				'selector' => '{{WRAPPER}} .soulsites-acf-repeater__description',
			]
		);

		$this->add_responsive_control(
			'description_margin',
			[
				'label'      => esc_html__( 'Außenabstand (Margin)', 'soulsites-learndash' ),
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => [ 'px', 'em', 'rem' ],
				'selectors'  => [
					'{{WRAPPER}} .soulsites-acf-repeater__description' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				],
			]
		);

		$this->end_controls_section();

		// =====================================================================
		// STYLE – Link
		// =====================================================================
		$this->start_controls_section(
			'section_style_link',
			[
				'label'     => esc_html__( 'Link', 'soulsites-learndash' ),
				'tab'       => Controls_Manager::TAB_STYLE,
				'condition' => [ 'show_link' => 'yes' ],
			]
		);

		$this->add_control(
			'link_color',
			[
				'label'     => esc_html__( 'Textfarbe', 'soulsites-learndash' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => [
					'{{WRAPPER}} .soulsites-acf-repeater__link' => 'color: {{VALUE}};',
				],
			]
		);

		$this->add_control(
			'link_color_hover',
			[
				'label'     => esc_html__( 'Textfarbe (Hover)', 'soulsites-learndash' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => [
					'{{WRAPPER}} .soulsites-acf-repeater__link:hover' => 'color: {{VALUE}};',
				],
			]
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			[
				'name'     => 'link_typography',
				'selector' => '{{WRAPPER}} .soulsites-acf-repeater__link',
			]
		);

		$this->add_responsive_control(
			'link_padding',
			[
				'label'      => esc_html__( 'Innenabstand (Padding)', 'soulsites-learndash' ),
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => [ 'px', 'em', 'rem' ],
				'selectors'  => [
					'{{WRAPPER}} .soulsites-acf-repeater__link' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				],
			]
		);

		$this->add_control(
			'link_background',
			[
				'label'     => esc_html__( 'Hintergrundfarbe', 'soulsites-learndash' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => [
					'{{WRAPPER}} .soulsites-acf-repeater__link' => 'background-color: {{VALUE}};',
				],
			]
		);

		$this->add_control(
			'link_background_hover',
			[
				'label'     => esc_html__( 'Hintergrundfarbe (Hover)', 'soulsites-learndash' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => [
					'{{WRAPPER}} .soulsites-acf-repeater__link:hover' => 'background-color: {{VALUE}};',
				],
			]
		);

		$this->add_responsive_control(
			'link_border_radius',
			[
				'label'      => esc_html__( 'Rahmen-Radius', 'soulsites-learndash' ),
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => [ 'px', '%', 'em' ],
				'selectors'  => [
					'{{WRAPPER}} .soulsites-acf-repeater__link' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				],
			]
		);

		$this->add_responsive_control(
			'link_margin',
			[
				'label'      => esc_html__( 'Außenabstand (Margin)', 'soulsites-learndash' ),
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => [ 'px', 'em', 'rem' ],
				'selectors'  => [
					'{{WRAPPER}} .soulsites-acf-repeater__link' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				],
			]
		);

		$this->end_controls_section();
	}

	protected function render() {
		if ( ! function_exists( 'have_rows' ) ) {
			if ( \Elementor\Plugin::$instance->editor->is_edit_mode() ) {
				echo '<p>' . esc_html__( 'ACF ist nicht aktiv. Dieses Widget benötigt "Advanced Custom Fields".', 'soulsites-learndash' ) . '</p>';
			}
			return;
		}

		$settings     = $this->get_settings_for_display();
		$field_name   = sanitize_key( $settings['repeater_field_name'] );
		$post_id_mode = $settings['post_id_mode'];
		$post_id      = ( $post_id_mode === 'custom' && ! empty( $settings['post_id'] ) )
			? absint( $settings['post_id'] )
			: get_the_ID();

		if ( empty( $field_name ) ) {
			if ( \Elementor\Plugin::$instance->editor->is_edit_mode() ) {
				echo '<p>' . esc_html__( 'Bitte einen Repeater-Feldnamen eingeben.', 'soulsites-learndash' ) . '</p>';
			}
			return;
		}

		if ( ! have_rows( $field_name, $post_id ) ) {
			if ( \Elementor\Plugin::$instance->editor->is_edit_mode() ) {
				echo '<p>' . sprintf(
					/* translators: %s: ACF field name */
					esc_html__( 'Keine Einträge im Repeater-Feld "%s" gefunden.', 'soulsites-learndash' ),
					esc_html( $field_name )
				) . '</p>';
			}
			return;
		}

		$show_title       = $settings['show_title'] === 'yes';
		$show_description = $settings['show_description'] === 'yes';
		$show_link        = $settings['show_link'] === 'yes';

		$title_field       = $show_title ? sanitize_key( $settings['title_field'] ) : '';
		$title_tag         = $show_title ? $this->get_validated_tag( $settings['title_tag'] ) : 'h3';
		$description_field = $show_description ? sanitize_key( $settings['description_field'] ) : '';
		$link_field        = $show_link ? sanitize_key( $settings['link_field'] ) : '';
		$link_text         = $show_link ? esc_html( $settings['link_text'] ) : '';
		$link_target       = ( $settings['link_target'] === '_blank' ) ? '_blank' : '_self';
		$link_rel          = ( $link_target === '_blank' ) ? ' rel="noopener noreferrer"' : '';

		echo '<div class="soulsites-acf-repeater">';

		while ( have_rows( $field_name, $post_id ) ) {
			the_row();

			echo '<div class="soulsites-acf-repeater__item">';

			if ( $show_title && $title_field ) {
				$title_value = get_sub_field( $title_field );
				if ( $title_value ) {
					echo '<' . esc_attr( $title_tag ) . ' class="soulsites-acf-repeater__title">'
						. esc_html( $title_value )
						. '</' . esc_attr( $title_tag ) . '>';
				}
			}

			if ( $show_description && $description_field ) {
				$description_value = get_sub_field( $description_field );
				if ( $description_value ) {
					echo '<p class="soulsites-acf-repeater__description">'
						. wp_kses_post( $description_value )
						. '</p>';
				}
			}

			if ( $show_link && $link_field ) {
				$url = get_sub_field( $link_field );
				if ( $url ) {
					echo '<a class="soulsites-acf-repeater__link"'
						. ' href="' . esc_url( $url ) . '"'
						. ' target="' . esc_attr( $link_target ) . '"'
						. $link_rel // already escaped above
						. '>'
						. $link_text
						. '</a>';
				}
			}

			echo '</div>';
		}

		echo '</div>';
	}

	/**
	 * Validates the title tag against an allowlist.
	 *
	 * @param string $tag
	 * @return string
	 */
	private function get_validated_tag( $tag ) {
		$allowed = [ 'h2', 'h3', 'h4', 'h5', 'h6', 'p', 'div' ];
		return in_array( $tag, $allowed, true ) ? $tag : 'h3';
	}
}
