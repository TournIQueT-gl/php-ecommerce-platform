<?php
declare(strict_types=1);


function admin_nav_icon(string $key): string {
    $icons=[
        'overview'=>'<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 4h6v6H4zM14 4h6v6h-6zM4 14h6v6H4zM14 14h6v6h-6z"/></svg>',
        'universal-catalog'=>'<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 4h16v16H4zM8 9h8M8 13h8M8 17h5"/></svg>',
        'catalog'=>'<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 7.5 12 3l8 4.5v9L12 21l-8-4.5z"/><path d="M4 7.5 12 12l8-4.5M12 12v9"/></svg>',
        'inventory'=>'<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 6h16v13H4zM7 3h10v3H7zM8 10h8M8 14h5"/></svg>',
        'taxonomy'=>'<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 5h7l2 3h7v11H4z"/><path d="M8 12h8M8 16h5"/></svg>',
        'media'=>'<svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="4" width="18" height="16" rx="2"/><circle cx="9" cy="9" r="2"/><path d="m5 18 5-5 3 3 2-2 4 4"/></svg>',
        'orders'=>'<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 4h14l-1 16H6zM9 8h6M8 12h8M8 16h5"/></svg>',
        'returns'=>'<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M8 7H4v-4M4 7c2-3 5-4 8-4 5 0 9 4 9 9s-4 9-9 9c-4 0-7-2-8.5-5"/></svg>',
        'shipping'=>'<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 6h11v11H3zM14 10h4l3 3v4h-7z"/><circle cx="7" cy="18" r="2"/><circle cx="18" cy="18" r="2"/></svg>',
        'customers'=>'<svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="9" cy="8" r="3"/><path d="M3.5 19c.5-4 2.3-6 5.5-6s5 2 5.5 6M16 6.5a3 3 0 0 1 0 5.5M17 13c2.2.5 3.4 2.4 3.8 5"/></svg>',
        'support'=>'<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 5h16v11H9l-5 4z"/><path d="M8 9h8M8 12h5"/></svg>',
        'homepage'=>'<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 11.5 12 4l9 7.5M5.5 10.5V20h13v-9.5M9 20v-6h6v6"/></svg>',
        'coupons'=>'<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 7h16v4a2 2 0 0 0 0 4v4H4v-4a2 2 0 0 0 0-4z"/><path d="M12 7v12"/></svg>',
        'reviews'=>'<svg viewBox="0 0 24 24" aria-hidden="true"><path d="m12 3 2.7 5.5 6.1.9-4.4 4.3 1 6.1-5.4-2.9-5.4 2.9 1-6.1-4.4-4.3 6.1-.9z"/></svg>',
        'announcements'=>'<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 10v4h4l7 4V6l-7 4zM15 9c2 1 2 5 0 6"/></svg>',
        'analytics'=>'<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 20V10M10 20V4M16 20v-7M22 20H2"/></svg>',
        'languages'=>'<svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3c5 5 5 13 0 18M12 3c-5 5-5 13 0 18"/></svg>',
        'store-modules'=>'<svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="3" width="18" height="18" rx="3"/><path d="M7 8h10M7 12h10M7 16h10"/></svg>',
        'store-experience'=>'<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 4h16v16H4zM4 10h16M10 10v10"/><circle cx="15" cy="15" r="2"/></svg>',
        'store-settings'=>'<svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="3"/><path d="M12 3v2M12 19v2M3 12h2M19 12h2M5.6 5.6 7 7M17 17l1.4 1.4M18.4 5.6 17 7M7 17l-1.4 1.4"/></svg>',
        'access'=>'<svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="8" cy="8" r="3"/><path d="M2.5 19c.6-4 2.4-6 5.5-6 2 0 3.5.8 4.4 2.4M15 11l2 2 4-4M15 13v6h6v-6"/></svg>',
        'email'=>'<svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="m4 7 8 6 8-6"/></svg>',
        'security'=>'<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3 5 6v5c0 4.8 2.5 8 7 10 4.5-2 7-5.2 7-10V6z"/><path d="m9 12 2 2 4-5"/></svg>',
        'activity'=>'<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 12h4l2-6 4 12 2-6h6"/></svg>',
        'health'=>'<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 12h4l2-4 4 8 2-4h4"/><circle cx="12" cy="12" r="9"/></svg>',
        'operations'=>'<svg viewBox="0 0 24 24" aria-hidden="true"><ellipse cx="12" cy="6" rx="7" ry="3"/><path d="M5 6v6c0 1.7 3.1 3 7 3s7-1.3 7-3V6M5 12v6c0 1.7 3.1 3 7 3s7-1.3 7-3v-6"/></svg>',
    ];
    return $icons[$key]??'<svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="7"/></svg>';
}

