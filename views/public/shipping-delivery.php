<?php
require_once __DIR__.'/../../includes/public_layout.php';
$settings=is_array($settings??null)?$settings:store_runtime_settings();
$zones=is_array($zones??null)?$zones:[];
$currency=strtoupper(trim((string)($settings['currency']??'EGP'))?:'EGP');
$standardFee=max(0,(float)($settings['standard_shipping_fee']??0));
$freeThreshold=max(0,(float)($settings['free_shipping_threshold']??0));
$codEnabled=(string)($settings['cod_enabled']??'1')==='1';$manualTransferEnabled=(string)($settings['manual_transfer_enabled']??'1')==='1';
$configuredBlocks=json_decode((string)($settings['page_shipping_blocks_json']??''),true);if(!is_array($configuredBlocks))$configuredBlocks=[['heading'=>'When is the delivery fee final?','body'=>'The amount shown at checkout is the amount saved with your order. If the store changes its delivery settings later, your existing order keeps its original delivery snapshot.'],['heading'=>'Need help with an address?','body'=>'If your area is not accepted at checkout, contact customer support before placing a different address. We do not recommend submitting an address that does not match the actual delivery destination.']];
$money=static fn(float $v):string=>number_format($v,($v==(float)(int)$v)?0:2).' '.$currency;
public_layout_start([
    'title'=>'Shipping & Delivery · '.store_runtime_name(),
    'description'=>'Delivery coverage, shipping fees, estimated delivery times and Cash on Delivery information.',
    'canonical'=>rtrim((string)CANONICAL_SITE_URL,'/').route_url('shipping-delivery.php'),
]);
?>
<div class="public-content-main">
  <section class="public-content-hero"><div class="eyebrow">DELIVERY</div><h1><?=store_h((string)($settings['page_shipping_heading']??'Shipping, without the guessing.'))?></h1><p><?=store_h((string)($settings['page_shipping_intro']??'See the delivery rules currently configured for the store before you place an order.'))?></p></section>
  <section class="public-info-grid" aria-label="Delivery summary">
    <article><small>STANDARD DELIVERY</small><strong><?=$money($standardFee)?></strong><p>Checkout always shows the final delivery charge before the order is created.</p></article>
    <article><small>FREE DELIVERY</small><strong><?=$freeThreshold>0?'From '.$money($freeThreshold):'Not configured'?></strong><p>Zone-specific thresholds can override the store-wide threshold.</p></article>
    <article><small>PAYMENT</small><strong><?=store_h(implode(' + ',array_filter([$codEnabled?'Cash on Delivery':'',$manualTransferEnabled?'Bank transfer / InstaPay':''])))?></strong><p>Bank transfer / InstaPay is arranged after the order; no card details are collected online.</p></article>
  </section>
  <section class="public-prose public-policy-section">
    <h2>Delivery coverage</h2>
    <p>Enter your delivery address at checkout. The store validates whether your governorate is covered and calculates the applicable delivery fee before you confirm the order.</p>
    <?php if($zones):?>
      <div class="public-zone-table" role="region" aria-label="Configured delivery areas" tabindex="0"><table><thead><tr><th>Area</th><th>Delivery fee</th><th>Free from</th><th>Estimate</th><th>COD</th></tr></thead><tbody><?php foreach($zones as $zone):$fee=max(0,(float)($zone['fee']??0));$threshold=$zone['free_shipping_threshold']!==null?(float)$zone['free_shipping_threshold']:null;$min=$zone['estimated_days_min']!==null?(int)$zone['estimated_days_min']:null;$max=$zone['estimated_days_max']!==null?(int)$zone['estimated_days_max']:null;$estimate=$min!==null?($max!==null&&$max!==$min?$min.'–'.$max.' days':$min.' day'.($min===1?'':'s')):'Shown at checkout';?><tr><td><strong><?=htmlspecialchars((string)($zone['governorate']??$zone['name']??'Delivery area'),ENT_QUOTES,'UTF-8')?></strong></td><td><?=$money($fee)?></td><td><?=$threshold!==null&&$threshold>0?$money($threshold):'—'?></td><td><?=htmlspecialchars($estimate,ENT_QUOTES,'UTF-8')?></td><td><?=!empty($zone['cod_enabled'])?'Available':'Unavailable'?></td></tr><?php endforeach;?></tbody></table></div>
    <?php else:?><div class="public-policy-callout"><strong>Standard delivery rules apply.</strong><p>No governorate-specific public delivery table is configured. Checkout will calculate the current store-wide delivery fee and availability.</p></div><?php endif;?>
    <?php foreach($configuredBlocks as $block):$bh=trim((string)($block['heading']??''));$bb=trim((string)($block['body']??''));if($bh===''||$bb==='')continue;?><h2><?=store_h($bh)?></h2><p><?=nl2br(store_h($bb),false)?></p><?php endforeach;?>
    <div class="public-policy-actions"><a class="store-primary-button" href="<?=htmlspecialchars(route_url('perfumes.php'),ENT_QUOTES,'UTF-8')?>">Shop perfumes</a><a class="store-secondary-button" href="<?=htmlspecialchars(route_url('support.php'),ENT_QUOTES,'UTF-8')?>">Contact support</a></div>
  </section>
</div>
<?php public_layout_end(); ?>
