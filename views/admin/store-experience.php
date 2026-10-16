<?php
admin_page_start('Store Experience','store-experience','STORE SETUP','Store','Experience','One guided workspace for client branding, themes, departments and storefront presentation.');
?>
<?php
$sxArabic=function_exists('store_lang')&&store_lang()==='ar';
// Admin editor labels are isolated here instead of being hardcoded into JS.
$sxCopy=[
 'Save Draft'=>'حفظ المسودة','Publish Theme'=>'نشر القالب','Restore Previous'=>'استعادة السابق',
 'Store identity'=>'هوية المتجر','Store name'=>'اسم المتجر','Tagline'=>'الشعار النصي','Logo subtitle'=>'النص الفرعي للشعار',
 'Choose a store style'=>'اختر أسلوب المتجر','Accent / buttons'=>'اللون المميز والأزرار',
 'Page background'=>'خلفية الصفحة','Main text'=>'النص الرئيسي','Footer background'=>'خلفية التذييل',
 'Typography'=>'الخطوط','Layout spacing'=>'المسافات','Card corners'=>'زوايا البطاقات',
 'Design your navigation'=>'تصميم شريط التنقل','Navigation spacing'=>'مسافات التنقل',
 'Sticky header'=>'شريط تنقل ثابت','Finish your storefront'=>'أكمل إعداد المتجر',
 'Departments & catalog'=>'الأقسام والمنتجات','Navigation Builder'=>'منشئ التنقل','Store modules'=>'وحدات المتجر',
 'Homepage Builder'=>'منشئ الصفحة الرئيسية','Business settings'=>'إعدادات النشاط التجاري',
 'Review and publish'=>'مراجعة ونشر','Storefront preview'=>'معاينة المتجر',
 'Desktop'=>'كمبيوتر','Tablet'=>'تابلت','Mobile'=>'موبايل',
 'Open full real preview ↗'=>'فتح المعاينة الكاملة ↗','Refresh saved draft'=>'تحديث المسودة المحفوظة',
 'Open full preview ↗'=>'فتح المعاينة الكاملة ↗','Saved draft · real storefront'=>'مسودة محفوظة · المتجر الفعلي',
 'Unsaved changes · Save draft before refreshing the real preview'=>'تغييرات غير محفوظة · احفظ المسودة أولًا لتحديث المعاينة',
 'Saved draft · Not yet published'=>'مسودة محفوظة · لم تُنشر بعد',
 'Preview reflects the last saved draft'=>'المعاينة تعرض آخر مسودة محفوظة',
 'Store setup steps'=>'خطوات إعداد المتجر','Storefront draft preview'=>'معاينة مسودة المتجر',
];
$sxText=static fn(string $label):string=>$sxArabic?($sxCopy[$label]??$label):$label;
?>
<link rel="stylesheet" href="<?=$h(asset_url('assets/css/store-experience.css'))?>">
<?php if($flash):?><div class="app-alert admin-alert success" role="status"><?=$h($flash)?></div><?php endif;?>
<?php if($error):?><div class="app-alert admin-alert danger" role="alert"><?=$h($error)?></div><?php endif;?>


<section class="app-panel sx-unified-intro" aria-label="Store Designer workflow">
 <div><strong><?=$sxArabic?'مصمم المتجر · المظهر':'Store Designer · Theme'?></strong><p><?=$sxArabic?'القالب يتحكم في الهوية البصرية. منشئ الصفحة الرئيسية يتحكم في الأقسام وترتيبها وعدد الأعمدة وارتفاع الصور. تغيير القالب لا يحذف محتوى الصفحة.':'Theme Studio sets the global style. Homepage Builder owns your sections, their order, columns and banner heights. Changing a theme never replaces the homepage.'?></p><span class="sx-status"><?=$sxArabic?($published===null?'إعدادات التصميم السابقة':'القالب منشور'):($published===null?'Using existing store design':'A theme is published')?></span></div>
 <a class="app-button admin-button secondary" href="<?=$h(route_url('admin/homepage.php'))?>"><?=$sxArabic?'تعديل أقسام الصفحة الرئيسية ↗':'Edit homepage sections ↗'?></a>