/** Render translations only; never translate route keys, status codes or stored values. */
function admin_ui_t(string $english): string {
    if(!function_exists('store_lang') || store_lang()!=='ar') return $english;
    static $labels=[
        'ADMIN'=>'الإدارة',
        'STORE'=>'المتجر',
        'CATALOG'=>'الكتالوج',
        'ORDERS'=>'الطلبات',
        'CUSTOMERS'=>'العملاء',
        'CONTENT & MARKETING'=>'المحتوى والتسويق',
        'INSIGHTS'=>'التقارير',
        'SETTINGS'=>'الإعدادات',
        'ADVANCED'=>'متقدم',
        'Dashboard'=>'لوحة التحكم',
        'Products'=>'المنتجات',
        'Universal Editor · v5'=>'المحرر الشامل',
        'Inventory'=>'المخزون',
        'Catalog Setup'=>'إعداد الكتالوج',
        'Media Library'=>'مكتبة الوسائط',
        'Orders'=>'الطلبات',
        'Returns & Refunds'=>'المرتجعات واسترداد المبالغ',
        'Shipping'=>'الشحن',
        'Customers'=>'العملاء',
        'Support'=>'الدعم',
        'Homepage'=>'الصفحة الرئيسية',
        'Coupons & Offers'=>'الكوبونات والعروض',
        'Reviews'=>'التقييمات',
        'Announcements'=>'الإعلانات',
        'Analytics'=>'التحليلات',
        'Store Setup & Themes'=>'إعداد المتجر والقوالب',
        'Languages & Translations'=>'اللغات والترجمات',
        'Store Modules'=>'وحدات المتجر',
        'Navigation Builder'=>'منشئ القوائم',
        'Store Designer'=>'مصمم المتجر',
        'Team & Access'=>'الفريق والصلاحيات',
        'Email Delivery'=>'إرسال البريد',
        'Security'=>'الأمان',
        'Audit Log'=>'سجل العمليات',
        'System Health'=>'حالة النظام',
        'Backups & Operations'=>'النسخ الاحتياطي والعمليات',
        'Customer Account'=>'حساب العميل',
        'View Store'=>'عرض المتجر',
        'View store ↗'=>'عرض المتجر ↗',
        'View storefront ↗'=>'عرض المتجر ↗',
        'Logout'=>'تسجيل الخروج',
        'Close admin navigation'=>'إغلاق قائمة الإدارة',
        'Open admin navigation'=>'فتح قائمة الإدارة',
        'Collapse admin navigation'=>'طي قائمة الإدارة',
        'Collapse navigation'=>'طي القائمة',
        'Notifications'=>'الإشعارات',
        'unread'=>'غير مقروء',
        'COMMERCE · ADMIN'=>'التجارة · الإدارة',
        'STORE CONTROL'=>'إدارة المتجر',
        'Homepage Builder'=>'منشئ الصفحة الرئيسية',
        'CONTENT'=>'المحتوى',
        'SECTION'=>'القسم',
        'PAGE SECTIONS'=>'أقسام الصفحة',
        'Draft'=>'مسودة',
        'Published now'=>'منشور الآن',
        'Hidden'=>'مخفي',
        'Scheduled'=>'مجدول',
        'Build the storefront visually. Choose a section, edit what customers see, save, and check the exact storefront preview beside it.'=>'أنشئ الصفحة الرئيسية بصرياً. اختر قسماً وعدّل محتواه ثم احفظ وراجع المعاينة الفعلية للمتجر.',
        'Hero'=>'البانر الرئيسي',
        'Categories'=>'الفئات',
        'Scent / mood'=>'الروائح والمزاج',
        'Editorial banner'=>'بانر تحريري',
        'Fragrance notes'=>'المكونات العطرية',
        'Occasions'=>'المناسبات',
        'Brands'=>'العلامات التجارية',
        'Gift banner'=>'بانر الهدايا',
        'Custom cards'=>'بطاقات مخصصة',
        'Video campaign'=>'حملة فيديو',
        'Shop by department'=>'تسوق حسب القسم',
        'Shop by audience'=>'تسوق حسب الفئة',
        'Large opening banner'=>'بانر افتتاحي كبير',
        'Shop by category'=>'تسوق حسب الفئة',
        'Featured or automatic products'=>'منتجات مميزة أو تلقائية',
        'Shop by mood or scent'=>'تسوق حسب الرائحة أو المزاج',
        'Story / campaign banner'=>'بانر قصة أو حملة',
        'Fragrance ingredients'=>'المكونات العطرية',
        'Shop by occasion'=>'تسوق حسب المناسبة',
        'Brand shortcuts'=>'اختصارات العلامات التجارية',
        'Gift campaign'=>'حملة الهدايا',
        'Flexible image cards'=>'بطاقات صور مرنة',
        'Full-width video campaign with poster'=>'حملة فيديو بعرض كامل مع صورة غلاف',
        'Automatic links to active departments'=>'روابط تلقائية للأقسام النشطة',
        'Men / Women / Unisex with Fashion and Perfumes'=>'رجالي / حريمي / للجنسين للأزياء والعطور',
        'Select a section to edit · arrows change display order'=>'اختر قسماً للتعديل · استخدم الأسهم لتغيير الترتيب',
        'Choose a section'=>'اختر قسماً',
        'Select a section on the left, or add one to begin.'=>'اختر قسماً من القائمة أو أضف قسماً جديداً.',
        'Section name'=>'اسم القسم',
        'Heading'=>'العنوان',
        'Arabic heading'=>'العنوان بالعربية',
        'Visibility'=>'حالة الظهور',
        'Supporting text'=>'نص توضيحي',
        'Arabic supporting text'=>'نص توضيحي بالعربية',
        'optional'=>'اختياري',
        'Not ready to publish yet'=>'غير جاهز للنشر بعد',
        'Save section'=>'حفظ القسم',
        'Unsaved changes'=>'تغييرات غير محفوظة',
        'Save to update the store preview.'=>'احفظ لتحديث معاينة المتجر.',
        'Storefront text & links'=>'النصوص والروابط',
        'Advanced design & scheduling'=>'التصميم المتقدم والجدولة',
        'View all button text'=>'نص زر عرض الكل',
        'View all destination URL'=>'رابط عرض الكل',
        'Show on storefront'=>'إظهار في المتجر',
        'Section title'=>'عنوان القسم',
        'View all link'=>'رابط عرض الكل',
        'Small heading'=>'عنوان صغير',
        'Small heading (above title)'=>'العنوان الصغير (فوق العنوان)',
        'Desktop columns'=>'أعمدة الكمبيوتر',
        'Tablet columns'=>'أعمدة التابلت',
        'Mobile columns'=>'أعمدة الموبايل',
        'Maximum items'=>'الحد الأقصى للعناصر',
        'Image ratio'=>'نسبة أبعاد الصورة',
        'Card style'=>'تصميم البطاقة',
        'Top spacing'=>'المسافة العلوية',
        'Bottom spacing'=>'المسافة السفلية',
        'Width'=>'العرض',
        'Standard'=>'قياسي',
        'Wide'=>'واسع',
        'Full width'=>'عرض كامل',
        'Product cards'=>'بطاقات المنتجات',
        'Custom colors'=>'ألوان مخصصة',
        'Override theme'=>'تجاوز ألوان القالب',
        'Background'=>'الخلفية',
        'Text'=>'النص',
        'Starts'=>'يبدأ',
        'Ends'=>'ينتهي',
        'Delete permanently'=>'حذف نهائياً',
        'Move up'=>'تحريك لأعلى',
        'Move down'=>'تحريك لأسفل',
        'Edit section'=>'تعديل القسم',
        'Duplicate section'=>'نسخ القسم',
        'Change section visibility'=>'تغيير ظهور القسم',
        'More section actions'=>'المزيد من إجراءات القسم',
        'Publish section'=>'نشر القسم',
        'Hide section'=>'إخفاء القسم',
        'Publish now'=>'نشر الآن',
        'Restore section'=>'استعادة القسم'
    ];
    return $labels[$english] ?? (function_exists('store_t') ? store_t($english) : $english);
}

