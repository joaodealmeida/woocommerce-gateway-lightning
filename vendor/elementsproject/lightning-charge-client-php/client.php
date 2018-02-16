<?php
class LightningChargeClient {
  protected $api;

  public function __construct($url, $api_token=null) {
    $this->api = new RestClient([
      'base_url' => rtrim($url, '/'),
      'curl_options' => $api_token ? [
        CURLOPT_USERPWD => 'api-token:' . $api_token
      ] : []
    ]);
  }

  /**
   * Create a new invoice.
   *
   * @param string|int $msatoshi
   * @param object $metadata
   * @return object the invoice
   */
  public function invoice($props) {
    $res = $this->api->post('/invoice', json_encode($props), [ 'Content-Type' => 'application/json' ]);

    if ($res->info->http_code !== 201) throw new Exception('saving invoice failed');

    return $res->decode_response();
  }

  /**
   * Fetch invoice by ID
   *
   * @param string $invoice_id
   * @return object the invoice
   */
  public function fetch($invoice_id) {
    $res = $this->api->get('/invoice/' . urlencode($invoice_id));
    if ($res->info->http_code !== 200) throw new Exception('fetching invoice failed');
    return $res->decode_response();
  }

  /**
   * Wait for an invoice to be paid.
   *
   * @param string $invoice_id
   * @param int $timeout the timeout in seconds
   * @return object|bool|null the paid invoice if paid, false if the invoice expired, or null if the timeout is reached.
   */
  public function wait($invoice_id, $timeout) {
    $res = $this->api->get('/invoice/' . urlencode($invoice_id) . '/wait?timeout=' . (int)$timeout);

    // 402 Payment Required: timeout reached without payment, invoice is still payable
    if ($res->info->http_code === 402)
      return null;
    // 410 Gone: invoice expired and can not longer be paid
    else if ($res->info->http_code === 410)
      return false;
    // 200 OK: invoice is paid, returns the updated invoice
    else if ($res->info->http_code === 200)
      return $res->decode_response();
    else
      throw new Exception('invalid response');
  }

  /**
   * Register a new webhook.
   *
   * @param string $invoice_id
   * @param string $url
   * @return bool
   */
  public function registerHook($invoice_id, $url) {
    $res = $this->api->post('/invoice/' . urlencode($invoice_id) . '/webhook', [ 'url' => $url ]);
    if ($res->info->http_code !== 201)
      throw new Exception('register hook failed');
    return true;
  }
}
