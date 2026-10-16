<?php
declare(strict_types=1);
$e=static fn(mixed $v):string=>htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');
// Defaults keep the existing view fixtures and legacy rendering compatible.
$departments=$departments??[];$categoryDepartmentMap=$categoryDepartmentMap??[];
$selectedDepartment=$selectedDepartment??0;$departmentView=$departmentView??false;$eligibleCounts=$eligibleCounts??[];
$listStatus=$listStatus??'';
$ucArabic=function_exists('store_lang') && store_lang()==='ar';
$base=route_url('admin/universal-catalog.php');
$optByAttr=[];foreach(($options??[]) as $row)$optByAttr[(int)$row['attribute_id']][]=$row;
$attrById=[];foreach(($attributes??[]) as $row)$attrById[(int)$row['id']]=$row;
$bindById=[];foreach(($bindings??[]) as $row)$bindById[(int)$row['attribute_id']]=$row;
$legacyCodes=['volume_ml','fragrance_family','concentration','fragrance_gender','longevity_score','projection_score','sillage_score','fresh_score','sweet_score','woody_score','spicy_score','season_tags','occasion_tags'];
$selectedCategoryName='Category';foreach(($categories??[]) as $c)if((int)$c['id']===(int)$selectedCategory)$selectedCategoryName=(string)$c['name'];
admin_page_start('Universal Catalog','universal-catalog','PRODUCT MANAGEMENT','Universal Catalog','','Products, categories, brands, and variant stock in one workspace.');
?>
<link rel="stylesheet" href="<?= $e(url('assets/css/admin-universal.css')) ?>">
<link rel="stylesheet" href="<?= $e(url('assets/css/admin-alpha5.css')) ?>">
<link rel="stylesheet" href="<?= $e(url('assets/css/v5-alpha5-1.css')) ?>">
<link rel="stylesheet" href="<?= $e(url('assets/css/v5-alpha7-2.css')) ?>">
<?php if($message):?><div class="app-alert admin-alert success"><?=$e($message)?></div><?php endif;?>
<?php if($error):?><div class="app-alert admin-alert danger"><?=$e($error)?></div><?php endif;?>
<?php if(!$ready):?><section class="app-panel admin-panel admin-card-pad"><h2>Universal catalog migrations required</h2><p><?=$e($error)?></p><p>Apply the required catalog migrations using your normal migration procedure before managing products. This editor does not run migrations automatically.</p></section><?php admin_page_end();return;endif;?>

<div class="uc-topbar">
  <div class="uc-pill">Universal Catalog · Product workspace</div>
  <div class="uc-nav">
    <a class="app-button admin-button secondary" href="<?=$e($base)?>">All products</a>
    <a class="app-button admin-button secondary" href="<?=$e($base)?>?departments=1">Department Manager</a>
    <a class="app-button admin-button secondary" href="<?=$e($base)?>?new=1">+ New product</a>
    <a class="app-button admin-button secondary" href="<?=$e($base)?>?setup=<?=(int)($setupId?:($categories[0]['id']??0))?>">Attributes &amp; presets</a>
    <a class="app-button admin-button secondary" href="<?=$e(route_url('admin/taxonomy.php'))?>">Manage brands &amp; categories</a>

  </div>
  <details class="uc-legacy-tools"><summary>Previous editor tools</summary><a href="<?=$e(route_url('admin/catalog.php'))?>">Legacy perfume editor</a> · Existing products remain available there.</details>
</div>

