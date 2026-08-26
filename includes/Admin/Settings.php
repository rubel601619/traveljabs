<?php
/**
 * Settings page registration and handling.
 *
 * @package Traveljabs
 */

namespace Traveljabs\Admin;

defined( 'ABSPATH' ) || exit;

/**
 * Registers the Traveljabs settings page and manages the settings option.
 */
final class Settings {

	/**
	 * Option name used to store all plugin settings.
	 */
	public const OPTION_KEY = 'traveljabs_settings';

	/**
	 * Settings page slug.
	 */
	public const PAGE_SLUG = 'traveljabs-settings';

	/**
	 * Settings group used by the WordPress Settings API.
	 */
	public const OPTION_GROUP = 'traveljabs_settings_group';

	/**
	 * Constructor. Hooks the settings page into WordPress.
	 */
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'register_submenu' ), 20 );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'update_option_' . self::OPTION_KEY, array( $this, 'maybe_flush_rewrite_rules' ), 10, 2 );
	}

	/**
	 * Returns the configurable rewrite slug fields mapped to their defaults.
	 *
	 * @return array<string, string>
	 */
	public static function get_slug_fields(): array {
		return array(
			'destinations_slug' => 'destinations',
			'our_clinic_slug'   => 'our-clinic',
			'vaccination_slug'  => 'vaccination',
		);
	}

	/**
	 * Returns the Google Maps settings fields mapped to their defaults.
	 *
	 * @return array<string, string>
	 */
	public static function get_google_maps_fields(): array {
		return array(
			'google_maps_api_key' => '',
		);
	}

	/**
	 * Adds the Settings submenu under the Traveljabs top-level menu.
	 *
	 * Priority 20 places it after the custom post type submenus that core
	 * attaches at priority 10.
	 *
	 * @return void
	 */
	public function register_submenu(): void {
		add_submenu_page(
			AdminMenu::PARENT_SLUG,
			__( 'Traveljabs Settings', 'traveljabs' ),
			__( 'Settings', 'traveljabs' ),
			'manage_options',
			self::PAGE_SLUG,
			array( $this, 'render_page' )
		);
	}

	/**
	 * Registers the setting, section, and fields via the Settings API.
	 *
	 * @return void
	 */
	public function register_settings(): void {
		register_setting(
			self::OPTION_GROUP,
			self::OPTION_KEY,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize_settings' ),
				'default'           => array(),
			)
		);

		add_settings_section(
			'traveljabs_cpt_section',
			__( 'Custom Post Type Settings', 'traveljabs' ),
			array( $this, 'render_section_description' ),
			self::PAGE_SLUG
		);

		foreach ( self::get_slug_fields() as $key => $default ) {
			add_settings_field(
				$key,
				$this->get_field_label( $key ),
				array( $this, 'render_slug_field' ),
				self::PAGE_SLUG,
				'traveljabs_cpt_section',
				array(
					'setting_key' => $key,
					'default'     => $default,
				)
			);
		}

		add_settings_section(
			'google_maps_section',
			__( 'Google Maps Settings', 'traveljabs' ),
			array( $this, 'render_google_maps_section_description' ),
			self::PAGE_SLUG
		);

		foreach ( self::get_google_maps_fields() as $key => $default ) {
			add_settings_field(
			$key,
			$this->get_field_label( $key ),
			array( $this, 'render_google_maps_field' ),
			self::PAGE_SLUG,
			'google_maps_section',
			array(
				'setting_key' => $key,
				'default'     => $default,
			)
			);
		}
	}

	/**
	 * Returns the translated label for a slug field.
	 *
	 * @param string $key Setting key.
	 * @return string
	 */
	private function get_field_label( string $key ): string {
		$labels = array(
			'destinations_slug' => __( 'Destinations Slug', 'traveljabs' ),
			'our_clinic_slug'   => __( 'Our Clinics Slug', 'traveljabs' ),
			'vaccination_slug'  => __( 'Vaccination Slug', 'traveljabs' ),
			'google_maps_api_key' => __( 'Google Maps API Key', 'traveljabs' ),
		);

		return isset( $labels[ $key ] ) ? $labels[ $key ] : '';
	}

	/**
	 * Renders the settings section description.
	 *
	 * @return void
	 */
	public function render_section_description(): void {
		echo '<p class="description">' . esc_html__( 'Configure the frontend rewrite slugs for the custom post types. The internal post type keys never change.', 'traveljabs' ) . '</p>';
	}

	/**
	 * Renders the Google Maps settings section description.
	 *
	 * @return void
	 */
	public function render_google_maps_section_description(): void {
		echo '<p class="description">' . esc_html__( 'Configure the Google Maps API key used by Traveljabs map features.', 'traveljabs' ) . '</p>';
	}

	/**
	 * Renders a rewrite slug text field.
	 *
	 * @param array<string, string> $args Field arguments.
	 * @return void
	 */
	public function render_slug_field( array $args ): void {
		$key     = isset( $args['setting_key'] ) ? (string) $args['setting_key'] : '';
		$default = isset( $args['default'] ) ? (string) $args['default'] : '';
		$options = $this->get_options();
		$value   = isset( $options[ $key ] ) ? (string) $options[ $key ] : $default;

		printf(
			'<input type="text" id="%1$s" name="%2$s[%1$s]" value="%3$s" class="regular-text" />',
			esc_attr( $key ),
			esc_attr( self::OPTION_KEY ),
			esc_attr( $value )
		);

		echo '<p class="description">' . esc_html( sprintf( __( 'Frontend URL prefix. Default: %s', 'traveljabs' ), $default ) ) . '</p>';
	}

	/**
	 * Renders the Google Maps API key field.
	 *
	 * @param array<string, string> $args Field arguments.
	 * @return void
	 */
	public function render_google_maps_field( array $args ): void {
		$key     = isset( $args['setting_key'] ) ? (string) $args['setting_key'] : '';
		$options = $this->get_options();
		$value   = isset( $options[ $key ] ) ? (string) $options[ $key ] : '';

		printf(
			'<input type="text" id="%1$s" name="%2$s[%1$s]" value="%3$s" class="regular-text" autocomplete="off" />',
			esc_attr( $key ),
			esc_attr( self::OPTION_KEY ),
			esc_attr( $value )
		);
	}

	/**
	 * Sanitizes the submitted settings.
	 *
	 * Slugs are normalized with sanitize_title(); empty values fall back to
	 * their defaults. Unknown existing keys are preserved.
	 *
	 * @param mixed $input Raw submitted settings.
	 * @return array<string, mixed>
	 */
	public function sanitize_settings( $input ): array {
		$input   = is_array( $input ) ? wp_unslash( $input ) : array();
		$current = $this->get_options();
		$clean   = array();

		foreach ( self::get_slug_fields() as $key => $default ) {
			$raw           = isset( $input[ $key ] ) ? (string) $input[ $key ] : '';
			$slug          = sanitize_title( $raw );
			$clean[ $key ] = '' !== $slug ? $slug : $default;
		}

		foreach ( self::get_google_maps_fields() as $key => $default ) {
			$clean[ $key ] = isset( $input[ $key ] ) ? sanitize_text_field( (string) $input[ $key ] ) : $default;
		}

		return array_merge( $current, $clean );
	}

	/**
	 * Flushes rewrite rules when a configured rewrite slug has changed.
	 *
	 * @param mixed $old_value Previous option value.
	 * @param mixed $new_value New option value.
	 * @return void
	 */
	public function maybe_flush_rewrite_rules( $old_value, $new_value ): void {
		$old_value = is_array( $old_value ) ? $old_value : array();
		$new_value = is_array( $new_value ) ? $new_value : array();

		foreach ( array_keys( self::get_slug_fields() ) as $key ) {
			$old = isset( $old_value[ $key ] ) ? (string) $old_value[ $key ] : '';
			$new = isset( $new_value[ $key ] ) ? (string) $new_value[ $key ] : '';

			if ( $old !== $new ) {
				flush_rewrite_rules();
				return;
			}
		}
	}

	/**
	 * Renders the settings page.
	 *
	 * @return void
	 */
	public function render_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		?>
		<div class="wrap">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
			<form method="post" action="options.php">
				<?php
				settings_fields( self::OPTION_GROUP );
				do_settings_sections( self::PAGE_SLUG );
				submit_button();
				?>
			</form>
		</div>
		<?php
	}

	/**
	 * Returns the stored settings as an array.
	 *
	 * @return array<string, mixed>
	 */
	private function get_options(): array {
		$options = get_option( self::OPTION_KEY, array() );

		return is_array( $options ) ? $options : array();
	}
}
