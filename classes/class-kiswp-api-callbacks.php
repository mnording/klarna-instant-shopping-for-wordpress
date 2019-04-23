<?php
/**
 * WooCommerce API Callbacks class file.
 *
 * @package KISWP/Classes
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}
/**
 * API Callbacks class.
 */
class KISWP_Api_Callbacks {
	/**
	 * Class constructor.
	 */
	public function __construct() {
		add_action( 'rest_api_init', array( $this, 'register_kiswp_endpoint' ) );
	}

	/**
	 * Register callback endpoint.
	 */
	public function register_kiswp_endpoint() {
		register_rest_route(
			'klarna-instant-shopping',
			'/place-order/',
			array(
				'methods'  => 'POST',
				'callback' => array( $this, 'handle_order_post_back' ),
			)
		);
	}

	/**
	 * Handle callback from Klarna.
	 */
	public function handle_order_post_back( $request_data ) {
		KISWP()->logger->log( 'Got postback with successfull auth ' . stripslashes_deep( json_encode( $request_data ) ) );

		$req = json_decode( $request_data->get_body() );

		$klarnaorder = $this->GetOrderDetailsFromKlarna( $req->authorization_token );

		if ( $this->VerifyOrder( $klarnaorder ) ) {
			$WCOrder   = $this->CreateWcOrder( $klarnaorder );
			$WCOrderId = $WCOrder->get_id();
			KISWP()->logger->log( 'Created WC order ' . $WCOrderId );
			$klarnaorderID = $this->PlaceOrder( $req->authorization_token, $klarnaorder, $WCOrder );
			KISWP()->logger->log( 'Created Klarna order ' . $klarnaorderID );
			$this->UpdateWCOrder( $WCOrderId, $klarnaorderID );
			KISWP()->logger->log( 'Updated WC Order ' . $WCOrderId . ' with klarna order id ' . $klarnaorderID );
		} else {
			$this->DenyOrder( $req->authorization_token, 'other', __( 'Could not place order', $this->textdomain ) );
		}
	}

	public function GetOrderDetailsFromKlarna( $authToken ) {

		$request_url  = KISWP()->api_requests->get_api_url_base() . 'instantshopping/v1/authorizations/' . $authToken;
		$request_args = array(
			'headers'    => KISWP()->api_requests->get_request_headers(),
			'user-agent' => KISWP()->api_requests->get_user_agent(),
			'body'       => wp_json_encode( $order ),
			'timeout'    => 10,
		);
		$response     = wp_safe_remote_get( $request_url, $request_args );
		KISWP()->logger->log( 'Got order details from klarna ' );
		KISWP()->logger->log( json_encode( $response ) );
		return json_decode( $response['body'] );
	}
	/*
	- address_error when there are problems with the provided address,
	- item_out_of_stock when the item has gone out of stock,
	- consumer_underaged when the product has limitations based on the age of the consumer,
	- unsupported_shipping_address when there are problems with the shipping address. You don’t need to specify a deny_message for the above codes.
	- other for which you may specify a deny_message which will be shown to the consumer. It is important that the language of the message matches the locale of the Instant Shopping flow
	*/
	public function DenyOrder( $auth, $code, $message ) {

		$request_url  = KISWP()->api_requests->get_api_url_base() . 'instantshopping/v1/authorizations/' . $auth;
		$request_args = array(
			'headers'    => KISWP()->api_requests->get_request_headers(),
			'user-agent' => KISWP()->api_requests->get_user_agent(),
			'timeout'    => 10,
			'method'     => 'DELETE',
			'body'       => wp_json_encode(
				array(
					'deny_code'    => $code,
					'deny_message' => $message,
				)
			),
		);
		$response     = wp_remote_request( $request_url, $request_args );
		KISWP()->logger->log( 'Deleted Klarna AuthToken ' . $auth );
	}

	public function PlaceOrder( $auth, $order, $wcorder ) {
		$wcorderUrl                         = $wcorder->get_checkout_order_received_url();
		$order->merchant_urls->confirmation = $wcorderUrl;
		$request_url                        = KISWP()->api_requests->get_api_url_base() . 'instantshopping/v1/authorizations/' . $auth . '/orders/';
		$request_args                       = array(
			'headers'    => KISWP()->api_requests->get_request_headers(),
			'user-agent' => KISWP()->api_requests->get_user_agent(),
			'body'       => wp_json_encode( $order ),
			'timeout'    => 10,
		);
		$response                           = wp_safe_remote_post( $request_url, $request_args );

		$order = json_decode( $response['body'] );
		return $order->order_id;
	}
	public function VerifyOrder( $klarnaOrder ) {
		if ( ! $this->verifyStockLevels( $klarnaOrder ) ) {
			return false;
		}
		$this->verifyShipping( $klarnaOrder );
		return true;
	}
	public function verifyStockLevels( $klarnaOrder ) {
		foreach ( $klarnaOrder->order_lines as $orderline ) {
			if ( $orderline->type != 'shipping_fee' ) {
				$merchdata = json_decode( $orderline->merchant_data );
				$prodid    = $merchdata->prod_id;
				$prod      = wc_get_product( $prodid );
				if ( $merchdata->variation_id ) {
					$prod = wc_get_product( $merchdata->variation_id );
				}
				if ( ! $prod->is_in_stock() ) {
					return false;
				}
			}
			return true;
		}
	}

