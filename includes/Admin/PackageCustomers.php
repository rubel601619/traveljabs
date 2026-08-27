<?php
/**
 * Displays and manages customer package entitlements in the WordPress admin.
 *
 * @package Traveljabs
 */

namespace Traveljabs\Admin;

use Traveljabs\Commerce\PackageService;

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( '\WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/** Registers the package customer report. */
final class PackageCustomers {

	public function __construct() {
		add_action( 'admin_menu', array( $this, 'register_submenu' ), 19 );
	}

	public function register_submenu(): void {
		add_submenu_page( AdminMenu::PARENT_SLUG, __( 'Package Customers', 'traveljabs' ), __( 'Package Customers', 'traveljabs' ), 'manage_options', 'traveljabs-package-customers', array( $this, 'render_page' ) );
	}

	public function render_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) return;
		$user_id = isset( $_GET['user_id'] ) ? absint( $_GET['user_id'] ) : 0;
		if ( 'edit' === ( $_GET['action'] ?? '' ) && $user_id > 0 ) {
			$this->process_edit( $user_id );
			$this->render_edit_page( $user_id );
			return;
		}
		$list_table = new PackageCustomersTable();
		$list_table->process_bulk_action();
		$list_table->prepare_items();
		?>
		<div class="wrap">
			<h1 class="wp-heading-inline"><?php echo esc_html__( 'Package Customers', 'traveljabs' ); ?></h1>
			<a href="<?php echo esc_url( admin_url( 'user-new.php' ) ); ?>" class="page-title-action"><?php echo esc_html__( 'Add New', 'traveljabs' ); ?></a>
			<hr class="wp-header-end">
			<p><?php echo esc_html__( 'WooCommerce purchases are used automatically. A manual assignment overrides purchases and can be managed here.', 'traveljabs' ); ?></p>
			<form method="post">
				<?php $list_table->display(); ?>
			</form>
		</div>
		<?php
	}

	private function process_edit( int $user_id ): void {
		if ( 'POST' !== strtoupper( $_SERVER['REQUEST_METHOD'] ?? '' ) ) return;
		check_admin_referer( 'traveljabs_edit_package_' . $user_id );
		if ( isset( $_POST['remove_assignment'] ) ) {
			PackageService::remove_assignment( $user_id );
		} else {
			$key = isset( $_POST['package_key'] ) ? sanitize_key( wp_unslash( $_POST['package_key'] ) ) : '';
			if ( '' === $key ) PackageService::remove_assignment( $user_id );
			else PackageService::assign_package( $user_id, $key );
		}
		wp_safe_redirect( admin_url( 'admin.php?page=traveljabs-package-customers&updated=1' ) );
		exit;
	}

	private function render_edit_page( int $user_id ): void {
		$user = get_user_by( 'id', $user_id );
		if ( ! $user || ! in_array( 'customer', (array) $user->roles, true ) ) return;
		$assigned = PackageService::get_manual_assignments()[ $user_id ] ?? '';
		?>
		<div class="wrap">
			<h1><?php echo esc_html( sprintf( __( 'Edit Package: %s', 'traveljabs' ), $user->display_name ?: $user->user_login ) ); ?></h1>
			<form method="post">
				<?php wp_nonce_field( 'traveljabs_edit_package_' . $user_id ); ?>
				<table class="form-table"><tr><th><label for="traveljabs-package-key"><?php echo esc_html__( 'Manual package', 'traveljabs' ); ?></label></th><td><select id="traveljabs-package-key" name="package_key"><option value=""><?php echo esc_html__( 'Use WooCommerce purchase', 'traveljabs' ); ?></option><?php foreach ( PackageService::get_packages() as $key => $package ) : ?><option value="<?php echo esc_attr( $key ); ?>" <?php selected( $assigned, $key ); ?>><?php echo esc_html( $package['label'] ); ?></option><?php endforeach; ?></select><p class="description"><?php echo esc_html__( 'A manual package overrides WooCommerce for this user. Selecting the WooCommerce option removes the manual assignment.', 'traveljabs' ); ?></p></td></tr></table>
				<?php submit_button( __( 'Save Package', 'traveljabs' ) ); ?>
				<button type="submit" name="remove_assignment" value="1" class="button"><?php echo esc_html__( 'Remove Manual Assignment', 'traveljabs' ); ?></button>
			</form>
			<p><a href="<?php echo esc_url( admin_url( 'admin.php?page=traveljabs-package-customers' ) ); ?>">&larr; <?php echo esc_html__( 'Back to Package Customers', 'traveljabs' ); ?></a></p>
		</div>
		<?php
	}
}

/** Native WordPress list table for package customers. */
final class PackageCustomersTable extends \WP_List_Table {

	public function __construct() {
		parent::__construct( array( 'singular' => 'package_customer', 'plural' => 'package_customers', 'ajax' => false ) );
	}

