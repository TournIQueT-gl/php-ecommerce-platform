<?php admin_page_start('Admin Security', 'security', 'ADMIN AUTHENTICATION', 'Admin', 'Security', 'Protect the control panel with a time-based authenticator and one-time recovery codes.'); ?>

<?php if($message):?><div class="app-alert admin-alert success" role="status"><?=htmlspecialchars($message,ENT_QUOTES,'UTF-8')?></div><?php endif;?>
<?php if($error):?><div class="app-alert admin-alert danger" role="alert"><?=htmlspecialchars($error,ENT_QUOTES,'UTF-8')?></div><?php endif;?>
<?php if(!web_request_is_https()):?><div class="app-alert admin-alert warning" role="alert"><strong>HTTPS required for 2FA setup.</strong> Open the admin panel through HTTPS before enabling authenticator protection.</div><?php endif;?>
<?php if(!$schemaReady):?>
<div class="app-alert admin-alert warning" role="alert"><strong>2FA schema required.</strong> Restore the current database baseline before enabling authenticator protection.</div>
<?php elseif(!$encryptedStorageReady):?>
<div class="app-alert admin-alert warning" role="alert"><strong>Encrypted TOTP storage upgrade required.</strong> Run <code>php bin/migrate.php</code> to apply migration 025. Existing legacy 2FA remains usable, but new 2FA setup is blocked until encrypted storage is ready.</div>
<?php endif;?>

<section class="admin-stats admin-stats-compact" aria-label="Security status">
  <article class="app-stat admin-stat <?=$state['enabled']?'stat-active':'stat-disabled'?>"><div class="stat-label"><span class="stat-mark"></span>Admin 2FA</div><strong class="admin-stat-version"><?=$state['enabled']?'ENABLED':'DISABLED'?></strong><small><?=$state['enabled']?'Required at admin sign-in':'Password only'?></small></article>
  <article class="app-stat admin-stat"><div class="stat-label"><span class="stat-mark"></span>Recovery codes</div><strong><?=count($state['recovery_codes'])?></strong><small>Unused codes remaining</small></article>
  <article class="app-stat admin-stat"><div class="stat-label"><span class="stat-mark"></span>Session verification</div><strong class="admin-stat-version"><?=((int)($_SESSION['admin_2fa_verified_for']??0)===$adminId)?'VERIFIED':'STANDARD'?></strong><small>Current browser session</small></article>
</section>

<?php if($recoveryDisplay):?>
<section class="app-panel admin-panel admin-card-pad admin-section-space recovery-panel">
  <p class="panel-kicker">SAVE THESE NOW</p><h2>One-time recovery codes</h2>
  <p class="admin-muted">Each code works once. Store them somewhere private. They will not be shown again after this page.</p>
  <div class="recovery-grid"><?php foreach($recoveryDisplay as $code):?><code><?=htmlspecialchars((string)$code,ENT_QUOTES,'UTF-8')?></code><?php endforeach;?></div>
</section>
<?php endif;?>

<?php if($schemaReady && !$state['enabled']):?>
<section class="app-panel admin-panel admin-card-pad admin-section-space">
  <p class="panel-kicker">SETUP</p><h2>Enable authenticator 2FA</h2>
  <p class="admin-muted">Scan the QR code with Google Authenticator, Microsoft Authenticator, Authy, 1Password, or another TOTP app. The QR code is generated locally in your browser; the setup secret is not sent to a QR service.</p>
  <div class="security-setup-layout">
    <div class="security-qr-card">
      <span class="security-qr-label">SCAN WITH AUTHENTICATOR</span>
      <div class="security-qr" data-otpauth-qr="<?=htmlspecialchars($otpauth,ENT_QUOTES,'UTF-8')?>" aria-label="Authenticator setup QR code" aria-busy="true"><span>Generating QR…</span></div>
      <small>Use <strong>Scan QR code</strong> in your authenticator app.</small>
    </div>
    <div class="security-manual-setup">
      <div class="security-key-grid">
        <div><span class="admin-muted">Account</span><strong><?=htmlspecialchars(store_runtime_name().' · '.(string)($admin['username']??''),ENT_QUOTES,'UTF-8')?></strong></div>
        <div><span class="admin-muted">Type</span><strong>Time based (TOTP) · 6 digits · 30 seconds</strong></div>
      </div>
      <div class="admin-secret-key"><span>MANUAL SETUP KEY</span><code><?=htmlspecialchars(totp_format_secret($setupSecret),ENT_QUOTES,'UTF-8')?></code><button class="ui-copy" type="button" data-copy="<?=htmlspecialchars($setupSecret,ENT_QUOTES,'UTF-8')?>">Copy key</button><small>When entering manually, paste only the copied key. Do not paste the otpauth URI. The key contains only A-Z and 2-7.</small></div>
      <details class="admin-details"><summary>Show otpauth URI (advanced)</summary><code class="admin-uri"><?=htmlspecialchars($otpauth,ENT_QUOTES,'UTF-8')?></code></details>
    </div>
  </div>
  <form method="post" class="admin-form-grid security-enable-grid">
    <?=csrf_field()?><input type="hidden" name="action" value="enable_2fa">
    <?=admin_password_field()?>
    <label class="app-field admin-field"><span>6-digit authenticator code</span><input name="totp_code" required maxlength="6" inputmode="numeric" autocomplete="one-time-code" placeholder="123456"></label>
    <div class="app-field admin-field admin-field-action"><span>&nbsp;</span><button class="app-button admin-button primary" type="submit" <?=$encryptedStorageReady?'':'disabled'?>>Enable 2FA</button></div>
  </form>
  <form method="post" class="admin-inline-actions admin-section-mini"><?=csrf_field()?><input type="hidden" name="action" value="new_setup_secret"><button class="app-button admin-button secondary" type="submit">Generate different setup key</button></form>
</section>
<?php elseif($schemaReady):?>
<section class="app-panel admin-panel admin-card-pad admin-section-space">
  <p class="panel-kicker">ACTIVE PROTECTION</p><h2>Two-factor authentication is enabled</h2>
  <p class="admin-muted">Enabled <?=web_local_datetime($state['enabled_at']??null,'previously')?>. Admin website sign-ins require the password plus an authenticator/recovery code. Storefront customer authentication is separate from administrator two-factor authentication.</p>
  <div class="security-action-grid">
    <form method="post" class="security-danger-form">
      <?=csrf_field()?><input type="hidden" name="action" value="regenerate_recovery">
      <h3>Replace recovery codes</h3><p class="admin-muted">All existing recovery codes are invalidated immediately.</p>
      <?=admin_password_field()?>
      <label class="app-field admin-field"><span>Authenticator or recovery code</span><input name="totp_code" required maxlength="16" autocomplete="one-time-code" placeholder="123456"></label>
      <button class="app-button admin-button secondary" type="submit">Generate new recovery codes</button>
    </form>
    <form method="post" class="security-danger-form" data-confirm="Disable two-factor authentication for this admin account?" data-confirm-tone="danger">
      <?=csrf_field()?><input type="hidden" name="action" value="disable_2fa">
      <h3>Disable 2FA</h3><p class="admin-muted">The admin account will return to password-only website authentication.</p>
      <?=admin_password_field()?>
      <label class="app-field admin-field"><span>Authenticator or recovery code</span><input name="totp_code" required maxlength="16" autocomplete="one-time-code" placeholder="123456"></label>
      <button class="app-button admin-button danger" type="submit">Disable 2FA</button>
    </form>
  </div>
</section>
<?php endif;?>
<?php admin_page_end(); ?>
