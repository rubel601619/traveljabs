<?php
/**
 * Redirect business logic: automatic slug-change redirects, validation,
 * chain/loop handling, and frontend processing.
 *
 * @package Traveljabs
 */

namespace Traveljabs\Redirects;

use Traveljabs\Admin\Settings;
use WP;
use WP_Post;

defined( 'ABSPATH' ) || exit;

/**
 * Handles redirect rules and their lifecycle.
 *
 * Database access is delegated to RedirectRepository. Admin UI lives in
 * RedirectAdmin.
 */
final class RedirectManager {

	/**
	 * Supported HTTP status codes.
	 */
	public const STATUS_CODES = array( 301, 302, 307, 308 );

	/**
	 * Human-readable status code labels used in the admin select field.
	 *
	 * @var array<int, string>
	 */
	private const STATUS_LABELS = array(
		301 => 'Permanent Redirect',
		302 => 'Temporary Redirect',
		307 => 'Temporary Redirect',
		308 => 'Permanent Redirect',
	);

	/**
	 * Maximum hops followed when resolving chains or detecting loops.
	 */
	private const MAX_CHAIN_DEPTH = 10;

	/**
	 * Maximum posts processed when syncing CPT rewrite-slug changes.
	 */
	private const MAX_SLUG_SYNC_POSTS = 500;

	/**
	 * Maps settings keys to their post type keys.
	 *
	 * @var array<string, string>
	 */
	private const SLUG_SETTING_POST_TYPES = array(
		'destinations_slug' => 'destination',
		'our_clinic_slug'   => 'clinic',
		'vaccination_slug'  => 'vaccination',
	);

	/**
	 * Repository instance.
	 *
	 * @var RedirectRepository
	 */
	private RedirectRepository $repository;

	/**
	 * Constructor. Registers lifecycle hooks for every module concern.
	 *
	 * @param RedirectRepository|null $repository Optional repository override.
	 */
	public function __construct( ?RedirectRepository $repository = null ) {
		$this->repository = $repository ?? new RedirectRepository();

		add_action( 'post_updated', array( $this, 'handle_post_updated' ), 10, 3 );
		add_action( 'update_option_' . Settings::OPTION_KEY, array( $this, 'handle_settings_updated' ), 20, 2 );
		add_action( 'parse_request', array( $this, 'process_request' ) );
		add_action( 'template_redirect', array( $this, 'process_not_found' ), 1 );
	}

	/* ---------------------------------------------------------------------
	 * Automatic redirects: post permalink changes
	 * ------------------------------------------------------------------- */

	/**
	 * Creates a 301 redirect whenever a public post type's permalink changes.
	 *
	 * Only real permalink changes trigger a redirect; title/content edits
	 * with an unchanged slug never create one.
	 *
	 * @param int     $post_id Post ID.
	 * @param WP_Post $after   Post object after the update.
	 * @param WP_Post $before  Post object before the update.
	 * @return void
	 */
	public function handle_post_updated( int $post_id, WP_Post $after, WP_Post $before ): void {
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
			return;
		}

		if ( ! $this->is_redirectable_post_type( $after->post_type ) ) {
			return;
		}

		// Both snapshots must be publicly visible; otherwise no live URL existed.
		if ( 'publish' !== $before->post_status || 'publish' !== $after->post_status ) {
			return;
		}

		$old_permalink = get_permalink( $before );
		$new_permalink = get_permalink( $after );

		if ( ! is_string( $old_permalink ) || ! is_string( $new_permalink ) || '' === $old_permalink || '' === $new_permalink ) {
			return;
		}

		$source = self::normalize_source( $old_permalink );
		$target = self::normalize_target( $new_permalink );

		if ( null === $source || null === $target || $source === $target ) {
			return;
		}

