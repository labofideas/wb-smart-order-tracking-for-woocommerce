<?php
namespace WBCOM\WBSOT;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Sample status provider adapter.
 *
 * This is intentionally opt-in and only for development/testing.
 * Enable with:
 * add_filter( 'wbsot_enable_sample_provider', '__return_true' );
 */
final class Status_Provider_Sample {
	/**
	 * Register provider hook.
	 *
	 * @return void
	 */
	public function register(): void {
		$enabled = (bool) apply_filters( 'wbsot_enable_sample_provider', false );

		if ( ! $enabled ) {
			return;
		}

		add_filter( 'wbsot_fetch_tracking_status', array( $this, 'fetch_status' ), 10, 3 );
	}

	/**
	 * Return simulated status payload.
	 *
	 * @param mixed                         $payload Existing payload.
	 * @param array<string, mixed>          $tracking_item Tracking item.
	 * @param \WC_Order                    $order Order object.
	 * @return array<string, string>|mixed
	 */
	public function fetch_status( $payload, array $tracking_item, \WC_Order $order ) {
		if ( is_array( $payload ) ) {
			return $payload;
		}

		$tracking_number = (string) ( $tracking_item['tracking_number'] ?? '' );

		if ( '' === $tracking_number ) {
			return $payload;
		}

		$states = array(
			array( 'status' => 'in_transit', 'label' => __( 'In Transit', 'wb-smart-order-tracking-for-woocommerce' ) ),
			array( 'status' => 'out_for_delivery', 'label' => __( 'Out for Delivery', 'wb-smart-order-tracking-for-woocommerce' ) ),
			array( 'status' => 'delivered', 'label' => __( 'Delivered', 'wb-smart-order-tracking-for-woocommerce' ) ),
		);

		$index = absint( crc32( strtoupper( $tracking_number ) ) ) % count( $states );
		$state = $states[ $index ];

		return array(
			'status'       => $state['status'],
			'status_label' => $state['label'],
		);
	}
}
