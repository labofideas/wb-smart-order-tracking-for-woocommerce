<?php
namespace WBCOM\WBSOT;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Carrier_Detector {
	/**
	 * Detect carrier by tracking number.
	 *
	 * @param string $tracking_number Tracking number.
	 * @return string
	 */
	public static function detect( string $tracking_number ): string {
		$tracking_number = strtoupper( trim( $tracking_number ) );

		if ( '' === $tracking_number ) {
			return '';
		}

		$patterns = apply_filters(
			'wbsot_carrier_detection_patterns',
			array(
				'ups'       => '/^1Z[0-9A-Z]{16}$/',
				'fedex'     => '/^(?:\\d{12}|\\d{15}|\\d{20}|\\d{22})$/',
				'dhl'       => '/^(?:\\d{10}|JJD\\d{18}|JD\\d{18,20})$/',
				'usps'      => '/^(?:94|93|92|95)\\d{20,22}$/',
				'delhivery' => '/^[A-Z]{2,4}\\d{8,14}IN$/',
				'dtdc'      => '/^[A-Z]\\d{8,12}$/',
			)
		);

		if ( ! is_array( $patterns ) ) {
			return '';
		}

		foreach ( $patterns as $carrier_id => $pattern ) {
			$carrier_id = sanitize_key( (string) $carrier_id );

			if ( ! is_string( $pattern ) || '' === $carrier_id ) {
				continue;
			}

			if ( 1 === @preg_match( $pattern, $tracking_number ) ) {
				return $carrier_id;
			}
		}

		return '';
	}
}
