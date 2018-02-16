<?php
require_once 'vendor/autoload.php';

class LightningChargeClientTest extends \PHPUnit\Framework\TestCase {

  public function test_create_invoice(){
    $charge = new LightningChargeClient(getenv('STRIKE_URL'));
    $invoice = $charge->invoice([ 'msatoshi'  => 50, 'metadata' => [ 'customer' => 'Satoshi', 'products' => [ 'potato', 'chips' ] ] ]);

    $this->assertObjectHasAttribute('id', $invoice);
    $this->assertObjectHasAttribute('rhash', $invoice);
    $this->assertObjectHasAttribute('payreq', $invoice);
    $this->assertEquals('50', $invoice->msatoshi);
    $this->assertEquals('Satoshi', $invoice->metadata->customer);
    $this->assertEquals('chips', $invoice->metadata->products[1]);
  }

  public function test_fetch_invoice(){
    $charge = new LightningChargeClient(getenv('STRIKE_URL'));
    $saved = $charge->invoice( [ 'msatoshi' => 50, 'metadata' => 'test_fetch_invoice' ]);
    $loaded = $charge->fetch($saved->id);

    $this->assertEquals($saved->id, $loaded->id);
    $this->assertEquals($saved->rhash, $loaded->rhash);
    $this->assertEquals($loaded->metadata, 'test_fetch_invoice');
    $this->assertEquals($loaded->msatoshi, '50');
  }

  public function test_register_webhook(){
    $charge = new LightningChargeClient(getenv('STRIKE_URL'));
    $invoice = $charge->invoice([ 'msatoshi' => 50 ]);
    $this->assertTrue($charge->registerHook($invoice->id, 'http://example.com/'));
  }
}
