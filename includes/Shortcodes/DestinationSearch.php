<?php
/**
 * Destination search shortcode.
 *
 * @package Traveljabs
 */

namespace Traveljabs\Shortcodes;

defined( 'ABSPATH' ) || exit;

/**
 * Registers the client-side destination search shortcode.
 */
final class DestinationSearch {

	/**
	 * Shortcode tag.
	 */
	private const SHORTCODE = 'search-destination';

	/**
	 * Constructor. Registers the shortcode.
	 */
	public function __construct() {
		add_shortcode( self::SHORTCODE, array( $this, 'render' ) );
	}

	/**
	 * Renders the search shell and queues its frontend asset.
	 *
	 * @return string
	 */
	public function render(): string {
		wp_enqueue_style(
			'traveljabs-destination-search',
			TRAVELJABS_URL . 'assets/css/destination-search.css',
			array(),
			TRAVELJABS_VERSION
		);

		wp_enqueue_script(
			'traveljabs-destination-search',
			TRAVELJABS_URL . 'assets/js/destination-search.js',
			array(),
			TRAVELJABS_VERSION,
			true
		);

		wp_localize_script(
			'traveljabs-destination-search',
			'traveljabsDestinationSearch',
			array(
				'restUrl' => esc_url_raw( rest_url( 'wp/v2/destination' ) ),
				'loadingText' => __( 'Loading destinations...', 'traveljabs' ),
				'errorText' => __( 'Could not load destinations. Please try again.', 'traveljabs' ),
				'notFoundText' => __( 'No destination found.', 'traveljabs' ),
			)
		);

		return '<div style="background: white;">'
		    .'<div class="traveljabs-destination-text">Find out what vaccinations you may need for your trip</div>'
		    .'<div class="traveljabs-destination-search">'
			. '<label class="screen-reader-text">'
			. esc_html__( 'Search the destination', 'traveljabs' )
			. '</label>'
			. '<input type="search" class="traveljabs-destination-search__input" placeholder="'
			. esc_attr__( 'Search the destination', 'traveljabs' )
			. '" autocomplete="off" disabled>'
			. '<ul class="traveljabs-destination-search__results" aria-live="polite"></ul>'
			. '</div>'
			. '</div>';
	}
}
