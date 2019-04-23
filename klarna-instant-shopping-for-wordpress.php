<?php
/*
 * Plugin Name: Klarna Instant Shopping for WordPress
 * Plugin URI:
 * Description: Klarna Instant Shopping for WordPress.
 * Version: 0.1
 * Author: Krokedil
 * Author URI:
 * Text Domain: klarna-instant-shopping-for-wordpress
 * Domain Path: /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

define( 'KISWP_VERSION', '0.1' );
define( 'KISWP_MAIN_FILE', __FILE__ );
define( 'KISWP_PLUGIN_PATH', untrailingslashit( plugin_dir_path( __FILE__ ) ) );
define( 'KISWP_PLUGIN_URL', untrailingslashit( plugin_dir_url( __FILE__ ) ) );

if ( ! class_exists( 'KISWP' ) ) {
	/**
	 * Main class for the plugin.
	 */
	class KISWP {
		/**
		 * The reference the *Singleton* instance of this class.
		 *
		 * @var $instance
		 */
		protected static $instance;
		/**
		 * Class constructor.
		 */
		public function __construct() {
			// Initiate the plugin.
			add_action( 'plugins_loaded', array( $this, 'init' ), 1000 );
		}
		/**
		 * Returns the *Singleton* instance of this class.
		 *
		 * @return self::$instance The *Singleton* instance.
		 */
		public static function get_instance() {
			if ( null === self::$instance ) {
				self::$instance = new self();
			}
			return self::$instance;
		}
		/**
		 * Private clone method to prevent cloning of the instance of the
		 * *Singleton* instance.
		 *
		 * @return void
		 */
		private function __clone() {
			wc_doing_it_wrong( __FUNCTION__, __( 'Nope' ), '1.0' );
		}
		/**
		 * Private unserialize method to prevent unserializing of the *Singleton*
		 * instance.
		 *
		 * @return void
		 */
		private function __wakeup() {
			wc_doing_it_wrong( __FUNCTION__, __( 'Nope' ), '1.0' );
		}
		/**
		 * Initiates the plugin.
		 *
		 * @return void
		 */
		public function init() {
			load_plugin_textdomain( 'klarna-instant-shopping-for-wordpress', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );
			add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), array( $this, 'plugin_action_links' ) );
			$this->include_files();
			// Load scripts.
			add_action( 'wp_enqueue_scripts', array( $this, 'load_scripts' ) );
			// Set variabled for shorthand access to classes.
			$this->options_page       = new KISWP_Options_Page();
			$this->settings           = new KISWP_Settings();
			$this->woocommerce_button = new KISWP_WooCommerce_Button();
			$this->api_requests       = new KISWP_Api_Requests();
			$this->api_callbacks      = new KISWP_Api_Callbacks();
			$this->logger             = new KISWP_Logging();
		}
		/**
		 * Includes the files for the plugin
		 *
		 * @return void
		 */
		public function include_files() {
			include_once KISWP_PLUGIN_PATH . '/classes/class-kiswp-settings.php';
			include_once KISWP_PLUGIN_PATH . '/classes/class-kiswp-logging.php';
			include_once KISWP_PLUGIN_PATH . '/classes/class-kiswp-woocommerce-button.php';
			include_once KISWP_PLUGIN_PATH . '/classes/class-kiswp-api-requests.php';
			include_once KISWP_PLUGIN_PATH . '/classes/class-kiswp-api-callbacks.php';
			include_once KISWP_PLUGIN_PATH . '/classes/admin/class-kiswp-options-page.php';

		}
		/**
		 * Adds plugin action links
		 *
		 * @param array $links Plugin action link before filtering.
		 *
		 * @return array Filtered links.
		 */
		public function plugin_action_links( $links ) {
			$setting_link = '#';
			$plugin_links = array(
				'<a href="' . $setting_link . '">' . __( 'Settings', 'klarna-instant-shopping-for-wordpress' ) . '</a>',
				'<a href="http://krokedil.se/">' . __( 'Support', 'klarna-instant-shopping-for-wordpress' ) . '</a>',
			);
			return array_merge( $plugin_links, $links );
		}

		/**
		 * Loads the needed scripts for PaysonCheckout.
		 */
		public function load_scripts() {
			if ( is_product() ) {

				// Checkout script.
				wp_register_script(
					'kiswp',
					KISWP_PLUGIN_URL . '/assets/js/kiswp.js',
					array( 'jquery' ),
					KISWP_VERSION,
					true
				);
				$params = array(
					'ajax_url' => admin_url( 'admin-ajax.php' ),
				);
				wp_localize_script(
					'kiswp',
					'kiswp_params',
					$params
				);
				wp_enqueue_script( 'kiswp' );
			}
		}
	}
	KISWP::get_instance();
	/**
	 * Main instance PaysonCheckout_For_WooCommerce.
	 *
	 * Returns the main instance of PaysonCheckout_For_WooCommerce.
	 *
	 * @return PaysonCheckout_For_WooCommerce
	 */
	function KISWP() { // phpcs:ignore
		return KISWP::get_instance();
	}
}
