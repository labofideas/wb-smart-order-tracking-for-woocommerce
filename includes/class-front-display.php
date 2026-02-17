<?php
namespace WBCOM\WBSOT;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Front_Display {
	/**
	 * Ensure styles are printed once per request.
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
		add_action( 'woocommerce_order_details_after_order_table', array( $this, 'render_my_account_tracking' ) );
		add_action( 'woocommerce_email_after_order_table', array( $this, 'render_email_tracking' ), 20, 4 );
	}

	/**
	 * Render tracking block in My Account order details.
	 *
	 * @param \WC_Order $order Order object.
	 * @return void
	 */
	public function render_my_account_tracking( \WC_Order $order ): void {
		if ( ! Settings::is_enabled() || ! Settings::my_account_enabled() ) {
			return;
		}

		$items = Admin_Order::get_tracking_items( $order->get_id() );

		if ( empty( $items ) ) {
			return;
		}

		echo '<section class="woocommerce-order-tracking">';
		echo '<h2>' . esc_html__( 'Shipment Tracking', 'wb-smart-order-tracking-for-woocommerce' ) . '</h2>';
		$this->render_tracking_table( $items );
		echo '</section>';
	}

	/**
	 * Render tracking block in order emails.
	 *
	 * @param \WC_Order  $order Order object.
	 * @param bool       $sent_to_admin Sent to admin.
	 * @param bool       $plain_text Plain text email.
	 * @param \WC_Email $email Email object.
	 * @return void
	 */
	public function render_email_tracking( \WC_Order $order, bool $sent_to_admin, bool $plain_text, $email ): void {
		if ( $sent_to_admin || ! Settings::is_enabled() || ! Settings::emails_enabled() ) {
			return;
		}

		$email_id = $email->id ?? '';

		if ( ! in_array( $email_id, array( 'customer_processing_order', 'customer_completed_order' ), true ) ) {
			return;
		}

		$items = Admin_Order::get_tracking_items( $order->get_id() );

		if ( empty( $items ) ) {
			return;
		}

		if ( $plain_text ) {
			echo "\n" . esc_html__( 'Shipment Tracking', 'wb-smart-order-tracking-for-woocommerce' ) . "\n";
			foreach ( $items as $item ) {
				$status_label = $this->status_label( $item );
				echo sprintf(
					"%s: %s | %s (%s)\n",
					esc_html( $item['carrier_name'] ),
					esc_html( $item['tracking_number'] ),
					esc_html( $status_label ),
					esc_url( $item['tracking_url'] )
				);
			}
			return;
		}

		echo '<h2>' . esc_html__( 'Shipment Tracking', 'wb-smart-order-tracking-for-woocommerce' ) . '</h2>';
		$this->render_tracking_table( $items );
	}

	/**
	 * Render tracking table.
	 *
	 * @param array<int, array<string, string>> $items Tracking items.
	 * @return void
	 */
	private function render_tracking_table( array $items ): void {
		$this->print_tracking_styles();
		echo '<div class="wb-order-tracking-table-wrap">';
		echo '<table class="shop_table shop_table_responsive wb-order-tracking-table" cellspacing="0" style="margin-bottom:16px;">';
		echo '<thead><tr>';
		echo '<th>' . esc_html__( 'Carrier', 'wb-smart-order-tracking-for-woocommerce' ) . '</th>';
		echo '<th>' . esc_html__( 'Tracking Number', 'wb-smart-order-tracking-for-woocommerce' ) . '</th>';
		echo '<th>' . esc_html__( 'Shipment Status', 'wb-smart-order-tracking-for-woocommerce' ) . '</th>';
		echo '<th>' . esc_html__( 'Track Shipment', 'wb-smart-order-tracking-for-woocommerce' ) . '</th>';
		echo '</tr></thead><tbody>';

		foreach ( $items as $item ) {
			$status_label = $this->status_label( $item );
			$last_sync    = sanitize_text_field( (string) ( $item['last_sync'] ?? '' ) );

			echo '<tr>';
			echo '<td>' . esc_html( $item['carrier_name'] ) . '</td>';
			echo '<td>' . esc_html( $item['tracking_number'] ) . '</td>';
			echo '<td>' . esc_html( $status_label );

			if ( '' !== $last_sync ) {
				/* translators: %s: last sync datetime string. */
				echo '<br /><small>' . esc_html( sprintf( __( 'Updated: %s', 'wb-smart-order-tracking-for-woocommerce' ), $last_sync ) ) . '</small>';
			}

			echo '</td>';
			echo '<td>';

			if ( ! empty( $item['tracking_url'] ) ) {
				echo '<a class="button" target="_blank" rel="noopener noreferrer" href="' . esc_url( $item['tracking_url'] ) . '">';
				echo esc_html__( 'Track', 'wb-smart-order-tracking-for-woocommerce' );
				echo '</a>';
			} else {
				echo '&mdash;';
			}

			echo '</td>';
			echo '</tr>';
		}

		echo '</tbody></table>';
		echo '</div>';
	}

	/**
	 * Print scoped tracking styles.
	 *
	 * @return void
	 */
	private function print_tracking_styles(): void {
		if ( self::$styles_printed ) {
			return;
		}

		self::$styles_printed = true;
		echo '<style id="wbsot-front-styles">';
		echo '.wb-order-tracking-table-wrap{max-width:100%;overflow-x:auto;-webkit-overflow-scrolling:touch;}';
		echo '.wb-order-tracking-table{min-width:640px;}';
		echo '.wb-order-tracking-table td,.wb-order-tracking-table th{white-space:normal;overflow-wrap:anywhere;word-break:break-word;vertical-align:top;}';
		echo '</style>';
	}

	/**
	 * Get display label for shipment status.
	 *
	 * @param array<string, string> $item Tracking item.
	 * @return string
	 */
	private function status_label( array $item ): string {
		$status = sanitize_key( $item['status'] ?? '' );
		$label  = sanitize_text_field( (string) ( $item['status_label'] ?? '' ) );

		if ( '' !== $label ) {
			return $label;
		}

		if ( '' === $status ) {
			return __( 'Pending Sync', 'wb-smart-order-tracking-for-woocommerce' );
		}

		return ucwords( str_replace( '_', ' ', $status ) );
	}
}
