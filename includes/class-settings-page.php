<?php
namespace WBCOM\WBSOT;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Settings_Page extends \WC_Settings_Page {
	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->id    = 'wb_order_tracking';
		$this->label = __( 'WB Order Tracking', 'wb-smart-order-tracking-for-woocommerce' );

		parent::__construct();
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_settings_assets' ) );
	}

	/**
	 * Section tabs.
	 *
	 * @return array<string, string>
	 */
	protected function get_own_sections() {
		return array(
			''           => __( 'General', 'wb-smart-order-tracking-for-woocommerce' ),
			'customer'   => __( 'Customer', 'wb-smart-order-tracking-for-woocommerce' ),
			'providers'  => __( 'Providers', 'wb-smart-order-tracking-for-woocommerce' ),
			'automation' => __( 'Automation', 'wb-smart-order-tracking-for-woocommerce' ),
		);
	}

	/**
	 * Default section settings.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	protected function get_settings_for_default_section(): array {
		return $this->general_settings();
	}

	/**
	 * Customer section settings.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	protected function get_settings_for_customer_section(): array {
		return $this->customer_settings();
	}

	/**
	 * Providers section settings.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	protected function get_settings_for_providers_section(): array {
		return $this->provider_settings();
	}

	/**
	 * Automation section settings.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	protected function get_settings_for_automation_section(): array {
		return $this->automation_settings();
	}

	/**
	 * General settings.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private function general_settings(): array {
		return array(
			array(
				'title' => __( 'Core Settings', 'wb-smart-order-tracking-for-woocommerce' ),
				'desc'  => __( 'Configure the core order tracking behavior for store admins.', 'wb-smart-order-tracking-for-woocommerce' ),
				'type'  => 'title',
				'id'    => 'wbsot_general_settings',
			),
			array(
				'title'    => __( 'Enable plugin', 'wb-smart-order-tracking-for-woocommerce' ),
				'desc'     => __( 'Enable all WB Order Tracking features.', 'wb-smart-order-tracking-for-woocommerce' ),
				'desc_tip' => true,
				'id'       => 'wbsot_enabled',
				'default'  => 'yes',
				'type'     => 'checkbox',
			),
				array(
					'title'    => __( 'Enable CSV import', 'wb-smart-order-tracking-for-woocommerce' ),
					'desc'     => __( 'Allow bulk tracking updates from CSV.', 'wb-smart-order-tracking-for-woocommerce' ),
					'desc_tip' => true,
					'id'       => 'wbsot_enable_csv_import',
					'default'  => 'yes',
					'type'     => 'checkbox',
				),
				array(
					'title'    => __( 'CSV strict mode', 'wb-smart-order-tracking-for-woocommerce' ),
					'desc'     => __( 'Reject CSV rows that have unknown carriers or disallowed order statuses.', 'wb-smart-order-tracking-for-woocommerce' ),
					'desc_tip' => true,
					'id'       => 'wbsot_csv_strict_mode',
					'default'  => 'no',
					'type'     => 'checkbox',
				),
				array(
					'title'             => __( 'Allowed order statuses for CSV', 'wb-smart-order-tracking-for-woocommerce' ),
					'desc'              => __( 'Used only in CSV strict mode. Rows for other statuses will be rejected.', 'wb-smart-order-tracking-for-woocommerce' ),
					'desc_tip'          => true,
					'id'                => 'wbsot_csv_allowed_statuses',
					'class'             => 'wc-enhanced-select',
					'css'               => 'min-width: 320px;',
					'default'           => array( 'processing', 'completed' ),
					'type'              => 'multiselect',
					'options'           => $this->order_status_options(),
					'sanitize_callback' => array( $this, 'sanitize_allowed_statuses' ),
				),
			array(
				'title'    => __( 'Allow multiple tracking numbers', 'wb-smart-order-tracking-for-woocommerce' ),
				'desc'     => __( 'Support split shipments with multiple tracking entries per order.', 'wb-smart-order-tracking-for-woocommerce' ),
				'desc_tip' => true,
				'id'       => 'wbsot_allow_multiple',
				'default'  => 'yes',
				'type'     => 'checkbox',
			),
			array(
				'title'             => __( 'Enabled carriers', 'wb-smart-order-tracking-for-woocommerce' ),
				'desc'              => __( 'Choose the carriers available in the order tracking editor.', 'wb-smart-order-tracking-for-woocommerce' ),
				'desc_tip'          => true,
				'id'                => 'wbsot_enabled_carriers',
				'class'             => 'wc-enhanced-select',
				'css'               => 'min-width: 420px;',
				'default'           => array_keys( Carriers::all() ),
				'type'              => 'multiselect',
				'options'           => Carriers::options(),
				'sanitize_callback' => array( $this, 'sanitize_enabled_carriers' ),
			),
			array(
				'type' => 'sectionend',
				'id'   => 'wbsot_general_settings',
			),
		);
	}

	/**
	 * Customer display settings.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private function customer_settings(): array {
		return array(
			array(
				'title' => __( 'Customer Experience', 'wb-smart-order-tracking-for-woocommerce' ),
				'desc'  => __( 'Control where customers can see tracking details.', 'wb-smart-order-tracking-for-woocommerce' ),
				'type'  => 'title',
				'id'    => 'wbsot_customer_settings',
			),
			array(
				'title'    => __( 'Show in My Account', 'wb-smart-order-tracking-for-woocommerce' ),
				'desc'     => __( 'Display tracking details on order view in customer account.', 'wb-smart-order-tracking-for-woocommerce' ),
				'desc_tip' => true,
				'id'       => 'wbsot_enable_my_account',
				'default'  => 'yes',
				'type'     => 'checkbox',
			),
			array(
				'title'    => __( 'Show in order emails', 'wb-smart-order-tracking-for-woocommerce' ),
				'desc'     => __( 'Include tracking details in processing and completed emails.', 'wb-smart-order-tracking-for-woocommerce' ),
				'desc_tip' => true,
				'id'       => 'wbsot_enable_emails',
				'default'  => 'yes',
				'type'     => 'checkbox',
			),
			array(
				'title'    => __( 'Enable public tracking shortcode', 'wb-smart-order-tracking-for-woocommerce' ),
				'desc'     => __( 'Allow `[wb_order_tracking]` public lookup with Order ID + billing email.', 'wb-smart-order-tracking-for-woocommerce' ),
				'desc_tip' => true,
				'id'       => 'wbsot_enable_public_tracking',
				'default'  => 'yes',
				'type'     => 'checkbox',
			),
			array(
				'title'             => __( 'Public lookup rate limit', 'wb-smart-order-tracking-for-woocommerce' ),
				'desc'              => __( 'Maximum tracking attempts per email and IP in 15 minutes (1-200).', 'wb-smart-order-tracking-for-woocommerce' ),
				'desc_tip'          => true,
				'id'                => 'wbsot_public_tracking_rate_limit',
				'default'           => '20',
				'type'              => 'number',
				'custom_attributes' => array(
					'min'  => '1',
					'max'  => '200',
					'step' => '1',
				),
				'sanitize_callback' => 'absint',
			),
			array(
				'type' => 'sectionend',
				'id'   => 'wbsot_customer_settings',
			),
		);
	}

	/**
	 * Provider settings.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private function provider_settings(): array {
		return array(
			array(
				'title' => __( 'AfterShip', 'wb-smart-order-tracking-for-woocommerce' ),
				'desc'  => __( 'Configure AfterShip integration for live shipment status sync.', 'wb-smart-order-tracking-for-woocommerce' ),
				'type'  => 'title',
				'id'    => 'wbsot_provider_aftership_settings',
			),
			array(
				'title'    => __( 'Enable AfterShip provider', 'wb-smart-order-tracking-for-woocommerce' ),
				'id'       => 'wbsot_aftership_enabled',
				'default'  => 'no',
				'type'     => 'checkbox',
			),
			array(
				'title'             => __( 'AfterShip API key', 'wb-smart-order-tracking-for-woocommerce' ),
				'id'                => 'wbsot_aftership_api_key',
				'type'              => 'password',
				'default'           => '',
				'sanitize_callback' => 'sanitize_text_field',
			),
			array(
				'title'             => __( 'AfterShip API base URL', 'wb-smart-order-tracking-for-woocommerce' ),
				'id'                => 'wbsot_aftership_base_url',
				'type'              => 'text',
				'default'           => 'https://api.aftership.com/v4',
				'sanitize_callback' => 'esc_url_raw',
			),
			array(
				'title'    => __( 'Enable live API requests', 'wb-smart-order-tracking-for-woocommerce' ),
				'desc'     => __( 'When disabled, no external API calls are sent to AfterShip.', 'wb-smart-order-tracking-for-woocommerce' ),
				'desc_tip' => true,
				'id'       => 'wbsot_aftership_live_requests',
				'default'  => 'no',
				'type'     => 'checkbox',
			),
			array(
				'type' => 'sectionend',
				'id'   => 'wbsot_provider_aftership_settings',
			),
			array(
				'title' => __( 'Shiprocket', 'wb-smart-order-tracking-for-woocommerce' ),
				'desc'  => __( 'Configure Shiprocket integration for live shipment status sync.', 'wb-smart-order-tracking-for-woocommerce' ),
				'type'  => 'title',
				'id'    => 'wbsot_provider_shiprocket_settings',
			),
			array(
				'title'    => __( 'Enable Shiprocket provider', 'wb-smart-order-tracking-for-woocommerce' ),
				'id'       => 'wbsot_shiprocket_enabled',
				'default'  => 'no',
				'type'     => 'checkbox',
			),
			array(
				'title'             => __( 'Shiprocket API token', 'wb-smart-order-tracking-for-woocommerce' ),
				'id'                => 'wbsot_shiprocket_api_token',
				'type'              => 'password',
				'default'           => '',
				'sanitize_callback' => 'sanitize_text_field',
			),
			array(
				'title'             => __( 'Shiprocket API base URL', 'wb-smart-order-tracking-for-woocommerce' ),
				'id'                => 'wbsot_shiprocket_base_url',
				'type'              => 'text',
				'default'           => 'https://apiv2.shiprocket.in/v1/external',
				'sanitize_callback' => 'esc_url_raw',
			),
			array(
				'title'    => __( 'Enable live API requests', 'wb-smart-order-tracking-for-woocommerce' ),
				'desc'     => __( 'When disabled, no external API calls are sent to Shiprocket.', 'wb-smart-order-tracking-for-woocommerce' ),
				'desc_tip' => true,
				'id'       => 'wbsot_shiprocket_live_requests',
				'default'  => 'no',
				'type'     => 'checkbox',
			),
			array(
				'type' => 'sectionend',
				'id'   => 'wbsot_provider_shiprocket_settings',
			),
		);
	}

	/**
	 * Automation settings.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private function automation_settings(): array {
		return array(
			array(
				'title' => __( 'Sync Engine', 'wb-smart-order-tracking-for-woocommerce' ),
				'desc'  => __( 'Fine-tune async status sync performance.', 'wb-smart-order-tracking-for-woocommerce' ),
				'type'  => 'title',
				'id'    => 'wbsot_automation_settings',
			),
			array(
				'title'    => __( 'Sync interval', 'wb-smart-order-tracking-for-woocommerce' ),
				'desc'     => __( 'How often queued orders are processed for status updates.', 'wb-smart-order-tracking-for-woocommerce' ),
				'desc_tip' => true,
				'id'       => 'wbsot_sync_interval',
				'default'  => 'wbsot_15min',
				'type'     => 'select',
				'options'  => array(
					'wbsot_5min'  => __( 'Every 5 minutes', 'wb-smart-order-tracking-for-woocommerce' ),
					'wbsot_15min' => __( 'Every 15 minutes', 'wb-smart-order-tracking-for-woocommerce' ),
					'wbsot_30min' => __( 'Every 30 minutes', 'wb-smart-order-tracking-for-woocommerce' ),
					'hourly'      => __( 'Hourly', 'wb-smart-order-tracking-for-woocommerce' ),
				),
			),
			array(
				'title'             => __( 'Batch size', 'wb-smart-order-tracking-for-woocommerce' ),
				'desc'              => __( 'Number of orders processed in one sync run (1-100).', 'wb-smart-order-tracking-for-woocommerce' ),
				'desc_tip'          => true,
				'id'                => 'wbsot_sync_batch_size',
				'default'           => '10',
				'type'              => 'number',
				'custom_attributes' => array(
					'min'  => '1',
					'max'  => '100',
					'step' => '1',
				),
				'sanitize_callback' => 'absint',
			),
			array(
				'type' => 'sectionend',
				'id'   => 'wbsot_automation_settings',
			),
		);
	}

	/**
	 * Sanitize enabled carriers.
	 *
	 * @param mixed $value Value from settings API.
	 * @return array<int, string>
	 */
	public function sanitize_enabled_carriers( $value ): array {
		$known = array_keys( Carriers::all() );

		if ( ! is_array( $value ) ) {
			return $known;
		}

		$sanitized = array_values(
			array_filter(
				array_map( 'sanitize_key', $value ),
				static fn( string $carrier ): bool => in_array( $carrier, $known, true )
			)
		);

		return empty( $sanitized ) ? $known : $sanitized;
	}

	/**
	 * Get WooCommerce order statuses for settings multiselect.
	 *
	 * @return array<string, string>
	 */
	private function order_status_options(): array {
		$statuses = wc_get_order_statuses();
		$options  = array();

		foreach ( $statuses as $key => $label ) {
			$options[ sanitize_key( str_replace( 'wc-', '', (string) $key ) ) ] = (string) $label;
		}

		return $options;
	}

	/**
	 * Sanitize allowed statuses setting.
	 *
	 * @param mixed $value Raw value.
	 * @return array<int, string>
	 */
	public function sanitize_allowed_statuses( $value ): array {
		$valid = array_keys( $this->order_status_options() );

		if ( ! is_array( $value ) ) {
			return array( 'processing', 'completed' );
		}

		$sanitized = array_values(
			array_filter(
				array_unique( array_map( 'sanitize_key', $value ) ),
				static fn( string $status ): bool => in_array( $status, $valid, true )
			)
		);

		return empty( $sanitized ) ? array( 'processing', 'completed' ) : $sanitized;
	}

	/**
	 * Enqueue premium admin styles/scripts for WB settings tab.
	 *
	 * @return void
	 */
	public function enqueue_settings_assets(): void {
		if ( ! $this->is_wbsot_settings_screen() ) {
			return;
		}

		wp_register_style( 'wbsot-admin-settings', false, array(), WBSOT_VERSION );
		wp_enqueue_style( 'wbsot-admin-settings' );
		wp_add_inline_style(
			'wbsot-admin-settings',
			':root{--wbsot-bg:#f6f8fb;--wbsot-card:#fff;--wbsot-border:#dce3ee;--wbsot-text:#19212c;--wbsot-muted:#5f6f86;--wbsot-primary:#1e6fff;--wbsot-shadow:0 12px 32px rgba(17,40,75,.08);}
			body.woocommerce_page_wc-settings{background:var(--wbsot-bg);}
			.woocommerce .wc-settings-sub-nav li a[href*=\"tab=wb_order_tracking\"]{font-weight:600;}
			body.woocommerce_page_wc-settings.wbsot-settings-screen #mainform{max-width:1100px;}
			body.woocommerce_page_wc-settings.wbsot-settings-screen h2{display:none;}
			#wbsot-settings-hero{background:linear-gradient(135deg,#0f1727 0%,#1a2f4d 50%,#244a84 100%);color:#fff;border-radius:18px;padding:26px 28px;margin:16px 0 22px;box-shadow:var(--wbsot-shadow);}
			#wbsot-settings-hero .wbsot-badge{display:inline-block;padding:6px 10px;border-radius:999px;background:rgba(255,255,255,.16);font-size:12px;font-weight:700;letter-spacing:.04em;text-transform:uppercase;margin-bottom:10px;}
			#wbsot-settings-hero h3{margin:0 0 6px;font-size:24px;line-height:1.25;color:#fff;}
			#wbsot-settings-hero p{margin:0;color:rgba(255,255,255,.88);font-size:14px;}
			#wbsot-health{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px;margin:0 0 16px;}
			#wbsot-health .wbsot-health-card{background:#fff;border:1px solid var(--wbsot-border);border-radius:14px;padding:14px 14px 12px;box-shadow:var(--wbsot-shadow);}
			#wbsot-health .wbsot-health-label{display:block;color:#667890;font-size:12px;font-weight:600;text-transform:uppercase;letter-spacing:.03em;margin-bottom:6px;}
			#wbsot-health .wbsot-health-value{display:block;color:#172133;font-size:16px;font-weight:700;line-height:1.25;}
			#wbsot-health .wbsot-health-value[data-tone=\"good\"]{color:#0f7a3d;}
			#wbsot-health .wbsot-health-value[data-tone=\"warn\"]{color:#9a5d00;}
			#wbsot-health .wbsot-health-value[data-tone=\"neutral\"]{color:#172133;}
			body.woocommerce_page_wc-settings.wbsot-settings-screen .form-table{background:var(--wbsot-card);border:1px solid var(--wbsot-border);border-radius:16px;overflow:hidden;box-shadow:var(--wbsot-shadow);}
			body.woocommerce_page_wc-settings.wbsot-settings-screen .form-table td, body.woocommerce_page_wc-settings.wbsot-settings-screen .form-table th{padding:16px 18px;}
			body.woocommerce_page_wc-settings.wbsot-settings-screen .form-table tr{border-top:1px solid #eef2f8;}
			body.woocommerce_page_wc-settings.wbsot-settings-screen .form-table tr:first-child{border-top:none;}
			body.woocommerce_page_wc-settings.wbsot-settings-screen .form-table th{width:280px;color:var(--wbsot-text);font-weight:600;}
			body.woocommerce_page_wc-settings.wbsot-settings-screen .form-table td{color:var(--wbsot-muted);}
			body.woocommerce_page_wc-settings.wbsot-settings-screen .form-table .description{color:var(--wbsot-muted);font-size:12px;}
			body.woocommerce_page_wc-settings.wbsot-settings-screen .wbsot-help-dot{display:inline-flex;align-items:center;justify-content:center;width:17px;height:17px;border-radius:999px;border:none;background:#d9e7ff;color:#124fb6;font-size:11px;font-weight:700;line-height:1;cursor:help;padding:0;margin-left:6px;vertical-align:middle;}
			body.woocommerce_page_wc-settings.wbsot-settings-screen .form-table input[type=\"text\"],body.woocommerce_page_wc-settings.wbsot-settings-screen .form-table input[type=\"password\"],body.woocommerce_page_wc-settings.wbsot-settings-screen .form-table input[type=\"number\"],body.woocommerce_page_wc-settings.wbsot-settings-screen .form-table select{min-height:38px;border-radius:10px;border:1px solid #cfd9e6;padding:0 12px;}
			body.woocommerce_page_wc-settings.wbsot-settings-screen .form-table input[type=\"text\"]:focus,body.woocommerce_page_wc-settings.wbsot-settings-screen .form-table input[type=\"password\"]:focus,body.woocommerce_page_wc-settings.wbsot-settings-screen .form-table input[type=\"number\"]:focus,body.woocommerce_page_wc-settings.wbsot-settings-screen .form-table select:focus{border-color:var(--wbsot-primary);box-shadow:0 0 0 3px rgba(30,111,255,.15);}
			body.woocommerce_page_wc-settings.wbsot-settings-screen .form-table input[type=\"checkbox\"]{appearance:none;-webkit-appearance:none;width:42px;height:24px;border-radius:999px;background:#c6d2e4;position:relative;border:none;box-shadow:inset 0 0 0 1px rgba(0,0,0,.04);margin:0 10px 0 0;vertical-align:middle;cursor:pointer;transition:background .2s ease;}
			body.woocommerce_page_wc-settings.wbsot-settings-screen .form-table input[type=\"checkbox\"]:before{content:\"\";position:absolute;top:3px;left:3px;width:18px;height:18px;border-radius:50%;background:#fff;box-shadow:0 1px 3px rgba(0,0,0,.25);transition:left .2s ease;}
			body.woocommerce_page_wc-settings.wbsot-settings-screen .form-table input[type=\"checkbox\"]:checked{background:var(--wbsot-primary);}
			body.woocommerce_page_wc-settings.wbsot-settings-screen .form-table input[type=\"checkbox\"]:checked:before{left:21px;}
			body.woocommerce_page_wc-settings.wbsot-settings-screen p.submit{position:sticky;bottom:0;background:rgba(246,248,251,.96);backdrop-filter:blur(6px);padding:14px 0 4px;margin:0;}
			body.woocommerce_page_wc-settings.wbsot-settings-screen p.submit .button-primary{min-height:40px;padding:0 16px;border-radius:10px;font-weight:600;}
			@media (max-width: 960px){
				#wbsot-health{grid-template-columns:1fr 1fr;}
				body.woocommerce_page_wc-settings.wbsot-settings-screen .form-table th,body.woocommerce_page_wc-settings.wbsot-settings-screen .form-table td{display:block;width:100%;padding:12px 14px;}
				body.woocommerce_page_wc-settings.wbsot-settings-screen .form-table th{padding-bottom:4px;}
			}'
		);

		wp_register_script( 'wbsot-admin-settings', '', array( 'jquery' ), WBSOT_VERSION, true );
		wp_enqueue_script( 'wbsot-admin-settings' );
		wp_add_inline_script(
			'wbsot-admin-settings',
			'window.wbsotSettingsMeta = ' . wp_json_encode( $this->settings_screen_meta() ) . ';',
			'before'
		);
		wp_add_inline_script(
			'wbsot-admin-settings',
			"(function($){
				'use strict';
				$(function(){
					$('body').addClass('wbsot-settings-screen');
					var hero = '<div id=\"wbsot-settings-hero\"><span class=\"wbsot-badge\">Premium Controls</span><h3>WB Smart Order Tracking Settings</h3><p>Fine-tune customer tracking experience, automation, and carrier sync with production-ready controls.</p></div>';
					var tips = {
						wbsot_public_tracking_rate_limit: 'Recommended 10-30 for most stores. Lower values are stricter against abuse.',
						wbsot_csv_strict_mode: 'Enable for safer imports when staff use mixed CSV sources.',
						wbsot_csv_allowed_statuses: 'Only used when strict mode is ON.',
						wbsot_aftership_live_requests: 'Keep OFF in staging/testing to avoid accidental API usage.',
						wbsot_shiprocket_live_requests: 'Keep OFF in staging/testing to avoid accidental API usage.',
						wbsot_sync_batch_size: 'Higher batch sizes process faster but increase server load.'
					};
					var meta = window.wbsotSettingsMeta || null;
					function esc(value){ return $('<div/>').text(String(value || '')).html(); }
					function buildHealthCard(label, value, tone){
						return '<div class=\"wbsot-health-card\"><span class=\"wbsot-health-label\">' + esc(label) + '</span><span class=\"wbsot-health-value\" data-tone=\"' + esc(tone) + '\">' + esc(value) + '</span></div>';
					}
					if (!$('#wbsot-settings-hero').length) {
						$('#mainform').prepend(hero);
					}
					if (meta && !$('#wbsot-health').length) {
						var health = '<div id=\"wbsot-health\">';
						health += buildHealthCard('Plugin Status', meta.plugin_status, meta.plugin_status_tone);
						health += buildHealthCard('Public Tracking', meta.public_tracking, meta.public_tracking_tone);
						health += buildHealthCard('Sync Queue', meta.sync_queue, meta.sync_queue_tone);
						health += buildHealthCard('Next Sync', meta.next_sync, meta.next_sync_tone);
						health += '</div>';
						$('#wbsot-settings-hero').after(health);
					}
					$.each(tips, function(fieldId, helpText){
						var $field = $('#' + fieldId);
						if (!$field.length) {
							return;
						}
						var $th = $field.closest('tr').find('th');
						if (!$th.length || $th.find('.wbsot-help-dot').length) {
							return;
						}
						$th.append('<button type=\"button\" class=\"wbsot-help-dot\" title=\"' + esc(helpText) + '\" aria-label=\"' + esc(helpText) + '\">?</button>');
					});
				});
			})(jQuery);"
		);
	}

	/**
	 * Build settings tab health summary for the premium header.
	 *
	 * @return array<string, string>
	 */
	private function settings_screen_meta(): array {
		$event = wp_get_scheduled_event( 'wbsot_status_sync_event' );
		$queue = get_option( 'wbsot_sync_queue', array() );
		$count = 0;

		if ( is_array( $queue ) ) {
			$count = count( array_filter( array_map( 'absint', $queue ) ) );
		}

		$plugin_enabled  = Settings::is_enabled();
		$public_enabled  = Settings::public_tracking_enabled();
		$next_sync_label = __( 'Not scheduled', 'wb-smart-order-tracking-for-woocommerce' );
		$next_sync_tone  = 'warn';

		if ( $event && isset( $event->timestamp ) && is_numeric( $event->timestamp ) ) {
			$timestamp       = (int) $event->timestamp;
			$next_sync_label = $timestamp <= time()
				? __( 'Due now', 'wb-smart-order-tracking-for-woocommerce' )
				: sprintf(
					/* translators: %s: relative time string such as "in 10 minutes". */
					__( 'In %s', 'wb-smart-order-tracking-for-woocommerce' ),
					human_time_diff( time(), $timestamp )
				);
			$next_sync_tone = $timestamp <= time() ? 'warn' : 'good';
		}

		return array(
			'plugin_status'      => $plugin_enabled ? __( 'Enabled', 'wb-smart-order-tracking-for-woocommerce' ) : __( 'Disabled', 'wb-smart-order-tracking-for-woocommerce' ),
			'plugin_status_tone' => $plugin_enabled ? 'good' : 'warn',
			'public_tracking'    => $public_enabled ? __( 'Active', 'wb-smart-order-tracking-for-woocommerce' ) : __( 'Inactive', 'wb-smart-order-tracking-for-woocommerce' ),
			'public_tracking_tone' => $public_enabled ? 'good' : 'warn',
			'sync_queue'         => $count > 0 ? sprintf(
				/* translators: %d: number of queued orders. */
				_n( '%d order pending', '%d orders pending', $count, 'wb-smart-order-tracking-for-woocommerce' ),
				$count
			) : __( 'Queue empty', 'wb-smart-order-tracking-for-woocommerce' ),
			'sync_queue_tone'    => $count > 0 ? 'neutral' : 'good',
			'next_sync'          => $next_sync_label,
			'next_sync_tone'     => $next_sync_tone,
		);
	}

	/**
	 * Check if current admin page is WB settings tab.
	 *
	 * @return bool
	 */
	private function is_wbsot_settings_screen(): bool {
		$page = sanitize_key( (string) filter_input( INPUT_GET, 'page', FILTER_SANITIZE_FULL_SPECIAL_CHARS ) );
		$tab  = sanitize_key( (string) filter_input( INPUT_GET, 'tab', FILTER_SANITIZE_FULL_SPECIAL_CHARS ) );

		return 'wc-settings' === $page && 'wb_order_tracking' === $tab;
	}
}
