<?php
/**
 * Cart Course Description
 *
 * Zeigt im WooCommerce-Warenkorb statt der Produktbeschreibung die Beschreibung
 * des verknüpften LearnDash-Kurses (meta: _related_course).
 * Als Quelle dient entweder ein ACF-Feld oder die WordPress-Kurzbeschreibung
 * (post_excerpt), mit Fallback auf post_content.
 *
 * @package SoulSites_LearnDash
 */

namespace SoulSites;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Cart_Course_Description {

	private static $instance = null;

	public static function get_instance() {
		if ( self::$instance === null ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_filter( 'woocommerce_cart_item_name', [ $this, 'append_course_description' ], 10, 3 );
	}

	/**
	 * Hängt die Kursbeschreibung an den Produktnamen im Warenkorb an.
	 *
	 * @param string $name          Bereits gefilterter Produktname (HTML).
	 * @param array  $cart_item     WooCommerce Cart-Item-Array.
	 * @param string $cart_item_key Cart-Item-Key.
	 * @return string
	 */
	public function append_course_description( $name, $cart_item, $cart_item_key ) {
		$product_id = absint( $cart_item['product_id'] ?? 0 );
		if ( ! $product_id ) {
			return $name;
		}

		$course_id = $this->get_linked_course_id( $product_id );
		if ( ! $course_id ) {
			return $name;
		}

		$description = $this->get_course_description( $course_id );
		if ( empty( $description ) ) {
			return $name;
		}

		return $name . '<span class="soulsites-cart-course-description">' . wp_kses_post( $description ) . '</span>';
	}

	/**
	 * Liest die Kurs-ID aus dem _related_course-Meta des Produkts.
	 *
	 * @param int $product_id WooCommerce Produkt-ID.
	 * @return int Kurs-ID oder 0.
	 */
	private function get_linked_course_id( $product_id ) {
		$related = get_post_meta( $product_id, '_related_course', true );
		if ( empty( $related ) || ! is_array( $related ) ) {
			return 0;
		}
		return (int) reset( $related );
	}

	/**
	 * Liest die Beschreibung des Kurses.
	 * Reihenfolge: ACF-Feld (wenn konfiguriert) → post_excerpt → post_content.
	 *
	 * @param int $course_id LearnDash Kurs-ID.
	 * @return string
	 */
	private function get_course_description( $course_id ) {
		$acf_field = trim( Settings_Page::get_option( 'cart_course_description_acf_field', '' ) );

		if ( ! empty( $acf_field ) && function_exists( 'get_field' ) ) {
			$value = get_field( $acf_field, $course_id );
			if ( ! empty( $value ) ) {
				return $value;
			}
		}

		$excerpt = get_post_field( 'post_excerpt', $course_id );
		if ( ! empty( $excerpt ) ) {
			return $excerpt;
		}

		$content = get_post_field( 'post_content', $course_id );
		if ( ! empty( $content ) ) {
			return wp_strip_all_tags( $content );
		}

		return '';
	}
}
