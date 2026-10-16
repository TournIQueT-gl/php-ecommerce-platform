<?php
$identity=is_array($profile['identity']??null)?$profile['identity']:[];
$prefs=is_array($profile['preferences']??null)?$profile['preferences']:[];
$email=trim((string)($identity['email']??''));
$verified=!empty($identity['email_verified_at']);
$pendingEmail=trim((string)($identity['pending_email']??''));
$deliveryAvailable=!empty($profile['delivery_enabled']);
account_page_start('Email','security','ACCOUNT COMMUNICATION','EMAIL','& RECOVERY.','Manage your verified recovery address and choose which optional account updates reach your inbox.',$user);
?>
<?php if($message):?><div class="app-alert dashboard-alert success" role="status"><?=htmlspecialchars($message,ENT_QUOTES,'UTF-8')?></div><?php endif;?>
<?php if($error):?><div class="app-alert dashboard-alert danger" role="alert"><?=htmlspecialchars($error,ENT_QUOTES,'UTF-8')?></div><?php endif;?>
<?php if(!$schemaReady):?><div class="app-alert dashboard-alert warning" role="alert"><?=store_h(store_t('Email features are temporarily unavailable while account email services are being prepared.'))?></div><?php endif;?>

<section class="security-summary" aria-label="<?=store_h(store_t('Email account summary'))?>">
  <div><span><?=store_h(store_t('RECOVERY EMAIL'))?></span><strong><?=$email!==''?htmlspecialchars($email,ENT_QUOTES,'UTF-8'):store_h(store_t('NOT SET'))?></strong></div>
  <div><span><?=store_h(store_t('VERIFICATION'))?></span><strong><?=store_h(store_t($pendingEmail!==''?'CHANGE PENDING':($verified?'VERIFIED':($email!==''?'PENDING':'NOT SET'))))?></strong></div>
  <div><span><?=store_h(store_t('EMAIL DELIVERY'))?></span><strong><?=store_h(store_t($deliveryAvailable?'AVAILABLE':'OFFLINE'))?></strong></div>
</section>

