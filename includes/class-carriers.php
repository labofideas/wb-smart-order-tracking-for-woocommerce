<?php
namespace WBCOM\WBSOT;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Carriers {
	/**
	 * Get built-in carriers.
	 *
	 * @return array<string, array<string, string>>
	 */
	public static function all(): array {
		return array(
			'fedex'      => array(
				'name' => 'FedEx',
				'url'  => 'https://www.fedex.com/fedextrack/?trknbr={tracking_number}',
			),
			'dhl'        => array(
				'name' => 'DHL',
				'url'  => 'https://www.dhl.com/global-en/home/tracking.html?tracking-id={tracking_number}',
			),
			'ups'        => array(
				'name' => 'UPS',
				'url'  => 'https://www.ups.com/track?tracknum={tracking_number}',
			),
			'usps'       => array(
				'name' => 'USPS',
				'url'  => 'https://tools.usps.com/go/TrackConfirmAction?tLabels={tracking_number}',
			),
			'bluedart'   => array(
				'name' => 'BlueDart',
				'url'  => 'https://www.bluedart.com/tracking?trackingNumber={tracking_number}',
			),
			'delhivery'  => array(
				'name' => 'Delhivery',
				'url'  => 'https://www.delhivery.com/track-v2/package/{tracking_number}',
			),
			'dtdc'       => array(
				'name' => 'DTDC',
				'url'  => 'https://www.dtdc.in/tracking/tracking_results.asp?strCnno={tracking_number}',
			),
			'indiapost'  => array(
				'name' => 'India Post',
				'url'  => 'https://www.indiapost.gov.in/_layouts/15/dop.portal.tracking/trackconsignment.aspx?ConsignmentNo={tracking_number}',
			),
			'aramex'     => array(
				'name' => 'Aramex',
				'url'  => 'https://www.aramex.com/track/shipments/{tracking_number}',
			),
			'custom'     => array(
				'name' => __( 'Custom Carrier', 'wb-smart-order-tracking-for-woocommerce' ),
				'url'  => '',
			),
		);
	}

	/**
	 * Get carrier options for select fields.
	 *
	 * @return array<string, string>
	 */
	public static function options(): array {
		$options = array();

		foreach ( self::all() as $id => $carrier ) {
			$options[ $id ] = $carrier['name'];
		}

		return $options;
	}

	/**
	 * Get carrier display name from ID.
	 *
	 * @param string $carrier_id Carrier ID.
	 * @return string
	 */
	public static function name_from_id( string $carrier_id ): string {
		$carrier_id = sanitize_key( $carrier_id );
		$all        = self::all();

		return $all[ $carrier_id ]['name'] ?? '';
	}

	/**
	 * Build carrier tracking URL from preset.
	 *
	 * @param string $carrier_id Carrier ID.
	 * @param string $tracking_number Tracking number.
	 * @return string
	 */
	public static function build_url( string $carrier_id, string $tracking_number ): string {
		$carrier_id       = sanitize_key( $carrier_id );
		$tracking_number  = trim( $tracking_number );
		$all              = self::all();
		$url_pattern      = $all[ $carrier_id ]['url'] ?? '';

		if ( '' === $url_pattern || '' === $tracking_number ) {
			return '';
		}

		return str_replace( '{tracking_number}', rawurlencode( $tracking_number ), $url_pattern );
	}
}
