<?php
/**
 * Plugin Name: Pay With Zap
 * Plugin URI: https://zap-wallet.me
 * Description: Allows instant and fee-reduced payments in BTC and LTC through Zap Wallet.
 * Author: João Almeida
 * Author URI: https://joaoalmeida.me
 * Version: 0.1
 * Text Domain: wc-gateway-zap
 * Domain Path: /i18n/languages/
 *
 * Copyright: (c) 2017, Zap
 *
 * License: GNU General Public License v3.0
 * License URI: http://www.gnu.org/licenses/gpl-3.0.html
 *
 * @package   WC-Gateway-Zap
 * @author    João Almeida
 * @category  payments
 * @copyright Copyright (c) 2017, Zap
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GNU General Public License v3.0
 *
 */

defined( 'ABSPATH' ) or exit;


// Make sure WooCommerce is active
if ( ! in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) {
	return;
}


/**
 * Add the gateway to WC Available Gateways
 *
 * @since 1.0.0
 * @param array $gateways all available WC gateways
 * @return array $gateways all WC gateways + zap gateway
 */
function wc_zap_add_to_gateways( $gateways ) {
	$gateways[] = 'WC_Gateway_Zap';
	return $gateways;
}
add_filter( 'woocommerce_payment_gateways', 'wc_zap_add_to_gateways' );



/**
 * Adds plugin page links
 *
 * @since 1.0.0
 * @param array $links all plugin links
 * @return array $links all plugin links + our custom links (i.e., "Settings")
 */
function wc_zap_gateway_plugin_links( $links ) {

	$plugin_links = array(
		'<a href="' . admin_url( 'admin.php?page=wc-settings&tab=checkout&section=zap_gateway' ) . '">' . __( 'Configure', 'wc-gateway-zap' ) . '</a>'
	);

	return array_merge( $plugin_links, $links );
}
add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'wc_zap_gateway_plugin_links' );


/**
 * Zap Payment Gateway
 *
 * Provides an Zap payment gateway.
 *
 * @class 		WC_Gateway_Zap
 * @extends		WC_Payment_Gateway
 * @version		1.0.0
 * @package		WooCommerce/Classes/Payment
 * @author 		João Almeida
 */
add_action( 'plugins_loaded', 'wc_zap_gateway_init', 11 );

