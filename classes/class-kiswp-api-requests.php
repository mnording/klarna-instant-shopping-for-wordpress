<?php
/**
 * WooCommerce API Requests class file.
 *
 * @package KISWP/Classes
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}
/**
 * API Requests class.
 */
class KISWP_Api_Requests {
	/**
	 * Class constructor.
	 */
	public function __construct() {
		$this->settings = get_option( 'kiswp_settings' );
	}

	public function generate_button_key() {
		$request_url  = $this->get_api_url_base() . 'instantshopping/v1/buttons';
		$request_args = array(
			'headers'    => $this->get_request_headers(),
			'user-agent' => $this->get_user_agent(),
			'body'       => $this->get_request_body(),
			'timeout'    => 10,
		);

		KISWP()->logger->log( 'Generate button key. Request (' . $request_url . '): ' . stripslashes_deep( json_encode( $request_args ) ) );
		$response = wp_safe_remote_post( $request_url, $request_args );
		KISWP()->logger->log( 'Generate button key. Response: ' . stripslashes_deep( json_encode( $response ) ) );
		return $response;
	}

	/**
	 * Gets Klarna API URL base.
	 */
	public function get_api_url_base() {
		$test_string = 'yes' === KISWP()->settings->get_testmode() ? '.playground' : '';
		return 'https://api' . $test_string . '.klarna.com/';
	}

	/**
	 * Gets Klarna API request headers.
	 *
	 * @return array
	 */
	public function get_request_headers() {
		$request_headers = array(
			'Authorization' => 'Basic ' . base64_encode( KISWP()->settings->get_merchant_id() . ':' . KISWP()->settings->get_secret() ),
			'Content-Type'  => 'application/json',
		);
		return $request_headers;
	}

	/**
	 * Gets Klarna API request body.
	 *
	 * @return false|string
	 */
	public function get_request_body() {
		$request_args = array(
			'merchant_urls' => array(
				'place_order' => get_site_url() . '/wp-json/klarna-instant-shopping/place-order',
			),
		);
		$request_body = wp_json_encode( apply_filters( 'kiswp_api_request_args', $request_args ) );
		return $request_body;
	}

	/**
	 * Gets Klarna API request headers.
	 *
	 * @return string
	 */
	public function get_user_agent() {
		$user_agent = apply_filters( 'http_headers_useragent', 'WordPress/' . get_bloginfo( 'version' ) . '; ' . get_bloginfo( 'url' ) ) . ' - Klarna Instant Shopping. ' . ' - PHP Version: ' . phpversion() . ' - Krokedil';
		return $user_agent;
	}

}