</section>
<div class="sx-layout">
<form id="sx-form" class="sx-editor" action="<?=$h(route_url('admin/store-experience.php'))?>" method="post" data-sx-form data-sx-lang="<?=$sxArabic?'ar':'en'?>">
<?=csrf_field()?><input type="hidden" name="revision" value="<?=$revision?>">
<section id="sx-identity" class="app-panel sx-panel"><div class="sx-panel-head"><p class="panel-kicker"><?=$sxArabic?'الخطوة ١':'STEP 1'?></p><h3><?=$h($sxText('Store identity'))?></h3><p><?=$sxArabic?'لن تظهر هذه التغييرات للعملاء قبل النشر. يظل الاسم والشعار الحاليان دون تغيير حتى ذلك الحين.':'These fields become visible only after publishing. The existing store name and tagline are preserved until then.'?></p></div>
<div class="sx-two"><label class="app-field"><span><?=$h($sxText('Store name'))?></span><input required name="store_name" maxlength="70" value="<?=$h($draft['store_name'])?>" data-sx-name></label><label class="app-field"><span><?=$h($sxText('Logo subtitle'))?> <small><?=$sxArabic?'(اختياري)':'(optional)'?></small></span><input name="logo_subtitle" maxlength="30" value="<?=$h($draft['logo_subtitle'])?>" data-sx-subtitle></label></div>
<label class="app-field"><span><?=$h($sxText('Tagline'))?></span><input name="tagline" maxlength="180" value="<?=$h($draft['tagline'])?>" data-sx-tagline></label><p class="sx-tip"><?=$sxArabic?'اسم العلامة يأتي من اسم المتجر المحفوظ. يظهر التعديل في الرأس بعد نشر القالب. لتغيير ملفات الشعار والأيقونة، افتح ':'The header brand comes from the saved store name. Publish changes to display them. For logo and favicon files, open '?><a href="<?=$h(route_url('admin/store-settings.php'))?>">Store Designer</a><?=$sxArabic?'. مكتبة الصور تبقى موحدة.':'. The existing media library is retained.'?></p>
</section>
<section id="sx-theme" class="app-panel sx-panel"><div class="sx-panel-head"><p class="panel-kicker"><?=$sxArabic?'الخطوة ٢':'STEP 2'?></p><h3><?=$h($sxText('Choose a store style'))?></h3><p><?=$sxArabic?'ابدأ بألوان متجرك، ثم اختر تخطيط عرض المنتجات. لن يتغير المخزون أو إتمام الطلبات.':'Start with your industry\'s palette, then select a real storefront layout below. Your inventory and checkout remain unchanged.'?></p></div>
<div class="sx-preset-grid">
<?php foreach($presets as $code=>$preset):?><label class="sx-preset-option"><input type="radio" name="preset" value="<?=$h($code)?>" data-sx-preset="<?=$h($code)?>" <?=$draft['preset']===$code?'checked':''?>><span class="sx-preset"><span class="sx-swatches"><i style="background:<?=$h($preset['accent'])?>"></i><i style="background:<?=$h($preset['background'])?>"></i><i style="background:<?=$h($preset['text'])?>"></i></span><strong><?=$h($sxArabic?(['neutral'=>'محايد','perfume'=>'عطور','fashion'=>'أزياء','beauty'=>'تجميل','electronics'=>'إلكترونيات'][$code]??ucfirst($code)):ucfirst($code))?></strong></span></label><?php endforeach;?>
</div>

