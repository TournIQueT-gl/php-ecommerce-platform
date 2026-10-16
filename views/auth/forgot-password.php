<?php $storeName=store_runtime_name(); ?>
<!doctype html>
<html lang="<?=htmlspecialchars(store_lang(),ENT_QUOTES,'UTF-8')?>" dir="<?=htmlspecialchars(store_lang_dir(),ENT_QUOTES,'UTF-8')?>">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="theme-color" content="#050609">
  <meta name="robots" content="noindex,nofollow">
  <title>Password Recovery · <?=htmlspecialchars($storeName,ENT_QUOTES,'UTF-8')?></title>
  <link rel="icon" type="image/svg+xml" href="<?=htmlspecialchars(asset_url('assets/images/favicon-store.svg'),ENT_QUOTES)?>"><link rel="alternate icon" type="image/x-icon" href="<?=htmlspecialchars(asset_url('favicon.ico'), ENT_QUOTES, 'UTF-8')?>"><link rel="apple-touch-icon" href="<?=htmlspecialchars(asset_url('apple-touch-icon.png'), ENT_QUOTES, 'UTF-8')?>">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Playfair+Display:wght@500;600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?=htmlspecialchars(asset_url('assets/css/auth.css'),ENT_QUOTES)?>"><link rel="stylesheet" href="<?=htmlspecialchars(asset_url('assets/css/auth-locale.css'),ENT_QUOTES)?>">
  <script defer src="<?=htmlspecialchars(asset_url('assets/js/ui.js'),ENT_QUOTES,'UTF-8')?>"></script>
</head>
<body class="auth-page">
<div class="page-noise" aria-hidden="true"></div>
<header class="auth-header">
  <a class="brand" href="<?=htmlspecialchars(url(),ENT_QUOTES)?>"><span class="brand-mark" aria-hidden="true"><span></span><span></span></span><span class="brand-copy"><strong><?=htmlspecialchars($storeName,ENT_QUOTES,'UTF-8')?></strong><em>PERFUMES</em></span></a>
  <a class="auth-home-link" href="<?=route_url('login.php')?>"><span aria-hidden="true">←</span> BACK TO LOGIN</a>
</header>
<main class="auth-layout">
  <section class="auth-intro">
    <p class="auth-eyebrow"><span></span> ACCOUNT RECOVERY</p>
    <h1>RECOVER.<br><strong>SECURELY.</strong></h1>
    <div class="auth-ornament" aria-hidden="true"><i></i><b></b><i></i></div>
    <p class="auth-description">If your account has a verified email address, a one-time reset link is sent automatically. If email recovery is unavailable, support can verify ownership and provide a fresh one-time reset link.</p>
  </section>
  <section class="auth-panel">
    <div class="auth-panel-top"><span>RECOVERY CENTER</span><span class="auth-panel-index">SECURE <i></i> RESET</span></div>
    <?php if($error):?><div class="auth-error" role="alert"><?=htmlspecialchars($error,ENT_QUOTES,'UTF-8')?></div><?php endif;?>

    <?php if(!empty($sent)):?>
      <div class="auth-heading"><p class="auth-kicker">CHECK YOUR INBOX</p><h2>Recovery requested<span>.</span></h2><p>If a verified email address is available, a one-time reset link has been queued and expires in 30 minutes. Otherwise, contact public support; after ownership is verified, an administrator can generate a fresh one-time reset link for you.</p></div>
      <div class="auth-inline-actions"><a class="auth-secondary-link" href="<?=route_url('login.php')?>">BACK TO LOGIN</a><a class="auth-secondary-link" href="<?=htmlspecialchars(store_public_support_url(),ENT_QUOTES,'UTF-8')?>">PUBLIC SUPPORT →</a></div>
    <?php elseif($token===''):?>
      <div class="auth-heading"><p class="auth-kicker">FORGOT PASSWORD</p><h2>Request access<span>.</span></h2><p>Enter your username or verified email address. Email-enabled accounts receive a one-time reset link. If email recovery is unavailable, public support can verify ownership and arrange a fresh administrator-generated reset link.</p></div>
      <form method="post" class="auth-form">
        <?=csrf_field()?>
        <input type="hidden" name="action" value="request">
        <div class="auth-field"><label for="username">USERNAME OR EMAIL</label><input id="username" name="username" required maxlength="254" autocomplete="username" placeholder="Username or verified email"></div>
        <button class="auth-submit" type="submit">REQUEST PASSWORD RESET <span aria-hidden="true">↗</span></button>
      </form>
    <?php else:?>
      <?php $state=(string)($request['effective_status']??'pending'); ?>
      <div class="auth-heading"><p class="auth-kicker">RECOVERY REQUEST</p><h2><?=($state==='approved')?'Approved':'Request status'?><span>.</span></h2><p>Keep this page private. The recovery URL itself is your secret request key.</p></div>
      <?php if($state==='approved'):?>
        <div class="auth-recovery-status"><strong>APPROVED</strong>Your administrator approved this reset. The approval expires <?=web_local_datetime($request['reset_expires_at']??null,'soon')?>.</div>
        <form method="post" class="auth-form">
          <?=csrf_field()?>
          <input type="hidden" name="action" value="reset">
          <div class="auth-field"><label for="new_password">NEW PASSWORD</label><input id="new_password" type="password" name="new_password" required minlength="8" maxlength="256" autocomplete="new-password"></div>
          <div class="auth-field"><label for="confirm_password">CONFIRM NEW PASSWORD</label><input id="confirm_password" type="password" name="confirm_password" required minlength="8" maxlength="256" autocomplete="new-password"></div>
          <button class="auth-submit" type="submit">RESET PASSWORD <span aria-hidden="true">↗</span></button>
        </form>
      <?php elseif(in_array($state,['used','expired','cancelled'],true)):?>
        <div class="auth-recovery-status"><strong><?=htmlspecialchars(strtoupper($state),ENT_QUOTES,'UTF-8')?></strong>This recovery request is no longer active. Start a new request if you still need access.</div>
        <div class="auth-inline-actions"><a class="auth-secondary-link" href="<?=route_url('forgot-password.php')?>">NEW REQUEST</a><a class="auth-secondary-link" href="<?=route_url('login.php')?>">BACK TO LOGIN</a></div>
      <?php else:?>
        <div class="auth-recovery-status"><strong>PENDING ADMIN APPROVAL</strong>Legacy recovery request pending. For current recovery, contact public support after submitting a request; an administrator will generate a fresh one-time reset link after verifying ownership.</div>
        <div class="auth-inline-actions"><a class="auth-secondary-link" href="<?=htmlspecialchars(store_public_support_url(),ENT_QUOTES,'UTF-8')?>">PUBLIC SUPPORT →</a><a class="auth-secondary-link" href="<?=route_url('login.php')?>">BACK TO LOGIN</a></div>
      <?php endif;?>
    <?php endif;?>
    <div class="auth-panel-foot"><span></span> ONE-TIME RECOVERY FLOW</div>
  </section>
</main>
</body>
</html>
