<?php
/*
    Plugin Name: WooCommerce Lightning Gateway
    Plugin URI:  https://zap-wallet.me
    Description: Enable instant and fee-reduced payments in BTC and LTC through Lightning Network.
    Author:      João Almeida
    Author URI:  https://joaoalmeida.me

    Version:           0.1.0
    GitHub Plugin URI: https://github.com/joaodealmeida/woocommerce-gateway-zap
*/

if ( ! defined( 'ABSPATH' ) ) {
  exit;
}

register_activation_hook( __FILE__, function(){
  if (!extension_loaded('gd') || !extension_loaded('curl')) {
    die('The php-curl and php-gd extensions are required. Please contact your hosting provider for additional help.');
  }
});

require_once 'vendor/autoload.php';
require_once 'utilities.php';

define('LIGHTNING_LONGPOLL_TIMEOUT', min(120, max(5, ini_get('max_execution_time') * 0.8)));

if (!function_exists('init_wc_lightning')) {

  function init_wc_lightning() {
    if (!class_exists('WC_Payment_Gateway')) return;

    class WC_Gateway_Lightning extends WC_Payment_Gateway {

      public function __construct() {
        $this->id                 = 'lightning';
        $this->order_button_text  = __('Proceed to Lightning Payment', 'woocommerce');
        $this->method_title       = __('Lightning', 'woocommerce');
        $this->method_description = __('Lightning Network Payment');
        //$this->icon               = plugin_dir_url(__FILE__).'assets/img/icon.png';
        $this->supports           = array();       

        // Load the settings.
        $this->init_form_fields();
        $this->init_settings();

        // Define user set variables.
        $this->title       = $this->get_option('title');
        $this->description = $this->get_option('description');
        $this->endpoint = $this->get_option( 'endpoint' );
        $this->macaroon = $this->get_option( 'macaroon' );

        add_action('woocommerce_payment_gateways', array($this, 'register_gateway'));
        add_action('woocommerce_update_options_payment_gateways_lightning', array($this, 'process_admin_options'));
        add_action('woocommerce_receipt_lightning', array($this, 'show_payment_info'));
        add_action('woocommerce_thankyou_lightning', array($this, 'show_payment_info'));
        add_action('wp_ajax_ln_wait_invoice', array($this, 'wait_invoice'));
        add_action('wp_ajax_nopriv_ln_wait_invoice', array($this, 'wait_invoice'));
      }

      /**
       * Initialise Gateway Settings Form Fields.
       */
      public function init_form_fields() {
        $this->form_fields = array(
          'enabled' => array(
            'title'       => __( 'Enable/Disable', 'woocommerce-gateway-lightning' ),
            'label'       => __( 'Enable Lightning payments', 'woocommerce-gateway-lightning' ),
            'type'        => 'checkbox',
            'description' => '',
            'default'     => 'no',
          ),
          'title' => array(
            'title'       => __('Title', 'lightning'),
            'type'        => 'text',
            'description' => __('Controls the name of this payment method as displayed to the customer during checkout.', 'lightning'),
            'default'     => __('Bitcoin Lightning', 'lightning'),
            'desc_tip'    => true,
           ),
          'endpoint' => array(
            'title'       => __( 'Endpoint', 'lightning' ),
            'type'        => 'textarea',
            'description' => __( 'Place here the API endpoint', 'lightning' ),
            'default'     => 'http://localhost:3000/api',
            'desc_tip'    => true,
          ),
          'description' => array(
            'title'       => __('Customer Message', 'lightning'),
            'type'        => 'textarea',
            'description' => __('Message to explain how the customer will be paying for the purchase.', 'lightning'),
            'default'     => 'You will pay using the Lightning Network.',
            'desc_tip'    => true,
          ),
          'macaroon' => array(
            'title'       => __('Macaroon Hex', 'lightning'),
            'type'        => 'textarea',
            'description' => __('Input Macaroon Hex to get access to LND API', 'lightning'),
            'default'     => '',
            'desc_tip'    => true,
          ),
          
        );
      }

      /**
       * Process the payment and return the result.
       * @param  int $order_id
       * @return array
       */
      public function process_payment( $order_id ) {
        $order = wc_get_order($order_id);
        $usedCurrency = get_woocommerce_currency();
        $livePrice = getLivePrice();

        $invoiceInfo = array();
        $btcPrice = $order->get_total() * ((float)1/ $livePrice);
        $header = array('Grpc-Metadata-macaroon: ' . $this->macaroon , 'Content-type: application/json');
        
        $invoiceInfo['value'] = round($btcPrice * 100000000);
        $invoiceInfo['memo'] = "Order key: " . $order->get_checkout_order_received_url();
        $invoiceResponse = curlWrap($this->endpoint . '/v1/invoices', json_encode($invoiceInfo), 'POST', $header);
        $invoiceResponse = json_decode($invoiceResponse, true);    

        update_post_meta( $order->get_id(), 'LN_INVOICE', $invoiceResponse['payment_request'], true);
        $order->add_order_note("Awaiting payment of " . number_format((float)$btcPrice, 7, '.', '') . " BTC @ 1 BTC ~ " . $livePrice ." USD. <br> Invoice ID: " . $invoiceResponse['payment_request']);

        return array(
          'result'   => 'success',
          'redirect' => $order->get_checkout_payment_url(true)
        );
      }


      /**
       * JSON endpoint for long polling payment updates.
       */
      public function wait_invoice() {

        $order = wc_get_order($_POST['invoice_id']);         
        
        if($order->get_status() == 'processing'){
          status_header(200);
          wp_send_json(true);
          return;
        }
        /**
         * 
         * Check if invoice is paid
         */

        $payReq = get_post_meta( $_POST['invoice_id'], 'LN_INVOICE', true );
        $header = array('Grpc-Metadata-macaroon: ' . $this->macaroon , 'Content-type: application/json');
        $callResponse = json_decode( curlWrap($this->endpoint . '/v1/payreq/' . $payReq,'', "GET", $header), true );

        if(!isset($callResponse['payment_hash']) ) {
          status_header(410);
          wp_send_json(false);
          return;
        }
        
        $invoiceRep = json_decode( curlWrap($this->endpoint . '/v1/invoice/' . $callResponse['payment_hash'],'', "GET", $header), true );
        if(!isset($invoiceRep['settled'])){
          status_header(402);
          wp_send_json(false);
          return;
        }
        
        if ($invoiceRep['settled']) {
          $order->payment_complete();
          $order->add_order_note('Zap Payment has been received.');
          status_header(200);
          wp_send_json(true);
          return;
        }

      }

      /**
       * Hooks into the checkout page to display Lightning-related payment info.
       */
      public function show_payment_info($order_id) {
        global $wp;

        $order = wc_get_order($order_id);

        if (!empty($wp->query_vars['order-received']) && $order->needs_payment()) {
          // thankyou page requested, but order is still unpaid
          wp_redirect($order->get_checkout_payment_url(true));
          exit;
        }

        //Check if it's not paid
        //if ($order->has_status == 'unpaid') {
          //$invoice = $this->charge->fetch($invoice->id);
          //$this->update_invoice($order, $invoice);
        //}

        if ($order->has_status('cancelled')) {
          // invoice expired, reload page to display expiry message
          wp_redirect($order->get_checkout_payment_url(true));
          exit;
        }
        $header = array('Grpc-Metadata-macaroon: ' . $this->macaroon , 'Content-type: application/json');
        if ($order->needs_payment()) {
          $qr_uri = self::get_qr_uri( get_post_meta( $order_id, 'LN_INVOICE', true ) );

          $payReq = get_post_meta( $order->get_id(), 'LN_INVOICE', true );
          $callResponse = json_decode( curlWrap($this->endpoint . '/v1/payreq/' . $payReq,'', "GET", $header), true );
          require __DIR__.'/templates/payment.php';
        } elseif ($order->has_status(array('processing', 'completed'))) {
          require __DIR__.'/templates/completed.php';
        }
      }

      /**
       * Register as a WooCommerce gateway.
       */
      public function register_gateway($methods) {
        $methods[] = $this;
        return $methods;
      }

      protected static function get_qr_uri($invoice) {
        $renderer = new \BaconQrCode\Renderer\Image\Png;
        $renderer->setWidth(180);
        $renderer->setHeight(180);
        $renderer->setMargin(0);
        $writer = new \BaconQrCode\Writer($renderer);
        $image = $writer->writeString(strtoupper('lightning:' . $invoice));
        return 'data:image/png;base64,' . base64_encode($image);
      }

      protected static function format_msat($msat) {
        return rtrim(rtrim(number_format($msat/100000000, 8), '0'), '.') . ' BTC';
      }
    }

    new WC_Gateway_Lightning();
  }

  add_action('plugins_loaded', 'init_wc_lightning');
}
