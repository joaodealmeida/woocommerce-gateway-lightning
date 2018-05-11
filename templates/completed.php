<h2>Payment completed successfully</h2>
<?php
  $payHash = get_post_meta( $order->get_id(), 'LN_HASH', true );
  $invoiceRep = $this->lndCon->getInvoiceInfoFromHash( $payHash ); 
?>
<ul class="order_details">
  <li>
    Payment completed at: <strong><?php echo date('r', $invoiceRep->settle_date) ?></strong>
  </li>
  <li>
    Lightning rhash: <strong><?php echo $invoiceRep->r_hash ?></strong>
  </li>
  <li>
    Invoice amount: <strong><?php echo self::format_msat($invoiceRep->value, $this->lndCon->getCoin()) ?></strong>
  </li>
</ul>
