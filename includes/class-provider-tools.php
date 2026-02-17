<?php
namespace WBCOM\WBSOT;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Provider_Tools {
	/**
	 * Option key for provider test history.
	 */
	private const TEST_RESULTS_OPTION = 'wbsot_provider_test_results';
	/**
	 * Register hooks.
	 */
	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		if ( ! is_admin() ) {
			return;
		}

		add_action( 'admin_menu', array( $this, 'register_menu' ), 100 );
			add_action( 'admin_post_wbsot_test_provider', array( $this, 'handle_provider_test' ) );
			add_action( 'admin_post_wbsot_clear_provider_tests', array( $this, 'handle_clear_history' ) );
			add_action( 'admin_post_wbsot_run_sync_now', array( $this, 'handle_run_sync_now' ) );
			add_action( 'admin_post_wbsot_clear_security_events', array( $this, 'handle_clear_security_events' ) );
	}

	/**
	 * Register tools submenu.
	 *
	 * @return void
	 */
	public function register_menu(): void {
		add_submenu_page(
			'woocommerce',
			esc_html__( 'WB Tracking Tools', 'wb-smart-order-tracking-for-woocommerce' ),
			esc_html__( 'WB Tracking Tools', 'wb-smart-order-tracking-for-woocommerce' ),
			'manage_woocommerce',
			'wbsot-tools',
			array( $this, 'render_page' )
		);
	}

	/**
	 * Render tools page.
	 *
	 * @return void
	 */
	public function render_page(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		$result   = sanitize_key( (string) filter_input( INPUT_GET, 'result', FILTER_SANITIZE_FULL_SPECIAL_CHARS ) );
		$provider = sanitize_key( (string) filter_input( INPUT_GET, 'provider', FILTER_SANITIZE_FULL_SPECIAL_CHARS ) );
		$message  = sanitize_text_field( (string) rawurldecode( (string) filter_input( INPUT_GET, 'message', FILTER_SANITIZE_FULL_SPECIAL_CHARS ) ) );

		echo '<div class="wrap">';
		echo '<h1>' . esc_html__( 'WB Tracking Tools', 'wb-smart-order-tracking-for-woocommerce' ) . '</h1>';
		echo '<p>' . esc_html__( 'Use these actions to test provider credentials and API reachability before enabling live sync.', 'wb-smart-order-tracking-for-woocommerce' ) . '</p>';

			if ( '' !== $result && '' !== $message ) {
				$class = 'success' === $result ? 'notice-success' : 'notice-error';
				echo '<div class="notice ' . esc_attr( $class ) . '"><p>';
				/* translators: 1: provider key, 2: result message. */
				echo esc_html( sprintf( __( '%1$s: %2$s', 'wb-smart-order-tracking-for-woocommerce' ), strtoupper( $provider ), $message ) );
				echo '</p></div>';
			}

			$this->render_sync_now_form();
			$this->render_last_results();
			$this->render_security_events();
			$this->render_provider_test_form( 'aftership', __( 'Test AfterShip Connection', 'wb-smart-order-tracking-for-woocommerce' ) );
			$this->render_provider_test_form( 'shiprocket', __( 'Test Shiprocket Connection', 'wb-smart-order-tracking-for-woocommerce' ) );
			$this->render_clear_history_form();
			$this->render_clear_security_form();

		echo '</div>';
	}

	/**
	 * Render a provider test form.
	 *
	 * @param string $provider Provider ID.
	 * @param string $label Button label.
	 * @return void
	 */
	private function render_provider_test_form( string $provider, string $label ): void {
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="margin: 16px 0;">';
		echo '<input type="hidden" name="action" value="wbsot_test_provider" />';
		echo '<input type="hidden" name="provider" value="' . esc_attr( $provider ) . '" />';
		wp_nonce_field( 'wbsot_test_provider', 'wbsot_tools_nonce' );
		submit_button( $label, 'secondary', 'submit', false );
		echo '</form>';
	}

	/**
	 * Render manual sync run form.
	 *
	 * @return void
	 */
	private function render_sync_now_form(): void {
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="margin: 16px 0;">';
		echo '<input type="hidden" name="action" value="wbsot_run_sync_now" />';
		wp_nonce_field( 'wbsot_run_sync_now', 'wbsot_sync_nonce' );
		submit_button( __( 'Run Sync Now', 'wb-smart-order-tracking-for-woocommerce' ), 'primary', 'submit', false );
		echo '</form>';
	}

	/**
	 * Render clear test history form.
	 *
	 * @return void
	 */
	private function render_clear_history_form(): void {
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="margin: 16px 0;">';
		echo '<input type="hidden" name="action" value="wbsot_clear_provider_tests" />';
		wp_nonce_field( 'wbsot_clear_provider_tests', 'wbsot_clear_nonce' );
		submit_button( __( 'Clear Test History', 'wb-smart-order-tracking-for-woocommerce' ), 'delete', 'submit', false );
		echo '</form>';
	}

	/**
	 * Render clear security events form.
	 *
	 * @return void
	 */
	private function render_clear_security_form(): void {
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="margin: 16px 0;">';
		echo '<input type="hidden" name="action" value="wbsot_clear_security_events" />';
		wp_nonce_field( 'wbsot_clear_security_events', 'wbsot_security_nonce' );
		submit_button( __( 'Clear Security Log', 'wb-smart-order-tracking-for-woocommerce' ), 'delete', 'submit', false );
		echo '</form>';
	}

	/**
	 * Handle provider test submission.
	 *
	 * @return void
	 */
	public function handle_provider_test(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'Permission denied.', 'wb-smart-order-tracking-for-woocommerce' ) );
		}

		if ( empty( $_POST['wbsot_tools_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['wbsot_tools_nonce'] ) ), 'wbsot_test_provider' ) ) {
			wp_die( esc_html__( 'Invalid request.', 'wb-smart-order-tracking-for-woocommerce' ) );
		}

		$provider = isset( $_POST['provider'] ) ? sanitize_key( wp_unslash( $_POST['provider'] ) ) : '';
		$result   = array(
			'success' => false,
			'message' => __( 'Unknown provider.', 'wb-smart-order-tracking-for-woocommerce' ),
		);

		if ( 'aftership' === $provider ) {
			$result = $this->test_aftership_connection();
		} elseif ( 'shiprocket' === $provider ) {
			$result = $this->test_shiprocket_connection();
		}

		$this->store_test_result(
			$provider,
			(bool) $result['success'],
			(string) $result['message'],
			(string) ( $result['code'] ?? '' ),
			(string) ( $result['snippet'] ?? '' )
		);
		$this->redirect_result( $provider, (bool) $result['success'], (string) $result['message'] );
	}

	/**
	 * Handle history clear action.
	 *
	 * @return void
	 */
	public function handle_clear_history(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'Permission denied.', 'wb-smart-order-tracking-for-woocommerce' ) );
		}

		if ( empty( $_POST['wbsot_clear_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['wbsot_clear_nonce'] ) ), 'wbsot_clear_provider_tests' ) ) {
			wp_die( esc_html__( 'Invalid request.', 'wb-smart-order-tracking-for-woocommerce' ) );
		}

		delete_option( self::TEST_RESULTS_OPTION );
		$this->redirect_result( 'tools', true, __( 'Test history cleared.', 'wb-smart-order-tracking-for-woocommerce' ) );
	}

	/**
	 * Handle manual sync action.
	 *
	 * @return void
	 */
	public function handle_run_sync_now(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'Permission denied.', 'wb-smart-order-tracking-for-woocommerce' ) );
		}

		if ( empty( $_POST['wbsot_sync_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['wbsot_sync_nonce'] ) ), 'wbsot_run_sync_now' ) ) {
			wp_die( esc_html__( 'Invalid request.', 'wb-smart-order-tracking-for-woocommerce' ) );
		}

		do_action( 'wbsot_status_sync_event' );
		$this->redirect_result( 'sync', true, __( 'Manual sync executed.', 'wb-smart-order-tracking-for-woocommerce' ) );
	}

	/**
	 * Handle security events clear action.
	 *
	 * @return void
	 */
	public function handle_clear_security_events(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'Permission denied.', 'wb-smart-order-tracking-for-woocommerce' ) );
		}

		if ( empty( $_POST['wbsot_security_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['wbsot_security_nonce'] ) ), 'wbsot_clear_security_events' ) ) {
			wp_die( esc_html__( 'Invalid request.', 'wb-smart-order-tracking-for-woocommerce' ) );
		}

		Security_Events::clear();
		$this->redirect_result( 'security', true, __( 'Security log cleared.', 'wb-smart-order-tracking-for-woocommerce' ) );
	}

	/**
	 * Test AfterShip provider connection.
	 *
	 * @return array<string, mixed>
	 */
	private function test_aftership_connection(): array {
		if ( ! Settings::aftership_enabled() ) {
			return array(
				'success' => false,
				'message' => __( 'AfterShip provider is disabled in settings.', 'wb-smart-order-tracking-for-woocommerce' ),
				'code'    => '',
				'snippet' => '',
			);
		}

		$api_key = Settings::aftership_api_key();

		if ( '' === $api_key ) {
			return array(
				'success' => false,
				'message' => __( 'AfterShip API key is missing.', 'wb-smart-order-tracking-for-woocommerce' ),
				'code'    => '',
				'snippet' => '',
			);
		}

		$url      = untrailingslashit( Settings::aftership_base_url() ) . '/couriers';
		$response = wp_remote_get(
			esc_url_raw( $url ),
			array(
				'timeout' => 12,
				'headers' => array(
					'aftership-api-key' => $api_key,
					'Content-Type'      => 'application/json',
				),
			)
		);

		return $this->normalize_response_result( $response );
	}

	/**
	 * Test Shiprocket provider connection.
	 *
	 * @return array<string, mixed>
	 */
	private function test_shiprocket_connection(): array {
		if ( ! Settings::shiprocket_enabled() ) {
			return array(
				'success' => false,
				'message' => __( 'Shiprocket provider is disabled in settings.', 'wb-smart-order-tracking-for-woocommerce' ),
				'code'    => '',
				'snippet' => '',
			);
		}

		$api_token = Settings::shiprocket_api_token();

		if ( '' === $api_token ) {
			return array(
				'success' => false,
				'message' => __( 'Shiprocket API token is missing.', 'wb-smart-order-tracking-for-woocommerce' ),
				'code'    => '',
				'snippet' => '',
			);
		}

		$url      = untrailingslashit( Settings::shiprocket_base_url() ) . '/channels';
		$response = wp_remote_get(
			esc_url_raw( $url ),
			array(
				'timeout' => 12,
				'headers' => array(
					'Authorization' => 'Bearer ' . $api_token,
					'Content-Type'  => 'application/json',
				),
			)
		);

		return $this->normalize_response_result( $response );
	}

	/**
	 * Normalize remote response to result payload.
	 *
	 * @param array<string, mixed>|\WP_Error $response Remote response.
	 * @return array<string, mixed>
	 */
	private function normalize_response_result( $response ): array {
		if ( is_wp_error( $response ) ) {
			return array(
				'success' => false,
				'message' => $response->get_error_message(),
				'code'    => 'wp_error',
				'snippet' => '',
			);
		}

		$code    = (int) wp_remote_retrieve_response_code( $response );
		$message = (string) wp_remote_retrieve_response_message( $response );
		$body    = (string) wp_remote_retrieve_body( $response );
		$snippet = sanitize_text_field( substr( trim( $body ), 0, 180 ) );

			if ( $code >= 200 && $code < 300 ) {
				return array(
					'success' => true,
					/* translators: %d: HTTP status code. */
					'message' => sprintf( __( 'Connection successful (HTTP %d).', 'wb-smart-order-tracking-for-woocommerce' ), $code ),
					'code'    => (string) $code,
					'snippet' => $snippet,
				);
		}

		if ( '' === $message ) {
			$message = __( 'Unexpected API response.', 'wb-smart-order-tracking-for-woocommerce' );
		}

			return array(
				'success' => false,
				/* translators: 1: HTTP status code, 2: HTTP message. */
				'message' => sprintf( __( 'Connection failed (HTTP %1$d: %2$s).', 'wb-smart-order-tracking-for-woocommerce' ), $code, $message ),
				'code'    => (string) $code,
				'snippet' => $snippet,
			);
	}

	/**
	 * Redirect back to tools page with status.
	 *
	 * @param string $provider Provider ID.
	 * @param bool   $success Test success.
	 * @param string $message Result message.
	 * @return void
	 */
	private function redirect_result( string $provider, bool $success, string $message ): void {
		$url = add_query_arg(
			array(
				'page'     => 'wbsot-tools',
				'provider' => $provider,
				'result'   => $success ? 'success' : 'error',
				'message'  => $message,
			),
			admin_url( 'admin.php' )
		);

		wp_safe_redirect( $url );
		exit;
	}

	/**
	 * Render previous test results.
	 *
	 * @return void
	 */
	private function render_last_results(): void {
		$results = get_option( self::TEST_RESULTS_OPTION, array() );

		if ( ! is_array( $results ) || empty( $results ) ) {
			return;
		}

		echo '<h2>' . esc_html__( 'Last Connection Results', 'wb-smart-order-tracking-for-woocommerce' ) . '</h2>';
		echo '<table class="widefat striped" style="max-width:900px;">';
		echo '<thead><tr>';
		echo '<th>' . esc_html__( 'Provider', 'wb-smart-order-tracking-for-woocommerce' ) . '</th>';
		echo '<th>' . esc_html__( 'Result', 'wb-smart-order-tracking-for-woocommerce' ) . '</th>';
		echo '<th>' . esc_html__( 'Message', 'wb-smart-order-tracking-for-woocommerce' ) . '</th>';
		echo '<th>' . esc_html__( 'HTTP Code', 'wb-smart-order-tracking-for-woocommerce' ) . '</th>';
		echo '<th>' . esc_html__( 'Response Snippet', 'wb-smart-order-tracking-for-woocommerce' ) . '</th>';
		echo '<th>' . esc_html__( 'Tested At (UTC)', 'wb-smart-order-tracking-for-woocommerce' ) . '</th>';
		echo '</tr></thead><tbody>';

		foreach ( $results as $provider => $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}

			$is_success = ! empty( $row['success'] );
			$message    = sanitize_text_field( (string) ( $row['message'] ?? '' ) );
			$code       = sanitize_text_field( (string) ( $row['code'] ?? '' ) );
			$snippet    = sanitize_text_field( (string) ( $row['snippet'] ?? '' ) );
			$tested_at  = sanitize_text_field( (string) ( $row['tested_at'] ?? '' ) );

			echo '<tr>';
			echo '<td>' . esc_html( strtoupper( sanitize_key( (string) $provider ) ) ) . '</td>';
			echo '<td><strong style="color:' . esc_attr( $is_success ? '#0a7f2e' : '#b32d2e' ) . ';">' . esc_html( $is_success ? __( 'Success', 'wb-smart-order-tracking-for-woocommerce' ) : __( 'Failed', 'wb-smart-order-tracking-for-woocommerce' ) ) . '</strong></td>';
			echo '<td>' . esc_html( $message ) . '</td>';
			echo '<td>' . esc_html( $code ) . '</td>';
			echo '<td><code>' . esc_html( $snippet ) . '</code></td>';
			echo '<td>' . esc_html( $tested_at ) . '</td>';
			echo '</tr>';
		}

		echo '</tbody></table>';
	}

	/**
	 * Render recent public tracking security events.
	 *
	 * @return void
	 */
	private function render_security_events(): void {
		$events = Security_Events::all();

		if ( empty( $events ) ) {
			return;
		}

		echo '<h2>' . esc_html__( 'Security Events', 'wb-smart-order-tracking-for-woocommerce' ) . '</h2>';
		echo '<table class="widefat striped" style="max-width:900px;">';
		echo '<thead><tr>';
		echo '<th>' . esc_html__( 'Event', 'wb-smart-order-tracking-for-woocommerce' ) . '</th>';
		echo '<th>' . esc_html__( 'Email', 'wb-smart-order-tracking-for-woocommerce' ) . '</th>';
		echo '<th>' . esc_html__( 'IP', 'wb-smart-order-tracking-for-woocommerce' ) . '</th>';
		echo '<th>' . esc_html__( 'Cooldown (s)', 'wb-smart-order-tracking-for-woocommerce' ) . '</th>';
		echo '<th>' . esc_html__( 'Recorded At (UTC)', 'wb-smart-order-tracking-for-woocommerce' ) . '</th>';
		echo '</tr></thead><tbody>';

		foreach ( array_reverse( $events ) as $event ) {
			if ( ! is_array( $event ) ) {
				continue;
			}

			$name      = sanitize_key( (string) ( $event['event'] ?? '' ) );
			$email     = sanitize_text_field( (string) ( $event['email_hint'] ?? '' ) );
			$ip        = sanitize_text_field( (string) ( $event['ip'] ?? '' ) );
			$cooldown  = absint( (string) ( $event['cooldown'] ?? 0 ) );
			$recorded  = sanitize_text_field( (string) ( $event['recorded_at'] ?? '' ) );

			echo '<tr>';
			echo '<td>' . esc_html( $name ) . '</td>';
			echo '<td>' . esc_html( $email ) . '</td>';
			echo '<td>' . esc_html( $ip ) . '</td>';
			echo '<td>' . esc_html( (string) $cooldown ) . '</td>';
			echo '<td>' . esc_html( $recorded ) . '</td>';
			echo '</tr>';
		}

		echo '</tbody></table>';
	}

	/**
	 * Store provider test result.
	 *
	 * @param string $provider Provider ID.
	 * @param bool   $success Success flag.
	 * @param string $message Result message.
	 * @param string $code HTTP code.
	 * @param string $snippet Response snippet.
	 * @return void
	 */
	private function store_test_result( string $provider, bool $success, string $message, string $code, string $snippet ): void {
		$provider = sanitize_key( $provider );

		if ( '' === $provider ) {
			return;
		}

		$results = get_option( self::TEST_RESULTS_OPTION, array() );

		if ( ! is_array( $results ) ) {
			$results = array();
		}

		$results[ $provider ] = array(
			'success'  => $success ? 1 : 0,
			'message'  => sanitize_text_field( $message ),
			'code'     => sanitize_text_field( $code ),
			'snippet'  => sanitize_text_field( $snippet ),
			'tested_at' => gmdate( 'Y-m-d H:i:s' ),
		);

		update_option( self::TEST_RESULTS_OPTION, $results, false );
	}
}