<fieldset class="sx-layout-choices"><legend><?=$sxArabic?'تصميم المتجر':'Storefront presentation'?></legend><p><?=$sxArabic?'يتحكم القالب في المظهر العام، بينما يتحكم منشئ الصفحة الرئيسية في الأقسام وترتيبها وأعمدتها. تغيير الألوان لا يغير تصميم الصفحة.':'Theme layouts change the catalog and global product presentation. The Homepage Builder always controls your homepage sections, columns, heights and order. Color presets never change this choice.'?></p><div class="sx-layout-grid">
<?php foreach(['classic'=>['Classic / Existing','Preserve the original layout while adjusting colors and typography'],'editorial'=>['Editorial / Perfume','Serif typography and premium, spacious catalog cards'],'boutique'=>['Boutique / Fashion','Fashion-style catalog cards, spacing and typography'],'catalog'=>['Catalog / Electronics','Dense catalog grids and information-first product pages']] as $code=>$copy):?>
<?php if($sxArabic){$copy=match($code){'classic'=>['كلاسيكي','احتفظ بالتنسيق الأصلي مع تخصيص الألوان والخطوط'],'editorial'=>['تحريري / عطور','منتجات فاخرة وخطوط أنيقة ومساحات واسعة'],'boutique'=>['بوتيك / أزياء','بطاقات منتجات وتصميم مناسب للأزياء'],'catalog'=>['كتالوج / إلكترونيات','شبكة منتجات كثيفة وعرض تقني واضح'],default=>$copy};} ?><label class="sx-layout-choice"><input type="radio" name="storefront_layout" value="<?=$h($code)?>" <?=$draft['storefront_layout']===$code?'checked':''?>><span class="sx-layout-mini sx-layout-mini-<?=$h($code)?>" aria-hidden="true"><i></i><b></b><em></em><em></em><em></em></span><span><strong><?=$h($copy[0])?></strong><small><?=$h($copy[1])?></small></span></label>
<?php endforeach;?></div></fieldset>
<fieldset class="sx-display-controls"><legend><?=$sxArabic?'خيارات العرض':'Storefront layout controls'?></legend>
<p><?=$sxArabic?'تؤثر هذه الاختيارات على عرض المنتجات والكتالوج فقط؛ لا تستبدل أقسام منشئ الصفحة الرئيسية.':'Customize how the catalog is presented. Homepage Builder retains ownership of its own section layouts.'?></p>
<div class="sx-display-grid">
<label class="app-field"><span><?=$sxArabic?'عرض المحتوى':'Content width'?></span><select name="content_width"><option value="standard" <?=$draft['content_width']==='standard'?'selected':''?>><?=$sxArabic?'افتراضي':'Standard · current width'?></option><option value="contained" <?=$draft['content_width']==='contained'?'selected':''?>><?=$sxArabic?'قياسي':'Contained'?></option><option value="wide" <?=$draft['content_width']==='wide'?'selected':''?>><?=$sxArabic?'عريض':'Wide'?></option></select></label>
<label class="app-field"><span><?=$sxArabic?'أعمدة الكتالوج - سطح المكتب':'Catalog columns · desktop'?></span><select name="catalog_columns"><option value="0" <?=$draft['catalog_columns']===0?'selected':''?>><?=$sxArabic?'تلقائي حسب التصميم':'Auto · follow selected layout'?></option><?php foreach([3,4,5] as $col):?><option value="<?=$col?>" <?=$draft['catalog_columns']===$col?'selected':''?>><?=$col?></option><?php endforeach;?></select></label>
<label class="app-field"><span><?=$sxArabic?'نسبة صورة المنتج':'Product image shape'?></span><select name="product_image_shape"><?php foreach(['square'=>($sxArabic?'مربع':'Square'),'portrait'=>($sxArabic?'طولي':'Portrait'),'landscape'=>($sxArabic?'عرضي':'Landscape')] as $value=>$label):?><option value="<?=$value?>" <?=$draft['product_image_shape']===$value?'selected':''?>><?=$label?></option><?php endforeach;?></select></label>
<label class="app-field"><span><?=$sxArabic?'عرض الصورة داخل البطاقة':'Product image fit'?></span><select name="product_image_fit"><option value="contain" <?=$draft['product_image_fit']==='contain'?'selected':''?>><?=$sxArabic?'عرض الصورة كاملة':'Contain · show full image'?></option><option value="cover" <?=$draft['product_image_fit']==='cover'?'selected':''?>><?=$sxArabic?'ملء الإطار':'Cover · crop to fill'?></option></select></label>
<label class="app-field"><span><?=$sxArabic?'أسلوب شريط التنقل':'Navigation style'?></span><select name="navigation_style"><option value="minimal" <?=$draft['navigation_style']==='minimal'?'selected':''?>><?=$sxArabic?'بسيط':'Minimal'?></option><option value="underline" <?=$draft['navigation_style']==='underline'?'selected':''?>><?=$sxArabic?'خط أسفل الرابط':'Underline'?></option></select></label>
</div></fieldset>
<div class="sx-two sx-colors">
<?php foreach(['accent'=>'Accent / buttons','background'=>'Page background','text'=>'Main text','footer'=>'Footer background'] as $field=>$caption):?><label class="app-field"><span><?=$h($sxText($caption))?></span><span class="sx-color-control"><input type="color" name="<?=$field?>" value="<?=$h($draft[$field])?>" data-sx-color="<?=$field?>"><output data-sx-color-output="<?=$field?>"><?=$h($draft[$field])?></output></span></label><?php endforeach;?>
<label class="app-field"><span><?=$h($sxText('Typography'))?></span><select name="font" data-sx-font><?php foreach(['editorial'=>'Editorial serif','modern'=>'Modern sans-serif','system'=>'System fonts'] as $k=>$v):?><option value="<?=$k?>" <?=$draft['font']===$k?'selected':''?>><?=$h($sxText($v))?></option><?php endforeach;?></select></label>
<label class="app-field"><span><?=$h($sxText('Layout spacing'))?></span><select name="density" data-sx-density><?php foreach(['compact'=>'Compact','comfortable'=>'Comfortable','spacious'=>'Spacious'] as $k=>$v):?><option value="<?=$k?>" <?=$draft['density']===$k?'selected':''?>><?=$h($sxText($v))?></option><?php endforeach;?></select></label>
<label class="app-field"><span><?=$h($sxText('Card corners'))?></span><select name="radius" data-sx-radius><?php foreach([0=>'Square',2=>'Subtle',8=>'Rounded',16=>'Soft rounded'] as $k=>$v):?><option value="<?=$k?>" <?=$draft['radius']===(int)$k?'selected':''?>><?=$h($sxText($v))?></option><?php endforeach;?></select></label>
</div>
<label class="sx-switch"><input name="show_utility_bar" type="checkbox" value="1" <?=$draft['show_utility_bar']?'checked':''?> data-sx-utility><span><strong><?=$sxArabic?'إظهار الشريط المعلوماتي العلوي':'Show top information strip'?></strong><small><?=$sxArabic?'إظهار أو إخفاء شريط التوصيل وخدمة العملاء دون تغيير قواعد الشحن.':'Hides or shows the existing delivery and customer-support strip. Does not alter shipping rules.'?></small></span></label>
</section>

