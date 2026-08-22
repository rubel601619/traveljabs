<?php
/**
 * Our Clinics custom post type.
 *
 * @package Traveljabs
 */

namespace Traveljabs\PostTypes;

defined( 'ABSPATH' ) || exit;

/**
 * Registers the Our Clinics custom post type.
 */
final class OurClinics extends AbstractPostType {

	/**
	 * Returns the internal, stable post type key.
	 *
	 * @return string
	 */
	protected function get_key(): string {
		return 'clinic';
	}

	/**
	 * Returns the post type labels.
	 *
	 * @return array<string, string>
	 */
	protected function get_labels(): array {
		return $this->build_labels(
			__( 'Our Clinics', 'traveljabs' ),
			__( 'Our Clinic', 'traveljabs' ),
			array(
				'add_new_item' => __( 'Add New Clinic', 'traveljabs' ),
				'edit_item'    => __( 'Edit Clinic', 'traveljabs' ),
				'new_item'     => __( 'New Clinic', 'traveljabs' ),
				'view_item'    => __( 'View Clinic', 'traveljabs' ),
				'view_items'   => __( 'View Clinics', 'traveljabs' ),
				'search_items' => __( 'Search Clinics', 'traveljabs' ),
			)
		);
	}

	/**
	 * Returns the settings key that stores the configured rewrite slug.
	 *
	 * @return string
	 */
	protected function get_slug_setting_key(): string {
		return 'our_clinic_slug';
	}
}
