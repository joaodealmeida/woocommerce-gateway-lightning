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
			$this->method_description = __( 'Allows instant and fee-reduced payments through Zap Wallet.', 'wc-gateway-zap' );

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

				try {
					$usedCurrency = get_woocommerce_currency();
					$livePrice = 6400;


					$invoiceInfo = array();
					$btcPrice = $order->order_total * ((float)1/ $livePrice);
					$invoiceInfo['value'] = $btcPrice * (float)100000000;
					$invoiceInfo['memo'] = "Order key: " . $order->get_checkout_order_received_url();

					$invoiceResponse = wp_remote_post ($this->endpoint. '/api/addinvoice',
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

					if (is_wp_error($response)) {
						throw new Exception($response->get_error_message());
					}

					$invoiceResponse = json_decode($invoiceResponse['body'], true)['data'];
					update_post_meta( $order->id, 'LN_INVOICE', $invoiceResponse['payment_request'] );
					$order->add_order_note("Awaiting payment of " . number_format((float)$btcPrice, 7, '.', '') . " BTC @ 1 BTC ~ " . $livePrice ." USD. <br> Invoice ID: " . $invoiceResponse['payment_request']);

				}
				catch (Exception $e) {
					//Do something here
				}

				$html = "<div id='zap-div'><p>Amount: " . number_format((float)$btcPrice, 7, '.', '') . " BTC <br><p>Waiting Payment</p><a href= 'lightning:" . $invoiceResponse['payment_request'] . "'><img src='data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAcQAAAB4CAYAAABsIIKlAAAABGdBTUEAALGPC/xhBQAAH3RJREFUeAHtnQWY3MT7x+cqtHihuBZ3dz/cvbgWd5eibXEoxd21OMXd3d1dCsWtSKEt97tP+M/9J3PJbjab3c1dvu/z7MUmI99c8s6r02RaqaWlZbpRo0ad0rrb3PqbuvUnEgJCQAgIASHQ2REY0TrAx3r27HlIU1PT8Kb/Y4avt56ctLOPXOMTAkJACAgBIRCBwE+tTHGBpr/++mto68UtIgrolBAQAkJACAiBoiBwHQzx69bRSk1alEeucQoBISAEhEAUAiNgiC1RV3ROCAgBISAEhECREOhSpMFqrEJACAgBISAE4hAQQ4xDRueFgBAQAkKgUAiIIRbqcWuwQkAICAEhEIeAGGIcMjovBISAEBAChUJADLFQj1uDFQJCQAgIgTgExBDjkNF5ISAEhIAQKBQCYoiFetwarBAQAkJACMQhIIYYh4zOCwEhIASEQKEQEEMs1OPWYIWAEBACQiAOATHEOGR0XggIASEgBAqFgBhioR63BisEhIAQEAJxCIghxiGj80JACAgBIVAoBMQQC/W4NVghIASEgBCIQ0AMMQ4ZnRcCQkAICIFCISCGWKjHrcEKASEgBIRAHAJiiHHI6LwQEAJCQAgUCgExxEI9bg1WCAgBISAE4hAQQ4xDRueFgBAQAkKgUAiIIRbqcWuwQkAICAEhEIeAGGIcMjovBISAEBAChUJADLFQj1uDFQJCQAgIgTgExBDjkNF5ISAEhIAQKBQCYoiFetwarBAQAkJACMQhIIYYh4zOCwEhIASEQKEQEEMs1OPWYIWAEBACQiAOATHEOGR0XggIASEgBAqFgBhioR63BisEhIAQEAJxCIghxiGj80JACAgBIVAoBMQQC/W4NVghIASEgBCIQ0AMMQ4ZnRcCQkAICIFCISCGWKjHrcEKASEgBIRAHAJiiHHI6LwQEAJCQAgUCgExxEI9bg1WCAgBISAE4hAQQ4xDRueFgBAQAkKgUAiIIRbqcWuwQkAICAEhEIeAGGIcMjovBISAEBAChUJADLFQj1uDFQJCQAgIgTgEusVd0HkhIATqh8A333xjXnjhheD38ccfm59++in4NTU1mUkmmcT07t3bzDLLLGbxxRcPflNOOWX9OqeWhEBBEGj666+/WgoyVg1TCOQKgZaWFvP444+bq666yrz44osV9W2JJZYw2267rVl++eUruk+FhYAQiEdADDEeG10RAjVD4JVXXjGDBg0yn3zySVVtzDrrrGbgwIFmgQUWqKoe3SwEhIAxYoj6LxACdUTg77//NkOGDDHXXXddZq2iVt1qq63M/vvvb8YZZ5zM6lVFQqBoCIghFu2Ja7wNQ2DkyJFmzz33NK+++mpkHxZeeGGz1FJLmUUXXdRMM800ZtJJJzWoVX/++Wfz9ddfB/bFZ5991rz22muR9y+22GLmrLPOMhNMMEHkdZ0UAkKgNAJiiKXx0VUhkAkCP/74o9lpp53MRx991K6+9ddf32y//fYG9WcS+uCDD8wVV1xh7rzzznbF55xzTnPhhRcGzLTdRZ0QAkKgJAJiiCXh0UUhUD0CY8aMMTvssEM7ybBPnz7mmGOOMQsttFCqRnDEGTBggPnyyy9D9yMpXnzxxaZr166h87U++Pzzz82IESNq3YyZeOKJzVxzzVVRO1999ZV54oknzMsvv2yw3+LV+9tvv5lff/3VdOvWzcwwwwxmxhlnbNsysVhrrbVSY0jd77zzTqiP8803nxl//PFD5+xBq3OjufHGGw0aAJ6n/aEOn2qqqQKNAfdvuOGGZtlllzVduihizmKX5VYMMUs0VZcQiEDg+OOPN9dff33oCuETqDfjPpChwiUOfv/9d7P33nubl156KVQKD9SDDz44dK7WB4cddpg544wzat2MaW5uNvfee2+idh566CFzwQUXBOX//fffRPfYQjPNNFOA4dZbb226d+9uTyfa4j28xhprhMo++eSTgTrcPckkAokeiR/VeBKaYoopzO677x7YjHv06JHkFpVJiICmGQmBUjEhkAYBYgt9ZrjMMsuY888/v2pmSH+wF/JBXXLJJUPdI5QDSaioBHNhUrDuuuuau+++21TKDMHt008/NXvssYeZe+65zaWXXpo5lDfccINZcMEFzemnn56YGdKJ7777LvBQXmSRRQwMX5QdAgrMT4kl/8h//vlnybtxikD1wm/ttdc2qDyKSJtvvnnwcb755pvN/PPPnxgCVIJ4T/Kx5wPfEenUU08NdXv66ac3gwcPztQbFM9S2tl0000D5xvbIN6s1157rT0szBapa9VVV22nSrYAoIbEaYlngaoUlekXX3wR/FCtjh071hYNtsOH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' border='0'></a></div> <div id='zap-success' style='display:none'><p> Transaction complete </p> <img src='data:image/svg+xml;utf8;base64,PD94bWwgdmVyc2lvbj0iMS4wIiBlbmNvZGluZz0iaXNvLTg4NTktMSI/Pgo8IS0tIEdlbmVyYXRvcjogQWRvYmUgSWxsdXN0cmF0b3IgMTkuMC4wLCBTVkcgRXhwb3J0IFBsdWctSW4gLiBTVkcgVmVyc2lvbjogNi4wMCBCdWlsZCAwKSAgLS0+CjxzdmcgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIiB4bWxuczp4bGluaz0iaHR0cDovL3d3dy53My5vcmcvMTk5OS94bGluayIgdmVyc2lvbj0iMS4xIiBpZD0iTGF5ZXJfMSIgeD0iMHB4IiB5PSIwcHgiIHZpZXdCb3g9IjAgMCA0MjYuNjY3IDQyNi42NjciIHN0eWxlPSJlbmFibGUtYmFja2dyb3VuZDpuZXcgMCAwIDQyNi42NjcgNDI2LjY2NzsiIHhtbDpzcGFjZT0icHJlc2VydmUiIHdpZHRoPSI1MTJweCIgaGVpZ2h0PSI1MTJweCI+CjxwYXRoIHN0eWxlPSJmaWxsOiM2QUMyNTk7IiBkPSJNMjEzLjMzMywwQzk1LjUxOCwwLDAsOTUuNTE0LDAsMjEzLjMzM3M5NS41MTgsMjEzLjMzMywyMTMuMzMzLDIxMy4zMzMgIGMxMTcuODI4LDAsMjEzLjMzMy05NS41MTQsMjEzLjMzMy0yMTMuMzMzUzMzMS4xNTcsMCwyMTMuMzMzLDB6IE0xNzQuMTk5LDMyMi45MThsLTkzLjkzNS05My45MzFsMzEuMzA5LTMxLjMwOWw2Mi42MjYsNjIuNjIyICBsMTQwLjg5NC0xNDAuODk4bDMxLjMwOSwzMS4zMDlMMTc0LjE5OSwzMjIuOTE4eiIvPgo8Zz4KPC9nPgo8Zz4KPC9nPgo8Zz4KPC9nPgo8Zz4KPC9nPgo8Zz4KPC9nPgo8Zz4KPC9nPgo8Zz4KPC9nPgo8Zz4KPC9nPgo8Zz4KPC9nPgo8Zz4KPC9nPgo8Zz4KPC9nPgo8Zz4KPC9nPgo8Zz4KPC9nPgo8Zz4KPC9nPgo8Zz4KPC9nPgo8L3N2Zz4K' width='60' height='60'/> </div>";

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

					var web = new WebSocket('wss://" . preg_replace('#^https?://#', '', $this->endpoint) . "');
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
							// TODO - Send Request to API to verify if settled is true
							if(get_post_meta( $order->id, 'LN_INVOICE', true ) === $payReq) {

								$order->payment_complete();
								$order->add_order_note('Zap Payment has been received.');
								$woocommerce->cart->empty_cart();
								echo 'success';
								exit;

							}
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
