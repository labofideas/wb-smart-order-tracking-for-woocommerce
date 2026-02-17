<?php
namespace WBCOM\WBSOT;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Settings {
	/**
	 * Default option values.
	 *
	 * @var array<string, mixed>
	 */
	private static array $defaults = array(
		'wbsot_enabled'                => 'yes',
		'wbsot_enable_my_account'      => 'yes',
		'wbsot_enable_emails'          => 'yes',
		'wbsot_enable_public_tracking' => 'yes',
		'wbsot_public_tracking_rate_limit' => '20',
		'wbsot_csv_strict_mode'        => 'no',
		'wbsot_csv_allowed_statuses'   => array(),
		'wbsot_enable_csv_import'      => 'yes',
		'wbsot_allow_multiple'         => 'yes',
		'wbsot_enabled_carriers'       => array(),
		'wbsot_aftership_enabled'      => 'no',
		'wbsot_aftership_api_key'      => '',
		'wbsot_aftership_base_url'     => 'https://api.aftership.com/v4',
		'wbsot_aftership_live_requests' => 'no',
		'wbsot_shiprocket_enabled'      => 'no',
		'wbsot_shiprocket_api_token'    => '',
		'wbsot_shiprocket_base_url'     => 'https://apiv2.shiprocket.in/v1/external',
		'wbsot_shiprocket_live_requests' => 'no',
		'wbsot_sync_interval'           => 'wbsot_15min',
		'wbsot_sync_batch_size'         => '10',
	);

	/**
	 * Get option with default fallback.
	 *
	 * @param string $key Option key.
	 * @return mixed
	 */
	public static function get( string $key ) {
		$default = self::$defaults[ $key ] ?? '';
		$value   = get_option( $key, $default );

		if ( 'wbsot_enabled_carriers' === $key ) {
			return is_array( $value ) ? $value : array();
		}

		return $value;
	}

	/**
	 * Check if plugin is enabled.
	 *
	 * @return bool
	 */
	public static function is_enabled(): bool {
		return 'yes' === self::get( 'wbsot_enabled' );
	}

	/**
	 * Check if account section is enabled.
	 *
	 * @return bool
	 */
	public static function my_account_enabled(): bool {
		return 'yes' === self::get( 'wbsot_enable_my_account' );
	}

	/**
	 * Check if email section is enabled.
	 *
	 * @return bool
	 */
	public static function emails_enabled(): bool {
		return 'yes' === self::get( 'wbsot_enable_emails' );
	}

	/**
	 * Check if public tracking shortcode is enabled.
	 *
	 * @return bool
	 */
	public static function public_tracking_enabled(): bool {
		return 'yes' === self::get( 'wbsot_enable_public_tracking' );
	}

	/**
	 * Get max public tracking attempts per window.
	 *
	 * @return int
	 */
	public static function public_tracking_rate_limit(): int {
		$limit = (int) self::get( 'wbsot_public_tracking_rate_limit' );

		return max( 1, min( 200, $limit ) );
	}

	/**
	 * Check if CSV import is enabled.
	 *
	 * @return bool
	 */
	public static function csv_import_enabled(): bool {
		return 'yes' === self::get( 'wbsot_enable_csv_import' );
	}

	/**
	 * Check if strict CSV validation mode is enabled.
	 *
	 * @return bool
	 */
	public static function csv_strict_mode_enabled(): bool {
		return 'yes' === self::get( 'wbsot_csv_strict_mode' );
	}

	/**
	 * Get allowed order statuses for CSV import strict mode.
	 *
	 * @return array<int, string>
	 */
	public static function csv_allowed_statuses(): array {
		$value = self::get( 'wbsot_csv_allowed_statuses' );

		if ( ! is_array( $value ) ) {
			$value = array();
		}

		$statuses = array_values( array_unique( array_filter( array_map( 'sanitize_key', $value ) ) ) );

		if ( empty( $statuses ) ) {
			$statuses = array( 'processing', 'completed' );
		}

		return $statuses;
	}

	/**
	 * Check if multiple tracking numbers are enabled.
	 *
	 * @return bool
	 */
	public static function multiple_tracking_enabled(): bool {
		return 'yes' === self::get( 'wbsot_allow_multiple' );
	}

	/**
	 * Get enabled carriers.
	 *
	 * @return array<int, string>
	 */
	public static function enabled_carriers(): array {
		$carriers = self::get( 'wbsot_enabled_carriers' );

		if ( ! is_array( $carriers ) || empty( $carriers ) ) {
			return array_keys( Carriers::all() );
		}

		$known = array_keys( Carriers::all() );

		return array_values(
			array_filter(
				array_map( 'sanitize_key', $carriers ),
				static fn( string $carrier ): bool => in_array( $carrier, $known, true )
			)
		);
	}

	/**
	 * Check if AfterShip provider is enabled.
	 *
	 * @return bool
	 */
	public static function aftership_enabled(): bool {
		return 'yes' === self::get( 'wbsot_aftership_enabled' );
	}

	/**
	 * Get AfterShip API key.
	 *
	 * @return string
	 */
	public static function aftership_api_key(): string {
		return trim( (string) self::get( 'wbsot_aftership_api_key' ) );
	}

	/**
	 * Get AfterShip API base URL.
	 *
	 * @return string
	 */
	public static function aftership_base_url(): string {
		$url = trim( (string) self::get( 'wbsot_aftership_base_url' ) );

		return '' !== $url ? $url : (string) self::$defaults['wbsot_aftership_base_url'];
	}

	/**
	 * Check if live provider requests are enabled.
	 *
	 * @return bool
	 */
	public static function aftership_live_requests_enabled(): bool {
		return 'yes' === self::get( 'wbsot_aftership_live_requests' );
	}

	/**
	 * Check if Shiprocket provider is enabled.
	 *
	 * @return bool
	 */
	public static function shiprocket_enabled(): bool {
		return 'yes' === self::get( 'wbsot_shiprocket_enabled' );
	}

	/**
	 * Get Shiprocket API token.
	 *
	 * @return string
	 */
	public static function shiprocket_api_token(): string {
		return trim( (string) self::get( 'wbsot_shiprocket_api_token' ) );
	}

	/**
	 * Get Shiprocket API base URL.
	 *
	 * @return string
	 */
	public static function shiprocket_base_url(): string {
		$url = trim( (string) self::get( 'wbsot_shiprocket_base_url' ) );

		return '' !== $url ? $url : (string) self::$defaults['wbsot_shiprocket_base_url'];
	}

	/**
	 * Check if live Shiprocket requests are enabled.
	 *
	 * @return bool
	 */
	public static function shiprocket_live_requests_enabled(): bool {
		return 'yes' === self::get( 'wbsot_shiprocket_live_requests' );
	}

	/**
	 * Get cron recurrence key for sync schedule.
	 *
	 * @return string
	 */
	public static function sync_interval(): string {
		$value = sanitize_key( (string) self::get( 'wbsot_sync_interval' ) );
		$valid = array( 'wbsot_5min', 'wbsot_15min', 'wbsot_30min', 'hourly' );

		return in_array( $value, $valid, true ) ? $value : 'wbsot_15min';
	}

	/**
	 * Get max orders to process per sync run.
	 *
	 * @return int
	 */
	public static function sync_batch_size(): int {
		$size = (int) self::get( 'wbsot_sync_batch_size' );

		return max( 1, min( 100, $size ) );
	}
}
