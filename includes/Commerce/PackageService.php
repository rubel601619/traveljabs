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
				'limit'      => 2,
				'product_id' => isset( $options['silver_product_id'] ) ? (int) $options['silver_product_id'] : 0,
			),
			'gold' => array(
				'label'      => __( 'Gold Package', 'traveljabs' ),
				'limit'      => 3,
				'product_id' => isset( $options['gold_product_id'] ) ? (int) $options['gold_product_id'] : 0,
			),
		);
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

				if ( null === $active || $packages[ $key ]['limit'] > $active['limit'] ) {
					$active = array(
						'key'        => $key,
						'label'      => $packages[ $key ]['label'],
						'limit'      => (int) $packages[ $key ]['limit'],
						'product_id' => $product_id,
					);
				}
			}
		}

		return $active;
	}
}
