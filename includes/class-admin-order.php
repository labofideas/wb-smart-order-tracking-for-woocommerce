<?php
namespace WBCOM\WBSOT;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Admin_Order {
	/**
	 * Prevent duplicate processing when multiple save hooks fire in one request.
	 *
	 * @var bool
	 */
	private bool $tracking_saved_for_request = false;

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'add_meta_boxes', array( $this, 'register_meta_box' ) );
		add_action( 'woocommerce_process_shop_order_meta', array( $this, 'save_tracking_meta' ), 10, 2 );
		add_action( 'admin_init', array( $this, 'maybe_save_tracking_from_hpos_request' ), 20 );
	}

	/**
	 * Fallback save path for HPOS edit screen requests where Woo hooks may not fire consistently.
	 *
	 * @return void
	 */
	public function maybe_save_tracking_from_hpos_request(): void {
		$request_method = strtoupper( (string) filter_input( INPUT_SERVER, 'REQUEST_METHOD', FILTER_SANITIZE_FULL_SPECIAL_CHARS ) );

		if ( 'POST' !== $request_method ) {
			return;
		}

		$page   = sanitize_key( (string) filter_input( INPUT_GET, 'page', FILTER_SANITIZE_FULL_SPECIAL_CHARS ) );
		$action = sanitize_key( (string) filter_input( INPUT_GET, 'action', FILTER_SANITIZE_FULL_SPECIAL_CHARS ) );
		$id     = absint( (string) filter_input( INPUT_GET, 'id', FILTER_SANITIZE_NUMBER_INT ) );

		if ( 'wc-orders' !== $page || 'edit' !== $action || $id <= 0 ) {
			return;
		}

		$this->save_tracking_meta( $id );
	}

	/**
	 * Register order tracking metabox.
	 *
	 * @return void
	 */
	public function register_meta_box(): void {
		if ( ! Settings::is_enabled() ) {
			return;
		}

		add_meta_box(
			'wbsot_order_tracking',
			esc_html__( 'WB Order Tracking', 'wb-smart-order-tracking-for-woocommerce' ),
			array( $this, 'render_meta_box' ),
			array( 'shop_order', 'woocommerce_page_wc-orders' ),
			'normal',
			'default'
		);
	}

	/**
	 * Render metabox fields.
	 *
	 * @param \WP_Post|\WC_Order $object Meta box context object.
	 * @return void
	 */
	public function render_meta_box( $object ): void {
		$order_id = $this->resolve_order_id( $object );

		if ( ! $order_id ) {
			echo '<p>' . esc_html__( 'Unable to load order.', 'wb-smart-order-tracking-for-woocommerce' ) . '</p>';
			return;
		}

		$items             = self::get_tracking_items( $order_id );
		$multiple_enabled  = Settings::multiple_tracking_enabled();
		$enabled_carriers  = Settings::enabled_carriers();
		$carrier_options   = Carriers::options();
		$carrier_patterns  = array();
		$all_carriers      = Carriers::all();

		if ( empty( $items ) ) {
			$items = array(
				array(
					'carrier_id'       => '',
					'carrier_name'     => '',
					'tracking_number'  => '',
					'tracking_url'     => '',
					'shipped_date'     => '',
					'notes'            => '',
				),
			);
		}

		foreach ( $enabled_carriers as $carrier_id ) {
			if ( ! empty( $all_carriers[ $carrier_id ]['url'] ) ) {
				$carrier_patterns[ $carrier_id ] = $all_carriers[ $carrier_id ]['url'];
			}
		}

		wp_nonce_field( 'wbsot_save_tracking', 'wbsot_nonce' );

		echo '<p>' . esc_html__( 'Add shipment tracking details for this order.', 'wb-smart-order-tracking-for-woocommerce' ) . '</p>';
		echo '<style id="wbsot-admin-order-styles">';
		echo '#wbsot-tracking-table{min-width:980px;}';
		echo '.wbsot-tracking-table-wrap{max-width:100%;overflow-x:auto;-webkit-overflow-scrolling:touch;}';
		echo '#wbsot-tracking-table td,#wbsot-tracking-table th{white-space:normal;overflow-wrap:anywhere;word-break:break-word;vertical-align:top;}';
		echo '#wbsot-tracking-table input[type=\"text\"],#wbsot-tracking-table input[type=\"url\"],#wbsot-tracking-table input[type=\"date\"],#wbsot-tracking-table select{width:100%;max-width:100%;box-sizing:border-box;}';
		echo '</style>';
		echo '<div class="wbsot-tracking-table-wrap">';
		echo '<table class="widefat striped" id="wbsot-tracking-table">';
		echo '<thead><tr>';
		echo '<th>' . esc_html__( 'Carrier', 'wb-smart-order-tracking-for-woocommerce' ) . '</th>';
		echo '<th>' . esc_html__( 'Carrier Name', 'wb-smart-order-tracking-for-woocommerce' ) . '</th>';
		echo '<th>' . esc_html__( 'Tracking Number', 'wb-smart-order-tracking-for-woocommerce' ) . '</th>';
		echo '<th>' . esc_html__( 'Tracking URL', 'wb-smart-order-tracking-for-woocommerce' ) . '</th>';
		echo '<th>' . esc_html__( 'Shipped Date', 'wb-smart-order-tracking-for-woocommerce' ) . '</th>';
		echo '<th>' . esc_html__( 'Notes', 'wb-smart-order-tracking-for-woocommerce' ) . '</th>';
		echo '<th>' . esc_html__( 'Actions', 'wb-smart-order-tracking-for-woocommerce' ) . '</th>';
		echo '</tr></thead><tbody>';

		foreach ( $items as $index => $item ) {
			$this->render_item_row( (int) $index, $item, $enabled_carriers, $carrier_options );
		}

		echo '</tbody></table>';
		echo '</div>';

		echo '<p style="margin-top: 12px;">';
		echo '<button type="button" class="button" id="wbsot-add-row"' . ( $multiple_enabled ? '' : ' style="display:none;"' ) . '>';
		echo esc_html__( 'Add Tracking Item', 'wb-smart-order-tracking-for-woocommerce' );
		echo '</button>';
		echo '</p>';

		echo '<p><label><input type="checkbox" name="wbsot_notify_customer" value="1" /> ';
		echo esc_html__( 'Add a customer-visible order note when saving tracking details.', 'wb-smart-order-tracking-for-woocommerce' );
		echo '</label></p>';

		echo '<script type="text/html" id="tmpl-wbsot-row">';
		$this->render_item_row( -1, array(), $enabled_carriers, $carrier_options, true );
		echo '</script>';

		wp_print_inline_script_tag(
			'window.WBSOT_CONFIG = ' . wp_json_encode(
				array(
					'allowMultiple' => $multiple_enabled,
					'patterns'      => $carrier_patterns,
				)
			) . ';'
		);
		$this->render_inline_script();
	}

	/**
	 * Render a tracking item row.
	 *
	 * @param int                         $index Row index.
	 * @param array<string, string>       $item Row values.
	 * @param array<int, string>          $enabled_carriers Enabled carrier IDs.
	 * @param array<string, string>       $carrier_options Carrier labels.
	 * @param bool                        $is_template Is template row.
	 * @return void
	 */
	private function render_item_row( int $index, array $item, array $enabled_carriers, array $carrier_options, bool $is_template = false ): void {
		$row_index       = $is_template ? '__index__' : (string) $index;
		$carrier_id      = sanitize_key( $item['carrier_id'] ?? '' );
		$carrier_name    = (string) ( $item['carrier_name'] ?? '' );
		$tracking_number = (string) ( $item['tracking_number'] ?? '' );
		$tracking_url    = (string) ( $item['tracking_url'] ?? '' );
		$shipped_date    = (string) ( $item['shipped_date'] ?? '' );
		$notes           = (string) ( $item['notes'] ?? '' );

		echo '<tr>';
		echo '<td><select class="wbsot-carrier" name="wbsot_items[' . esc_attr( $row_index ) . '][carrier_id]">';
		echo '<option value="">' . esc_html__( 'Select carrier', 'wb-smart-order-tracking-for-woocommerce' ) . '</option>';

		foreach ( $enabled_carriers as $id ) {
			if ( isset( $carrier_options[ $id ] ) ) {
				echo '<option value="' . esc_attr( $id ) . '" ' . selected( $carrier_id, $id, false ) . '>' . esc_html( $carrier_options[ $id ] ) . '</option>';
			}
		}

		echo '</select></td>';
		echo '<td><input type="text" name="wbsot_items[' . esc_attr( $row_index ) . '][carrier_name]" value="' . esc_attr( $carrier_name ) . '" class="regular-text" placeholder="' . esc_attr__( 'Auto or custom', 'wb-smart-order-tracking-for-woocommerce' ) . '" /></td>';
		echo '<td><input type="text" class="regular-text wbsot-tracking-number" name="wbsot_items[' . esc_attr( $row_index ) . '][tracking_number]" value="' . esc_attr( $tracking_number ) . '" /></td>';
		echo '<td><input type="url" class="regular-text wbsot-tracking-url" name="wbsot_items[' . esc_attr( $row_index ) . '][tracking_url]" value="' . esc_attr( $tracking_url ) . '" /></td>';
		echo '<td><input type="date" name="wbsot_items[' . esc_attr( $row_index ) . '][shipped_date]" value="' . esc_attr( $shipped_date ) . '" /></td>';
		echo '<td><input type="text" name="wbsot_items[' . esc_attr( $row_index ) . '][notes]" value="' . esc_attr( $notes ) . '" class="regular-text" /></td>';
		echo '<td><button type="button" class="button-link-delete wbsot-remove-row">' . esc_html__( 'Remove', 'wb-smart-order-tracking-for-woocommerce' ) . '</button></td>';
		echo '</tr>';
	}

	/**
	 * Save metabox data.
	 *
	 * @param int      $order_id Order ID.
	 * @param mixed    $context Order save context (post/order object based on storage mode).
	 * @return void
	 */
	public function save_tracking_meta( int $order_id, $context = null ): void {
		if ( $this->tracking_saved_for_request ) {
			return;
		}

		if ( ! Settings::is_enabled() ) {
			return;
		}

		if ( empty( $_POST['wbsot_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['wbsot_nonce'] ) ), 'wbsot_save_tracking' ) ) {
			return;
		}

		if ( ! current_user_can( 'manage_woocommerce' ) && ! current_user_can( 'edit_shop_orders' ) ) {
			return;
		}

		$raw_items = isset( $_POST['wbsot_items'] ) && is_array( $_POST['wbsot_items'] )
			? map_deep( wp_unslash( $_POST['wbsot_items'] ), 'sanitize_text_field' )
			: array();
		$items     = self::sanitize_tracking_items( $raw_items );

		if ( ! Settings::multiple_tracking_enabled() && ! empty( $items ) ) {
			$items = array( $items[0] );
		}

		$order = wc_get_order( $order_id );

		if ( ! $order ) {
			return;
		}

		$this->tracking_saved_for_request = true;
		$current_items                    = self::get_tracking_items( $order_id );

		if ( empty( $items ) ) {
			if ( empty( $current_items ) ) {
				return;
			}

			$order->delete_meta_data( '_wb_tracking_items' );
			$order->save_meta_data();
			return;
		}

		if ( $items === $current_items ) {
			return;
		}

		$order->update_meta_data( '_wb_tracking_items', $items );
		$order->save_meta_data();
		do_action( 'wbsot_tracking_added', $order_id, $items );

		$first           = $items[0];
		$summary_message = sprintf(
			/* translators: 1: carrier name 2: tracking number */
			esc_html__( 'Tracking details updated: %1$s %2$s', 'wb-smart-order-tracking-for-woocommerce' ),
			$first['carrier_name'],
			$first['tracking_number']
		);

		$order->add_order_note( $summary_message, 0, true );

		if ( ! empty( $_POST['wbsot_notify_customer'] ) ) {
			$order->add_order_note( $summary_message, 1, true );
		}
	}

	/**
	 * Get normalized tracking items from order meta.
	 *
	 * @param int $order_id Order ID.
	 * @return array<int, array<string, string>>
	 */
	public static function get_tracking_items( int $order_id ): array {
		$order = wc_get_order( $order_id );

		if ( ! $order ) {
			return array();
		}

		$items = $order->get_meta( '_wb_tracking_items', true );

		if ( ! is_array( $items ) ) {
			return array();
		}

		return self::sanitize_tracking_items( $items );
	}

	/**
	 * Sanitize tracking items.
	 *
	 * @param mixed $items Raw items.
	 * @return array<int, array<string, string>>
	 */
	public static function sanitize_tracking_items( $items ): array {
		if ( ! is_array( $items ) ) {
			return array();
		}

		$normalized = array();

		foreach ( $items as $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}

			$carrier_id      = sanitize_key( $item['carrier_id'] ?? '' );
			$carrier_name    = sanitize_text_field( $item['carrier_name'] ?? '' );
			$tracking_number = sanitize_text_field( $item['tracking_number'] ?? '' );
			$tracking_url    = self::normalize_tracking_url( (string) ( $item['tracking_url'] ?? '' ) );
			$shipped_date    = sanitize_text_field( $item['shipped_date'] ?? '' );
			$notes           = sanitize_text_field( $item['notes'] ?? '' );
			$status          = sanitize_key( $item['status'] ?? '' );
			$status_label    = sanitize_text_field( $item['status_label'] ?? '' );
			$last_sync       = sanitize_text_field( $item['last_sync'] ?? '' );

			if ( '' === $tracking_number ) {
				continue;
			}

			if ( '' === $carrier_id ) {
				$carrier_id = Carrier_Detector::detect( $tracking_number );
			}

			if ( '' === $carrier_name ) {
				$carrier_name = Carriers::name_from_id( $carrier_id );
			}

			if ( '' === $tracking_url && '' !== $carrier_id ) {
				$tracking_url = Carriers::build_url( $carrier_id, $tracking_number );
			}

			$normalized[] = array(
				'carrier_id'       => $carrier_id,
				'carrier_name'     => $carrier_name,
				'tracking_number'  => $tracking_number,
				'tracking_url'     => $tracking_url,
				'shipped_date'     => $shipped_date,
				'notes'            => $notes,
				'status'           => $status,
				'status_label'     => $status_label,
				'last_sync'        => $last_sync,
			);
		}

		return array_values( $normalized );
	}

	/**
	 * Normalize tracking URL to avoid malformed concatenated values.
	 *
	 * @param string $raw_url Raw tracking URL.
	 * @return string
	 */
	private static function normalize_tracking_url( string $raw_url ): string {
		$raw_url = trim( $raw_url );

		if ( '' === $raw_url ) {
			return '';
		}

		$http_pos  = strrpos( $raw_url, 'http://' );
		$https_pos = strrpos( $raw_url, 'https://' );
		$start     = max(
			false === $http_pos ? -1 : (int) $http_pos,
			false === $https_pos ? -1 : (int) $https_pos
		);

		if ( $start > 0 ) {
			$raw_url = substr( $raw_url, $start );
		}

		return esc_url_raw( $raw_url );
	}

	/**
	 * Resolve order ID from metabox context.
	 *
	 * @param \WP_Post|\WC_Order $object Meta box object.
	 * @return int
	 */
	private function resolve_order_id( $object ): int {
		if ( $object instanceof \WC_Order ) {
			return $object->get_id();
		}

		if ( $object instanceof \WP_Post ) {
			return (int) $object->ID;
		}

		$id = absint( (string) filter_input( INPUT_GET, 'id', FILTER_SANITIZE_NUMBER_INT ) );

		if ( $id > 0 ) {
			return $id;
		}

		return 0;
	}

	/**
	 * Print inline script for repeatable fields.
	 *
	 * @return void
	 */
	private function render_inline_script(): void {
		wp_print_inline_script_tag(
			"(function(){
				const table = document.getElementById('wbsot-tracking-table');
				if (!table) return;
				const tbody = table.querySelector('tbody');
				const addBtn = document.getElementById('wbsot-add-row');
				const template = document.getElementById('tmpl-wbsot-row');
				const cfg = window.WBSOT_CONFIG || { allowMultiple: true, patterns: {} };

				const refreshRemoveVisibility = () => {
					const rows = tbody.querySelectorAll('tr');
					rows.forEach((row, idx) => {
						const btn = row.querySelector('.wbsot-remove-row');
						if (!btn) return;
						btn.style.visibility = (!cfg.allowMultiple && idx === 0) ? 'hidden' : 'visible';
					});
				};

				const nextRowIndex = () => {
					let maxIndex = -1;
					tbody.querySelectorAll('input[name^=\"wbsot_items[\"]').forEach((input) => {
						const match = input.name.match(/^wbsot_items\\[(\\d+)\\]/);
						if (!match) return;
						const index = parseInt(match[1], 10);
						if (Number.isNaN(index)) return;
						maxIndex = Math.max(maxIndex, index);
					});
					return maxIndex + 1;
				};

				const autoBuildUrl = (row) => {
					const carrier = row.querySelector('.wbsot-carrier');
					const number = row.querySelector('.wbsot-tracking-number');
					const urlInput = row.querySelector('.wbsot-tracking-url');
					if (!carrier || !number || !urlInput) return;

					const pattern = cfg.patterns[carrier.value];
					if (!pattern || !number.value.trim()) return;
					if (urlInput.value.trim() && urlInput.dataset.wbsotAutofilled !== '1') return;

					urlInput.value = pattern.replace('{tracking_number}', encodeURIComponent(number.value.trim()));
					urlInput.dataset.wbsotAutofilled = '1';
				};

				if (addBtn && template) {
					addBtn.addEventListener('click', () => {
						if (!cfg.allowMultiple) return;
						const index = nextRowIndex();
						const html = template.innerHTML.split('__index__').join(String(index));
						tbody.insertAdjacentHTML('beforeend', html);
						refreshRemoveVisibility();
					});
				}

				tbody.addEventListener('click', (event) => {
					const target = event.target;
					if (!(target instanceof HTMLElement)) return;
					if (!target.classList.contains('wbsot-remove-row')) return;
					event.preventDefault();

					const rows = tbody.querySelectorAll('tr');
					if (!cfg.allowMultiple && rows.length <= 1) return;
					const row = target.closest('tr');
					if (!row) return;
					row.remove();
					refreshRemoveVisibility();
				});

				tbody.addEventListener('change', (event) => {
					const target = event.target;
					if (!(target instanceof HTMLElement)) return;
					const row = target.closest('tr');
					if (!row) return;
					if (!target.classList.contains('wbsot-carrier') && !target.classList.contains('wbsot-tracking-number')) return;
					autoBuildUrl(row);
				});

				tbody.addEventListener('input', (event) => {
					const target = event.target;
					if (!(target instanceof HTMLElement) || !target.classList.contains('wbsot-tracking-url')) return;
					target.dataset.wbsotAutofilled = '0';
				});

				refreshRemoveVisibility();
			})();"
		);
	}
}
