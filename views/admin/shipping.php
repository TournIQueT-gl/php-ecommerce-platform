<?php
admin_page_start('Shipping','shipping','ORDERS','Shipping','Zones','Manage delivery fees, Cash on Delivery and delivery estimates by governorate.');
$h=static fn(mixed $v):string=>htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');
$money=static fn(mixed $v):string=>number_format((float)$v,0);
?>
<?php if($message):?><div class="app-alert admin-alert success"><?=$h($message)?></div><?php endif;?><?php if($error):?><div class="app-alert admin-alert danger"><?=$h($error)?></div><?php endif;?>
<?php if(!$ready):?><section class="app-panel admin-panel admin-card-pad"><h2>Commerce migration required</h2><p class="admin-muted">Run the current database migrations before managing shipping zones.</p></section><?php admin_page_end();return;endif;?>

<details class="app-panel admin-panel admin-create-panel">
  <summary class="admin-create-summary"><span><small>NEW DELIVERY AREA</small><strong>Add shipping zone</strong></span><em>+</em></summary>
  <div class="admin-create-body">
    <p class="admin-muted">Use one zone per governorate. Leave governorate blank only for one default fallback zone.</p>
    <form method="post" class="admin-form-grid admin-form-grid-compact"><?=csrf_field()?><input type="hidden" name="action" value="save">
      <label class="app-field admin-field"><span>Zone name</span><input name="name" maxlength="140" required placeholder="Cairo delivery"></label>
      <label class="app-field admin-field"><span>Governorate</span><select name="governorate"><option value="">Rest of Egypt / default</option><?php foreach($governorates as $gov):?><option value="<?=$h($gov)?>"><?=$h($gov)?></option><?php endforeach;?></select><small>Use one default zone for every governorate that does not have its own row.</small></label>
      <label class="app-field admin-field"><span>Delivery fee (<?=$h($currency)?>)</span><input type="number" min="0" step="0.01" name="fee" value="0" required></label>
      <label class="app-field admin-field"><span>Free shipping from</span><input type="number" min="0" step="0.01" name="free_shipping_threshold" placeholder="Optional"></label>
      <label class="app-field admin-field"><span>Minimum days</span><input type="number" min="1" max="90" name="estimated_days_min"></label>
      <label class="app-field admin-field"><span>Maximum days</span><input type="number" min="1" max="90" name="estimated_days_max"></label>
      <label class="app-field admin-field"><span>Sort order</span><input type="number" name="sort_order" value="0"></label>
      <label class="app-field admin-field"><span>Status</span><select name="status"><option value="active">Active</option><option value="inactive">Inactive</option></select></label>
      <label class="admin-checkbox-line admin-field-wide"><input type="checkbox" name="cod_enabled" value="1" checked> Cash on Delivery allowed</label>
      <div class="admin-field-wide admin-form-actions"><button class="app-button admin-button primary" type="submit">Add shipping zone</button></div>
    </form>
  </div>
</details>

