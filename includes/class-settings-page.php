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
			// Widgets
			'enable_widget_progress_bar'          => 1,
			'enable_widget_course_content'        => 1,
			'enable_widget_acf_repeater'          => 1,
			// Query Filter
			'enable_query_course_purchase'        => 1,
			'enable_query_tutor_courses'          => 1,
			'enable_query_acf_course_filter'      => 1,
			// E-Mail Benachrichtigungen
			'pending_course_email_enabled'        => 0,
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
	 * Enqueue CSS only on our settings page.
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
					}
				);

				// -----------------------------------------------------------------
				// 7. E-Mail Benachrichtigungen
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
				?>

				<div class="soulsites-settings-footer">
					<?php submit_button( esc_html__( 'Einstellungen speichern', 'soulsites-learndash' ), 'primary large', 'submit', false ); ?>
				</div>

			</form>
		</div>
		<?php
	}
}
