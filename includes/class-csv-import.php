<?php
namespace WBCOM\WBSOT;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class CSV_Import {
	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		if ( ! is_admin() ) {
			return;
		}

		add_action( 'admin_menu', array( $this, 'register_menu' ), 99 );
		add_action( 'admin_post_wbsot_import_csv', array( $this, 'handle_import' ) );
	}

	/**
	 * Register import submenu.
	 *
	 * @return void
	 */
	public function register_menu(): void {
		add_submenu_page(
			'woocommerce',
			esc_html__( 'WB Order Tracking Import', 'wb-smart-order-tracking-for-woocommerce' ),
			esc_html__( 'WB Order Tracking', 'wb-smart-order-tracking-for-woocommerce' ),
			'manage_woocommerce',
			'wbsot-import',
			array( $this, 'render_page' )
		);
	}

	/**
	 * Render CSV import page.
	 *
	 * @return void
	 */
	public function render_page(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		$imported = absint( (string) filter_input( INPUT_GET, 'imported', FILTER_SANITIZE_NUMBER_INT ) );
		$failed   = absint( (string) filter_input( INPUT_GET, 'failed', FILTER_SANITIZE_NUMBER_INT ) );
		$dry_run  = absint( (string) filter_input( INPUT_GET, 'dry_run', FILTER_SANITIZE_NUMBER_INT ) );
		$error_token = sanitize_key( (string) filter_input( INPUT_GET, 'error_token', FILTER_SANITIZE_FULL_SPECIAL_CHARS ) );
		$row_errors  = $this->consume_row_errors( $error_token );

		echo '<div class="wrap">';
		echo '<h1>' . esc_html__( 'WB Order Tracking CSV Import', 'wb-smart-order-tracking-for-woocommerce' ) . '</h1>';

		if ( $imported || $failed || $dry_run ) {
			echo '<div class="notice notice-info"><p>';
			if ( 1 === $dry_run ) {
				/* translators: 1: valid rows count, 2: invalid rows count. */
				echo esc_html( sprintf( __( 'Dry run complete. Valid rows: %1$d, Invalid rows: %2$d', 'wb-smart-order-tracking-for-woocommerce' ), $imported, $failed ) );
			} else {
				/* translators: 1: imported rows count, 2: failed rows count. */
				echo esc_html( sprintf( __( 'Imported: %1$d, Failed: %2$d', 'wb-smart-order-tracking-for-woocommerce' ), $imported, $failed ) );
			}
			echo '</p></div>';
		}

		if ( ! Settings::csv_import_enabled() ) {
			echo '<div class="notice notice-warning"><p>' . esc_html__( 'CSV import is currently disabled in plugin settings.', 'wb-smart-order-tracking-for-woocommerce' ) . '</p></div>';
		}

		$strict_mode = Settings::csv_strict_mode_enabled();
		$statuses    = array_map( 'sanitize_text_field', Settings::csv_allowed_statuses() );

		echo '<div class="notice notice-info"><p><strong>' . esc_html__( 'Import Rules', 'wb-smart-order-tracking-for-woocommerce' ) . '</strong></p><ul style="list-style:disc;margin-left:20px;">';
		echo '<li>' . esc_html__( 'CSV must include: Order ID, Tracking number, Carrier, Tracking URL (optional), Shipped date (optional).', 'wb-smart-order-tracking-for-woocommerce' ) . '</li>';
		echo '<li>' . esc_html__( 'Use Dry run to validate CSV before saving data.', 'wb-smart-order-tracking-for-woocommerce' ) . '</li>';
		if ( $strict_mode ) {
			/* translators: %s: allowed order statuses list. */
			echo '<li>' . esc_html( sprintf( __( 'Strict mode is enabled. Allowed order statuses: %s.', 'wb-smart-order-tracking-for-woocommerce' ), implode( ', ', $statuses ) ) ) . '</li>';
			echo '<li>' . esc_html__( 'Rows with unknown carriers are rejected in strict mode.', 'wb-smart-order-tracking-for-woocommerce' ) . '</li>';
		} else {
			echo '<li>' . esc_html__( 'Strict mode is disabled. Unknown carriers can be imported as custom carriers.', 'wb-smart-order-tracking-for-woocommerce' ) . '</li>';
		}
		echo '</ul></div>';

		if ( ! empty( $row_errors ) ) {
			echo '<h2>' . esc_html__( 'Row Errors', 'wb-smart-order-tracking-for-woocommerce' ) . '</h2>';
			echo '<table class="widefat striped" style="max-width:900px;">';
			echo '<thead><tr><th>' . esc_html__( 'Row', 'wb-smart-order-tracking-for-woocommerce' ) . '</th><th>' . esc_html__( 'Reason', 'wb-smart-order-tracking-for-woocommerce' ) . '</th></tr></thead><tbody>';
			foreach ( $row_errors as $row_error ) {
				echo '<tr><td>' . esc_html( (string) $row_error['row'] ) . '</td><td>' . esc_html( $row_error['reason'] ) . '</td></tr>';
			}
			echo '</tbody></table>';
		}

		echo '<p>' . esc_html__( 'Expected columns: Order ID, Tracking number, Carrier, Tracking URL (optional), Shipped date (optional).', 'wb-smart-order-tracking-for-woocommerce' ) . '</p>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" enctype="multipart/form-data">';
		echo '<input type="hidden" name="action" value="wbsot_import_csv" />';
		wp_nonce_field( 'wbsot_import_csv', 'wbsot_import_nonce' );
		echo '<input type="file" name="wbsot_csv" accept=".csv,text/csv" required /> ';
		echo '<label style="margin-left:10px;"><input type="checkbox" name="wbsot_dry_run" value="1" /> ' . esc_html__( 'Dry run (validate only)', 'wb-smart-order-tracking-for-woocommerce' ) . '</label> ';
		submit_button( __( 'Import CSV', 'wb-smart-order-tracking-for-woocommerce' ), 'primary', 'submit', false );
		echo '</form>';
		echo '</div>';
	}

	/**
	 * Handle CSV import request.
	 *
	 * @return void
	 */
	public function handle_import(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'Permission denied.', 'wb-smart-order-tracking-for-woocommerce' ) );
		}

		if ( ! Settings::csv_import_enabled() ) {
			$this->redirect_with_counts( 0, 0 );
		}

		if ( empty( $_POST['wbsot_import_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['wbsot_import_nonce'] ) ), 'wbsot_import_csv' ) ) {
			wp_die( esc_html__( 'Invalid import request.', 'wb-smart-order-tracking-for-woocommerce' ) );
		}

		$file = $this->uploaded_file_data();
		$dry_run = isset( $_POST['wbsot_dry_run'] ) && '1' === sanitize_text_field( wp_unslash( $_POST['wbsot_dry_run'] ) );

		if ( ! $this->is_valid_csv_upload( $file ) ) {
			$this->redirect_with_counts( 0, 1 );
		}

		$rows = $this->parse_csv_rows( $file['tmp_name'] );

		if ( empty( $rows ) ) {
			$this->redirect_with_counts( 0, 1 );
		}

		if ( count( $rows ) - 1 > $this->max_csv_rows() ) {
			$this->redirect_with_counts( 0, 1 );
		}

		$imported = 0;
		$failed   = 0;
		$errors   = array();
		$header   = array_shift( $rows );

		if ( ! is_array( $header ) ) {
			$this->redirect_with_counts( 0, 1 );
		}

		$map = $this->build_header_map( $header );

		$row_number = 1;
		foreach ( $rows as $row ) {
			++$row_number;
			if ( ! is_array( $row ) || empty( $row ) ) {
				continue;
			}

			$error  = '';
			$result = $this->import_row( $row, $map, $dry_run, $error );

			if ( $result ) {
				++$imported;
			} else {
				++$failed;
				if ( '' === $error ) {
					$error = __( 'Unknown validation error.', 'wb-smart-order-tracking-for-woocommerce' );
				}
				$errors[] = array(
					'row'    => $row_number,
					'reason' => $error,
				);
			}
		}

		$error_token = $this->store_row_errors( $errors );
		$this->redirect_with_counts( $imported, $failed, $dry_run, $error_token );
	}

	/**
	 * Parse uploaded CSV to array rows using WordPress filesystem APIs.
	 *
	 * @param string $path Uploaded file path.
	 * @return array<int, array<int, string>>
	 */
	private function parse_csv_rows( string $path ): array {
		require_once ABSPATH . 'wp-admin/includes/file.php';
		\WP_Filesystem();

		global $wp_filesystem;

		if ( ! $wp_filesystem ) {
			return array();
		}

		$contents = $wp_filesystem->get_contents( $path );

		if ( ! is_string( $contents ) || '' === $contents ) {
			return array();
		}

		$rows  = array();
		$lines = preg_split( '/\r\n|\n|\r/', $contents );

		if ( ! is_array( $lines ) ) {
			return array();
		}

		foreach ( $lines as $line ) {
			if ( '' === trim( (string) $line ) ) {
				continue;
			}

			$row = str_getcsv( (string) $line );

			if ( is_array( $row ) ) {
				$rows[] = $row;
			}
		}

		return $rows;
	}

	/**
	 * Extract uploaded file data.
	 *
	 * @return array{tmp_name:string,name:string,size:int,error:int}
	 */
	private function uploaded_file_data(): array {
		return array(
			// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce already verified in handle_import().
			'tmp_name' => isset( $_FILES['wbsot_csv']['tmp_name'] ) ? sanitize_text_field( (string) wp_unslash( $_FILES['wbsot_csv']['tmp_name'] ) ) : '',
			// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce already verified in handle_import().
			'name'     => isset( $_FILES['wbsot_csv']['name'] ) ? sanitize_file_name( (string) wp_unslash( $_FILES['wbsot_csv']['name'] ) ) : '',
			// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce already verified in handle_import().
			'size'     => isset( $_FILES['wbsot_csv']['size'] ) ? absint( wp_unslash( $_FILES['wbsot_csv']['size'] ) ) : 0,
			// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce already verified in handle_import().
			'error'    => isset( $_FILES['wbsot_csv']['error'] ) ? absint( wp_unslash( $_FILES['wbsot_csv']['error'] ) ) : UPLOAD_ERR_NO_FILE,
		);
	}

	/**
	 * Validate uploaded CSV metadata and type.
	 *
	 * @param array{tmp_name:string,name:string,size:int,error:int} $file Uploaded file data.
	 * @return bool
	 */
	private function is_valid_csv_upload( array $file ): bool {
		if ( UPLOAD_ERR_OK !== $file['error'] ) {
			return false;
		}

		if ( '' === $file['tmp_name'] || '' === $file['name'] || ! is_uploaded_file( $file['tmp_name'] ) ) {
			return false;
		}

		if ( $file['size'] < 1 || $file['size'] > $this->max_csv_file_size() ) {
			return false;
		}

		require_once ABSPATH . 'wp-admin/includes/file.php';
		$filetype = wp_check_filetype_and_ext( $file['tmp_name'], $file['name'] );
		$ext      = strtolower( (string) ( $filetype['ext'] ?? pathinfo( $file['name'], PATHINFO_EXTENSION ) ) );
		$mime     = strtolower( (string) ( $filetype['type'] ?? '' ) );

		if ( 'csv' !== $ext ) {
			return false;
		}

		$allowed_mimes = array(
			'text/csv',
			'text/plain',
			'application/csv',
			'application/vnd.ms-excel',
		);

		if ( '' !== $mime && ! in_array( $mime, $allowed_mimes, true ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Get maximum allowed CSV upload size in bytes.
	 *
	 * @return int
	 */
	private function max_csv_file_size(): int {
		$max_size = (int) apply_filters( 'wbsot_csv_max_file_size', 2 * MB_IN_BYTES );

		return max( 1024, $max_size );
	}

	/**
	 * Get maximum allowed CSV data rows (excluding header).
	 *
	 * @return int
	 */
	private function max_csv_rows(): int {
		$max_rows = (int) apply_filters( 'wbsot_csv_max_rows', 2000 );

		return max( 1, $max_rows );
	}

	/**
	 * Build normalized header map.
	 *
	 * @param array<int, string> $header Header row.
	 * @return array<string, int>
	 */
	private function build_header_map( array $header ): array {
		$map = array();

		foreach ( $header as $index => $col ) {
			$key        = strtolower( trim( (string) $col ) );
			$map[ $key ] = $index;
		}

		return $map;
	}

	/**
	 * Import single CSV row.
	 *
	 * @param array<int, string> $row CSV row.
	 * @param array<string, int> $map Header map.
	 * @return bool
	 */
	private function import_row( array $row, array $map, bool $dry_run = false, string &$error = '' ): bool {
		$order_id = absint( $this->cell( $row, $map, array( 'order id', 'order_id' ) ) );

		if ( ! $order_id ) {
			$error = __( 'Missing or invalid Order ID.', 'wb-smart-order-tracking-for-woocommerce' );
			return false;
		}

		$order = wc_get_order( $order_id );

		if ( ! $order ) {
			$error = __( 'Order not found.', 'wb-smart-order-tracking-for-woocommerce' );
			return false;
		}

		if ( Settings::csv_strict_mode_enabled() && ! $this->is_allowed_order_status( (string) $order->get_status() ) ) {
			$error = __( 'Order status is not allowed by strict mode.', 'wb-smart-order-tracking-for-woocommerce' );
			return false;
		}

		$tracking_number = sanitize_text_field( $this->cell( $row, $map, array( 'tracking number', 'tracking_number' ) ) );

		if ( '' === $tracking_number ) {
			$error = __( 'Missing tracking number.', 'wb-smart-order-tracking-for-woocommerce' );
			return false;
		}

		$carrier_raw = sanitize_text_field( $this->cell( $row, $map, array( 'carrier', 'carrier name', 'carrier_name' ) ) );
		$carrier_id  = $this->resolve_carrier_id( $carrier_raw );

		if ( '' === $carrier_id ) {
			$carrier_id = Carrier_Detector::detect( $tracking_number );
		}

		if ( Settings::csv_strict_mode_enabled() && '' === $carrier_id ) {
			$error = __( 'Unknown carrier is not allowed by strict mode.', 'wb-smart-order-tracking-for-woocommerce' );
			return false;
		}

		$carrier     = '' !== $carrier_id ? Carriers::name_from_id( $carrier_id ) : $carrier_raw;
		$url         = esc_url_raw( $this->cell( $row, $map, array( 'tracking url', 'tracking_url' ) ) );
		$ship_date   = sanitize_text_field( $this->cell( $row, $map, array( 'shipped date', 'shipped_date', 'shipping date', 'shipping_date' ) ) );

		if ( '' === $url && '' !== $carrier_id ) {
			$url = Carriers::build_url( $carrier_id, $tracking_number );
		}

		$new_item = array(
			'carrier_id'       => $carrier_id,
			'carrier_name'     => $carrier,
			'tracking_number'  => $tracking_number,
			'tracking_url'     => $url,
			'shipped_date'     => $ship_date,
			'notes'            => '',
		);

		$existing = Admin_Order::get_tracking_items( $order_id );

		if ( Settings::multiple_tracking_enabled() ) {
			$existing[] = $new_item;
			$items      = Admin_Order::sanitize_tracking_items( $existing );
		} else {
			$items = Admin_Order::sanitize_tracking_items( array( $new_item ) );
		}

		if ( empty( $items ) ) {
			$error = __( 'Tracking item failed validation.', 'wb-smart-order-tracking-for-woocommerce' );
			return false;
		}

		if ( $dry_run ) {
			return true;
		}

		$order->update_meta_data( '_wb_tracking_items', $items );
		$order->save();
		do_action( 'wbsot_tracking_added', $order_id, $items );
		$order->add_order_note(
			sprintf(
				/* translators: 1: carrier name 2: tracking number */
				esc_html__( 'Tracking imported: %1$s %2$s', 'wb-smart-order-tracking-for-woocommerce' ),
				$new_item['carrier_name'],
				$new_item['tracking_number']
			),
			0,
			true
		);

		return true;
	}

	/**
	 * Check strict-mode allowed order status.
	 *
	 * @param string $status Order status slug.
	 * @return bool
	 */
	private function is_allowed_order_status( string $status ): bool {
		$status = sanitize_key( str_replace( 'wc-', '', $status ) );
		$allowed = Settings::csv_allowed_statuses();

		return in_array( $status, $allowed, true );
	}

	/**
	 * Resolve CSV carrier string to known carrier ID.
	 *
	 * @param string $carrier Carrier string.
	 * @return string
	 */
	private function resolve_carrier_id( string $carrier ): string {
		$carrier = strtolower( trim( $carrier ) );

		if ( '' === $carrier ) {
			return '';
		}

		$all = Carriers::all();

		if ( isset( $all[ $carrier ] ) ) {
			return $carrier;
		}

		foreach ( $all as $id => $data ) {
			if ( strtolower( $data['name'] ) === $carrier ) {
				return $id;
			}
		}

		return '';
	}

	/**
	 * Get cell by known headers.
	 *
	 * @param array<int, string> $row CSV row.
	 * @param array<string, int> $map Header map.
	 * @param array<int, string> $keys Header aliases.
	 * @return string
	 */
	private function cell( array $row, array $map, array $keys ): string {
		foreach ( $keys as $key ) {
			if ( isset( $map[ $key ] ) ) {
				$index = $map[ $key ];
				return isset( $row[ $index ] ) ? (string) $row[ $index ] : '';
			}
		}

		return '';
	}

	/**
	 * Redirect back to import page with results.
	 *
	 * @param int $imported Imported rows.
	 * @param int $failed Failed rows.
	 * @return void
	 */
	private function redirect_with_counts( int $imported, int $failed, bool $dry_run = false, string $error_token = '' ): void {
		$args = array(
			'page'     => 'wbsot-import',
			'imported' => $imported,
			'failed'   => $failed,
			'dry_run'  => $dry_run ? 1 : 0,
		);

		if ( '' !== $error_token ) {
			$args['error_token'] = $error_token;
		}

		$url = add_query_arg( $args, admin_url( 'admin.php' ) );

		wp_safe_redirect( $url );
		exit;
	}

	/**
	 * Store row-level import errors in a short-lived transient.
	 *
	 * @param array<int, array{row:int,reason:string}> $errors Error rows.
	 * @return string
	 */
	private function store_row_errors( array $errors ): string {
		if ( empty( $errors ) ) {
			return '';
		}

		$user_id = get_current_user_id();
		if ( ! $user_id ) {
			return '';
		}

		$token = sanitize_key( 'err' . wp_generate_password( 12, false, false ) );
		$key   = 'wbsot_import_errors_' . $user_id . '_' . $token;

		set_transient( $key, array_slice( $errors, 0, 25 ), 15 * MINUTE_IN_SECONDS );

		return $token;
	}

	/**
	 * Consume row errors transient for current user.
	 *
	 * @param string $token Error token from query arg.
	 * @return array<int, array{row:int,reason:string}>
	 */
	private function consume_row_errors( string $token ): array {
		if ( '' === $token ) {
			return array();
		}

		$user_id = get_current_user_id();
		if ( ! $user_id ) {
			return array();
		}

		$key    = 'wbsot_import_errors_' . $user_id . '_' . sanitize_key( $token );
		$errors = get_transient( $key );
		delete_transient( $key );

		if ( ! is_array( $errors ) ) {
			return array();
		}

		$normalized = array();
		foreach ( $errors as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$normalized[] = array(
				'row'    => absint( (string) ( $row['row'] ?? 0 ) ),
				'reason' => sanitize_text_field( (string) ( $row['reason'] ?? '' ) ),
			);
		}

		return $normalized;
	}
}