<section class="app-panel admin-panel admin-card-pad admin-section-space">
  <div class="admin-panel-head"><div><p class="panel-kicker">DELIVERY MATRIX</p><h2>Configured zones</h2><p class="admin-muted">Checkout uses these values directly. Open a row only when you need to edit it.</p></div><span class="app-badge status-pill status-active"><i></i><?=count($rows)?> ZONES</span></div>
  <?php if(!$rows):?><div class="admin-empty-state"><strong>No shipping zones yet.</strong><p>Checkout will fall back to the global Store Settings delivery fee until you add zones.</p></div>
  <?php else:?>
  <form id="shipping-bulk-form" method="post" class="admin-bulk-bar" data-bulk-form data-bulk-kind="shipping zones"><?=csrf_field()?><input type="hidden" name="action" value="bulk_shipping"><label class="admin-row-check admin-bulk-select-label" title="Select all shipping zones"><input type="checkbox" data-bulk-select-all aria-label="Select all shipping zones"><span></span></label><div class="admin-bulk-summary"><strong data-bulk-count>0 selected</strong><small>Enable, disable or permanently delete several delivery areas.</small></div><label class="admin-bulk-action"><span>Bulk action</span><select name="bulk_action" data-bulk-action><option value="">Choose action</option><option value="enable">Enable selected</option><option value="disable">Disable selected</option><option value="delete">Delete selected permanently</option></select></label><button class="app-button admin-button secondary" type="submit" data-bulk-apply disabled>Apply</button></form>
  <div class="shipping-zone-directory" role="list">
    <div class="shipping-zone-columns" aria-hidden="true"><span>Area</span><span>Fee</span><span>Free from</span><span>Delivery</span><span>COD</span><span>Status</span><span></span></div>
    <?php foreach($rows as $z):$active=(string)$z['status']==='active';$gov=trim((string)($z['governorate']??''));$min=(int)($z['estimated_days_min']??0);$max=(int)($z['estimated_days_max']??0);?>
    <details class="shipping-zone-record" role="listitem" data-bulk-row <?=$openZoneId===(int)$z['id']?'open':''?>>
      <summary>
        <span class="shipping-zone-name"><label class="admin-row-check admin-shipping-bulk-check" title="Select <?=$h($z['name'])?>"><input type="checkbox" name="shipping_ids[]" value="<?=(int)$z['id']?>" form="shipping-bulk-form" data-bulk-item aria-label="Select <?=$h($z['name'])?>"><span></span></label><span><strong><?=$h($z['name'])?></strong><small><?=$h($gov!==''?$gov:'Default / other areas')?></small></span></span>
        <strong><?=$money($z['fee'])?> <?=$h($currency)?></strong>
        <span><?=$z['free_shipping_threshold']!==null&&$z['free_shipping_threshold']!==''?$money($z['free_shipping_threshold']).' '.$h($currency):'—'?></span>
        <span><?=$min>0?($min.($max>0&&$max!==$min?'–'.$max:'').' days'):'—'?></span>
        <span class="shipping-cod-state <?=!empty($z['cod_enabled'])?'is-on':'is-off'?>"><?=!empty($z['cod_enabled'])?'Yes':'No'?></span>
        <span class="status-badge <?=$active?'status-active':'status-disabled'?>"><?=$active?'ACTIVE':'INACTIVE'?></span>
        <span class="shipping-zone-edit">Edit</span>
      </summary>
      <form method="post" class="shipping-zone-form"><?=csrf_field()?><input type="hidden" name="action" value="save"><input type="hidden" name="id" value="<?=(int)$z['id']?>">
        <div class="admin-form-grid admin-form-grid-compact">
          <label class="app-field admin-field"><span>Zone name</span><input name="name" maxlength="140" value="<?=$h($z['name'])?>" required></label>
          <label class="app-field admin-field"><span>Governorate</span><select name="governorate"><option value="" <?=$gov===''?'selected':''?>>Rest of Egypt / default</option><?php if($gov!==''&&!in_array($gov,$governorates,true)):?><option value="<?=$h($gov)?>" selected><?=$h($gov)?> · legacy value</option><?php endif;?><?php foreach($governorates as $g):?><option value="<?=$h($g)?>" <?=$gov===$g?'selected':''?>><?=$h($g)?></option><?php endforeach;?></select></label>
          <label class="app-field admin-field"><span>Fee (<?=$h($currency)?>)</span><input type="number" min="0" step="0.01" name="fee" value="<?=$h($z['fee'])?>"></label>
          <label class="app-field admin-field"><span>Free from</span><input type="number" min="0" step="0.01" name="free_shipping_threshold" value="<?=$h($z['free_shipping_threshold']??'')?>"></label>
          <label class="app-field admin-field"><span>Days min</span><input type="number" min="1" max="90" name="estimated_days_min" value="<?=$h($z['estimated_days_min']??'')?>"></label>
          <label class="app-field admin-field"><span>Days max</span><input type="number" min="1" max="90" name="estimated_days_max" value="<?=$h($z['estimated_days_max']??'')?>"></label>
          <label class="app-field admin-field"><span>Sort order</span><input type="number" name="sort_order" value="<?=(int)$z['sort_order']?>"></label>
          <label class="app-field admin-field"><span>Status</span><select name="status"><option value="active" <?=$active?'selected':''?>>Active</option><option value="inactive" <?=$active?'':'selected'?>>Inactive</option></select></label>
          <label class="admin-checkbox-line admin-field-wide"><input type="checkbox" name="cod_enabled" value="1" <?=!empty($z['cod_enabled'])?'checked':''?>> Cash on Delivery allowed</label>
        </div>
        <div class="shipping-zone-actions"><button class="app-button admin-button primary" type="submit">Save changes</button><?php if($active):?><button class="app-button admin-button secondary" type="submit" name="action" value="archive" formnovalidate data-confirm="Disable this shipping zone?" data-confirm-tone="warning">Disable</button><?php else:?><button class="app-button admin-button secondary" type="submit" name="action" value="enable" formnovalidate>Enable</button><details class="admin-row-menu"><summary>More</summary><div><button class="app-button admin-button danger small" type="submit" name="action" value="delete" formnovalidate data-require-reauth="1" data-confirm="Delete this inactive shipping zone permanently? Existing orders keep their saved delivery snapshot." data-confirm-tone="danger">Delete permanently</button></div></details><?php endif;?></div>
      </form>
    </details>
    <?php endforeach;?>
  </div>
  <?php endif;?>
</section>
<?php admin_page_end(); ?>