<?php if($departmentView): ?>
<section class="app-panel admin-panel admin-card-pad uc-panel" data-uc-department-manager>
 <div class="uc-heading"><div><span class="uc-kicker">STORE DEPARTMENTS</span><h2>Department Manager</h2><p>Manage active departments independently. Pausing a department hides its classified products from customers and blocks new purchases, but never deletes orders, stock history or SKUs. Existing unassigned products stay visible during migration.</p></div></div>
 <div class="uc-department-grid">
 <?php foreach($departments as $d): ?>
 <div class="uc-department-card"><div><span class="uc-kicker"><?=$e(strtoupper($d['status']))?></span><h3><?=$e($d['name'])?></h3><p><?=$e($d['description']??'')?></p><small><?=(int)$d['category_count']?> categories · <?=(int)$d['product_count']?> assigned products</small></div>
  <div class="uc-actions"><a class="app-button admin-button secondary" href="<?=$e($base)?>?department=<?=(int)$d['id']?>">Products</a><a class="app-button admin-button secondary" href="<?=$e($base)?>?new=1&department=<?=(int)$d['id']?>">Add product</a></div>
 <?php if($d['status']==='active' && (int)($eligibleCounts[(int)$d['id']]??0)>0):?><form method="post" class="uc-assign-safe"><?=csrf_field()?><input type="hidden" name="action" value="classify_department_products"><input type="hidden" name="department_id" value="<?=(int)$d['id']?>"><p class="uc-muted"><?=(int)$eligibleCounts[(int)$d['id']]?> unassigned products have unambiguous category matches.</p><button class="app-button admin-button secondary" type="submit">Classify matched products</button></form><?php endif;?>
  <form method="post" class="uc-section-form"><?=csrf_field()?><input type="hidden" name="action" value="save_department"><input type="hidden" name="department_id" value="<?=(int)$d['id']?>"><label>Name<input name="department_name" maxlength="120" value="<?=$e($d['name'])?>" required></label><label>Description<input name="description" maxlength="500" value="<?=$e($d['description']??'')?>"></label><div class="uc-two"><label>Department visibility<select name="status"><option value="active" <?=$d['status']==='active'?'selected':''?>>Enabled</option><option value="hidden" <?=$d['status']==='hidden'?'selected':''?>>Paused — hide listings and block new purchases</option></select></label><label>Order<input type="number" name="sort_order" min="0" max="9999" value="<?=(int)$d['sort_order']?>"></label></div><button class="app-button admin-button secondary" type="submit">Save department</button></form>
 </div><?php endforeach;?></div>
 <details class="uc-quick-create"><summary>+ Create another department</summary><form class="uc-section-form" method="post"><?=csrf_field()?><input type="hidden" name="action" value="save_department"><label>Department name<input name="department_name" maxlength="120" required placeholder="Home & Living"></label><label>Description<input name="description" maxlength="500"></label><button class="app-button admin-button" type="submit">Create department</button></form></details>
 <div class="uc-heading" style="margin-top:24px"><div><h3>Assign existing categories</h3><p>Every category belongs to one department. Unassigned categories stay available in the legacy catalog until you decide where they belong.</p></div></div>
 <div class="uc-department-assignments"><div class="uc-table-wrap"><table class="uc-table"><thead><tr><th>Category</th><th>Current department</th><th>Assign to</th></tr></thead><tbody>
 <?php foreach($categories as $cat):$assigned=(int)($categoryDepartmentMap[(int)$cat['id']]??0); ?>
  <tr><td><strong><?=$e($cat['name'])?></strong><small>#<?=(int)$cat['id']?><?php if((int)($cat['parent_id']??0)>0):?> · child category<?php endif;?></small></td><td><?=$assigned>0?$e((string)(array_values(array_filter($departments,static fn($d)=>(int)$d['id']===$assigned))[0]['name']??'Unknown')):'Unassigned'?></td><td><form method="post" class="uc-inline-assign"><?=csrf_field()?><input type="hidden" name="action" value="assign_department_category"><input type="hidden" name="category_id" value="<?=(int)$cat['id']?>"><select name="department_id" required><option value="">Choose department</option><?php foreach($departments as $d):if($d['status']!=='active')continue;?><option value="<?=(int)$d['id']?>" <?=$assigned===(int)$d['id']?'selected':''?>><?=$e($d['name'])?></option><?php endforeach;?></select><button class="app-button admin-button secondary">Assign</button></form></td></tr>
 <?php endforeach;?></tbody></table></div></div>
</section>
<?php endif; ?>

<?php if(!$departmentView): ?>
<section class="app-panel admin-panel admin-card-pad uc-setup-strip" aria-label="Catalog setup">
 <div class="uc-heading"><div><span class="uc-kicker">CATALOG WORKFLOW</span><h2>Manage products</h2><p>Start with a department and category. Then create a product, set its variants and stock, and publish when ready.</p></div><a class="app-button admin-button secondary" href="<?=$e(route_url('admin/taxonomy.php'))?>">Edit or archive existing entries →</a></div>
 <form method="get" class="uc-department-switcher" action="<?=$e($base)?>">
 <?php if(isset($_GET['new'])):?><input type="hidden" name="new" value="1"><?php endif;?>
 <label>Work in department <select name="department" data-uc-department-switch><option value="0">All departments</option><?php foreach($departments as $d):if($d['status']!=='active')continue;?><option value="<?=(int)$d['id']?>" <?=$selectedDepartment===(int)$d['id']?'selected':''?>><?=$e($d['name'])?></option><?php endforeach;?></select></label>
 <button class="app-button admin-button secondary" type="submit">Open workspace</button>
 <a href="<?=$e($base)?>?departments=1">Manage departments &amp; categories →</a>
</form>
<?php if($selectedDepartment===0):?><p class="uc-muted">Choose a department above before adding a category so it is classified immediately. Existing unassigned categories can be linked in Department Manager.</p><?php endif;?>
 <nav class="uc-workspace-nav" aria-label="Catalog workspaces">
  <a href="<?=$e($base)?>?departments=1">1. Departments &amp; categories</a>
  <a href="<?=$e($base)?>?setup=<?=(int)($setupId?:($selectedCategory?:($categories[0]['id']??0)))?>">2. Category attributes</a>
  <a href="<?=$e($base)?>?new=1&amp;department=<?=(int)$selectedDepartment?>">3. Product editor</a>
  <a href="<?=$e($base)?>">4. Product list</a>
  <a href="<?=$e(route_url('admin/inventory.php'))?>">5. Inventory</a>
 </nav>
 <div class="uc-quick-links"><button type="button" class="app-button admin-button secondary" data-uc-taxonomy-open="category">+ Add category</button><button type="button" class="app-button admin-button secondary" data-uc-taxonomy-open="brand">+ Add brand</button><a class="app-button admin-button secondary" href="<?=$e($base)?>?setup=<?=(int)($setupId?:($selectedCategory?:($categories[0]['id']??0)))?>">Set up attributes</a><a class="app-button admin-button" href="<?=$e($base)?>?new=1&amp;department=<?=(int)$selectedDepartment?>">Create product</a></div>
 <details class="uc-quick-create" data-uc-quick-create>
   <summary>Add category or brand</summary>
   <form method="post" action="<?=$e($base)?>" enctype="multipart/form-data" data-uc-taxonomy-form data-ui-async-submit class="uc-section-form">
      <?=csrf_field()?><input type="hidden" name="action" value="quick_taxonomy"><input type="hidden" name="ajax" value="1"><input type="hidden" name="selected_category" value="<?=(int)$selectedCategory?>" data-uc-current-category><input type="hidden" name="department_id" value="<?=(int)$selectedDepartment?>" data-uc-quick-department>
      <div class="uc-form-grid"><label>What are you adding?<select name="taxonomy_type" data-uc-taxonomy-type><option value="category">Category</option><option value="brand">Brand</option></select></label><label>Name<input name="taxonomy_name" required maxlength="120" placeholder="e.g. T-Shirts, Sneakers, Samsung"></label>
      <label data-uc-parent-wrap>Parent category (optional)<select name="parent_id"><option value="0">Top-level category</option><?php foreach($categories as $c):if(($c['status']??'active')==='active'):?><option value="<?=(int)$c['id']?>"><?=$e($c['name'])?></option><?php endif;endforeach;?></select></label>
      <label>Image or logo (optional)<input name="taxonomy_image" type="file" accept="image/jpeg,image/png,image/webp" data-uc-taxonomy-image><img data-uc-taxonomy-preview hidden class="uc-photo-preview" alt="New category or brand image preview"><small>You can also manage images later under Catalog Setup.</small></label></div>
      <div class="uc-actions"><button type="submit" class="app-button admin-button">Save category / brand</button><span class="uc-muted" data-uc-taxonomy-feedback role="status" aria-live="polite"></span></div>
   </form>
 </details>
