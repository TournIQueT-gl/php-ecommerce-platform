<?php
admin_page_start('Orders','orders','COMMERCE','Orders','','Review, search and update customer orders from a clean queue and dedicated order drawer.');
$statusClass=static fn(string $value):string=>'status-'.preg_replace('/[^a-z0-9_]+/','_',strtolower($value));
$queryParams=static function(array $extra=[])use($query,$statusFilter,$paymentFilter,$paymentMethodFilter,$fulfillmentFilter):string{
    $params=array_filter(['q'=>$query,'status'=>$statusFilter,'payment'=>$paymentFilter,'method'=>$paymentMethodFilter,'fulfillment'=>$fulfillmentFilter],static fn($v)=>$v!=='');
    foreach($extra as $k=>$v){if($v===null||$v==='')unset($params[$k]);else$params[$k]=$v;}
    return $params?'?'.http_build_query($params):'';
};
?>
<?php if($message):?><div class="app-alert admin-alert success"><?=htmlspecialchars($message,ENT_QUOTES,'UTF-8')?></div><?php endif;?>
<?php if($error):?><div class="app-alert admin-alert danger"><?=htmlspecialchars($error,ENT_QUOTES,'UTF-8')?></div><?php endif;?>
<section class="app-panel admin-panel admin-card-pad">
  <div class="admin-panel-head">
    <div><p class="panel-kicker">ORDER QUEUE</p><h2>Orders</h2><p class="admin-muted"><?=number_format((int)$total)?> matching order<?=((int)$total===1?'':'s')?>.</p></div>
    <span class="app-badge status-pill status-active"><i></i><?=number_format((int)$total)?> ORDERS</span>
  </div>
  <?php $orderFilterCount=($query!==''?1:0)+($statusFilter!==''?1:0)+($paymentFilter!==''?1:0)+($paymentMethodFilter!==''?1:0)+($fulfillmentFilter!==''?1:0);?><button class="admin-mobile-filter-trigger" type="button" data-admin-filter-open="order-filter-sheet">Filters<?=$orderFilterCount>0?' · '.$orderFilterCount:''?></button>
  <form id="order-filter-sheet" class="admin-search admin-order-filters admin-mobile-filter-sheet" data-admin-filter-sheet method="get" action="<?=htmlspecialchars(route_url('admin/orders.php'),ENT_QUOTES,'UTF-8')?>"><div class="admin-mobile-filter-head"><strong>Filter orders</strong><button type="button" data-admin-filter-close aria-label="Close filters">×</button></div>
    <input type="search" name="q" value="<?=htmlspecialchars($query,ENT_QUOTES,'UTF-8')?>" placeholder="Order number, customer, phone or email..." aria-label="Search orders">
    <select name="status" aria-label="Order status"><option value="">All order states</option><option value="in_progress" <?=$statusFilter==='in_progress'?'selected':''?>>In progress</option><?php foreach(['pending','confirmed','processing','completed','cancelled'] as $v):?><option value="<?=$v?>" <?=$statusFilter===$v?'selected':''?>><?=ucwords(str_replace('_',' ',$v))?></option><?php endforeach;?></select>
    <select name="payment" aria-label="Payment status"><option value="">All payments</option><?php foreach(['pending','paid','failed','refunded','partially_refunded'] as $v):?><option value="<?=$v?>" <?=$paymentFilter===$v?'selected':''?>><?=ucwords(str_replace('_',' ',$v))?></option><?php endforeach;?></select>
    <select name="method" aria-label="Payment method"><option value="">All methods</option><option value="cod" <?=$paymentMethodFilter==='cod'?'selected':''?>>Cash on Delivery</option><option value="manual_transfer" <?=$paymentMethodFilter==='manual_transfer'?'selected':''?>>Bank transfer / InstaPay</option></select>
    <select name="fulfillment" aria-label="Fulfillment status"><option value="">All fulfillment</option><?php foreach(['unfulfilled','preparing','shipped','out_for_delivery','fulfilled','returned'] as $v):?><option value="<?=$v?>" <?=$fulfillmentFilter===$v?'selected':''?>><?=ucwords(str_replace('_',' ',$v))?></option><?php endforeach;?></select>
    <button class="app-button admin-button secondary" type="submit">Filter</button>
    <?php if($query!==''||$statusFilter!==''||$paymentFilter!==''||$paymentMethodFilter!==''||$fulfillmentFilter!==''):?><a class="clear-search" href="<?=htmlspecialchars(route_url('admin/orders.php'),ENT_QUOTES,'UTF-8')?>">Clear</a><?php endif;?>
  </form>

  <?php if(!$ready):?>
    <p class="admin-muted">Apply the current commerce migrations first.</p>
  <?php elseif(!$orders):?>
    <div class="admin-empty-state"><strong>No orders found.</strong><p>Try a different filter or wait for the next checkout.</p></div>
  <?php else:?>
    <div class="admin-table-wrap admin-orders-table-wrap"><table class="admin-table admin-orders-table">
      <thead><tr><th>Order</th><th>Customer</th><th>Total</th><th>Order state</th><th>Payment</th><th>Fulfillment</th><th></th></tr></thead>
      <tbody><?php foreach($orders as $o):$oid=(int)$o['id'];$lineItems=$itemsByOrder[$oid]??[];?><tr>
        <td><strong><?=htmlspecialchars((string)$o['order_number'],ENT_QUOTES,'UTF-8')?></strong><small><?=web_local_datetime($o['placed_at']??null,'—')?> · <?=count($lineItems)?> item<?=count($lineItems)===1?'':'s'?></small></td>
        <td><strong><?=htmlspecialchars((string)$o['customer_name'],ENT_QUOTES,'UTF-8')?></strong><small><?=htmlspecialchars((string)$o['customer_phone'],ENT_QUOTES,'UTF-8')?><?php if(!empty($o['guest_email'])):?> · <?=htmlspecialchars((string)$o['guest_email'],ENT_QUOTES,'UTF-8')?><?php endif;?></small></td>
        <td><strong><?=number_format((float)$o['grand_total'],2)?> <?=htmlspecialchars((string)$o['currency'],ENT_QUOTES,'UTF-8')?></strong><small>Delivery <?=number_format((float)$o['shipping_total'],2)?></small></td>
        <td><span class="status-badge <?=$statusClass((string)$o['status'])?>"><?=htmlspecialchars(strtoupper((string)$o['status']),ENT_QUOTES,'UTF-8')?></span></td>
        <td><span class="status-badge <?=$statusClass((string)$o['payment_status'])?>"><?=htmlspecialchars(strtoupper(str_replace('_',' ',(string)$o['payment_status'])),ENT_QUOTES,'UTF-8')?></span><small><?=htmlspecialchars((string)($o['payment_method']??'cod')==='manual_transfer'?'Bank transfer / InstaPay':'Cash on Delivery',ENT_QUOTES,'UTF-8')?></small></td>
        <td><span class="status-badge <?=$statusClass((string)$o['fulfillment_status'])?>"><?=htmlspecialchars(strtoupper(str_replace('_',' ',(string)$o['fulfillment_status'])),ENT_QUOTES,'UTF-8')?></span></td>
        <td class="action-cell"><button class="app-button admin-button secondary small" type="button" data-order-drawer-open="order-drawer-<?=$oid?>">Manage</button></td>
      </tr><?php endforeach;?></tbody>
    </table></div>
    <div class="admin-mobile-card-list admin-order-mobile-list" aria-label="Orders">
      <?php foreach($orders as $o):$oid=(int)$o['id'];$lineItems=$itemsByOrder[$oid]??[];$method=(string)($o['payment_method']??'cod');?><article class="admin-mobile-record admin-mobile-order-card">
        <div class="admin-mobile-order-head"><div><strong><?=htmlspecialchars((string)$o['order_number'],ENT_QUOTES,'UTF-8')?></strong><small><?=web_local_datetime($o['placed_at']??null,'—')?> · <?=count($lineItems)?> item<?=count($lineItems)===1?'':'s'?></small></div><b><?=number_format((float)$o['grand_total'],0)?> <?=htmlspecialchars((string)$o['currency'],ENT_QUOTES,'UTF-8')?></b></div>
        <div class="admin-mobile-order-customer"><strong><?=htmlspecialchars((string)$o['customer_name'],ENT_QUOTES,'UTF-8')?></strong><small><?=htmlspecialchars((string)$o['customer_phone'],ENT_QUOTES,'UTF-8')?><?=!empty($o['guest_email'])?' · '.htmlspecialchars((string)$o['guest_email'],ENT_QUOTES,'UTF-8'):''?></small></div>
        <div class="admin-mobile-order-statuses"><span class="status-badge <?=$statusClass((string)$o['status'])?>"><?=htmlspecialchars(strtoupper((string)$o['status']),ENT_QUOTES,'UTF-8')?></span><span class="status-badge <?=$statusClass((string)$o['payment_status'])?>"><?=htmlspecialchars(strtoupper(str_replace('_',' ',(string)$o['payment_status'])),ENT_QUOTES,'UTF-8')?></span><span class="status-badge <?=$statusClass((string)$o['fulfillment_status'])?>"><?=htmlspecialchars(strtoupper(str_replace('_',' ',(string)$o['fulfillment_status'])),ENT_QUOTES,'UTF-8')?></span></div>
        <small class="admin-mobile-order-method"><?=$method==='manual_transfer'?'Bank transfer / InstaPay':'Cash on Delivery'?></small>
        <button class="app-button admin-button primary admin-mobile-order-open" type="button" data-order-drawer-open="order-drawer-<?=$oid?>">Open order</button>
      </article><?php endforeach;?>
    </div>
  <?php endif;?>

  <?php if($totalPages>1):?><nav class="admin-pagination" aria-label="Order pages"><?php for($i=max(1,$page-2);$i<=min($totalPages,$page+2);$i++):?><a class="<?=$i===$page?'is-current':''?>" href="<?=htmlspecialchars(route_url('admin/orders.php').$queryParams(['page'=>$i>1?$i:null]),ENT_QUOTES,'UTF-8')?>" <?=$i===$page?'aria-current="page"':''?>><?=$i?></a><?php endfor;?></nav><?php endif;?>