<section id="sx-header" class="app-panel sx-panel sx-header-editor">
 <div class="sx-panel-head"><p class="panel-kicker"><?=$sxArabic?'تصميم الرأس':'HEADER DESIGN'?></p><h3><?=$h($sxText('Design your navigation'))?></h3><p><?=$sxArabic?'اختر شكل الرأس وشاهده على الكمبيوتر والتابلت والموبايل. الروابط والفئات تأتي من منشئ التنقل.':'Choose a layout and immediately inspect desktop, tablet and mobile. Links and categories come from the Navigation Builder.'?></p></div>
 <div class="sx-header-options" role="group" aria-label="Header layout">
 <?php foreach(['balanced'=>['Balanced','Logo left · links centred · actions right'],'compact'=>['Compact','A shorter, space-saving header'],'centered'=>['Centred','Brand above a slim navigation row']] as $mode=>$values):?>
 <label class="sx-header-choice"><input type="radio" name="header_layout" value="<?=$h($mode)?>" <?=$draft['header_layout']===$mode?'checked':''?>><span><strong><?=$h($sxText($values[0]))?></strong><small><?=$h($sxText($values[1]))?></small></span></label>
 <?php endforeach;?>
 </div>
 <div class="sx-two"><label class="app-field"><span><?=$h($sxText('Navigation spacing'))?></span><select name="header_density"><option value="comfortable" <?=$draft['header_density']==='comfortable'?'selected':''?>><?=$sxArabic?'مريح':'Comfortable'?></option><option value="compact" <?=$draft['header_density']==='compact'?'selected':''?>><?=$sxArabic?'متقارب ومدمج':'Tight / compact'?></option></select></label>
 <label class="sx-switch sx-inline-switch"><input type="checkbox" name="sticky_header" value="1" <?=$draft['sticky_header']?'checked':''?>><span><strong><?=$h($sxText('Sticky header'))?></strong><small><?=$sxArabic?'إبقاء شريط التنقل ظاهرًا أثناء التمرير':'Keep the header visible as customers scroll'?></small></span></label></div>
 <p class="sx-tip"><?=$sxArabic?'لتحرير الروابط والفئات في القائمة، افتح ':'To edit links and categories in the menu, open '?><a href="<?=$h(route_url('admin/storefront-configuration.php'))?>"><?=$h($sxText('Navigation Builder'))?> ↗</a><?=$sxArabic?'. هنا تختار شكل القائمة فقط.':'. This page controls the menu appearance only.'?></p>
