<h2>Payment completed successfully</h2>
<?php
  $payReq = get_post_meta( $order->get_id(), 'LN_INVOICE', true );
  $callResponse = $this->lndCon->getInvoiceInfoFromPayReq( $payReq );
  $invoiceRep = $this->lndCon->getInvoiceInfoFromHash( $callResponse->payment_hash ); 
?>
<ul class="order_details">
  <li>
    Payment completed at: <strong><?php echo date('r', $invoiceRep->settle_date) ?></strong>
  </li>
  <li>
    Lightning rhash: <strong><?php echo $invoiceRep->r_hash ?></strong>
  </li>
  <li>
    Invoice amount: <strong><?php echo self::format_msat($invoiceRep->value) ?></strong>
  </li>
</ul>
