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
 * Liest ein ACF-Repeater-Feld aus und erkennt die Sub-Felder sowie deren
 * Typen automatisch via get_field_object(). Die Darstellung erfolgt
 * typgerecht (Text, Textarea, WYSIWYG, URL, Link, Bild, Datei, …).
 * Einzelne Felder können über eine Ausschlussliste übersprungen werden.
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

	// =========================================================================
	// Controls
	// =========================================================================

	protected function register_controls() {

		// ---------------------------------------------------------------------
		// INHALT – Repeater-Feld
		// ---------------------------------------------------------------------
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
				'raw'             => esc_html__( 'Die Sub-Felder und ihre Typen werden automatisch aus ACF ausgelesen. Neue Felder im Repeater werden sofort berücksichtigt.', 'soulsites-learndash' ),
				'content_classes' => 'elementor-panel-alert elementor-panel-alert-info',
			]
		);

		$this->add_control(
			'repeater_field_name',
			[
				'label'       => esc_html__( 'Repeater-Feldname (ACF-Schlüssel)', 'soulsites-learndash' ),
				'description' => esc_html__( 'Name des ACF-Repeater-Feldes, z. B. "lektionen".', 'soulsites-learndash' ),
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
				'label'     => esc_html__( 'Beitrags-ID', 'soulsites-learndash' ),
				'type'      => Controls_Manager::NUMBER,
				'min'       => 1,
				'condition' => [ 'post_id_mode' => 'custom' ],
			]
		);

		$this->end_controls_section();

		// ---------------------------------------------------------------------
		// INHALT – Anzeigeoptionen
		// ---------------------------------------------------------------------
		$this->start_controls_section(
			'section_display',
			[
				'label' => esc_html__( 'Anzeigeoptionen', 'soulsites-learndash' ),
				'tab'   => Controls_Manager::TAB_CONTENT,
			]
		);

		$this->add_control(
			'show_field_labels',
			[
				'label'        => esc_html__( 'ACF-Feld-Labels anzeigen', 'soulsites-learndash' ),
				'description'  => esc_html__( 'Zeigt die in ACF definierten Feld-Labels als Beschriftung vor jedem Wert.', 'soulsites-learndash' ),
				'type'         => Controls_Manager::SWITCHER,
				'label_on'     => esc_html__( 'Ja', 'soulsites-learndash' ),
				'label_off'    => esc_html__( 'Nein', 'soulsites-learndash' ),
				'return_value' => 'yes',
				'default'      => '',
			]
		);

		$this->add_control(
			'excluded_fields',
			[
				'label'       => esc_html__( 'Felder ausschließen', 'soulsites-learndash' ),
				'description' => esc_html__( 'Kommagetrennte Liste von Sub-Feld-Schlüsseln, die nicht ausgegeben werden sollen, z. B. "internes_feld, notiz".', 'soulsites-learndash' ),
				'type'        => Controls_Manager::TEXTAREA,
				'rows'        => 2,
				'placeholder' => 'feld_a, feld_b',
				'ai'          => [ 'active' => false ],
			]
		);

		$this->add_control(
			'link_divider',
			[ 'type' => Controls_Manager::DIVIDER ]
		);

		$this->add_control(
			'link_target',
			[
				'label'   => esc_html__( 'Links öffnen in', 'soulsites-learndash' ),
				'type'    => Controls_Manager::SELECT,
				'options' => [
					'_blank' => esc_html__( 'Neuem Tab', 'soulsites-learndash' ),
					'_self'  => esc_html__( 'Gleichem Tab', 'soulsites-learndash' ),
				],
				'default' => '_blank',
			]
		);

		$this->add_control(
			'link_fallback_text',
			[
				'label'       => esc_html__( 'Link-Beschriftung (Fallback)', 'soulsites-learndash' ),
				'description' => esc_html__( 'Text für URL-Felder ohne eigene Beschriftung. Leer = die URL selbst wird angezeigt.', 'soulsites-learndash' ),
				'type'        => Controls_Manager::TEXT,
				'placeholder' => esc_html__( 'Öffnen', 'soulsites-learndash' ),
				'ai'          => [ 'active' => false ],
			]
		);

		$this->add_control(
			'bool_divider',
			[ 'type' => Controls_Manager::DIVIDER ]
		);

		$this->add_control(
			'true_false_yes',
			[
				'label'   => esc_html__( 'Ja/Wahr-Text (true_false Felder)', 'soulsites-learndash' ),
				'type'    => Controls_Manager::TEXT,
				'default' => esc_html__( 'Ja', 'soulsites-learndash' ),
				'ai'      => [ 'active' => false ],
			]
		);

		$this->add_control(
			'true_false_no',
			[
				'label'   => esc_html__( 'Nein/Falsch-Text (true_false Felder)', 'soulsites-learndash' ),
				'type'    => Controls_Manager::TEXT,
				'default' => esc_html__( 'Nein', 'soulsites-learndash' ),
				'ai'      => [ 'active' => false ],
			]
		);

		$this->end_controls_section();

		// =====================================================================
		// STYLE – Einträge (Items)
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
		// STYLE – Feld-Labels
		// =====================================================================
		$this->start_controls_section(
			'section_style_label',
			[
				'label'     => esc_html__( 'Feld-Labels', 'soulsites-learndash' ),
				'tab'       => Controls_Manager::TAB_STYLE,
				'condition' => [ 'show_field_labels' => 'yes' ],
			]
		);

		$this->add_control(
			'label_color',
			[
				'label'     => esc_html__( 'Farbe', 'soulsites-learndash' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => [
					'{{WRAPPER}} .soulsites-acf-repeater__field-label' => 'color: {{VALUE}};',
				],
			]
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			[
				'name'     => 'label_typography',
				'selector' => '{{WRAPPER}} .soulsites-acf-repeater__field-label',
			]
		);

		$this->add_responsive_control(
			'label_margin',
			[
				'label'      => esc_html__( 'Außenabstand', 'soulsites-learndash' ),
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => [ 'px', 'em', 'rem' ],
				'selectors'  => [
					'{{WRAPPER}} .soulsites-acf-repeater__field-label' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				],
			]
		);

		$this->end_controls_section();

		// =====================================================================
		// STYLE – Textfelder
		// =====================================================================
		$this->start_controls_section(
			'section_style_text',
			[
				'label' => esc_html__( 'Textfelder', 'soulsites-learndash' ),
				'tab'   => Controls_Manager::TAB_STYLE,
			]
		);

		$this->add_control(
			'text_color',
			[
				'label'     => esc_html__( 'Farbe', 'soulsites-learndash' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => [
					'{{WRAPPER}} .soulsites-acf-repeater__field-value' => 'color: {{VALUE}};',
				],
			]
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			[
				'name'     => 'text_typography',
				'selector' => '{{WRAPPER}} .soulsites-acf-repeater__field-value',
			]
		);

		$this->add_responsive_control(
			'text_margin',
			[
				'label'      => esc_html__( 'Außenabstand', 'soulsites-learndash' ),
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => [ 'px', 'em', 'rem' ],
				'selectors'  => [
					'{{WRAPPER}} .soulsites-acf-repeater__field-value' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				],
			]
		);

		$this->end_controls_section();

		// =====================================================================
		// STYLE – Links
		// =====================================================================
		$this->start_controls_section(
			'section_style_link',
			[
				'label' => esc_html__( 'Links', 'soulsites-learndash' ),
				'tab'   => Controls_Manager::TAB_STYLE,
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
				'label'      => esc_html__( 'Innenabstand', 'soulsites-learndash' ),
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
				'label'      => esc_html__( 'Außenabstand', 'soulsites-learndash' ),
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => [ 'px', 'em', 'rem' ],
				'selectors'  => [
					'{{WRAPPER}} .soulsites-acf-repeater__link' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				],
			]
		);

		$this->end_controls_section();

		// =====================================================================
		// STYLE – Bilder
		// =====================================================================
		$this->start_controls_section(
			'section_style_image',
			[
				'label' => esc_html__( 'Bilder', 'soulsites-learndash' ),
				'tab'   => Controls_Manager::TAB_STYLE,
			]
		);

		$this->add_responsive_control(
			'image_width',
			[
				'label'      => esc_html__( 'Breite', 'soulsites-learndash' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => [ 'px', '%', 'vw' ],
				'range'      => [
					'px' => [ 'min' => 10, 'max' => 1200 ],
					'%'  => [ 'min' => 1, 'max' => 100 ],
				],
				'selectors'  => [
					'{{WRAPPER}} .soulsites-acf-repeater__image' => 'width: {{SIZE}}{{UNIT}};',
				],
			]
		);

		$this->add_responsive_control(
			'image_border_radius',
			[
				'label'      => esc_html__( 'Rahmen-Radius', 'soulsites-learndash' ),
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => [ 'px', '%', 'em' ],
				'selectors'  => [
					'{{WRAPPER}} .soulsites-acf-repeater__image' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				],
			]
		);

		$this->add_responsive_control(
			'image_margin',
			[
				'label'      => esc_html__( 'Außenabstand', 'soulsites-learndash' ),
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => [ 'px', 'em', 'rem' ],
				'selectors'  => [
					'{{WRAPPER}} .soulsites-acf-repeater__image' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				],
			]
		);

		$this->end_controls_section();
	}

	// =========================================================================
	// Render
	// =========================================================================

	protected function render() {
		if ( ! function_exists( 'have_rows' ) ) {
			$this->editor_notice( esc_html__( 'ACF ist nicht aktiv. Dieses Widget benötigt "Advanced Custom Fields".', 'soulsites-learndash' ) );
			return;
		}

		$settings   = $this->get_settings_for_display();
		$field_name = sanitize_key( $settings['repeater_field_name'] );
		$post_id    = ( $settings['post_id_mode'] === 'custom' && ! empty( $settings['post_id'] ) )
			? absint( $settings['post_id'] )
			: get_the_ID();

		if ( empty( $field_name ) ) {
			$this->editor_notice( esc_html__( 'Bitte einen Repeater-Feldnamen eingeben.', 'soulsites-learndash' ) );
			return;
		}

		// Resolve excluded field names.
		$excluded = [];
		if ( ! empty( $settings['excluded_fields'] ) ) {
			$excluded = array_filter( array_map( 'trim', explode( ',', $settings['excluded_fields'] ) ) );
		}

		// Try to get field definition (type info for sub-fields).
		$sub_fields = $this->get_sub_fields( $field_name, $post_id );

		if ( ! have_rows( $field_name, $post_id ) ) {
			$this->editor_notice(
				sprintf(
					/* translators: %s: ACF field name */
					esc_html__( 'Keine Einträge im Repeater-Feld "%s" gefunden.', 'soulsites-learndash' ),
					esc_html( $field_name )
				)
			);
			return;
		}

		echo '<div class="soulsites-acf-repeater">';

		while ( have_rows( $field_name, $post_id ) ) {
			the_row();

			echo '<div class="soulsites-acf-repeater__item">';

			if ( ! empty( $sub_fields ) ) {
				// Render in ACF-defined order with type information.
				foreach ( $sub_fields as $sub_field ) {
					if ( in_array( $sub_field['name'], $excluded, true ) ) {
						continue;
					}
					$value = get_sub_field( $sub_field['name'] );
					$this->render_sub_field( $sub_field, $value, $settings );
				}
			} else {
				// Fallback when no field definition is available.
				$row = function_exists( 'get_row' ) ? get_row( true ) : [];
				if ( is_array( $row ) ) {
					foreach ( $row as $key => $value ) {
						if ( in_array( $key, $excluded, true ) ) {
							continue;
						}
						$this->render_fallback_field( $key, $value, $settings );
					}
				}
			}

			echo '</div>';
		}

		echo '</div>';
	}

	// =========================================================================
	// Field type rendering
	// =========================================================================

	/**
	 * Renders a single sub-field based on its ACF type.
	 *
	 * @param array $sub_field ACF field definition.
	 * @param mixed $value     Field value.
	 * @param array $settings  Widget settings.
	 */
	private function render_sub_field( array $sub_field, $value, array $settings ) {
		$type       = $sub_field['type'] ?? 'text';
		$label      = $sub_field['label'] ?? '';
		$name       = $sub_field['name'] ?? '';
		$show_label = ( $settings['show_field_labels'] === 'yes' ) && $label !== '';

		// Skip empty values (keep 0 and '0').
		if ( $value === '' || $value === null || $value === false || ( is_array( $value ) && empty( $value ) ) ) {
			return;
		}

		$type_class = 'soulsites-acf-repeater__field--' . esc_attr( $type );

		echo '<div class="soulsites-acf-repeater__field ' . $type_class . '"'
			. ' data-field="' . esc_attr( $name ) . '">';

		if ( $show_label ) {
			echo '<span class="soulsites-acf-repeater__field-label">' . esc_html( $label ) . '</span>';
		}

		switch ( $type ) {
			// --- Plain text types ---
			case 'text':
			case 'number':
			case 'range':
			case 'password':
			case 'date_picker':
			case 'date_time_picker':
			case 'time_picker':
				echo '<p class="soulsites-acf-repeater__field-value">' . esc_html( $value ) . '</p>';
				break;

			case 'textarea':
				echo '<p class="soulsites-acf-repeater__field-value">' . nl2br( esc_html( $value ) ) . '</p>';
				break;

			case 'wysiwyg':
				echo '<div class="soulsites-acf-repeater__field-value">' . wp_kses_post( $value ) . '</div>';
				break;

			// --- URL field (plain URL string) ---
			case 'url':
				$this->render_link(
					(string) $value,
					$settings['link_fallback_text'] ?: (string) $value,
					$settings['link_target']
				);
				break;

			// --- ACF Link field (array: url, title, target) ---
			case 'link':
				if ( is_array( $value ) && ! empty( $value['url'] ) ) {
					$title  = $value['title'] ?: ( $settings['link_fallback_text'] ?: $value['url'] );
					$target = $value['target'] ?: $settings['link_target'];
					$this->render_link( $value['url'], $title, $target );
				}
				break;

			// --- Email ---
			case 'email':
				echo '<a class="soulsites-acf-repeater__link" href="mailto:' . esc_attr( $value ) . '">'
					. esc_html( $value )
					. '</a>';
				break;

			// --- Image (array, attachment ID, or URL) ---
			case 'image':
				$this->render_image( $value );
				break;

			// --- File ---
			case 'file':
				$this->render_file( $value, $settings );
				break;

			// --- Boolean ---
			case 'true_false':
				$text = $value
					? ( $settings['true_false_yes'] ?: esc_html__( 'Ja', 'soulsites-learndash' ) )
					: ( $settings['true_false_no'] ?: esc_html__( 'Nein', 'soulsites-learndash' ) );
				echo '<p class="soulsites-acf-repeater__field-value">' . esc_html( $text ) . '</p>';
				break;

			// --- Choice fields ---
			case 'select':
			case 'radio':
			case 'button_group':
				$display = is_array( $value ) ? implode( ', ', $value ) : (string) $value;
				echo '<p class="soulsites-acf-repeater__field-value">' . esc_html( $display ) . '</p>';
				break;

			case 'checkbox':
				if ( is_array( $value ) ) {
					echo '<ul class="soulsites-acf-repeater__list">';
					foreach ( $value as $item ) {
						echo '<li>' . esc_html( $item ) . '</li>';
					}
					echo '</ul>';
				}
				break;

			// --- Color ---
			case 'color_picker':
				echo '<span class="soulsites-acf-repeater__field-value">'
					. '<span style="display:inline-block;width:1em;height:1em;vertical-align:middle;'
					. 'background-color:' . esc_attr( $value ) . ';border:1px solid currentColor;border-radius:2px;margin-right:.3em;"></span>'
					. esc_html( $value )
					. '</span>';
				break;

			// --- Unsupported / complex types: skip silently ---
			default:
				break;
		}

		echo '</div>';
	}

	/**
	 * Renders a field without type info (fallback when no field definition available).
	 *
	 * @param string $key      Field name.
	 * @param mixed  $value    Field value.
	 * @param array  $settings Widget settings.
	 */
	private function render_fallback_field( string $key, $value, array $settings ) {
		if ( is_array( $value ) || is_object( $value ) ) {
			return; // Cannot safely render complex values without type info.
		}

		$show_label = $settings['show_field_labels'] === 'yes';

		echo '<div class="soulsites-acf-repeater__field soulsites-acf-repeater__field--text"'
			. ' data-field="' . esc_attr( $key ) . '">';

		if ( $show_label ) {
			echo '<span class="soulsites-acf-repeater__field-label">' . esc_html( $key ) . '</span>';
		}

		// Simple heuristic: if value looks like a URL, render as link.
		if ( filter_var( $value, FILTER_VALIDATE_URL ) ) {
			$this->render_link( $value, $settings['link_fallback_text'] ?: $value, $settings['link_target'] );
		} else {
			echo '<p class="soulsites-acf-repeater__field-value">' . nl2br( esc_html( $value ) ) . '</p>';
		}

		echo '</div>';
	}

	// =========================================================================
	// Rendering helpers
	// =========================================================================

	/**
	 * Outputs an anchor tag.
	 *
	 * @param string $url    Target URL.
	 * @param string $text   Link text.
	 * @param string $target Link target attribute.
	 */
	private function render_link( string $url, string $text, string $target ) {
		$rel = ( $target === '_blank' ) ? ' rel="noopener noreferrer"' : '';
		echo '<a class="soulsites-acf-repeater__link"'
			. ' href="' . esc_url( $url ) . '"'
			. ' target="' . esc_attr( $target ) . '"'
			. $rel
			. '>' . esc_html( $text ) . '</a>';
	}

	/**
	 * Outputs an image (supports ACF array, attachment ID, and URL string).
	 *
	 * @param mixed $value ACF image value.
	 */
	private function render_image( $value ) {
		if ( is_array( $value ) ) {
			$url = $value['url'] ?? ( $value['sizes']['large'] ?? '' );
			$alt = $value['alt'] ?? '';
			if ( $url ) {
				echo '<img class="soulsites-acf-repeater__image"'
					. ' src="' . esc_url( $url ) . '"'
					. ' alt="' . esc_attr( $alt ) . '" />';
			}
		} elseif ( is_numeric( $value ) ) {
			echo wp_get_attachment_image(
				(int) $value,
				'large',
				false,
				[ 'class' => 'soulsites-acf-repeater__image' ]
			);
		} elseif ( is_string( $value ) && $value !== '' ) {
			echo '<img class="soulsites-acf-repeater__image" src="' . esc_url( $value ) . '" alt="" />';
		}
	}

	/**
	 * Outputs a file link (supports ACF array and attachment ID).
	 *
	 * @param mixed $value    ACF file value.
	 * @param array $settings Widget settings.
	 */
	private function render_file( $value, array $settings ) {
		if ( is_array( $value ) && ! empty( $value['url'] ) ) {
			$title = $value['title'] ?: ( $value['filename'] ?? basename( $value['url'] ) );
			$this->render_link( $value['url'], $title, $settings['link_target'] );
		} elseif ( is_numeric( $value ) ) {
			$url = wp_get_attachment_url( (int) $value );
			if ( $url ) {
				$title = get_the_title( (int) $value ) ?: basename( $url );
				$this->render_link( $url, $title, $settings['link_target'] );
			}
		}
	}

	// =========================================================================
	// ACF helpers
	// =========================================================================

	/**
	 * Returns the sub-field definitions for a repeater field, or an empty
	 * array when ACF or the field cannot be found.
	 *
	 * @param string     $field_name ACF field name.
	 * @param int|string $post_id    Post ID or 'options'.
	 * @return array
	 */
	private function get_sub_fields( string $field_name, $post_id ): array {
		if ( ! function_exists( 'get_field_object' ) ) {
			return [];
		}

		$field = get_field_object( $field_name, $post_id, false );

		if ( ! is_array( $field ) || ( $field['type'] ?? '' ) !== 'repeater' ) {
			return [];
		}

		return $field['sub_fields'] ?? [];
	}

	/**
	 * Shows a notice only in the Elementor editor.
	 *
	 * @param string $message Already-escaped message.
	 */
	private function editor_notice( string $message ) {
		if ( \Elementor\Plugin::$instance->editor->is_edit_mode() ) {
			echo '<p class="elementor-panel-alert elementor-panel-alert-warning">' . $message . '</p>';
		}
	}
}
