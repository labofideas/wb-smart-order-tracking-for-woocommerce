<?php
namespace WBCOM\WBSOT;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once WBSOT_PATH . 'includes/class-settings.php';
require_once WBSOT_PATH . 'includes/class-carriers.php';
require_once WBSOT_PATH . 'includes/class-admin-order.php';
require_once WBSOT_PATH . 'includes/class-front-display.php';
require_once WBSOT_PATH . 'includes/class-shortcode.php';
require_once WBSOT_PATH . 'includes/class-csv-import.php';
require_once WBSOT_PATH . 'includes/class-carrier-detector.php';
require_once WBSOT_PATH . 'includes/class-status-provider-interface.php';
require_once WBSOT_PATH . 'includes/class-status-provider-aftership.php';
require_once WBSOT_PATH . 'includes/class-status-provider-shiprocket.php';
require_once WBSOT_PATH . 'includes/class-status-provider-manager.php';
require_once WBSOT_PATH . 'includes/class-status-sync.php';
require_once WBSOT_PATH . 'includes/class-status-provider-sample.php';
require_once WBSOT_PATH . 'includes/class-provider-tools.php';
require_once WBSOT_PATH . 'includes/class-security-events.php';

final class Plugin {
	/**
	 * Settings page instance.
	 *
	 * @var Settings_Page|null
	 */
	private static ?Settings_Page $settings_page = null;

	/**
	 * Boot plugin.
	 *
	 * @return void
	 */
	public static function init(): void {
		if ( ! class_exists( 'WooCommerce' ) ) {
			add_action( 'admin_notices', array( __CLASS__, 'woocommerce_missing_notice' ) );
			return;
		}

		if ( is_admin() ) {
			add_filter( 'woocommerce_get_settings_pages', array( __CLASS__, 'register_settings_page' ) );
		}

		( new Admin_Order() )->register();
		( new Front_Display() )->register();
		( new Shortcode() )->register();
		( new CSV_Import() )->register();
		( new Status_Sync() )->register();
		( new Status_Provider_Sample() )->register();
		( new Provider_Tools() )->register();
		add_action( 'wp_head', array( __CLASS__, 'print_front_layout_guard' ), 99 );
	}

	/**
	 * Display WooCommerce missing notice.
	 *
	 * @return void
	 */
	public static function woocommerce_missing_notice(): void {
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}

		echo '<div class="notice notice-error"><p>';
		echo esc_html__( 'WB Smart Order Tracking for WooCommerce requires WooCommerce to be active.', 'wb-smart-order-tracking-for-woocommerce' );
		echo '</p></div>';
	}

	/**
	 * Register WooCommerce settings page.
	 *
	 * @param array<int, mixed> $pages Existing settings pages.
	 * @return array<int, mixed>
	 */
	public static function register_settings_page( array $pages ): array {
		if ( ! class_exists( 'WC_Settings_Page' ) ) {
			return $pages;
		}

		require_once WBSOT_PATH . 'includes/class-settings-page.php';

		if ( ! self::$settings_page ) {
			self::$settings_page = new Settings_Page();
		}

		$pages[] = self::$settings_page;

		return $pages;
	}

	/**
	 * Print minimal frontend guard styles to prevent horizontal whitespace.
	 *
	 * @return void
	 */
	public static function print_front_layout_guard(): void {
		echo '<style id="wbsot-layout-guard">';
		echo 'html,body{overflow-x:hidden;}';
		echo '.wc-block-mini-cart__drawer{max-width:100vw;}';
		echo '</style>';
	}
}
