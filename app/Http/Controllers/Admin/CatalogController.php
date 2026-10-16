<?php
declare(strict_types=1);
namespace App\Http\Controllers\Admin;

use App\Support\PublicException;
use App\Support\ServiceFactory;
use App\Services\DepartmentService;

final class CatalogController
{
    public function handle(): array
    {
        $db=\db();$repo=ServiceFactory::catalogRepository($db);$service=ServiceFactory::catalog($db);$media=ServiceFactory::media();$inventory=ServiceFactory::inventory($db);
        if(!$repo->schemaReady())return ['schemaReady'=>false,'products'=>[],'brands'=>[],'categories'=>[],'families'=>[],'notes'=>[],'message'=>'','error'=>'Commerce schema is not installed yet. Run the database migration first.','editProduct'=>null,'variants'=>[],'productCategoryIds'=>[],'productNotes'=>[],'storeCurrency'=>(string)(\store_runtime_settings()['currency']??'EGP')];

        if(($_SERVER['REQUEST_METHOD']??'GET')==='POST'){
            \verify_csrf();$action=(string)($_POST['action']??'');$message='';$error='';$id=0;$newUploads=[];
            try{
                if($action==='create_product' || $action==='update_product'){
                    $id=(int)($_POST['id']??0);$existing=$id>0?$repo->findProduct($id):null;
                    if($action==='update_product' && !$existing)throw new PublicException('Product not found.');

                    $mainPath=trim((string)($existing['main_image_path']??$_POST['main_image_path']??''));
                    if(!empty($_POST['remove_main_image']))$mainPath='';
                    $uploadedMain=$media->storeOne($_FILES['main_image_upload']??null,'products',700,700);
                    if($uploadedMain!==null){$mainPath=$uploadedMain;$newUploads[]=$uploadedMain;}

                    $gallery=[];$rawGallery=(string)($existing['gallery_json']??'');
                    if($rawGallery!==''){$decoded=json_decode($rawGallery,true);if(is_array($decoded))$gallery=array_values(array_filter(array_map('strval',$decoded)));}
                    $removeGallery=$_POST['remove_gallery']??[];if(!is_array($removeGallery))$removeGallery=[];
                    $gallery=array_values(array_filter($gallery,static fn($p)=>!in_array($p,$removeGallery,true)));
                    $galleryOrder=json_decode((string)($_POST['gallery_order']??''),true);
                    if(is_array($galleryOrder)){
                        $known=array_fill_keys($gallery,true);$ordered=[];
                        foreach($galleryOrder as $path){$path=(string)$path;if(isset($known[$path])&&!in_array($path,$ordered,true))$ordered[]=$path;}
                        foreach($gallery as $path)if(!in_array($path,$ordered,true))$ordered[]=$path;
                        $gallery=$ordered;
                    }
                    $promote=trim((string)($_POST['promote_gallery_path']??''));
                    if($uploadedMain===null&&$promote!==''&&in_array($promote,$gallery,true)){
                        $oldMain=$mainPath;$mainPath=$promote;$gallery=array_values(array_filter($gallery,static fn($path)=>$path!==$promote));
                        if($oldMain!==''&&$oldMain!==$mainPath)$gallery[]=$oldMain;
                    }
                    // The storefront displays at most six product photos. Keep storage and
                    // browser payload aligned with that UI: one main photo + up to five
                    // gallery photos, or six gallery photos when no main image is present.
                    $maxGallery=$mainPath!==''?5:6;
                    $gallery=array_slice(array_values(array_unique($gallery)),0,$maxGallery);
                    $remainingGallery=max(0,$maxGallery-count($gallery));
                    $galleryFileErrors=$_FILES['gallery_uploads']['error']??[];
                    $hasGalleryUpload=is_array($galleryFileErrors)&&count(array_filter($galleryFileErrors,static fn($e)=>((int)$e)!==UPLOAD_ERR_NO_FILE))>0;
                    if($remainingGallery<=0&&$hasGalleryUpload)throw new PublicException('This product already has the maximum of six storefront photos. Remove a gallery image before adding another.');
                    $galleryUploads=$remainingGallery>0?$media->storeMany($_FILES['gallery_uploads']??null,'products',700,700,$remainingGallery):[];$newUploads=array_merge($newUploads,$galleryUploads);
                    $gallery=array_slice(array_values(array_unique(array_merge($gallery,$galleryUploads))),0,$maxGallery);

                    $_POST['main_image_path']=$mainPath;$_POST['gallery_json']=$gallery;
                    $data=$service->productData($_POST);
                    if($action==='update_product'&&$existing&&!isset($_POST['slug']))$data['slug']=(string)$existing['slug'];
                    $data['slug']=$repo->uniqueProductSlug($data['slug'],$action==='update_product'&&$id>0?$id:null);
                    $categoryIds=$_POST['category_ids']??[];if(!is_array($categoryIds))$categoryIds=[];
                    $layers=[];foreach(['top','heart','base','other'] as $layer){$value=$_POST['notes_'.$layer]??[];$layers[$layer]=is_array($value)?$value:[];}

                    $db->beginTransaction();
                    try{
                        if($action==='create_product'){
                            $id=$repo->createProductUnique($data);
                            $initialPrice=trim((string)($_POST['initial_price']??''));
                            if(!preg_match('/^\d+(?:\.\d{1,2})?$/',$initialPrice)||(float)$initialPrice<=0)throw new PublicException('Enter a valid price for the first size.');
                            if($initialPrice!=='' && is_numeric($initialPrice) && (float)$initialPrice>0){
                                $label=trim((string)($_POST['initial_label']??''));if($label===''){$size=trim((string)($_POST['initial_size_ml']??''));$label=$size!==''?$size.' ML':'Standard';}
                                $variantInput=['label'=>$label,'size_ml'=>$_POST['initial_size_ml']??null,'sku'=>$_POST['initial_sku']??'','barcode'=>$_POST['initial_barcode']??'','cost_price'=>$_POST['initial_cost_price']??null,'price'=>$initialPrice,'sale_price'=>$_POST['initial_sale_price']??null,'currency'=>(string)(store_runtime_settings()['currency']??'EGP'),'stock_qty'=>$_POST['initial_stock_qty']??0,'low_stock_threshold'=>3,'sort_order'=>0,'status'=>'active'];
                                $variantData=$service->variantData($variantInput,$repo->nextVariantSku($id,$label));$initialStock=(int)$variantData['stock_qty'];$variantData['stock_qty']=0;
                                $variantId=$repo->addVariant($id,$variantData);
                                if($initialStock>0)$inventory->setStock($variantId,$initialStock,'catalog_initial_stock','Initial stock entered while creating product',(int)($_SESSION['user_id']??0),'product',(string)$id);
                            }
                            // All additional sizes are created in the SAME product transaction.
                            $extraSizes=is_array($_POST['extra_size_ml']??null)?$_POST['extra_size_ml']:[];
                            $extraLabels=is_array($_POST['extra_label']??null)?$_POST['extra_label']:[];
                            $extraPrices=is_array($_POST['extra_price']??null)?$_POST['extra_price']:[];
                            $extraStocks=is_array($_POST['extra_stock']??null)?$_POST['extra_stock']:[];
                            $extraSales=is_array($_POST['extra_sale_price']??null)?$_POST['extra_sale_price']:[];
                            $extraSkus=is_array($_POST['extra_sku']??null)?$_POST['extra_sku']:[];
                            if(count($extraPrices)>19)throw new PublicException('You can create at most 20 sizes in one product.');
                            foreach($extraPrices as $i=>$priceRaw){
                                if(!is_scalar($priceRaw)||!preg_match('/^\d+(?:\.\d{1,2})?$/',trim((string)$priceRaw))||(float)$priceRaw<=0)throw new PublicException('Each added size needs a valid positive price.');
                                $sizeRaw=trim((string)($extraSizes[$i]??''));
                                if($sizeRaw!==''&&(!is_numeric($sizeRaw)||(float)$sizeRaw<0))throw new PublicException('Enter a valid size in ML.');
                                $label=mb_substr(trim((string)($extraLabels[$i]??'')),0,100);
                                if($label==='')$label=$sizeRaw!==''?$sizeRaw.' ML':'Standard';
                                $stockRaw=trim((string)($extraStocks[$i]??'0'));
                                if(!preg_match('/^\d+$/',$stockRaw))throw new PublicException('Stock must be a nonnegative whole number.');
                                $saleRaw=trim((string)($extraSales[$i]??''));
                                if($saleRaw!==''&&(!preg_match('/^\d+(?:\.\d{1,2})?$/',$saleRaw)||(float)$saleRaw<=0||(float)$saleRaw>=(float)$priceRaw))throw new PublicException('Sale price must be positive and lower than regular price.');
                                $skuRaw=trim((string)($extraSkus[$i]??''));
                                $variantInput=['label'=>$label,'size_ml'=>$sizeRaw,'sku'=>$skuRaw,'price'=>$priceRaw,'sale_price'=>$saleRaw,'currency'=>(string)(store_runtime_settings()['currency']??'EGP'),'stock_qty'=>$stockRaw,'low_stock_threshold'=>3,'sort_order'=>$i+1,'status'=>'active'];
                                $variantData=$service->variantData($variantInput,$repo->nextVariantSku($id,$label));
                                $startingStock=(int)$variantData['stock_qty'];$variantData['stock_qty']=0;
                                $variantId=$repo->addVariant($id,$variantData);
                                if($startingStock>0)$inventory->setStock($variantId,$startingStock,'catalog_initial_stock','Initial stock entered during product creation',(int)($_SESSION['user_id']??0),'product',(string)$id);
                            }
                        }else{
                            $oldSlug=(string)($existing['slug']??'');$repo->updateProduct($id,$data);
                            if($oldSlug!==''&&$oldSlug!==(string)$data['slug'])$repo->recordRedirect(\route_url('product.php').'?slug='.rawurlencode($oldSlug),\route_url('product.php').'?slug='.rawurlencode((string)$data['slug']));
                        }
                        $repo->syncCategories($id,$categoryIds);(new DepartmentService($db))->reconcileLegacyEditor($id,$categoryIds);$repo->syncNotes($id,$layers);$db->commit();
                    }catch(\Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
                    // The product now owns these files. Post-commit audit/cache failures
                    // must never make the generic error cleanup delete live product media.
                    $newUploads=[];

                    if($action==='create_product'){\admin_audit('store_product_created','Product #'.$id);$message='Product created. You can add more sizes below if needed.';}
                    else{\admin_audit('store_product_updated','Product #'.$id);$message='Product updated.';}
                }
                elseif($action==='bulk_products'){
                    $rawIds=$_POST['product_ids']??[];if(!is_array($rawIds))$rawIds=[];$ids=array_values(array_unique(array_filter(array_map('intval',$rawIds),static fn(int $v):bool=>$v>0)));if(!$ids)throw new PublicException('Select at least one product.');if(count($ids)>100)throw new PublicException('Bulk actions are limited to 100 products at a time.');
                    $bulk=(string)($_POST['bulk_action']??'');if(!in_array($bulk,['archive','restore','delete'],true))throw new PublicException('Choose a bulk action.');$targets=[];foreach($ids as $productId){$product=$repo->findProduct($productId);if($product)$targets[$productId]=$product;}if(!$targets)throw new PublicException('The selected products no longer exist.');
                    if($bulk==='delete'){
                        if(!\admin_require_password_from_post($error))throw new PublicException($error);
                        foreach($targets as $product){if((string)($product['status']??'')!=='archived')throw new PublicException('Permanent deletion only works for archived products. Archive the selected products first.');}
                        $db->beginTransaction();try{foreach(array_keys($targets) as $productId){if(!$repo->deleteProduct((int)$productId))throw new PublicException('One selected product could not be deleted.');} $db->commit();}catch(\Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
                        \admin_audit('store_products_bulk_deleted',count($targets).' products permanently deleted');$message=count($targets).' archived product'.(count($targets)===1?'':'s').' permanently deleted.';
                    }elseif($bulk==='archive'){
                        $changed=0;$db->beginTransaction();try{foreach($targets as $productId=>$product){if((string)($product['status']??'')==='archived')continue;if($repo->archiveProduct((int)$productId))$changed++;}if($changed===0)throw new PublicException('The selected products are already archived.');$db->commit();}catch(\Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
                        \admin_audit('store_products_bulk_archived',$changed.' products archived');$message=$changed.' product'.($changed===1?'':'s').' archived.';
                    }else{
                        $changed=0;$db->beginTransaction();try{foreach($targets as $productId=>$product){if((string)($product['status']??'')!=='archived')continue;if($repo->restoreProduct((int)$productId))$changed++;}if($changed===0)throw new PublicException('Select archived products to restore.');$db->commit();}catch(\Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
                        \admin_audit('store_products_bulk_restored',$changed.' products restored');$message=$changed.' product'.($changed===1?'':'s').' restored as draft.';
                    }
                }
                elseif($action==='archive_product'){$id=(int)($_POST['id']??0);if($id<=0||!$repo->archiveProduct($id))throw new PublicException('Product could not be archived.');\admin_audit('store_product_archived','Product #'.$id);$message='Product archived.';}
                elseif($action==='restore_product'){$id=(int)($_POST['id']??0);if($id<=0||!$repo->restoreProduct($id))throw new PublicException('Product could not be restored.');\admin_audit('store_product_restored','Product #'.$id);$message='Product restored as draft.';}
                elseif($action==='delete_product'){if(!\admin_require_password_from_post($error))throw new PublicException($error);$id=(int)($_POST['id']??0);$product=$id>0?$repo->findProduct($id):null;if(!$product)throw new PublicException('Product not found.');if((string)$product['status']!=='archived')throw new PublicException('Archive the product before deleting it permanently.');if(!$repo->deleteProduct($id))throw new PublicException('Product could not be deleted.');\admin_audit('store_product_deleted','Product #'.$id);$message='Empty archived product permanently deleted; no order, review or stock history was removed.';}
                elseif($action==='add_variant'){
                    $productId=(int)($_POST['product_id']??0);if($productId<=0||!$repo->findProduct($productId))throw new PublicException('Product not found.');
                    $fallback=$repo->nextVariantSku($productId,(string)($_POST['label']??''));$data=$service->variantData($_POST,$fallback);$desiredStock=(int)$data['stock_qty'];$data['stock_qty']=0;
                    $db->beginTransaction();try{$id=$repo->addVariant($productId,$data);if($desiredStock>0)$inventory->setStock($id,$desiredStock,'catalog_initial_stock','Initial stock entered while adding size',(int)($_SESSION['user_id']??0),'product',(string)$productId);$db->commit();}catch(\Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
                    \admin_audit('store_variant_created','Variant #'.$id.' for product #'.$productId);$message='Variant added.';
                }
                elseif($action==='update_variant'){
                    $id=(int)($_POST['variant_id']??0);$existingVariant=$id>0?$repo->findVariant($id):null;if(!$existingVariant)throw new PublicException('Variant not found.');
                    $fallback=(string)($existingVariant['sku']??$repo->nextVariantSku((int)($existingVariant['product_id']??0),(string)($_POST['label']??'')));$data=$service->variantData($_POST,$fallback);$desiredStock=(int)$data['stock_qty'];$data['stock_qty']=(int)$existingVariant['stock_qty'];
                    $db->beginTransaction();try{$repo->updateVariant($id,$data);$inventory->setStock($id,$desiredStock,'catalog_stock_edit','Stock changed from product editor',(int)($_SESSION['user_id']??0),'product',(string)$existingVariant['product_id']);$db->commit();}catch(\Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
                    \admin_audit('store_variant_updated','Variant #'.$id);$message='Variant updated.';
                }
                elseif($action==='archive_variant'){$id=(int)($_POST['variant_id']??0);if($id<=0||!$repo->archiveVariant($id))throw new PublicException('Variant could not be archived.');\admin_audit('store_variant_archived','Variant #'.$id);$message='Variant archived.';}
                elseif($action==='restore_variant'){$id=(int)($_POST['variant_id']??0);if($id<=0||!$repo->restoreVariant($id))throw new PublicException('Variant could not be restored.');\admin_audit('store_variant_restored','Variant #'.$id);$message='Variant restored.';}
                elseif($action==='delete_variant'){if(!\admin_require_password_from_post($error))throw new PublicException($error);$id=(int)($_POST['variant_id']??0);$variant=$id>0?$repo->findVariant($id):null;if(!$variant)throw new PublicException('Variant not found.');if((string)$variant['status']!=='archived')throw new PublicException('Archive the size before deleting it permanently.');if(!$repo->deleteVariant($id))throw new PublicException('Variant could not be deleted.');\admin_audit('store_variant_deleted','Variant #'.$id);$message='Empty archived variant permanently deleted; no order or stock history was removed.';}
                elseif($action==='create_brand'){[$name,$nameAr,$slug]=$service->brandData($_POST);if($slug==='')throw new PublicException('Brand slug is invalid.');$slug=$repo->uniqueBrandSlug($slug);$id=$repo->createBrand($name,$nameAr,$slug);\admin_audit('store_brand_created','Brand #'.$id);$message='Brand created.';}
                else throw new PublicException('Unknown catalog action.');
            }catch(PublicException $e){foreach($newUploads as $path)$media->delete((string)$path);$error=$e->getMessage();}
            catch(\Throwable $e){foreach($newUploads as $path)$media->delete((string)$path);$error=\web_safe_exception_message($e,'store_catalog_action','The catalog action could not be completed.');}
            if($error==='')\storefront_cache_clear();
            $redirect='admin/catalog.php';$pid=(int)($_POST['product_id']??0);if($pid<=0)$pid=(int)($_POST['id']??0);if($pid<=0)$pid=(int)$id;if(in_array($action,['create_product','update_product','add_variant','update_variant','archive_variant','restore_variant','delete_variant'],true)&&$pid>0){$redirect.='?edit='.$pid;}else{$listParams=[];$returnQ=substr(trim((string)($_POST['return_q']??'')),0,120);$returnStatus=trim((string)($_POST['return_status']??''));$returnPage=max(1,(int)($_POST['return_page']??1));if($returnQ!=='')$listParams['q']=$returnQ;if(in_array($returnStatus,['active','draft','hidden','archived'],true))$listParams['status']=$returnStatus;if($returnPage>1)$listParams['page']=$returnPage;if($listParams)$redirect.='?'.http_build_query($listParams);}\web_post_redirect($redirect,'admin_catalog',$message,$error);
        }

        $flash=\web_flash_take('admin_catalog');$message=$flash['message'];$error=$flash['error'];
        $catalogQuery=substr(trim((string)($_GET['q']??'')),0,120);$catalogStatus=trim((string)($_GET['status']??''));$catalogPage=max(1,(int)($_GET['page']??1));$catalogPageData=$repo->productPage($catalogQuery,$catalogStatus,$catalogPage,30);$products=$catalogPageData['rows'];$brands=$repo->brands();$categories=$repo->categories();$families=$repo->families();$notes=$repo->notes();
        $editId=(int)($_GET['edit']??0);$editProduct=$editId>0?$repo->findProduct($editId):null;$variants=$editProduct?$repo->variants((int)$editProduct['id']):[];
        $productCategoryIds=$editProduct?$repo->productCategoryIds((int)$editProduct['id']):[];$productNotes=$editProduct?$repo->productNotes((int)$editProduct['id']):[];
        $mainImageQuality=null;
        if($editProduct&&!empty($editProduct['main_image_path'])){
            $relative=ltrim((string)$editProduct['main_image_path'],'/');$absolute=dirname(__DIR__,4).DIRECTORY_SEPARATOR.str_replace('/',DIRECTORY_SEPARATOR,$relative);
            if(is_file($absolute)){$size=@getimagesize($absolute);if(is_array($size)){$mainImageQuality=['width'=>(int)$size[0],'height'=>(int)$size[1],'low'=>(int)$size[0]<700||(int)$size[1]<700];}}
        }
        $storeCurrency=(string)(\store_runtime_settings()['currency']??'EGP');
        return compact('products','brands','categories','families','notes','message','error','editProduct','variants','productCategoryIds','productNotes','mainImageQuality','catalogQuery','catalogStatus','catalogPageData','storeCurrency')+['schemaReady'=>true];
    }
}
