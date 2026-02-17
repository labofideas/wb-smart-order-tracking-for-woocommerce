<?php
namespace WBCOM\WBSOT;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Security_Events {
	/**
	 * Option key for security events.
	 */
	private const OPTION_KEY = 'wbsot_security_events';

	/**
	 * Store one security event entry.
	 *
	 * @param string $event Event slug.
	 * @param array<string, string|int> $context Event context.
	 * @return void
	 */
	public static function add( string $event, array $context = array() ): void {
		$events = self::all();

		$events[] = array(
			'event'      => sanitize_key( $event ),
			'email_hint' => self::mask_email( (string) ( $context['email'] ?? '' ) ),
			'ip'         => sanitize_text_field( (string) ( $context['ip'] ?? '' ) ),
			'cooldown'   => absint( (string) ( $context['cooldown'] ?? 0 ) ),
			'recorded_at' => gmdate( 'Y-m-d H:i:s' ),
		);

		if ( count( $events ) > 100 ) {
			$events = array_slice( $events, -100 );
		}

		update_option( self::OPTION_KEY, $events, false );
	}

	/**
	 * Get all logged security events.
	 *
	 * @return array<int, array<string, string|int>>
	 */
	public static function all(): array {
		$events = get_option( self::OPTION_KEY, array() );

		return is_array( $events ) ? $events : array();
	}

	/**
	 * Clear logged security events.
	 *
	 * @return void
	 */
	public static function clear(): void {
		delete_option( self::OPTION_KEY );
	}

	/**
	 * Build masked email string for logs.
	 *
	 * @param string $email Email.
	 * @return string
	 */
	private static function mask_email( string $email ): string {
		$email = sanitize_email( $email );

		if ( '' === $email || ! str_contains( $email, '@' ) ) {
			return '';
		}

		list( $name, $domain ) = explode( '@', $email, 2 );
		$prefix = substr( $name, 0, 2 );

		return sanitize_text_field( $prefix . '***@' . $domain );
	}
}
