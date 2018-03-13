<?php

class LndWrapper
{

    private $macaroonHex;
    private $endpoint;
    private $pubKey;
    private $privKey;
    private $coin = 'BTC';


    /**
     * Call this method to get singleton
     */
    public static function instance()
    {
      static $instance = false;
      if( $instance === false )
      {
        // Late static binding (PHP 5.3+)
        $instance = new static();
      }

      return $instance;
    }

    /**
     * Make constructor private, so nobody can call "new Class".
     */
    private function __construct() {
    }

    /**
     * Make clone magic method private, so nobody can clone instance.
     */
    private function __clone() {}

    /**
     * Make sleep magic method private, so nobody can serialize instance.
     */
    private function __sleep() {}

    /**
     * Make wakeup magic method private, so nobody can unserialize instance.
     */
    private function __wakeup() {}

    /**
     * Custom function to make curl requests
     */
    private function curlWrap( $url, $json, $action, $headers ) {
        $ch			=			curl_init();
        
        curl_setopt($ch, CURLOPT_URL, $url);
        
        switch($action){
            case "POST":
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
                curl_setopt($ch, CURLOPT_POSTFIELDS, $json);
                break;
            case "GET":
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "GET");
                break;
            case "PUT":
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PUT");
                curl_setopt($ch, CURLOPT_POSTFIELDS, $json);
                break;
            case "DELETE":
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "DELETE");
                break;
            default:
                break;
            }
        
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            //This is set to 0 for development mode. Set 1 when production (self-signed certificate error)
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 1);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        
        
            $output = curl_exec($ch);
        
            curl_close($ch);
            return $output;
    }

    /**
     * Set endpoint credentials
     */
    public function setCredentials ( $endpoint , $macaroonHex ){
        $this->endpoint = $endpoint;
        $this->macaroonHex = $macaroonHex;
    }

    public function setCoin ( $coin ){
        $this->coin = $coin;
    }

    public function getCoin () {
        return $this->coin;
    }

    public function generateQr( $paymentRequest ){
        $size = "150x150";
        $encoding = "UTF-8";
        return 'https://chart.googleapis.com/chart?cht=qr' . '&chs=' . $size . '&chl=' . $paymentRequest . '&choe=' . $encoding;
    }

    /**
     * Generate Payment Request
     */
    public function createInvoice ( $invoice ) {
        $header = array('Grpc-Metadata-macaroon: ' . $this->macaroonHex , 'Content-type: application/json');
        $createInvoiceResponse = $this->curlWrap( $this->endpoint . '/v1/invoices', json_encode( $invoice ), 'POST', $header );
        $createInvoiceResponse = json_decode($createInvoiceResponse);
        return $createInvoiceResponse;
    }
    
    public function getInvoiceInfoFromPayReq ($paymentRequest) {
        $header = array('Grpc-Metadata-macaroon: ' . $this->macaroonHex , 'Content-type: application/json');
        $invoiceInfoResponse = $this->curlWrap( $this->endpoint . '/v1/payreq/' . $paymentRequest,'', "GET", $header );
        $invoiceInfoResponse = json_decode( $invoiceInfoResponse );
        return $invoiceInfoResponse;
    }

    public function getInvoiceInfoFromHash ( $paymentHash ) {
        $header = array('Grpc-Metadata-macaroon: ' . $this->macaroonHex , 'Content-type: application/json');
        $invoiceInfoResponse = $this->curlWrap( $this->endpoint . '/v1/invoice/' . $paymentHash,'', "GET", $header );
        $invoiceInfoResponse = json_decode( $invoiceInfoResponse );
        return $invoiceInfoResponse;      
    }

    public function getLivePrice() {
        $secretKey = 'NDI4Zjc1ZDk2MzExNDMyYmFiZGNkNzEwNWQwMzBhZTc5OTViMGZhNTM1OWY0MDFjODhhYTlmODFiMjEwNzkwMQ';
        $publicKey = 'MzNjZmYxMTU0ODEwNDY0YTg5NjI4MDYzNjlkMzNkYjI';
        $timestamp = time();
        $payload = $timestamp . '.' . $publicKey;
        $hash = hash_hmac('sha256', $payload, $secretKey, true);
        $keys = unpack('H*', $hash);
        $hexHash = array_shift($keys);
        $signature = $payload . '.' . $hexHash;
        $ticker = "BTCUSD";
        if($this->coin === 'LTC'){
            $ticker = 'LTCUSD';
        }
        $tickerUrl = "https://apiv2.bitcoinaverage.com/indices/global/ticker/" . $ticker;
        $aHTTP = array(
          'http' =>
            array(
            'method'  => 'GET',
              )
        );
        $aHTTP['http']['header']  = "X-Signature: " . $signature;
        $context = stream_context_create($aHTTP);
        $content = file_get_contents($tickerUrl, false, $context);
    
        return json_decode($content, true)['ask'];
    }

}