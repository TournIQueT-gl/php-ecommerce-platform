<?php account_page_start('Notification','notifications','ACCOUNT UPDATE','NOTIFICATION','DETAILS.','Review the update first, then decide whether to open the related area.',$user); ?>
<?php if($error):?><div class="app-alert dashboard-alert danger" role="alert"><?=htmlspecialchars($error,ENT_QUOTES,'UTF-8')?></div><?php endif;?>
<?php if($notification):?>
<section class="app-panel dashboard-card tool-card notification-detail-card">
  <div class="notification-detail-head">
    <div><p class="card-kicker"><?=htmlspecialchars(strtoupper(str_replace(['.','_'],' ',(string)$notification['type'])),ENT_QUOTES,'UTF-8')?></p><h2><?=htmlspecialchars((string)$notification['title'],ENT_QUOTES,'UTF-8')?></h2></div>
    <?=web_local_datetime($notification['created_at']??null,store_t('Unknown'))?>
  </div>
  <p class="notification-detail-body"><?=nl2br(htmlspecialchars((string)$notification['body'],ENT_QUOTES,'UTF-8'))?></p>
  <?php $meta=[];$data=$notification['data']??[];
    foreach(['ticket_id'=>'Ticket','order_id'=>'Order','order_number'=>'Order number','product'=>'Product','reason'=>'Reason','audience'=>'Audience','starts_at'=>'Starts','ends_at'=>'Ends'] as $key=>$label){if(isset($data[$key])&&$data[$key]!==''&&$data[$key]!==null)$meta[$label]=(string)$data[$key];}
  ?>
  <?php if($meta):?><dl class="notification-detail-meta"><?php foreach($meta as $label=>$value):?><div><dt><?=store_h(store_t($label))?></dt><dd><?=htmlspecialchars($value,ENT_QUOTES,'UTF-8')?></dd></div><?php endforeach;?></dl><?php endif;?>
  <div class="notification-detail-actions"><a class="app-button tool-button primary" href="<?=htmlspecialchars((string)$target['url'],ENT_QUOTES,'UTF-8')?>"><?=store_h(store_t('OPEN RELATED ITEM'))?> ↗</a><a class="app-button tool-button secondary" href="<?=htmlspecialchars(route_url('notifications.php'),ENT_QUOTES,'UTF-8')?>"><?=store_h(store_t('Back to notifications'))?></a></div>
</section>
<?php endif;?>
<?php account_page_end('NOTIFICATION DETAILS'); ?>
