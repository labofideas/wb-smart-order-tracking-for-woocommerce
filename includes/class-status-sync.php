<?php
namespace WBCOM\WBSOT;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Status_Sync {
	/**
	 * Provider manager instance.
	 *
	 * @var Status_Provider_Manager|null
	 */
	private ?Status_Provider_Manager $provider_manager = null;
	/**
	 * Cron hook name.
	 */
	private const CRON_HOOK = 'wbsot_status_sync_event';

	/**
	 * Queue option key.
	 */
	private const QUEUE_OPTION = 'wbsot_sync_queue';

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		$this->provider_manager = new Status_Provider_Manager();

		add_filter( 'cron_schedules', array( $this, 'register_schedule' ) );
		add_action( 'init', array( $this, 'schedule_event' ) );
		add_action( self::CRON_HOOK, array( $this, 'run_sync' ) );

		add_action( 'wbsot_tracking_added', array( $this, 'queue_from_tracking_added' ), 10, 2 );
		add_action( 'woocommerce_order_status_completed', array( $this, 'queue_order_for_sync' ), 10, 1 );
	}

	/**
	 * Register custom schedule.
	 *
	 * @param array<string, array<string, mixed>> $schedules Existing schedules.
	 * @return array<string, array<string, mixed>>
	 */
	public function register_schedule( array $schedules ): array {
		if ( ! isset( $schedules['wbsot_5min'] ) ) {
			$schedules['wbsot_5min'] = array(
				'interval' => 5 * MINUTE_IN_SECONDS,
				'display'  => __( 'Every 5 minutes (WB Order Tracking)', 'wb-smart-order-tracking-for-woocommerce' ),
			);
		}

		if ( ! isset( $schedules['wbsot_15min'] ) ) {
			$schedules['wbsot_15min'] = array(
				'interval' => 15 * MINUTE_IN_SECONDS,
				'display'  => __( 'Every 15 minutes (WB Order Tracking)', 'wb-smart-order-tracking-for-woocommerce' ),
			);
		}

		if ( ! isset( $schedules['wbsot_30min'] ) ) {
			$schedules['wbsot_30min'] = array(
				'interval' => 30 * MINUTE_IN_SECONDS,
				'display'  => __( 'Every 30 minutes (WB Order Tracking)', 'wb-smart-order-tracking-for-woocommerce' ),
			);
		}

		return $schedules;
	}

	/**
	 * Ensure cron event is scheduled.
	 *
	 * @return void
	 */
	public function schedule_event(): void {
		if ( ! Settings::is_enabled() ) {
			return;
		}

		$recurrence = Settings::sync_interval();
		$event      = wp_get_scheduled_event( self::CRON_HOOK );

		if ( $event && isset( $event->schedule ) && $event->schedule !== $recurrence ) {
			wp_clear_scheduled_hook( self::CRON_HOOK );
			$event = false;
		}

		if ( ! $event ) {
			wp_schedule_event( time() + MINUTE_IN_SECONDS, $recurrence, self::CRON_HOOK );
		}
	}

	/**
	 * Queue order when tracking is added.
	 *
	 * @param int                              $order_id Order ID.
	 * @param array<int, array<string, mixed>> $items Tracking items.
	 * @return void
	 */
	public function queue_from_tracking_added( int $order_id, array $items ): void {
		if ( $order_id > 0 && ! empty( $items ) ) {
			$this->queue_order( $order_id );
		}
	}

	/**
	 * Queue order by status change hook.
	 *
	 * @param int $order_id Order ID.
	 * @return void
	 */
	public function queue_order_for_sync( int $order_id ): void {
		if ( $order_id > 0 ) {
			$this->queue_order( $order_id );
		}
	}

	/**
	 * Run batched status sync.
	 *
	 * @return void
	 */
	public function run_sync(): void {
		$queue = $this->get_queue();

		if ( empty( $queue ) ) {
			return;
		}

		$batch_size = (int) apply_filters( 'wbsot_status_sync_batch_size', Settings::sync_batch_size() );
		$batch_size = max( 1, $batch_size );
		$batch      = array_slice( $queue, 0, $batch_size );
		$remaining  = array_values( array_diff( $queue, $batch ) );

		foreach ( $batch as $order_id ) {
			$this->sync_order( (int) $order_id );
		}

		$this->set_queue( $remaining );
	}

	/**
	 * Sync tracking status for one order.
	 *
	 * @param int $order_id Order ID.
	 * @return void
	 */
	private function sync_order( int $order_id ): void {
		$order = wc_get_order( $order_id );

		if ( ! $order ) {
			return;
		}

		$items = Admin_Order::get_tracking_items( $order_id );

		if ( empty( $items ) ) {
			return;
		}

		$changed = false;

		foreach ( $items as $index => $item ) {
			$status_payload = null;

			if ( $this->provider_manager ) {
				$status_payload = $this->provider_manager->fetch_status( $item, $order );
			}

			if ( ! is_array( $status_payload ) ) {
				$status_payload = apply_filters( 'wbsot_fetch_tracking_status', null, $item, $order );
			}

			if ( ! is_array( $status_payload ) ) {
				continue;
			}

			$status = sanitize_key( (string) ( $status_payload['status'] ?? '' ) );

			if ( '' === $status ) {
				continue;
			}

			$items[ $index ]['status']       = $status;
			$items[ $index ]['status_label'] = sanitize_text_field( (string) ( $status_payload['status_label'] ?? ucwords( str_replace( '_', ' ', $status ) ) ) );
			$items[ $index ]['last_sync']    = gmdate( 'c' );
			$changed                         = true;
		}

		if ( $changed ) {
			$order->update_meta_data( '_wb_tracking_items', $items );
			$order->save();
		}
	}

	/**
	 * Add order to queue.
	 *
	 * @param int $order_id Order ID.
	 * @return void
	 */
	private function queue_order( int $order_id ): void {
		$queue   = $this->get_queue();
		$queue[] = $order_id;
		$queue   = array_values( array_unique( array_map( 'absint', $queue ) ) );
		$queue   = array_values( array_filter( $queue ) );

		$this->set_queue( $queue );
	}

	/**
	 * Get queue from options.
	 *
	 * @return array<int, int>
	 */
	private function get_queue(): array {
		$queue = get_option( self::QUEUE_OPTION, array() );

		if ( ! is_array( $queue ) ) {
			return array();
		}

		$queue = array_values( array_unique( array_map( 'absint', $queue ) ) );

		return array_values( array_filter( $queue ) );
	}

	/**
	 * Persist queue.
	 *
	 * @param array<int, int> $queue Queue IDs.
	 * @return void
	 */
	private function set_queue( array $queue ): void {
		update_option( self::QUEUE_OPTION, $queue, false );
	}
}