		$this->create_redirect( $source, $target, 301, 'auto' );
	}

	/**
	 * Regenerates redirects when a configurable CPT rewrite slug changes.
	 *
	 * Example: Destinations moving from /destinations/london/ to /places/london/
	 * creates /destinations/london/ -> /places/london/ for every published post.
	 *
	 * @param mixed $old_value Previous settings option value.
	 * @param mixed $new_value New settings option value.
	 * @return void
	 */
	public function handle_settings_updated( $old_value, $new_value ): void {
		$old_value = is_array( $old_value ) ? $old_value : array();
		$new_value = is_array( $new_value ) ? $new_value : array();

		foreach ( self::SLUG_SETTING_POST_TYPES as $setting_key => $post_type ) {
			$old_slug = isset( $old_value[ $setting_key ] ) ? sanitize_title( (string) $old_value[ $setting_key ] ) : '';
			$new_slug = isset( $new_value[ $setting_key ] ) ? sanitize_title( (string) $new_value[ $setting_key ] ) : '';

			if ( '' === $old_slug || '' === $new_slug || $old_slug === $new_slug ) {
				continue;
			}

			$this->sync_rewrite_slug_change( $post_type, $old_slug, $new_slug );
		}
	}

	/* ---------------------------------------------------------------------
	 * Creation API (manual + automatic)
	 * ------------------------------------------------------------------- */

	/**
	 * Creates multiple manual redirects pointing at one target.
	 *
	 * Each valid source becomes its own record. Duplicates are reported and
	 * skipped; conflicting active sources are reported, never overwritten.
	 *
	 * @param array<int|string, mixed> $sources_raw Raw source values.
	 * @param string                   $target_raw  Raw target value.
	 * @param int|string               $status_raw  Raw status code.
	 * @param bool                     $activate    Whether records start active.
	 * @return array{created: int, duplicates: string[], conflicts: string[], errors: string[]}
	 */
	public function create_manual_batch( array $sources_raw, string $target_raw, $status_raw, bool $activate = true ): array {
		$report = array(
			'created'    => 0,
			'duplicates' => array(),
			'conflicts'  => array(),
			'errors'     => array(),
		);

		$status = $this->validate_status_code( $status_raw );

		if ( null === $status ) {
			$report['errors'][] = __( 'Invalid status code submitted.', 'traveljabs' );

			return $report;
		}

		$target = self::normalize_target( wp_unslash( $target_raw ) );

		if ( null === $target ) {
			$report['errors'][] = __( 'The target must be a local path (e.g. /new-url/) or a valid http(s) URL.', 'traveljabs' );

			return $report;
		}

		$sources = $this->collect_unique_sources( $sources_raw, $report );

		foreach ( $sources as $source ) {
			if ( $source === $target ) {
				$report['errors'][] = sprintf(
					/* translators: %s: source path. */
					esc_html__( 'Source %s equals the target; redirects cannot point at themselves.', 'traveljabs' ),
					$source
				);
				continue;
			}

			if ( $this->creates_loop( $source, $target, 0 ) ) {
				$report['errors'][] = sprintf(
					/* translators: %s: source path. */
					esc_html__( '%s was rejected because it would create a redirect loop.', 'traveljabs' ),
					$source
				);
				continue;
			}

			$resolved_target = $this->resolve_chain( $target, 0 );

			$existing = $this->repository->find_by_source( $source );

			if ( null !== $existing ) {
				$is_active   = (int) $existing['is_active'];
				$same_target = (string) $existing['target'] === $resolved_target;
				$same_status = (int) $existing['status_code'] === $status;

				if ( $is_active && $same_target && $same_status ) {
					$report['duplicates'][] = $source;
					continue;
				}

				if ( $is_active ) {
					$report['conflicts'][] = sprintf(
						/* translators: 1: source path, 2: existing target. */
						esc_html__( '%1$s already redirects to %2$s. Edit that rule instead of creating a conflict.', 'traveljabs' ),
						$source,
						(string) $existing['target']
					);
					continue;
				}

				// An inactive record occupies this source: refresh and reuse it.
				$this->repository->update(
					(int) $existing['id'],
					array(
						'target'      => $resolved_target,
						'status_code' => $status,
						'is_active'   => (int) $activate,
						'origin'      => 'manual',
					)
				);
				++$report['created'];
				continue;
			}

			$inserted = $this->repository->create(
				array(
					'source'      => $source,
					'target'      => $resolved_target,
					'status_code' => $status,
					'is_active'   => $activate,
					'origin'      => 'manual',
				)
			);

			if ( $inserted > 0 ) {
				++$report['created'];
				$this->flatten_inbound_links( $source, $resolved_target );
			} else {
				$report['errors'][] = sprintf(
					/* translators: %s: source path. */
					esc_html__( '%s could not be saved to the database.', 'traveljabs' ),
					$source
				);
			}
		}

		return $report;
	}

	/**
	 * Updates an existing single redirect record after full validation.
	 *
	 * The same duplicate and loop checks used during creation apply here.
	 *
	 * @param int          $id          Record ID.
	 * @param array<mixed> $sources_raw Single-element raw sources list.
	 * @param string       $target_raw  Raw target value.
	 * @param int|string   $status_raw  Raw status code.
	 * @param bool         $active      Active state.
	 * @return array{updated: bool, errors: string[]}
	 */
	public function update_manual( int $id, array $sources_raw, string $target_raw, $status_raw, bool $active ): array {
		$errors = array();
		$record = $this->repository->find( $id );

		if ( null === $record ) {
			$errors[] = __( 'The redirect you tried to edit no longer exists.', 'traveljabs' );

			return array(
				'updated' => false,
				'errors'  => $errors,
			);
		}

		$status = $this->validate_status_code( $status_raw );

		if ( null === $status ) {
			$errors[] = __( 'Invalid status code submitted.', 'traveljabs' );

			return array(
				'updated' => false,
				'errors'  => $errors,
			);
		}

		$sources = $this->collect_unique_sources( $sources_raw, $errors );

		if ( count( $sources ) > 1 ) {
			$errors[] = __( 'Editing accepts exactly one source per redirect record.', 'traveljabs' );

			return array(
				'updated' => false,
				'errors'  => $errors,
			);
		}

		$source = $sources[0] ?? '';
		$target = self::normalize_target( wp_unslash( $target_raw ) );

		if ( '' === $source ) {
			$errors[] = __( 'A non-empty source path is required.', 'traveljabs' );
		}

		if ( null === $target ) {
			$errors[] = __( 'The target must be a local path (e.g. /new-url/) or a valid http(s) URL.', 'traveljabs' );
		}

		if ( ! empty( $errors ) ) {
			return array(
				'updated' => false,
				'errors'  => $errors,
			);
		}

		if ( $source === $target ) {
			$errors[] = __( 'Source and target cannot be identical.', 'traveljabs' );

			return array(
				'updated' => false,
				'errors'  => $errors,
			);
		}

		$resolved_target = $this->resolve_chain( (string) $target, $id );

		if ( $this->creates_loop( $source, $resolved_target, $id ) ) {
			$errors[] = __( 'This change would create a redirect loop and was rejected.', 'traveljabs' );

			return array(
				'updated' => false,
				'errors'  => $errors,
			);
		}

		$duplicate = $this->repository->find_duplicate( $source, $resolved_target, $status, $id );

		if ( null !== $duplicate ) {
			$errors[] = sprintf(
				/* translators: %s: source path. */
				esc_html__( 'An identical redirect already exists for %s.', 'traveljabs' ),
				$source
			);

			return array(
				'updated' => false,
				'errors'  => $errors,
			);
		}

		$conflict = $this->repository->find_by_source( $source );

		if ( null !== $conflict && (int) $conflict['id'] !== $id && (int) $conflict['is_active'] ) {
			$errors[] = sprintf(
				/* translators: 1: source path, 2: existing target. */
				esc_html__( '%1$s is already used by another active redirect targeting %2$s.', 'traveljabs' ),
				$source,
				(string) $conflict['target']
			);

			return array(
				'updated' => false,
				'errors'  => $errors,
			);
		}

		$updated = $this->repository->update(
			$id,
			array(
				'source'      => $source,
				'target'      => $resolved_target,
				'status_code' => $status,
				'is_active'   => (int) $active,
			)
		);

		if ( $updated ) {
			$this->flatten_inbound_links( $source, $resolved_target );
		} else {
			$errors[] = __( 'The redirect could not be updated in the database.', 'traveljabs' );
		}

		return array(
			'updated' => $updated,
			'errors'  => $errors,
		);
	}

	/**
	 * Core creation routine shared by manual and automatic flows.
	 *
	 * Resolves chains ahead of time, refuses loops and exact duplicates, and
	 * updates the occupying rule when the caller owns it automatically.
	 *
	 * @param string $source Normalized source path.
	 * @param string $target Normalized target path or URL.
	 * @param int    $status HTTP status code.
	 * @param string $origin 'auto' or 'manual'.
	 * @return bool True when a record was created or meaningfully updated.
	 */
	public function create_redirect( string $source, string $target, int $status = 301, string $origin = 'auto' ): bool {
		if ( $source === $target || ! in_array( $status, self::STATUS_CODES, true ) ) {
			return false;
		}

		if ( $this->creates_loop( $source, $target, 0 ) ) {
			return false;
		}

		$resolved_target = $this->resolve_chain( $target, 0 );
		$existing        = $this->repository->find_by_source( $source );

		if ( null !== $existing ) {
			$same_target = (string) $existing['target'] === $resolved_target;

			if ( $same_target && (int) $existing['status_code'] === $status ) {
				return false; // Exact duplicate; nothing to do.
			}

			if ( 'auto' !== $origin || 'auto' !== (string) $existing['origin'] || (int) $existing['is_active'] ) {
				// Never overwrite manual rules or active foreign rules silently.
				return false;
			}

			return (bool) $this->repository->update(
				(int) $existing['id'],
				array(
					'target'      => $resolved_target,
					'status_code' => $status,
					'is_active'   => 1,
				)
			);
		}

		$inserted = $this->repository->create(
			array(
				'source'      => $source,
				'target'      => $resolved_target,
				'status_code' => $status,
				'is_active'   => 1,
				'origin'      => $origin,
			)
		);

		if ( 0 === $inserted ) {
			return false;
		}

		$this->flatten_inbound_links( $source, $resolved_target );

		return true;
	}

	/* ---------------------------------------------------------------------
	 * Frontend processing
	 * ------------------------------------------------------------------- */

	/**
	 * Matches the parsed request path against active redirects.
	 *
	 * Runs on parse_request, before WordPress query/template processing, so
	 * matched requests exit as early as practical. Never runs in admin.
	 *
	 * @param WP $wp WordPress environment instance.
	 * @return void
	 */
	public function process_request( WP $wp ): void {
		if ( is_admin() || wp_doing_ajax() || wp_doing_cron() ) {
			return;
		}

		$path = isset( $wp->request ) ? trim( (string) $wp->request ) : '';

		if ( '' === $path || preg_match( '#^(?:wp-admin|wp-login\.php|wp-json|xmlrpc\.php|robots\.txt|favicon\.ico|sitemap(?:[\._/\x23-]|$)|feed(?:[/?\x23]|$))#i', $path ) ) {
			return;
		}

		$this->maybe_send_redirect( '/' . ltrim( $path, '/' ) );
	}

	/**
	 * Last-chance match for 404 responses at template_redirect.
	 *
	 * @return void
	 */
	public function process_not_found(): void {
		if ( is_admin() || ! is_404() ) {
			return;
		}

		global $wp;

		$path = isset( $wp->request ) ? trim( (string) $wp->request ) : '';

		if ( '' !== $path ) {
			$this->maybe_send_redirect( '/' . ltrim( $path, '/' ) );
		}
	}

	/**
	 * Sends the redirect response when the path matches an active rule.
	 *
	 * Query strings are preserved unless the target defines its own query
	 * parameters.
	 *
	 * @param string $path Request path beginning with a slash.
	 * @return bool True when a redirect response was sent.
	 */
	public function maybe_send_redirect( string $path ): bool {
		$normalized = self::normalize_source( $path );

		if ( null === $normalized ) {
			return false;
		}

		$record = $this->repository->find_by_source( $normalized, true );

		if ( null === $record ) {
			return false;
		}

		$location = (string) $record['target'];

		$query_string = isset( $_SERVER['QUERY_STRING'] ) ? (string) $_SERVER['QUERY_STRING'] : '';

		if ( '' !== $query_string && false === strpos( $location, '?' ) ) {
			$location .= '?' . $query_string;
		}

		$status = (int) $record['status_code'];
		$status = in_array( $status, self::STATUS_CODES, true ) ? $status : 301;
		$status = (int) apply_filters( 'traveljabs_redirect_status_code', $status, $record );

		wp_redirect( $location, $status, 'Traveljabs' ); // phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect -- External targets are supported by design.

		exit;
	}

	/* ---------------------------------------------------------------------
	 * URL normalization
	 * ------------------------------------------------------------------- */

	/**
	 * Normalizes any URL into a canonical site-relative source path.
	 *
	 * Strategy used consistently across storage, matching, and comparison:
	 * absolute same-site URLs are reduced to paths; a leading slash is added;
	 * trailing slashes are removed (except the root); the path is decoded
	 * once. Root and empty paths are invalid sources.
	 *
	 * @param string $url Raw URL or path.
	 * @return string|null Normalized path, or null when unusable.
	 */
	public static function normalize_source( string $url ): ?string {
		$url = trim( $url );

		if ( '' === $url ) {
			return null;
		}

		$parsed = wp_parse_url( $url );

		if ( ! is_array( $parsed ) ) {
			return null;
		}

		if ( isset( $parsed['host'] ) && ! self::is_local_host( (string) $parsed['host'] ) ) {
			return null;
		}

		$path = isset( $parsed['path'] ) ? (string) $parsed['path'] : '';

		$path = '/' . ltrim( str_replace( '\\', '/', $path ), '/' );
		$path = rtrim( $path, '/' );
		$path = urldecode( $path );

		if ( '' === $path || '/' === $path ) {
			return null; // Never allow redirecting the entire site root.
		}

		if ( preg_match( '#[\x00-\x1f\x7f]#', $path ) ) {
			return null;
		}

		return $path;
	}

	/**
	 * Normalizes a target value.
	 *
	 * Local targets become canonical paths (the root "/" is allowed as a
	 * homepage target). External targets must be well-formed http(s) URLs
	 * and are stored via esc_url_raw(). Unsafe schemes are rejected.
	 *
	 * @param string $url Raw target value.
	 * @return string|null Normalized target, or null when invalid.
	 */
	public static function normalize_target( string $url ): ?string {
		$url = trim( $url );

		if ( '' === $url ) {
			return null;
		}

		$parsed = wp_parse_url( $url );

		if ( ! is_array( $parsed ) ) {
			return null;
		}

		if ( isset( $parsed['scheme'], $parsed['host'] ) ) {
			$scheme = strtolower( (string) $parsed['scheme'] );

			if ( ! in_array( $scheme, array( 'http', 'https' ), true ) ) {
				return null;
			}

			$query_suffix = isset( $parsed['query'] ) && '' !== (string) $parsed['query']
				? '?' . (string) $parsed['query']
				: '';

			if ( self::is_local_host( (string) $parsed['host'] ) ) {
				// Local absolute URLs collapse to internal paths; root allowed.
				$path = self::normalize_source( $url );

				if ( null !== $path ) {
					return '' === $query_suffix ? $path : $path . $query_suffix;
				}

				$path_only = rtrim( '/' . ltrim( (string) ( $parsed['path'] ?? '' ), '/' ), '/' );

				return ( '' === $path_only ? '/' : $path_only ) . $query_suffix;
			}

			$external = esc_url_raw( $url, array( 'http', 'https' ) );

			if ( '' === $external || strlen( $external ) > 2048 || false === strpos( $external, '://' ) ) {
				return null;
			}

			return $external;
		}

		if ( isset( $parsed['host'] ) || isset( $parsed['scheme'] ) ) {
			return null; // Malformed combination such as "https:/foo".
		}

		$path = self::normalize_path_only( (string) ( $parsed['path'] ?? '' ) );

		if ( null === $path ) {
			return null;
		}

		return isset( $parsed['query'] ) && '' !== (string) $parsed['query']
			? $path . '?' . (string) $parsed['query']
			: $path;
	}

	/**
	 * Returns translated labels for the supported status codes.
	 *
	 * @return array<int, string>
	 */
	public static function get_status_choices(): array {
		$choices = array();

		foreach ( self::STATUS_CODES as $code ) {
			$choices[ $code ] = sprintf(
				/* translators: 1: HTTP status code, 2: redirect type. */
				__( '%1$d - %2$s', 'traveljabs' ),
				$code,
				self::STATUS_LABELS[ $code ]
			);
		}

		return $choices;
	}

	/**
	 * Validates a submitted status code against the supported whitelist.
	 *
	 * @param int|string $raw Raw status input.
	 * @return int|null Valid code, or null when unsupported.
	 */
	public function validate_status_code( $raw ): ?int {
		$code = absint( $raw );

		return in_array( $code, self::STATUS_CODES, true ) ? $code : null;
	}

	/* ---------------------------------------------------------------------
	 * Internal helpers
	 * ------------------------------------------------------------------- */

	/**
	 * Determines whether the post type participates in auto-redirects.
	 *
	 * Works generically for every public post type; attachments and menu
	 * items are excluded.
	 *
	 * @param string $post_type Post type key.
	 * @return bool
	 */
	private function is_redirectable_post_type( string $post_type ): bool {
		if ( in_array( $post_type, array( 'attachment', 'nav_menu_item' ), true ) ) {
			return false;
		}

		$object = get_post_type_object( $post_type );

		return null !== $object && ! empty( $object->public );
	}

	/**
	 * Follows active source-to-target hops and returns the final destination.
	 *
	 * Collecting every visited hop lets callers flatten inbound links so
	 * repeated slug changes never build long chains.
	 *
	 * @param string $target     Starting normalized target.
	 * @param int    $exclude_id Record ID to ignore while walking.
	 * @return string Final resolved target.
	 */
	private function resolve_chain( string $target, int $exclude_id ): string {
		$current = $target;
		$hops    = 0;

		while ( $hops < self::MAX_CHAIN_DEPTH ) {
			$next = $this->repository->find_by_source( $current, true );

			if ( null === $next || (int) $next['id'] === $exclude_id || (string) $next['target'] === $current ) {
				break;
			}

			$current = (string) $next['target'];
			++$hops;
		}

		return $current;
	}

	/**
	 * Detects whether creating source->target would produce a cycle.
	 *
	 * Walks forward from the target; reaching the proposed source again
	 * means the pair would close a loop.
	 *
	 * @param string $source     Proposed source path.
	 * @param string $target     Proposed target value.
	 * @param int    $exclude_id Record ID to ignore while walking.
	 * @return bool
	 */
	private function creates_loop( string $source, string $target, int $exclude_id ): bool {
		if ( $source === $target ) {
			return true;
		}

		$current = $target;
		$hops    = 0;

		while ( $hops < self::MAX_CHAIN_DEPTH ) {
			if ( $current === $source ) {
				return true;
			}

			$next = $this->repository->find_by_source( $current, true );

			if ( null === $next || (int) $next['id'] === $exclude_id ) {
				return false;
			}

			$current = (string) $next['target'];
			++$hops;
		}

		return true; // Depth exhausted: treat as a potential loop.
	}

	/**
	 * Repoints active rules whose target became a source of another rule.
	 *
	 * This keeps /hello-world/ -> /hello-dunia/ up to date when
	 * /hello-dunia/ itself starts redirecting to /hello-bangladesh/.
	 *
	 * @param string $new_source Source that now resolves elsewhere.
	 * @param string $final      Final resolved target for that source.
	 * @return void
	 */
	private function flatten_inbound_links( string $new_source, string $final ): void {
		if ( $new_source === $final ) {
			return;
		}

		$inbound = $this->repository->list_by_target( $new_source );

		foreach ( $inbound as $row ) {
			$row_id = (int) $row['id'];

			if ( (string) $row['source'] === $final ) {
				continue;
			}

			if ( $this->creates_loop( (string) $row['source'], $final, $row_id ) ) {
				continue;
			}

			$this->repository->update(
				$row_id,
				array(
					'target' => $final,
				)
			);
		}
	}

	/**
	 * Sanitizes, deduplicates, and validates a raw sources list.
	 *
	 * @param array<mixed> $sources_raw Raw values.
	 * @param array        $report      Report array receiving error strings.
	 * @return array<int, string> Unique normalized sources, order preserved.
	 */
	private function collect_unique_sources( array $sources_raw, array &$report ): array {
		$sources  = array();
		$seen     = array();

		foreach ( $sources_raw as $raw ) {
			$raw = is_scalar( $raw ) ? (string) $raw : '';

			if ( '' === trim( $raw ) ) {
				continue; // Empty fields are ignored, never stored.
			}

			$normalized = self::normalize_source( wp_unslash( $raw ) );

			if ( null === $normalized ) {
				$report['errors'][] = sprintf(
					/* translators: %s: submitted value. */
					esc_html__( '"%s" is not a usable source. Sources must be local paths like /old-url/.', 'traveljabs' ),
					sanitize_text_field( $raw )
				);
				continue;
			}

			if ( strlen( $normalized ) > 2000 ) {
				$report['errors'][] = sprintf(
					/* translators: %s: source path. */
					esc_html__( '%s exceeds the maximum source length and was skipped.', 'traveljabs' ),
					$normalized
				);
				continue;
			}

			if ( isset( $seen[ $normalized ] ) ) {
				continue; // Duplicate values inside one submission.
			}

			$seen[ $normalized ] = true;
			$sources[]           = $normalized;
		}

		return $sources;
	}

	/**
	 * Creates redirects for every published post affected by a rewrite-slug
	 * configuration change.
	 *
	 * Old permalinks are produced through WordPress APIs by temporarily
	 * swapping the registered permastruct base back to the previous slug.
	 *
	 * @param string $post_type Post type key.
	 * @param string $old_slug  Previous rewrite slug.
	 * @param string $new_slug  Current rewrite slug.
	 * @return void
	 */
	private function sync_rewrite_slug_change( string $post_type, string $old_slug, string $new_slug ): void {
		global $wp_rewrite;

		if ( ! isset( $wp_rewrite->extra_permastructs[ $post_type ]['struct'] ) ) {
			return;
		}

		$struct = (string) $wp_rewrite->extra_permastructs[ $post_type ]['struct'];
		$legacy = str_replace( '/' . $new_slug . '/', '/' . $old_slug . '/', $struct );

		if ( $legacy === $struct ) {
			return; // Unexpected structure; do not guess.
		}

		$post_ids = get_posts(
			array(
				'post_type'              => $post_type,
				'post_status'            => 'publish',
				'fields'                 => 'ids',
				'posts_per_page'         => self::MAX_SLUG_SYNC_POSTS,
				'orderby'                => 'ID',
				'order'                  => 'ASC',
				'no_found_rows'          => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
			)
		);

		if ( empty( $post_ids ) ) {
			return;
		}

		$wp_rewrite->extra_permastructs[ $post_type ]['struct'] = $legacy;

		try {
			foreach ( $post_ids as $post_id ) {
				$post = get_post( (int) $post_id );

				if ( null === $post || 'publish' !== $post->post_status ) {
					continue;
				}

				$new_url = get_permalink( $post );

				$wp_rewrite->extra_permastructs[ $post_type ]['struct'] = $legacy;

				$old_url = get_permalink( $post );

				$wp_rewrite->extra_permastructs[ $post_type ]['struct'] = $struct;

				if ( ! is_string( $old_url ) || ! is_string( $new_url ) ) {
					continue;
				}

				$source = self::normalize_source( $old_url );
				$target = self::normalize_target( $new_url );

				if ( null === $source || null === $target || $source === $target ) {
					continue;
				}

				$this->create_redirect( $source, $target, 301, 'auto' );
			}
		} finally {
			$wp_rewrite->extra_permastructs[ $post_type ]['struct'] = $struct;
		}
	}

	/**
	 * Normalizes a bare relative path without host checks.
	 *
	 * @param string $path Raw path value.
	 * @return string|null Canonical path ('/' allowed), or null.
	 */
	private static function normalize_path_only( string $path ): ?string {
		$path = '/' . ltrim( str_replace( '\\', '/', trim( $path ) ), '/' );
		$path = urldecode( $path );

		if ( preg_match( '#[\x00-\x1f\x7f]#', $path ) ) {
			return null;
		}

		if ( '/' !== $path ) {
			$path = rtrim( $path, '/' );
			$path = '' === $path ? '/' : $path;
		}

		return $path;
	}

	/**
	 * Compares a hostname against the site home URL host.
	 *
	 * @param string $host Hostname from a parsed URL.
	 * @return bool True when the host belongs to this site.
	 */
	private static function is_local_host( string $host ): bool {
		$home = wp_parse_url( home_url() );

		if ( empty( $home['host'] ) || '' === $host ) {
			return false;
		}

		$site_host = strtolower( (string) $home['host'] );
		$given     = strtolower( $host );

		return $given === $site_host
			|| ( 'www.' . $site_host ) === $given
			|| $site_host === ( 'www.' . $given );
	}
}