</section>
<section class="app-panel sx-panel sx-related"><div class="sx-panel-head"><p class="panel-kicker"><?=$sxArabic?'المحررات المرتبطة':'RELATED EDITORS'?></p><h3><?=$h($sxText('Finish your storefront'))?></h3><p><?=$sxArabic?'استخدم المحررات المرتبطة دون إنشاء بيانات أقسام أو تنقل أو صفحة رئيسية مكررة.':'Use the existing connected editors; no duplicated category, navigation, or homepage data.'?></p></div>
<div class="sx-tasks">
<a href="<?=$h(route_url('admin/universal-catalog.php?departments=1'))?>"><strong><?=$h($sxText('Departments & catalog'))?></strong><span><?=count($departments)?><?=$sxArabic?' قسم نشط · إدارة المنتجات والفئات وخصائص النشاط ←':' active departments · Control products, categories and industry attributes →'?></span></a>
<a href="<?=$h(route_url('admin/storefront-configuration.php'))?>"><strong><?=$h($sxText('Navigation Builder'))?></strong><span><?=$sxArabic?'إعداد روابط الزبائن وقائمة الأقسام والتنقل على الموبايل ←':'Choose customer links, department menu, mobile navigation and page introductions →'?></span></a>
<a href="<?=$h(route_url('admin/store-modules.php'))?>"><strong><?=$h($sxText('Store modules'))?></strong><span><?=$sxArabic?'التحكم في المفضلة والتقييمات والإضافة السريعة واقتراحات المنتجات ←':'Enable or pause customer wishlist, verified reviews, quick add and product recommendations →'?></span></a>
<a href="<?=$h(route_url('admin/homepage.php'))?>"><strong><?=$h($sxText('Homepage Builder'))?></strong><span><?=$sxArabic?'تحرير البانرات وأقسام المنتجات والعروض والصور والفيديو ←':'Edit banners, product sections, carousels, imagery and videos →'?></span></a>
<a href="<?=$h(route_url('admin/store-settings.php'))?>"><strong><?=$h($sxText('Business settings'))?></strong><span><?=$sxArabic?'طرق الدفع والشحن والشعار والتذييل ←':'Existing payment methods, shipping policies, logos, and footer content →'?></span></a>
</div></section>
<section id="sx-publish" class="app-panel sx-panel sx-publish"><div><h3><?=$h($sxText('Review and publish'))?></h3><p><?=$sxArabic?'حفظ المسودة لا يغير المتجر المنشور. النشر يجعل التعديلات مرئية للعملاء. الاستعادة تعيد القالب المنشور السابق.':'Save Draft stores your changes privately. Publish makes the theme visible to customers. Restore returns to the previously published theme, or to the original Store Designer settings.'?></p></div><div class="sx-buttons"><button type="submit" name="intent" value="save" class="app-button admin-button secondary"><?=$h($sxText('Save Draft'))?></button><?php if($published!==null):?><button type="submit" name="intent" value="restore" class="app-button admin-button quiet" data-confirm="Restore the previous theme? Your draft will remain saved."><?=$h($sxText('Restore Previous'))?></button><?php endif;?><button type="submit" name="intent" value="publish" class="app-button admin-button primary"><?=$h($sxText('Publish Theme'))?></button></div></section>
</form>
<aside class="sx-preview" aria-label="Visual theme preview">
 <div class="sx-preview-head"><div><p class="panel-kicker"><?=$sxArabic?'استوديو المعاينة':'VISUAL THEME STUDIO'?></p><h3><?=$h($sxText('Storefront preview'))?> <small class="sx-preview-hint"><?=$sxArabic?'مسودة محفوظة · المتجر الفعلي':'Saved draft · real storefront'?></small></h3></div>
 <div class="sx-preview-toggle" role="group" aria-label="Preview viewport"><button type="button" class="is-active" data-sx-mode="desktop" aria-pressed="true"><?=$h($sxText('Desktop'))?></button><button type="button" data-sx-mode="tablet" aria-pressed="false"><?=$h($sxText('Tablet'))?></button><button type="button" data-sx-mode="mobile" aria-pressed="false"><?=$h($sxText('Mobile'))?></button></div></div>
 <p class="sx-preview-explainer"><?=$sxArabic?'المعاينة تستخدم أقسام الصفحة الحقيقية وآخر إعدادات مسودة محفوظة. احفظ المسودة لتظهر تعديلاتك هنا دون نشرها.':'Real storefront preview using saved draft settings and real Homepage Builder sections. Save Draft to see your latest changes here; this never publishes them.'?></p>
 <div class="sx-real-toolbar"><a href="<?=$h(route_url('index.php').'?homepage_preview=1&theme_draft_preview=1')?>" target="_blank" rel="noopener noreferrer"><?=$h($sxText('Open full real preview ↗'))?></a><button type="button" data-sx-preview-reload><?=$h($sxText('Refresh saved draft'))?></button></div>
 <p class="sx-preview-pending" data-sx-preview-hint hidden><?=$sxArabic?'التغييرات الحالية غير موجودة في المعاينة الفعلية بعد. احفظ المسودة أولًا.':'Your current edits are not in the real preview yet. Save Draft first.'?></p><div class="sx-real-preview-box" data-sx-real-box data-mode="desktop"><iframe data-sx-real-frame title="<?=$h($sxText('Storefront draft preview'))?>" loading="lazy" src="<?=$h(route_url('index.php').'?homepage_preview=1&homepage_preview_embed=1&theme_draft_preview=1')?>"></iframe></div>
 <details class="sx-preview-sketch"><summary><?=$sxArabic?'عرض رسم توضيحي للألوان غير المحفوظة':'Show an illustrative sketch for unsaved color changes'?></summary>
 <div class="sx-preview-canvas"><div class="sx-preview-frame" data-sx-preview data-mode="desktop" data-sx-layout="<?=$h($draft['header_layout'])?>" data-sx-storefront-layout="<?=$h($draft['storefront_layout'])?>">
  <div class="sx-preview-utility" data-sx-preview-utility>DELIVERY · ORIGINAL PRODUCTS · SUPPORT</div>
  <div class="sx-preview-header"><button class="sx-preview-hamburger" type="button" data-sx-mobile-menu aria-label="Toggle preview menu" aria-expanded="false">☰</button><div class="sx-preview-brand"><strong data-sx-preview-name><?=$h($draft['store_name'])?></strong><small data-sx-preview-subtitle><?=$h($draft['logo_subtitle'])?></small></div><span class="sx-preview-actions" aria-hidden="true">⌕ &nbsp;♡ &nbsp;♧</span></div>
  <div class="sx-preview-nav" data-sx-preview-nav><?php foreach(($previewNavigation['audiences']??[]) as $a):?><span><?=ucfirst($h($a))?> ⌄</span><?php endforeach;?><?php foreach(array_slice($previewNavigation['links']??[],0,6) as $nav):if(empty($nav['desktop']))continue;?><span><?=$h($nav['label'])?><?=$nav['type']==='shop'?'⌄':''?></span><?php endforeach;?></div>
  <div class="sx-preview-drawer" data-sx-preview-drawer hidden><div class="sx-preview-drawer-head"><strong>MENU</strong><button type="button" data-sx-mobile-menu-close aria-label="Close preview menu">✕</button></div><?php foreach(($previewNavigation['audiences']??[]) as $a):?><div><?=ucfirst($h($a))?> <span>⌄</span></div><?php endforeach;?><?php foreach(($previewNavigation['links']??[]) as $nav):if(empty($nav['mobile']))continue;?><div><?=$h($nav['label'])?> <span>›</span></div><?php endforeach;?></div>
  <div class="sx-preview-hero"><small>THE NEW COLLECTION</small><h2 data-sx-preview-tagline><?=$h($draft['tagline']!==''?$draft['tagline']:'Discover your next favourite.')?></h2><span>Explore the collection →</span></div>
  <div class="sx-preview-products"><div><i></i><strong>New arrival</strong><small>Explore products</small></div><div><i></i><strong>Customer favourite</strong><small>Browse collection</small></div><div><i></i><strong>Handpicked for you</strong><small>Discover more</small></div></div>
  <div class="sx-preview-footer"><strong data-sx-preview-footer-name><?=$h($draft['store_name'])?></strong> · CUSTOMER SUPPORT</div>
 </div></div></details>
 <div class="sx-preview-meta"><span data-sx-contrast><?=$sxArabic?'جارٍ فحص تباين النص…':'Checking text contrast…'?></span><span data-sx-draft-state role="status" aria-live="polite"><?=$h($sxText('Saved draft · Not yet published'))?></span></div>
 <p class="sx-preview-foot"><?=$sxArabic?'الرسم التوضيحي يعرض التغييرات غير المحفوظة فقط. المعاينة الفعلية تعتمد على آخر مسودة محفوظة وأقسام الصفحة الرئيسية.':'The sketch above shows unsaved styling only. The real preview uses saved draft theme settings and your actual homepage sections.'?> <a href="<?=$h(route_url('index.php'))?>" target="_blank" rel="noopener noreferrer"><?=$sxArabic?'عرض المتجر المنشور حاليًا ↗':'View the currently published website ↗'?></a>. Existing product images, homepage sections and navigation destinations remain in their respective builders.</p>
</aside>
</div>
<script type="application/json" id="sx-presets-json"><?=json_encode($presets,JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_UNESCAPED_SLASHES)?></script>
<script src="<?=$h(asset_url('assets/js/store-experience.js'))?>" defer></script>
<?php admin_page_end(); ?>
