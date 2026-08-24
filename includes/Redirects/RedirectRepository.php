<?php
/**
 * Database repository for redirect records.
 *
 * @package Traveljabs
 */

namespace Traveljabs\Redirects;

defined( 'ABSPATH' ) || exit;

/**
 * Encapsulates every database operation for redirect rules.
 *
 * All queries use $wpdb with prepared statements where parameters are
 * involved. Business logic lives in Redirect_Manager, not here.
 */
final class RedirectRepository {

	/**
	 * Fully prefixed table name.
	 *
	 * @var string
	 */
	private string $table;

	/**
	 * Constructor. Resolves the table name once.
	 */
	public function __construct() {
		$this->table = RedirectTable::table_name();
	}

	/**
	 * Inserts a new redirect record.
	 *
	 * @param array<string, mixed> $row Column values: source, target,
	 *                                  status_code, is_active, origin.
	 * @return int New record ID, or 0 on failure.
	 */
	public function create( array $row ): int {
		global $wpdb;

		$now     = current_time( 'mysql' );
		$created = $wpdb->insert(
			$this->table,
			array(
				'source'      => (string) $row['source'],
				'target'      => (string) $row['target'],
				'status_code' => (int) $row['status_code'],
				'is_active'   => isset( $row['is_active'] ) ? (int) (bool) $row['is_active'] : 1,
				'origin'      => (string) ( $row['origin'] ?? 'manual' ),
				'created_at'  => $now,
				'updated_at'  => $now,
			),
			array( '%s', '%s', '%d', '%d', '%s', '%s', '%s' )
		);

		return $created ? (int) $wpdb->insert_id : 0;
	}

