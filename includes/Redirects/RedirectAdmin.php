<?php
/**
 * Traveljabs -> Redirects admin page and form handling.
 *
 * @package Traveljabs
 */

namespace Traveljabs\Redirects;

use Traveljabs\Admin\AdminMenu;

defined( 'ABSPATH' ) || exit;

/**
 * Registers the Redirects submenu, renders the management UI, and processes
 * all admin actions. Frontend redirect execution lives in RedirectManager.
 */
final class RedirectAdmin {

	/**
	 * Submenu slug under the Traveljabs parent menu.
	 */
	public const PAGE_SLUG = 'traveljabs-redirects';

	/**
	 * Nonce action/field used by the create form.
	 */
	private const NONCE_STORE = 'traveljabs_store_redirects';

	/**
	 * Nonce action/field used by the edit form.
	 */
	private const NONCE_UPDATE = 'traveljabs_update_redirect';

	/**
	 * Transient prefix for one-shot admin notices.
	 */
	private const NOTICE_TRANSIENT_PREFIX = 'traveljabs_redirects_notice_';

	/**
	 * Manager instance.
	 *
	 * @var RedirectManager
	 */
	private RedirectManager $manager;

	/**
	 * Repository instance.
	 *
	 * @var RedirectRepository
	 */
	private RedirectRepository $repository;

	/**
	 * Constructor. Registers admin hooks.
	 *
	 * @param RedirectManager|null    $manager    Optional manager override.
	 * @param RedirectRepository|null $repository Optional repository override.
	 */
	public function __construct( ?RedirectManager $manager = null, ?RedirectRepository $repository = null ) {
		$this->repository = $repository ?? new RedirectRepository();
		$this->manager    = $manager ?? new RedirectManager( $this->repository );

		add_action( 'admin_menu', array( $this, 'register_submenu' ), 12 );
		add_action( 'admin_init', array( $this, 'maybe_install_table' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'admin_post_traveljabs_store_redirects', array( $this, 'handle_store' ) );
		add_action( 'admin_post_traveljabs_update_redirect', array( $this, 'handle_update' ) );
		add_action( 'admin_post_traveljabs_delete_redirect', array( $this, 'handle_delete' ) );
		add_action( 'admin_post_traveljabs_toggle_redirect', array( $this, 'handle_toggle' ) );
		add_action( 'admin_post_traveljabs_export_redirects', array( $this, 'handle_export' ) );
		add_action( 'admin_post_traveljabs_import_redirects', array( $this, 'handle_import' ) );
	}

	/**
	 * Adds the Redirects submenu under the Traveljabs top-level menu.
	 *
	 * Priority 12 places it after the CPT submenus (priority 10) and before
	 * Settings (priority 20).
	 *
	 * @return void
	 */
	public function register_submenu(): void {
		add_submenu_page(
			AdminMenu::PARENT_SLUG,
			__( 'Redirects', 'traveljabs' ),
			__( 'Redirects', 'traveljabs' ),
			'manage_options',
			self::PAGE_SLUG,
			array( $this, 'render_page' )
		);
	}

	/**
	 * Ensures the table exists when plugin files were updated without
	 * reactivation. The version check makes this a no-op normally.
	 *
	 * @return void
	 */
	public function maybe_install_table(): void {
		if ( RedirectTable::needs_install() ) {
			RedirectTable::install();
		}
	}

	/**
	 * Loads the repeater script only on the Redirects screen.
	 *
	 * @param string $hook_suffix Current admin page hook suffix.
	 * @return void
	 */
	public function enqueue_assets( string $hook_suffix ): void {
		if ( get_plugin_page_hookname( self::PAGE_SLUG, AdminMenu::PARENT_SLUG ) !== $hook_suffix ) {
			return;
		}

		wp_enqueue_style(
			'traveljabs-redirects',
			plugins_url( 'assets/css/redirects.css', TRAVELJABS_FILE ),
			array(),
			TRAVELJABS_VERSION
		);

		wp_enqueue_script(
			'traveljabs-redirects',
			plugins_url( 'assets/js/redirects.js', TRAVELJABS_FILE ),
			array(),
			TRAVELJABS_VERSION,
			true
		);
	}

	/* ---------------------------------------------------------------------
	 * Page rendering
	 * ------------------------------------------------------------------- */

	/**
	 * Renders the Redirects page: notices, add/edit form, listing.
	 *
	 * @return void
	 */
	public function render_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to manage redirects.', 'traveljabs' ) );
		}

