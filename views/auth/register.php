<?php $storeName=store_runtime_name(); ?>

<!doctype html>
<html lang="<?=htmlspecialchars(store_lang(),ENT_QUOTES,'UTF-8')?>" dir="<?=htmlspecialchars(store_lang_dir(),ENT_QUOTES,'UTF-8')?>">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="theme-color" content="#050609">
<meta name="robots" content="noindex,nofollow">
  <title><?=htmlspecialchars(store_t('Create account'),ENT_QUOTES,'UTF-8')?> · <?=htmlspecialchars($storeName,ENT_QUOTES,'UTF-8')?></title>
  <link rel="icon" type="image/svg+xml" href="<?=htmlspecialchars(asset_url('assets/images/favicon-store.svg'), ENT_QUOTES, 'UTF-8')?>"><link rel="alternate icon" type="image/x-icon" href="<?=htmlspecialchars(asset_url('favicon.ico'), ENT_QUOTES, 'UTF-8')?>"><link rel="apple-touch-icon" href="<?=htmlspecialchars(asset_url('apple-touch-icon.png'), ENT_QUOTES, 'UTF-8')?>">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Playfair+Display:wght@500;600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?=htmlspecialchars(asset_url('assets/css/auth.css'), ENT_QUOTES)?>"><link rel="stylesheet" href="<?=htmlspecialchars(asset_url('assets/css/auth-locale.css'), ENT_QUOTES)?>">
</head>
<body class="auth-page">
  <div class="page-noise" aria-hidden="true"></div>
  <header class="auth-header">
    <a class="brand" href="<?=htmlspecialchars(url(), ENT_QUOTES)?>" aria-label="<?=htmlspecialchars($storeName,ENT_QUOTES,'UTF-8')?> home">
      <span class="brand-mark" aria-hidden="true"><span></span><span></span></span>
      <span class="brand-copy"><strong><?=htmlspecialchars($storeName,ENT_QUOTES,'UTF-8')?></strong><em><?=htmlspecialchars(store_t('ONLINE STORE'),ENT_QUOTES,'UTF-8')?></em></span>
    </a>
    <a class="auth-home-link" href="<?=htmlspecialchars(url(), ENT_QUOTES)?>"><span aria-hidden="true">←</span> BACK TO HOME</a>
  <?php if(count(store_available_languages())>1):?><nav class="auth-languages" aria-label="Language"><?php foreach(store_available_languages() as $loc):?><a href="<?=htmlspecialchars(store_language_url($loc),ENT_QUOTES,'UTF-8')?>" <?=$loc===store_lang()?'aria-current="true"':''?>><?=$loc==='ar'?'العربية':'English'?></a><?php endforeach;?></nav><?php endif;?></header>

  <main class="auth-layout auth-layout-register">
    <section class="auth-intro" aria-label="<?=htmlspecialchars($storeName,ENT_QUOTES,'UTF-8')?>">
      <p class="auth-eyebrow"><span></span> YOUR STORE, YOUR ACCOUNT</p>
      <h1><?=htmlspecialchars(store_t('SHOP YOUR'),ENT_QUOTES,'UTF-8')?><br><strong><?=htmlspecialchars(store_t('FAVOURITES.'),ENT_QUOTES,'UTF-8')?></strong></h1>
      <div class="auth-ornament" aria-hidden="true"><i></i><b></b><i></i></div>
      <p class="auth-description"><?=htmlspecialchars(store_t('Create an account to save products, track orders and manage deliveries.'),ENT_QUOTES,'UTF-8')?></p>
      <div class="auth-system-note"><span class="auth-system-mark" aria-hidden="true">✦</span><span><b><?=htmlspecialchars(strtoupper($storeName),ENT_QUOTES,'UTF-8')?></b><small><?=htmlspecialchars(store_t('CURATED ONLINE STORE'),ENT_QUOTES,'UTF-8')?></small></span></div>
    </section>

    <section class="auth-panel" aria-labelledby="auth-title">
      <div class="auth-panel-top"><span><?=htmlspecialchars(store_t('ACCOUNT REGISTRATION'),ENT_QUOTES,'UTF-8')?></span><span class="auth-panel-index">02 <i></i> 02</span></div>
      <div class="auth-heading">
        <p class="auth-kicker"><?=htmlspecialchars(store_t('START YOUR JOURNEY'),ENT_QUOTES,'UTF-8')?></p>
        <h2 id="auth-title"><?=htmlspecialchars(store_t('Create account'),ENT_QUOTES,'UTF-8')?><span>.</span></h2>
        <p><?=htmlspecialchars(store_t('Set up your customer account.'),ENT_QUOTES,'UTF-8')?></p>
      </div>
      <?php if($error): ?><div class="auth-error" role="alert"><?=htmlspecialchars($error)?></div><?php endif; ?>
      <form method="post" class="auth-form">
        <?=csrf_field()?>
        <div class="auth-field"><label for="username"><?=htmlspecialchars(store_t('USERNAME'),ENT_QUOTES,'UTF-8')?></label><input id="username" name="username" required minlength="3" maxlength="32" pattern="[A-Za-z0-9_]+" autocomplete="username" placeholder="<?=htmlspecialchars(store_t('Choose a username'),ENT_QUOTES,'UTF-8')?>" value="<?=htmlspecialchars((string)$username, ENT_QUOTES, 'UTF-8')?>"></div>
        <div class="auth-field"><label for="email">EMAIL <small style="opacity:.65"><?=htmlspecialchars(store_t('OPTIONAL'),ENT_QUOTES,'UTF-8')?></small></label><input id="email" type="email" name="email" maxlength="254" autocomplete="email" placeholder="you@example.com" value="<?=htmlspecialchars((string)$email, ENT_QUOTES, 'UTF-8')?>"><small><?=htmlspecialchars(store_t('Recommended for password recovery and security alerts. You will verify it after registration.'),ENT_QUOTES,'UTF-8')?></small></div>
        <div class="auth-field"><label for="password"><?=htmlspecialchars(store_t('PASSWORD'),ENT_QUOTES,'UTF-8')?></label><input id="password" type="password" name="password" required minlength="8" maxlength="256" autocomplete="new-password" placeholder="<?=htmlspecialchars(store_t('Minimum 8 characters'),ENT_QUOTES,'UTF-8')?>"></div>
        <div class="auth-field"><label for="confirm_password"><?=htmlspecialchars(store_t('CONFIRM PASSWORD'),ENT_QUOTES,'UTF-8')?></label><input id="confirm_password" type="password" name="confirm_password" required minlength="8" maxlength="256" autocomplete="new-password" placeholder="<?=htmlspecialchars(store_t('Repeat your password'),ENT_QUOTES,'UTF-8')?>"></div>
        <button class="auth-submit" type="submit">CREATE ACCOUNT <span aria-hidden="true">↗</span></button>
      </form>
      <div class="auth-switch"><span><?=htmlspecialchars(store_t('ALREADY HAVE AN ACCOUNT?'),ENT_QUOTES,'UTF-8')?></span><a href="<?=route_url('login.php')?>">SIGN IN <span aria-hidden="true">→</span></a></div>
      <div class="auth-panel-foot"><span></span> SECURE ACCOUNT REGISTRATION</div>
    </section>
  </main>
</body>
</html>
