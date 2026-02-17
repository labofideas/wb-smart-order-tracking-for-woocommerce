<?php
namespace WBCOM\WBSOT;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

interface Status_Provider_Interface {
	/**
	 * Provider ID.
	 *
	 * @return string
	 */
	public function get_id(): string;

	/**
	 * Whether provider can be used.
	 *
	 * @return bool
	 */
	public function is_configured(): bool;

	/**
	 * Fetch shipment status for a tracking item.
	 *
	 * @param array<string, mixed> $tracking_item Tracking item.
	 * @param \WC_Order            $order Order object.
	 * @return array<string, string>|null
	 */
	public function fetch_status( array $tracking_item, \WC_Order $order ): ?array;
}