<section class="dashboard-tools dashboard-security-grid" aria-label="<?=store_h(store_t('Email settings'))?>">
  <article class="app-panel dashboard-card tool-card security-card">
    <div class="card-topline"><span class="card-label"><?=store_h(store_t('RECOVERY ADDRESS'))?></span></div>
    <div class="tool-heading">
      <div><p class="card-kicker"><?=store_h(store_t('ACCOUNT EMAIL'))?></p><h2><?=store_h(store_t($pendingEmail!==''?'Verify replacement email':($verified?'Verified recovery email':'Email verification')))?></h2></div>
      <span class="app-badge account-status-badge <?=$verified?'is-on':($email!==''||$pendingEmail!==''?'is-neutral':'is-off')?>"><?=store_h(store_t($pendingEmail!==''?'CHANGE PENDING':($verified?'VERIFIED':($email!==''?'PENDING':'NOT SET'))))?></span>
    </div>
    <p class="account-help-text"><?=store_h(store_t('Your verified email is used for password recovery and critical security messages. Changing it requires your current password. The existing verified address remains active until the replacement is verified.'))?></p>
    <?php if($pendingEmail!==''):?>
      <div class="account-info-row">
        <div class="account-info-copy"><div class="account-info-title"><strong><?=store_h(store_t('Replacement pending'))?></strong></div><small><?=htmlspecialchars($pendingEmail,ENT_QUOTES,'UTF-8')?> <?=store_h(store_t('must be verified before it replaces your current recovery address.'))?></small></div>
        <form method="post"><?=csrf_field()?><input type="hidden" name="action" value="resend_verification"><button class="app-button tool-button secondary" type="submit"><?=store_h(store_t('RESEND VERIFICATION'))?></button></form>
      </div>
    <?php endif;?>

    <form method="post" class="security-form password-form">
      <?=csrf_field()?>
      <input type="hidden" name="action" value="change_email">
      <label><span><?=store_h(store_t('NEW EMAIL ADDRESS'))?></span><input type="email" name="email" maxlength="254" required autocomplete="email" value="<?=htmlspecialchars($pendingEmail!==''?$pendingEmail:$email,ENT_QUOTES,'UTF-8')?>" placeholder="you@example.com"></label>
      <label><span><?=store_h(store_t('CURRENT PASSWORD'))?></span><input type="password" name="current_password" maxlength="256" required autocomplete="current-password" placeholder="<?=store_h(store_t('Confirm your password'))?>"></label>
      <button class="app-button tool-button primary" type="submit"><?=store_h(store_t($email===''?'ADD EMAIL':'SAVE EMAIL'))?> ↗</button>
    </form>

    <?php if($pendingEmail===''&&$email!==''&&!$verified):?>
      <div class="account-info-row">
        <div class="account-info-copy"><div class="account-info-title"><strong><?=store_h(store_t('Verification required'))?></strong></div><small><?=store_h(store_t('Check'))?> <?=htmlspecialchars($email,ENT_QUOTES,'UTF-8')?> <?=store_h(store_t('for the one-time verification message. If it expired or never arrived, send another one.'))?></small></div>
        <form method="post"><?=csrf_field()?><input type="hidden" name="action" value="resend_verification"><button class="app-button tool-button secondary" type="submit"><?=store_h(store_t('RESEND VERIFICATION'))?></button></form>
      </div>
    <?php endif;?>
  </article>

  <article class="app-panel dashboard-card tool-card security-card">
    <div class="card-topline"><span class="card-label"><?=store_h(store_t('EMAIL PREFERENCES'))?></span></div>
    <div class="tool-heading"><div><p class="card-kicker"><?=store_h(store_t('OPTIONAL DELIVERY'))?></p><h2><?=store_h(store_t('Inbox preferences'))?></h2></div></div>
    <p class="account-help-text"><?=store_h(store_t('Password recovery and critical security messages are always enabled for a verified recovery address. The categories below are optional.'))?></p>

    <form method="post" class="security-form password-form">
      <?=csrf_field()?>
      <input type="hidden" name="action" value="preferences">
      <label class="app-check-row"><input type="checkbox" name="support" value="1" <?=!empty($prefs['support_enabled'])?'checked':''?>><span><strong><?=store_h(store_t('Support replies'))?></strong><small><?=store_h(store_t('Ticket replies and support updates.'))?></small></span></label>
      <label class="app-check-row"><input type="checkbox" name="orders" value="1" <?=!empty($prefs['orders_enabled'])?'checked':''?>><span><strong><?=store_h(store_t('Orders & delivery'))?></strong><small><?=store_h(store_t('Order confirmations, fulfillment and delivery updates.'))?></small></span></label>
      <label class="app-check-row"><input type="checkbox" name="marketing" value="1" <?=!empty($prefs['marketing_enabled'])?'checked':''?>><span><strong><?=store_h(store_t('New arrivals & promotions'))?></strong><small><?=store_h(store_t('New collections, product drops and selected store offers.'))?></small></span></label>
      <label class="app-check-row"><input type="checkbox" name="maintenance" value="1" <?=!empty($prefs['maintenance_enabled'])?'checked':''?>><span><strong><?=store_h(store_t('Service notices'))?></strong><small><?=store_h(store_t('Important service availability notices.'))?></small></span></label>
      <label class="app-check-row"><input type="checkbox" name="announcements" value="1" <?=!empty($prefs['announcements_enabled'])?'checked':''?>><span><strong><?=store_h(store_t('Store announcements'))?></strong><small><?=store_h(store_t('Important store announcements.'))?></small></span></label>
      <button class="app-button tool-button primary" type="submit"><?=store_h(store_t('SAVE PREFERENCES'))?></button>
    </form>
  </article>
</section>
<?php account_page_end('ACCOUNT PORTAL'); ?>
