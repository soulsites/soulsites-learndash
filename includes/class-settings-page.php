<?php
/**
 * Settings Page for SoulSites LearnDash for Elementor
 *
 * @package SoulSites_LearnDash
 */

namespace SoulSites;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Settings Page Class
 */
class Settings_Page {

	const OPTION_NAME = 'soulsites_learndash_settings';

	/**
	 * @var Settings_Page
	 */
	private static $instance = null;

	/**
	 * @return Settings_Page
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
		add_action( 'admin_menu', [ $this, 'add_menu_page' ] );
		add_action( 'admin_init', [ $this, 'register_settings' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_styles' ] );
	}

	/**
	 * Add submenu page under LearnDash (or under Settings as fallback)
	 */
	public function add_menu_page() {
		if ( defined( 'LEARNDASH_VERSION' ) ) {
			add_submenu_page(
				'learndash-lms',
				esc_html__( 'SoulSites Einstellungen', 'soulsites-learndash' ),
				esc_html__( 'SoulSites Settings', 'soulsites-learndash' ),
				'manage_options',
				'soulsites-learndash-settings',
				[ $this, 'render_page' ]
			);
		} else {
			add_options_page(
				esc_html__( 'SoulSites LearnDash', 'soulsites-learndash' ),
				esc_html__( 'SoulSites LearnDash', 'soulsites-learndash' ),
				'manage_options',
				'soulsites-learndash-settings',
				[ $this, 'render_page' ]
			);
		}
	}

	/**
	 * Register settings via WordPress Settings API
	 */
	public function register_settings() {
		register_setting(
			'soulsites_learndash_group',
			self::OPTION_NAME,
			[ 'sanitize_callback' => [ $this, 'sanitize_settings' ] ]
		);
	}

	/**
	 * Default values for all settings.
	 * All features are enabled by default to preserve existing behaviour.
	 *
	 * @return array
	 */
	public static function get_defaults() {
		return [
			// Editor
			'disable_editor_courses'              => 0,
			'disable_editor_lessons'              => 0,
			'disable_editor_topics'               => 0,
			// Element Conditions (Erweiterte Bedingungen im Advanced-Tab)
			'enable_element_conditions'           => 1,
			// Display Conditions
			'enable_condition_login_status'       => 1,
			'enable_condition_course_enrolled'    => 1,
			'enable_condition_course_access'      => 1,
			// Dynamic Tags – Kurs
			'enable_tag_course_purchase_status'   => 1,
			'enable_tag_course_price'             => 1,
			'enable_tag_course_enrollment_status' => 1,
			'enable_tag_course_progress'          => 1,
			'enable_tag_course_completion_date'   => 1,
			// Dynamic Tags – Tutor (ACF)
			'enable_tag_tutor_name'               => 1,
			'enable_tag_tutor_bio'                => 1,
			'enable_tag_tutor_foto'               => 1,
			'enable_tag_tutor_link'               => 1,
			'enable_tag_tutor_course_categories'  => 1,
			// Dynamic Tags – WooCommerce Kurs (ACF)
			'enable_tag_woo_course_acf_text'      => 1,
			'enable_tag_woo_course_acf_image'     => 1,
			'enable_tag_woo_course_thumbnail'     => 1,
			// Widgets
			'enable_widget_progress_bar'          => 1,
			'enable_widget_course_content'        => 1,
			'enable_widget_acf_repeater'          => 1,
			'enable_widget_lazy_template'         => 1,
			// Query Filter
			'enable_query_course_purchase'        => 1,
			'enable_query_tutor_courses'          => 1,
			'enable_query_acf_course_filter'      => 1,
			'enable_query_course_tag_filter'      => 1,
			// E-Mail Benachrichtigungen
			'pending_course_email_enabled'        => 0,
			// ACF Auto-Tag
			'auto_tag_enabled'                    => 1,
			// Performance
			'enable_lazy_slider'                  => 0,
			'enable_lazy_template'                => 0,
		];
	}

	/**
	 * Read a single setting value with default fallback.
	 *
	 * @param string $key     Setting key.
	 * @param mixed  $default Optional explicit default (overrides built-in defaults).
	 * @return mixed
	 */
	public static function get_option( $key, $default = null ) {
		$options  = get_option( self::OPTION_NAME, [] );
		$defaults = self::get_defaults();

		if ( array_key_exists( $key, $options ) ) {
			return $options[ $key ];
		}

		if ( $default !== null ) {
			return $default;
		}

		return isset( $defaults[ $key ] ) ? $defaults[ $key ] : false;
	}

	/**
	 * Sanitize settings before saving.
	 *
	 * @param array $input Raw input from form.
	 * @return array
	 */
	public function sanitize_settings( $input ) {
		$sanitized = [];
		$bool_keys = array_keys( self::get_defaults() );

		foreach ( $bool_keys as $key ) {
			$sanitized[ $key ] = ! empty( $input[ $key ] ) ? 1 : 0;
		}

		// E-Mail-Adresse (Textfeld)
		$sanitized['pending_course_email_address'] = isset( $input['pending_course_email_address'] )
			? sanitize_email( $input['pending_course_email_address'] )
			: '';

		return $sanitized;
	}

