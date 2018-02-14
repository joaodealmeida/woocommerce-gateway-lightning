<?php

function getLivePrice() {
    $secretKey = 'NDI4Zjc1ZDk2MzExNDMyYmFiZGNkNzEwNWQwMzBhZTc5OTViMGZhNTM1OWY0MDFjODhhYTlmODFiMjEwNzkwMQ';
    $publicKey = 'MzNjZmYxMTU0ODEwNDY0YTg5NjI4MDYzNjlkMzNkYjI';
    $timestamp = time();
    $payload = $timestamp . '.' . $publicKey;
    $hash = hash_hmac('sha256', $payload, $secretKey, true);
    $keys = unpack('H*', $hash);
    $hexHash = array_shift($keys);
    $signature = $payload . '.' . $hexHash;
    $tickerUrl = "https://apiv2.bitcoinaverage.com/indices/global/ticker/BTCUSD"; // request URL
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

function curlWrap($url, $json, $action, $headers) {
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
      curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
      curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);


      $output = curl_exec($ch);

      curl_close($ch);
      return $output;
}
?>