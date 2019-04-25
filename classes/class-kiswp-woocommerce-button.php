<?php
/**
 * WooCommerce button class file.
 *
 * @package KISWP/Classes
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}
/**
 * WooCommerce product page button class.
 */
class KISWP_WooCommerce_Button {
	/**
	 * Class constructor.
	 */
	public function __construct() {
		add_action( 'woocommerce_before_add_to_cart_form', array( $this, 'init_and_render_buttton' ) );
	}

	public function init_and_render_buttton() {
		$button_id = get_option( 'kiswp_buttonid' ); // $this->generateButtonKey();
		error_log( '$button_id ' . var_export( $button_id, true ) );
		global $product;
		if ( 'instock' === $product->get_stock_status() ) {
			wp_enqueue_script( 'klarna-instant-shopping', 'https://x.klarnacdn.net/instantshopping/lib/v1/lib.js' );
			$this->render_button( $button_id );
			$this->initiate_button();
		} else {
			KISWP()->logger->log( 'Product Not in stock' );
		}
	}

	public function render_button( $button_id ) {
		$options     = get_option( 'kiswp_settings' );
		$testmode    = $options['testmode'];
		$environment = ( 'yes' === $testmode ) ? 'playground' : 'production';
		echo '<klarna-instant-shopping data-key="' . $button_id . '" data-environment="' . $environment . '" data-region="eu"></klarna-instant-shopping>';
	}

	public function initiate_button() {
		global $product;
		$id = $product->get_id();
		if ( $product->get_image_id() ) {
			$imageUlr = wp_get_attachment_image_src( $product->get_image_id(), 'woocommerce_single' );
		} else {
			$imageUlr = esc_url( wc_placeholder_img_src( 'woocommerce_single' ) );
		}
		$productPrice = $product->get_price();

		WC()->cart->empty_cart();
		WC()->cart->add_to_cart( $id );
		foreach ( WC()->cart->get_cart() as $cartitem ) {
			$klarnaTaxAmount = ( $cartitem['line_tax_data']['subtotal'][1] );
		}

		$vat             = $klarnaTaxAmount / ( $productPrice - $klarnaTaxAmount );
		$shippingMethods = $this->GetShippingMethodsForKlarna( $productPrice, $vat );

		if ( $product->get_type() == 'simple' ) {
			$this->load_js_for_simple_product( $product, $klarnaTaxAmount, $imageUlr, $vat, $shippingMethods );
		}
		if ( $product->get_type() == 'variable' ) {
			$this->load_js_for_variable_product( $product, $shippingMethods, $vat );
		}
	}