function wc_zap_gateway_init() {

	class WC_Gateway_Zap extends WC_Payment_Gateway {

		/**
		 * Constructor for the gateway.
		 */
		public function __construct() {

			$this->id                 = 'zap_gateway';
			$this->icon               = apply_filters('woocommerce_offline_icon', '');
			$this->has_fields         = false;
			$this->method_title       = __( 'Pay with Zap', 'wc-gateway-zap' );
			$this->method_description = __( 'Allows payments fee-less and instant payments through Zap Wallet.', 'wc-gateway-zap' );

			// Load the settings.
			$this->init_form_fields();
			$this->init_settings();

			// Define user set variables
			$this->title        = $this->get_option( 'title' );
			$this->description  = $this->get_option( 'description' );
			$this->instructions = $this->get_option( 'instructions', $this->description );
			$this->endpoint = $this->get_option( 'endpoint' );

			// Actions
			add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
			add_action( 'woocommerce_thankyou_' . $this->id, array( $this, 'thankyou_page' ) );
			add_action( 'woocommerce_email_before_order_table', array( $this, 'email_instructions' ), 10, 3 );
			add_action('woocommerce_receipt_' . $this->id, array(&$this, 'receipt_page'));
      add_action('woocommerce_api_wc_gateway_zap', array( $this, 'check_zap_response' ) );
		}


		/**
		 * Initialize Gateway Settings Form Fields
		 */
		public function init_form_fields() {

			$this->form_fields = apply_filters( 'wc_zap_form_fields', array(

				'enabled' => array(
					'title'   => __( 'Enable/Disable', 'wc-gateway-zap' ),
					'type'    => 'checkbox',
					'label'   => __( 'Enable Pay with zap', 'wc-gateway-zap' ),
					'default' => 'yes'
				),

				'title' => array(
					'title'       => __( 'Title', 'wc-gateway-zap' ),
					'type'        => 'text',
					'description' => __( 'This controls the title for the payment method the customer sees during checkout.', 'wc-gateway-zap' ),
					'default'     => __( 'Pay with Zap', 'wc-gateway-zap' ),
					'desc_tip'    => true,
				),

				'description' => array(
					'title'       => __( 'Description', 'wc-gateway-zap' ),
					'type'        => 'textarea',
					'description' => __( 'Payment method description that the customer will see on your checkout.', 'wc-gateway-zap' ),
					'default'     => __( 'Pay through Lightning Network.', 'wc-gateway-zap' ),
					'desc_tip'    => true,
				),

				'instructions' => array(
					'title'       => __( 'Instructions', 'wc-gateway-zap' ),
					'type'        => 'textarea',
					'description' => __( 'Instructions that will be added to the thank you page and emails.', 'wc-gateway-zap' ),
					'default'     => '',
					'desc_tip'    => true,
				),
				'endpoint' => array(
					'title'       => __( 'Endpoint', 'wc-gateway-zap' ),
					'type'        => 'textarea',
					'description' => __( 'Place here the API endpoint', 'wc-gateway-zap' ),
					'default'     => 'http://localhost:3000/api',
					'desc_tip'    => true,
				),
			) );
		}



		/**
		 * Output for the order received page.
		 */
		public function thankyou_page() {
			if ( $this->instructions ) {
				echo wpautop( wptexturize( $this->instructions ) );
			}
		}


		/**
		 * Add content to the WC emails.
		 *
		 * @access public
		 * @param WC_Order $order
		 * @param bool $sent_to_admin
		 * @param bool $plain_text
		 */
		public function email_instructions( $order, $sent_to_admin, $plain_text = false ) {

			if ( $this->instructions && ! $sent_to_admin && $this->id === $order->payment_method && $order->has_status( 'on-hold' ) ) {
				echo wpautop( wptexturize( $this->instructions ) ) . PHP_EOL;
			}
		}

		/**
		 * Receipt page.
		 *
		 * @access public
		 * @param WC_Order $order
		 */
	    function receipt_page($order){
	        echo $this -> generate_zap_button($order);
	    }


	   /**
		 * Generate Pay with Zap button.
		 *
		 * @access public
		 * @param WC_Order $order
		 * @return Button
		 */
	    function generate_zap_button ($order) {
	    	global $woocommerce;
	    	$order = wc_get_order( $order );

	    	$invoiceInfo = array();
	    	$invoiceInfo['value'] = $order->order_total;
	    	$invoiceInfo['memo'] = "Order key: " . $order->get_checkout_order_received_url();

      	$invoiceResponse = wp_remote_post ($this->endpoint. '/addinvoice',
        array(
        	'method' => 'POST',
        	'timeout' => 45,
        	'redirection' => 5,
        	'httpversion' => '1.0',
        	'blocking' => true,
        	'headers' => array( 'Content-Type' => 'application/json' ),
        	'body' => json_encode($invoiceInfo)
            )
        );


        $invoiceResponse = json_decode($invoiceResponse['body'], true)['data'];

				$order->add_order_note("Awaiting payment of " . $invoiceInfo['value'] . "BTC @ 1 BTC ~ XXXUSD. <br> Invoice ID: " . $invoiceResponse['payment_request']);
        $html = "<div id='zap-div'><p>Amount: " . $invoiceInfo['value'] . "<br><a href= 'lightning:" . $invoiceResponse['payment_request'] . "'><img src='https://joaoalmeida.me/zap_2.png'  width='60' height='60' border='0'></a><p>Waiting for payment.</div><div id='zap-success' style='display:none'><p> Transaction complete </p> <img src='data:image/svg+xml;utf8;base64,PD94bWwgdmVyc2lvbj0iMS4wIiBlbmNvZGluZz0iaXNvLTg4NTktMSI/Pgo8IS0tIEdlbmVyYXRvcjogQWRvYmUgSWxsdXN0cmF0b3IgMTkuMC4wLCBTVkcgRXhwb3J0IFBsdWctSW4gLiBTVkcgVmVyc2lvbjogNi4wMCBCdWlsZCAwKSAgLS0+CjxzdmcgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIiB4bWxuczp4bGluaz0iaHR0cDovL3d3dy53My5vcmcvMTk5OS94bGluayIgdmVyc2lvbj0iMS4xIiBpZD0iTGF5ZXJfMSIgeD0iMHB4IiB5PSIwcHgiIHZpZXdCb3g9IjAgMCA0MjYuNjY3IDQyNi42NjciIHN0eWxlPSJlbmFibGUtYmFja2dyb3VuZDpuZXcgMCAwIDQyNi42NjcgNDI2LjY2NzsiIHhtbDpzcGFjZT0icHJlc2VydmUiIHdpZHRoPSI1MTJweCIgaGVpZ2h0PSI1MTJweCI+CjxwYXRoIHN0eWxlPSJmaWxsOiM2QUMyNTk7IiBkPSJNMjEzLjMzMywwQzk1LjUxOCwwLDAsOTUuNTE0LDAsMjEzLjMzM3M5NS41MTgsMjEzLjMzMywyMTMuMzMzLDIxMy4zMzMgIGMxMTcuODI4LDAsMjEzLjMzMy05NS41MTQsMjEzLjMzMy0yMTMuMzMzUzMzMS4xNTcsMCwyMTMuMzMzLDB6IE0xNzQuMTk5LDMyMi45MThsLTkzLjkzNS05My45MzFsMzEuMzA5LTMxLjMwOWw2Mi42MjYsNjIuNjIyICBsMTQwLjg5NC0xNDAuODk4bDMxLjMwOSwzMS4zMDlMMTc0LjE5OSwzMjIuOTE4eiIvPgo8Zz4KPC9nPgo8Zz4KPC9nPgo8Zz4KPC9nPgo8Zz4KPC9nPgo8Zz4KPC9nPgo8Zz4KPC9nPgo8Zz4KPC9nPgo8Zz4KPC9nPgo8Zz4KPC9nPgo8Zz4KPC9nPgo8Zz4KPC9nPgo8Zz4KPC9nPgo8Zz4KPC9nPgo8Zz4KPC9nPgo8Zz4KPC9nPgo8L3N2Zz4K' width='60' height='60'/> </div>";

        return "<script type=\"text/javascript\">

  			jQuery(function($){
    			$(\"body\").block( {
            message: \"" . $html . "\",
              overlayCSS: {
                background: \"\#fff\",
                opacity: 0.6
            },
            css: {
              padding:        20,
              textAlign:      \"center\",
              color:          \"\#555\",
              border:         \"3px solid \#aaa\",
              backgroundColor:\"\#fff\",
              lineHeight:\"32px\",
              cursor: \"default\"
            }
        	});

					$('#zap-success').on('click', function () {
						window.location.replace('" . $this->get_return_url( $order ) . "');
						$(\"body\").unblock();
					});

					var web = new WebSocket('wss://ff52370d.ngrok.io');
					var response;
					web.onmessage = function (event) {
						response = JSON.parse(event.data);

						if(response.payment_request == '" . $invoiceResponse['payment_request'] . "') {

							//Send Callback to API
							$.get( '" . get_site_url() . "/?wc-api=wc_gateway_zap&ln_invoice=" . $invoiceResponse['payment_request'] . "&order_cb=" . $order->id ."', function( data ) {
								if(data == 'success'){
									$('#zap-div').fadeOut( 'slow', function() {});
									$('#zap-success').fadeIn( 'slow', function() {});
									console.log(data);
								}

							});
						}
					}

        });
        </script>";
      }

		/**
		 * Process the payment and return the result
		 *
		 * @param int $order_id
		 * @return array
		 */
		function process_payment($order_id){
    		$order = wc_get_order( $order_id );
        	return array('result' => 'success', 'redirect' => add_query_arg('order',
            	$order->id, add_query_arg('key', $order->order_key, get_permalink(get_option('woocommerce_pay_page_id'))))
        	);
    	}


    /**
     * Check and process server callback response
     *
     *
     */
    function check_zap_response() {
      global $woocommerce;
      if(isset($_REQUEST['ln_invoice']) && isset($_REQUEST['order_cb'])) {
        $orderId = $_REQUEST['order_cb'];
				$payReq = $_REQUEST['ln_invoice'];
        try {
          $order = new WC_Order( $orderId );

          if($order->status !== 'completed') {

            if($order->status == 'processing'){

            }
            else {

							$order->payment_complete();
              $order->add_order_note('Zap payment for invoice ' . $payReq . 'received.');
              $woocommerce->cart->empty_cart();
							echo 'success';
							exit;
            }
          }

					echo 'error';
					exit;
        }
        catch (Exception $e){
          echo 'error';
					exit;
        }
      }
    }

		function payment_fields(){
        	if($this -> description) echo wpautop(wptexturize($this -> description));
   		}

  } // end \WC_Gateway_Zap class
}
