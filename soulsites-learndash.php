<?php
/**
 * Plugin Name: SoulSites LearnDash for Elementor
 * Plugin URI: https://soulsites.de
 * Description: Erweitert Elementor mit LearnDash Dynamic Tags und Display Conditions für Login Status und Kurs-Kaufstatus.
 * Version: 1.3.3
 * Author: Christian Wedel
 * Author URI: https://soulsites.de
 * Text Domain: soulsites-learndash
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

// Define plugin constants
define( 'SOULSITES_LEARNDASH_VERSION', '1.3.3' );
define( 'SOULSITES_LEARNDASH_FILE', __FILE__ );
define( 'SOULSITES_LEARNDASH_PATH', plugin_dir_path( __FILE__ ) );
define( 'SOULSITES_LEARNDASH_URL', plugin_dir_url( __FILE__ ) );

// Load and initialize settings page (runs always, independent of Elementor/LearnDash)
require_once SOULSITES_LEARNDASH_PATH . 'includes/class-settings-page.php';
SoulSites\Settings_Page::get_instance();

// Lazy Template shortcode – runs always so shortcodes in saved content are never broken.
require_once SOULSITES_LEARNDASH_PATH . 'includes/class-lazy-template.php';
SoulSites\Lazy_Template::get_instance();

// ACF Migration – läuft nur im Admin, benötigt kein Elementor/LearnDash.
require_once SOULSITES_LEARNDASH_PATH . 'includes/class-acf-migration.php';
add_action( 'admin_init', function () {
	\SoulSites\ACF_Migration::get_instance();
} );

// ACF Auto-Tag – läuft unabhängig von Elementor, benötigt nur ACF + LearnDash.
// Wird immer initialisiert wenn ACF und LearnDash aktiv sind, damit acf/render_field_settings
// die Checkbox in den Feld-Einstellungen anzeigt. Der globale Toggle steuert nur die
// Live-/Save-Hooks (wird in get_field_config() und enqueue_admin_scripts() geprüft).
require_once SOULSITES_LEARNDASH_PATH . 'includes/class-auto-tag-course.php';
add_action( 'plugins_loaded', function () {
	if ( function_exists( 'get_field' ) && defined( 'LEARNDASH_VERSION' ) ) {
		\SoulSites\Auto_Tag_Course::get_instance();
	}
}, 15 );

/**
 * Main Plugin Class
 */
final class SoulSites_LearnDash_Elementor {

	/**
	 * Plugin instance
	 *
	 * @var SoulSites_LearnDash_Elementor
	 */
	private static $instance = null;

