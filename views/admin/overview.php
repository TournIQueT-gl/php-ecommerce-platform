<?php $storeCurrency=strtoupper((string)(store_runtime_settings()['currency']??'EGP'));admin_page_start('Dashboard','overview','STORE CONTROL','Today','','What needs attention now, without turning the dashboard into another admin menu.'); ?>
<?php if(!empty($isSupport)):?>
<section class="admin-attention-grid admin-attention-grid-simple"><a class="admin-attention-card <?=$openSupport>0?'is-alert':''?>" href="<?=htmlspecialchars(route_url('admin/support.php'),ENT_QUOTES,'UTF-8')?>"><strong><?=number_format($openSupport)?></strong><span>support conversations</span></a><a class="admin-attention-card" href="<?=htmlspecialchars(route_url('admin/customers.php'),ENT_QUOTES,'UTF-8')?>"><strong><?=number_format((int)$metrics['customers'])?></strong><span>active customers</span></a></section>
<section class="app-panel admin-panel admin-card-pad admin-section-space"><p class="panel-kicker">SUPPORT WORKSPACE</p><h2>Customer service</h2><p class="admin-muted">Use the support queue and customer search. Financial, inventory and system controls remain restricted.</p></section>
<?php admin_page_end();return;endif;?>
<?php if(!$commerceReady):?><div class="app-alert admin-alert warning">Commerce schema is not installed yet. Run the current database migrations before using the store dashboard.</div><?php endif;?>
<?php if((int)$metrics['active_products']===0):?><div class="app-alert admin-alert warning admin-empty-catalog-cta"><strong>Your catalog is empty.</strong> <a href="<?=htmlspecialchars(route_url('admin/catalog.php'),ENT_QUOTES,'UTF-8')?>">Add your first product →</a></div><?php endif;?>
<section class="app-panel admin-panel admin-card-pad admin-dashboard-focus">
  <div class="panel-heading"><div><p class="panel-kicker">NEEDS ATTENTION</p><h2>Store queue</h2></div><small class="admin-muted">Only operational items that may need action.</small></div>
  <div class="admin-attention-grid admin-attention-grid-simple">
    <a class="admin-attention-card <?=$metrics['pending_orders']>0?'is-alert':''?>" href="<?=htmlspecialchars(route_url('admin/orders.php').'?status=in_progress',ENT_QUOTES,'UTF-8')?>"><strong><?=number_format((int)$metrics['pending_orders'])?></strong><span>orders in progress</span></a>
    <a class="admin-attention-card <?=$metrics['pending_returns']>0?'is-alert':''?>" href="<?=htmlspecialchars(route_url('admin/returns.php'),ENT_QUOTES,'UTF-8')?>"><strong><?=number_format((int)$metrics['pending_returns'])?></strong><span>returns to review</span></a>
    <a class="admin-attention-card <?=$metrics['low_stock']>0?'is-alert':''?>" href="<?=htmlspecialchars(route_url('admin/inventory.php').'?stock=low',ENT_QUOTES,'UTF-8')?>"><strong><?=number_format((int)$metrics['low_stock'])?></strong><span>sellable low-stock sizes</span></a>
    <a class="admin-attention-card <?=$openSupport>0?'is-alert':''?>" href="<?=htmlspecialchars(route_url('admin/support.php'),ENT_QUOTES,'UTF-8')?>"><strong><?=number_format($openSupport)?></strong><span>support conversations</span></a>
  </div>
</section>
<section class="store-admin-metrics store-admin-metrics-primary admin-dashboard-metrics-simple">
  <article><span>30-DAY REVENUE</span><strong><?=number_format((float)$metrics['revenue_30d'],0)?> <small><?=htmlspecialchars($storeCurrency,ENT_QUOTES,'UTF-8')?></small></strong><i>Paid orders only</i></article>
  <article><span>ORDERS · 30 DAYS</span><strong><?=number_format((int)$metrics['orders_30d'])?></strong><i><?=number_format((int)$metrics['pending_orders'])?> currently in progress</i></article>
</section>
<section class="app-panel admin-panel admin-card-pad admin-section-space">
  <div class="panel-heading"><div><p class="panel-kicker">RECENT ORDERS</p><h2>Latest activity</h2></div><a class="panel-link" href="<?=htmlspecialchars(route_url('admin/orders.php'),ENT_QUOTES,'UTF-8')?>">View all orders →</a></div>
  <?php if(!$recentOrders):?><p class="admin-muted">No orders yet.</p><?php else:?><div class="admin-dashboard-order-list"><?php foreach($recentOrders as $o):?><a href="<?=htmlspecialchars(route_url('admin/orders.php').'?q='.rawurlencode((string)$o['order_number']),ENT_QUOTES,'UTF-8')?>"><span><strong><?=htmlspecialchars((string)$o['order_number'],ENT_QUOTES,'UTF-8')?></strong><small><?=htmlspecialchars((string)$o['customer_name'],ENT_QUOTES,'UTF-8')?> · <?=web_local_datetime($o['placed_at']??null,'—')?></small></span><span><b><?=number_format((float)$o['grand_total'],0)?> <?=htmlspecialchars((string)$o['currency'],ENT_QUOTES,'UTF-8')?></b><small><?=htmlspecialchars(ucwords(str_replace('_',' ',(string)$o['fulfillment_status'])),ENT_QUOTES,'UTF-8')?></small></span></a><?php endforeach;?></div><?php endif;?>
</section>
<?php admin_page_end(); ?>