</section>

<?php if($setupId>0):?>
<section class="app-panel admin-panel admin-card-pad uc-panel" data-uc-attribute-setup>
  <div class="uc-heading"><div><span class="uc-kicker">CATEGORY CONFIGURATION</span><h2>Choose product attributes</h2><p>Assign specifications and SKU axes to this category. Existing products, SKUs and historical orders are not changed.</p></div></div>
  <form class="uc-category-picker" method="get" action="<?=$e($base)?>"><label>Category <select name="setup" onchange="this.form.submit()" required><?php foreach($categories as $cat):?><option value="<?=(int)$cat['id']?>" <?=(int)$cat['id']===$setupId?'selected':''?>><?=$e($cat['name'])?></option><?php endforeach;?></select></label><button class="app-button admin-button secondary">Open category</button></form>
  <?php if(!$setupBindings):?>
  <div class="uc-presets"><div><strong>Choose an industry preset</strong><p class="uc-muted">Start with ready-made fields. This only configures an empty category; it never replaces existing assignments.</p></div>
  <?php foreach(['clothing'=>['Clothing','Size + Color variants, Material and Fit'], 'electronics'=>['Electronics','Storage + Color variants, RAM and Warranty'], 'general'=>['General retail','Color variants and Shipping Weight']] as $preset=>$copy):?>
  <form method="post" class="uc-preset-card"><?=csrf_field()?><input type="hidden" name="action" value="apply_preset"><input type="hidden" name="category_id" value="<?=$setupId?>"><input type="hidden" name="preset" value="<?=$e($preset)?>"><strong><?=$e($copy[0])?></strong><small><?=$e($copy[1])?></small><button class="app-button admin-button secondary" type="submit">Use preset</button></form><?php endforeach;?></div>
  <?php endif;?>
  <details class="uc-quick-create uc-custom-attribute">
    <summary>+ Create a custom attribute for this category</summary>
    <form method="post" action="<?=$e($base)?>" class="uc-section-form" data-uc-attribute-form data-ui-async-submit>
      <?=csrf_field()?><input type="hidden" name="action" value="create_attribute"><input type="hidden" name="ajax" value="1"><input type="hidden" name="category_id" value="<?=$setupId?>">
      <div class="uc-form-grid">
        <label>Attribute name *<input name="attribute_label" required maxlength="140" placeholder="e.g. Skin type, Shade, Screen size"></label>
        <label>Field type<select name="attribute_input_type" data-uc-attribute-type><option value="select">Dropdown</option><option value="text">Text</option><option value="number">Number</option><option value="boolean">Yes / No</option></select></label>
        <label>Applies to<select name="attribute_scope" data-uc-attribute-scope><option value="product">Whole product (specification)</option><option value="variant">Individual SKU (size, shade, color)</option></select></label>
        <label>Unit (optional)<input name="attribute_unit" maxlength="24" placeholder="e.g. ml, cm, inches"></label>
        <label class="uc-wide" data-uc-attribute-options>Dropdown values (one per line)<textarea name="attribute_options" rows="3" placeholder="Fair&#10;Medium&#10;Deep"></textarea></label>
        <label class="uc-check"><input type="checkbox" name="attribute_filterable" value="1" checked> Mark as a future storefront filter</label>
        <label class="uc-check"><input type="checkbox" name="attribute_required" value="1"> Required when editing products</label>
        <label class="uc-check uc-wide" data-uc-attribute-axis hidden><input type="checkbox" name="attribute_variant_axis" value="1"> Generate separate SKUs for each option</label>
      </div>
      <div class="uc-actions"><button class="app-button admin-button" type="submit">Create attribute</button><span class="uc-muted" role="status" aria-live="polite" data-uc-attribute-feedback></span></div>
    </form>
  </details>
  <?php $assigned=[];foreach($setupBindings as $a)$assigned[(int)$a['attribute_id']]=$a; ?>
  <form method="post" class="uc-section-form">
    <?=csrf_field()?><input type="hidden" name="action" value="category_settings"><input type="hidden" name="category_id" value="<?=$setupId?>">
    <div class="uc-table-wrap"><table class="uc-table"><thead><tr><th>Use</th><th>Attribute</th><th>Scope</th><th>Required</th><th>SKU axis</th></tr></thead><tbody>
      <?php foreach($attributes as $a):$aid=(int)$a['id'];$old=$assigned[$aid]??null;$canAxis=$a['scope']==='variant'&&$a['input_type']==='select'&&!in_array((string)$a['code'],$legacyCodes,true);?>
      <tr><td><input aria-label="Enable <?=$e($a['label'])?>" type="checkbox" name="attributes[]" value="<?=$aid?>" <?=$old?'checked':''?>></td><td><strong><?=$e($a['label'])?></strong><small><?=$e($a['code'])?></small></td><td><?=$e($a['scope'])?> · <?=$e($a['input_type'])?></td><td><input type="checkbox" name="required[]" value="<?=$aid?>" <?=$old && $old['is_required']?'checked':''?>></td><td><?php if($canAxis):?><input aria-label="Variant axis <?=$e($a['label'])?>" type="checkbox" name="axes[]" value="<?=$aid?>" <?=$old && $old['is_variant_axis']?'checked':''?>><?php else:?><span class="uc-muted">—</span><?php endif;?></td></tr>
      <?php endforeach;?></tbody></table></div>
    <p class="uc-muted">For clothes, select Size and Color as SKU axes. For electronics, select Storage and Color. A variant axis must be enabled under “Use”.</p>
    <button class="app-button admin-button" type="submit">Save category settings</button>
  </form>
  <hr class="uc-rule">
  <h3>Add dropdown choices</h3><p class="uc-muted">Enter one value per line, e.g. 2XL or Red. Preset options are already available for popular attributes.</p>
  <form method="post" class="uc-option-form">
    <?=csrf_field()?><input type="hidden" name="action" value="add_options"><input type="hidden" name="category_id" value="<?=$setupId?>">
    <label>Attribute<select name="attribute_id" required><?php foreach($attributes as $a)if($a['input_type']==='select'):?><option value="<?=(int)$a['id']?>"><?=$e($a['label'])?> (<?=$e($a['scope'])?>)</option><?php endif;?></select></label>
    <label>New options<textarea name="option_labels" rows="3" placeholder="Red&#10;Blue&#10;Green" required></textarea></label>
    <button class="app-button admin-button">Add options</button>
  </form>
