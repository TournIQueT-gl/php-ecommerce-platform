<?php
require_once __DIR__.'/../../includes/public_layout.php';
$settings=is_array($settings??null)?$settings:store_runtime_settings();
$returnWindow=max(1,(int)($settings['return_window_days']??14));
$returnIntro=(string)($settings['page_returns_intro']??'');if(trim($returnIntro)==='')$returnIntro='Eligible delivered orders can be submitted for review within '.number_format($returnWindow).' days of delivery.';
$configuredBlocks=json_decode((string)($settings['page_returns_blocks_json']??''),true);if(!is_array($configuredBlocks))$configuredBlocks=[['heading'=>'How to request a return','body'=>'Open the delivered order from your account, choose the eligible item and quantity, explain the reason and submit it for review. Guest customers can contact support with the order number.'],['heading'=>'What happens after approval?','body'=>'Approved quantities can be restocked when appropriate. Refunds remain attached to the original order and cannot exceed the amount eligible for refund.'],['heading'=>'What can affect eligibility?','body'=>'Eligibility depends on the delivered order, the configured return window, purchased quantity, previous returns and the store review.']];
public_layout_start([
    'title'=>'Returns & Refunds · '.store_runtime_name(),
    'description'=>'How return requests, eligibility review, restocking and refunds work for perfume orders.',
    'canonical'=>rtrim((string)CANONICAL_SITE_URL,'/').route_url('returns.php'),
]);
?>
<div class="public-content-main">
  <section class="public-content-hero"><div class="eyebrow">RETURNS & REFUNDS</div><h1><?=store_h((string)($settings['page_returns_heading']??'Clear steps when an order needs to come back.'))?></h1><p><?=store_h($returnIntro)?></p></section>
  <section class="public-info-grid" aria-label="Return summary">
    <article><small>REQUEST WINDOW</small><strong><?=number_format($returnWindow)?> days</strong><p>The window starts after the order is delivered.</p></article>
    <article><small>REQUEST STATUS</small><strong>Reviewed first</strong><p>A return request is not automatically an approved refund.</p></article>
    <article><small>ORDER HISTORY</small><strong>Preserved</strong><p>Order and refund records remain attached to the original purchase.</p></article>
  </section>
  <section class="public-prose public-policy-section">
    <?php foreach($configuredBlocks as $block):$bh=trim((string)($block['heading']??''));$bb=trim((string)($block['body']??''));if($bh===''||$bb==='')continue;?><h2><?=store_h($bh)?></h2><p><?=nl2br(store_h($bb),false)?></p><?php endforeach;?>
    <div class="public-policy-callout"><strong>Do not send a return without an approved request.</strong><p>Use the account return flow or contact support first so the order, items and quantities can be matched correctly.</p></div>
    <div class="public-policy-actions"><a class="store-primary-button" href="<?=htmlspecialchars(route_url('account-orders.php'),ENT_QUOTES,'UTF-8')?>">View my orders</a><a class="store-secondary-button" href="<?=htmlspecialchars(route_url('support.php'),ENT_QUOTES,'UTF-8')?>">Contact support</a></div>
  </section>
</div>
<?php public_layout_end(); ?>
