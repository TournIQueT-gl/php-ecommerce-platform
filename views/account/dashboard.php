<?php
$h=static fn(mixed $v):string=>htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');
$last=$recentOrders[0]??null;
$status=$last?strtolower((string)($last['fulfillment_status']??'pending')):'';
$lastLabel=match($status){'preparing'=>store_t('Preparing'),'shipped'=>store_t('Shipped'),'out_for_delivery'=>store_t('Out for delivery'),'fulfilled'=>store_t('Delivered'),'returned'=>store_t('Returned'),default=>store_t('Order received')};
if(($last['status']??'')==='cancelled')$lastLabel=store_t('Cancelled');
$addressLine=$defaultAddress?trim(implode(', ',array_filter([(string)($defaultAddress['address_line1']??''),(string)($defaultAddress['city']??''),(string)($defaultAddress['governorate']??'')]))):'';
account_page_start('Dashboard','overview','MY ACCOUNT','Welcome back,',(string)($user['username']??'customer'),'Manage your orders, delivery addresses and favourites.',$user);
?>
<?php if($message):?><div class="app-alert dashboard-alert success" role="status"><?=store_h(store_public_message_t($message))?></div><?php endif;?>
<?php if($error):?><div class="app-alert dashboard-alert danger" role="alert"><?=store_h(store_public_message_t($error))?></div><?php endif;?>
<?php if(!empty($announcements)):?><section class="account-announcements account-announcements-compact" aria-label="<?=store_h(store_t('Store announcements'))?>"><?php foreach($announcements as $announcement):?><article class="app-panel dashboard-card account-announcement"><span class="status-badge status-active"><?=$h(strtoupper((string)($announcement['type']??'info')))?></span><div><strong><?=$h($announcement['title']??'')?></strong><p><?=nl2br($h($announcement['message']??''))?></p></div></article><?php endforeach;?></section><?php endif;?>
<section class="account-overview-v13" aria-label="<?=store_h(store_t('Account overview'))?>">
  <article class="app-panel dashboard-card account-order-spotlight">
    <div class="account-spotlight-heading"><div><p class="card-kicker"><?=store_h(store_t('YOUR LATEST ORDER'))?></p><h2><?=$last?store_t('Order').' '.$h($last['order_number']):store_h(store_t('Find something you’ll love?'))?></h2></div><?php if($last):?><span class="account-order-status<?=($last['status']??'')==='cancelled'?' is-cancelled':''?>"><?=$h($lastLabel)?></span><?php endif;?></div>
    <?php if($last):?>
      <div class="account-order-spotlight-meta"><span><small><?=store_h(store_t('PLACED'))?></small><strong><?=web_local_datetime($last['placed_at']??null,'—')?></strong></span><span><small><?=store_h(store_t('ORDER TOTAL'))?></small><strong><?=number_format((float)$last['grand_total'],2)?> <?=$h($last['currency'])?></strong></span><span><small><?=store_h(store_t('PAYMENT'))?></small><strong><?=$h(store_order_status_t((string)$last['payment_status']))?></strong></span></div>
      <a class="account-spotlight-link" href="<?=$h(route_url('account-orders.php'))?>"><?=store_h(store_t('View order details'))?> <span aria-hidden="true">→</span></a>
    <?php else:?>
      <p class="account-spotlight-empty"><?=store_h(store_t('Your orders will appear here once you check out.'))?></p>
      <a class="account-spotlight-link" href="<?=$h(route_url('perfumes.php'))?>"><?=store_h(store_t('Browse products'))?> <span aria-hidden="true">→</span></a>
    <?php endif;?>
  </article>
  <div class="account-overview-shortcuts">
    <a class="app-panel dashboard-card account-overview-tile" href="<?=$h(route_url('account-addresses.php'))?>"><span class="account-overview-tile-top"><b>01</b><span aria-hidden="true">↗</span></span><small><?=store_h(store_t('DELIVERY ADDRESS'))?></small><?php if($defaultAddress):?><strong><?=$h($defaultAddress['label']?:store_t('Default address'))?></strong><span class="account-overview-tile-description"><?=$h($addressLine)?></span><em><?=store_h(store_t('Edit your addresses'))?></em><?php else:?><strong><?=store_h(store_t('Add an address'))?></strong><span class="account-overview-tile-description"><?=store_h(store_t('Save your delivery details for faster checkout.'))?></span><em><?=store_h(store_t('Add delivery address'))?></em><?php endif;?></a>
    <?php if(store_module_enabled('wishlist')):?>
    <a class="app-panel dashboard-card account-overview-tile" href="<?=$h(route_url('wishlist.php'))?>"><span class="account-overview-tile-top"><b>02</b><span aria-hidden="true">↗</span></span><small><?=store_h(store_t('WISHLIST'))?></small><strong><?=number_format((int)$metrics['wishlist'])?> <?=store_h(store_t(((int)$metrics['wishlist']===1)?'saved product':'saved products'))?></strong><span class="account-overview-tile-description"><?=store_h(store_t('Your saved products, all in one place.'))?></span><em><?=store_h(store_t('View wishlist'))?></em></a><?php endif;?>
    <a class="app-panel dashboard-card account-overview-tile" href="<?=$h(route_url('account-email.php'))?>"><span class="account-overview-tile-top"><b>03</b><span aria-hidden="true">↗</span></span><small><?=store_h(store_t('PERSONAL DETAILS'))?></small><strong><?=store_h(store_t('My profile'))?></strong><span class="account-overview-tile-description"><?=store_h(store_t('Update your email and account information.'))?></span><em><?=store_h(store_t('Manage profile'))?></em></a>
  </div>
  <div class="account-overview-support"><span><?=store_h(store_t('Need assistance with an order?'))?></span><a href="<?=$h(route_url('support.php'))?>"><?=store_h(store_t('Contact support →'))?></a></div>
</section>
<?php account_page_end(); ?>