	/**
	 * Get plugin instance
	 *
	 * @return SoulSites_LearnDash_Elementor
	 */
	public static function get_instance() {
		if ( self::$instance === null ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor
	 */
	private function __construct() {
		add_action( 'plugins_loaded', [ $this, 'init' ], 20 );
		// Editor disable runs at init, independent of Elementor
		add_action( 'init', [ $this, 'apply_editor_settings' ] );
		// Kurs-Pending-Benachrichtigung – läuft unabhängig von Elementor
		add_action( 'transition_post_status', [ $this, 'maybe_send_pending_course_email' ], 10, 3 );
	}

	/**
	 * Remove Block-Editor support from LearnDash post types based on settings.
	 * Runs on the 'init' hook so post types are already registered.
	 */
	public function apply_editor_settings() {
		$post_type_map = [
			'disable_editor_courses' => 'sfwd-courses',
			'disable_editor_lessons' => 'sfwd-lessons',
			'disable_editor_topics'  => 'sfwd-topic',
		];

		foreach ( $post_type_map as $setting => $post_type ) {
			if ( \SoulSites\Settings_Page::get_option( $setting ) ) {
				remove_post_type_support( $post_type, 'editor' );
			}
		}
	}

	/**
	 * Send notification email when a LearnDash course is set to "pending".
	 *
	 * @param string   $new  New post status.
	 * @param string   $old  Previous post status.
	 * @param \WP_Post $post Post object.
	 */
	public function maybe_send_pending_course_email( $new, $old, $post ) {
		if ( $new !== 'pending' || $old === 'pending' ) {
			return;
		}
		if ( $post->post_type !== 'sfwd-courses' ) {
			return;
		}
		if ( ! \SoulSites\Settings_Page::get_option( 'pending_course_email_enabled' ) ) {
			return;
		}

		$email = \SoulSites\Settings_Page::get_option( 'pending_course_email_address', '' );
		if ( empty( $email ) ) {
			return;
		}

		$author     = get_userdata( $post->post_author );
		$tutor_name = $author ? $author->display_name : __( 'Unbekannt', 'soulsites-learndash' );

		$subject = sprintf(
			/* translators: %s: course title */
			__( 'Neuer Kurs zur Überprüfung: %s', 'soulsites-learndash' ),
			$post->post_title
		);

		$body = sprintf(
			/* translators: 1: tutor display name, 2: admin edit URL */
			__( "Tutor: %1\$s\nLink: %2\$s", 'soulsites-learndash' ),
			$tutor_name,
			admin_url( 'post.php?post=' . $post->ID . '&action=edit' )
		);

		// PHPMailer-Fehler abfangen (tiefere Ebene als wp_mail() Return-Wert).
		$mailer_error = null;
		$error_handler = function( \WP_Error $error ) use ( &$mailer_error ) {
			$mailer_error = $error->get_error_message();
		};
		add_action( 'wp_mail_failed', $error_handler );

		$sent = wp_mail( $email, $subject, $body );

		remove_action( 'wp_mail_failed', $error_handler );

		if ( ! $sent ) {
			error_log( sprintf(
				'[SoulSites LearnDash] wp_mail() fehlgeschlagen. An: %s | Kurs-ID: %d | Fehler: %s',
				$email,
				$post->ID,
				$mailer_error ?? '(kein Fehlertext)'
			) );
		}
	}

	/**
	 * Initialize plugin
	 */
	public function init() {
		// Check if Elementor is installed and activated
		if ( ! did_action( 'elementor/loaded' ) ) {
			add_action( 'admin_notices', [ $this, 'admin_notice_missing_elementor' ] );
			return;
		}

		// Check if Elementor Pro is installed and activated
		if ( ! defined( 'ELEMENTOR_PRO_VERSION' ) ) {
			add_action( 'admin_notices', [ $this, 'admin_notice_missing_elementor_pro' ] );
			return;
		}

		// Check if LearnDash is installed and activated
		if ( ! defined( 'LEARNDASH_VERSION' ) ) {
			add_action( 'admin_notices', [ $this, 'admin_notice_missing_learndash' ] );
			return;
		}

		// Jede Dateigruppe wird erst im jeweiligen Elementor-Hook geladen,
		// damit Elementors Autoloader alle Basisklassen bereits bereitstellt.
		add_action( 'elementor/theme/register_conditions', [ $this, 'register_conditions' ], 10, 1 );
		add_action( 'elementor/display_conditions/register', [ $this, 'register_display_conditions' ], 10, 1 );
		add_action( 'elementor/dynamic_tags/register', [ $this, 'register_dynamic_tags' ], 10, 1 );
		add_action( 'elementor/widgets/register', [ $this, 'register_widgets' ], 10, 1 );
		add_action( 'elementor/init', [ $this, 'init_query_filters' ], 10 );
		if ( \SoulSites\Settings_Page::get_option( 'enable_element_conditions' ) ) {
			add_action( 'elementor/init', [ $this, 'init_element_conditions' ], 10 );
		}
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_styles' ] );
		add_action( 'wp_enqueue_scripts',   [ $this, 'enqueue_frontend_assets' ] );
	}

	/**
	 * Register Display Conditions
	 * Lädt die Condition-Klassen erst hier, damit ElementorPro\Condition_Base verfügbar ist.
	 */
	public function register_conditions( $conditions_manager ) {
		if ( ! $conditions_manager || ! is_object( $conditions_manager ) ) {
			return;
		}

		try {
			$general_condition = $conditions_manager->get_condition( 'general' );

			if ( ! $general_condition || ! is_object( $general_condition ) ) {
				return;
			}

			// Login-Status (Eingeloggt / Ausgeloggt)
			if ( \SoulSites\Settings_Page::get_option( 'enable_condition_login_status' ) ) {
				foreach ( [ 'class-login-status-condition.php', 'class-logged-in-condition.php', 'class-logged-out-condition.php' ] as $file ) {
					$filepath = SOULSITES_LEARNDASH_PATH . 'includes/conditions/' . $file;
					if ( file_exists( $filepath ) ) {
						require_once $filepath;
					}
				}
				if ( class_exists( 'SoulSites\Conditions\Login_Status_Condition' ) ) {
					$general_condition->register_sub_condition(
						new SoulSites\Conditions\Login_Status_Condition()
					);
				}
			}

			// Kurs-Einschreibung
			if ( \SoulSites\Settings_Page::get_option( 'enable_condition_course_enrolled' ) ) {
				$filepath = SOULSITES_LEARNDASH_PATH . 'includes/conditions/class-course-enrolled-condition.php';
				if ( file_exists( $filepath ) ) {
					require_once $filepath;
				}
				if ( class_exists( 'SoulSites\Conditions\Course_Enrolled_Condition' ) ) {
					$general_condition->register_sub_condition(
						new SoulSites\Conditions\Course_Enrolled_Condition()
					);
				}
			}

			// Kurs-Zugriffsrecht
			if ( \SoulSites\Settings_Page::get_option( 'enable_condition_course_access' ) ) {
				$filepath = SOULSITES_LEARNDASH_PATH . 'includes/conditions/class-course-access-condition.php';
				if ( file_exists( $filepath ) ) {
					require_once $filepath;
				}
				if ( class_exists( 'SoulSites\Conditions\Course_Access_Condition' ) ) {
					$general_condition->register_sub_condition(
						new SoulSites\Conditions\Course_Access_Condition()
					);
				}
			}
		} catch ( \Exception $e ) {
			return;
		}
	}

	/**
	 * Register Element Display Conditions (per-widget visibility, Elementor Pro 3.19+)
	 * Verwendet einen anderen Hook und eine andere Basisklasse als Theme Builder Conditions.
	 */
	public function register_display_conditions( $conditions_manager ) {
		if ( ! $conditions_manager || ! is_object( $conditions_manager ) ) {
			return;
		}

		// Basisklasse muss vorhanden sein – fehlt sie (falsche Version o.ä.), überspringen.
		if ( ! class_exists( '\ElementorPro\Modules\DisplayConditions\Conditions\Base\Condition_Base' ) ) {
			return;
		}

		try {
			// Kurs-Einschreibung (Eingeschrieben / Nicht eingeschrieben)
			if ( \SoulSites\Settings_Page::get_option( 'enable_condition_course_enrolled' ) ) {
				$filepath = SOULSITES_LEARNDASH_PATH . 'includes/display-conditions/class-course-enrolled-display-condition.php';
				if ( file_exists( $filepath ) ) {
					require_once $filepath;
				}
				if ( class_exists( 'SoulSites\Display_Conditions\Course_Is_Enrolled_Display_Condition' ) ) {
					$conditions_manager->register_condition_instance( new SoulSites\Display_Conditions\Course_Is_Enrolled_Display_Condition() );
					$conditions_manager->register_condition_instance( new SoulSites\Display_Conditions\Course_Not_Enrolled_Display_Condition() );
				}
			}

			// Kurs-Zugriffsrecht (Zugang / Kein Zugang)
			if ( \SoulSites\Settings_Page::get_option( 'enable_condition_course_access' ) ) {
				$filepath = SOULSITES_LEARNDASH_PATH . 'includes/display-conditions/class-course-access-display-condition.php';
				if ( file_exists( $filepath ) ) {
					require_once $filepath;
				}
				if ( class_exists( 'SoulSites\Display_Conditions\Course_Has_Access_Display_Condition' ) ) {
					$conditions_manager->register_condition_instance( new SoulSites\Display_Conditions\Course_Has_Access_Display_Condition() );
					$conditions_manager->register_condition_instance( new SoulSites\Display_Conditions\Course_No_Access_Display_Condition() );
				}
			}
		} catch ( \Throwable $e ) {
			// Fängt sowohl \Exception als auch PHP \Error (z.B. "Class not found").
			return;
		}
	}

	/**
	 * Enqueue Admin CSS
	 */
	public function enqueue_admin_styles( $hook ) {
		$screen = get_current_screen();

		// Nur im LearnDash Kurs Post Type laden
		if ( isset( $screen->post_type ) && $screen->post_type === 'sfwd-courses' ) {
			wp_enqueue_style(
				'soulsites-learndash-admin',
				SOULSITES_LEARNDASH_URL . 'assets/css/admin.css',
				[],
				SOULSITES_LEARNDASH_VERSION
			);
		}
	}

	/**
	 * Enqueue frontend assets (CSS/JS) – only outside the Elementor editor.
	 */
	public function enqueue_frontend_assets() {
		// Never load inside the Elementor editor iframe.
		if ( isset( $_GET['elementor-preview'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			return;
		}

		if ( \SoulSites\Settings_Page::get_option( 'enable_lazy_slider' ) ) {
			wp_enqueue_style(
				'soulsites-lazy-slider',
				SOULSITES_LEARNDASH_URL . 'assets/css/lazy-slider.css',
				[],
				SOULSITES_LEARNDASH_VERSION
			);

			wp_enqueue_script(
				'soulsites-lazy-slider',
				SOULSITES_LEARNDASH_URL . 'assets/js/lazy-slider.js',
				[ 'jquery', 'elementor-frontend' ],
				SOULSITES_LEARNDASH_VERSION,
				true
			);
		}

		if ( \SoulSites\Settings_Page::get_option( 'enable_lazy_template' ) ) {
			wp_enqueue_style(
				'soulsites-lazy-template',
				SOULSITES_LEARNDASH_URL . 'assets/css/lazy-template.css',
				[],
				SOULSITES_LEARNDASH_VERSION
			);

			wp_enqueue_script(
				'soulsites-lazy-template',
				SOULSITES_LEARNDASH_URL . 'assets/js/lazy-template.js',
				[ 'jquery' ],
				SOULSITES_LEARNDASH_VERSION,
				true
			);

			wp_localize_script(
				'soulsites-lazy-template',
				'SsLazyTemplate',
				[
					'ajaxUrl' => admin_url( 'admin-ajax.php' ),
					'nonce'   => wp_create_nonce( 'ss_lazy_template' ),
				]
			);
		}
	}

	/**
	 * Register Dynamic Tags
	 * Lädt die Tag-Klassen erst hier, damit Elementor\Core\DynamicTags\Tag verfügbar ist.
	 */
	public function register_dynamic_tags( $dynamic_tags_manager ) {
		if ( ! $dynamic_tags_manager || ! is_object( $dynamic_tags_manager ) ) {
			return;
		}

		try {
			// Map: setting key => [ file, class ]
			$tag_map = [
				'enable_tag_course_purchase_status'   => [ 'class-course-purchase-status.php',  'SoulSites\Dynamic_Tags\Course_Purchase_Status' ],
				'enable_tag_course_price'             => [ 'class-course-price.php',             'SoulSites\Dynamic_Tags\Course_Price' ],
				'enable_tag_course_enrollment_status' => [ 'class-course-enrollment-status.php', 'SoulSites\Dynamic_Tags\Course_Enrollment_Status' ],
				'enable_tag_course_progress'          => [ 'class-course-progress.php',          'SoulSites\Dynamic_Tags\Course_Progress' ],
				'enable_tag_course_completion_date'   => [ 'class-course-completion-date.php',   'SoulSites\Dynamic_Tags\Course_Completion_Date' ],
				'enable_tag_tutor_name'               => [ 'class-tutor-name.php',               'SoulSites\Dynamic_Tags\Tutor_Name' ],
				'enable_tag_tutor_bio'                => [ 'class-tutor-bio.php',                'SoulSites\Dynamic_Tags\Tutor_Bio' ],
				'enable_tag_tutor_foto'               => [ 'class-tutor-foto.php',               'SoulSites\Dynamic_Tags\Tutor_Foto' ],
				'enable_tag_tutor_link'               => [ 'class-tutor-link.php',               'SoulSites\Dynamic_Tags\Tutor_Link' ],
				'enable_tag_tutor_course_categories'  => [ 'class-tutor-course-categories.php',  'SoulSites\Dynamic_Tags\Tutor_Course_Categories' ],
				// WooCommerce Kurs-Tags (ACF)
				'enable_tag_woo_course_acf_text'      => [ 'class-woo-course-acf-text.php',      'SoulSites\Dynamic_Tags\Woo_Course_ACF_Text' ],
				'enable_tag_woo_course_acf_image'     => [ 'class-woo-course-acf-image.php',     'SoulSites\Dynamic_Tags\Woo_Course_ACF_Image' ],
				'enable_tag_woo_course_thumbnail'     => [ 'class-woo-course-thumbnail.php',     'SoulSites\Dynamic_Tags\Woo_Course_Thumbnail' ],
			];

			// Collect active tags
			$active_classes = [];
			foreach ( $tag_map as $setting => [ $file, $class ] ) {
				if ( ! \SoulSites\Settings_Page::get_option( $setting ) ) {
					continue;
				}
				$filepath = SOULSITES_LEARNDASH_PATH . 'includes/dynamic-tags/' . $file;
				if ( file_exists( $filepath ) ) {
					require_once $filepath;
				}
				$active_classes[] = $class;
			}

			// Only register the group when at least one tag is active
			if ( empty( $active_classes ) ) {
				return;
			}

			$dynamic_tags_manager->register_group( 'learndash', [
				'title' => esc_html__( 'LearnDash', 'soulsites-learndash' ),
			] );

			foreach ( $active_classes as $class ) {
				if ( class_exists( $class ) ) {
					$dynamic_tags_manager->register( new $class() );
				}
			}
		} catch ( \Exception $e ) {
			return;
		}
	}

	/**
	 * Register Elementor Widgets
	 * Lädt die Widget-Klassen erst hier, damit Elementor\Widget_Base verfügbar ist.
	 */
	public function register_widgets( $widgets_manager ) {
		if ( ! $widgets_manager || ! is_object( $widgets_manager ) ) {
			return;
		}

		try {
			$widget_map = [
				'enable_widget_progress_bar'   => [ 'class-course-progress-bar.php', 'SoulSites\Widgets\Course_Progress_Bar' ],
				'enable_widget_course_content' => [ 'class-course-content.php',      'SoulSites\Widgets\Course_Content' ],
				'enable_widget_acf_repeater'   => [ 'class-acf-repeater.php',        'SoulSites\Widgets\ACF_Repeater' ],
			];

			foreach ( $widget_map as $setting => [ $file, $class ] ) {
				if ( ! \SoulSites\Settings_Page::get_option( $setting ) ) {
					continue;
				}
				$filepath = SOULSITES_LEARNDASH_PATH . 'includes/widgets/' . $file;
				if ( file_exists( $filepath ) ) {
					require_once $filepath;
				}
				if ( class_exists( $class ) ) {
					$widgets_manager->register( new $class() );
				}
			}
		} catch ( \Exception $e ) {
			return;
		}
	}

	/**
	 * Initialize Element Conditions
	 * Adds "Erweiterte Bedingungen" section to every Elementor element.
	 */
	public function init_element_conditions() {
		try {
			$filepath = SOULSITES_LEARNDASH_PATH . 'includes/element-conditions/class-element-conditions.php';
			if ( file_exists( $filepath ) ) {
				require_once $filepath;
			}

			if ( class_exists( 'SoulSites\Element_Conditions\Element_Conditions' ) ) {
				SoulSites\Element_Conditions\Element_Conditions::get_instance();
			}
		} catch ( \Exception $e ) {
			return;
		}
	}

	/**
	 * Initialize Query Filters
	 */
	public function init_query_filters() {
		$has_purchase   = \SoulSites\Settings_Page::get_option( 'enable_query_course_purchase' );
		$has_acf_filter = \SoulSites\Settings_Page::get_option( 'enable_query_acf_course_filter' );
		$has_tag_filter = \SoulSites\Settings_Page::get_option( 'enable_query_course_tag_filter' );

		// Course Purchase Query
		if ( $has_purchase ) {
			try {
				$query_file = SOULSITES_LEARNDASH_PATH . 'includes/query/class-course-purchase-query.php';
				if ( file_exists( $query_file ) ) {
					require_once $query_file;
				}
				if ( class_exists( 'SoulSites\Query\Course_Purchase_Query' ) ) {
					new SoulSites\Query\Course_Purchase_Query();
				}
			} catch ( \Exception $e ) {
				// Bei Fehler nichts tun
			}
		}

		// ACF Course Filter Query
		if ( $has_acf_filter ) {
			try {
				$acf_file = SOULSITES_LEARNDASH_PATH . 'includes/query/class-acf-course-filter-query.php';
				if ( file_exists( $acf_file ) ) {
					require_once $acf_file;
				}
				if ( class_exists( 'SoulSites\Query\ACF_Course_Filter_Query' ) ) {
					new SoulSites\Query\ACF_Course_Filter_Query();
				}
			} catch ( \Exception $e ) {
				// Bei Fehler nichts tun
			}
		}

		// Course Tag Filter Query
		if ( $has_tag_filter ) {
			try {
				$tag_filter_file = SOULSITES_LEARNDASH_PATH . 'includes/query/class-course-tag-filter-query.php';
				if ( file_exists( $tag_filter_file ) ) {
					require_once $tag_filter_file;
				}
				if ( class_exists( 'SoulSites\Query\Course_Tag_Filter_Query' ) ) {
					new SoulSites\Query\Course_Tag_Filter_Query();
				}
			} catch ( \Exception $e ) {
				// Bei Fehler nichts tun
			}
		}

		// Controls in Loop-Widgets registrieren (wenn mind. ein Filter aktiv ist)
		if ( $has_purchase || $has_acf_filter || $has_tag_filter ) {
			add_action( 'elementor/element/loop-grid/section_query/before_section_end', [ $this, 'add_query_controls' ], 10, 2 );
			add_action( 'elementor/element/loop-carousel/section_query/before_section_end', [ $this, 'add_query_controls' ], 10, 2 );
		}

		// Tutor Courses Query
		if ( \SoulSites\Settings_Page::get_option( 'enable_query_tutor_courses' ) ) {
			try {
				$tutor_query_file = SOULSITES_LEARNDASH_PATH . 'includes/query/class-tutor-courses-query.php';
				if ( file_exists( $tutor_query_file ) ) {
					require_once $tutor_query_file;
				}
				if ( class_exists( 'SoulSites\Query\Tutor_Courses_Query' ) ) {
					new SoulSites\Query\Tutor_Courses_Query();
				}
			} catch ( \Exception $e ) {
				// Bei Fehler nichts tun
			}
		}
	}

	/**
	 * Add Query Controls to Loop Widgets
	 */
	public function add_query_controls( $element, $args ) {
		try {
			if ( \SoulSites\Settings_Page::get_option( 'enable_query_course_purchase' )
				&& class_exists( 'SoulSites\Query\Course_Purchase_Query' ) ) {
				SoulSites\Query\Course_Purchase_Query::register_controls( $element );
			}

			if ( \SoulSites\Settings_Page::get_option( 'enable_query_acf_course_filter' )
				&& class_exists( 'SoulSites\Query\ACF_Course_Filter_Query' ) ) {
				SoulSites\Query\ACF_Course_Filter_Query::register_controls( $element );
			}

			if ( \SoulSites\Settings_Page::get_option( 'enable_query_course_tag_filter' )
				&& class_exists( 'SoulSites\Query\Course_Tag_Filter_Query' ) ) {
				SoulSites\Query\Course_Tag_Filter_Query::register_controls( $element );
			}
		} catch ( \Exception $e ) {
			return;
		}
	}

	/**
	 * Admin notice for missing Elementor
	 */
	public function admin_notice_missing_elementor() {
		$message = sprintf(
			esc_html__( '"%1$s" benötigt "%2$s" um zu funktionieren.', 'soulsites-learndash' ),
			'<strong>' . esc_html__( 'SoulSites LearnDash for Elementor', 'soulsites-learndash' ) . '</strong>',
			'<strong>' . esc_html__( 'Elementor', 'soulsites-learndash' ) . '</strong>'
		);
		echo '<div class="notice notice-warning is-dismissible"><p>' . wp_kses_post( $message ) . '</p></div>';
	}

	/**
	 * Admin notice for missing Elementor Pro
	 */
	public function admin_notice_missing_elementor_pro() {
		$message = sprintf(
			esc_html__( '"%1$s" benötigt "%2$s" um zu funktionieren.', 'soulsites-learndash' ),
			'<strong>' . esc_html__( 'SoulSites LearnDash for Elementor', 'soulsites-learndash' ) . '</strong>',
			'<strong>' . esc_html__( 'Elementor Pro', 'soulsites-learndash' ) . '</strong>'
		);
		echo '<div class="notice notice-warning is-dismissible"><p>' . wp_kses_post( $message ) . '</p></div>';
	}

	/**
	 * Admin notice for missing LearnDash
	 */
	public function admin_notice_missing_learndash() {
		$message = sprintf(
			esc_html__( '"%1$s" benötigt "%2$s" um zu funktionieren.', 'soulsites-learndash' ),
			'<strong>' . esc_html__( 'SoulSites LearnDash for Elementor', 'soulsites-learndash' ) . '</strong>',
			'<strong>' . esc_html__( 'LearnDash LMS', 'soulsites-learndash' ) . '</strong>'
		);
		echo '<div class="notice notice-warning is-dismissible"><p>' . wp_kses_post( $message ) . '</p></div>';
	}
}

// Initialize plugin
SoulSites_LearnDash_Elementor::get_instance();