</section>

<?php foreach($orders as $o):
    $oid=(int)$o['id'];
    $address=json_decode((string)($o['shipping_address_json']??''),true);if(!is_array($address))$address=[];
    $lineItems=$itemsByOrder[$oid]??[];
    $addressLine=trim(implode(', ',array_filter([(string)($address['address']??''),(string)($address['building']??''),(string)($address['city']??''),(string)($address['governorate']??'')]))) ;
    $os=(string)$o['status'];$ps=(string)$o['payment_status'];$fs=(string)$o['fulfillment_status'];$paymentMethod=(string)($o['payment_method']??'cod');
    $waitingForTransfer=$paymentMethod==='manual_transfer'&&$ps!=='paid';
    $nextAction=null;$nextState=[];
    if(!in_array($os,['cancelled','completed'],true)&&$fs!=='returned'&&!$waitingForTransfer){
        if($os==='pending'&&$fs==='unfulfilled'){$nextAction='Confirm order';$nextState=['confirmed',$ps,'unfulfilled'];}
        elseif($fs==='unfulfilled'){$nextAction='Start preparing';$nextState=['processing',$ps,'preparing'];}
        elseif($fs==='preparing'){$nextAction='Mark shipped';$nextState=['processing',$ps,'shipped'];}
        elseif($fs==='shipped'){$nextAction='Out for delivery';$nextState=['processing',$ps,'out_for_delivery'];}
        elseif($fs==='out_for_delivery'){$nextAction=$paymentMethod==='cod'?'Mark delivered & paid':'Mark delivered';$nextState=['completed','paid','fulfilled'];}
    }