</section>
<?php elseif($product || isset($_GET['new'])):?>
<section class="app-panel admin-panel admin-card-pad uc-panel" id="uc-product-details">
 <div class="uc-heading"><div><span class="uc-kicker"><?=$product?'EDIT PRODUCT #'.(int)$product['id']:'NEW PRODUCT'?></span><h2><?=$product?$e($product['name']):'Create a product'?></h2><p>1. Select a category &amp; brand → 2. Save product → 3. Generate SKUs → 4. Publish. Your existing product and order IDs are preserved.</p></div></div>
 <nav class="uc72-editor-nav" aria-label="Product editor sections"><a href="#uc-product-details">Details</a><a href="#uc-product-gallery">Media gallery</a><a href="#uc-product-specifications">Specifications</a><?php if($product):?><a href="#uc-product-variants">SKU combinations</a><a href="#uc-product-inventory">Stock, pricing &amp; SKU photos</a><?php endif;?></nav>
 <div class="uc-progress" aria-label="Product setup steps"><span class="is-current">1. Details and gallery</span><span class="<?=$product?'is-current':''?>">2. Variants</span><span>3. Inventory &amp; publish</span></div>
 <form method="post" enctype="multipart/form-data" class="uc-section-form" data-dirty-form>
  <?=csrf_field()?><input type="hidden" name="action" value="save_product"><input type="hidden" name="department_id" value="<?=(int)$selectedDepartment?>" data-uc-product-department><input type="hidden" name="product_id" value="<?=(int)($product['id']??0)?>">
  <div class="uc-form-grid">
   <label class="uc-wide">Product name *<input name="name" maxlength="180" required value="<?=$e($product['name']??'')?>" placeholder="Cotton T-shirt / Smartphone / Fragrance"></label>
   <label>Category *<select name="category_id" required data-uc-category-select><option value="">Select a category</option><?php foreach($categories as $c):if(($c['status']??'active')==='active'):?><option value="<?=(int)$c['id']?>" data-department="<?=(int)($categoryDepartmentMap[(int)$c['id']]??0)?>" <?=(int)$c['id']===$selectedCategory?'selected':''?>><?=$e($c['name'])?></option><?php endif;endforeach;?></select><button type="button" class="uc-text-action" data-uc-taxonomy-open="category">+ New category</button></label>
   <label>Brand<select name="brand_id" data-uc-brand-select><option value="">No brand</option><?php foreach($brands as $b):if(($b['status']??'active')==='active'):?><option value="<?=(int)$b['id']?>" <?=(int)($product['brand_id']??0)===(int)$b['id']?'selected':''?>><?=$e($b['name'])?></option><?php endif;endforeach;?></select><button type="button" class="uc-text-action" data-uc-taxonomy-open="brand">+ New brand</button></label>
   <label>Shopping audience<select name="gender"><option value="men" <?=($product['gender']??'unisex')==='men'?'selected':''?>>Men</option><option value="women" <?=($product['gender']??'unisex')==='women'?'selected':''?>>Women</option><option value="unisex" <?=($product['gender']??'unisex')==='unisex'?'selected':''?>>Unisex / General</option></select><small>Used for Fashion and Perfumes audience filters. Electronics is never filtered by audience.</small></label>
   <label>Visibility<select name="status" <?=$product?'':'disabled' ?>><?php foreach(['draft'=>'Draft','active'=>'Published','hidden'=>'Hidden','scheduled'=>'Scheduled','archived'=>'Archived'] as $val=>$caption):?><option value="<?=$val?>" <?=(string)($product['status']??'draft')===$val?'selected':''?>><?=$caption?></option><?php endforeach;?></select><?php if(!$product):?><small>New products are Draft until you create an active SKU.</small><?php endif;?></label>
   <label class="uc-wide">Short description<textarea name="short_description" rows="2" maxlength="500"><?=$e($product['short_description']??'')?></textarea></label>
   <label class="uc-wide">Full description<textarea name="description" rows="4" maxlength="20000"><?=$e($product['description']??'')?></textarea></label>
   <label>SEO title<input type="text" name="seo_title" maxlength="180" value="<?=$e($product['seo_title']??'')?>" placeholder="Optional search result title"></label>
   <label>SEO description<input name="seo_description" maxlength="320" value="<?=$e($product['seo_description']??'')?>" placeholder="Optional search result description"></label>
   <label class="uc-wide">Main product photo <input type="file" name="main_image_upload" accept="image/jpeg,image/png,image/webp" data-uc-photo-input><?php $existingPhoto=($product && function_exists('store_image_path'))?store_image_path($product['main_image_path']??''):'';?><img data-uc-photo-preview alt="Product photo preview" <?=$existingPhoto!==''?'src="'.$e(url($existingPhoto)).'"':'hidden'?> class="uc-photo-preview"><small>JPG, PNG or WebP, minimum 700 × 700. Current images remain unless you upload a replacement. Manage all gallery photos directly below.</small></label>
  </div>
  <?php $ucGalleryRaw=json_decode((string)($product['gallery_json']??'[]'),true);$ucGallery=is_array($ucGalleryRaw)?array_values(array_filter($ucGalleryRaw,'is_string')):[]; ?>
  <section class="uc72-media-section" id="uc-product-gallery" data-uc-gallery>
    <div class="uc72-section-intro"><div><strong>Product gallery</strong><p>Add, remove and reorder up to six product photos. The first photo is featured if no main image is selected. Removed photos are unlinked on Save; files are not physically deleted.</p></div><span class="uc72-media-count" data-uc-gallery-count><?=(($existingPhoto??'')!==''?1:0)+count($ucGallery)?> / 6</span></div>
    <input type="hidden" name="gallery_order" data-uc-gallery-order value="<?=$e(json_encode($ucGallery,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE))?>">
    <input type="hidden" name="promote_gallery_path" data-uc-gallery-promote value="">
    <?php if(($existingPhoto??'')!==''):?><label class="uc72-remove-main"><input type="checkbox" name="remove_main_image" value="1" data-uc-remove-main> Remove current main photo when saving (unless replaced)</label><?php endif;?>
    <div class="uc72-gallery-grid" data-uc-gallery-grid>
      <?php foreach($ucGallery as $path):$safeImage=function_exists('store_image_path')?store_image_path($path):$path;if($safeImage==='')continue;?>
        <article class="uc72-gallery-card" data-uc-gallery-card data-path="<?=$e($path)?>">
          <img src="<?=$e(url($safeImage))?>" alt="Gallery photo" loading="lazy">
          <div class="uc72-gallery-actions"><button type="button" data-uc-gallery-move="left" aria-label="Move photo left">←</button><button type="button" data-uc-gallery-move="right" aria-label="Move photo right">→</button><button type="button" data-uc-gallery-main>Set main</button><button type="button" data-uc-gallery-remove aria-label="Remove photo">Remove</button></div>
          <input type="checkbox" name="remove_gallery[]" value="<?=$e($path)?>" data-uc-gallery-remove-input hidden>
        </article>
      <?php endforeach;?>
      <div data-uc-gallery-pending class="uc72-pending-photos"></div>
    </div>
    <label class="uc72-upload-label">+ Add gallery photos <input type="file" name="gallery_uploads[]" multiple accept="image/jpeg,image/png,image/webp" data-uc-gallery-input></label>
    <p class="uc-muted" data-uc-gallery-feedback role="status" aria-live="polite">Photos are previewed before Save. Maximum six photos total, minimum 700 × 700 pixels per upload.</p>
  </section>
  <?php if($product && !in_array($selectedCategory,$assignedCategoryIds,true)):?><div class="uc-notice">This category is not assigned yet. Saving it will update the primary department and reconcile category links. Moving to another department replaces old categories; order and SKU history are preserved.</div><?php endif;?>
  <div data-uc-specs id="uc-product-specifications"><hr class="uc-rule"><h3><?=$selectedCategory>0?$e($selectedCategoryName).' specifications':'Product specifications'?></h3>
  <?php if($bindings):?><div class="uc-form-grid">
   <?php foreach($bindings as $b):$attr=$attrById[(int)$b['attribute_id']]??null;if(!$attr||$attr['scope']!=='product')continue;$aid=(int)$attr['id'];$stored=$values[$aid]??[];$legacy=in_array((string)$attr['code'],$legacyCodes,true);$val=(string)($stored['value_text']??$stored['value_decimal']??$stored['value_boolean']??'');if($legacy):?><div class="uc-field-muted"><?=$e($attr['label'])?> · Existing perfume field (edit in perfume editor)</div><?php continue;endif;?>
   <label><?=$e($attr['label'])?><?=$b['is_required']?' *':''?>
    <?php if($attr['input_type']==='select'):?><select name="attributes[<?=$aid?>]" <?=$b['is_required']?'required':''?>><option value="">Choose</option><?php foreach($optByAttr[$aid]??[] as $option):?><option value="<?=(int)$option['id']?>" <?=(int)($stored['option_id']??0)===(int)$option['id']?'selected':''?>><?=$e($option['label'])?></option><?php endforeach;?></select>
    <?php elseif($attr['input_type']==='number'):?><input type="number" step="0.0001" name="attributes[<?=$aid?>]" value="<?=$e($val)?>" <?=$b['is_required']?'required':''?>>
    <?php elseif($attr['input_type']==='boolean'):?><select name="attributes[<?=$aid?>]"><option value="">Not set</option><option value="1" <?=$val==='1'?'selected':''?>>Yes</option><option value="0" <?=$val==='0'?'selected':''?>>No</option></select>
    <?php else:?><input name="attributes[<?=$aid?>]" maxlength="500" value="<?=$e($val)?>" <?=$b['is_required']?'required':''?>><?php endif;?>
   </label>
   <?php endforeach;?></div><?php else:?><div class="uc-notice"><?=$selectedCategory>0?'This category has no configured attributes yet.':'Select or create a category to load its specifications.'?> <?php if($selectedCategory>0):?><a href="<?=$e($base)?>?setup=<?=(int)$selectedCategory?>" data-uc-setup-link>Configure attributes</a><?php endif;?></div><?php endif;?></div>
  <div class="uc-actions"><button class="app-button admin-button" type="submit"><?=$product?'Save product':'Create draft product'?></button><span class="uc-muted">SKU generation and stock management appear after saving the draft.</span><a class="app-button admin-button secondary" href="<?=$e($base)?>">Cancel</a></div>
 </form>
