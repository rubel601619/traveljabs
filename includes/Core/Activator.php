<?php
/**
 * Plugin activation and deactivation routines.
 *
 * @package Traveljabs
 */

namespace Traveljabs\Core;

use Traveljabs\Admin\Settings;
use Traveljabs\PostTypes\Destinations;
use Traveljabs\PostTypes\OurClinics;
use Traveljabs\PostTypes\Vaccinations;
use Traveljabs\Redirects\RedirectTable;

defined( 'ABSPATH' ) || exit;

/**
 * Handles lifecycle hooks for the plugin.
 */
final class Activator {

	/**
	 * Runs on plugin activation.
	 *
	 * Sets default settings when missing, registers post types and
	 * taxonomies, then flushes rewrite rules. Existing settings are never
	 * overwritten.
	 *
	 * @return void
	 */
	public static function activate(): void {
		if ( false === get_option( Settings::OPTION_KEY, false ) ) {
			add_option( Settings::OPTION_KEY, Settings::get_slug_fields() );
		}

		( new Destinations() )->register_post_type();
		( new OurClinics() )->register_post_type();
		( new Vaccinations() )->register_post_type();

		RedirectTable::install();

		flush_rewrite_rules();
	}

	/**
	 * Runs on plugin deactivation.
	 *
	 * Flushes rewrite rules only. No settings, posts, taxonomies, or user
	 * data are removed.
	 *
	 * @return void
	 */
	public static function deactivate(): void {
		flush_rewrite_rules();
	}
}
