<?php
require_once __DIR__.'/../../includes/store_layout.php';
$storeName=trim((string)($settings['store_name']??store_runtime_name()))?:store_runtime_name();
$currency=(string)($items[array_key_first($items)]['currency']??($settings['currency']??'EGP'));
$savedAddresses=is_array($savedAddresses??null)?$savedAddresses:[];
$defaultAddress=null;foreach($savedAddresses as $saved){if(!empty($saved['is_default'])){$defaultAddress=$saved;break;}}
$defaultAddress??=$savedAddresses[0]??null;
$checkoutValue=static function(string $field,string $savedKey='')use($defaultAddress):string{
    $map=['customer_name'=>'recipient_name','phone'=>'phone','governorate'=>'governorate','city'=>'city','address'=>'address_line1','building'=>'building'];
    $key=$savedKey!==''?$savedKey:($map[$field]??$field);
    return (string)($_POST[$field]??($defaultAddress[$key]??''));
};
$checkoutAddress=function()use($checkoutValue,$defaultAddress):string{
    $base=$checkoutValue('address');
    if(array_key_exists('address',$_POST)||!$defaultAddress)return $base;
    $extra=trim((string)($defaultAddress['address_line2']??''));
    return $base.($extra!==''?' · '.$extra:'');
};
$checkoutBuilding=function()use($checkoutValue,$defaultAddress):string{
    $base=$checkoutValue('building');
    if(array_key_exists('building',$_POST)||!$defaultAddress)return $base;
    $pieces=array_filter([$base,($defaultAddress['floor']??'')!==''?store_t('Floor').' '.$defaultAddress['floor']:'',($defaultAddress['apartment']??'')!==''?store_t('Apartment').' '.$defaultAddress['apartment']:'']);
    return implode(', ',$pieces);
};
$postedGovernorate=$checkoutValue('governorate');
$codEnabled=(string)($settings['cod_enabled']??'1')==='1';$manualTransferEnabled=(string)($settings['manual_transfer_enabled']??'1')==='1';
$selectedPayment=(string)($_POST['payment_method']??($codEnabled?'cod':'manual_transfer'));
if($selectedPayment==='cod'&&!$codEnabled)$selectedPayment='manual_transfer';if($selectedPayment==='manual_transfer'&&!$manualTransferEnabled)$selectedPayment='cod';
$paymentLabel=$selectedPayment==='manual_transfer'?'Bank transfer / InstaPay':'Cash on Delivery';
store_page_start(store_t('Checkout'),'Secure checkout and delivery details.',['store_name'=>$storeName,'user'=>$user,'settings'=>$settings,'robots'=>'noindex,nofollow']);
?>
<section class="checkout-shell">
  <div class="checkout-heading"><span><?=store_h(store_t('SECURE CHECKOUT'))?></span><h1><?=store_h(store_t('Delivery & payment'))?></h1><p><?=store_h(store_t('Enter your delivery details and payment choice. Your total updates automatically before you place the order.'))?></p></div>
  <?php if($message):?><div class="store-alert success"><?=store_h(store_public_message_t($message))?></div><?php endif;?>
  <?php if($error):?><div class="store-alert danger"><?=store_h(store_public_message_t($error))?></div><?php endif;?>
  <?php if(!$items):?>
    <div class="cart-empty"><h2><?=store_h(store_t('Your bag is empty.'))?></h2><p><?=store_h(store_t('Add a product before checking out.'))?></p><a class="store-primary-button" href="<?=store_h(store_catalog_url())?>"><?=store_h(store_t('BROWSE PRODUCTS'))?> <?=store_icon('arrow')?></a></div>
  <?php else:?>
  <form class="checkout-layout" method="post" action="checkout.php" data-live-checkout data-quote-url="<?=store_h(route_url('checkout-quote.php'))?>">
    <?=csrf_field()?><input type="hidden" name="checkout_token" value="<?=store_h($checkoutToken)?>"><input type="hidden" name="order_idempotency_key" value="<?=store_h($idempotencyKey)?>"><input type="hidden" name="checkout_action" value="place">
    <div class="checkout-form-column">
      <section class="checkout-panel"><div class="checkout-panel-head"><span>01</span><div><small><?=store_h(store_t('CONTACT'))?></small><h2><?=store_h(store_t('Who should receive the order?'))?></h2></div></div>
        <div class="checkout-fields">
          <label class="checkout-field full"><span><?=store_h(store_t('Full name'))?></span><input name="customer_name" dir="auto" maxlength="160" required autocomplete="name" value="<?=store_h($checkoutValue('customer_name')?:($user['username']??''))?>"></label>
          <label class="checkout-field"><span><?=store_h(store_t('Email'))?> <small><?=store_h(store_t('(recommended)'))?></small></span><input type="email" dir="ltr" name="email" maxlength="190" autocomplete="email" value="<?=store_h((string)($_POST['email']??($user['email']??'')))?>"><small><?=store_h(store_t('Used for order confirmation and updates when email delivery is enabled.'))?></small></label>
          <label class="checkout-field"><span><?=store_h(store_t('Phone'))?></span><input name="phone" dir="ltr" maxlength="40" required inputmode="tel" autocomplete="tel" value="<?=store_h($checkoutValue('phone'))?>"></label>
        </div>
      </section>
      <section class="checkout-panel"><div class="checkout-panel-head"><span>02</span><div><small><?=store_h(store_t('DELIVERY'))?></small><h2><?=store_h(store_t('Where should we deliver?'))?></h2></div></div>
        <?php if($savedAddresses):?>
        <div class="checkout-saved-address"><label for="saved-checkout-address"><?=store_h(store_t('Saved address'))?> <small><?=store_h(store_t('optional'))?></small></label>
          <select id="saved-checkout-address" name="saved_address_id" data-saved-checkout-address>
            <option value=""><?=store_h(store_t('Enter another address manually'))?></option>
            <?php foreach($savedAddresses as $a):?>
            <option value="<?=(int)$a['id']?>" <?=(!array_key_exists('governorate',$_POST)&&$defaultAddress&&(int)$a['id']===(int)$defaultAddress['id'])?'selected':''?>
              data-customer-name="<?=store_h($a['recipient_name'])?>" data-phone="<?=store_h($a['phone'])?>" data-governorate="<?=store_h($a['governorate'])?>" data-city="<?=store_h($a['city'])?>"
              data-address="<?=store_h(trim($a['address_line1'].(($a['address_line2']??'')!==''?' · '.$a['address_line2']:'')))?>"
              data-building="<?=store_h(implode(', ',array_filter([$a['building'],$a['floor']!==''?store_t('Floor').' '.$a['floor']:'',$a['apartment']!==''?store_t('Apartment').' '.$a['apartment']:''])))?>">
              <?=store_h(($a['label']?:store_t('Address')).' — '.$a['city'].', '.$a['governorate'])?><?=!empty($a['is_default'])?' ('.store_t('Default').')':''?>
            </option><?php endforeach;?>
          </select><a href="<?=store_h(route_url('account-addresses.php'))?>"><?=store_h(store_t('Manage saved addresses →'))?></a>
        </div>
        <?php elseif($user):?><p class="checkout-address-hint"><?=store_h(store_t('Want faster checkout next time? '))?><a href="<?=store_h(route_url('account-addresses.php'))?>"><?=store_h(store_t('Save a delivery address'))?></a>.</p><?php endif;?>

        <div class="checkout-fields">
          <label class="checkout-field"><span><?=store_h(store_t('Governorate'))?></span><select name="governorate" required autocomplete="address-level1"><option value=""><?=store_h(store_t('Choose governorate'))?></option><?php foreach($governorates as $gov):?><option value="<?=store_h($gov)?>" <?=$postedGovernorate===$gov?'selected':''?>><?=store_h(store_t($gov))?></option><?php endforeach;?></select><small><?=store_h(store_t('Delivery fee and ETA update automatically. Governorates without a dedicated zone use the active default / Rest of Egypt zone.'))?></small></label>
          <label class="checkout-field"><span><?=store_h(store_t('City / area'))?></span><input name="city" dir="auto" maxlength="120" required autocomplete="address-level2" value="<?=store_h($checkoutValue('city'))?>" placeholder="<?=store_h(store_t('Nasr City'))?>"></label>
          <label class="checkout-field full"><span><?=store_h(store_t('Street address'))?></span><textarea name="address" dir="auto" maxlength="500" required autocomplete="street-address" placeholder="<?=store_h(store_t('Street, district and delivery details'))?>"><?=store_h($checkoutAddress())?></textarea></label>
          <label class="checkout-field full"><span><?=store_h(store_t('Building / floor / apartment'))?> <small><?=store_h(store_t('(optional)'))?></small></span><input name="building" dir="auto" maxlength="160" value="<?=store_h($checkoutBuilding())?>"></label>
        </div>
        <?php if($user):?>
        <div class="checkout-address-save"><label class="checkout-save-address-option"><input type="checkbox" name="save_delivery_address" value="1" data-checkout-save-address <?=!empty($_POST['save_delivery_address'])?'checked':''?>><span><?=store_h(store_t('Save this delivery address for next time'))?></span></label><label class="checkout-save-address-label" data-checkout-address-label hidden><?=store_h(store_t('Address label'))?> <small><?=store_h(store_t('optional'))?></small><input name="delivery_address_label" maxlength="80" placeholder="<?=store_h(store_t('Home, Work…'))?>" value="<?=store_h((string)($_POST['delivery_address_label']??''))?>"></label><small><?=store_h(store_t('Saved to your account after a successful order. You can edit or remove it any time.'))?></small></div>
        <?php endif;?>
      </section>
      <section class="checkout-panel"><div class="checkout-panel-head"><span>03</span><div><small><?=store_h(store_t('PAYMENT'))?></small><h2><?=store_h(store_t('How would you like to pay?'))?></h2></div></div><div class="checkout-payment-options" role="radiogroup" aria-label="<?=store_h(store_t('Payment method'))?>"><?php if($codEnabled):?><label class="checkout-payment-option <?=$selectedPayment==='cod'?'is-selected':''?>"><input type="radio" name="payment_method" value="cod" <?=$selectedPayment==='cod'?'checked':''?>><span><strong><?=store_h(store_t('Cash on Delivery'))?></strong><small><?=store_h(store_t('Pay when your order arrives.'))?></small></span><b><?=store_h(store_t('COD'))?></b></label><?php endif;?><?php if($manualTransferEnabled):?><label class="checkout-payment-option <?=$selectedPayment==='manual_transfer'?'is-selected':''?>"><input type="radio" name="payment_method" value="manual_transfer" <?=$selectedPayment==='manual_transfer'?'checked':''?>><span><strong><?=store_h(store_t('Bank transfer / InstaPay'))?></strong><small><?=store_h(store_t('Place the order now. Our team will contact you with Bank transfer / InstaPay details. No card details are entered here and no payment gateway opens.'))?></small></span><b><?=store_h(store_t('MANUAL'))?></b></label><?php endif;?></div></section>
      <section class="checkout-panel"><div class="checkout-fields"><label class="checkout-field"><span><?=store_h(store_t('Coupon code'))?> <small><?=store_h(store_t('(optional)'))?></small></span><input name="coupon_code" maxlength="80" value="<?=store_h((string)($_POST['coupon_code']??''))?>" placeholder="WELCOME10"></label><label class="checkout-field full"><span><?=store_h(store_t('Order note'))?> <small><?=store_h(store_t('(optional)'))?></small></span><textarea name="customer_note" maxlength="1000" placeholder="<?=store_h(store_t('Anything the delivery team should know?'))?>"><?=store_h((string)($_POST['customer_note']??''))?></textarea></label></div></section>
    </div>
    <aside class="checkout-summary" data-checkout-summary>
      <span><?=store_h(store_t('YOUR ORDER'))?></span>
      <div class="checkout-summary-items"><?php foreach($items as $item):?><div class="checkout-summary-item"><div class="checkout-summary-item-copy"><strong><?=store_h($item['name'])?></strong><small><span><?=store_h($item['label'])?></span><span><?=store_h(store_t('Qty'))?> <?=(int)$item['quantity']?></span></small></div><b><?=store_h(store_money((float)$item['unit_price']*(int)$item['quantity'],(string)$item['currency']))?></b></div><?php endforeach;?></div>
      <div class="checkout-summary-row"><span><?=store_h(store_t('Subtotal'))?></span><strong data-checkout-subtotal><?=store_h(store_money($subtotal,$currency))?></strong></div>
      <div class="checkout-summary-row checkout-shipping-row"><span><?=store_h(store_t('Delivery'))?><small data-checkout-shipping-note><?=store_h(store_t('Choose your delivery area to calculate shipping.'))?></small></span><strong data-checkout-shipping>—</strong></div>
      <div class="checkout-summary-row checkout-discount" data-checkout-discount-row hidden><span><?=store_h(store_t('Discount'))?> <small data-checkout-coupon></small></span><strong data-checkout-discount></strong></div>
      <div class="checkout-delivery-note" data-checkout-delivery-note hidden><span><?=store_h(store_t('DELIVER TO'))?></span><strong data-checkout-zone></strong><small data-checkout-days></small></div>
      <div class="checkout-summary-row checkout-payment-summary"><span><?=store_h(store_t('Payment'))?></span><strong data-checkout-payment><?=store_h(store_t($paymentLabel))?></strong></div>
      <div class="checkout-summary-total"><span><?=store_h(store_t('ORDER TOTAL'))?></span><strong data-checkout-total><?=store_h(store_t('Complete delivery details'))?></strong></div>
      <div class="checkout-summary-actions">
        <button class="store-primary-button checkout-place-button" type="submit" data-checkout-place><?=store_h(store_t('PLACE ORDER'))?></button>
        <small class="checkout-quote-status" data-checkout-quote-status aria-live="polite"><?=store_h(store_t('Your exact total updates automatically. The server verifies price and stock again when you place the order.'))?></small>
        <small class="checkout-legal" data-checkout-payment-legal><?php if($selectedPayment==='manual_transfer'):?><?=store_h(store_t('No payment is taken online. We will contact you with Bank transfer / InstaPay details.'))?><?php else:?><?=store_h(store_t('Payment is collected on delivery.'))?><?php endif;?></small>
      </div>
    </aside>
  </form>
  <?php endif;?>
</section>
<?php store_page_end(); ?>
