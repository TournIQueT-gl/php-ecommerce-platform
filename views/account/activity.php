<?php account_page_start('Account Activity','security','ACCOUNT HISTORY','ACTIVITY','TIMELINE.','Orders, security events and account updates in one timeline.',$user); ?>
<section class="activity-page-grid">
  <article class="app-panel dashboard-card tool-card">
    <div class="activity-section-head"><div><p class="card-kicker"><?=store_h(store_t('ORDER HISTORY'))?></p><h2><?=store_h(store_t('Recent orders'))?></h2></div><span><?=count($orders)?> <?=store_h(store_t('shown'))?></span></div>
    <div class="activity-timeline"><?php if(!$orders):?><p class="tool-empty"><?=store_h(store_t('No orders yet. Your purchases will appear here.'))?></p><?php else:foreach($orders as $item):?><article class="activity-item"><i></i><div><div class="activity-head"><strong><?=store_h(store_t('Order'))?> <?=htmlspecialchars((string)$item['order_number'],ENT_QUOTES,'UTF-8')?> · <?=htmlspecialchars(store_order_status_t((string)$item['status']),ENT_QUOTES,'UTF-8')?></strong><?=web_local_datetime($item['placed_at']??null,store_t('Unknown'))?></div><p><?=htmlspecialchars(store_order_status_t((string)$item['payment_status']),ENT_QUOTES,'UTF-8')?> · <?=htmlspecialchars((string)$item['currency'],ENT_QUOTES,'UTF-8')?> <?=number_format((float)$item['grand_total'],2)?></p></div></article><?php endforeach;endif;?></div>
  </article>
  <article class="app-panel dashboard-card tool-card">
    <div class="activity-section-head"><div><p class="card-kicker"><?=store_h(store_t('ACCOUNT EVENTS'))?></p><h2><?=store_h(store_t('Activity timeline'))?></h2></div><span><?=count($activity)?> <?=store_h(store_t('recent'))?></span></div>
    <div class="activity-timeline"><?php if(!$activity):?><p class="tool-empty"><?=store_h(store_t('No activity recorded yet.'))?></p><?php else:foreach($activity as $item):?><article class="activity-item"><i></i><div><div class="activity-head"><strong><?=htmlspecialchars((string)$item['title'],ENT_QUOTES,'UTF-8')?></strong><?=web_local_datetime($item['created_at']??null,store_t('Unknown'))?></div><?php if(!empty($item['details'])):?><p><?=htmlspecialchars((string)$item['details'],ENT_QUOTES,'UTF-8')?></p><?php endif;?><?php if(!empty($item['ip_address'])):?><small>IP <?=htmlspecialchars((string)$item['ip_address'],ENT_QUOTES,'UTF-8')?></small><?php endif;?></div></article><?php endforeach;endif;?></div>
  </article>
</section>
<?php account_page_end('ACTIVITY'); ?>
