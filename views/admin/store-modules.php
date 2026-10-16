<?php
admin_page_start('Store Modules','store-modules','STORE SETUP','Store','Modules','Choose optional customer-facing features without changing orders, inventory or existing customer data.');
?>
<style>
.sm-wrap{max-width:1120px;margin:auto}.sm-head{padding:28px;margin-bottom:20px}.sm-head p{max-width:760px;color:var(--muted,#786e66);line-height:1.6}.sm-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:14px}.sm-tile{display:flex;align-items:flex-start;gap:14px;border:1px solid #e5dbd0;padding:20px;border-radius:12px;background:#fff;cursor:pointer;min-height:112px}.sm-tile input{width:20px;height:20px;flex:none;margin-top:3px;accent-color:#94704d}.sm-tile strong,.sm-tile small{display:block}.sm-tile small{margin-top:7px;color:#756c65;line-height:1.5}.sm-actions{display:flex;flex-wrap:wrap;gap:12px;padding:22px;margin-top:18px;align-items:center}.sm-actions p{flex:1 1 100%;color:#756c65;margin:0}.sm-links{display:flex;gap:12px;flex-wrap:wrap;margin:18px 0}.sm-info{border-left:3px solid #a07d58;background:#faf7f3;padding:14px 18px;margin-top:18px;color:#55473b;line-height:1.55}@media(max-width:700px){.sm-grid{grid-template-columns:1fr}.sm-actions button{flex:1 1 auto}}
</style>
<div class="sm-wrap">
<?php if($flash):?><div class="app-alert admin-alert success" role="status"><?=$h($flash)?></div><?php endif;?>
<?php if($error):?><div class="app-alert admin-alert danger" role="alert"><?=$h($error)?></div><?php endif;?>
<section class="app-panel sm-head"><p class="panel-kicker">CUSTOMER EXPERIENCE</p><h2>Customer experience modules</h2><p>Choose optional features for this client installation. Save a private draft, then Publish when ready. No product data is deleted when a module is turned off.</p><div class="sm-links"><a class="app-button admin-button secondary" href="<?=$h(route_url('admin/store-experience.php'))?>">Store setup &amp; themes →</a><a class="app-button admin-button secondary" href="<?=$h(route_url('admin/storefront-configuration.php'))?>">Navigation →</a><a class="app-button admin-button secondary" href="<?=$h(route_url('admin/homepage.php'))?>">Homepage →</a></div><p class="sm-info">Essential commerce features—cart, checkout, order history, security and stock validation—remain on. Modules change customer presentation and relevant public actions, not your historical records.</p></section>
<form method="post" action="<?=$h(route_url('admin/store-modules.php'))?>">
<?=csrf_field()?><input type="hidden" name="revision" value="<?=$revision?>">
<div class="sm-grid">
<?php foreach($modules as $key=>$module):?>
<label class="sm-tile"><input type="checkbox" name="module[<?=$h($key)?>]" value="1" <?=$draft[$key]?'checked':''?>><span><strong><?=$h($module['label'])?></strong><small><?=$h($module['description'])?></small></span></label>
<?php endforeach;?>
</div>
<section class="app-panel sm-actions"><p><strong>Published status:</strong> <?=$published===null?'Legacy defaults (all modules enabled)':implode(', ',array_map(fn($k)=>$modules[$k]['label'],array_keys(array_filter($published))))?></p><button class="app-button admin-button secondary" type="submit" name="intent" value="save">Save draft</button><?php if($published!==null):?><button class="app-button admin-button quiet" type="submit" name="intent" value="restore" data-confirm="Restore the previously published module settings?">Restore previous</button><?php endif;?><button class="app-button admin-button primary" type="submit" name="intent" value="publish">Publish modules</button></section>
</form>
</div>
<?php admin_page_end(); ?>