	public function get_columns(): array {
		return array( 'cb' => '<input type="checkbox" />', 'user' => __( 'User', 'traveljabs' ), 'email' => __( 'Email', 'traveljabs' ), 'package' => __( 'Package', 'traveljabs' ), 'services' => __( 'Services', 'traveljabs' ) );
	}

	protected function get_sortable_columns(): array {
		return array( 'user' => array( 'user', false ), 'email' => array( 'email', false ) );
	}

	protected function get_bulk_actions(): array {
		return array( 'assign_bronze' => __( 'Assign Bronze', 'traveljabs' ), 'assign_silver' => __( 'Assign Silver', 'traveljabs' ), 'assign_gold' => __( 'Assign Gold', 'traveljabs' ), 'remove_assignment' => __( 'Remove manual assignment', 'traveljabs' ) );
	}

	public function prepare_items(): void {
		$per_page = 20;
		$this->_column_headers = array( $this->get_columns(), array(), $this->get_sortable_columns(), 'user' );
		$users = get_users( array( 'role' => 'customer', 'orderby' => 'display_name', 'order' => 'ASC', 'number' => -1 ) );
		$orderby = isset( $_REQUEST['orderby'] ) ? sanitize_key( wp_unslash( $_REQUEST['orderby'] ) ) : 'user';
		usort( $users, static function ( $first, $second ) use ( $orderby ) {
			$first_value = 'email' === $orderby ? $first->user_email : $first->display_name;
			$second_value = 'email' === $orderby ? $second->user_email : $second->display_name;
			return strcasecmp( (string) $first_value, (string) $second_value );
		} );
		$total = count( $users );
		$page = $this->get_pagenum();
		$this->items = array_slice( $users, ( $page - 1 ) * $per_page, $per_page );
		$this->set_pagination_args( array( 'total_items' => $total, 'per_page' => $per_page, 'total_pages' => (int) ceil( $total / $per_page ) ) );
	}

	public function column_cb( $user ): string {
		return sprintf( '<input type="checkbox" name="users[]" value="%d" />', (int) $user->ID );
	}

	public function column_user( $user ): string {
		$edit_url = add_query_arg( array( 'page' => 'traveljabs-package-customers', 'action' => 'edit', 'user_id' => $user->ID ), admin_url( 'admin.php' ) );
		$actions = array( 'edit' => '<a href="' . esc_url( $edit_url ) . '">' . esc_html__( 'Edit package', 'traveljabs' ) . '</a>' );
		return sprintf( '<strong><a href="%s">%s</a></strong>%s', esc_url( get_edit_user_link( $user->ID ) ), esc_html( $user->display_name ?: $user->user_login ), $this->row_actions( $actions ) );
	}

	public function column_email( $user ): string { return esc_html( $user->user_email ); }

	public function column_package( $user ): string {
		$package = PackageService::get_active_package( (int) $user->ID );
		$manual = isset( PackageService::get_manual_assignments()[ $user->ID ] );
		return esc_html( $package ? $package['label'] . ' - ' . __( '1 clinic', 'traveljabs' ) . ( $manual ? ' (' . __( 'Manual', 'traveljabs' ) . ')' : '' ) : __( 'No active package', 'traveljabs' ) );
	}

	public function column_services( $user ): string {
		$package = PackageService::get_active_package( (int) $user->ID );
		if ( ! $package ) return esc_html__( 'No package services assigned.', 'traveljabs' );
		$services = PackageService::get_services()[ $package['key'] ] ?? array();
		return '<ul style="margin:0;padding-left:1.25rem">' . implode( '', array_map( static function ( $service ) { return '<li>' . esc_html( $service ) . '</li>'; }, $services ) ) . '</ul>';
	}

	public function process_bulk_action(): void {
		$action = $this->current_action();
		$allowed = array( 'assign_bronze', 'assign_silver', 'assign_gold', 'remove_assignment' );
		if ( ! $action || ! in_array( $action, $allowed, true ) || ! check_admin_referer( 'bulk-' . $this->_args['plural'] ) ) return;
		$users = isset( $_REQUEST['users'] ) && is_array( $_REQUEST['users'] ) ? array_map( 'absint', wp_unslash( $_REQUEST['users'] ) ) : array();
		$key = str_replace( 'assign_', '', $action );
		foreach ( $users as $user_id ) {
			$user = get_userdata( $user_id );
			if ( ! $user || ! in_array( 'customer', (array) $user->roles, true ) ) continue;
			if ( 'remove_assignment' === $action ) {
				PackageService::remove_assignment( $user_id );
			} elseif ( user_can( $user_id, 'read' ) ) {
				PackageService::assign_package( $user_id, $key );
			}
		}
		wp_safe_redirect( add_query_arg( array( 'page' => 'traveljabs-package-customers', 'updated' => count( $users ) ), admin_url( 'admin.php' ) ) );
		exit;
	}
}
