<?php admin_page_start('Notifications','notifications','COMMUNICATION','Notifications','Inbox','Review account and system activity before opening the related item.'); ?>
<?php if($message):?><div class="app-alert admin-alert success"><?=htmlspecialchars($message,ENT_QUOTES,'UTF-8')?></div><?php endif;?>
<?php if($error):?><div class="app-alert admin-alert danger"><?=htmlspecialchars($error,ENT_QUOTES,'UTF-8')?></div><?php endif;?>

<section class="admin-stats" aria-label="Notification statistics">
  <article class="app-stat admin-stat stat-active"><div class="stat-label"><span class="stat-mark"></span>Unread</div><strong><?=number_format($unread)?></strong><small>Needs attention</small></article>
  <article class="app-stat admin-stat"><div class="stat-label"><span class="stat-mark"></span>Total</div><strong><?=number_format($total)?></strong><small>Stored notifications</small></article>
</section>

<section class="app-panel admin-panel admin-card-pad admin-section-space">
  <div class="panel-heading">
    <div><p class="panel-kicker">ADMIN INBOX</p><h2>Notification center</h2><p class="panel-count"><?=number_format($total)?> notification<?=$total===1?'':'s'?> · page <?=$page?> of <?=$pages?></p></div>
    <?php if($unread>0):?><form method="post"><?=csrf_field()?><input type="hidden" name="action" value="mark_all"><button class="app-button admin-button secondary" type="submit">Mark all read</button></form><?php endif;?>
  </div>
  <div class="admin-table-wrap">
    <table class="app-table admin-table admin-notification-table">
      <thead><tr><th>Notification</th><th>Message</th><th>Received</th><th>Status</th><th class="action-head">Action</th></tr></thead>
      <tbody>
      <?php if(!$rows):?>
        <tr><td colspan="5" class="empty-state"><span>◇</span><strong>No notifications yet.</strong><small>New operational and account events will appear here.</small></td></tr>
      <?php else: foreach($rows as $n):?>
        <tr class="<?=empty($n['read_at'])?'is-unread-row':''?>">
          <td><strong class="admin-table-title"><?=htmlspecialchars((string)$n['title'],ENT_QUOTES,'UTF-8')?></strong><small class="admin-table-kicker"><?=htmlspecialchars(strtoupper(str_replace(['.','_'],' ',(string)$n['type'])),ENT_QUOTES,'UTF-8')?></small></td>
          <td class="admin-table-message"><?=htmlspecialchars((string)$n['body'],ENT_QUOTES,'UTF-8')?></td>
          <td class="date-cell"><?=web_local_datetime($n['created_at']??null,'Unknown')?></td>
          <td><span class="app-badge <?=empty($n['read_at'])?'status-active':'status-disabled'?>"><i></i><?=empty($n['read_at'])?'Unread':'Read'?></span></td>
          <td class="action-cell"><a href="<?=htmlspecialchars(route_url('admin/notification.php').'?id='.(int)$n['id'],ENT_QUOTES,'UTF-8')?>">View details <span aria-hidden="true">↗</span></a></td>
        </tr>
      <?php endforeach; endif;?>
      </tbody>
    </table>
  </div>
  <div class="panel-footer admin-panel-footer-paged"><span><i></i> ADMIN NOTIFICATION CENTER</span><span><?=number_format($unread)?> UNREAD</span></div>
  <?php if($pages>1):?><nav class="admin-pagination" aria-label="Notification pages"><?php if($page>1):?><a href="<?=htmlspecialchars(route_url('admin/notifications.php').'?page='.($page-1),ENT_QUOTES,'UTF-8')?>">← Previous</a><?php else:?><span class="is-disabled">← Previous</span><?php endif;?><strong>Page <?=$page?> / <?=$pages?></strong><?php if($page<$pages):?><a href="<?=htmlspecialchars(route_url('admin/notifications.php').'?page='.($page+1),ENT_QUOTES,'UTF-8')?>">Next →</a><?php else:?><span class="is-disabled">Next →</span><?php endif;?></nav><?php endif;?>
</section>
<?php admin_page_end(); ?>
