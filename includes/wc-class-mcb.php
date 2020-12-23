<?php

class WC_MCB extends WC_Payment_Gateway {

	public function __construct() {
 
		$this->id = 'mcb';
		$this->icon = '';
		$this->has_fields = true;
		$this->method_title = 'MCB';
		$this->method_description = 'Allows your customers to pay for order via MCB Payment Gateway.';
		$this->supports = array(
			'products'
		);
	 
		// Method with all the options fields
		$this->init_form_fields();
	 
		// Load the settings.
		$this->init_settings();
		$this->title 		 = $this->get_option( 'title' );
		$this->description 	 = $this->get_option( 'description' );
		$this->testmode 	 = 'yes' === $this->get_option( 'testmode' );
		$this->merchant_id 	 = $this->get_option( 'merchant_id' );
		$this->api_password  = $this->get_option( 'api_password' );
		$this->merchant_name = $this->get_option( 'merchant_name' );
		$this->address_1 	 = $this->get_option( 'address_1' );
		$this->address_2 	 = $this->get_option( 'address_2' );
	 
		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, [$this, 'process_admin_options'] );
		add_action( 'wp_enqueue_scripts', [$this, 'mcb_scripts'] );
		add_action( 'woocommerce_available_payment_gateways', [$this, 'leave_only_mcb'] );
		add_filter( 'script_loader_tag', [$this, 'mcb_callbacks'], 10, 2 );
		add_action( 'woocommerce_api_mcb-gateway', [$this, 'callback_handler'] );
		add_action( 'admin_notices', [$this, 'mcb_notices'] );
	}

	public function callback_handler() {
		if ( ! isset( $_GET['sessionVersion'] ) || empty( $_GET['sessionVersion'] ) ) {
			echo json_encode( [
				'success' => false,
				'message' => 'Transaction details were not passed.'
			] );
			wp_safe_redirect( wc_get_checkout_url() );
			die;
		}
		if ( ! $order_id = $_GET['order_id'] ) {
			echo json_encode( [
				'success' => false,
				'message' => 'Order id was not passed.'
			] );
			wp_safe_redirect( wc_get_checkout_url() );
			die;
		} elseif ( ! $order_key = $_GET['order_key'] ) {
			echo json_encode( [
				'success' => false,
				'message' => 'Order key was not passed.'
			] );
			wp_safe_redirect( wc_get_checkout_url() );
			die;
		}
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			echo json_encode( [
				'success' => false,
				'message' => 'Order does not exist.'
			] );
			wp_safe_redirect( wc_get_checkout_url() );
			die;
		} elseif ( $order->get_order_key() !== $order_key ) {
			echo json_encode( [
				'success' => false,
				'message' => 'Order key is invalid.'
			] );
			wp_safe_redirect( wc_get_checkout_url() );
			die;
		}
		$order->payment_complete( $_GET['sessionVersion'] );
		WC()->cart->empty_cart();
		wp_safe_redirect( $order->get_checkout_order_received_url() );
		die;
	}
  
	public function leave_only_mcb ( $available_gateways ) {
		if ( is_wc_endpoint_url( 'order-pay' ) ) {

			global $wp;
		    $order_id = $wp->query_vars['order-pay'];
		    $order 	  = new WC_Order( $order_id );

		    if ( $order && $order->get_payment_method() === 'mcb' ) {
		    	foreach ( $available_gateways as $key => $value ) {
					if ( $key !== 'mcb' ) {
						unset( $available_gateways[$key] );
					}
				}
		    }
		}
		if ( is_checkout() ) {
			if ( ! $this->gateway_is_ready() ) {
				foreach ( $available_gateways as $key => $value ) {
					if ( $key === 'mcb' ) {
						unset( $available_gateways[$key] );
					}
				}
			}
		}
		return $available_gateways;
	}

	public function mcb_scripts () {
		if ( is_wc_endpoint_url( 'order-pay' ) ) {

			global $wp;
		    $order_id = $wp->query_vars['order-pay'];
		    $order 	  = new WC_Order( $order_id );

		    if ( $order && $order->get_payment_method() === 'mcb' ) {
		    	$products = [];
		    	foreach ( $order->get_items() as $key => $item ) {
		    		$products[] = $item->get_name();
		    	}

				wp_enqueue_script( 'mcb', 'https://mcb.gateway.mastercard.com/checkout/version/56/checkout.js' );
				wp_enqueue_script( 'wc-mcb', plugins_url( '/assets/js/wc-mcb.js', dirname( __FILE__ ) ), ['mcb', 'jquery'] );
				wp_localize_script( 'wc-mcb', 'mcb', [ 
					'currency' 		=> get_woocommerce_currency(), 
					'merchant_id' 	=> $this->merchant_id,
					'order_id' 		=> $order_id,
					'amount'		=> $order->get_total(),
					'order_desc'	=> implode( ', ', $products ),
					'merchant_name' => $this->merchant_name,
					'address_1'		=> $this->address_1,
					'address_2'		=> $this->address_2
				] );
			}
		}
	}

	public function mcb_callbacks( $tag, $handle ) {
		if ( $handle === 'mcb' ) {
			global $wp;
		    $order_id 	= $wp->query_vars['order-pay'];
		    $order 	  	= new WC_Order( $order_id );
		    $order_key 	= $order->get_order_key();

			$complete 	= get_site_url() . '/wc-api/mcb-gateway?order_id=' . $order_id . '&order_key=' . $order_key;
			$error 	  	= wc_get_checkout_url();
			$tag 		= str_replace( '<script', '<script data-error="' . $error . '" data-cancel="' . $error . '" data-complete="' . $complete . '"', $tag );
		}
		return $tag;
	}

	public function init_form_fields () {
		$this->form_fields = array(
			'enabled' => array(
				'title'       => 'Enable/Disable',
				'label'       => 'Enable MCB Gateway',
				'type'        => 'checkbox',
				'description' => '',
				'default'     => 'no'
			),
			'title' => array(
				'title'       => 'Title',
				'type'        => 'text',
				'description' => 'This controls the title which the user sees during checkout.',
				'default'     => 'Credit Card',
				'desc_tip'    => true,
			),
			'description' => array(
				'title'       => 'Description',
				'type'        => 'textarea',
				'description' => 'This controls the description which the user sees during checkout.',
				'default'     => 'Pay with your credit card via MCB payment gateway.',
			),
			'testmode' => array(
				'title'       => 'Test Mode',
				'label'       => 'Enable test mode',
				'type'        => 'checkbox',
				'description' => 'Place the payment gateway in test mode using test API credentials.',
				'default'     => 'yes',
				'desc_tip'    => true,
			),
			'merchant_id' => array(
				'title'       => 'Merchant ID',
				'type'        => 'text'
			),
			'api_password' => array(
				'title'       => 'API Password',
				'type'        => 'password'
			),
			'merchant_name' => array(
				'title'       => 'Merchant name',
				'type'        => 'text',
				'default'	  => get_bloginfo('name')
			),
			'address_1' => array(
				'title'       => 'Address line 1',
				'type'        => 'text',
				'default'	  => WC()->countries->get_base_address()
			),
			'address_2' => array(
				'title'       => 'Address line 2',
				'type'        => 'text',
				'default'	  => WC()->countries->get_base_address_2()
			),
		);
	}

	public function process_payment ( $order_id ) {

		$order = wc_get_order( $order_id );
		return array(
			'result' => 'success',
			'redirect' => $order->get_checkout_payment_url()
		);

	}

	public function gateway_is_ready() {
		if ( ! $this->merchant_id )
			return false;
		if ( ! $this->api_password )
			return false;
		if ( ! $this->merchant_name )
			return false;
		if ( ! $this->address_1 )
			return false;
		if ( ! $this->address_2 )
			return false;

		return true;
	}

	public function mcb_notices() {
		if ( ! $this->gateway_is_ready() ) {
			$message = 'Please configure <a href="' . admin_url( 'admin.php?page=wc-settings&tab=checkout&section=mcb' ) . '">settings</a> to enable MCB gateway in checkout page.';
			echo '<div class="notice notice-info is-dismissible"> <p>' . $message . '</p></div>';
		}
	}
}