</section>
<?php if($product):?>
<section class="app-panel admin-panel admin-card-pad uc-panel">
 <div class="uc-heading" id="uc-product-variants"><div><span class="uc-kicker">VARIANTS</span><h2>Generate SKU combinations</h2><p>Each combination has its own SKU, price and stock. Existing combinations will not be duplicated.</p></div></div>
 <?php $axes=[];foreach($bindings as $b)if((int)$b['is_variant_axis']===1)$axes[]=$attrById[(int)$b['attribute_id']]; ?>
 <form method="post" class="uc-section-form" data-variant-generator>
  <?=csrf_field()?><input type="hidden" name="action" value="generate_variants"><input type="hidden" name="product_id" value="<?=(int)$product['id']?>"><input type="hidden" name="category_id" value="<?=(int)$selectedCategory?>">
  <?php if($axes):?><div class="uc-axis-grid">
   <?php foreach($axes as $attr):$aid=(int)$attr['id'];?><fieldset><legend><?=$e($attr['label'])?></legend><div class="uc-options"><?php foreach($optByAttr[$aid]??[] as $opt):?><label><input type="checkbox" name="choices[<?=$aid?>][]" value="<?=(int)$opt['id']?>" data-variant-choice> <?=$e($opt['label'])?></label><?php endforeach;?></div></fieldset><?php endforeach;?></div>
  <?php else:?><div class="uc-notice">No SKU axes for this category. You can create a single Standard variant, or configure category axes first.</div><?php endif;?>
  <div class="uc-form-grid"><label>Regular price *<input type="number" name="price" step="0.01" min="0.01" required placeholder="1200.00"></label><label>Sale price<input type="number" name="sale_price" step="0.01" min="0"></label><label>Unit cost<input type="number" name="cost_price" step="0.01" min="0"></label><label>Initial stock for each SKU<input type="number" name="stock_qty" min="0" max="1000000" value="0" required></label><label>Low stock threshold<input type="number" name="low_stock_threshold" min="0" max="100000" value="3" required></label></div>
  <div class="uc-actions"><button class="app-button admin-button">Generate missing variants</button><span class="uc-muted" data-variant-count>Choose options to preview the number of SKUs.</span></div>
 </form>
