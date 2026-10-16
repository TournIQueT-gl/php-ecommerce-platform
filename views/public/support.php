<?php
public_layout_start([
    'title'=>'Customer Support',
    'description'=>'Contact '.store_runtime_name().' customer support for order, delivery, product and account help.',
    'canonical'=>store_absolute_url(route_url('support.php')),
]);
$settings=store_runtime_settings();
$hasEmail=$contactEmail!=='';
$hasWhatsapp=$whatsapp!=='';
$blocks=json_decode((string)($settings['page_support_blocks_json']??''),true);if(!is_array($blocks))$blocks=[];
?>
<section class="public-legal public-support-page">
  <div class="public-legal-head"><p>CUSTOMER CARE</p><h1><?=store_h((string)($settings['page_support_heading']??'How can we help?'))?></h1><span><?=store_h((string)($settings['page_support_intro']??'Guest checkout is supported. You do not need an account to contact us.'))?></span></div>
  <div class="public-support-grid">
    <article><small>ORDER HELP</small><h2>Already placed an order?</h2><p>Keep your order number and private tracking link ready. They help us verify the order without asking you to create an account.</p><a class="store-btn dark" href="<?=store_h(route_url('track-order.php'))?>">TRACK AN ORDER</a></article>
    <article><small>CONTACT</small><h2>Talk to the store team</h2><?php if($hasWhatsapp):?><p>For guest orders and time-sensitive delivery questions, WhatsApp is the fastest public support channel.</p><a class="store-btn dark" href="<?=store_h($whatsapp)?>" target="_blank" rel="noopener noreferrer">OPEN WHATSAPP ↗</a><?php elseif($hasEmail):?><p>Email us with your order number, account username (if any), and a short description of the issue.</p><a class="store-btn dark" href="mailto:<?=store_h($contactEmail)?>">EMAIL SUPPORT</a><?php else:?><p>Public contact details have not been configured yet. If you already have an account, sign in to open a private support ticket.</p><a class="store-btn dark" href="<?=store_h(route_url('login.php'))?>">SIGN IN FOR SUPPORT</a><?php endif;?></article>
    <article><small>ACCOUNT HOLDERS</small><h2>Private support tickets</h2><p>Signed-in customers can keep a complete support conversation, attachments and replies inside their account.</p><a class="store-btn outline" href="<?=store_h(route_url('login.php'))?>">SIGN IN</a></article>
  </div>
  <?php if($blocks):?><div class="public-prose public-support-extra"><?php foreach($blocks as $block):$bh=trim((string)($block['heading']??''));$bb=trim((string)($block['body']??''));if($bh===''||$bb==='')continue;?><h2><?=store_h($bh)?></h2><p><?=nl2br(store_h($bb),false)?></p><?php endforeach;?></div><?php endif;?>
</section>
<?php public_layout_end(); ?>
