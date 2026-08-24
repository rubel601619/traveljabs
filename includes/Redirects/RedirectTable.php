<?php
/**
 * Redirects database table installation and schema versioning.
 *
 * @package Traveljabs
 */

namespace Traveljabs\Redirects;

defined( 'ABSPATH' ) || exit;

/**
 * Creates and versions the {$wpdb->prefix}traveljabs_redirects table.
 *
 * The table is only created or migrated when the stored database schema
 * version differs from SCHEMA_VERSION, never on every request.
 */
final class RedirectTable {

	/**
	 * Current database schema version. Bump to trigger a migration.
	 */
	public const SCHEMA_VERSION = '1.0';

	/**
	 * Option key storing the installed schema version.
	 */
	public const DB_VERSION_OPTION = 'traveljabs_db_version';

	/**
	 * Returns the fully prefixed table name.
	 *
	 * @return string
	 */
	public static function table_name(): string {
		global $wpdb;

		return $wpdb->prefix . 'traveljabs_redirects';
	}

	/**
	 * Determines whether the table requires (re)installation.
	 *
	 * @return bool
	 */
	public static function needs_install(): bool {
		return get_option( self::DB_VERSION_OPTION ) !== self::SCHEMA_VERSION;
	}

	/**
	 * Installs or migrates the redirects table via dbDelta().
	 *
	 * Safe to call repeatedly; the work is gated by the schema version.
	 *
	 * @return void
	 */
	public static function install(): void {
		global $wpdb;

		if ( ! self::needs_install() ) {
			return;
		}

		$table           = self::table_name();
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$table} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			source VARCHAR(2048) NOT NULL,
			target VARCHAR(2048) NOT NULL,
			status_code SMALLINT UNSIGNED NOT NULL DEFAULT 301,
			is_active TINYINT(1) NOT NULL DEFAULT 1,
			origin VARCHAR(20) NOT NULL DEFAULT 'manual',
			created_at DATETIME NOT NULL,
			updated_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			KEY idx_source (source(191)),
			KEY idx_is_active (is_active)
		) {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );

		update_option( self::DB_VERSION_OPTION, self::SCHEMA_VERSION, false );
	}
}
