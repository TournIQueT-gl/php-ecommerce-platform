<?php admin_page_start('Email Delivery','email','SMTP & DELIVERY','Email','Delivery','Configure transactional email, test SMTP, and inspect delivery results.'); ?>
<?php if($message):?><div class="app-alert admin-alert success" role="status"><?=htmlspecialchars($message,ENT_QUOTES,'UTF-8')?></div><?php endif;?>
<?php if($error):?><div class="app-alert admin-alert danger" role="alert"><?=htmlspecialchars($error,ENT_QUOTES,'UTF-8')?></div><?php endif;?>
<?php if(!$schemaReady):?><div class="app-alert admin-alert warning" role="alert"><strong>Historical email schema is missing.</strong> Ask the store Owner to open System Health and follow the database recovery guidance. Historical pre-baseline migrations are not shipped individually.</div><?php endif;?>

<section class="admin-stats admin-stats-compact" aria-label="Email delivery summary">
  <article class="app-stat admin-stat <?=!empty($settings['enabled'])?'stat-active':'stat-disabled'?>"><div class="stat-label"><span class="stat-mark"></span>Delivery</div><strong class="admin-stat-version"><?=!empty($settings['enabled'])?'ENABLED':'DISABLED'?></strong><small><?=htmlspecialchars((string)($settings['smtp_host']??'Not configured'),ENT_QUOTES,'UTF-8')?></small></article>
  <article class="app-stat admin-stat"><div class="stat-label"><span class="stat-mark"></span>Pending</div><strong><?=(int)($stats['pending']??0)?></strong><small>Queued messages</small></article>
  <article class="app-stat admin-stat <?=$stats['failed']??0?'stat-disabled':'stat-active'?>"><div class="stat-label"><span class="stat-mark"></span>Failed</div><strong><?=(int)($stats['failed']??0)?></strong><small>Retryable deliveries</small></article>
  <article class="app-stat admin-stat"><div class="stat-label"><span class="stat-mark"></span>Sent 24h</div><strong><?=(int)($stats['sent_24h']??0)?></strong><small>Accepted by SMTP</small></article>
</section>

<?php if($schemaReady):?>
<section class="app-panel admin-panel admin-card-pad admin-section-space">
  <div class="panel-heading"><div><p class="panel-kicker">SMTP CONFIGURATION</p><h2>Outbound mail server</h2><p class="panel-count">The SMTP password is encrypted with a server-local key under protected storage. It is never displayed back in Admin.</p></div></div>
  <form method="post" class="admin-form-grid">
    <?=csrf_field()?><input type="hidden" name="action" value="save_settings">
    <label class="app-field admin-field"><span>SMTP host</span><input name="smtp_host" maxlength="255" required value="<?=htmlspecialchars((string)($settings['smtp_host']??''),ENT_QUOTES,'UTF-8')?>" placeholder="smtp.example.com"></label>
    <label class="app-field admin-field"><span>Port</span><input type="number" name="smtp_port" min="1" max="65535" required value="<?=(int)($settings['smtp_port']??587)?>"></label>
    <label class="app-field admin-field"><span>Encryption</span><select name="smtp_encryption"><option value="tls" <?=($settings['smtp_encryption']??'tls')==='tls'?'selected':''?>>STARTTLS</option><option value="ssl" <?=($settings['smtp_encryption']??'')==='ssl'?'selected':''?>>SSL/TLS</option><option value="none" <?=($settings['smtp_encryption']??'')==='none'?'selected':''?>>None</option></select></label>
    <label class="app-field admin-field"><span>SMTP username</span><input name="smtp_username" maxlength="255" autocomplete="off" value="<?=htmlspecialchars((string)($settings['smtp_username']??''),ENT_QUOTES,'UTF-8')?>"></label>
    <label class="app-field admin-field"><span>SMTP password</span><input type="password" name="smtp_password" maxlength="1000" autocomplete="new-password" placeholder="<?=!empty($settings['password_configured'])?'Saved · leave blank to keep':'Enter SMTP password'?>"></label>
    <label class="app-field admin-field"><span>Saved password</span><select name="clear_password"><option value="0">Keep saved password</option><option value="1">Clear saved password</option></select></label>
    <label class="app-field admin-field"><span>From email</span><input type="email" name="from_email" maxlength="254" required value="<?=htmlspecialchars((string)($settings['from_email']??''),ENT_QUOTES,'UTF-8')?>" placeholder="noreply@example.com"></label>
    <label class="app-field admin-field"><span>From name</span><input name="from_name" maxlength="160" required value="<?=htmlspecialchars((string)($settings['from_name']??store_runtime_name()),ENT_QUOTES,'UTF-8')?>"></label>
    <label class="app-field admin-field"><span>Reply-To email</span><input type="email" name="reply_to_email" maxlength="254" value="<?=htmlspecialchars((string)($settings['reply_to_email']??''),ENT_QUOTES,'UTF-8')?>" placeholder="support@example.com"></label>
    <label class="app-field admin-field"><span>Canonical public site URL</span><input type="url" value="<?=htmlspecialchars((string)CANONICAL_SITE_URL,ENT_QUOTES,'UTF-8')?>" readonly><small>Security and account links always use this production origin.</small></label>
    <label class="app-field admin-field"><span>Delivery state</span><select name="enabled"><option value="1" <?=!empty($settings['enabled'])?'selected':''?>>Enabled</option><option value="0" <?=empty($settings['enabled'])?'selected':''?>>Disabled</option></select></label>
    <?=admin_password_field()?>
    <div class="app-field admin-field admin-field-action"><span>&nbsp;</span><button class="app-button admin-button primary" type="submit">Save email settings</button></div>
  </form>
  <p class="admin-muted">Recommended: port 587 + STARTTLS or port 465 + SSL/TLS. If you move servers, restore the matching persistent <code>mail.key</code> from your recovery snapshot or re-enter the SMTP password.</p>
