<?php
/**
 * Lazy Template Shortcode
 *
 * Usage: [ss_lazy_template id="123"]
 * Optional: [ss_lazy_template id="123" height="400px"]
 *
 * When the feature is disabled in settings the shortcode renders the template
 * immediately (non-lazy) so existing content is never broken.
 *
 * @package SoulSites_LearnDash
 */

namespace SoulSites;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Lazy_Template {

	private static $instance = null;

	public static function get_instance() {
		if ( self::$instance === null ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_shortcode( 'ss_lazy_template', [ $this, 'render_shortcode' ] );
		add_action( 'wp_ajax_ss_load_lazy_template',        [ $this, 'ajax_load_template' ] );
		add_action( 'wp_ajax_nopriv_ss_load_lazy_template', [ $this, 'ajax_load_template' ] );
	}

	/**
	 * Shortcode handler.
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string HTML output.
	 */
	public function render_shortcode( $atts ) {
		$atts = shortcode_atts(
			[
				'id'     => 0,
				'height' => '300px',
			],
			$atts,
			'ss_lazy_template'
		);

		$template_id = absint( $atts['id'] );
		if ( ! $template_id ) {
			return '';
		}

		// Inside Elementor editor – render directly so the designer sees live content.
		$is_editor = isset( $_GET['elementor-preview'] ) // phpcs:ignore WordPress.Security.NonceVerification
			|| ( defined( '\Elementor\Plugin::class' ) && class_exists( '\Elementor\Plugin' ) && \Elementor\Plugin::$instance->editor->is_edit_mode() );

		$is_lazy_active = Settings_Page::get_option( 'enable_lazy_template' );

		if ( ! $is_lazy_active || $is_editor || is_admin() ) {
			return $this->render_template_directly( $template_id );
		}

		$nonce  = wp_create_nonce( 'ss_lazy_template' );
		$height = sanitize_text_field( $atts['height'] );

		return sprintf(
			'<div class="ss-lazy-template" data-template-id="%d" data-nonce="%s" style="min-height:%s" aria-busy="true"></div>',
			$template_id,
			esc_attr( $nonce ),
			esc_attr( $height )
		);
	}

	/**
	 * AJAX handler – validates the request and returns rendered template HTML
	 * together with any CSS assets that Elementor enqueued during rendering but
	 * would normally only reach the browser through wp_head().
	 */
	public function ajax_load_template() {
		$template_id = absint( $_POST['template_id'] ?? 0 );
		$nonce       = sanitize_text_field( wp_unslash( $_POST['nonce'] ?? '' ) );

		if ( ! wp_verify_nonce( $nonce, 'ss_lazy_template' ) ) {
			wp_send_json_error( [ 'code' => 'invalid_nonce' ], 403 );
		}

		if ( ! $template_id ) {
			wp_send_json_error( [ 'code' => 'invalid_id' ], 400 );
		}

		$post = get_post( $template_id );
		if (
			! $post
			|| $post->post_type   !== 'elementor_library'
			|| $post->post_status !== 'publish'
		) {
			wp_send_json_error( [ 'code' => 'template_not_found' ], 404 );
		}

		if ( ! class_exists( '\Elementor\Plugin' ) ) {
			wp_send_json_error( [ 'code' => 'elementor_inactive' ], 500 );
		}

		// Snapshot of already-processed/queued handles so we only return what
		// Elementor adds during rendering (not the whole page's stylesheet list).
		global $wp_styles;
		$handles_before = array_flip(
			array_merge( $wp_styles->done, $wp_styles->queue )
		);

		$html = \Elementor\Plugin::instance()->frontend->get_builder_content_for_display( $template_id, true );

		// Collect CSS assets that were freshly enqueued during rendering.
		$css_assets = [];
		$all_handles = array_unique( array_merge( $wp_styles->done, $wp_styles->queue ) );

		foreach ( $all_handles as $handle ) {
			if ( isset( $handles_before[ $handle ] ) ) {
				continue; // Was already on the page before we started rendering.
			}

			$style = $wp_styles->registered[ $handle ] ?? null;
			if ( ! $style ) {
				continue;
			}

			$asset = [];

			// External stylesheet URL (Elementor stores CSS as files in uploads/elementor/css/).
			if ( $style->src ) {
				$asset['url'] = $style->src;
			}

			// Inline CSS added via wp_add_inline_style() – e.g. custom CSS per element.
			$inline_parts = [];
			foreach ( [ 'before', 'after' ] as $position ) {
				$chunk = $wp_styles->get_data( $handle, $position );
				if ( ! empty( $chunk ) ) {
					$inline_parts[] = is_array( $chunk ) ? implode( "\n", $chunk ) : (string) $chunk;
				}
			}
			if ( $inline_parts ) {
				$asset['inline'] = implode( "\n", $inline_parts );
			}

			if ( ! empty( $asset ) ) {
				$css_assets[ $handle ] = $asset;
			}
		}

		wp_send_json_success(
			[
				'html'       => $html,
				'css_assets' => $css_assets,
			]
		);
	}

	// -----------------------------------------------------------------------

	/**
	 * Render the Elementor template synchronously (fallback / editor mode).
	 *
	 * @param int $template_id Post ID of the elementor_library template.
	 * @return string HTML or empty string.
	 */
	private function render_template_directly( $template_id ) {
		if ( ! class_exists( '\Elementor\Plugin' ) ) {
			return '';
		}
		return \Elementor\Plugin::instance()->frontend->get_builder_content_for_display( $template_id, true );
	}
}