		$editing_id = isset( $_GET['edit'] ) ? absint( wp_unslash( $_GET['edit'] ) ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only display state.
		$editing    = $editing_id > 0 ? $this->repository->find( $editing_id ) : null;

		if ( null === $editing && $editing_id > 0 ) {
			$editing_id = 0;
		}

		$search = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only display state.
		$paged  = isset( $_GET['paged'] ) ? max( 1, absint( wp_unslash( $_GET['paged'] ) ) ) : 1; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only display state.

		$list = $this->repository->paginate(
			array(
				'search'   => $search,
				'status'   => 'all',
				'paged'    => $paged,
				'per_page' => 20,
			)
		);

		?>
		<div class="wrap">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>

			<?php $this->render_notices(); ?>

			<p>
				<a class="button" href="<?php echo esc_url( $this->action_url( 'traveljabs_export_redirects' ) ); ?>"><?php echo esc_html__( 'Export Redirects', 'traveljabs' ); ?></a>
			</p>
			<div class="card traveljabs-redirect-import-card">
				<h2><?php echo esc_html__( 'Import Redirects', 'traveljabs' ); ?></h2>
				<p><?php echo esc_html__( 'Upload a CSV file exported from Excel. Required columns: source, target, status_code, is_active.', 'traveljabs' ); ?></p>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" enctype="multipart/form-data">
					<input type="hidden" name="action" value="traveljabs_import_redirects" />
					<?php wp_nonce_field( 'traveljabs_import_redirects', 'traveljabs_import_redirects' ); ?>
					<input type="file" name="traveljabs_redirect_file" accept=".csv,text/csv" required />
					<?php submit_button( __( 'Import CSV', 'traveljabs' ), 'secondary', 'submit', false ); ?>
				</form>
			</div>

			<div class="card traveljabs-redirect-form-card">
				<h2><?php echo esc_html( $editing ? __( 'Edit Redirect', 'traveljabs' ) : __( 'Add Redirect', 'traveljabs' ) ); ?></h2>

				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="<?php echo esc_attr( $editing ? 'traveljabs_update_redirect' : 'traveljabs_store_redirects' ); ?>" />
					<input type="hidden" name="redirect_id" value="<?php echo esc_attr( (string) $editing_id ); ?>" />
					<?php wp_nonce_field( $editing ? self::NONCE_UPDATE : self::NONCE_STORE, $editing ? self::NONCE_UPDATE : self::NONCE_STORE ); ?>

					<div class="traveljabs-field" id="traveljabs-sources">
						<label><?php echo esc_html__( 'Source', 'traveljabs' ); ?></label>
						<p class="description">
							<?php echo esc_html__( 'Local paths only, e.g. /old-url/. Use + to point multiple sources at one target. Empty fields are ignored.', 'traveljabs' ); ?>
						</p>

						<?php
						if ( $editing ) {
							$this->render_source_row( (string) $editing['source'], true );
						} else {
							$this->render_source_row();
						}
						?>
					</div>

					<div class="traveljabs-field">
						<label for="traveljabs-redirect-target"><?php echo esc_html__( 'Target', 'traveljabs' ); ?></label>
						<input type="text" id="traveljabs-redirect-target" name="traveljabs_redirect[target]" class="regular-text" value="<?php echo esc_attr( $editing ? (string) $editing['target'] : '' ); ?>" placeholder="/new-url or https://example.org/new-page" required />
						<p class="description"><?php echo esc_html__( 'A local path or an external http(s) URL.', 'traveljabs' ); ?></p>
					</div>

					<div class="traveljabs-field">
						<label for="traveljabs-redirect-status"><?php echo esc_html__( 'Status', 'traveljabs' ); ?></label>
						<select id="traveljabs-redirect-status" name="traveljabs_redirect[status]">
							<?php foreach ( RedirectManager::get_status_choices() as $code => $label ) : ?>
								<option value="<?php echo esc_attr( (string) $code ); ?>" <?php selected( $editing ? (int) $editing['status_code'] : 301, $code ); ?>>
									<?php echo esc_html( $label ); ?>
								</option>
							<?php endforeach; ?>
						</select>
					</div>

					<?php if ( $editing ) : ?>
						<div class="traveljabs-field">
							<label for="traveljabs-redirect-active">
								<input type="checkbox" id="traveljabs-redirect-active" name="traveljabs_redirect[is_active]" value="1" <?php checked( (int) $editing['is_active'], 1 ); ?> />
								<?php echo esc_html__( 'Active', 'traveljabs' ); ?>
							</label>
						</div>
					<?php endif; ?>

					<?php submit_button( $editing ? __( 'Update Redirect', 'traveljabs' ) : __( 'Create Redirects', 'traveljabs' ) ); ?>

					<?php if ( $editing ) : ?>
						<a href="<?php echo esc_url( $this->page_url() ); ?>"><?php echo esc_html__( 'Cancel editing', 'traveljabs' ); ?></a>
					<?php endif; ?>
				</form>
			</div>

			<h2 class="title"><?php echo esc_html__( 'Existing Redirects', 'traveljabs' ); ?> <span class="count">(<?php echo esc_html( number_format_i18n( $list['total'] ) ); ?>)</span></h2>

			<form method="get" class="search-form">
				<input type="hidden" name="page" value="<?php echo esc_attr( self::PAGE_SLUG ); ?>" />
				<p class="search-box">
					<input type="search" name="s" value="<?php echo esc_attr( $search ); ?>" />
					<?php submit_button( __( 'Search', 'traveljabs' ), '', '', false ); ?>
				</p>
			</form>

			<table class="wp-list-table widefat fixed striped">
				<thead>
					<tr>
						<th scope="col" class="col-id"><?php echo esc_html__( 'ID', 'traveljabs' ); ?></th>
						<th scope="col"><?php echo esc_html__( 'Source', 'traveljabs' ); ?></th>
						<th scope="col"><?php echo esc_html__( 'Target', 'traveljabs' ); ?></th>
						<th scope="col"><?php echo esc_html__( 'Status', 'traveljabs' ); ?></th>
						<th scope="col"><?php echo esc_html__( 'Active', 'traveljabs' ); ?></th>
						<th scope="col"><?php echo esc_html__( 'Created', 'traveljabs' ); ?></th>
						<th scope="col"><?php echo esc_html__( 'Updated', 'traveljabs' ); ?></th>
						<th scope="col"><?php echo esc_html__( 'Actions', 'traveljabs' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php if ( empty( $list['rows'] ) ) : ?>
						<tr>
							<td colspan="8"><?php echo esc_html__( 'No redirects found.', 'traveljabs' ); ?></td>
						</tr>
					<?php else : ?>
						<?php foreach ( $list['rows'] as $row ) : ?>
							<?php $row_id = (int) $row['id']; ?>
							<tr>
								<td><?php echo esc_html( (string) $row_id ); ?></td>
								<td><code class="traveljabs-path"><?php echo esc_html( (string) $row['source'] ); ?></code></td>
								<td><code class="traveljabs-path"><?php echo esc_html( (string) $row['target'] ); ?></code></td>
								<td><?php echo esc_html( (string) $row['status_code'] ); ?></td>
								<td>
									<span class="traveljabs-dot traveljabs-dot--<?php echo esc_attr( $row['is_active'] ? 'on' : 'off' ); ?>"></span>
									<?php echo esc_html( $row['is_active'] ? __( 'Yes', 'traveljabs' ) : __( 'No', 'traveljabs' ) ); ?>
								</td>
								<td><?php echo esc_html( $this->format_date( (string) $row['created_at'] ) ); ?></td>
								<td><?php echo esc_html( $this->format_date( (string) $row['updated_at'] ) ); ?></td>
								<td>
									<a href="<?php echo esc_url( add_query_arg( 'edit', $row_id, $this->page_url() ) ); ?>"><?php echo esc_html__( 'Edit', 'traveljabs' ); ?></a> |
									<a href="<?php echo esc_url( $this->action_url( 'traveljabs_toggle_redirect', $row_id, 'traveljabs_toggle_redirect_' . $row_id, array( 'paged' => $paged, 's' => $search, 'state' => $row['is_active'] ? 'deactivate' : 'activate' ) ) ); ?>">
										<?php echo esc_html( $row['is_active'] ? __( 'Disable', 'traveljabs' ) : __( 'Enable', 'traveljabs' ) ); ?>
									</a> |
									<a href="<?php echo esc_url( $this->action_url( 'traveljabs_delete_redirect', $row_id, 'traveljabs_delete_redirect_' . $row_id, array( 'paged' => $paged, 's' => $search ) ) ); ?>"
										onclick="return confirm('<?php echo esc_js( __( 'Delete this redirect permanently?', 'traveljabs' ) ); ?>');">
										<?php echo esc_html__( 'Delete', 'traveljabs' ); ?>
									</a>
								</td>
							</tr>
						<?php endforeach; ?>
					<?php endif; ?>
				</tbody>
			</table>

			<?php
			if ( $list['pages'] > 1 ) {
				echo '<div class="tablenav"><div class="tablenav-pages">';
				echo wp_kses_post(
					paginate_links(
						array(
							'base'    => add_query_arg( 'paged', '%#%' ),
							'format'  => '',
							'current' => min( $paged, $list['pages'] ),
							'total'   => $list['pages'],
						)
					)
				);
				echo '</div></div>';
			}
			?>
		</div>
		<?php
	}

	/* ---------------------------------------------------------------------
	 * Action handlers
	 * ------------------------------------------------------------------- */

	/**
	 * Processes the multi-source creation form.
	 *
	 * @return void
	 */
	public function handle_store(): void {
		$this->assert_access( self::NONCE_STORE );

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Verified via check_admin_referer().
		$input  = isset( $_POST['traveljabs_redirect'] ) && is_array( $_POST['traveljabs_redirect'] ) ? wp_unslash( $_POST['traveljabs_redirect'] ) : array();
		$sources = isset( $input['sources'] ) && is_array( $input['sources'] ) ? $input['sources'] : array();

		$report = $this->manager->create_manual_batch(
			$sources,
			(string) ( $input['target'] ?? '' ),
			(int) ( $input['status'] ?? 301 ),
			true
		);

		$message = $report['created'] > 0
			? sprintf(
				/* translators: %d: number of created redirects. */
				_n( '%d redirect created.', '%d redirects created.', $report['created'], 'traveljabs' ),
				$report['created']
			)
			: __( 'No redirects were created.', 'traveljabs' );

		$type = $report['created'] > 0 && empty( $report['errors'] ) && empty( $report['conflicts'] )
			? 'success'
			: 'warning';

		$this->queue_notice( $type, $message, $report );

		wp_safe_redirect( $this->page_url() );
		exit;
	}

	/**
	 * Processes the single-record edit form.
	 *
	 * @return void
	 */
	public function handle_update(): void {
		$this->assert_access( self::NONCE_UPDATE );

		$id = isset( $_POST['redirect_id'] ) ? absint( wp_unslash( $_POST['redirect_id'] ) ) : 0;

		if ( $id <= 0 ) {
			$this->queue_notice( 'error', __( 'Invalid redirect ID.', 'traveljabs' ), array() );
			wp_safe_redirect( $this->page_url() );
			exit;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Verified via check_admin_referer().
		$input = isset( $_POST['traveljabs_redirect'] ) && is_array( $_POST['traveljabs_redirect'] ) ? wp_unslash( $_POST['traveljabs_redirect'] ) : array();

		$result = $this->manager->update_manual(
			$id,
			isset( $input['sources'] ) && is_array( $input['sources'] ) ? $input['sources'] : array(),
			(string) ( $input['target'] ?? '' ),
			(int) ( $input['status'] ?? 301 ),
			! empty( $input['is_active'] )
		);

		if ( $result['updated'] ) {
			$this->queue_notice( 'success', __( 'Redirect updated.', 'traveljabs' ), array() );
			wp_safe_redirect( $this->page_url() );
		} else {
			$this->queue_notice( 'error', __( 'The redirect was not updated.', 'traveljabs' ), $result );
			wp_safe_redirect( add_query_arg( 'edit', $id, $this->page_url() ) );
		}

		exit;
	}

	/**
	 * Deletes a redirect after nonce and capability checks.
	 *
	 * @return void
	 */
	public function handle_delete(): void {
		$id = isset( $_GET['id'] ) ? absint( wp_unslash( $_GET['id'] ) ) : 0;

		check_admin_referer( 'traveljabs_delete_redirect_' . $id );
		$this->assert_capability();

		if ( $id <= 0 || ! $this->repository->delete( $id ) ) {
			$this->queue_notice( 'error', __( 'The redirect could not be deleted.', 'traveljabs' ), array() );
		} else {
			$this->queue_notice( 'success', __( 'Redirect deleted.', 'traveljabs' ), array() );
		}

		wp_safe_redirect( $this->list_back_url() );
		exit;
	}

	/**
	 * Enables or disables a redirect after nonce and capability checks.
	 *
	 * @return void
	 */
	public function handle_toggle(): void {
		$id    = isset( $_GET['id'] ) ? absint( wp_unslash( $_GET['id'] ) ) : 0;
		$state = isset( $_GET['state'] ) ? sanitize_key( wp_unslash( $_GET['state'] ) ) : '';

		check_admin_referer( 'traveljabs_toggle_redirect_' . $id );
		$this->assert_capability();

		$active = 'activate' === $state ? true : ( 'deactivate' === $state ? false : null );

		if ( $id <= 0 || ! $this->repository->set_active( $id, $active ) ) {
			$this->queue_notice( 'error', __( 'The redirect state could not be changed.', 'traveljabs' ), array() );
		} else {
			$record = $this->repository->find( $id );

			$this->queue_notice(
				'success',
				$record && (int) $record['is_active']
					? __( 'Redirect enabled.', 'traveljabs' )
					: __( 'Redirect disabled.', 'traveljabs' ),
				array()
			);
		}

		wp_safe_redirect( $this->list_back_url() );
		exit;
	}

	/**
	 * Downloads all redirects as an Excel-compatible CSV file.
	 *
	 * @return void
	 */
	public function handle_export(): void {
		$this->assert_capability();
		check_admin_referer( 'traveljabs_export_redirects' );
		$list = $this->repository->paginate( array( 'status' => 'all', 'paged' => 1, 'per_page' => 100 ) );
		while ( count( $list['rows'] ) < $list['total'] ) {
			$next_page = (int) ( count( $list['rows'] ) / 100 ) + 1;
			$next = $this->repository->paginate( array( 'status' => 'all', 'paged' => $next_page, 'per_page' => 100 ) );
			$list['rows'] = array_merge( $list['rows'], $next['rows'] );
		}

		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=traveljabs-redirects-' . gmdate( 'Y-m-d' ) . '.csv' );
		$output = fopen( 'php://output', 'w' );
		fwrite( $output, "\xEF\xBB\xBF" );
		fputcsv( $output, array( 'id', 'source', 'target', 'status_code', 'is_active', 'origin', 'created_at', 'updated_at' ) );
		foreach ( $list['rows'] as $row ) fputcsv( $output, array( $row['id'], $row['source'], $row['target'], $row['status_code'], $row['is_active'], $row['origin'], $row['created_at'], $row['updated_at'] ) );
		fclose( $output );
		exit;
	}

	/**
	 * Imports validated redirect rows from an Excel-compatible CSV file.
	 *
	 * @return void
	 */
	public function handle_import(): void {
		$this->assert_capability();
		check_admin_referer( 'traveljabs_import_redirects', 'traveljabs_import_redirects' );
		$file = isset( $_FILES['traveljabs_redirect_file'] ) && is_array( $_FILES['traveljabs_redirect_file'] ) ? $_FILES['traveljabs_redirect_file'] : array();
		$filename = isset( $file['name'] ) ? sanitize_file_name( wp_unslash( $file['name'] ) ) : '';
		$extension = strtolower( pathinfo( $filename, PATHINFO_EXTENSION ) );
		if ( empty( $file['tmp_name'] ) || ! empty( $file['error'] ) || 'csv' !== $extension || ! is_uploaded_file( $file['tmp_name'] ) ) {
			$this->queue_notice( 'error', __( 'Please upload a valid CSV file.', 'traveljabs' ), array() );
			wp_safe_redirect( $this->page_url() );
			exit;
		}

		$handle = fopen( $file['tmp_name'], 'r' );
		$header = $handle ? fgetcsv( $handle ) : false;
		if ( is_array( $header ) && isset( $header[0] ) ) {
			$header[0] = preg_replace( '/^\xEF\xBB\xBF/', '', (string) $header[0] );
		}
		$required = array( 'source', 'target', 'status_code', 'is_active' );
		$report = array( 'created' => 0, 'duplicates' => array(), 'conflicts' => array(), 'errors' => array() );
		if ( ! is_array( $header ) || array_diff( $required, array_map( 'sanitize_key', $header ) ) ) $report['errors'][] = __( 'The CSV header must contain source, target, status_code, and is_active.', 'traveljabs' );
		if ( empty( $report['errors'] ) ) {
			$indexes = array_flip( array_map( 'sanitize_key', $header ) );
			while ( ( $row = fgetcsv( $handle ) ) !== false ) {
				$batch = $this->manager->create_manual_batch( array( $row[ $indexes['source'] ] ?? '' ), (string) ( $row[ $indexes['target'] ] ?? '' ), (int) ( $row[ $indexes['status_code'] ] ?? 301 ), ! empty( $row[ $indexes['is_active'] ] ) );
				$report['created'] += $batch['created'];
				$report['duplicates'] = array_merge( $report['duplicates'], $batch['duplicates'] );
				$report['conflicts'] = array_merge( $report['conflicts'], $batch['conflicts'] );
				$report['errors'] = array_merge( $report['errors'], $batch['errors'] );
			}
		}
		if ( $handle ) fclose( $handle );
		$this->queue_notice( $report['created'] > 0 && empty( $report['errors'] ) ? 'success' : 'warning', sprintf( _n( '%d redirect imported.', '%d redirects imported.', $report['created'], 'traveljabs' ), $report['created'] ), $report );
		wp_safe_redirect( $this->page_url() );
		exit;
	}

	/* ---------------------------------------------------------------------
	 * Internal helpers
	 * ------------------------------------------------------------------- */

	/**
	 * Verifies capability and nonce for a POST form handler.
	 *
	 * @param string $nonce_action Nonce action to verify.
	 * @return void
	 */
	private function assert_access( string $nonce_action ): void {
		$this->assert_capability();
		check_admin_referer( $nonce_action, $nonce_action );
	}

	/**
	 * Verifies the manage_options capability.
	 *
	 * @return void
	 */
	private function assert_capability(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to manage redirects.', 'traveljabs' ) );
		}
	}

	/**
	 * Returns the base URL of the Redirects page.
	 *
	 * @return string
	 */
	private function page_url(): string {
		return add_query_arg(
			array( 'page' => self::PAGE_SLUG ),
			admin_url( AdminMenu::PARENT_SLUG )
		);
	}

	/**
	 * Builds a nonce-protected action URL.
	 *
	 * The URL targets admin-post.php because the delete/toggle handlers are
	 * registered on the admin_post_{$action} hooks, which only fire on
	 * requests to that endpoint.
	 *
	 * @param string                     $action       Action hook name.
	 * @param int                        $id           Record ID.
	 * @param string                     $nonce_action Nonce action name.
	 * @param array<string, string|int>  $extra        Extra query args preserved.
	 * @return string
	 */
	private function action_url( string $action, int $id = 0, string $nonce_action = '', array $extra = array() ): string {
		$args = array_merge(
			$extra,
			array(
				'action' => $action,
			)
		);
		if ( $id > 0 ) {
			$args['id'] = $id;
		}

		return wp_nonce_url(
			add_query_arg( $args, admin_url( 'admin-post.php' ) ),
			$nonce_action ?: $action
		);
	}

	/**
	 * Renders one source input row with its +/- buttons.
	 *
	 * @param string $value       Pre-filled source value.
	 * @param bool   $single_mode Hide remove button (single-record editing).
	 * @return void
	 */
	private function render_source_row( string $value = '', bool $single_mode = false ): void {
		?>
		<div class="tj-source-row">
			<input type="text" name="traveljabs_redirect[sources][]" class="regular-text tj-source-input" value="<?php echo esc_attr( $value ); ?>" placeholder="/old-url" />
			<button type="button" class="button button-secondary tj-add-source" aria-label="<?php echo esc_attr__( 'Add another source field', 'traveljabs' ); ?>">+</button>
			<button type="button" class="button button-secondary tj-remove-source" aria-label="<?php echo esc_attr__( 'Remove this source field', 'traveljabs' ); ?>" <?php echo $single_mode ? 'hidden' : ''; ?>>&minus;</button>
		</div>
		<?php
	}

	/**
	 * Stores a one-shot notice for display after the next redirect back.
	 *
	 * @param string               $type    success|error|warning|info.
	 * @param string               $message Primary message.
	 * @param array<string, mixed> $details Optional detail lines grouped by key.
	 * @return void
	 */
	private function queue_notice( string $type, string $message, array $details ): void {
		$lines = array( 'message' => $message, 'type' => $type, 'details' => array() );

		foreach ( array( 'duplicates', 'conflicts', 'errors' ) as $key ) {
			if ( ! empty( $details[ $key ] ) && is_array( $details[ $key ] ) ) {
				foreach ( $details[ $key ] as $line ) {
					if ( is_string( $line ) && '' !== $line ) {
						$lines['details'][ $key ][] = sanitize_text_field( $line );
					}
				}
			}
		}

		set_transient(
			self::NOTICE_TRANSIENT_PREFIX . get_current_user_id(),
			$lines,
			MINUTE_IN_SECONDS
		);
	}

	/**
	 * Renders and clears the queued notice, if any.
	 *
	 * @return void
	 */
	private function render_notices(): void {
		$notice = get_transient( self::NOTICE_TRANSIENT_PREFIX . get_current_user_id() );

		if ( ! is_array( $notice ) || empty( $notice['message'] ) ) {
			return;
		}

		delete_transient( self::NOTICE_TRANSIENT_PREFIX . get_current_user_id() );

		$class = in_array( $notice['type'], array( 'success', 'error', 'warning', 'info' ), true )
			? 'notice-' . $notice['type']
			: 'info';

		printf(
			'<div class="notice %1$s is-dismissible"><p><strong>%2$s</strong></p>',
			esc_attr( $class ),
			esc_html( (string) $notice['message'] )
		);

		if ( ! empty( $notice['details'] ) && is_array( $notice['details'] ) ) {
			echo '<ul>';

			foreach ( $notice['details'] as $group_lines ) {
				if ( is_array( $group_lines ) ) {
					foreach ( $group_lines as $line ) {
						printf( '<li>%s</li>', esc_html( (string) $line ) );
					}
				}
			}

			echo '</ul>';
		}

		echo '</div>';
	}

	/**
	 * Formats a stored datetime for display.
	 *
	 * @param string $mysql_date MySQL datetime string.
	 * @return string
	 */
	private function format_date( string $mysql_date ): string {
		$timestamp = strtotime( $mysql_date );

		return $timestamp ? date_i18n( get_option( 'date_format' ) . ' H:i', $timestamp ) : '—';
	}

	/**
	 * Builds the redirect-back URL preserving list context.
	 *
	 * @param array<string, string|int> $extra Additional query args.
	 * @return string
	 */
	private function back_url( array $extra = array() ): string {
		return add_query_arg( $extra, $this->page_url() );
	}

	/**
	 * Builds the redirect-back URL for row actions, preserving pagination
	 * and the active search term.
	 *
	 * @return string
	 */
	private function list_back_url(): string {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only context for building a return URL.
		return $this->back_url(
			array(
				'paged' => isset( $_GET['paged'] ) ? max( 1, absint( wp_unslash( $_GET['paged'] ) ) ) : 1,
				's'     => isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '',
			)
		);
	}
}