</section>

<section class="admin-grid-2 admin-section-space">
  <article class="app-panel admin-panel admin-card-pad">
    <p class="panel-kicker">CONNECTION TEST</p><h2>Send a test email</h2><p class="admin-muted">This performs an immediate SMTP connection so you get the real server result now.</p>
    <form method="post" class="admin-form-grid"><?=csrf_field()?><input type="hidden" name="action" value="send_test"><label class="app-field admin-field"><span>Recipient</span><input type="email" name="recipient" required maxlength="254" placeholder="you@example.com"></label><?=admin_password_field()?><div class="app-field admin-field admin-field-action"><span>&nbsp;</span><button class="app-button admin-button secondary" type="submit">Send test</button></div></form>
  </article>
  <article class="app-panel admin-panel admin-card-pad">
    <p class="panel-kicker">QUEUE CONTROL</p><h2>Background delivery</h2><p class="admin-muted"><?php $mailMode=strtoupper((string)($schedulerMode['effective']??'web'));?>Scheduler mode: <strong><?=htmlspecialchars($mailMode,ENT_QUOTES,'UTF-8')?></strong>. In WEB mode, small locked queue batches are delivered by the safe browser fallback; in CLI mode, cron handles larger batches. You can process the queue manually at any time.</p>
    <form method="post"><?=csrf_field()?><input type="hidden" name="action" value="process_queue"><button class="app-button admin-button secondary" type="submit">Process queue now</button></form>
  </article>
</section>

<section class="app-panel admin-panel admin-card-pad admin-section-space">
  <div class="panel-heading"><div><p class="panel-kicker">DELIVERY LOG</p><h2>Recent email attempts</h2><p class="panel-count">Message bodies are cleared after successful delivery so reset links and other sensitive content are not retained.</p></div></div>
  <?php if(!$deliveries):?><div class="admin-empty-panel">No email deliveries recorded yet.</div><?php else:?><div class="admin-table-wrap"><table class="admin-table"><thead><tr><th>Recipient</th><th>Category</th><th>Subject</th><th>Status</th><th>Attempts</th><th>Created</th><th>Action</th></tr></thead><tbody><?php foreach($deliveries as $d):?><tr><td><?=htmlspecialchars((string)$d['recipient'],ENT_QUOTES,'UTF-8')?></td><td><?=htmlspecialchars(strtoupper((string)$d['category']),ENT_QUOTES,'UTF-8')?></td><td><?=htmlspecialchars((string)$d['subject'],ENT_QUOTES,'UTF-8')?><?php if(!empty($d['last_error'])):?><small class="admin-muted"><?=htmlspecialchars((string)$d['last_error'],ENT_QUOTES,'UTF-8')?></small><?php endif;?></td><td><span class="app-badge status-pill <?=($d['status']==='sent')?'status-active':(($d['status']==='failed')?'status-disabled':'status-neutral')?>"><i></i><?=htmlspecialchars(strtoupper((string)$d['status']),ENT_QUOTES,'UTF-8')?></span></td><td><?=(int)$d['attempts']?></td><td><?=web_local_datetime($d['created_at']??null,'Unknown')?></td><td><?php if($d['status']==='failed'):?><form method="post"><?=csrf_field()?><input type="hidden" name="action" value="retry"><input type="hidden" name="delivery_id" value="<?=(int)$d['id']?>"><button class="app-button admin-button secondary" type="submit">Retry</button></form><?php else:?>—<?php endif;?></td></tr><?php endforeach;?></tbody></table></div><?php endif;?>
</section>
<?php endif;?>
<?php admin_page_end(); ?>
