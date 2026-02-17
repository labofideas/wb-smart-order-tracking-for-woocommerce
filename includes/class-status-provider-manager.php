<?php
namespace WBCOM\WBSOT;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Status_Provider_Manager {
	/**
	 * Registered providers.
	 *
	 * @var array<int, Status_Provider_Interface>
	 */
	private array $providers = array();

	/**
	 * Constructor.
	 */
	public function __construct() {
		$providers = array(
			new Status_Provider_Shiprocket(),
			new Status_Provider_AfterShip(),
		);

		$providers = apply_filters( 'wbsot_register_status_providers', $providers );
		$this->providers = is_array( $providers ) ? $providers : array();
	}

	/**
	 * Resolve status from configured providers.
	 *
	 * @param array<string, mixed> $tracking_item Tracking item.
	 * @param \WC_Order            $order Order object.
	 * @return array<string, string>|null
	 */
	public function fetch_status( array $tracking_item, \WC_Order $order ): ?array {
		foreach ( $this->providers as $provider ) {
			if ( ! $provider instanceof Status_Provider_Interface ) {
				continue;
			}

			if ( ! $provider->is_configured() ) {
				continue;
			}

			$payload = $provider->fetch_status( $tracking_item, $order );

			if ( is_array( $payload ) && ! empty( $payload['status'] ) ) {
				return $payload;
			}
		}

		return null;
	}
}
