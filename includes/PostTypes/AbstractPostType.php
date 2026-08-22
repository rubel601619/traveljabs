<?php
/**
 * Abstract custom post type definition.
 *
 * @package Traveljabs
 */

namespace Traveljabs\PostTypes;

use Traveljabs\Admin\AdminMenu;
use Traveljabs\Admin\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Shared registration logic for all Traveljabs custom post types.
 */
abstract class AbstractPostType {

	/**
	 * Constructor. Hooks the post type registration into WordPress.
	 */
	public function __construct() {
		add_action( 'init', array( $this, 'register_post_type' ) );
	}

	/**
	 * Registers the post type on the init hook.
	 *
	 * @return void
	 */
	public function register_post_type(): void {
		if ( post_type_exists( $this->get_key() ) ) {
			return;
		}

		register_post_type( $this->get_key(), $this->get_args() );
	}

	/**
	 * Returns the internal, stable post type key.
	 *
	 * @return string
	 */
	abstract protected function get_key(): string;

	/**
	 * Returns the post type labels.
	 *
	 * @return array<string, string>
	 */
	abstract protected function get_labels(): array;

	/**
	 * Returns the settings key that stores the configured rewrite slug.
	 *
	 * @return string
	 */
	abstract protected function get_slug_setting_key(): string;

	/**
	 * Builds the full registration arguments.
	 *
	 * @return array<string, mixed>
	 */
	protected function get_args(): array {
		return array(
			'labels'              => $this->get_labels(),
			'public'              => true,
			'publicly_queryable'  => true,
			'show_ui'             => true,
			'show_in_menu'        => AdminMenu::PARENT_SLUG,
			'show_in_rest'        => true,
			'has_archive'         => false,
			'hierarchical'        => false,
			'map_meta_cap'        => true,
			'capability_type'     => 'post',
			'supports'            => array( 'title', 'editor', 'thumbnail', 'excerpt', 'revisions' ),
			'taxonomies'          => array( 'category', 'post_tag' ),
			'rewrite'             => array(
				'slug'       => $this->get_rewrite_slug(),
				'with_front' => false,
			),
		);
	}

	/**
	 * Returns the configured rewrite slug with a safe default fallback.
	 *
	 * The internal post type key is never modified by this value.
	 *
	 * @return string
	 */
	protected function get_rewrite_slug(): string {
		$key     = $this->get_slug_setting_key();
		$options = get_option( Settings::OPTION_KEY, array() );
		$slug    = isset( $options[ $key ] ) ? sanitize_title( (string) $options[ $key ] ) : '';

		return '' !== $slug ? $slug : Settings::get_slug_fields()[ $key ];
	}

	/**
	 * Builds a standard label set from plural and singular names.
	 *
	 * @param string                $plural    Plural name.
	 * @param string                $singular  Singular name.
	 * @param array<string, string> $overrides Optional per-label overrides.
	 * @return array<string, string>
	 */
	protected function build_labels( string $plural, string $singular, array $overrides = array() ): array {
		return array_replace(
			array(
				'name'                  => $plural,
				'singular_name'         => $singular,
				'menu_name'             => $plural,
				'all_items'             => $plural,
				'add_new'               => __( 'Add New', 'traveljabs' ),
				'add_new_item'          => sprintf( __( 'Add New %s', 'traveljabs' ), $singular ),
				'edit_item'             => sprintf( __( 'Edit %s', 'traveljabs' ), $singular ),
				'new_item'              => sprintf( __( 'New %s', 'traveljabs' ), $singular ),
				'view_item'             => sprintf( __( 'View %s', 'traveljabs' ), $singular ),
				'view_items'            => sprintf( __( 'View %s', 'traveljabs' ), $plural ),
				'search_items'          => sprintf( __( 'Search %s', 'traveljabs' ), $plural ),
				'not_found'             => __( 'No items found.', 'traveljabs' ),
				'not_found_in_trash'    => __( 'No items found in Trash.', 'traveljabs' ),
				'parent_item_colon'     => sprintf( __( 'Parent %s:', 'traveljabs' ), $singular ),
				'filter_items_list'     => __( 'Filter items list', 'traveljabs' ),
				'items_list_navigation' => __( 'Items list navigation', 'traveljabs' ),
				'items_list'            => __( 'Items list', 'traveljabs' ),
				'item_published'        => __( 'Item published.', 'traveljabs' ),
				'item_updated'          => __( 'Item updated.', 'traveljabs' ),
			),
			$overrides
		);
	}
}