	public function load_js_for_simple_product( $product, $klarnaTaxAmount, $imageUlr, $vat, $shippingMethods ) {
		$location = WC_Geolocation::geolocate_ip();
		$country  = $location['country'];

		wp_add_inline_script(
			'klarna-instant-shopping',
			'window.klarnaAsyncCallback = function () {
            Klarna.InstantShopping.load({
            "purchase_country": "' . $country . '",
            "purchase_currency": "' . get_woocommerce_currency() . '",
            "locale": "' . str_replace( '_', '-', get_locale() ) . '",
            "merchant_urls": {
            "terms": "' . get_permalink( woocommerce_get_page_id( 'terms' ) ) . '",  
            },
            "order_lines": [{
                "type": "physical",
                "reference": "' . $product->get_sku() . '",
                "name": "' . $product->get_name() . '",
                "quantity": 1,
                "merchant_data": "{\"prod_id\":' . $product->get_id() . '}",
                "unit_price": ' . intval( $product->get_price() * 100 ) . ',
                "tax_rate": ' . intval( $vat * 10000 ) . ',
                "total_amount": ' . intval( $product->get_price() * 100 ) . ',
                "total_discount_amount": 0,
                "total_tax_amount": ' . intval( $klarnaTaxAmount * 100 ) . ',
                "image_url": "' . $imageUlr . '"
            }],
            "shipping_options": 
                ' . json_encode( $shippingMethods ) . '
                
            }, function (response) {
                console.log("Klarna.InstantShopping.load callback with data:" + JSON.stringify(response))
            })
        };',
			'before'
		);
	}
	public function load_js_for_variable_product( $product, $shippingMethods, $vat ) {
		$location = WC_Geolocation::geolocate_ip();
		$country  = $location['country'];
		wp_add_inline_script(
			'klarna-instant-shopping',
			'jQuery( ".single_variation_wrap" ).on( "show_variation", function ( event, variation ) {
            if(variation.is_in_stock) {
            var priceEx = variation.display_price*100/(1+' . floatval( $vat ) . ');
            var taxamount = variation.display_price*100 - priceEx;
            var extraprodname = "";
            for (var key in variation.attributes) {
                extraprodname += variation.attributes[key]+" ";
            }
            Klarna.InstantShopping.load({
                "purchase_country": "' . $country . '",
                "purchase_currency": "' . get_woocommerce_currency() . '",
                "locale": "' . str_replace( '_', '-', get_locale() ) . '",
                "merchant_urls": {
                "terms": "' . rtrim( get_permalink( woocommerce_get_page_id( 'terms' ) ), '/' ) /* TODO: rtrim pending klarna fix*/ . '",  
                },
                "order_lines": [{
                "type": "physical",
                "reference": variation.sku,
                "name": "' . $product->get_name() . ' "+extraprodname,
                "quantity": 1,
                "merchant_data": "{\"prod_id\":' . $product->get_id() . ',\"variation_id\":"+variation.variation_id+"}",
                "unit_price": variation.display_price*100,
                "tax_rate": ' . intval( $vat * 10000 ) . ',
                "total_amount": variation.display_price*100,
                "total_discount_amount": 0,
                "total_tax_amount": taxamount,
                "image_url": variation.image.src
            }],
            "shipping_options": 
                    ' . json_encode( $shippingMethods ) . '
                
            }, function (response) {
                console.log("Klarna.InstantShopping.load callback with data:" + JSON.stringify(response))
            });
        }
        else {
            var getbbdom = document.getElementsByTagName("klarna-instant-shopping")[0];
            getbbdom.innerHTML ="";
        }
        } );',
			'after'
		);
	}

	public function verifyShipping( $klarnaOrder ) {
		// Verify that selected shipping is applicable
	}
	public function GetShippingMethodsForKlarna( $productPrice, $vat ) {
		$lowestCost      = 999999999;
		$selectedIndex   = 0;
		$shippingMethods = array();
		$location        = WC_Geolocation::geolocate_ip();
		$country         = $location['country'];
		foreach ( $this->GetShippingMethodsForAmount( $productPrice, $country ) as $key => $methods ) {
			KISWP()->logger->log( 'Shipping price is ' . $methods['price'] );
			$shippingPriceInCents = $methods['price'] * 100;
			KISWP()->logger->log( 'Shipping in cents price is ' . $shippingPriceInCents );
			if ( $shippingPriceInCents < $lowestCost ) {
				$lowestCost    = $shippingPriceInCents;
				$selectedIndex = $key;
			}
			$shippingMethods[] = array(
				'id'              => $methods['id'],
				'name'            => $methods['name'],
				'description'     => '',
				'price'           => $shippingPriceInCents + intval( $shippingPriceInCents * $vat ),
				'tax_amount'      => intval( $shippingPriceInCents * $vat ),
				'tax_rate'        => intval( $vat * 10000 ),
				'preselected'     => false,
				'shipping_method' => 'PickUpPoint',
			);
		};
		KISWP()->logger->log( 'Lowest shipping was ' . $lowestCost . ' for index ' . $selectedIndex );
		$shippingMethods[ $selectedIndex ]['preselected'] = true;
		KISWP()->logger->log( json_encode( $shippingMethods ) );
		return $shippingMethods;
	}
	public function GetShippingMethodsForAmount( $amount, $country ) {
		$active_methods = array();
		$values         = array(
			'country' => $country,
			'amount'  => $amount,
		);
		// Fake product number to get a filled card....
		WC()->cart->add_to_cart( '1' );
		WC()->shipping->calculate_shipping( $this->get_shipping_packages( $values ) );
		$shipping_methods = WC()->shipping->packages;
		foreach ( $shipping_methods[0]['rates'] as $id => $shipping_method ) {
			$active_methods[] = array(
				'id'       => $shipping_method->get_instance_id(),
				'type'     => $shipping_method->method_id,
				'provider' => $shipping_method->method_id,
				'name'     => $shipping_method->label,
				'price'    => number_format( $shipping_method->cost, 2, '.', '' ),
			);
		}
		return $active_methods;
	}
	public function get_shipping_packages( $value ) {
		// Packages array for storing 'carts'
		$packages                                = array();
		$packages[0]['contents']                 = WC()->cart->cart_contents;
		$packages[0]['contents_cost']            = $value['amount'];
		$packages[0]['applied_coupons']          = WC()->session->applied_coupon;
		$packages[0]['destination']['country']   = $value['country'];
		$packages[0]['destination']['state']     = '';
		$packages[0]['destination']['postcode']  = '';
		$packages[0]['destination']['city']      = '';
		$packages[0]['destination']['address']   = '';
		$packages[0]['destination']['address_2'] = '';
		return apply_filters( 'woocommerce_cart_shipping_packages', $packages );
	}
}
