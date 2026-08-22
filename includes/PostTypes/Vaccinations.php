<?php
/**
 * Vaccinations custom post type.
 *
 * @package Traveljabs
 */

namespace Traveljabs\PostTypes;

defined( 'ABSPATH' ) || exit;

/**
 * Registers the Vaccinations custom post type.
 */
final class Vaccinations extends AbstractPostType {

	/**
	 * Returns the internal, stable post type key.
	 *
	 * @return string
	 */
	protected function get_key(): string {
		return 'vaccination';
	}

	/**
	 * Returns the post type labels.
	 *
	 * @return array<string, string>
	 */
	protected function get_labels(): array {
		return $this->build_labels(
			__( 'Vaccinations', 'traveljabs' ),
			__( 'Vaccination', 'traveljabs' )
		);
	}

	/**
	 * Returns the settings key that stores the configured rewrite slug.
	 *
	 * @return string
	 */
	protected function get_slug_setting_key(): string {
		return 'vaccination_slug';
	}
}