	public function verifyShipping( $klarnaOrder ) {
		// Verify that selected shipping is applicable
	}

	public function UpdateWCOrder( $orderid, $klarnaId ) {
		$order = wc_get_order( $orderid );
		$order->payment_complete( $klarnaId );
		$order->set_payment_method( 'kco' );
		$order->set_payment_method_title( 'Klarna' );
		$order->set_created_via( 'klarna_instant_shopping' );
		$order->update_status( 'processing', 'Got Klarna OK', true );
		$order->save();
	}
	public function CreateWcOrder( $klarnaOrderObject ) {
		$address = $this->GetWooAdressFromKlarnaOrder( $klarnaOrderObject );
		KISWP()->logger->log( 'Got address from klarna object ' );
		KISWP()->logger->log( json_encode( $address ), $this->logContext );
		$orderlines = $this->GetWCLineItemsFromKlarnaOrder( $klarnaOrderObject );
		KISWP()->logger->log( 'Got line items from klarna object ' );
		KISWP()->logger->log( json_encode( $orderlines ), $this->logContext );
		$shippinglines = $this->GetWCShippingLinesFromKlarnaOrder( $klarnaOrderObject );
		KISWP()->logger->log( 'Got shipping lines from klarna object ' );
		KISWP()->logger->log( json_encode( $shippinglines ) );
		// Now we create the order
		try {
			$order = wc_create_order();
			KISWP()->logger->log( 'Created WC  order' );
			$item = new WC_Order_Item_Shipping();
			KISWP()->logger->log( 'Adding shipping' );
			$item->set_method_title( $shippinglines['name'] );
			$item->set_method_id( $shippinglines['id'] );
			$item->set_total( $shippinglines['price'] );
			$order->add_item( $item );
			foreach ( $orderlines as $line ) {
				if ( $line['variation_id'] ) {
					KISWP()->logger->log( 'Creating variation order' );
					$membershipProduct = new WC_Product_Variable( $line['product_id'] );
					$theMemberships    = $membershipProduct->get_available_variations();
					$variationsArray   = array();
					foreach ( $theMemberships as $membership ) {
						if ( $membership['variation_id'] == $line['variation_id'] ) {
							$variationID                  = $membership['variation_id'];
							$variationsArray['variation'] = $membership['attributes'];
						}
					}
					KISWP()->logger->log( 'Looking for variation id ' . $line['variation_id'] );
					if ( $variationID ) {
						KISWP()->logger->log( 'Found variation with id ' . $variationID );
						$varProduct = new WC_Product_Variation( $variationID );
						$order->add_product( $varProduct, 1, $variationsArray );
					}
				} else {
					$order->add_product( get_product( $line['product_id'] ), $line['quantity'] );
				}
			}
			$order->set_address( $address, 'billing' );
			$order->set_address( $address, 'shipping' ); // TODO: Seperate shipping/billing?
			$order->calculate_totals();
			$order->update_status( 'pending', 'Imported order', true );
		} catch ( Exception $e ) {
			$this->logger->logError( 'Unable to create order' );
			$this->logger->logError( 'Caught exception: ', $e->getMessage() );
		}
		return $order;
	}

	public function GetWooAdressFromKlarnaOrder( $klarnaOrder ) {

		$adress = array(
			'first_name' => $klarnaOrder->billing_address->given_name,
			'last_name'  => $klarnaOrder->billing_address->family_name,
			'email'      => $klarnaOrder->billing_address->email,
			'phone'      => $klarnaOrder->billing_address->phone,
			'address_1'  => $klarnaOrder->billing_address->street_address,
			'city'       => $klarnaOrder->billing_address->city,
			'postcode'   => $klarnaOrder->billing_address->postal_code,
			'country'    => $klarnaOrder->billing_address->country,
		);
		return $adress;
	}

	public function GetWCLineItemsFromKlarnaOrder( $klarnaOrder ) {
		$newlineitems = array();
		foreach ( $klarnaOrder->order_lines as $orderline ) {

			if ( $orderline->type != 'shipping_fee' ) {
				$newline = array(
					'name'       => $orderline->name,
					'product_id' => json_decode( $orderline->merchant_data )->prod_id,
					'quantity'   => $orderline->quantity,
					'price'      => (float) ( $orderline->unit_price / 100 ),
					'sku'        => $orderline->reference,
				);
				if ( json_decode( $orderline->merchant_data )->variation_id ) {
					$newline['variation_id'] = json_decode( $orderline->merchant_data )->variation_id;
				}
				$newlineitems[] = $newline;
			}
		}
		return $newlineitems;
	}

	public function GetWCShippingLinesFromKlarnaOrder( $klarnaOrder ) {
		$newlineitems = array();
		foreach ( $klarnaOrder->order_lines as $orderline ) {
			if ( $orderline->type == 'shipping_fee' ) {
				$newlineitems[] = array(
					'name'        => $orderline->name,
					'quantity'    => $orderline->quantity,
					'price'       => (float) ( $orderline->unit_price / 100 ) - (float) ( $orderline->total_tax_amount / 100 ),
					'instance_id' => $orderline->reference,
				);
			}
		}
		return $newlineitems[0];
	}

}
