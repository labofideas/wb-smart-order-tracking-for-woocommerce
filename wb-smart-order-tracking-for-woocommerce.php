<?php
/**
 * Plugin Name: WB Smart Order Tracking for WooCommerce
 * Plugin URI:  https://wbcomdesigns.com/
 * Description: Add, manage, import, and display WooCommerce shipment tracking details for customers.
 * Version:     1.0.0
 * Author:      Wbcom Designs
 * Author URI:  https://wbcomdesigns.com/
 * Text Domain: wb-smart-order-tracking-for-woocommerce
 * License:     GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Requires at least: 6.5
 * Requires PHP: 8.0
 * WC requires at least: 8.0
 * WC tested up to: 10.5.1
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'WBSOT_FILE' ) ) {
	define( 'WBSOT_FILE', __FILE__ );
}

if ( ! defined( 'WBSOT_PATH' ) ) {
	define( 'WBSOT_PATH', plugin_dir_path( __FILE__ ) );
}

if ( ! defined( 'WBSOT_URL' ) ) {
	define( 'WBSOT_URL', plugin_dir_url( __FILE__ ) );
}

if ( ! defined( 'WBSOT_VERSION' ) ) {
	define( 'WBSOT_VERSION', '1.0.0' );
}

require_once WBSOT_PATH . 'includes/class-plugin.php';

add_action(
	'before_woocommerce_init',
	static function () {
		if ( class_exists( '\\Automattic\\WooCommerce\\Utilities\\FeaturesUtil' ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
		}
	}
);

add_action(
	'plugins_loaded',
	static function () {
		\WBCOM\WBSOT\Plugin::init();
	}
);

add_filter(
	'plugin_action_links_' . plugin_basename( __FILE__ ),
	static function ( array $links ): array {
		$settings_url = admin_url( 'admin.php?page=wc-settings&tab=wb_order_tracking' );
		$tools_url    = admin_url( 'admin.php?page=wbsot-tools' );

		$custom_links = array(
			'<a href="' . esc_url( $settings_url ) . '">' . esc_html__( 'Settings', 'wb-smart-order-tracking-for-woocommerce' ) . '</a>',
			'<a href="' . esc_url( $tools_url ) . '">' . esc_html__( 'Tools', 'wb-smart-order-tracking-for-woocommerce' ) . '</a>',
		);

		return array_merge( $custom_links, $links );
	}
);

register_activation_hook(
	__FILE__,
	static function () {
		if ( ! class_exists( 'WooCommerce' ) ) {
			deactivate_plugins( plugin_basename( __FILE__ ) );
			wp_die( esc_html__( 'WB Smart Order Tracking for WooCommerce requires WooCommerce to be active.', 'wb-smart-order-tracking-for-woocommerce' ) );
		}

		update_option( 'wbsot_version', WBSOT_VERSION );
	}
);

register_deactivation_hook(
	__FILE__,
	static function () {
		wp_clear_scheduled_hook( 'wbsot_status_sync_event' );
	}
);