?>
<dialog class="admin-order-drawer" id="order-drawer-<?=$oid?>" aria-labelledby="order-title-<?=$oid?>">
  <div class="admin-order-drawer-head">
    <div><span>ORDER</span><h2 id="order-title-<?=$oid?>"><?=htmlspecialchars((string)$o['order_number'],ENT_QUOTES,'UTF-8')?></h2><small><?=web_local_datetime($o['placed_at']??null,'—')?></small></div>
    <button type="button" data-order-drawer-close aria-label="Close order details">×</button>
  </div>
  <div class="admin-order-drawer-body">
    <section class="admin-order-summary-grid">
      <div><span>Customer</span><strong><?=htmlspecialchars((string)$o['customer_name'],ENT_QUOTES,'UTF-8')?></strong><small><?=htmlspecialchars((string)$o['customer_phone'],ENT_QUOTES,'UTF-8')?><?=!empty($o['guest_email'])?' · '.htmlspecialchars((string)$o['guest_email'],ENT_QUOTES,'UTF-8'):''?></small></div>
      <div><span>Delivery</span><strong><?=htmlspecialchars($addressLine!==''?$addressLine:'No delivery address',ENT_QUOTES,'UTF-8')?></strong></div>
      <div><span>Total</span><strong><?=number_format((float)$o['grand_total'],2)?> <?=htmlspecialchars((string)$o['currency'],ENT_QUOTES,'UTF-8')?></strong><small>Shipping <?=number_format((float)$o['shipping_total'],2)?> · Discount <?=number_format((float)$o['discount_total'],2)?></small></div>
      <?php if(!empty($o['customer_note'])):?><div><span>Customer note</span><strong><?=htmlspecialchars((string)$o['customer_note'],ENT_QUOTES,'UTF-8')?></strong></div><?php endif;?>
    </section>

    <?php if($paymentMethod==='manual_transfer'):?><section class="admin-order-payment-alert <?=$ps==='paid'?'is-paid':'is-pending'?>"><div><span>PAYMENT METHOD</span><strong>Bank transfer / InstaPay</strong><small><?=$ps==='paid'?'Payment has been marked received.':'No payment was taken online. Contact the customer with transfer details, verify the funds, then use Mark transfer received before fulfillment.'?></small></div><?php if($ps!=='paid'):?><a class="app-button admin-button secondary small" href="tel:<?=htmlspecialchars(preg_replace('/[^0-9+]/','',(string)$o['customer_phone']),ENT_QUOTES,'UTF-8')?>">Call customer</a><?php endif;?></section><?php endif;?>

    <section class="admin-order-lines"><h3>Items</h3><?php foreach($lineItems as $item):$orderImage=store_image_path($item['image_path']??'');?><article>
      <div class="admin-order-line-media"><?php if($orderImage!==''):?><img data-admin-image src="<?=htmlspecialchars(url(store_image_variant_path($orderImage,'sm')),ENT_QUOTES,'UTF-8')?>" alt=""><span class="admin-order-image-fallback" data-admin-image-fallback hidden>NO IMAGE</span><?php else:?><span class="admin-order-image-fallback">NO IMAGE</span><?php endif;?></div>
      <span><strong><?=htmlspecialchars((string)$item['product_name'],ENT_QUOTES,'UTF-8')?></strong><small><?=htmlspecialchars((string)($item['variant_label']??''),ENT_QUOTES,'UTF-8')?> · Qty <?=(int)$item['quantity']?></small></span>
      <b><?=number_format((float)$item['line_total'],2)?> <?=htmlspecialchars((string)$o['currency'],ENT_QUOTES,'UTF-8')?></b>
    </article><?php endforeach;?></section>

    <form method="post" class="store-admin-order-form admin-order-update-form"
          data-current-order-status="<?=htmlspecialchars($os,ENT_QUOTES,'UTF-8')?>"
          data-current-payment-status="<?=htmlspecialchars($ps,ENT_QUOTES,'UTF-8')?>"
          data-current-fulfillment-status="<?=htmlspecialchars($fs,ENT_QUOTES,'UTF-8')?>"
          data-payment-method="<?=htmlspecialchars($paymentMethod,ENT_QUOTES,'UTF-8')?>">
      <?=csrf_field()?><input type="hidden" name="id" value="<?=$oid?>">
      <?php if($paymentMethod==='manual_transfer'&&in_array($ps,['pending','failed'],true)&&!in_array($os,['cancelled'],true)):?><div class="admin-order-next-action admin-order-payment-action"><span>PAYMENT</span><button class="app-button admin-button secondary" type="button" data-order-preset data-order-status="<?=htmlspecialchars($os,ENT_QUOTES,'UTF-8')?>" data-payment-status="paid" data-fulfillment-status="<?=htmlspecialchars($fs,ENT_QUOTES,'UTF-8')?>">Mark transfer received</button><small>Use only after the money is visible in the owner’s account. This does not capture a card payment.</small></div><?php endif;?>
      <?php if($nextAction):?><div class="admin-order-next-action" data-order-next-action><span>NEXT ACTION</span><button class="app-button admin-button primary" type="button" data-order-preset data-order-status="<?=htmlspecialchars($nextState[0],ENT_QUOTES,'UTF-8')?>" data-payment-status="<?=htmlspecialchars($paymentMethod==='manual_transfer'&&$ps==='paid'?'paid':$nextState[1],ENT_QUOTES,'UTF-8')?>" data-fulfillment-status="<?=htmlspecialchars($nextState[2],ENT_QUOTES,'UTF-8')?>"><?=htmlspecialchars($nextAction,ENT_QUOTES,'UTF-8')?></button><small>Apply the recommended next state, then save the order.</small></div><?php endif;?>

      <details class="admin-order-advanced">
        <summary>Advanced status controls</summary>
        <div class="admin-order-status-grid">
          <label><span>Order state</span><select name="status"><?php $editableOrderStates=['pending','confirmed','processing','completed'];if($ps!=='paid'||$os==='cancelled')$editableOrderStates[]='cancelled';foreach($editableOrderStates as $v):?><option value="<?=$v?>" <?=$os===$v?'selected':''?>><?=strtoupper($v)?></option><?php endforeach;?></select></label>
          <label><span>Payment</span><?php if(in_array($ps,['refunded','partially_refunded'],true)):?><input type="hidden" name="payment_status" value="<?=htmlspecialchars($ps,ENT_QUOTES,'UTF-8')?>"><select disabled aria-label="Payment status locked by refund workflow"><option selected><?=htmlspecialchars(strtoupper(str_replace('_',' ',$ps)),ENT_QUOTES,'UTF-8')?> · LOCKED</option></select><small>Further payment changes must use Returns & Refunds.</small><?php else:?><select name="payment_status"><?php foreach(['pending','paid','failed'] as $v):?><option value="<?=$v?>" <?=$ps===$v?'selected':''?>><?=strtoupper(str_replace('_',' ',$v))?></option><?php endforeach;?></select><?php endif;?></label>
          <label><span>Fulfillment</span><select name="fulfillment_status"><?php foreach(['unfulfilled','preparing','shipped','out_for_delivery','fulfilled'] as $v):?><option value="<?=$v?>" <?=$fs===$v?'selected':''?>><?=strtoupper(str_replace('_',' ',$v))?></option><?php endforeach;?><?php if($fs==='returned'):?><option value="returned" selected>RETURNED</option><?php endif;?></select></label>
        </div>
        <p class="admin-order-state-guidance" data-order-state-guidance></p>
      </details>

      <label class="admin-order-note"><span>Internal note</span><textarea name="admin_note" rows="3" placeholder="Visible only to admins"><?=htmlspecialchars((string)($o['admin_note']??''),ENT_QUOTES,'UTF-8')?></textarea></label>
      <button class="app-button admin-button primary" type="submit">Save order</button>
    </form>
  </div>
</dialog>
<?php endforeach;?>
<?php admin_page_end(); ?>
