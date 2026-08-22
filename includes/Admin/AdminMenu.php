<?php
/**
 * Top-level admin menu registration.
 *
 * @package Traveljabs
 */

namespace Traveljabs\Admin;

defined( 'ABSPATH' ) || exit;

/**
 * Registers the single Traveljabs top-level admin menu.
 *
 * The menu slug points to the Destinations post list screen so that custom
 * post types configured with this slug as their `show_in_menu` value are
 * rendered as submenus of Traveljabs instead of independent top-level menus.
 */
final class AdminMenu {

	/**
	 * Parent menu slug shared by all Traveljabs submenu pages.
	 */
	public const PARENT_SLUG = 'edit.php?post_type=destination';

	/**
	 * Constructor. Hooks the menu registration into WordPress.
	 */
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'register_top_level_menu' ), 9 );
	}

	/**
	 * Registers the top-level Traveljabs menu.
	 *
	 * Priority 9 ensures the parent exists before core attaches custom post
	 * type submenus at priority 10 via _add_post_type_submenus().
	 *
	 * @return void
	 */
	public function register_top_level_menu(): void {
		add_menu_page(
			__( 'Traveljabs', 'traveljabs' ),
			__( 'Traveljabs', 'traveljabs' ),
			'edit_posts',
			self::PARENT_SLUG,
			'',
			'dashicons-airplane',
			20
		);
	}
}