function admin_page_start(string $title,string $current,string $eyebrow,string $heading,string $accent='',string $subtitle=''): void {
    $storeName=store_runtime_name();$pageTitle=htmlspecialchars(admin_ui_t($title).' · '.$storeName.' '.admin_ui_t('ADMIN'),ENT_QUOTES,'UTF-8');$GLOBALS['_admin_layout_current']=$current;
    $monogram='';foreach((preg_split('/[^A-Za-z0-9]+/',trim($storeName))?:[]) as $part){if($part==='')continue;$monogram.=strtoupper(substr($part,0,1));if(strlen($monogram)>=2)break;}if($monogram==='')$monogram='S';
    $adminUser=current_user();$adminName=trim((string)($adminUser['username']??''));if($adminName==='')$adminName='STORE ADMIN';
    $role=function_exists('admin_current_role')?strtoupper(admin_current_role($adminUser)):'ADMIN';
    $navState=['notifications_enabled'=>false,'notification_unread'=>0,'support_unread_enabled'=>false,'support_unread'=>0,'global_unread'=>0,'has_unread'=>false,'notification_href'=>route_url('admin/notifications.php')];
    $adminUserId=(int)($adminUser['id']??0);if($adminUserId>0){try{$navState=\App\Support\ServiceFactory::navigationState(db())->forUser($adminUserId,true,function_exists('admin_notification_types_for_role')?admin_notification_types_for_role($adminUser):null);}catch(\Throwable $e){\App\Support\Logger::warning('Admin navigation state unavailable',['admin_id'=>$adminUserId,'message'=>$e->getMessage()]);}}
    $topbarUnread=(int)($navState['global_unread']??0);$topbarHasUnread=!empty($navState['has_unread']);$topbarNotificationHref=(string)($navState['notification_href']??route_url('admin/notifications.php'));
    // Keep everyday store work obvious. Technical/admin-only tools live in
    // Advanced so a normal store owner does not have to scan a developer menu.
    $groups=[
        'STORE'=>[
            'overview'=>['Dashboard',route_url('admin/index.php'),'overview'],
        ],
        'CATALOG'=>[
            'catalog'=>['Products',route_url('admin/catalog.php'),'catalog_manage'],
            'universal-catalog'=>['Universal Editor · v5',route_url('admin/universal-catalog.php'),'catalog_manage'],
            'inventory'=>['Inventory',route_url('admin/inventory.php'),'inventory_manage'],
            'taxonomy'=>['Catalog Setup',route_url('admin/taxonomy.php'),'catalog_manage'],
            'media'=>['Media Library',route_url('admin/media.php'),'cms_manage'],
        ],
        'ORDERS'=>[
            'orders'=>['Orders',route_url('admin/orders.php'),'orders_manage'],
            'returns'=>['Returns & Refunds',route_url('admin/returns.php'),'orders_manage'],
            'shipping'=>['Shipping',route_url('admin/shipping.php'),'store_settings'],
        ],
        'CUSTOMERS'=>[
            'customers'=>['Customers',route_url('admin/customers.php'),'users'],
            'support'=>['Support',route_url('admin/support.php'),'support'],
        ],
        'CONTENT & MARKETING'=>[
            'homepage'=>['Homepage',route_url('admin/homepage.php'),'cms_manage'],
            'coupons'=>['Coupons & Offers',route_url('admin/coupons.php'),'marketing_manage'],
            'reviews'=>['Reviews',route_url('admin/reviews.php'),'catalog_manage'],
            'announcements'=>['Announcements',route_url('admin/announcements.php'),'announcements'],
        ],
        'INSIGHTS'=>[
            'analytics'=>['Analytics',route_url('admin/analytics.php'),'analytics_view'],
        ],
        'SETTINGS'=>[
            'store-experience'=>['Store Setup & Themes',route_url('admin/store-experience.php'),'store_settings'],
            'languages'=>['Languages & Translations',route_url('admin/languages.php'),'store_settings'],
            'store-modules'=>['Store Modules',route_url('admin/store-modules.php'),'store_settings'],
            'storefront-configuration'=>['Navigation Builder',route_url('admin/storefront-configuration.php'),'store_settings'],
            'store-settings'=>['Store Designer',route_url('admin/store-settings.php'),'store_settings'],
            'access'=>['Team & Access',route_url('admin/access.php'),'access'],
        ],
        'ADVANCED'=>[
            'email'=>['Email Delivery',route_url('admin/email.php'),'email'],
            'security'=>['Security',route_url('admin/security.php'),'security'],
            'activity'=>['Audit Log',route_url('admin/activity.php'),'activity'],
            'health'=>['System Health',route_url('admin/health.php'),'health'],
            'operations'=>['Backups & Operations',route_url('admin/operations.php'),'operations'],
        ],
    ];
    $currentLabel=$title;$currentGroup='ADMIN';foreach($groups as $groupLabel=>$links){if(isset($links[$current])){$currentLabel=$links[$current][0];$currentGroup=$groupLabel;break;}}
    $desktopCollapsed=($_COOKIE['auraa_admin_sidebar_collapsed']??'')==='1';
web_emit_server_timing('admin');
?>
<!doctype html><html lang="<?=htmlspecialchars(store_lang(),ENT_QUOTES,'UTF-8')?>" dir="<?=htmlspecialchars(store_lang_dir(),ENT_QUOTES,'UTF-8')?>"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="theme-color" content="#f6f1e9"><meta name="robots" content="noindex,nofollow"><meta name="sm-csrf" content="<?=htmlspecialchars(csrf_token(),ENT_QUOTES,'UTF-8')?>"><title><?=$pageTitle?></title><link rel="icon" type="image/svg+xml" href="<?=htmlspecialchars(asset_url('assets/images/favicon-store.svg'),ENT_QUOTES,'UTF-8')?>"><link rel="alternate icon" type="image/x-icon" href="<?=htmlspecialchars(asset_url('favicon.ico'),ENT_QUOTES,'UTF-8')?>"><link rel="apple-touch-icon" href="<?=htmlspecialchars(asset_url('apple-touch-icon.png'),ENT_QUOTES,'UTF-8')?>">
<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin><link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Playfair+Display:wght@500;600&display=swap" rel="stylesheet" media="print" data-deferred-fonts><noscript><link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Playfair+Display:wght@500;600&display=swap" rel="stylesheet"></noscript>
<script src="<?=htmlspecialchars(asset_url('assets/js/nav-pref.js'),ENT_QUOTES,'UTF-8')?>"></script><link rel="stylesheet" href="<?=htmlspecialchars(asset_url('assets/css/admin-bundle.css'),ENT_QUOTES,'UTF-8')?>"></head>
<body class="admin-page app-page store-admin-page<?=$desktopCollapsed?' admin-sidebar-collapsed':''?>" data-foundation-pulse-url="<?=htmlspecialchars(route_url('foundation-pulse.php'),ENT_QUOTES,'UTF-8')?>"><div class="admin-shell app-shell store-admin-shell">
<aside class="admin-sidebar app-sidebar store-admin-sidebar" id="admin-sidebar" aria-label="Admin navigation">
  <div class="admin-sidebar-head app-sidebar-head store-admin-brand"><a href="<?=htmlspecialchars(route_url('index.php'),ENT_QUOTES,'UTF-8')?>"><b class="app-sidebar-monogram" aria-hidden="true"><?=htmlspecialchars($monogram,ENT_QUOTES,'UTF-8')?></b><strong><?=htmlspecialchars($storeName,ENT_QUOTES,'UTF-8')?></strong><small><?=htmlspecialchars(admin_ui_t('COMMERCE · ADMIN'),ENT_QUOTES,'UTF-8')?></small></a><span><?=htmlspecialchars(admin_ui_t('STORE CONTROL'),ENT_QUOTES,'UTF-8')?></span></div>
  <nav class="admin-side-nav app-side-nav"><?php foreach($groups as $groupLabel=>$links):$visible=[];foreach($links as $key=>$item){if(admin_can($item[2],$adminUser))$visible[$key]=$item;}if(!$visible)continue;$groupActive=array_key_exists($current,$visible);$groupOpen=$groupActive||$groupLabel==='STORE';?><div class="admin-nav-group app-nav-group <?=$groupActive?'is-current-group ':''?><?=$groupOpen?'':'is-collapsed'?>" data-admin-nav-group data-group-key="<?=htmlspecialchars(strtolower($groupLabel),ENT_QUOTES,'UTF-8')?>"><button class="admin-nav-group-title app-nav-group-title" type="button" data-admin-nav-group-toggle aria-expanded="<?=$groupOpen?'true':'false'?>"><span><?=htmlspecialchars(admin_ui_t($groupLabel),ENT_QUOTES,'UTF-8')?></span><i aria-hidden="true"><?=$groupOpen?'−':'+'?></i></button><div class="admin-nav-group-links"><?php foreach($visible as $key=>[$label,$href,$cap]):?><a class="admin-side-link app-side-link <?=$current===$key?'is-current':''?>" href="<?=htmlspecialchars($href,ENT_QUOTES,'UTF-8')?>" title="<?=htmlspecialchars(admin_ui_t($label),ENT_QUOTES,'UTF-8')?>" data-label="<?=htmlspecialchars(admin_ui_t($label),ENT_QUOTES,'UTF-8')?>" data-collapsed-primary="1" <?=$current===$key?'aria-current="page"':''?>><i class="admin-nav-icon" aria-hidden="true"><?=admin_nav_icon($key)?></i><span><?=htmlspecialchars(admin_ui_t($label),ENT_QUOTES,'UTF-8')?></span></a><?php endforeach;?></div></div><?php endforeach;?></nav>
  <div class="admin-sidebar-account app-sidebar-account"><div class="admin-sidebar-identity app-sidebar-identity"><span><?=htmlspecialchars($adminName,ENT_QUOTES,'UTF-8')?></span><small><?=htmlspecialchars($role,ENT_QUOTES,'UTF-8')?></small></div><a href="<?=htmlspecialchars(route_url('dashboard.php'),ENT_QUOTES,'UTF-8')?>"><?=htmlspecialchars(admin_ui_t('Customer Account'),ENT_QUOTES,'UTF-8')?></a><a href="<?=htmlspecialchars(route_url('index.php'),ENT_QUOTES,'UTF-8')?>"><?=htmlspecialchars(admin_ui_t('View Store'),ENT_QUOTES,'UTF-8')?></a><form class="app-sidebar-logout-form" method="post" action="<?=htmlspecialchars(route_url('logout.php'),ENT_QUOTES,'UTF-8')?>"><?=csrf_field()?><button class="admin-sidebar-logout app-sidebar-logout-button" type="submit"><?=htmlspecialchars(admin_ui_t('Logout'),ENT_QUOTES,'UTF-8')?> <span>↗</span></button></form></div>
</aside>
<button class="admin-sidebar-backdrop app-sidebar-backdrop" type="button" data-admin-menu-close aria-label="<?=htmlspecialchars(admin_ui_t('Close admin navigation'),ENT_QUOTES,'UTF-8')?>"></button>
<div class="admin-workspace app-workspace"><header class="admin-topbar app-topbar"><button class="admin-menu-toggle app-menu-toggle" type="button" data-admin-menu-toggle aria-controls="admin-sidebar" aria-expanded="false" aria-label="<?=htmlspecialchars(admin_ui_t('Open admin navigation'),ENT_QUOTES,'UTF-8')?>"><span></span><span></span><span></span></button><button class="admin-sidebar-collapse app-sidebar-collapse" type="button" data-admin-sidebar-collapse aria-pressed="false" aria-label="<?=htmlspecialchars(admin_ui_t('Collapse admin navigation'),ENT_QUOTES,'UTF-8')?>" title="<?=htmlspecialchars(admin_ui_t('Collapse navigation'),ENT_QUOTES,'UTF-8')?>"><span aria-hidden="true">‹</span></button><div class="admin-topbar-copy app-topbar-copy"><span><?=htmlspecialchars(admin_ui_t($currentGroup),ENT_QUOTES,'UTF-8')?></span><strong><?=htmlspecialchars(admin_ui_t($currentLabel),ENT_QUOTES,'UTF-8')?></strong></div><div class="admin-topbar-actions"><a class="admin-locale-toggle" href="<?=htmlspecialchars(store_language_url(store_lang()==='ar'?'en':'ar'),ENT_QUOTES,'UTF-8')?>" lang="<?=store_lang()==='ar'?'en':'ar'?>" aria-label="<?=store_lang()==='ar'?'Switch to English':'التبديل إلى العربية'?>"><?=store_lang()==='ar'?'English':'العربية'?></a><a class="admin-topbar-store-link" href="<?=htmlspecialchars(route_url('index.php'),ENT_QUOTES,'UTF-8')?>" target="_blank" rel="noopener"><?=htmlspecialchars(admin_ui_t('View store ↗'),ENT_QUOTES,'UTF-8')?> </a><?php if(!empty($navState['notifications_enabled'])||!empty($navState['support_unread_enabled'])):?><a class="app-notification-bell <?=$topbarHasUnread?'has-unread':''?>" data-admin-notification-bell data-nav-state-url="<?=htmlspecialchars(route_url('admin/nav-state.php'),ENT_QUOTES,'UTF-8')?>" href="<?=htmlspecialchars($topbarNotificationHref,ENT_QUOTES,'UTF-8')?>" aria-label="<?=htmlspecialchars(admin_ui_t('Notifications').($topbarHasUnread?' · '.$topbarUnread.' '.admin_ui_t('unread'):''),ENT_QUOTES,'UTF-8')?>"><span class="app-bell-icon"></span><?php if($topbarHasUnread):?><span class="app-notification-count"><?=min(99,$topbarUnread)?></span><?php endif;?></a><?php endif;?><div class="admin-topbar-user"><span><?=htmlspecialchars($adminName,ENT_QUOTES,'UTF-8')?></span><small><?=htmlspecialchars($role,ENT_QUOTES,'UTF-8')?></small></div></div></header>
<main class="admin-main app-main"><section class="admin-intro app-intro"><p class="admin-eyebrow app-eyebrow"><span></span> <?=htmlspecialchars(admin_ui_t($eyebrow),ENT_QUOTES,'UTF-8')?></p><h1><?=htmlspecialchars(admin_ui_t($heading),ENT_QUOTES,'UTF-8')?><?php if($accent!==''):?> <span><?=htmlspecialchars(admin_ui_t($accent),ENT_QUOTES,'UTF-8')?></span><?php endif;?></h1><?php if($subtitle!==''):?><p class="admin-subtitle app-subtitle"><?=htmlspecialchars(admin_ui_t($subtitle),ENT_QUOTES,'UTF-8')?></p><?php endif;?></section>
<?php }
function admin_page_end(): void { ?>
<footer class="admin-footer app-footer"><span><?=htmlspecialchars(strtoupper(store_runtime_name()),ENT_QUOTES,'UTF-8')?> · <?=htmlspecialchars(admin_ui_t('STORE CONTROL'),ENT_QUOTES,'UTF-8')?></span><a href="<?=htmlspecialchars(route_url('index.php'),ENT_QUOTES,'UTF-8')?>"><?=htmlspecialchars(admin_ui_t('View storefront ↗'),ENT_QUOTES,'UTF-8')?></a></footer></main></div></div>
<?php if((string)($GLOBALS['_admin_layout_current']??'')==='security'):?><script src="<?=htmlspecialchars(asset_url('assets/js/qr-local.js'),ENT_QUOTES,'UTF-8')?>" defer></script><?php endif;?><script src="<?=htmlspecialchars(asset_url('assets/js/admin.js'),ENT_QUOTES,'UTF-8')?>" defer></script><script src="<?=htmlspecialchars(asset_url('assets/js/ui.js'),ENT_QUOTES,'UTF-8')?>" defer></script><script src="<?=htmlspecialchars(asset_url('assets/js/performance.js'),ENT_QUOTES,'UTF-8')?>" defer></script></body></html><?php }
