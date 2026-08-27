<?php
/**
 * WooCommerce package entitlement service.
 *
 * @package Traveljabs
 */

namespace Traveljabs\Commerce;

use Traveljabs\Admin\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Resolves the highest valid clinic package purchased by a user.
 */
final class PackageService {

	public const ASSIGNMENTS_OPTION = 'traveljabs_package_assignments';

	/**
	 * Package definitions and their configured WooCommerce product settings.
	 *
	 * @return array<string, array<string, int|string>>
	 */
	public static function get_packages(): array {
		$options = get_option( Settings::OPTION_KEY, array() );
		$options = is_array( $options ) ? $options : array();

		return array(
			'bronze' => array(
				'label'      => __( 'Bronze Package', 'traveljabs' ),
				'limit'      => 1,
				'product_id' => isset( $options['bronze_product_id'] ) ? (int) $options['bronze_product_id'] : 0,
			),
			'silver' => array(
				'label'      => __( 'Silver Package', 'traveljabs' ),
				'limit'      => 1,
				'product_id' => isset( $options['silver_product_id'] ) ? (int) $options['silver_product_id'] : 0,
			),
			'gold' => array(
				'label'      => __( 'Gold Package', 'traveljabs' ),
				'limit'      => 1,
				'product_id' => isset( $options['gold_product_id'] ) ? (int) $options['gold_product_id'] : 0,
			),
		);
	}

	/**
	 * Returns the services included with each package.
	 *
	 * @return array<string, array<int, string>>
	 */
	public static function get_services(): array {
		$standard = array(
			__( 'Centralised Booking System', 'traveljabs' ),
			__( 'Increased visibility', 'traveljabs' ),
			__( 'Enhanced Credibility', 'traveljabs' ),
			__( 'Improved SEO and Online Presence', 'traveljabs' ),
		);

		return array(
			'bronze' => $standard,
			'silver' => array_merge( $standard, array( __( 'PPC Marketing*', 'traveljabs' ) ) ),
			'gold'   => array_merge( $standard, array( __( 'PPC Marketing*', 'traveljabs' ), __( 'Premium SEO Marketing*', 'traveljabs' ) ) ),
		);
	}

	/** @return array<int, string> */
	public static function get_manual_assignments(): array {
		$assignments = get_option( self::ASSIGNMENTS_OPTION, array() );
		return is_array( $assignments ) ? array_map( 'sanitize_key', $assignments ) : array();
	}

	/** @return bool */
	public static function assign_package( int $user_id, string $package_key ): bool {
		if ( $user_id <= 0 || ! isset( self::get_packages()[ $package_key ] ) ) return false;
		$assignments = self::get_manual_assignments();
		$assignments[ $user_id ] = $package_key;
		return update_option( self::ASSIGNMENTS_OPTION, $assignments );
	}

	/** @return bool */
	public static function remove_assignment( int $user_id ): bool {
		$assignments = self::get_manual_assignments();
		if ( ! isset( $assignments[ $user_id ] ) ) return false;
		unset( $assignments[ $user_id ] );
		return update_option( self::ASSIGNMENTS_OPTION, $assignments );
	}

	/**
	 * Returns the configured package purchase URL.
	 *
	 * @return string
	 */
	public static function get_purchase_url(): string {
		$options = get_option( Settings::OPTION_KEY, array() );
		$options = is_array( $options ) ? $options : array();
		$url     = isset( $options['package_purchase_url'] ) ? (string) $options['package_purchase_url'] : '';

		if ( '' !== $url ) {
			return $url;
		}

		return function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'shop' ) : home_url( '/' );
	}

	/**
	 * Resolves the highest active package for a user.
	 *
	 * Only processing and completed WooCommerce orders are considered. If
	 * WooCommerce is unavailable or no configured product has been purchased,
	 * no entitlement is returned.
	 *
	 * @param int $user_id WordPress user ID.
	 * @return array<string, int|string>|null
	 */
	public static function get_active_package( int $user_id ): ?array {
		$manual_key = self::get_manual_assignments()[ $user_id ] ?? '';
		if ( '' !== $manual_key ) return self::build_package( $manual_key );

		if ( $user_id <= 0 || ! function_exists( 'wc_get_orders' ) ) {
			return null;
		}

		$packages = self::get_packages();
		$products = array();

		foreach ( $packages as $key => $package ) {
			$product_id = (int) $package['product_id'];

			if ( $product_id > 0 ) {
				$products[ $product_id ] = $key;
			}
		}

		if ( empty( $products ) ) {
			return null;
		}

		$orders = wc_get_orders(
			array(
				'customer_id' => $user_id,
				'limit'       => -1,
				'orderby'     => 'date',
				'order'       => 'DESC',
				'status'      => array( 'processing', 'completed' ),
			)
		);
		$active = null;
		$package_priority = array( 'bronze' => 1, 'silver' => 2, 'gold' => 3 );
		$active_priority = 0;

		foreach ( $orders as $order ) {
			foreach ( $order->get_items( 'line_item' ) as $item ) {
				$product_id = (int) $item->get_product_id();
				$key        = isset( $products[ $product_id ] ) ? $products[ $product_id ] : '';

				if ( '' === $key && $item->get_variation_id() > 0 ) {
					$variation_id = (int) $item->get_variation_id();
					$key          = isset( $products[ $variation_id ] ) ? $products[ $variation_id ] : '';
				}

				if ( '' === $key ) {
					continue;
				}

				$package_limit = (int) $packages[ $key ]['limit'];

				if ( $package_priority[ $key ] > $active_priority ) {
					$active = array(
						'key'        => $key,
						'label'      => $packages[ $key ]['label'],
						'limit'      => $package_limit,
						'product_id' => $product_id,
					);
					$active_priority = $package_priority[ $key ];
				}
			}
		}

		return $active;
	}

	/** @return array<string, int|string>|null */
	private static function build_package( string $key ): ?array {
		$packages = self::get_packages();
		if ( ! isset( $packages[ $key ] ) ) return null;
		return array( 'key' => $key, 'label' => $packages[ $key ]['label'], 'limit' => (int) $packages[ $key ]['limit'], 'product_id' => (int) $packages[ $key ]['product_id'] );
	}
}
