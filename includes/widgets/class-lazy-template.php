<?php
namespace SoulSites\Widgets;

use Elementor\Widget_Base;
use Elementor\Controls_Manager;
use Elementor\Group_Control_Responsive;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Lazy_Template extends Widget_Base {

	public function get_name() {
		return 'soulsites-lazy-template';
	}

	public function get_title() {
		return esc_html__( 'Lazy Template', 'soulsites-learndash' );
	}

	public function get_icon() {
		return 'eicon-template';
	}

	public function get_categories() {
		return [ 'theme-elements' ];
	}

	public function get_keywords() {
		return [ 'template', 'lazy', 'load', 'lazy-load', 'performance' ];
	}

	protected function register_controls() {
		$this->start_controls_section(
			'section_template',
			[
				'label' => esc_html__( 'Template', 'soulsites-learndash' ),
				'tab'   => Controls_Manager::TAB_CONTENT,
			]
		);

		// Template dropdown
		$this->add_control(
			'template_id',
			[
				'label'       => esc_html__( 'Select Template', 'soulsites-learndash' ),
				'type'        => Controls_Manager::SELECT,
				'options'     => $this->get_templates_list(),
				'default'     => '',
				'description' => esc_html__( 'Choose an Elementor template to load lazily.', 'soulsites-learndash' ),
			]
		);

		$this->add_control(
			'info_lazy_feature',
			[
				'type'            => Controls_Manager::RAW_HTML,
				'raw'             => sprintf(
					'<div style="padding:12px; background:#f0f6fc; border-left:4px solid #4a90e2; color:#333;">%s</div>',
					esc_html__( 'Template will load when scrolled into view (300px threshold). Enable "Lazy Template" in SoulSites settings to activate lazy-loading. Templates load immediately in the Elementor editor.', 'soulsites-learndash' )
				),
				'content_classes' => 'elementor-panel-alert elementor-panel-alert-info',
			]
		);

		$this->end_controls_section();

		// Layout settings
		$this->start_controls_section(
			'section_layout',
			[
				'label' => esc_html__( 'Layout', 'soulsites-learndash' ),
				'tab'   => Controls_Manager::TAB_CONTENT,
			]
		);

		$this->add_responsive_control(
			'min_height',
			[
				'label'           => esc_html__( 'Minimum Height', 'soulsites-learndash' ),
				'description'     => esc_html__( 'Prevents layout shifts while template is loading. Recommended for smooth user experience.', 'soulsites-learndash' ),
				'type'            => Controls_Manager::SLIDER,
				'size_units'      => [ 'px', 'vh', 'em' ],
				'range'           => [
					'px' => [
						'min'  => 0,
						'max'  => 1000,
						'step' => 10,
					],
					'vh' => [
						'min'  => 0,
						'max'  => 100,
						'step' => 5,
					],
					'em' => [
						'min'  => 0,
						'max'  => 100,
						'step' => 1,
					],
				],
				'selectors'       => [
					'{{WRAPPER}} .ss-lazy-template' => 'min-height: {{SIZE}}{{UNIT}};',
				],
				'default'         => [
					'unit' => 'px',
					'size' => 300,
				],
			]
		);

		$this->add_responsive_control(
			'breakpoint_display',
			[
				'label'       => esc_html__( 'Display on', 'soulsites-learndash' ),
				'description' => esc_html__( 'Control which viewport sizes show this template.', 'soulsites-learndash' ),
				'type'        => Controls_Manager::SELECT,
				'options'     => [
					'all'    => esc_html__( 'All devices', 'soulsites-learndash' ),
					'desktop' => esc_html__( 'Desktop only', 'soulsites-learndash' ),
					'tablet' => esc_html__( 'Tablet & desktop', 'soulsites-learndash' ),
					'mobile' => esc_html__( 'Mobile & up', 'soulsites-learndash' ),
				],
				'default'     => 'all',
				'prefix_class' => 'ss-lt-breakpoint-',
			]
		);

		$this->end_controls_section();

		// CSS class for custom styling
		$this->start_controls_section(
			'section_advanced',
			[
				'label' => esc_html__( 'Advanced', 'soulsites-learndash' ),
				'tab'   => Controls_Manager::TAB_ADVANCED,
			]
		);

		$this->add_control(
			'css_classes',
			[
				'label'       => esc_html__( 'CSS Classes', 'soulsites-learndash' ),
				'type'        => Controls_Manager::TEXT,
				'description' => esc_html__( 'Space-separated list of custom CSS classes to add to the template container.', 'soulsites-learndash' ),
			]
		);

		$this->end_controls_section();
	}

	public function get_templates_list() {
		$templates = [
			'' => esc_html__( '— Select a template —', 'soulsites-learndash' ),
		];

		$args = [
			'post_type'      => 'elementor_library',
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'orderby'        => 'title',
			'order'          => 'ASC',
		];

		$query = new \WP_Query( $args );

		if ( $query->have_posts() ) {
			foreach ( $query->posts as $post ) {
				$templates[ $post->ID ] = $post->post_title;
			}
		}

		return $templates;
	}

	protected function render() {
		$settings = $this->get_settings_for_display();
		$template_id = absint( $settings['template_id'] ?? 0 );

		if ( ! $template_id ) {
			if ( \Elementor\Plugin::$instance->editor->is_edit_mode() ) {
				?>
				<div class="elementor-alert elementor-alert-warning">
					<?php esc_html_e( 'Please select a template.', 'soulsites-learndash' ); ?>
				</div>
				<?php
			}
			return;
		}

		$post = get_post( $template_id );
		if ( ! $post || $post->post_type !== 'elementor_library' || $post->post_status !== 'publish' ) {
			if ( \Elementor\Plugin::$instance->editor->is_edit_mode() ) {
				?>
				<div class="elementor-alert elementor-alert-danger">
					<?php esc_html_e( 'Selected template not found or not published.', 'soulsites-learndash' ); ?>
				</div>
				<?php
			}
			return;
		}

		$is_editor = \Elementor\Plugin::$instance->editor->is_edit_mode();
		$is_lazy_active = \SoulSites\Settings_Page::get_option( 'enable_lazy_template' );

		// In editor or if lazy loading is disabled, render immediately
		if ( $is_editor || ! $is_lazy_active ) {
			$this->render_template_directly( $template_id );
			return;
		}

		// Get min-height from settings
		$min_height = '300px';
		if ( ! empty( $settings['min_height']['size'] ) ) {
			$min_height = $settings['min_height']['size'] . $settings['min_height']['unit'];
		}

		$nonce = wp_create_nonce( 'ss_lazy_template' );
		$css_classes = ! empty( $settings['css_classes'] ) ? sanitize_html_class( $settings['css_classes'] ) : '';

		?>
		<div class="ss-lazy-template <?php echo esc_attr( $css_classes ); ?>"
			data-template-id="<?php echo esc_attr( $template_id ); ?>"
			data-nonce="<?php echo esc_attr( $nonce ); ?>"
			data-context-id="<?php echo esc_attr( get_the_ID() ); ?>"
			style="min-height:<?php echo esc_attr( $min_height ); ?>;"
			aria-busy="true">
		</div>
		<?php
	}

	private function render_template_directly( $template_id ) {
		if ( ! class_exists( '\Elementor\Plugin' ) ) {
			return;
		}
		?>
		<div class="ss-lt-content">
			<?php
			echo \Elementor\Plugin::instance()->frontend->get_builder_content_for_display( $template_id, true );
			?>
		</div>
		<?php
	}
}