</section>
<section class="app-panel admin-panel admin-card-pad uc-panel" id="uc-product-inventory"><div class="uc-heading"><div><span class="uc-kicker">STOCK & PRICING</span><h2>Existing SKUs (<?=count($variants)?>)</h2><p>Update individual prices, status and stock. Quantity adjustments are recorded in inventory history.</p></div><a class="app-button admin-button secondary" href="<?=$e(route_url('admin/inventory.php'))?>?view=stock">Open inventory</a></div>
 <?php if(!$variants):?><div class="uc-notice">No variants yet. Generate at least one SKU above before publishing.</div><?php endif;?>
 <div class="uc-variant-stack"><?php $variantIndex=0;$focusedSku=(int)($_GET['sku']??0);foreach($variants as $v):$variantIndex++;?><details class="uc-sku-fold" id="uc-sku-<?=(int)$v['id']?>" <?=($focusedSku>0?(int)$v['id']===$focusedSku:$variantIndex===1)?'open':''?>>
  <summary><span class="uc-sku-title"><strong><?=$e($v['label'])?></strong><small><?=$e($v['sku'])?> · <?=$e($v['status'])?></small></span><span class="uc-sku-summary"><span><?=$e((string)$v['price'])?> <?=$e((string)$v['currency'])?></span><span><?=(int)$v['stock_qty']?> <?=$ucArabic?'في المخزون':'in stock'?></span></span><span class="uc-sku-edit"><?=$ucArabic?'تعديل':'Edit SKU'?></span></summary>
  <form method="post" enctype="multipart/form-data" class="uc-variant-card" data-dirty-form>
  <?=csrf_field()?><input type="hidden" name="action" value="update_variant"><input type="hidden" name="product_id" value="<?=(int)$product['id']?>"><input type="hidden" name="category_id" value="<?=(int)$selectedCategory?>"><input type="hidden" name="variant_id" value="<?=(int)$v['id']?>">
  <div><strong><?=$e($v['label'])?></strong><small>#<?=(int)$v['id']?> · <?=$e($v['currency'])?> · <?=(int)($v['reserved_qty']??0)?> reserved</small></div>
  <label>SKU<input name="sku" value="<?=$e($v['sku'])?>" maxlength="80" required></label><label>Barcode<input name="barcode" value="<?=$e($v['barcode']??'')?>" maxlength="80"></label><label>Price<input name="price" value="<?=$e($v['price'])?>" type="number" min="0.01" step="0.01" required></label><label>Sale<input name="sale_price" value="<?=$e($v['sale_price']??'')?>" type="number" min="0" step="0.01"></label><label>Cost<input name="cost_price" value="<?=$e($v['cost_price']??'')?>" type="number" min="0" step="0.01"></label><label>Stock<input name="stock_qty" value="<?=(int)$v['stock_qty']?>" type="number" min="0" max="1000000" required></label><label>Alert at<input name="low_stock_threshold" value="<?=(int)$v['low_stock_threshold']?>" type="number" min="0" max="100000" required></label><label>Status<select name="status"><?php foreach(['active'=>'Active','hidden'=>'Hidden','archived'=>'Archived'] as $state=>$label):?><option value="<?=$state?>" <?=$v['status']===$state?'selected':''?>><?=$label?></option><?php endforeach;?></select></label><div class="uc73-sku-media" data-uc-variant-media>
    <?php $skuMain=(string)($v['main_image_path']??'');$skuGalleryRaw=json_decode((string)($v['gallery_json']??'[]'),true);$skuGallery=is_array($skuGalleryRaw)?array_values(array_filter($skuGalleryRaw,'is_string')):[];$safeSkuMain=($skuMain!=='' && function_exists('store_image_path'))?store_image_path($skuMain):''; ?>
    <div class="uc73-sku-media-title"><strong>Photos for this SKU</strong><small>Optional. If no SKU photos are assigned, customers see the product gallery.</small></div>
    <label>SKU main photo<input type="file" name="main_image_upload" accept="image/jpeg,image/png,image/webp" data-uc-photo-input><img class="uc-photo-preview" data-uc-photo-preview alt="SKU image preview" <?= $safeSkuMain!==''?'src="'.$e(url($safeSkuMain)).'"':'hidden' ?>></label>
    <div data-uc-gallery data-uc-sku-gallery>
      <input type="hidden" name="gallery_order" data-uc-gallery-order value="<?=$e(json_encode($skuGallery,JSON_UNESCAPED_SLASHES))?>">
      <input type="hidden" name="promote_gallery_path" data-uc-gallery-promote value="">
      <?php if($safeSkuMain!==''):?><label class="uc73-remove"><input type="checkbox" name="remove_main_image" value="1" data-uc-remove-main> Remove current SKU main photo</label><?php endif;?>
      <div class="uc72-gallery-grid" data-uc-gallery-grid>
        <?php foreach($skuGallery as $path):$safeImage=function_exists('store_image_path')?store_image_path($path):$path;if($safeImage==='')continue; ?>
        <article class="uc72-gallery-card" data-uc-gallery-card data-path="<?=$e($path)?>">
          <img src="<?=$e(url($safeImage))?>" alt="SKU photo" loading="lazy">
          <div class="uc72-gallery-actions"><button type="button" data-uc-gallery-move="left" aria-label="Move left">←</button><button type="button" data-uc-gallery-move="right" aria-label="Move right">→</button><button type="button" data-uc-gallery-main>Set main</button><button type="button" data-uc-gallery-remove>Remove</button></div>
          <input type="checkbox" name="remove_gallery[]" value="<?=$e($path)?>" data-uc-gallery-remove-input hidden>
        </article>
        <?php endforeach;?>
        <div data-uc-gallery-pending class="uc72-pending-photos"></div>
      </div>
      <label class="uc72-upload-label">+ Add SKU photos<input type="file" name="gallery_uploads[]" accept="image/jpeg,image/png,image/webp" multiple data-uc-gallery-input></label>
      <p class="uc-muted" data-uc-gallery-feedback role="status" aria-live="polite">Maximum six photos (700 × 700 minimum), including the main photo.</p>
    </div>
  </div>
  <button class="app-button admin-button secondary">Save SKU and photos</button>
 </form></details><?php endforeach;?></div>