	/**
	 * Enqueue CSS (and migration JS data) only on our settings page.
	 *
	 * @param string $hook Current admin page hook.
	 */
	public function enqueue_admin_styles( $hook ) {
		if ( strpos( $hook, 'soulsites-learndash-settings' ) === false ) {
			return;
		}

		wp_enqueue_style(
			'soulsites-learndash-admin',
			SOULSITES_LEARNDASH_URL . 'assets/css/admin.css',
			[],
			SOULSITES_LEARNDASH_VERSION
		);

		// Inline data for the migration JS block rendered in render_page()
		wp_add_inline_script(
			'jquery',
			'window.SoulsitesMigration = ' . wp_json_encode( [
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'soulsites_acf_migrate_dates' ),
				'i18n'    => [
					'running'       => __( 'Migration läuft …', 'soulsites-learndash' ),
					'dryRunRunning' => __( 'Vorschau wird berechnet …', 'soulsites-learndash' ),
					'confirm'       => __( 'Achtung: Die Migration überschreibt bestehende Werte im Zielfeld. Fortfahren?', 'soulsites-learndash' ),
					'noFields'      => __( 'Bitte Quell- und Zielfeld auswählen.', 'soulsites-learndash' ),
					'sameField'     => __( 'Quell- und Zielfeld dürfen nicht identisch sein.', 'soulsites-learndash' ),
				],
			] ) . ';'
		);
	}

	// -------------------------------------------------------------------------
	// Rendering helpers
	// -------------------------------------------------------------------------

	/**
	 * Render a single checkbox row.
	 *
	 * @param string $key         Option key.
	 * @param string $label       Visible label.
	 * @param string $description Optional description below the checkbox.
	 */
	private function render_checkbox( $key, $label, $description = '' ) {
		$value = self::get_option( $key );
		$name  = esc_attr( self::OPTION_NAME . '[' . $key . ']' );
		?>
		<label class="soulsites-toggle">
			<input type="checkbox" name="<?php echo $name; ?>" value="1" <?php checked( 1, $value ); ?> />
			<span class="soulsites-toggle-label"><?php echo esc_html( $label ); ?></span>
		</label>
		<?php if ( $description ) : ?>
			<p class="description"><?php echo esc_html( $description ); ?></p>
		<?php endif;
	}

	/**
	 * Render a single text input row.
	 *
	 * @param string $key         Option key.
	 * @param string $label       Visible label.
	 * @param string $description Optional description below the input.
	 * @param string $type        Input type attribute (e.g. "text", "email"). Default "text".
	 */
	private function render_text_input( $key, $label, $description = '', $type = 'text' ) {
		$value = self::get_option( $key, '' );
		$name  = esc_attr( self::OPTION_NAME . '[' . $key . ']' );
		?>
		<div class="soulsites-text-input-row">
			<label for="<?php echo esc_attr( $key ); ?>">
				<span class="soulsites-toggle-label"><?php echo esc_html( $label ); ?></span>
			</label>
			<input
				type="<?php echo esc_attr( $type ); ?>"
				id="<?php echo esc_attr( $key ); ?>"
				name="<?php echo $name; ?>"
				value="<?php echo esc_attr( $value ); ?>"
				class="regular-text"
			/>
			<?php if ( $description ) : ?>
				<p class="description"><?php echo esc_html( $description ); ?></p>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Render a textarea input row.
	 *
	 * @param string $key         Option key.
	 * @param string $label       Visible label.
	 * @param string $description Optional description below the textarea.
	 * @param string $placeholder Placeholder text.
	 */
	private function render_textarea( $key, $label, $description = '', $placeholder = '' ) {
		$value = self::get_option( $key, '' );
		$name  = esc_attr( self::OPTION_NAME . '[' . $key . ']' );
		?>
		<div class="soulsites-text-input-row">
			<label for="<?php echo esc_attr( $key ); ?>">
				<span class="soulsites-toggle-label"><?php echo esc_html( $label ); ?></span>
			</label>
			<textarea
				id="<?php echo esc_attr( $key ); ?>"
				name="<?php echo $name; ?>"
				rows="6"
				class="large-text code"
				placeholder="<?php echo esc_attr( $placeholder ); ?>"
				style="font-family:monospace;margin-top:6px;"
			><?php echo esc_textarea( $value ); ?></textarea>
			<?php if ( $description ) : ?>
				<p class="description"><?php echo wp_kses_post( $description ); ?></p>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Render a settings section card.
	 *
	 * @param string   $dashicon   Dashicons class name (without "dashicons-").
	 * @param string   $title      Section title.
	 * @param string   $intro      Short introductory sentence.
	 * @param callable $body_cb    Callback that renders the body content.
	 */
	private function render_section( $dashicon, $title, $intro, $body_cb ) {
		?>
		<div class="soulsites-settings-section">
			<div class="soulsites-section-header">
				<span class="soulsites-section-icon dashicons dashicons-<?php echo esc_attr( $dashicon ); ?>"></span>
				<div>
					<h2><?php echo esc_html( $title ); ?></h2>
					<p><?php echo esc_html( $intro ); ?></p>
				</div>
			</div>
			<div class="soulsites-section-body">
				<?php $body_cb(); ?>
			</div>
		</div>
		<?php
	}

	// -------------------------------------------------------------------------
	// Page render
	// -------------------------------------------------------------------------

	/**
	 * Render the full settings page.
	 */
	public function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		?>
		<div class="wrap soulsites-settings-wrap">

			<div class="soulsites-settings-header">
				<h1><?php esc_html_e( 'SoulSites LearnDash for Elementor', 'soulsites-learndash' ); ?></h1>
				<span class="soulsites-version">v<?php echo esc_html( SOULSITES_LEARNDASH_VERSION ); ?></span>
			</div>

			<?php settings_errors( 'soulsites_learndash_group' ); ?>

			<form method="post" action="options.php">
				<?php settings_fields( 'soulsites_learndash_group' ); ?>

				<?php
				// -----------------------------------------------------------------
				// 1. Block-Editor Einstellungen
				// -----------------------------------------------------------------
				$this->render_section(
					'edit',
					__( 'Block-Editor Einstellungen', 'soulsites-learndash' ),
					__( 'Deaktiviert den Gutenberg Block-Editor für ausgewählte LearnDash Post-Typen. Sinnvoll, wenn Inhalte ausschließlich mit Elementor bearbeitet werden sollen.', 'soulsites-learndash' ),
					function () {
						$this->render_checkbox(
							'disable_editor_courses',
							__( 'Kurse (sfwd-courses)', 'soulsites-learndash' ),
							__( 'Entfernt den Block-Editor für LearnDash Kurse und zeigt stattdessen den klassischen Editor an.', 'soulsites-learndash' )
						);
						$this->render_checkbox(
							'disable_editor_lessons',
							__( 'Lektionen (sfwd-lessons)', 'soulsites-learndash' ),
							__( 'Entfernt den Block-Editor für LearnDash Lektionen.', 'soulsites-learndash' )
						);
						$this->render_checkbox(
							'disable_editor_topics',
							__( 'Themen (sfwd-topic)', 'soulsites-learndash' ),
							__( 'Entfernt den Block-Editor für LearnDash Themen.', 'soulsites-learndash' )
						);
					}
				);

				// -----------------------------------------------------------------
				// 2. Erweiterte Bedingungen (Element-Level)
				// -----------------------------------------------------------------
				$this->render_section(
					'hidden',
					__( 'Erweiterte Bedingungen (Element-Level)', 'soulsites-learndash' ),
					__( 'Fügt jedem Elementor-Element im Advanced-Tab einen „Erweiterte Bedingungen"-Abschnitt hinzu. Damit kann die Sichtbarkeit einzelner Elemente direkt nach Login-Status oder Kurs-Einschreibung gesteuert werden.', 'soulsites-learndash' ),
					function () {
						$this->render_checkbox(
							'enable_element_conditions',
							__( 'Erweiterte Bedingungen aktivieren', 'soulsites-learndash' ),
							__( 'Deaktivieren, wenn der Elementor-Editor nicht mehr korrekt lädt oder die Funktion nicht benötigt wird.', 'soulsites-learndash' )
						);
					}
				);

				// -----------------------------------------------------------------
				// 3. Elementor Anzeigebedingungen (Pro Display Conditions)
				// -----------------------------------------------------------------
				$this->render_section(
					'visibility',
					__( 'Elementor Anzeigebedingungen', 'soulsites-learndash' ),
					__( 'Steuert, welche LearnDash-Anzeigebedingungen in Elementor Pro (Display Conditions) verfügbar sind.', 'soulsites-learndash' ),
					function () {
						$this->render_checkbox(
							'enable_condition_login_status',
							__( 'Login-Status (Eingeloggt / Ausgeloggt)', 'soulsites-learndash' ),
							__( 'Zeigt oder versteckt Elementor-Blöcke abhängig davon, ob der Besucher eingeloggt ist.', 'soulsites-learndash' )
						);
						$this->render_checkbox(
							'enable_condition_course_enrolled',
							__( 'Kurs-Einschreibung (Eingeschrieben / Nicht eingeschrieben)', 'soulsites-learndash' ),
							__( 'Zeigt oder versteckt Blöcke abhängig vom Einschreibestatus des Benutzers im aktuellen Kurs (nur auf Kursseiten).', 'soulsites-learndash' )
						);
						$this->render_checkbox(
							'enable_condition_course_access',
							__( 'Kurs-Zugriffsrecht (Zugang / Kein Zugang)', 'soulsites-learndash' ),
							__( 'Wie die Einschreibung, funktioniert aber auch auf Lektionen und Themen (prüft den Kurszugang).', 'soulsites-learndash' )
						);
					}
				);

				// -----------------------------------------------------------------
				// 4. Dynamische Tags
				// -----------------------------------------------------------------
				$this->render_section(
					'tag',
					__( 'Dynamische Tags', 'soulsites-learndash' ),
					__( 'Aktiviert oder deaktiviert einzelne LearnDash Dynamic Tags in Elementor. Deaktivierte Tags erscheinen nicht mehr in der Tag-Auswahl.', 'soulsites-learndash' ),
					function () {
						echo '<h3>' . esc_html__( 'Kurs-Tags', 'soulsites-learndash' ) . '</h3>';
						$this->render_checkbox(
							'enable_tag_course_purchase_status',
							__( 'Kauf-Status', 'soulsites-learndash' ),
							__( 'Zeigt konfigurierbaren Text basierend auf dem Kaufstatus des Benutzers ("Bereits gekauft" / "Noch nicht gekauft").', 'soulsites-learndash' )
						);
						$this->render_checkbox(
							'enable_tag_course_price',
							__( 'Kurs-Preis', 'soulsites-learndash' ),
							__( 'Gibt den Preis des aktuellen Kurses aus, optional mit Währungssymbol.', 'soulsites-learndash' )
						);
						$this->render_checkbox(
							'enable_tag_course_enrollment_status',
							__( 'Einschreibe-Status', 'soulsites-learndash' ),
							__( 'Zeigt den Einschreibestatus des Benutzers als konfigurierbaren Text (Eingeschrieben / Nicht eingeschrieben / Bitte einloggen).', 'soulsites-learndash' )
						);
						$this->render_checkbox(
							'enable_tag_course_progress',
							__( 'Kursfortschritt (%)', 'soulsites-learndash' ),
							__( 'Gibt den prozentualen Lernfortschritt des aktuellen Benutzers im Kurs aus.', 'soulsites-learndash' )
						);
						$this->render_checkbox(
							'enable_tag_course_completion_date',
							__( 'Abschlussdatum', 'soulsites-learndash' ),
							__( 'Zeigt das Datum, an dem der Benutzer den Kurs abgeschlossen hat. Datumsformat konfigurierbar.', 'soulsites-learndash' )
						);

						echo '<h3>'
							. esc_html__( 'Tutor-Tags', 'soulsites-learndash' )
							. '<span class="soulsites-badge soulsites-badge-acf">ACF</span>'
							. '</h3>';
						echo '<p class="description soulsites-info-box">'
							. esc_html__( 'Diese Tags erfordern das Plugin "Advanced Custom Fields" (ACF) und entsprechende Felder an den Tutor-Beiträgen (tutor, name, bio_kurz, profilbild).', 'soulsites-learndash' )
							. '</p>';
						$this->render_checkbox(
							'enable_tag_tutor_name',
							__( 'Tutor-Name', 'soulsites-learndash' ),
							__( 'Gibt den Namen des zugeordneten Tutors aus (ACF-Feld "name" am Tutor-Beitrag).', 'soulsites-learndash' )
						);
						$this->render_checkbox(
							'enable_tag_tutor_bio',
							__( 'Tutor-Bio', 'soulsites-learndash' ),
							__( 'Gibt die Kurzbiografie des Tutors aus (ACF-Feld "bio_kurz").', 'soulsites-learndash' )
						);
						$this->render_checkbox(
							'enable_tag_tutor_foto',
							__( 'Tutor-Foto', 'soulsites-learndash' ),
							__( 'Gibt das Profilbild des Tutors aus (ACF-Feld "profilbild", Fallback: WordPress Beitragsbild).', 'soulsites-learndash' )
						);
						$this->render_checkbox(
							'enable_tag_tutor_link',
							__( 'Tutor-Link', 'soulsites-learndash' ),
							__( 'Gibt den Permalink zum Tutor-Beitrag aus.', 'soulsites-learndash' )
						);
						$this->render_checkbox(
							'enable_tag_tutor_course_categories',
							__( 'Kurs-Kategorien', 'soulsites-learndash' ),
							__( 'Gibt die Kategorien des Kurses aus einem ACF-Taxonomie-Feld aus (kommagetrennt).', 'soulsites-learndash' )
						);

						echo '<h3>'
							. esc_html__( 'WooCommerce Kurs-Tags', 'soulsites-learndash' )
							. '<span class="soulsites-badge soulsites-badge-acf">ACF</span>'
							. '</h3>';
						echo '<p class="description soulsites-info-box">'
							. esc_html__( 'Diese Tags lesen ACF-Felder vom LearnDash-Kurs, der mit dem aktuellen WooCommerce-Produkt verknüpft ist (meta: _related_course). Funktionieren auch direkt auf Kurs-, Lektions- und Themenseiten. Der ACF-Feldname wird direkt im Elementor-Tag konfiguriert.', 'soulsites-learndash' )
							. '</p>';
						$this->render_checkbox(
							'enable_tag_woo_course_acf_text',
							__( 'Kurs ACF-Textfeld (Produkt)', 'soulsites-learndash' ),
							__( 'Gibt ein beliebiges ACF-Textfeld des verknüpften Kurses aus. Feldname frei wählbar. Unterstützt Plaintext und HTML (WYSIWYG). Für Kurzbeschreibung, Beschreibung, etc.', 'soulsites-learndash' )
						);
						$this->render_checkbox(
							'enable_tag_woo_course_acf_image',
							__( 'Kurs ACF-Bild (Produkt)', 'soulsites-learndash' ),
							__( 'Gibt ein ACF-Bild-Feld des verknüpften Kurses zurück. Feldname frei wählbar. Kompatibel mit Elementor Bild-Widgets und Hintergrundbild-Attributen.', 'soulsites-learndash' )
						);
						$this->render_checkbox(
							'enable_tag_woo_course_thumbnail',
							__( 'Kurs Beitragsbild (Produkt)', 'soulsites-learndash' ),
							__( 'Gibt das WordPress-Beitragsbild (Featured Image) des verknüpften Kurses zurück. Kein ACF erforderlich. Kompatibel mit Elementor Bild-Widgets und Hintergrundbild-Attributen.', 'soulsites-learndash' )
						);
					}
				);

				// -----------------------------------------------------------------
				// 5. Elementor Widgets
				// -----------------------------------------------------------------
				$this->render_section(
					'screenoptions',
					__( 'Elementor Widgets', 'soulsites-learndash' ),
					__( 'Aktiviert oder deaktiviert benutzerdefinierte LearnDash-Widgets in der Elementor-Widget-Bibliothek (Kategorie "Theme Elements").', 'soulsites-learndash' ),
					function () {
						$this->render_checkbox(
							'enable_widget_progress_bar',
							__( 'Kursfortschritts-Balken', 'soulsites-learndash' ),
							__( 'Visualisiert den Kursfortschritt des Benutzers als animierten Balken. Farben, Höhe, Position und Texte sind vollständig über Elementor-Steuerelemente konfigurierbar.', 'soulsites-learndash' )
						);
						$this->render_checkbox(
							'enable_widget_course_content',
							__( 'Kurs-Inhalt (reiner Text)', 'soulsites-learndash' ),
							__( 'Zeigt den post_content eines Kurses ohne die von LearnDash automatisch hinzugefügten Elemente (Kursliste, Navigation etc.).', 'soulsites-learndash' )
						);
						$this->render_checkbox(
							'enable_widget_acf_repeater',
							__( 'ACF Repeater Liste', 'soulsites-learndash' ),
							__( 'Liest ein ACF-Repeater-Feld aus und zeigt dessen Einträge als Liste an. Feldschlüssel für Titel, Beschreibung und Link sind im Widget frei konfigurierbar.', 'soulsites-learndash' )
						);
						$this->render_checkbox(
							'enable_widget_lazy_template',
							__( 'Lazy Template', 'soulsites-learndash' ),
							__( 'Lädt Elementor-Templates lazy (per AJAX), wenn der Benutzer in ihre Nähe scrollt. Mit Einstellungen für Min-Height und Breakpoints. Erfordert aktivierte "Lazy Template" Performance-Option.', 'soulsites-learndash' )
						);
					}
				);

				// -----------------------------------------------------------------
				// 6. Loop-Filter / Abfragen
				// -----------------------------------------------------------------
				$this->render_section(
					'filter',
					__( 'Loop-Abfragen & Filter', 'soulsites-learndash' ),
					__( 'Erweitert Elementor Loop-Widgets (Loop Grid, Loop Carousel) um LearnDash-spezifische Abfrageoptionen.', 'soulsites-learndash' ),
					function () {
						$this->render_checkbox(
							'enable_query_course_purchase',
							__( 'Kauf-Status Filter', 'soulsites-learndash' ),
							__( 'Fügt Loop-Widgets einen Filter hinzu, um Kurse nach dem Kauf-/Einschreibestatus des aktuellen Benutzers zu filtern (Gekauft / Nicht gekauft). Mit eingebautem Performance-Cache.', 'soulsites-learndash' )
						);
						$this->render_checkbox(
							'enable_query_tutor_courses',
							__( 'Tutor-Kurse Query (Query ID: tutor_courses)', 'soulsites-learndash' ),
							__( 'Aktiviert die Elementor Query ID "tutor_courses". Filtert sfwd-courses nach dem ACF-Feld am Kurs, das die aktuelle Tutor-Post-ID enthält. Feldname per Filter "soulsites_tutor_courses_field_name" anpassbar (Standard: "tutor").', 'soulsites-learndash' )
						);
						$this->render_checkbox(
							'enable_query_acf_course_filter',
							__( 'ACF Kursfelder Filter (Query ID: acf_course_filter)', 'soulsites-learndash' )
								. '<span class="soulsites-badge soulsites-badge-acf">ACF</span>',
							__( 'Fügt Loop-Widgets einen Filter hinzu, um Kurse nach einem beliebigen ACF-Feld zu filtern. Im Widget "Query ID" auf "acf_course_filter" setzen, dann Feld, Vergleichsoperator und Wert wählen.', 'soulsites-learndash' )
						);
						$this->render_checkbox(
							'enable_query_course_tag_filter',
							__( 'Kurs-Tag Filter (Query ID: course_tag_filter)', 'soulsites-learndash' ),
							__( 'Fügt Loop-Widgets einen Taxonomie-Filter hinzu, um Kurse nach Tags (ld_course_tag) oder anderen Kurs-Taxonomien zu filtern. Im Widget "Query ID" auf "course_tag_filter" setzen, dann Taxonomie, Term-Slugs/IDs und Operator wählen.', 'soulsites-learndash' )
						);
					}
				);

				// -----------------------------------------------------------------
				// 7. ACF Auto-Tag
				// -----------------------------------------------------------------
				$this->render_section(
					'tag',
					__( 'ACF Auto-Tag für Kurse', 'soulsites-learndash' ),
					__( 'Weist einem Kurs automatisch Tags aus ACF-Feldern zu – live nach Feldänderung und beim Speichern. Manuell gesetzte Tags bleiben erhalten.', 'soulsites-learndash' ),
					function () {
						$this->render_checkbox(
							'auto_tag_enabled',
							__( 'Auto-Tagging aktivieren', 'soulsites-learndash' ),
							__( 'Globaler Schalter. Die eigentliche Konfiguration erfolgt direkt im jeweiligen ACF-Feld unter "Feldgruppen bearbeiten" → Feld auswählen → Option "Auto-Tag für Kurse" aktivieren.', 'soulsites-learndash' )
						);
						echo '<p class="description soulsites-info-box">'
							. wp_kses(
								__( '<strong>So geht\'s:</strong> Öffne eine ACF-Feldgruppe, wähle ein Feld aus (z.&nbsp;B. Checkbox, Select oder Text) und aktiviere dort die Option <em>„Auto-Tag für Kurse"</em>. Optional kann eine abweichende Taxonomie eingetragen werden (Standard: <code>ld_course_tag</code>). Unterstützte Feldtypen: Text, Textarea, Select (Einzel- &amp; Mehrfachauswahl), Checkbox, Radio.', 'soulsites-learndash' ),
								[ 'strong' => [], 'em' => [], 'code' => [], 'br' => [] ]
							)
							. '</p>';
					}
				);

				// -----------------------------------------------------------------
				// 8. E-Mail Benachrichtigungen
				// -----------------------------------------------------------------
				$this->render_section(
					'email-alt',
					__( 'E-Mail Benachrichtigungen', 'soulsites-learndash' ),
					__( 'Sendet automatisch eine E-Mail, wenn ein Kurs zur Überprüfung eingereicht wird (Status wechselt auf "Ausstehend").', 'soulsites-learndash' ),
					function () {
						$this->render_checkbox(
							'pending_course_email_enabled',
							__( 'Benachrichtigung bei ausstehenden Kursen aktivieren', 'soulsites-learndash' ),
							__( 'Sendet eine E-Mail, sobald ein Kurs (sfwd-courses) in den Status "Ausstehend" (pending) wechselt.', 'soulsites-learndash' )
						);
						$this->render_text_input(
							'pending_course_email_address',
							__( 'Empfänger-E-Mail-Adresse', 'soulsites-learndash' ),
							__( 'E-Mail-Adresse, an die die Benachrichtigung gesendet wird. Muss ausgefüllt sein, damit die Benachrichtigung verschickt wird.', 'soulsites-learndash' ),
							'email'
						);
					}
				);
				// -----------------------------------------------------------------
				// 9. Performance
				// -----------------------------------------------------------------
				$this->render_section(
					'performance',
					__( 'Performance', 'soulsites-learndash' ),
					__( 'Optimierungen für Seiten mit vielen Elementor-Elementen.', 'soulsites-learndash' ),
					function () {
						$this->render_checkbox(
							'enable_lazy_slider',
							__( 'Loop Slider Lazy Loading', 'soulsites-learndash' ),
							__( 'Initialisiert Elementor Loop Slider erst wenn sie in die Nähe des Viewports scrollen. Zeigt bis dahin einen animierten Skeleton-Platzhalter. Sinnvoll auf Seiten mit vielen Slidern.', 'soulsites-learndash' )
						);
						$this->render_checkbox(
							'enable_lazy_template',
							__( 'Lazy Template Shortcode', 'soulsites-learndash' ),
							__( 'Aktiviert den [ss_lazy_template id="123"]-Shortcode. Das angegebene Elementor-Template wird erst per AJAX nachgeladen, wenn der Besucher in seine Nähe scrollt. Zeigt bis dahin einen Skeleton-Platzhalter. Funktioniert auch mit Loop Carousels und Grids innerhalb des Templates.', 'soulsites-learndash' )
						);
						echo '<p class="description soulsites-info-box">'
							. wp_kses(
								__( '<strong>Verwendung:</strong> <code>[ss_lazy_template id="123"]</code> – ersetze <code>123</code> durch die ID des Elementor-Templates (Typ: Abschnitt oder vollständige Seite aus der Template-Bibliothek). Optional: <code>height="400px"</code> legt die Mindesthöhe des Platzhalters fest.', 'soulsites-learndash' ),
								[ 'strong' => [], 'code' => [] ]
							)
							. '</p>';
					}
				);
				?>

				<div class="soulsites-settings-footer">
					<?php submit_button( esc_html__( 'Einstellungen speichern', 'soulsites-learndash' ), 'primary large', 'submit', false ); ?>
				</div>

			</form>

			<?php $this->render_migration_section(); ?>

		</div>
		<?php
	}

	// -------------------------------------------------------------------------
	// ACF Migration section (outside the settings <form>)
	// -------------------------------------------------------------------------

	/**
	 * Render the ACF date-migration tool section.
	 * Placed outside the settings <form> so it does not interfere with saving.
	 */
	private function render_migration_section() {
		$acf_active = function_exists( 'acf_get_field_groups' );
		$all_fields = $acf_active ? \SoulSites\ACF_Migration::get_all_fields() : [];

		$post_type_labels = [
			'sfwd-courses'       => __( 'Kurse (sfwd-courses)', 'soulsites-learndash' ),
			'sfwd-lessons'       => __( 'Lektionen (sfwd-lessons)', 'soulsites-learndash' ),
			'sfwd-topic'         => __( 'Themen (sfwd-topic)', 'soulsites-learndash' ),
			'sfwd-quiz'          => __( 'Quiz (sfwd-quiz)', 'soulsites-learndash' ),
			'sfwd-certificates'  => __( 'Zertifikate (sfwd-certificates)', 'soulsites-learndash' ),
			'post'               => __( 'Beiträge (post)', 'soulsites-learndash' ),
			'page'               => __( 'Seiten (page)', 'soulsites-learndash' ),
		];
		?>
		<div class="soulsites-settings-section" id="soulsites-migration-section">
			<div class="soulsites-section-header">
				<span class="soulsites-section-icon dashicons dashicons-migrate"></span>
				<div>
					<h2><?php esc_html_e( 'ACF Datenmigration', 'soulsites-learndash' ); ?></h2>
					<p><?php esc_html_e( 'Konvertiert ein ACF-Textfeld mit Datumsangaben (deutsches Format) in ein ACF-Datumsfeld (Format: JJJJMMTT).', 'soulsites-learndash' ); ?></p>
				</div>
			</div>
			<div class="soulsites-section-body">

				<?php if ( ! $acf_active ) : ?>
					<p class="description soulsites-info-box" style="color:#b32d2e;">
						<?php esc_html_e( 'Advanced Custom Fields (ACF) ist nicht aktiv. Die Migration ist daher nicht verfügbar.', 'soulsites-learndash' ); ?>
					</p>
				<?php elseif ( empty( $all_fields ) ) : ?>
					<p class="description soulsites-info-box">
						<?php esc_html_e( 'Keine ACF-Felder gefunden. Lege zuerst Feldgruppen und Felder in ACF an.', 'soulsites-learndash' ); ?>
					</p>
				<?php else : ?>

					<p class="description soulsites-info-box">
						<?php
						echo wp_kses(
							__( '<strong>Hinweis:</strong> Die Migration liest den Wert aus dem <em>Quellfeld</em>, wandelt ihn in ein Datum (Format <code>JJJJMMTT</code>) um und schreibt ihn ins <em>Zielfeld</em>. Bestehende Werte im Zielfeld werden überschrieben. Nutze die <strong>Vorschau</strong>, um das Ergebnis zuerst zu prüfen, ohne Daten zu verändern.', 'soulsites-learndash' ),
							[ 'strong' => [], 'em' => [], 'code' => [] ]
						);
						?>
					</p>

					<p class="description soulsites-info-box" style="margin-top:8px;">
						<?php
						echo wp_kses(
							__( '<strong>Erkannte Formate:</strong> <code>30.05.2026</code>, <code>30.5.26</code>, <code>Montag, 30. Mai 2026</code>, <code>30.Mai.2026</code>, <code>30 mai 2026</code> sowie englische Datumsangaben. Wochentage (Mo–So) und Bindestriche werden automatisch entfernt.', 'soulsites-learndash' ),
							[ 'strong' => [], 'code' => [] ]
						);
						?>
					</p>

					<table class="form-table soulsites-migration-table" style="max-width:700px;">
						<tr>
							<th scope="row">
								<label for="ss-migration-post-type"><?php esc_html_e( 'Post-Typ', 'soulsites-learndash' ); ?></label>
							</th>
							<td>
								<select id="ss-migration-post-type" class="regular-text">
									<?php foreach ( $post_type_labels as $slug => $label ) : ?>
										<option value="<?php echo esc_attr( $slug ); ?>"><?php echo esc_html( $label ); ?></option>
									<?php endforeach; ?>
								</select>
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label for="ss-migration-source"><?php esc_html_e( 'Quellfeld (Text)', 'soulsites-learndash' ); ?></label>
							</th>
							<td>
								<select id="ss-migration-source" class="regular-text">
									<option value=""><?php esc_html_e( '— Feld auswählen —', 'soulsites-learndash' ); ?></option>
									<?php foreach ( $all_fields as $group_title => $fields ) : ?>
										<optgroup label="<?php echo esc_attr( $group_title ); ?>">
											<?php foreach ( $fields as $field ) : ?>
												<option value="<?php echo esc_attr( $field['name'] ); ?>">
													<?php echo esc_html( $field['label'] . ' (' . $field['name'] . ', ' . $field['type'] . ')' ); ?>
												</option>
											<?php endforeach; ?>
										</optgroup>
									<?php endforeach; ?>
								</select>
								<p class="description"><?php esc_html_e( 'Das Textfeld, das das Datum als Klartext enthält.', 'soulsites-learndash' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label for="ss-migration-target"><?php esc_html_e( 'Zielfeld (Datum)', 'soulsites-learndash' ); ?></label>
							</th>
							<td>
								<select id="ss-migration-target" class="regular-text">
									<option value=""><?php esc_html_e( '— Feld auswählen —', 'soulsites-learndash' ); ?></option>
									<?php foreach ( $all_fields as $group_title => $fields ) : ?>
										<optgroup label="<?php echo esc_attr( $group_title ); ?>">
											<?php foreach ( $fields as $field ) : ?>
												<option value="<?php echo esc_attr( $field['name'] ); ?>">
													<?php echo esc_html( $field['label'] . ' (' . $field['name'] . ', ' . $field['type'] . ')' ); ?>
												</option>
											<?php endforeach; ?>
										</optgroup>
									<?php endforeach; ?>
								</select>
								<p class="description"><?php esc_html_e( 'Das ACF-Datumsfeld, in das der konvertierte Wert gespeichert wird.', 'soulsites-learndash' ); ?></p>
							</td>
						</tr>
					</table>

					<div style="margin-top:16px;display:flex;gap:10px;align-items:center;flex-wrap:wrap;">
						<button type="button" id="ss-migration-dry-run" class="button button-secondary">
							<?php esc_html_e( 'Vorschau (kein Speichern)', 'soulsites-learndash' ); ?>
						</button>
						<button type="button" id="ss-migration-run" class="button button-primary">
							<?php esc_html_e( 'Jetzt migrieren', 'soulsites-learndash' ); ?>
						</button>
						<span id="ss-migration-spinner" class="spinner" style="float:none;margin:0;visibility:hidden;"></span>
					</div>

					<div id="ss-migration-result" style="margin-top:20px;display:none;">
						<div id="ss-migration-result-inner"></div>
					</div>

				<?php endif; ?>

			</div>
		</div>

		<script>
		(function ($) {
			'use strict';
			var cfg = window.SoulsitesMigration || {};

			function runMigration(dryRun) {
				var source = $('#ss-migration-source').val();
				var target = $('#ss-migration-target').val();
				var type   = $('#ss-migration-post-type').val();

				if (!source || !target) {
					alert(cfg.i18n.noFields);
					return;
				}
				if (source === target) {
					alert(cfg.i18n.sameField);
					return;
				}
				if (!dryRun && !confirm(cfg.i18n.confirm)) {
					return;
				}

				$('#ss-migration-spinner').css('visibility', 'visible');
				$('#ss-migration-dry-run, #ss-migration-run').prop('disabled', true);
				$('#ss-migration-result').hide();

				$.post(cfg.ajaxUrl, {
					action:       'soulsites_acf_migrate_dates',
					nonce:        cfg.nonce,
					source_field: source,
					target_field: target,
					post_type:    type,
					dry_run:      dryRun ? '1' : ''
				})
				.done(function (res) {
					if (!res.success) {
						showError(res.data && res.data.message ? res.data.message : '<?php echo esc_js( __( 'Unbekannter Fehler.', 'soulsites-learndash' ) ); ?>');
						return;
					}
					showResult(res.data, dryRun);
				})
				.fail(function (xhr) {
					showError('<?php echo esc_js( __( 'Netzwerkfehler. Bitte Seite neu laden und erneut versuchen.', 'soulsites-learndash' ) ); ?> (' + xhr.status + ')');
				})
				.always(function () {
					$('#ss-migration-spinner').css('visibility', 'hidden');
					$('#ss-migration-dry-run, #ss-migration-run').prop('disabled', false);
				});
			}

			function showError(msg) {
				$('#ss-migration-result-inner').html(
					'<div class="notice notice-error inline" style="margin:0;"><p>' + escHtml(msg) + '</p></div>'
				);
				$('#ss-migration-result').show();
			}

			function showResult(data, dryRun) {
				var label = dryRun
					? '<?php echo esc_js( __( 'Vorschau-Ergebnis (nichts gespeichert)', 'soulsites-learndash' ) ); ?>'
					: '<?php echo esc_js( __( 'Migration abgeschlossen', 'soulsites-learndash' ) ); ?>';

				var noticeClass = dryRun ? 'notice-info' : (data.failed > 0 ? 'notice-warning' : 'notice-success');

				var html = '<div class="notice ' + noticeClass + ' inline" style="margin:0;">';
				html += '<p><strong>' + escHtml(label) + '</strong></p>';
				html += '<p>'
					+ '✅ <?php echo esc_js( __( 'Erfolgreich', 'soulsites-learndash' ) ); ?>: <strong>' + data.success + '</strong> &nbsp;|&nbsp; '
					+ '⏭ <?php echo esc_js( __( 'Übersprungen (leer)', 'soulsites-learndash' ) ); ?>: <strong>' + data.skipped + '</strong> &nbsp;|&nbsp; '
					+ '❌ <?php echo esc_js( __( 'Fehlgeschlagen', 'soulsites-learndash' ) ); ?>: <strong>' + data.failed + '</strong>'
					+ '</p>';

				if (data.failed_details && data.failed_details.length > 0) {
					html += '<p><strong><?php echo esc_js( __( 'Problematische Einträge (Datum konnte nicht erkannt werden):', 'soulsites-learndash' ) ); ?></strong></p>';
					html += '<table style="border-collapse:collapse;width:100%;margin-top:4px;">';
					html += '<thead><tr>'
						+ '<th style="text-align:left;padding:4px 8px;background:#f0f0f1;border:1px solid #c3c4c7;">ID</th>'
						+ '<th style="text-align:left;padding:4px 8px;background:#f0f0f1;border:1px solid #c3c4c7;"><?php echo esc_js( __( 'Titel', 'soulsites-learndash' ) ); ?></th>'
						+ '<th style="text-align:left;padding:4px 8px;background:#f0f0f1;border:1px solid #c3c4c7;"><?php echo esc_js( __( 'Wert im Quellfeld', 'soulsites-learndash' ) ); ?></th>'
						+ '</tr></thead><tbody>';

					$.each(data.failed_details, function (i, row) {
						html += '<tr>'
							+ '<td style="padding:4px 8px;border:1px solid #c3c4c7;white-space:nowrap;">'
								+ '<a href="' + escHtml('<?php echo esc_js( admin_url( 'post.php?action=edit&post=' ) ); ?>' + row.id) + '" target="_blank">'
								+ escHtml(String(row.id)) + '</a></td>'
							+ '<td style="padding:4px 8px;border:1px solid #c3c4c7;">' + escHtml(row.title) + '</td>'
							+ '<td style="padding:4px 8px;border:1px solid #c3c4c7;font-family:monospace;">' + escHtml(row.value) + '</td>'
							+ '</tr>';
					});

					html += '</tbody></table>';
				}

				html += '</div>';
				$('#ss-migration-result-inner').html(html);
				$('#ss-migration-result').show();
				$('#ss-migration-result')[0].scrollIntoView({ behavior: 'smooth', block: 'nearest' });
			}

			function escHtml(str) {
				return $('<div>').text(str).html();
			}

			$('#ss-migration-dry-run').on('click', function () { runMigration(true); });
			$('#ss-migration-run').on('click', function () { runMigration(false); });

		}(jQuery));
		</script>
		<?php
	}
}
