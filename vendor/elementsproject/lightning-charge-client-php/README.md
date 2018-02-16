# lightning-charge-client-php

PHP client for the Lightning Charge REST API.

## Install

```bash
$ composer require elementsproject/lightning-charge-client-php
```

## Use

```php
<?php
// Initialize client
$charge = new LightingChargeClient('http://localhost:8009', '[TOKEN]');
// alternatively, the token can be provided as part of the URL:
$charge = new LightingChargeClient('http://api-token:[TOKEN]@localhost:8009');

// Create invoice
$invoice = $charge->invoice([ 'msatoshi' => 50, 'metadata' => [ 'customer' => 'Satoshi', 'products' => [ 'potato', 'chips' ] ] ]);

tell_user("to pay, send $invoice->msatoshi milli-satoshis with rhash $invoice->rhash, or copy the BOLT11 payment request: $invoice->payreq");

// Fetch invoice by id
$invoice = $charge->fetch('m51vlVWuIKGumTLbJ1RPb');

// Create invoice denominated in USD
$invoice = $charge->invoice([ 'currency' => 'USD', 'amount' => 0.15 ]);
```

TODO: document missing methods

## Test

```bash
$ STRIKE_URL=http://api-token:[TOKEN]@localhost:8009 phpunit test
```

## License
MIT
