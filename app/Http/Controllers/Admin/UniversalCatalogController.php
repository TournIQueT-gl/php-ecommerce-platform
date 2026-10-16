<?php
declare(strict_types=1);
namespace App\Http\Controllers\Admin;

use App\Services\UniversalCatalogService;
use App\Services\UniversalSetupService;
use App\Services\DepartmentService;
use App\Support\PublicException;
use App\Support\ServiceFactory;
use PDO;

final class UniversalCatalogController
{
    public function handle(): array
    {
        $db=\db();$repo=ServiceFactory::catalogRepository($db);
        $universal=new UniversalCatalogService($db,$repo,ServiceFactory::inventory($db));
        $setup=new UniversalSetupService($db,$repo);
        $departmentsService=new DepartmentService($db);
        if(!$repo->schemaReady() || !$universal->ready() || !$departmentsService->ready())return ['ready'=>false,'message'=>'','error'=>'Run migrations 046 through 050 against your cloned development database before using SKU media and the Universal Editor.'];
        $message='';$error='';$createdTaxonomy=null;$newUpload='';$galleryUploads=[];$skuImage='';$skuGalleryUploads=[];
        if(($_SERVER['REQUEST_METHOD']??'GET')==='POST'){
            \verify_csrf();$action=(string)($_POST['action']??'');$id=(int)($_POST['product_id']??0);$redirect='admin/universal-catalog.php';$image='';
            try{
                if($action==='save_department'){
                    $departmentId=$departmentsService->save($_POST);$message='Department saved.';$redirect.='?departments=1&department='.$departmentId;
                }elseif($action==='classify_department_products'){
                    $departmentId=(int)($_POST['department_id']??0);
                    $count=$departmentsService->classifyUnassignedProducts($departmentId);
                    $message=$count.' safely matched products classified. Ambiguous products were left unchanged.';
                    $redirect.='?departments=1&department='.$departmentId;
                }elseif($action==='assign_department_category'){
                    $departmentsService->assignCategory((int)($_POST['category_id']??0),(int)($_POST['department_id']??0));
                    $message='Category linked to department.';$redirect.='?departments=1&department='.(int)($_POST['department_id']??0);
                }elseif($action==='bulk_product_status'){
                    // Non-destructive bulk actions only. Never change stock, orders or historical SKUs.
                    $bulk=(string)($_POST['bulk_action']??'');
                    $changed=$universal->bulkProductStatus((array)($_POST['product_ids']??[]),$bulk);
                    $message=$changed.' product'.($changed===1?'':'s').' '.($bulk==='archive'?'archived.':'restored as draft.');
                    $returnQ=substr(trim((string)($_POST['return_q']??'')),0,120);
                    $returnStatus=(string)($_POST['return_status']??'');
                    if(!in_array($returnStatus,['','active','draft','hidden','archived'],true))$returnStatus='';
                    $returnDepartment=max(0,(int)($_POST['return_department']??0));
                    $returnPage=max(1,min(100000,(int)($_POST['return_page']??1)));
                    $redirect.='?'.http_build_query(['q'=>$returnQ,'status'=>$returnStatus,'department'=>$returnDepartment,'page'=>$returnPage]);
                }elseif($action==='assign_legacy_product'){
                    $departmentsService->assignLegacyProduct((int)($_POST['product_id']??0),(int)($_POST['department_id']??0));
                    $message='Existing product assigned to department.';$redirect.='?department='.(int)($_POST['department_id']??0);
                }elseif($action==='quick_taxonomy'){
                    $type=(string)($_POST['taxonomy_type']??'');
                    $taxonomyDepartment=(int)($_POST['department_id']??0);
                    if($type==='category' && $taxonomyDepartment>0)$departmentsService->requireActive($taxonomyDepartment);
                    $newUpload=ServiceFactory::media()->storeOne($_FILES['taxonomy_image']??null,$type==='brand'?'brands':'categories',0,0,false)??'';
                    if($type==='category' && $taxonomyDepartment<1)throw new PublicException('Choose a department to create a category.');
                    $createdTaxonomy=$setup->create($type,(string)($_POST['taxonomy_name']??''),(int)($_POST['parent_id']??0),$newUpload,
                        $type==='category' ? static function(int $newCategoryId)use($departmentsService,$taxonomyDepartment):void {
                            $departmentsService->assignCategory($newCategoryId,$taxonomyDepartment);
                        } : null);
                    $newUpload='';$message=ucfirst($type).' added.';
                    $redirect.='?new=1&department='.$taxonomyDepartment.'&category='.($type==='category'?(int)$createdTaxonomy['id']:(int)($_POST['selected_category']??0));
                }elseif($action==='apply_preset'){
                    $category=(int)($_POST['category_id']??0);
                    $count=$setup->applyPreset($universal,$category,(string)($_POST['preset']??''));
                    $message=$count.' category attributes configured.';$redirect.='?setup='.$category;
                }elseif($action==='category_settings'){
                    $category=(int)($_POST['category_id']??0);$universal->configureCategory($category,(array)($_POST['attributes']??[]),(array)($_POST['axes']??[]),(array)($_POST['required']??[]));
                    $message='Category attributes saved.';$redirect.='?setup='.$category;
                }elseif($action==='create_attribute'){
                    $category=(int)($_POST['category_id']??0);
                    $created=$setup->createAttribute($category,$_POST);
                    $message='Custom attribute '.$created['label'].' created and assigned to this category.';
                    $redirect.='?setup='.$category;
                }elseif($action==='add_options'){
                    $count=$universal->addOptions((int)($_POST['attribute_id']??0),(string)($_POST['option_labels']??''));
                    $message=$count.' new options saved.';$redirect.='?setup='.(int)($_POST['category_id']??0);
                }elseif($action==='save_product'){
                    $existing=$id>0?$repo->findProduct($id):null;
                    if($id>0&&!$existing)throw new PublicException('Product not found.');
                    $image=ServiceFactory::media()->storeOne($_FILES['main_image_upload']??null,'products',700,700,false)??'';
                    $galleryFiles=$_FILES['gallery_uploads']??null;
                    $provided=is_array($galleryFiles['error']??null)?count(array_filter($galleryFiles['error'],static fn($x)=>(int)$x!==UPLOAD_ERR_NO_FILE)):0;
                    if($provided>6)throw new PublicException('Choose no more than six gallery photos.');
                    $galleryUploads=ServiceFactory::media()->storeMany($galleryFiles,'products',700,700,6);
                    $catId=(int)($_POST['category_id']??0);
                    $departmentId=$departmentsService->categoryDepartment($catId);
                    if($departmentId<1)throw new PublicException('Assign this category to a department before creating products.');
                    $departmentsService->requireActive($departmentId);
                    if((int)($_POST['department_id']??0)>0 && (int)$_POST['department_id']!==$departmentId)throw new PublicException('Selected category belongs to another department.');
                    $id=$universal->saveProduct($_POST,$existing,$image,static function(int $savedId,int $categoryId)use($departmentsService,$departmentId):void{
                        $departmentsService->syncProduct($savedId,$categoryId,$departmentId);
                    },$galleryUploads);
                    $image='';$galleryUploads=[];$message='Product saved. Configure its SKU combinations below.';$redirect.='?edit='.$id.'&category='.(int)($_POST['category_id']??0).'&department='.$departmentId;
                }elseif($action==='generate_variants'){
                    if($id<1)throw new PublicException('Save the product first.');
                    $result=$universal->generateVariants($id,(int)($_POST['category_id']??0),(array)($_POST['choices']??[]),$_POST);
                    $message=$result['created'].' variants created; '.$result['skipped'].' existing combinations skipped.';
                    $redirect.='?edit='.$id.'&category='.(int)($_POST['category_id']??0);
                }elseif($action==='update_variant'){
                    if($id<1)throw new PublicException('Product is required.');
                    $skuImage=ServiceFactory::media()->storeOne($_FILES['main_image_upload']??null,'products',700,700,false)??'';
                    $skuFiles=$_FILES['gallery_uploads']??null;
                    $skuCount=is_array($skuFiles['error']??null)?count(array_filter($skuFiles['error'],static fn($x)=>(int)$x!==UPLOAD_ERR_NO_FILE)):0;
                    if($skuCount>6)throw new PublicException('Choose no more than six SKU gallery photos.');
                    $skuGalleryUploads=ServiceFactory::media()->storeMany($skuFiles,'products',700,700,6);
                    $universal->updateVariant($id,(int)($_POST['variant_id']??0),$_POST,$skuImage,$skuGalleryUploads);
                    $skuImage='';$skuGalleryUploads=[];
                    $message='SKU, pricing, stock and photos saved.';$redirect.='?edit='.$id.'&category='.(int)($_POST['category_id']??0).'&sku='.(int)($_POST['variant_id']??0).'#uc-product-inventory';
                }else throw new PublicException('Unknown editor action.');
                if($action==='bulk_product_status'){\admin_audit('universal_catalog_bulk_'.(string)($_POST['bulk_action']??''),$changed.' product statuses updated');}
                else{\admin_audit('universal_catalog_'.$action,'Product #'.$id);}
                \storefront_cache_clear();
            }catch(PublicException $e){$error=$e->getMessage();}
            catch(\Throwable $e){$error=\web_safe_exception_message($e,'universal_catalog','The universal editor could not save your changes.');}
            if($image!=='')ServiceFactory::media()->delete($image);
            foreach($galleryUploads as $pendingPath)ServiceFactory::media()->delete($pendingPath);
            if($skuImage!=='')ServiceFactory::media()->delete($skuImage);
            foreach($skuGalleryUploads as $pendingPath)ServiceFactory::media()->delete($pendingPath);
            if($newUpload!=='')ServiceFactory::media()->delete($newUpload);
            if(in_array($action,['quick_taxonomy','create_attribute'],true) && (string)($_POST['ajax']??'')==='1' && strtolower((string)($_SERVER['HTTP_X_REQUESTED_WITH']??''))==='xmlhttprequest'){
                header('Content-Type: application/json; charset=utf-8');
                header('Cache-Control: no-store');
                http_response_code($error!==''?422:200);
                echo json_encode(['ok'=>$error==='','error'=>$error,'message'=>$message,'item'=>$action==='quick_taxonomy'?$createdTaxonomy:($created??null)],JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);
                exit;
            }
            if($error!=='' && $action==='bulk_product_status'){$redirect='admin/universal-catalog.php?'.http_build_query(['department'=>max(0,(int)($_POST['return_department']??0)),'status'=>in_array((string)($_POST['return_status']??''),['','active','draft','hidden','archived'],true)?(string)($_POST['return_status']??''):'','q'=>substr(trim((string)($_POST['return_q']??'')),0,120),'page'=>max(1,min(100000,(int)($_POST['return_page']??1)))]);}
            elseif($error!=='' && in_array($action,['save_department','classify_department_products','assign_department_category','assign_legacy_product'],true)){
                $redirect='admin/universal-catalog.php'.($action==='assign_legacy_product'?'':'?departments=1');
            }elseif($error!==''){
                // Return to the same editor with an actionable, visible error.
                $redirect='admin/universal-catalog.php'.($action==='quick_taxonomy'?'?new=1':($id>0?'?edit='.$id.'&category='.(int)($_POST['category_id']??0):('?setup='.(int)($_POST['category_id']??0))));
            }
            \web_post_redirect($redirect,'admin_universal_catalog',$message,$error);
        }
        $flash=\web_flash_take('admin_universal_catalog');$message=$flash['message'];$error=$flash['error'];
        $categories=$repo->categories();$brands=$repo->brands();$attributes=$universal->attributes();$options=$universal->options();
        $departments=$departmentsService->all();$categoryDepartmentMap=$departmentsService->categoryMap();
        $setupId=max(0,(int)($_GET['setup']??0));$editId=max(0,(int)($_GET['edit']??0));
        $product=$editId>0?$repo->findProduct($editId):null;
        if($editId>0&&!$product)$error='Product not found.';
        $assignedCategoryIds=$product?$repo->productCategoryIds($editId):[];
        $selectedCategory=(int)($_GET['category']??0);
        $selectedDepartment=(int)($_GET['department']??0);
        if($product && $selectedDepartment<1)$selectedDepartment=$departmentsService->productDepartment($editId);
        if($selectedCategory>0 && $selectedDepartment<1)$selectedDepartment=(int)($categoryDepartmentMap[$selectedCategory]??0);
        if($selectedCategory<1 && $product)$selectedCategory=(int)($assignedCategoryIds[0]??0);
        if($selectedCategory<1 && !isset($_GET['new']) && $selectedDepartment>0){foreach($categories as $cat){if((int)($categoryDepartmentMap[(int)$cat['id']]??0)===$selectedDepartment){$selectedCategory=(int)$cat['id'];break;}}}
        if($selectedCategory<1 && !isset($_GET['new']) && $selectedDepartment===0)$selectedCategory=(int)($categories[0]['id']??0);
        if($product && $selectedDepartment===0 && $selectedCategory>0)$selectedDepartment=(int)($categoryDepartmentMap[$selectedCategory]??0);
        if($product && !in_array($selectedCategory,$assignedCategoryIds,true) && !in_array($selectedCategory,array_map(static fn($c)=>(int)$c['id'],$categories),true))$selectedCategory=(int)($assignedCategoryIds[0]??0);
        $bindings=$selectedCategory>0?$universal->assignments($selectedCategory):[];
        $setupBindings=$setupId>0?$universal->assignments($setupId):[];
        $values=$product?$universal->productValues((int)$product['id']):[];
        $variants=$product?$repo->variants((int)$product['id']):[];
        $listStatus=(string)($_GET['status']??'');if(!in_array($listStatus,['','active','draft','hidden','archived'],true))$listStatus='';
        $list=$repo->productPage((string)($_GET['q']??''),$listStatus,max(1,(int)($_GET['page']??1)),20,$selectedDepartment);
        $departmentView=isset($_GET['departments']);
        $eligibleCounts=[];
        if($departmentView)foreach($departments as $dep)if($dep['status']==='active')$eligibleCounts[(int)$dep['id']]=$departmentsService->eligibleProductCount((int)$dep['id']);
        return compact('message','error','categories','brands','attributes','options','setupId','setupBindings','product','selectedCategory','assignedCategoryIds','bindings','values','variants','list','departments','categoryDepartmentMap','selectedDepartment','departmentView','eligibleCounts','listStatus')+['ready'=>true];
    }
}
