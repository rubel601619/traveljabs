<?php
/**
 * Clinic frontend single-post controller.
 *
 * @package Traveljabs
 */

namespace Traveljabs\PostTypes\Clinic;

defined( 'ABSPATH' ) || exit;

/**
 * Detects a singular Clinic post and provides the plugin template
 * and assets for it. Presentation lives in templates/; this class only
 * coordinates loading.
 */
final class ClinicSingle {

	/**
	 * Constructor. Hooks the single-post functionality into WordPress.
	 */
	public function __construct() {
		add_action( 'wp_enqueue_scripts', array( $this, 'register_assets' ) );
		add_filter( 'template_include', array( $this, 'load_template' ) );
	}

	/**
	 * Enqueues the single-post stylesheet on the Clinic singular view only.
	 *
	 * @return void
	 */
	public function register_assets(): void {
		if ( ! is_singular( 'clinic' ) ) {
			return;
		}

		wp_enqueue_style(
			'traveljabs-clinic-single',
			TRAVELJABS_URL . 'assets/css/clinic-single.css',
			array(),
			TRAVELJABS_VERSION
		);
	}

	/**
	 * Loads the plugin single template for the Clinic post type.
	 *
	 * Themes can override the recent-item part via the
	 * traveljabs_clinic_recent_item_template filter; every other view
	 * is left untouched.
	 *
	 * @param string $template Template resolved by WordPress.
	 * @return string
	 */
	public function load_template( $template ): string {
		if ( ! is_singular( 'clinic' ) ) {
			return is_string( $template ) ? $template : '';
		}

		$single_template = TRAVELJABS_PATH . 'templates/single-clinic.php';

		if ( is_readable( $single_template ) ) {
			return $single_template;
		}

		return is_string( $template ) ? $template : '';
	}
}
