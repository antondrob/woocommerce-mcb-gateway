<?php
/**
 * MCB WooCommerce Payment Gateway
 *
 * @package           MCB
 * @author            OnePix
 * @copyright         2020 Anton Drobyshev
 * @license           GPL-2.0-or-later
 *
 * @wordpress-plugin
 * Plugin Name:       MCB WooCommerce Payment Gateway
 * Description:       Allows your customers to pay for order via MCB Payment Gateway.
 * Version:           1.0
 * Requires at least: 5.0
 * Requires PHP:      7.0
 * Author:            OnePix
 * Author URI:        https://onepix.net/
 * Text Domain:       woo-mcb
 * License:           GPL v2 or later
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 */

namespace MCB;

class Gateway {

	public function __construct () {

		add_action( 'plugins_loaded', [$this, 'init_mcb'] );

	}

	public function mcb_notices () {

		echo '<div class="notice notice-error">
			<p>' . __( 'MCB WooCommerce Payment Gateway needs WooCommerce to be active.', 'woo-mcb' ) . '</p>
		</div>';

	}	

	public function mcb_gateway_class ( $gateways ) {

		$gateways[] = 'WC_MCB';
		return $gateways;

	}

	public function init_mcb () {

		if ( class_exists( 'WooCommerce' ) ) {

			add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), [$this, 'settings_link'] );
			add_filter( 'woocommerce_payment_gateways', [$this, 'mcb_gateway_class'] );
			add_filter( 'script_loader_tag', [$this, 'mcb_src_attr'], 10, 3 );
			// MCB class
			require_once( 'includes/wc-class-mcb.php' );

		} else {
			add_action( 'admin_notices', [$this, 'mcb_notices'] );
		}

	}

	public function settings_link ( $links ) {

		$links[] = '<a href="' . admin_url( 'admin.php?page=wc-settings&tab=checkout&section=mcb' ) . '">' . __( 'Settings', 'woo-mcb' ) . '</a>';
		return $links;

	}

	public function mcb_src_attr( $tag, $handle, $src ) {
	    // the handles of the enqueued scripts we want to async
	    $async_scripts = array( 'mcd' );

	    if ( $handle === 'mcd' ) {
	        return '<script type="text/javascript" src="' . $src . '" data-error="errorCallback" data-cancel="cancelCallback" data-complete="completeCallback"></script>' . "\n";
	    }

	    return $tag;
	}

}

new Gateway();