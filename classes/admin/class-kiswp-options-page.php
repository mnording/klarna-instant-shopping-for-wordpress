<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * KISWP_Options_Page class.
 *
 * Handles Klarna Instant Shopping Options page.
 */
class KISWP_Options_Page {
	/**
	 * The reference the *Singleton* instance of this class.
	 *
	 * @var $instance
	 */
	protected static $instance;
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
	 * Klarna_Checkout_For_WooCommerce_Confirmation constructor.
	 */
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'add_menu' ), 101 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_css' ) );
		add_action( 'wp_ajax_change_klarna_addon_status', array( $this, 'change_klarna_addon_status' ) );
		add_action( 'admin_init', array( $this, 'kiswp_settings_init' ) );
	}
	/**
	 * Load Admin CSS
	 **/
	public function enqueue_css( $hook ) {
		if ( 'klarna-instant-shopping' == $hook ) {
			wp_register_style( 'kispw-admin', KISWP_PLUGIN_URL . '/assets/css/kispw-admin-admin.css', false, KISWP_VERSION );
			wp_enqueue_style( 'kispw-admin' );
			wp_register_script( 'kispw-admin', KISWP_PLUGIN_URL . '/assets/js/kispw-admin-admin.js', true, KISWP_VERSION );
			wp_enqueue_script( 'kispw-admin' );
		}
	}
	/**
	 * Add the Addons menu to WooCommerce
	 **/
	public function add_menu() {
		$submenu = add_submenu_page( 'woocommerce', __( 'Klarna Instant Shopping', 'klarna-checkout-for-woocommerce' ), __( 'Klarna Instant Shopping', 'klarna-checkout-for-woocommerce' ), 'manage_woocommerce', 'klarna-instant-shopping', array( $this, 'options_page' ) );
	}


	public function kiswp_settings_init() {

		register_setting( 'pluginPage', 'kiswp_settings' );

		add_settings_section(
			'kiswp_pluginPage_section',
			__( 'Klarna instant shopping settings', 'klarna-instant-shopping-for-wordpress' ),
			array( $this, 'kiswp_settings_section_callback' ),
			'pluginPage'
		);
		add_settings_field(
			'logging',
			__( 'Debug logging', 'klarna-instant-shopping-for-wordpress' ),
			array( $this, 'field_logging_render' ),
			'pluginPage',
			'kiswp_pluginPage_section'
		);

		// Load plugin options page if KCO or KP plugin isn't installed.
		if ( ! class_exists( 'Klarna_Checkout_For_WooCommerce' ) && ! class_exists( 'WC_Klarna_Payments' ) ) {
			add_settings_field(
				'test_id',
				__( 'Test Username (UID)', 'klarna-instant-shopping-for-wordpress' ),
				array( $this, 'field_test_id_render' ),
				'pluginPage',
				'kiswp_pluginPage_section'
			);

			add_settings_field(
				'test_secret',
				__( 'Test Password', 'klarna-instant-shopping-for-wordpress' ),
				array( $this, 'field_test_secret_render' ),
				'pluginPage',
				'kiswp_pluginPage_section'
			);

			add_settings_field(
				'live_id',
				__( 'Production Username (UID)', 'klarna-instant-shopping-for-wordpress' ),
				array( $this, 'field_live_id_render' ),
				'pluginPage',
				'kiswp_pluginPage_section'
			);

			add_settings_field(
				'live_secret',
				__( 'Production Password', 'klarna-instant-shopping-for-wordpress' ),
				array( $this, 'field_live_secret_render' ),
				'pluginPage',
				'kiswp_pluginPage_section'
			);

			add_settings_field(
				'testmode',
				__( 'Test mode', 'klarna-instant-shopping-for-wordpress' ),
				array( $this, 'field_testmode_render' ),
				'pluginPage',
				'kiswp_pluginPage_section'
			);

		}

	}


	public function field_test_id_render() {

		$options = get_option( 'kiswp_settings' );
		?>
		<input type='text' name='kiswp_settings[test_id]' value='<?php echo $options['test_id']; ?>'>
		<?php

	}


	public function field_test_secret_render() {

		$options = get_option( 'kiswp_settings' );
		?>
		<input type='text' name='kiswp_settings[test_secret]' value='<?php echo $options['test_secret']; ?>'>
		<?php

	}


	public function field_live_id_render() {

		$options = get_option( 'kiswp_settings' );
		?>
		<input type='text' name='kiswp_settings[live_id]' value='<?php echo $options['live_id']; ?>'>
		<?php

	}


	public function field_live_secret_render() {

		$options = get_option( 'kiswp_settings' );
		?>
		<input type='text' name='kiswp_settings[live_secret]' value='<?php echo $options['live_secret']; ?>'>
		<?php

	}


	public function field_testmode_render() {

		$options = get_option( 'kiswp_settings' );
		?>
		<input type='checkbox' name='kiswp_settings[testmode]' <?php checked( $options['testmode'], 'yes' ); ?> value='yes'>
		<?php

	}


	public function field_logging_render() {

		$options = get_option( 'kiswp_settings' );
		?>
		<input type='checkbox' name='kiswp_settings[logging]' <?php checked( $options['logging'], 'yes' ); ?> value='yes'>
		<?php

	}


	public function kiswp_settings_section_callback() {

		// echo __( 'This section description', 'klarna-instant-shopping-for-wordpress' );
	}

	/**
	 * Add the Addons options page to WooCommerce.
	 **/
	public function options_page() {
		?>
		<div class="kiswp-wrap wrap">
			<div class="kiswp-heading">
				<h1><?php esc_html_e( 'Klarna Instant Shopping', 'klarna-checkout-for-woocommerce' ); ?></h1>
				<?php echo '<p>' . __( '<i>All credentials will be fetched from your main Klarna plugin.</i>', 'klarna-checkout-for-woocommerce' ) . '</p>'; ?>
			</div>
			<div class="kiswp-body">
			
				<form action="options.php" method='post'>
					<?php
					$environment = ( 'yes' === KISWP()->settings->get_testmode() ) ? 'playground' : 'production';
					echo 'Current selected environment: ' . $environment;
					settings_fields( 'pluginPage' );
					do_settings_sections( 'pluginPage' );
					submit_button();
					?>
				</form>
				
				<form action="<?php echo admin_url( 'admin.php?page=klarna-instant-shopping&newbutton' ); ?>" method="post">
					<?php
					if ( isset( $_POST['newbutton'] ) ) {
						$response = KISWP()->api_requests->generate_button_key();
						if ( is_wp_error( $response ) ) {
							echo '<pre>';
							print_r( $response );
							echo '</pre>';
						} else {
							$response_body = json_decode( $response['body'] );
						}

						update_option( 'kiswp_buttonid', $response_body->button_key );
					}
					?>
					<button name="newbutton">Create new button</button>
					
				</form>
				<?php

				echo '<p>Button: ' . get_option( 'kiswp_buttonid' ) . '</p>';
				?>
				
			</div>
		</div>

		<?php
	}

}