	/**
	 * Finds a single record by ID.
	 *
	 * @param int $id Record ID.
	 * @return array<string, mixed>|null
	 */
	public function find( int $id ): ?array {
		global $wpdb;

		if ( $id <= 0 ) {
			return null;
		}

		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$this->table} WHERE id = %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is internal.
				$id
			),
			ARRAY_A
		);

		return is_array( $row ) ? $row : null;
	}

	/**
	 * Finds the newest record occupying a source path.
	 *
	 * @param string $source      Normalized source path.
	 * @param bool   $active_only Restrict to active records.
	 * @return array<string, mixed>|null
	 */
	public function find_by_source( string $source, bool $active_only = false ): ?array {
		global $wpdb;

		$sql    = "SELECT * FROM {$this->table} WHERE source = %s"; // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is internal.
		$params = array( $source );

		if ( $active_only ) {
			$sql     .= ' AND is_active = %d';
			$params[] = 1;
		}

		$sql .= ' ORDER BY id DESC LIMIT 1';

		$row = $wpdb->get_row( $wpdb->prepare( $sql, $params ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Built with placeholders above.

		return is_array( $row ) ? $row : null;
	}

	/**
	 * Finds an exact duplicate (same source, target, and status).
	 *
	 * @param string $source     Normalized source path.
	 * @param string $target     Normalized target path or URL.
	 * @param int    $status_code HTTP status code.
	 * @param int    $exclude_id Optional record ID to ignore.
	 * @return array<string, mixed>|null
	 */
	public function find_duplicate( string $source, string $target, int $status_code, int $exclude_id = 0 ): ?array {
		global $wpdb;

		$sql    = "SELECT * FROM {$this->table} WHERE source = %s AND target = %s AND status_code = %d"; // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is internal.
		$params = array( $source, $target, $status_code );

		if ( $exclude_id > 0 ) {
			$sql     .= ' AND id != %d';
			$params[] = $exclude_id;
		}

		$row = $wpdb->get_row( $wpdb->prepare( $sql, $params ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Built with placeholders above.

		return is_array( $row ) ? $row : null;
	}

	/**
	 * Lists active records whose target equals the given value.
	 *
	 * Used to flatten inbound chains when a target becomes a source.
	 *
	 * @param string $target Normalized target value.
	 * @param int    $limit  Maximum rows returned.
	 * @return array<int, array<string, mixed>>
	 */
	public function list_by_target( string $target, int $limit = 100 ): array {
		global $wpdb;

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$this->table} WHERE target = %s AND is_active = 1 ORDER BY id ASC LIMIT %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is internal.
				$target,
				max( 1, $limit )
			),
			ARRAY_A
		);

		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Updates selected columns of a record and refreshes updated_at.
	 *
	 * @param int                  $id      Record ID.
	 * @param array<string, mixed> $columns Allowed column/value pairs.
	 * @return bool
	 */
	public function update( int $id, array $columns ): bool {
		global $wpdb;

		if ( $id <= 0 || empty( $columns ) ) {
			return false;
		}

		$allowed = array_intersect_key(
			$columns,
			array(
				'source'      => true,
				'target'      => true,
				'status_code' => true,
				'is_active'   => true,
				'origin'      => true,
			)
		);

		if ( empty( $allowed ) ) {
			return false;
		}

		$allowed['updated_at'] = current_time( 'mysql' );

		$formats = array();

		foreach ( $allowed as $column => $value ) {
			if ( in_array( $column, array( 'status_code', 'is_active' ), true ) ) {
				$allowed[ $column ] = (int) $value;
				$formats[]          = '%d';
			} else {
				$allowed[ $column ] = (string) $value;
				$formats[]          = '%s';
			}
		}

		return false !== $wpdb->update( $this->table, $allowed, array( 'id' => $id ), $formats, array( '%d' ) );
	}

	/**
	 * Deletes a record by ID.
	 *
	 * @param int $id Record ID.
	 * @return bool
	 */
	public function delete( int $id ): bool {
		global $wpdb;

		if ( $id <= 0 ) {
			return false;
		}

		return false !== $wpdb->delete( $this->table, array( 'id' => $id ), array( '%d' ) );
	}

	/**
	 * Toggles or sets the active state of a record.
	 *
	 * @param int         $id     Record ID.
	 * @param bool|null   $active True/false to set explicitly; null flips.
	 * @return bool
	 */
	public function set_active( int $id, ?bool $active = null ): bool {
		$record = $this->find( $id );

		if ( null === $record ) {
			return false;
		}

		$new_state = null === $active ? ! ( (int) $record['is_active'] ) : $active;

		return $this->update(
			$id,
			array(
				'is_active' => (int) (bool) $new_state,
			)
		);
	}

	/**
	 * Returns paginated records plus the total row count.
	 *
	 * @param array<string, mixed> $args {
	 *     Query arguments.
	 *
	 *     @type string $search    Search term matched against source/target.
	 *     @type string $status    all|active|inactive filter.
	 *     @type int    $paged     1-based page number.
	 *     @type int    $per_page  Rows per page.
	 * }
	 * @return array{rows: array<int, array<string, mixed>>, total: int, pages: int}
	 */
	public function paginate( array $args = array() ): array {
		global $wpdb;

		$search   = isset( $args['search'] ) ? trim( (string) $args['search'] ) : '';
		$status   = isset( $args['status'] ) ? (string) $args['status'] : 'all';
		$paged    = max( 1, isset( $args['paged'] ) ? absint( $args['paged'] ) : 1 );
		$per_page = min( 100, max( 1, isset( $args['per_page'] ) ? absint( $args['per_page'] ) : 20 ) );

		$where  = array();
		$params = array();

		if ( '' !== $search ) {
			$like     = '%' . $wpdb->esc_like( $search ) . '%';
			$where[]  = '(source LIKE %s OR target LIKE %s)';
			$params[] = $like;
			$params[] = $like;
		}

		if ( 'active' === $status || 'inactive' === $status ) {
			$where[]  = 'is_active = %d';
			$params[] = 'active' === $status ? 1 : 0;
		}

		$where_sql = empty( $where ) ? '' : 'WHERE ' . implode( ' AND ', $where );

		$count_sql = "SELECT COUNT(*) FROM {$this->table} {$where_sql}"; // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Placeholders only; params bound below.

		$total = empty( $params )
			? (int) $wpdb->get_var( $count_sql ) // phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- Administrative listing query.
			: (int) $wpdb->get_var( $wpdb->prepare( $count_sql, $params ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- Prepared above.

		$pages    = max( 1, (int) ceil( $total / $per_page ) );
		$paged    = min( $paged, $pages );
		$offset   = ( $paged - 1 ) * $per_page;
		$list_sql = "SELECT * FROM {$this->table} {$where_sql} ORDER BY updated_at DESC, id DESC LIMIT %d OFFSET %d"; // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Placeholders only.

		$list_params   = $params;
		$list_params[] = $per_page;
		$list_params[] = $offset;

		$rows = $wpdb->get_results( $wpdb->prepare( $list_sql, $list_params ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- Prepared above.

		return array(
			'rows'  => is_array( $rows ) ? $rows : array(),
			'total' => $total,
			'pages' => $pages,
		);
	}
}
