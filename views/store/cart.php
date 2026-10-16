<?php
require_once __DIR__.'/../../includes/store_layout.php';
$storeName=trim((string)($settings['store_name']??store_runtime_name()))?:store_runtime_name();
$cartCurrencies=array_values(array_unique(array_filter(array_map(static fn($i)=>strtoupper((string)($i['currency']??'')),$items))));$cartCurrency=count($cartCurrencies)===1?$cartCurrencies[0]:(string)($settings['currency']??'EGP');
store_page_start(store_t('Your Bag'),'Review your selected products before checkout.',['store_name'=>$storeName,'user'=>$user,'settings'=>$settings]);
?>
<section class="cart-page-shell">
  <div class="section-heading cart-heading"><div><span><?=store_h(store_t('YOUR SELECTION'))?></span><h1><?=store_h(store_t('Shopping Bag'))?></h1></div><span class="cart-count-copy" data-cart-count-label data-single="<?=store_h(store_t('item'))?>" data-plural="<?=store_h(store_t('items'))?>"><?=store_cart_count()?> <?=store_h(store_t(store_cart_count()===1?'item':'items'))?></span></div>
  <?php if($message):?><div class="store-inline-alert success"><?=store_h($message)?></div><?php endif;?>
  <?php if($error):?><div class="store-inline-alert danger"><?=store_h($error)?></div><?php endif;?>
  <?php if(!$items):?>
    <div class="cart-empty"><span><?=store_h(store_t('YOUR BAG IS WAITING'))?></span><h2><?=store_h(store_t('Find something you love.'))?></h2><p><?=store_h(store_t('Explore the latest products across our store.'))?></p><a class="store-primary-button" href="<?=store_h(store_catalog_url())?>"><?=store_h(store_t('BROWSE PRODUCTS'))?> <?=store_icon('arrow')?></a></div>
  <?php else:?>
  <div class="cart-layout">
    <form class="cart-lines" method="post" action="cart.php" data-cart-live-form><?=csrf_field()?><input type="hidden" name="action" value="update">
      <?php foreach($items as $item):$cartImage=store_image_path($item['image']??'');?>
      <article class="cart-line" data-cart-line="<?=store_h($item['key'])?>">
        <a class="cart-line-media" href="<?=store_h(route_url('product.php').'?slug='.rawurlencode((string)$item['slug']))?>"><?php if($cartImage!==''):?><img data-store-image-fallback-source loading="lazy" decoding="async" src="<?=store_h(url(store_image_variant_path($cartImage,'sm')))?>" alt=""><div data-store-image-fallback hidden><?=store_media_placeholder((string)$item['name'],'cart-media-placeholder')?></div><?php else:?><?=store_media_placeholder((string)$item['name'],'cart-media-placeholder')?><?php endif;?></a>
        <div class="cart-line-copy"><span><?=store_h(strtoupper((string)$item['brand']))?></span><h2><a href="<?=store_h(route_url('product.php').'?slug='.rawurlencode((string)$item['slug']))?>"><?=store_h($item['name'])?></a></h2><p><?=store_h($item['label'])?></p><strong><?=store_h(store_money($item['unit_price'],$item['currency']))?></strong></div>
        <div class="cart-qty"><span><?=store_h(store_t('Quantity'))?></span><span class="cart-qty-control"><button type="button" data-cart-qty-minus aria-label="<?=store_h(store_t('Decrease quantity'))?>">−</button><input type="number" min="1" max="10" name="quantity[<?=store_h($item['key'])?>]" value="<?=(int)$item['quantity']?>" data-cart-qty-input aria-label="<?=store_h(store_t('Quantity for'))?> <?=store_h($item['name'])?>"><button type="button" data-cart-qty-plus aria-label="<?=store_h(store_t('Increase quantity'))?>">+</button></span></div>
        <strong class="cart-line-total" data-cart-line-total><?=store_h(store_money((float)$item['unit_price']*(int)$item['quantity'],$item['currency']))?></strong>
        <button class="cart-remove" type="submit" name="remove_key" value="<?=store_h($item['key'])?>"><?=store_h(store_t('Remove'))?></button>
      </article>
      <?php endforeach;?><p class="cart-live-status" data-cart-live-status aria-live="polite" role="status"></p><div class="cart-update-row"><a href="<?=store_h(store_catalog_url())?>">← <?=store_h(store_t('CONTINUE SHOPPING'))?></a><button class="store-secondary-button" type="submit"><?=store_h(store_t('UPDATE BAG'))?></button></div>
    </form>
    <aside class="cart-summary"><span><?=store_h(store_t('Order Summary'))?></span><div><p><?=store_h(store_t('Subtotal'))?></p><strong data-cart-summary-subtotal><?=store_h(store_money($subtotal,$cartCurrency))?></strong></div><div><p><?=store_h(store_t('Delivery'))?></p><strong><?=store_h(store_t('Calculated at checkout'))?></strong></div><div class="cart-summary-total"><p><?=store_h(store_t('Total'))?></p><strong data-cart-summary-total><?=store_h(store_money($subtotal,$cartCurrency))?></strong></div><a class="store-primary-button cart-checkout-button" href="<?=store_h(route_url('checkout.php'))?>"><?=store_h(store_t('Checkout'))?> <?=store_icon('arrow')?></a><small><?=store_h(store_t('Cash on Delivery or Bank transfer / InstaPay can be selected at checkout. No card payment is taken online.'))?></small><div class="cart-trust-row"><span><?=store_h(store_t('✓ Authentic'))?></span><span><?=store_h(store_t('↩ Easy returns'))?></span><span><?=store_h(store_t('⌁ Delivery across Egypt'))?></span></div></aside>
  </div>
  <?php endif;?>
</section>
<?php store_page_end(); ?>
