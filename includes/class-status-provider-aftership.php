<?php
namespace WBCOM\WBSOT;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Status_Provider_AfterShip implements Status_Provider_Interface {
	/**
	 * Get provider ID.
	 *
	 * @return string
	 */
	public function get_id(): string {
		return 'aftership';
	}

	/**
	 * Whether provider is fully configured.
	 *
	 * @return bool
	 */
	public function is_configured(): bool {
		return Settings::aftership_enabled() && '' !== Settings::aftership_api_key();
	}

	/**
	 * Fetch status from AfterShip API.
	 *
	 * This is a production skeleton and is disabled unless
	 * "Enable live API requests" is turned on in settings.
	 *
	 * @param array<string, mixed> $tracking_item Tracking item.
	 * @param \WC_Order            $order Order object.
	 * @return array<string, string>|null
	 */
	public function fetch_status( array $tracking_item, \WC_Order $order ): ?array {
		if ( ! $this->is_configured() || ! Settings::aftership_live_requests_enabled() ) {
			return null;
		}

		$tracking_number = sanitize_text_field( (string) ( $tracking_item['tracking_number'] ?? '' ) );
		$carrier_id      = sanitize_key( (string) ( $tracking_item['carrier_id'] ?? '' ) );
		$carrier_name    = sanitize_text_field( (string) ( $tracking_item['carrier_name'] ?? '' ) );
		$slug            = $this->map_carrier_slug( $carrier_id, $carrier_name );

		if ( '' === $tracking_number || '' === $slug ) {
			return null;
		}

		$base_url = untrailingslashit( Settings::aftership_base_url() );
		$url      = sprintf(
			'%s/trackings/%s/%s',
			esc_url_raw( $base_url ),
			rawurlencode( $slug ),
			rawurlencode( $tracking_number )
		);

		$response = wp_remote_get(
			$url,
			array(
				'timeout' => 12,
				'headers' => array(
					'aftership-api-key' => Settings::aftership_api_key(),
					'Content-Type'      => 'application/json',
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return null;
		}

		$status_code = (int) wp_remote_retrieve_response_code( $response );

		if ( $status_code < 200 || $status_code >= 300 ) {
			return null;
		}

		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( (string) $body, true );

		if ( ! is_array( $data ) ) {
			return null;
		}

		return $this->map_response( $data );
	}

	/**
	 * Map known carrier IDs/names to AfterShip slugs.
	 *
	 * @param string $carrier_id Carrier ID.
	 * @param string $carrier_name Carrier name.
	 * @return string
	 */
	private function map_carrier_slug( string $carrier_id, string $carrier_name ): string {
		$slug_map = apply_filters(
			'wbsot_aftership_carrier_slugs',
			array(
				'fedex'     => 'fedex',
				'dhl'       => 'dhl',
				'ups'       => 'ups',
				'usps'      => 'usps',
				'bluedart'  => 'bluedart',
				'delhivery' => 'delhivery',
				'dtdc'      => 'dtdc',
				'indiapost' => 'india-post',
				'aramex'    => 'aramex',
			)
		);

		if ( isset( $slug_map[ $carrier_id ] ) ) {
			return sanitize_text_field( (string) $slug_map[ $carrier_id ] );
		}

		$normalized_name = strtolower( trim( $carrier_name ) );

		foreach ( $slug_map as $id => $slug ) {
			if ( strtolower( Carriers::name_from_id( (string) $id ) ) === $normalized_name ) {
				return sanitize_text_field( (string) $slug );
			}
		}

		return '';
	}

	/**
	 * Map AfterShip response to internal status payload.
	 *
	 * @param array<string, mixed> $data API response data.
	 * @return array<string, string>|null
	 */
	private function map_response( array $data ): ?array {
		$tracking = $data['data']['tracking'] ?? null;

		if ( ! is_array( $tracking ) ) {
			return null;
		}

		$tag = sanitize_text_field( (string) ( $tracking['tag'] ?? '' ) );

		if ( '' === $tag ) {
			return null;
		}

		$status = $this->normalize_status( $tag );

		if ( '' === $status ) {
			return null;
		}

		return array(
			'status'       => $status,
			'status_label' => $tag,
		);
	}

	/**
	 * Convert provider-specific status values to internal slugs.
	 *
	 * @param string $tag Raw status tag.
	 * @return string
	 */
	private function normalize_status( string $tag ): string {
		$normalized = strtolower( str_replace( array( ' ', '-' ), '_', trim( $tag ) ) );
		$map        = array(
			'pending'          => 'pending',
			'info_received'    => 'pending',
			'in_transit'       => 'in_transit',
			'out_for_delivery' => 'out_for_delivery',
			'attempt_fail'     => 'delivery_failed',
			'failed_attempt'   => 'delivery_failed',
			'exception'        => 'exception',
			'expired'          => 'exception',
			'delivered'        => 'delivered',
			'available_for_pickup' => 'out_for_delivery',
		);

		return $map[ $normalized ] ?? '';
	}
}
