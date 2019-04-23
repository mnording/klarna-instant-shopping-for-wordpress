<?php
/**
 * WooCommerce Settings class file.
 *
 * @package KISWP/Classes
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}
/**
 * API Requests class.
 */
class KISWP_Settings {
	/**
	 * Class constructor.
	 */
	public function __construct() {
		if ( class_exists( 'Klarna_Checkout_For_WooCommerce' ) ) {
			$this->settings      = get_option( 'woocommerce_kco_settings' );
			$store_base_location = wc_get_base_location();
			if ( 'US' === $store_base_location['country'] ) {
				$this->test_merchant_id     = $this->settings['test_merchant_id_us'];
				$this->test_merchant_secret = $this->settings['test_shared_secret_us'];
				$this->prod_merchant_id     = $this->settings['merchant_id_us'];
				$this->prod_merchant_secret = $this->settings['shared_secret_us'];
			} else {
				$this->test_merchant_id     = $this->settings['test_merchant_id_eu'];
				$this->test_merchant_secret = $this->settings['test_shared_secret_eu'];
				$this->prod_merchant_id     = $this->settings['merchant_id_eu'];
				$this->prod_merchant_secret = $this->settings['shared_secret_eu'];
			}
		} elseif ( class_exists( 'WC_Klarna_Payments' ) ) {
			$this->settings             = get_option( 'woocommerce_klarna_payments_settings' );
			$store_base_location        = wc_get_base_location();
			$store_base_country         = strtolower( $store_base_location['country'] );
			$this->test_merchant_id     = $this->settings[ 'test_merchant_id_' . $store_base_country ];
			$this->test_merchant_secret = $this->settings[ 'test_shared_secret_' . $store_base_country ];
			$this->prod_merchant_id     = $this->settings[ 'merchant_id_' . $store_base_country ];
			$this->prod_merchant_secret = $this->settings[ 'shared_secret_' . $store_base_country ];
		} else {
			$this->settings             = get_option( 'kiswp_settings' );
			$this->test_merchant_id     = $this->settings['test_id'];
			$this->test_merchant_secret = $this->settings['test_secret'];
			$this->prod_merchant_id     = $this->settings['live_id'];
			$this->prod_merchant_secret = $this->settings['live_secret'];
		}
	}

	/**
	 * Gets Klarna merchant ID.
	 *
	 * @return string
	 */
	public function get_merchant_id() {
		$merchant_id = ( 'yes' === $this->settings['testmode'] ) ? $this->test_merchant_id : $this->prod_merchant_id;

		return $merchant_id;
	}

	/**
	 * Gets Klarna secret.
	 *
	 * @return string
	 */
	public function get_secret() {
		$secret = ( 'yes' === $this->settings['testmode'] ) ? $this->test_merchant_secret : $this->prod_merchant_secret;

		return $secret;
	}

	/**
	 * Gets Klarna testmode.
	 *
	 * @return string
	 */
	public function get_testmode() {
		return $this->settings['testmode'];
	}

}
