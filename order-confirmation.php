<?php
declare(strict_types=1);
require_once __DIR__.'/includes/auth.php';
require_once __DIR__.'/includes/store_layout.php';
init_web_request();
header('Cache-Control: no-store, private');header('Pragma: no-cache');header('Referrer-Policy: no-referrer');
$order=$_SESSION['_last_order']??null;
if(!is_array($order)){header('Location: '.route_url('index.php'),true,302);exit;}
$settings=store_runtime_settings();$user=null;try{$user=current_user();}catch(Throwable){}
if(!$user&&!empty($order['email'])&&filter_var((string)$order['email'],FILTER_VALIDATE_EMAIL))$_SESSION['_register_email_prefill']=strtolower(trim((string)$order['email']));
$paymentMethod=(string)($order['payment_method']??'cod');
$paymentCopy=$paymentMethod==='manual_transfer'
    ? 'No payment was taken online. Our team will contact you with Bank transfer / InstaPay details, and your payment will stay pending until the transfer is confirmed.'
    : 'Payment will be collected when your order is delivered.';
store_page_start('Order Confirmed','Your order has been received.',['store_name'=>(string)($settings['store_name']??store_runtime_name()),'user'=>$user,'settings'=>$settings,'robots'=>'noindex,nofollow']);
?>
<section class="checkout-shell order-confirmation-shell"><div class="checkout-confirmation-card">
  <span>ORDER RECEIVED</span>
  <h1>Thank you, <?=store_h($order['customer_name']??'')?>.</h1>
  <p>Your order <strong><?=store_h($order['order_number']??'')?></strong> has been placed successfully. <?=store_h($paymentCopy)?></p>
  <div class="checkout-confirmation-total"><small>TOTAL</small><strong><?=store_h(store_money($order['grand_total']??0,(string)($order['currency']??'EGP')))?></strong></div>
  <p>We’ll use <?=store_h($order['phone']??'your phone number')?> for delivery coordination.</p>
  <?php if(!empty($_SESSION['_checkout_address_notice'])):?><p class="checkout-address-notice" role="status"><?=store_h((string)$_SESSION['_checkout_address_notice'])?></p><?php unset($_SESSION['_checkout_address_notice']);endif;?>
  <?php $guestToken=trim((string)($order['guest_access_token']??''));if(!$user&&$guestToken!==''):?>
    <div class="checkout-private-link-note"><strong>Keep your private tracking link.</strong><p>It lets you reopen this order after this browser session ends. Create or sign in to an account in this browser and this specific order will be added automatically.</p></div>
  <?php endif;?>
  <div class="checkout-confirmation-actions">
    <a class="store-primary-button" href="<?=store_h(route_url('perfumes.php'))?>">CONTINUE SHOPPING</a>
    <?php if($user):?><a class="store-secondary-button" href="<?=store_h(route_url('account-orders.php'))?>">VIEW MY ORDERS</a>
    <?php elseif($guestToken!==''):?>
      <a class="store-secondary-button" href="<?=store_h(route_url('register.php'))?>">CREATE ACCOUNT & SAVE ORDER</a>
      <a class="store-text-link" href="<?=store_h(route_url('track-order.php').'?order='.rawurlencode((string)($order['order_number']??'')).'&token='.rawurlencode($guestToken))?>">Track this order</a>
    <?php endif;?>
  </div>
</div></section>
<?php store_page_end(); ?>
