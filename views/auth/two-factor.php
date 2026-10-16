<?php $storeName=store_runtime_name(); ?>

<!doctype html>
<html lang="<?=htmlspecialchars(store_lang(),ENT_QUOTES,'UTF-8')?>" dir="<?=htmlspecialchars(store_lang_dir(),ENT_QUOTES,'UTF-8')?>">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="theme-color" content="#050609">
<meta name="robots" content="noindex,nofollow">
  <title>Two-factor authentication · <?=htmlspecialchars($storeName,ENT_QUOTES,'UTF-8')?></title>
  <link rel="icon" type="image/svg+xml" href="<?=htmlspecialchars(asset_url('assets/images/favicon-store.svg'), ENT_QUOTES, 'UTF-8')?>"><link rel="alternate icon" type="image/x-icon" href="<?=htmlspecialchars(asset_url('favicon.ico'), ENT_QUOTES, 'UTF-8')?>"><link rel="apple-touch-icon" href="<?=htmlspecialchars(asset_url('apple-touch-icon.png'), ENT_QUOTES, 'UTF-8')?>">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Playfair+Display:wght@500;600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?=htmlspecialchars(asset_url('assets/css/auth.css'), ENT_QUOTES)?>">
</head>
<body class="auth-page">
  <div class="page-noise" aria-hidden="true"></div>
  <header class="auth-header">
    <a class="brand" href="<?=htmlspecialchars(url(), ENT_QUOTES)?>" aria-label="<?=htmlspecialchars($storeName,ENT_QUOTES,'UTF-8')?> home"><span class="brand-mark" aria-hidden="true"><span></span><span></span></span><span class="brand-copy"><strong><?=htmlspecialchars($storeName,ENT_QUOTES,'UTF-8')?></strong><em>PERFUMES</em></span></a>
    <form class="auth-home-form" method="post" action="<?=htmlspecialchars(route_url('logout.php'), ENT_QUOTES)?>"><?=csrf_field()?><button class="auth-home-link auth-home-button" type="submit"><span aria-hidden="true">←</span> CANCEL SIGN IN</button></form>
  </header>
  <main class="auth-layout">
    <section class="auth-intro" aria-label="Two-factor authentication">
      <p class="auth-eyebrow"><span></span> ADMIN SECURITY CHECK</p>
      <h1>SECOND FACTOR.<br><strong>ONE MORE STEP.</strong></h1>
      <div class="auth-ornament" aria-hidden="true"><i></i><b></b><i></i></div>
      <p class="auth-description">The password was accepted. Complete the admin sign-in using your authenticator app or a one-time recovery code.</p>
    </section>
    <section class="auth-panel" aria-labelledby="auth-title">
      <div class="auth-panel-top"><span>ADMIN ACCESS</span><span class="auth-panel-index">02 <i></i> 02</span></div>
      <div class="auth-heading"><p class="auth-kicker">TWO-FACTOR AUTHENTICATION</p><h2 id="auth-title">Verify<span>.</span></h2><p>Signed in as <?=htmlspecialchars($username, ENT_QUOTES, 'UTF-8')?>. Enter the 6-digit code from your authenticator app.</p></div>
      <?php if($error): ?><div class="auth-error" role="alert"><?=htmlspecialchars($error, ENT_QUOTES, 'UTF-8')?></div><?php endif; ?>
      <form method="post" class="auth-form">
        <?=csrf_field()?>
        <div class="auth-field"><label for="code">AUTHENTICATOR OR RECOVERY CODE</label><input id="code" class="auth-code-input" name="code" required maxlength="16" autocomplete="one-time-code" inputmode="text" autofocus placeholder="123456"></div>
        <button class="auth-submit" type="submit">VERIFY ADMIN ACCESS <span aria-hidden="true">↗</span></button>
      </form>
      <div class="auth-panel-foot"><span></span> TIME-BASED ONE-TIME PASSWORD</div>
    </section>
  </main>
</body>
</html>
