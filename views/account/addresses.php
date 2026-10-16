<?php
$h=static fn(mixed $v):string=>htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');
account_page_start('Saved addresses','addresses','MY ACCOUNT','Saved','addresses','Save your delivery details for faster checkout.',$user);
?>
<?php if($message):?><div class="app-alert dashboard-alert success" role="status"><?=$h($message)?></div><?php endif;?>
<?php if($error):?><div class="app-alert dashboard-alert danger" role="alert"><?=$h($error)?></div><?php endif;?>
<section class="app-panel dashboard-card account-address-manager">
 <div class="tool-heading"><div><p class="card-kicker"><?=store_h(store_t('YOUR ADDRESSES'))?></p><h2><?=count($addresses)?> <?=store_h(store_t(count($addresses)===1?'saved address':'saved addresses'))?></h2></div><button type="button" class="app-button primary" data-address-new><?=store_h(store_t('Add address +'))?></button></div>
 <?php if(!$addresses):?><p class="account-help-text"><?=store_h(store_t('No saved addresses yet. Add one to fill your delivery details automatically at checkout.'))?></p><?php endif;?>
 <div class="account-address-list">
 <?php foreach($addresses as $address):?>
  <article class="account-address-card"><div><strong><?=$h($address['label']?:store_t('Delivery address'))?></strong><?php if($address['is_default']):?><small class="account-address-default"><?=store_h(store_t('DEFAULT'))?></small><?php endif;?><p><?=$h($address['recipient_name'])?> · <?=$h($address['phone'])?></p><p><?=$h($address['address_line1'])?>, <?=$h($address['city'])?>, <?=$h($address['governorate'])?></p></div>
  <div class="account-address-actions"><button class="app-button secondary small" type="button" data-address-edit data-address-json="<?=$h(json_encode($address,JSON_UNESCAPED_UNICODE|JSON_INVALID_UTF8_SUBSTITUTE|JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT))?>"><?=store_h(store_t('Edit'))?></button>
   <?php if(!$address['is_default']):?><form method="post" action="account-addresses.php"><?=csrf_field()?><input type="hidden" name="action" value="make_default"><input type="hidden" name="id" value="<?=(int)$address['id']?>"><button class="app-button secondary small" type="submit"><?=store_h(store_t('Make default'))?></button></form><?php endif;?>
   <form method="post" action="account-addresses.php"><?=csrf_field()?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?=(int)$address['id']?>"><button class="app-button secondary small" type="submit"><?=store_h(store_t('Remove'))?></button></form>
  </div></article>
 <?php endforeach;?></div>
</section>
<section class="app-panel dashboard-card account-address-manager" id="address-editor">
 <div class="tool-heading"><div><p class="card-kicker"><?=store_h(store_t('ADDRESS DETAILS'))?></p><h2 data-address-heading><?=store_h(store_t('Add delivery address'))?></h2></div></div>
 <form method="post" action="account-addresses.php" class="account-address-form" data-address-form>
 <?=csrf_field()?><input type="hidden" name="action" value="save"><input type="hidden" name="id" value="0">
 <label><?=store_h(store_t('Label'))?> <small><?=store_h(store_t('optional'))?></small><input name="label" maxlength="80" placeholder="<?=store_h(store_t('Home / Work'))?>"></label>
 <label><?=store_h(store_t('Recipient name'))?><input name="recipient_name" maxlength="160" required autocomplete="name"></label>
 <label><?=store_h(store_t('Phone'))?><input name="phone" maxlength="40" required autocomplete="tel" inputmode="tel"></label>
 <label><?=store_h(store_t('Governorate'))?><select name="governorate" required><option value=""><?=store_h(store_t('Choose governorate'))?></option><?php foreach($governorates as $gov):?><option value="<?=$h($gov)?>"><?=$h(store_t($gov))?></option><?php endforeach;?></select></label>
 <label><?=store_h(store_t('City / area'))?><input name="city" maxlength="120" required></label>
 <label class="wide"><?=store_h(store_t('Street address'))?><input name="address_line1" maxlength="255" required autocomplete="street-address"></label>
 <label class="wide"><?=store_h(store_t('Additional address line'))?> <small><?=store_h(store_t('optional'))?></small><input name="address_line2" maxlength="255"></label>
 <label><?=store_h(store_t('Building'))?> <small><?=store_h(store_t('optional'))?></small><input name="building" maxlength="80"></label>
 <label><?=store_h(store_t('Floor'))?> <small><?=store_h(store_t('optional'))?></small><input name="floor" maxlength="40"></label>
 <label><?=store_h(store_t('Apartment'))?> <small><?=store_h(store_t('optional'))?></small><input name="apartment" maxlength="40"></label>
 <label><?=store_h(store_t('Postal code'))?> <small><?=store_h(store_t('optional'))?></small><input name="postal_code" maxlength="40"></label>
 <label class="wide"><?=store_h(store_t('Delivery instructions'))?> <small><?=store_h(store_t('optional'))?></small><textarea name="notes" maxlength="500" rows="2"></textarea></label>
 <label class="wide account-address-checkbox"><input type="checkbox" name="is_default" value="1"> <?=store_h(store_t('Use as my default delivery address'))?></label>
 <div class="account-address-actions wide"><button class="app-button primary" type="submit"><?=store_h(store_t('Save address'))?></button><button type="button" class="app-button secondary" data-address-new><?=store_h(store_t('Clear form'))?></button></div>
 </form>
</section>

<?php account_page_end(); ?>
