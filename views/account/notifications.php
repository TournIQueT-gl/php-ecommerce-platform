<?php account_page_start('Notifications','notifications','ACCOUNT UPDATES','NOTIFICATION','CENTER.','Order updates, support replies, security alerts and store messages in one place.',$user); ?>
<?php if($message):?><div class="app-alert dashboard-alert success" role="status"><?=htmlspecialchars($message,ENT_QUOTES,'UTF-8')?></div><?php endif;?>
<?php if($error):?><div class="app-alert dashboard-alert danger" role="alert"><?=htmlspecialchars($error,ENT_QUOTES,'UTF-8')?></div><?php endif;?>

<section class="app-panel dashboard-card tool-card notification-center">
  <div class="notification-center-head">
    <div><p class="card-kicker"><?=store_h(store_t('INBOX'))?></p><h2><?=store_h(store_t('Account notifications'))?></h2><p><?=intval($unread)?> <?=store_h(store_t('unread'))?> · <?=intval($total)?> <?=store_h(store_t('total'))?></p></div>
    <?php if($enabled&&$unread>0):?><form method="post"><?=csrf_field()?><input type="hidden" name="action" value="mark_all"><button class="app-button tool-button secondary" type="submit"><?=store_h(store_t('MARK ALL READ'))?></button></form><?php endif;?>
  </div>

  <?php if(!$enabled):?>
    <div class="notification-empty"><strong><?=store_h(store_t('Notification center is disabled.'))?></strong><p><?=store_h(store_t('An administrator can enable it for this account.'))?></p></div>
  <?php elseif(!$notifications):?>
    <div class="notification-empty"><strong><?=store_h(store_t('You are all caught up.'))?></strong><p><?=store_h(store_t('New account and support events will appear here.'))?></p></div>
  <?php else:?>
    <div class="notification-list">
      <?php foreach($notifications as $n):?>
        <?php $isUnread=empty($n['read_at']); ?>
        <article class="notification-item <?=$isUnread?'is-unread':'is-read'?>">
          <span class="notification-marker" aria-hidden="true"></span>
          <div class="notification-copy">
            <div class="notification-meta"><span><?=htmlspecialchars(strtoupper(str_replace(['.','_'],' ',(string)$n['type'])),ENT_QUOTES,'UTF-8')?></span><?=web_local_datetime($n['created_at']??null,store_t('Unknown'))?></div>
            <h3><?=htmlspecialchars((string)$n['title'],ENT_QUOTES,'UTF-8')?></h3>
            <p><?=htmlspecialchars((string)$n['body'],ENT_QUOTES,'UTF-8')?></p>
          </div>
          <div class="notification-actions">
            <a class="app-button tool-button <?=$isUnread?'primary':'secondary'?>" href="<?=htmlspecialchars(route_url('notification.php').'?id='.(int)$n['id'],ENT_QUOTES,'UTF-8')?>"><?=store_h(store_t('DETAILS'))?></a>
            <?php if($isUnread):?><form method="post"><?=csrf_field()?><input type="hidden" name="action" value="mark_read"><input type="hidden" name="notification_id" value="<?=(int)$n['id']?>"><button class="notification-read-button" type="submit"><?=store_h(store_t('Mark read'))?></button></form><?php endif;?>
          </div>
        </article>
      <?php endforeach;?>
    </div>
    <?php if($pages>1):?><nav class="notification-pagination" aria-label="<?=store_h(store_t('Notification pages'))?>"><?php for($i=1;$i<=$pages;$i++):?><a class="<?=$i===$page?'is-current':''?>" href="<?=htmlspecialchars(route_url('notifications.php').'?page='.$i,ENT_QUOTES,'UTF-8')?>"><?=$i?></a><?php endfor;?></nav><?php endif;?>
  <?php endif;?>
</section>
<?php account_page_end('NOTIFICATIONS'); ?>
