<?php
/**
 * Clinic search and map shortcode.
 *
 * @package Traveljabs
 */

namespace Traveljabs\Shortcodes;

use Traveljabs\Admin\Settings;
use WP_Query;

defined( 'ABSPATH' ) || exit;

/**
 * Registers the client-side clinic search shortcode.
 */
final class ClinicSearch {

	/**
	 * Shortcode tag.
	 */
	private const SHORTCODE = 'search-clinic';

	/**
	 * Number of shortcode instances rendered on the current request.
	 *
	 * @var int
	 */
	private static int $instance = 0;

	/**
	 * Constructor. Registers the shortcode.
	 */
	public function __construct() {
		add_shortcode( self::SHORTCODE, array( $this, 'render' ) );
	}

	/**
	 * Renders the clinic search layout and queues its assets.
	 *
	 * @return string
	 */
	public function render(): string {
		self::$instance++;
		$instance_id = 'traveljabs-clinic-search-' . self::$instance;
		$clinics     = $this->get_clinics();
		$options     = get_option( Settings::OPTION_KEY, array() );
		$api_key     = is_array( $options ) && isset( $options['google_maps_api_key'] )
			? (string) $options['google_maps_api_key']
			: '';

		wp_enqueue_style(
			'traveljabs-clinic-search',
			TRAVELJABS_URL . 'assets/css/clinic-search.css',
			array(),
			TRAVELJABS_VERSION
		);

		wp_enqueue_script(
			'traveljabs-clinic-search',
			TRAVELJABS_URL . 'assets/js/clinic-search.js',
			array(),
			TRAVELJABS_VERSION,
			true
		);

		wp_localize_script(
			'traveljabs-clinic-search',
			'traveljabsClinicSearch',
			array(
				'apiKey'          => sanitize_text_field( $api_key ),
				'initialClinics'  => $clinics,
				'noClinicsText'   => __( 'No clinics found.', 'traveljabs' ),
				'mapErrorText'    => __( 'Google Maps could not be loaded.', 'traveljabs' ),
			)
		);

		return sprintf(
			'<div class="clinic-layout traveljabs-clinic-search" data-clinic-search="%1$s">
				<div class="clinic-sidebar">
					<div class="clinic-search-wrap">
						<label class="screen-reader-text" for="%1$s-input">%2$s</label>
						<input type="text" id="%1$s-input" class="clinic-search-input" placeholder="%3$s" autocomplete="off">
						<button type="button" class="clinic-search-btn">%4$s</button>
					</div>
					<div class="clinic-list"></div>
				</div>
				<div class="clinic-map-wrap">
					<div class="clinic-map"></div>
				</div>
			</div>',
			esc_attr( $instance_id ),
			esc_html__( 'Search clinics', 'traveljabs' ),
			esc_attr__( 'Search clinics by name, address...', 'traveljabs' ),
			esc_html__( 'Submit', 'traveljabs' )
		);
	}

	/**
	 * Gets all published clinics and their public search data.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private function get_clinics(): array {
		$query = new WP_Query(
			array(
				'post_type'      => 'clinic',
				'posts_per_page' => -1,
				'post_status'    => 'publish',
				'orderby'        => 'title',
				'order'          => 'ASC',
			)
		);
		$clinics = array();

		while ( $query->have_posts() ) {
			$query->the_post();
			$post_id = get_the_ID();
			$lat     = $this->get_field( $post_id, 'clinic_latitude' );
			$lng     = $this->get_field( $post_id, 'clinic_longitude' );

			$clinics[] = array(
				'id'        => $post_id,
				'title'     => get_the_title(),
				'link'      => get_permalink(),
				'address'   => $this->get_field( $post_id, 'clinic_address' ),
				'postcode'  => $this->get_field( $post_id, 'clinic_postcode' ),
				'phone'     => $this->get_field( $post_id, 'clinic_phone' ),
				'content'   => wp_strip_all_tags( get_the_content() ),
				'latitude'  => '' !== (string) $lat ? (float) $lat : null,
				'longitude' => '' !== (string) $lng ? (float) $lng : null,
				'thumbnail' => get_the_post_thumbnail_url( $post_id, 'thumbnail' ) ?: '',
			);
		}

		wp_reset_postdata();

		return $clinics;
	}

	/**
	 * Reads an ACF field when available and falls back to post meta.
	 *
	 * @param int    $post_id Post ID.
	 * @param string $key     Field key.
	 * @return mixed
	 */
	private function get_field( int $post_id, string $key ) {
		if ( function_exists( 'get_field' ) ) {
			return get_field( $key, $post_id );
		}

		return get_post_meta( $post_id, $key, true );
	}
}
