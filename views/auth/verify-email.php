<?php $storeName=store_runtime_name(); ?>
<!doctype html>
<html lang="<?=htmlspecialchars(store_lang(),ENT_QUOTES,'UTF-8')?>" dir="<?=htmlspecialchars(store_lang_dir(),ENT_QUOTES,'UTF-8')?>">
<head>
  <meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
  <meta name="theme-color" content="#050609"><meta name="robots" content="noindex,nofollow">
  <title>Email Verification · <?=htmlspecialchars($storeName,ENT_QUOTES,'UTF-8')?></title>
  <link rel="icon" type="image/svg+xml" href="<?=htmlspecialchars(asset_url('assets/images/favicon-store.svg'),ENT_QUOTES)?>"><link rel="alternate icon" type="image/x-icon" href="<?=htmlspecialchars(asset_url('favicon.ico'), ENT_QUOTES, 'UTF-8')?>"><link rel="apple-touch-icon" href="<?=htmlspecialchars(asset_url('apple-touch-icon.png'), ENT_QUOTES, 'UTF-8')?>">
  <link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Playfair+Display:wght@500;600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?=htmlspecialchars(asset_url('assets/css/auth.css'),ENT_QUOTES)?>"><link rel="stylesheet" href="<?=htmlspecialchars(asset_url('assets/css/auth-locale.css'),ENT_QUOTES)?>">
</head>
<body class="auth-page">
<div class="page-noise" aria-hidden="true"></div>
<header class="auth-header"><a class="brand" href="<?=htmlspecialchars(url(),ENT_QUOTES)?>"><span class="brand-mark" aria-hidden="true"><span></span><span></span></span><span class="brand-copy"><strong><?=htmlspecialchars($storeName,ENT_QUOTES,'UTF-8')?></strong><em>PERFUMES</em></span></a></header>
<main class="auth-layout">
  <section class="auth-intro"><p class="auth-eyebrow"><span></span> EMAIL SECURITY</p><h1>VERIFY.<br><strong>CONNECTED.</strong></h1><div class="auth-ornament" aria-hidden="true"><i></i><b></b><i></i></div><p class="auth-description">Verified email enables one-time password recovery and optional account notifications.</p></section>
  <section class="auth-panel">
    <div class="auth-panel-top"><span>EMAIL CENTER</span><span class="auth-panel-index">SECURE <i></i> VERIFY</span></div>
    <?php $verificationState=$result['ok']??null; ?>
    <div class="auth-heading"><p class="auth-kicker"><?=$verificationState===true?'VERIFIED':($verificationState===false?'VERIFICATION FAILED':'CONFIRM EMAIL')?></p><h2><?=$verificationState===true?'Email verified':($verificationState===false?'Could not verify':'Verify email')?><span>.</span></h2><p><?=htmlspecialchars((string)$result['message'],ENT_QUOTES,'UTF-8')?></p></div>
    <?php if($verificationState===null && $token!==''):?>
      <form method="post" class="auth-form"><?=csrf_field()?><button class="auth-submit" type="submit">VERIFY EMAIL <span aria-hidden="true">↗</span></button></form>
    <?php endif;?>
    <div class="auth-inline-actions"><a class="auth-secondary-link" href="<?=$loggedIn?route_url('account-email.php'):route_url('login.php')?>"><?=$loggedIn?'EMAIL CENTER':'BACK TO LOGIN'?></a></div>
    <div class="auth-panel-foot"><span></span> VERIFIED DELIVERY</div>
  </section>
</main>
</body>
</html>
