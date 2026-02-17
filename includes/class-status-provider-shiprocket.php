<?php
namespace WBCOM\WBSOT;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Status_Provider_Shiprocket implements Status_Provider_Interface {
	/**
	 * Get provider ID.
	 *
	 * @return string
	 */
	public function get_id(): string {
		return 'shiprocket';
	}

	/**
	 * Whether provider is configured.
	 *
	 * @return bool
	 */
	public function is_configured(): bool {
		return Settings::shiprocket_enabled() && '' !== Settings::shiprocket_api_token();
	}

	/**
	 * Fetch status from Shiprocket API.
	 *
	 * Live requests run only when explicitly enabled in settings.
	 *
	 * @param array<string, mixed> $tracking_item Tracking item.
	 * @param \WC_Order            $order Order object.
	 * @return array<string, string>|null
	 */
	public function fetch_status( array $tracking_item, \WC_Order $order ): ?array {
		if ( ! $this->is_configured() || ! Settings::shiprocket_live_requests_enabled() ) {
			return null;
		}

		$tracking_number = sanitize_text_field( (string) ( $tracking_item['tracking_number'] ?? '' ) );

		if ( '' === $tracking_number ) {
			return null;
		}

		$url = sprintf(
			'%s/courier/track/awb/%s',
			untrailingslashit( Settings::shiprocket_base_url() ),
			rawurlencode( $tracking_number )
		);

		$response = wp_remote_get(
			esc_url_raw( $url ),
			array(
				'timeout' => 12,
				'headers' => array(
					'Authorization' => 'Bearer ' . Settings::shiprocket_api_token(),
					'Content-Type'  => 'application/json',
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
	 * Map Shiprocket response to internal payload.
	 *
	 * @param array<string, mixed> $data Raw API response.
	 * @return array<string, string>|null
	 */
	private function map_response( array $data ): ?array {
		$candidates = array(
			$data['tracking_data']['shipment_status'] ?? '',
			$data['tracking_data']['track_status'] ?? '',
			$data['tracking_data']['shipment_track'][0]['current_status'] ?? '',
			$data['tracking_data']['shipment_track_activities'][0]['activity'] ?? '',
			$data['current_status'] ?? '',
			$data['status'] ?? '',
		);

		$raw_status = '';

		foreach ( $candidates as $candidate ) {
			$candidate = sanitize_text_field( (string) $candidate );
			if ( '' !== $candidate ) {
				$raw_status = $candidate;
				break;
			}
		}

		if ( '' === $raw_status ) {
			return null;
		}

		$status = $this->normalize_status( $raw_status );

		if ( '' === $status ) {
			return null;
		}

		return array(
			'status'       => $status,
			'status_label' => $raw_status,
		);
	}

	/**
	 * Normalize Shiprocket statuses to internal values.
	 *
	 * @param string $raw_status Raw status string.
	 * @return string
	 */
	private function normalize_status( string $raw_status ): string {
		$normalized = strtolower( str_replace( array( ' ', '-' ), '_', trim( $raw_status ) ) );

		$map = apply_filters(
			'wbsot_shiprocket_status_map',
			array(
				'in_transit'       => 'in_transit',
				'out_for_delivery' => 'out_for_delivery',
				'delivered'        => 'delivered',
				'ndr'              => 'delivery_failed',
				'failed'           => 'delivery_failed',
				'undelivered'      => 'delivery_failed',
				'rto_initiated'    => 'exception',
				'rto_delivered'    => 'exception',
				'pickup_scheduled' => 'pending',
				'label_generated'  => 'pending',
			)
		);

		return $map[ $normalized ] ?? '';
	}
}