</section>
<?php endif;endif;?>

<?php if(!isset($_GET['new']) && !$product && $setupId===0):?>
<section class="app-panel admin-panel admin-card-pad uc-panel">
 <div class="uc-heading"><div><span class="uc-kicker">PRODUCTS</span><h2>Catalog (<?=number_format((int)$list['total'])?>)</h2><p>20 products per page. <?= $selectedDepartment>0?"Filtered by department.":"All departments, including legacy unassigned products." ?> Stock and orders remain shared.</p></div></div>
 <form method="get" class="uc-search" action="<?=$e($base)?>"><input type="hidden" name="department" value="<?=(int)$selectedDepartment?>"><label class="uc-search-name"><?=$ucArabic?'بحث في كل المنتجات':'Search all products'?> <input name="q" placeholder="<?=$ucArabic?'اسم المنتج أو العلامة أو SKU':'Search name, brand or SKU'?>" value="<?=$e($_GET['q']??'')?>"></label><label><?=$ucArabic?'الحالة':'Status'?><select name="status"><option value="" <?=$listStatus===''?'selected':''?>><?=$ucArabic?'جميع الحالات':'All statuses'?></option><?php foreach(['active'=>'Active','draft'=>'Draft','hidden'=>'Hidden','archived'=>'Archived'] as $key=>$name):?><option value="<?=$key?>" <?=$listStatus===$key?'selected':''?>><?=$e($ucArabic?(['active'=>'نشط','draft'=>'مسودة','hidden'=>'مخفي','archived'=>'مؤرشف'][$key]):$name)?></option><?php endforeach;?></select></label><button class="app-button admin-button secondary"><?=$ucArabic?'بحث':'Search'?></button></form>
 <div class="uc-inline-filter"><label for="uc-visible-products-filter"><?=$ucArabic?'تصفية هذه الصفحة':'Filter this page'?></label><input type="search" id="uc-visible-products-filter" data-uc-visible-filter placeholder="<?=$ucArabic?'تصفية المنتجات المعروضة...':'Filter the 20 loaded products...'?>"><small data-uc-visible-count role="status" aria-live="polite"></small></div>
 <form method="post" class="uc-bulk-form" data-uc-bulk-products>
   <?=csrf_field()?><input type="hidden" name="action" value="bulk_product_status"><input type="hidden" name="return_q" value="<?=$e((string)($_GET['q']??''))?>"><input type="hidden" name="return_department" value="<?=(int)$selectedDepartment?>"><input type="hidden" name="return_status" value="<?=$e($listStatus)?>"><input type="hidden" name="return_page" value="<?=(int)$list['page']?>">
   <div class="uc-bulk-toolbar"><span class="uc-bulk-selected" data-uc-bulk-count role="status" aria-live="polite"><?=$ucArabic?'لم يتم تحديد منتجات':'No products selected'?></span><label><span><?=$ucArabic?'الإجراء':'Action'?></span><select name="bulk_action" data-uc-bulk-action required><option value="archive"><?=$ucArabic?'أرشفة المنتجات المحددة':'Archive selected'?></option><option value="restore"><?=$ucArabic?'استعادة كمسودة':'Restore as drafts'?></option></select></label><button class="app-button admin-button secondary" type="submit" data-uc-bulk-submit disabled><?=$ucArabic?'تطبيق':'Apply to selected'?></button></div>
   <p class="uc-muted"><?=$ucArabic?'هذا الإجراء يغيّر ظهور المنتجات فقط ولا يحذف المخزون أو الطلبات. يتم استعادة المنتجات المؤرشفة كمسودات.':'Only product visibility changes. Orders, stock and history remain intact. Restored products become drafts.'?></p>
   <div class="uc-table-wrap"><table class="uc-table" data-uc-visible-table><thead><tr><th class="uc-check-column"><input type="checkbox" data-uc-bulk-all aria-label="<?=$ucArabic?'تحديد المنتجات الظاهرة':'Select visible products'?>"></th><th><?=$ucArabic?'المنتج':'Product'?></th><th><?=$ucArabic?'العلامة':'Brand'?></th><th>SKUs</th><th><?=$ucArabic?'المخزون':'Stock'?></th><th><?=$ucArabic?'الحالة':'Status'?></th><th><?=$ucArabic?'تعديل':'Edit'?></th></tr></thead><tbody><?php foreach($list['rows'] as $p):?><tr><td><input type="checkbox" name="product_ids[]" value="<?=(int)$p['id']?>" data-uc-bulk-item aria-label="<?=$e(($ucArabic?'حدد ':'Select ').(string)$p['name'])?>"></td><td><strong><?=$e($p['name'])?></strong><small>#<?=(int)$p['id']?> · <?=$e($p['slug'])?></small></td><td><?=$e($p['brand_name']??'—')?></td><td><?=(int)$p['variant_count']?></td><td><?=(int)$p['available_stock']?></td><td><?=$e($p['status'])?></td><td><a class="app-button admin-button secondary" href="<?=$e($base)?>?edit=<?=(int)$p['id']?>&amp;department=<?=(int)$selectedDepartment?>"><?=$ucArabic?'تعديل':'Edit'?></a></td></tr><?php endforeach;?></tbody></table></div>
 </form>
 <?php if(!$list['rows']):?><p class="uc-muted">No products match this search.</p><?php endif;?>
 <?php if($selectedDepartment===0 && $list['rows']):?><details class="uc-quick-create"><summary>Classify existing catalog products into departments</summary><div style="padding:12px"><p class="uc-muted">Assign a product's category in Department Manager first. This changes only classification, not stock or order history.</p><div class="uc-table-wrap"><table class="uc-table"><thead><tr><th>Product</th><th>Primary department</th></tr></thead><tbody><?php foreach($list['rows'] as $p):?><tr><td><?=$e($p['name'])?> (#<?=(int)$p['id']?>)</td><td><form method="post" class="uc-inline-assign"><?=csrf_field()?><input type="hidden" name="action" value="assign_legacy_product"><input type="hidden" name="product_id" value="<?=(int)$p['id']?>"><select name="department_id" required><option value="">Choose</option><?php foreach($departments as $d):if($d['status']!=='active')continue;?><option value="<?=(int)$d['id']?>"><?=$e($d['name'])?></option><?php endforeach;?></select><button class="app-button admin-button secondary">Assign</button></form></td></tr><?php endforeach;?></tbody></table></div></div></details><?php endif;?>
 <div class="uc-pagination"><?php $query=http_build_query(['q'=>(string)($_GET['q']??''),'department'=>$selectedDepartment,'status'=>$listStatus]);if((int)$list['page']>1):?><a href="<?=$e($base)?>?<?=$e($query)?>&page=<?=(int)$list['page']-1?>">← Previous</a><?php endif;?><span>Page <?=(int)$list['page']?> / <?=(int)$list['total_pages']?></span><?php if((int)$list['page']<(int)$list['total_pages']):?><a href="<?=$e($base)?>?<?=$e($query)?>&page=<?=(int)$list['page']+1?>">Next →</a><?php endif;?></div>
</section>
<?php endif;?>
<?php endif; // end regular editor (Department Manager has its own workspace) ?>
<script src="<?=$e(url('assets/js/admin-universal.js'))?>" defer></script>
<script src="<?=$e(url('assets/js/editor-discovery.js'))?>" defer></script>
<?php admin_page_end(); ?>
