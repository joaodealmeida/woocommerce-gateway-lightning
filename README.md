# WooCommerce Plugin for Bitcoin Lightning

Plugin to accept Bitcoin Lightning payments at [WooCommerce](https://woocommerce.com) stores,
using [LND](https://github.com/lightningnetwork/lnd).

## Installation

Requires PHP >= 5.6 and the `php-curl` and `php-gd` extensions.

1. Setup a [LND] node (https://github.com/lightningnetwork/lnd).

2. Get the Node server IP and macaroon key

3. Install and enable the plugin on your WordPress installation.

4. Under the WordPress administration panel, go to `WooCommerce -> Settings -> Checkout -> Lightning` and set LND endpoint and macaroon key.

The payment option should now be available in your checkout page.

## License

MIT
