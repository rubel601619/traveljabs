<?php
/**
 * Destinations custom post type.
 *
 * @package Traveljabs
 */

namespace Traveljabs\PostTypes;

defined( 'ABSPATH' ) || exit;

/**
 * Registers the Destinations custom post type.
 */
final class Destinations extends AbstractPostType {

	/**
	 * Returns the internal, stable post type key.
	 *
	 * @return string
	 */
	protected function get_key(): string {
		return 'destination';
	}

	/**
	 * Returns the post type labels.
	 *
	 * @return array<string, string>
	 */
	protected function get_labels(): array {
		return $this->build_labels(
			__( 'Destinations', 'traveljabs' ),
			__( 'Destination', 'traveljabs' )
		);
	}

	/**
	 * Returns the settings key that stores the configured rewrite slug.
	 *
	 * @return string
	 */
	protected function get_slug_setting_key(): string {
		return 'destinations_slug';
	}
}
