# WooCommerce Plugin for Bitcoin Lightning

Plugin to accept Bitcoin Lightning payments at [WooCommerce](https://woocommerce.com) stores,
using [LND](https://github.com/lightningnetwork/lnd).

## Installation

Requires PHP >= 5.6 and the `php-curl` and `php-gd` extensions.

1. Setup a [LND] node (https://github.com/lightningnetwork/lnd), Raspberry Pi set-up (https://stadicus.github.io/RaspiBolt/)

2. Get the Node server IP and macaroon key

3. Install and enable the plugin on your WordPress installation.

4. Under the WordPress administration panel, go to `WooCommerce -> Settings -> Checkout -> Lightning` and set LND endpoint and macaroon key.

5. Add LND tls.cert to plugin-folder/tls or input the exact path on plugin settings.

6. Add BitcoinAverage.com API public key to authenticate live price reliably (https://pro.bitcoinaverage.com/pages/auth/api-keys)


The payment option should now be available in your checkout page.

## License

MIT
