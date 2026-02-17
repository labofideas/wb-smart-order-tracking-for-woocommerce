<?php
namespace WBCOM\WBSOT;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Shortcode {
	/**
	 * Ensure shortcode styles are printed once per request.
	 *
	 * @var bool
	 */
	private static bool $styles_printed = false;

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		add_shortcode( 'wb_order_tracking', array( $this, 'render_shortcode' ) );
	}

	/**
	 * Render order tracking shortcode.
	 *
	 * @return string
	 */
	public function render_shortcode(): string {
		if ( ! Settings::is_enabled() || ! Settings::public_tracking_enabled() ) {
			return '<p>' . esc_html__( 'Order tracking is currently unavailable.', 'wb-smart-order-tracking-for-woocommerce' ) . '</p>';
		}

		$order_id = isset( $_POST['wbsot_order_id'] ) ? absint( wp_unslash( $_POST['wbsot_order_id'] ) ) : 0;
		$email    = isset( $_POST['wbsot_billing_email'] ) ? sanitize_email( wp_unslash( $_POST['wbsot_billing_email'] ) ) : '';
		$has_post = isset( $_POST['wbsot_track_order'] );

		ob_start();
		$this->print_shortcode_styles();
		?>
		<form method="post" class="wb-order-tracking-form" style="margin-bottom:24px;">
			<?php wp_nonce_field( 'wbsot_track_order', 'wbsot_track_nonce' ); ?>
			<p>
				<label for="wbsot_order_id"><?php esc_html_e( 'Order ID', 'wb-smart-order-tracking-for-woocommerce' ); ?></label>
				<input type="number" required id="wbsot_order_id" name="wbsot_order_id" value="<?php echo esc_attr( $order_id ); ?>" />
			</p>
			<p>
				<label for="wbsot_billing_email"><?php esc_html_e( 'Billing Email', 'wb-smart-order-tracking-for-woocommerce' ); ?></label>
				<input type="email" required id="wbsot_billing_email" name="wbsot_billing_email" value="<?php echo esc_attr( $email ); ?>" />
			</p>
			<p>
				<button type="submit" class="button" name="wbsot_track_order" value="1"><?php esc_html_e( 'Track Order', 'wb-smart-order-tracking-for-woocommerce' ); ?></button>
			</p>
		</form>
		<?php

		if ( ! $has_post ) {
			return (string) ob_get_clean();
		}

		if ( empty( $_POST['wbsot_track_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['wbsot_track_nonce'] ) ), 'wbsot_track_order' ) ) {
			echo '<p>' . esc_html__( 'Invalid request. Please try again.', 'wb-smart-order-tracking-for-woocommerce' ) . '</p>';
			return (string) ob_get_clean();
		}

		if ( $this->is_lookup_rate_limited( $email ) ) {
			echo '<p>' . esc_html__( 'Too many tracking requests. Please wait 15 minutes and try again.', 'wb-smart-order-tracking-for-woocommerce' ) . '</p>';
			return (string) ob_get_clean();
		}

		$order = wc_get_order( $order_id );

		if ( ! $order ) {
			echo '<p>' . esc_html__( 'Order not found.', 'wb-smart-order-tracking-for-woocommerce' ) . '</p>';
			return (string) ob_get_clean();
		}

		$billing_email = (string) $order->get_billing_email();

		if ( '' === $email || strtolower( $email ) !== strtolower( $billing_email ) ) {
			echo '<p>' . esc_html__( 'Order details do not match. Please check your order ID and billing email.', 'wb-smart-order-tracking-for-woocommerce' ) . '</p>';
			return (string) ob_get_clean();
		}

		$items = Admin_Order::get_tracking_items( $order->get_id() );

		echo '<div class="wb-order-tracking-results">';
		echo '<h3>' . esc_html__( 'Tracking Results', 'wb-smart-order-tracking-for-woocommerce' ) . '</h3>';
		echo '<p><strong>' . esc_html__( 'Order Status:', 'wb-smart-order-tracking-for-woocommerce' ) . '</strong> ' . esc_html( wc_get_order_status_name( $order->get_status() ) ) . '</p>';

		if ( empty( $items ) ) {
			echo '<p>' . esc_html__( 'Tracking details are not available yet for this order.', 'wb-smart-order-tracking-for-woocommerce' ) . '</p>';
		} else {
			echo '<div class="wb-order-tracking-table-wrap">';
			echo '<table class="shop_table shop_table_responsive wb-order-tracking-table" cellspacing="0">';
			echo '<thead><tr><th>' . esc_html__( 'Carrier', 'wb-smart-order-tracking-for-woocommerce' ) . '</th><th>' . esc_html__( 'Tracking Number', 'wb-smart-order-tracking-for-woocommerce' ) . '</th><th>' . esc_html__( 'Shipment Status', 'wb-smart-order-tracking-for-woocommerce' ) . '</th><th>' . esc_html__( 'Track', 'wb-smart-order-tracking-for-woocommerce' ) . '</th></tr></thead>';
			echo '<tbody>';

			foreach ( $items as $item ) {
				$status     = sanitize_key( $item['status'] ?? '' );
				$status_raw = sanitize_text_field( (string) ( $item['status_label'] ?? '' ) );
				$status_ui  = '' !== $status_raw ? $status_raw : ( '' !== $status ? ucwords( str_replace( '_', ' ', $status ) ) : __( 'Pending Sync', 'wb-smart-order-tracking-for-woocommerce' ) );
				$last_sync  = sanitize_text_field( (string) ( $item['last_sync'] ?? '' ) );

				echo '<tr>';
				echo '<td>' . esc_html( $item['carrier_name'] ) . '</td>';
				echo '<td>' . esc_html( $item['tracking_number'] ) . '</td>';
				echo '<td>' . esc_html( $status_ui );
				if ( '' !== $last_sync ) {
					/* translators: %s: last sync datetime string. */
					echo '<br /><small>' . esc_html( sprintf( __( 'Updated: %s', 'wb-smart-order-tracking-for-woocommerce' ), $last_sync ) ) . '</small>';
				}
				echo '</td>';
				echo '<td>';
				if ( ! empty( $item['tracking_url'] ) ) {
					echo '<a class="button" target="_blank" rel="noopener noreferrer" href="' . esc_url( $item['tracking_url'] ) . '">' . esc_html__( 'Track Shipment', 'wb-smart-order-tracking-for-woocommerce' ) . '</a>';
				} else {
					echo '&mdash;';
				}
				echo '</td>';
				echo '</tr>';
			}

			echo '</tbody></table>';
			echo '</div>';
		}

		echo '</div>';

		return (string) ob_get_clean();
	}

	/**
	 * Check and increment public tracking request throttle.
	 *
	 * @param string $email Billing email used for lookup.
	 * @return bool True if rate limited.
	 */
	private function is_lookup_rate_limited( string $email ): bool {
		$window_seconds = (int) apply_filters( 'wbsot_public_tracking_rate_window', 15 * MINUTE_IN_SECONDS );
		$window_seconds = max( 60, $window_seconds );
		$max_attempts   = Settings::public_tracking_rate_limit();
		$key            = $this->lookup_rate_key( $email );
		$ip             = $this->client_ip();
		$lock_key       = $this->lookup_lock_key( $ip );
		$lock_until     = get_transient( $lock_key );

		if ( is_numeric( $lock_until ) && (int) $lock_until > time() ) {
			Security_Events::add(
				'tracking_lookup_locked',
				array(
					'email'    => $email,
					'ip'       => $ip,
					'cooldown' => max( 0, (int) $lock_until - time() ),
				)
			);
			return true;
		}

		$current        = get_transient( $key );

		if ( ! is_array( $current ) ) {
			$current = array( 'count' => 0 );
		}

		$count = isset( $current['count'] ) ? absint( $current['count'] ) : 0;

		if ( $count >= $max_attempts ) {
			$cooldown = $this->apply_lockout_escalation( $ip, $window_seconds );
			Security_Events::add(
				'tracking_lookup_rate_limited',
				array(
					'email'    => $email,
					'ip'       => $ip,
					'cooldown' => $cooldown,
				)
			);
			return true;
		}

		set_transient(
			$key,
			array(
				'count' => $count + 1,
			),
			$window_seconds
		);

		return false;
	}

	/**
	 * Apply progressive cooldown for repeated bursts.
	 *
	 * @param string $ip Client IP.
	 * @param int    $window_seconds Base throttle window in seconds.
	 * @return int Cooldown seconds applied.
	 */
	private function apply_lockout_escalation( string $ip, int $window_seconds ): int {
		$strikes_key = 'wbsot_lookup_strikes_' . md5( $ip );
		$lock_key    = $this->lookup_lock_key( $ip );
		$strikes     = absint( (string) get_transient( $strikes_key ) );
		$strikes     = max( 1, $strikes + 1 );
		$multiplier  = min( 8, 1 << ( $strikes - 1 ) );
		$cooldown    = $window_seconds * $multiplier;

		set_transient( $strikes_key, $strikes, DAY_IN_SECONDS );
		set_transient( $lock_key, time() + $cooldown, $cooldown );

		return $cooldown;
	}

	/**
	 * Build lookup throttle key.
	 *
	 * @param string $email Billing email used for lookup.
	 * @return string
	 */
	private function lookup_rate_key( string $email ): string {
		$ip = $this->client_ip();

		return 'wbsot_lookup_' . md5( strtolower( trim( $email ) ) . '|' . $ip );
	}

	/**
	 * Build lock key for IP level cooldown.
	 *
	 * @param string $ip Client IP.
	 * @return string
	 */
	private function lookup_lock_key( string $ip ): string {
		return 'wbsot_lookup_lock_' . md5( $ip );
	}

	/**
	 * Get request client IP for throttling key.
	 *
	 * @return string
	 */
	private function client_ip(): string {
		$remote = sanitize_text_field( (string) filter_input( INPUT_SERVER, 'REMOTE_ADDR', FILTER_SANITIZE_FULL_SPECIAL_CHARS ) );

		if ( '' === $remote ) {
			return 'unknown';
		}

		return $remote;
	}

	/**
	 * Print shortcode styles.
	 *
	 * @return void
	 */
	private function print_shortcode_styles(): void {
		if ( self::$styles_printed ) {
			return;
		}

		self::$styles_printed = true;
		echo '<style id="wbsot-shortcode-styles">';
		echo '.wb-order-tracking-form input[type=\"number\"],.wb-order-tracking-form input[type=\"email\"]{width:100%;max-width:420px;box-sizing:border-box;}';
		echo '.wb-order-tracking-table-wrap{max-width:100%;overflow-x:auto;-webkit-overflow-scrolling:touch;}';
		echo '.wb-order-tracking-table{min-width:640px;}';
		echo '.wb-order-tracking-table td,.wb-order-tracking-table th{white-space:normal;overflow-wrap:anywhere;word-break:break-word;vertical-align:top;}';
		echo '</style>';
	}
}
