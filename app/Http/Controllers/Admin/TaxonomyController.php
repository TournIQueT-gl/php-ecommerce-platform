<?php
declare(strict_types=1);
namespace App\Http\Controllers\Admin;

use App\Support\PublicException;
use App\Support\ServiceFactory;

final class TaxonomyController
{
    private const MAP=['brand'=>'store_brands','category'=>'store_categories','family'=>'fragrance_families','note'=>'fragrance_notes'];

    public function handle(): array
    {
        $db=\db();$repo=ServiceFactory::catalogRepository($db);$media=ServiceFactory::media();$message='';$error='';
        if(!$repo->schemaReady())return ['schemaReady'=>false,'message'=>'','error'=>'Commerce schema is not installed yet.','brands'=>[],'categories'=>[],'families'=>[],'notes'=>[]];
        if(($_SERVER['REQUEST_METHOD']??'GET')==='POST'){
            \verify_csrf();$action=(string)($_POST['action']??'');
            try{
                if(in_array($action,['create_taxonomy','update_taxonomy'],true)){
                    $type=(string)($_POST['type']??'');if(!isset(self::MAP[$type]))throw new PublicException('Invalid catalog data type.');
                    $name=trim((string)($_POST['name']??''));$nameAr=null;$baseSlug=$this->slug((string)($_POST['slug']??$name));$status=(string)($_POST['status']??'active');$sort=(int)($_POST['sort_order']??0);$id=(int)($_POST['id']??0);
                    if($name===''||preg_match('/\p{L}/u',$name)!==1)throw new PublicException('Name must include at least one letter.');
                    if($baseSlug==='')throw new PublicException('A URL slug could not be generated. Use English letters or enter a slug manually.');$slug=$this->uniqueSlug($repo,$type,$baseSlug,$action==='update_taxonomy'?$id:0);if(!in_array($status,['active','hidden','archived'],true))$status='active';
                    $imagePath=trim((string)($_POST['image_path']??''));if(!empty($_POST['remove_image']))$imagePath='';$folder=$type==='brand'?'brands':($type==='category'?'categories':($type==='note'?'notes':'catalog'));
                    $uploaded=$media->storeOne($_FILES['image_upload']??null,$folder,0,0,false);if($uploaded!==null)$imagePath=$uploaded;
                    $db->beginTransaction();
                    try{
                        if($action==='create_taxonomy'){
                            $id=$repo->createTaxonomy($type,$name,$nameAr,$slug,$sort,$status,$imagePath);
                            $auditAction='store_'.$type.'_created';$auditDetails=ucfirst($type).' #'.$id;$message=ucfirst($type).' created.';
                        }else{
                            if($id<=0)throw new PublicException('Invalid item.');$existing=$repo->taxonomyById($type,$id);if(!$existing)throw new PublicException('Item not found.');$oldSlug=(string)($existing['slug']??'');
                            $repo->updateTaxonomy($type,$id,$name,$nameAr,$slug,$sort,$status,$imagePath);
                            if($oldSlug!==''&&$oldSlug!==$slug){$filterKey=match($type){'brand'=>'brand','category'=>'category','family'=>'family','note'=>'note'};$repo->recordRedirect(\store_catalog_url([$filterKey=>$oldSlug]),\store_catalog_url([$filterKey=>$slug]));}
                            $auditAction='store_'.$type.'_updated';$auditDetails=ucfirst($type).' #'.$id;$message=ucfirst($type).' updated.';
                        }
                        $db->commit();
                    }catch(\Throwable $e){if($db->inTransaction())$db->rollBack();if($uploaded!==null)$media->delete($uploaded);throw $e;}
                    \admin_audit($auditAction,$auditDetails);
                }elseif($action==='bulk_taxonomy'){
                    $type=(string)($_POST['type']??'');if(!isset(self::MAP[$type]))throw new PublicException('Invalid catalog data type.');
                    $rawIds=$_POST['taxonomy_ids']??[];if(!is_array($rawIds))$rawIds=[];$ids=array_values(array_unique(array_filter(array_map('intval',$rawIds),static fn(int $v):bool=>$v>0)));if(!$ids)throw new PublicException('Select at least one item.');if(count($ids)>100)throw new PublicException('Bulk actions are limited to 100 items at a time.');
                    $bulk=(string)($_POST['bulk_action']??'');if(!in_array($bulk,['archive','delete'],true))throw new PublicException('Choose a bulk action.');
                    $rows=match($type){'brand'=>$repo->brands(true),'category'=>$repo->categories(true),'family'=>$repo->families(true),'note'=>$repo->notes(true)};$byId=[];foreach($rows as $candidate)$byId[(int)$candidate['id']]=$candidate;$targets=[];foreach($ids as $targetId)if(isset($byId[$targetId]))$targets[$targetId]=$byId[$targetId];if(!$targets)throw new PublicException('The selected items no longer exist.');
                    if($bulk==='delete'){
                        if(!\admin_require_password_from_post($error))throw new PublicException($error);$counts=$repo->taxonomyUsageCounts($type);
                        foreach($targets as $targetId=>$target){if((string)($target['status']??'')!=='archived')throw new PublicException('Permanent deletion only works for archived items. Archive the selected items first.');if((int)($counts[$targetId]??0)>0)throw new PublicException((string)($target['name']??'One selected item').' is still used by products or homepage content. Remove those links before deleting.');}
                        $db->beginTransaction();try{foreach(array_keys($targets) as $targetId){if(!$repo->deleteTaxonomy($type,(int)$targetId))throw new PublicException('One selected item could not be deleted.');}$db->commit();}catch(\Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
                        \admin_audit('store_'.$type.'_bulk_deleted',count($targets).' '.$type.' items deleted');$message=count($targets).' '.($type).(count($targets)===1?'':'s').' permanently deleted.';
                    }else{
                        $changed=0;$db->beginTransaction();try{foreach($targets as $targetId=>$target){if((string)($target['status']??'')==='archived')continue;if($repo->archiveTaxonomy($type,(int)$targetId))$changed++;}if($changed===0)throw new PublicException('The selected items are already archived.');$db->commit();}catch(\Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}\admin_audit('store_'.$type.'_bulk_archived',$changed.' '.$type.' items archived');$message=$changed.' '.($type).($changed===1?'':'s').' archived.';
                    }
                }elseif($action==='archive_taxonomy'){$type=(string)($_POST['type']??'');$id=(int)($_POST['id']??0);if(!isset(self::MAP[$type])||$id<=0)throw new PublicException('Invalid item.');$repo->archiveTaxonomy($type,$id);\admin_audit('store_'.$type.'_archived',ucfirst($type).' #'.$id);$message=ucfirst($type).' archived.';}
                elseif($action==='delete_taxonomy'){$type=(string)($_POST['type']??'');$id=(int)($_POST['id']??0);if(!isset(self::MAP[$type])||$id<=0)throw new PublicException('Invalid item.');if(!\admin_require_password_from_post($error))throw new PublicException($error);$rows=match($type){'brand'=>$repo->brands(true),'category'=>$repo->categories(true),'family'=>$repo->families(true),'note'=>$repo->notes(true)};$target=null;foreach($rows as $candidate){if((int)$candidate['id']===$id){$target=$candidate;break;}}if(!$target)throw new PublicException('Item was already removed.');if((string)($target['status']??'')!=='archived')throw new PublicException('Archive this item before deleting it permanently.');$counts=$repo->taxonomyUsageCounts($type);if((int)($counts[$id]??0)>0)throw new PublicException('This item is still used by products or homepage content. Remove those links before permanent deletion.');if(!$repo->deleteTaxonomy($type,$id))throw new PublicException('Item was already removed.');\admin_audit('store_'.$type.'_deleted',ucfirst($type).' #'.$id);$message=ucfirst($type).' permanently deleted.';}
                else throw new PublicException('Unknown catalog-data action.');
            }catch(PublicException $e){$error=$e->getMessage();}catch(\Throwable $e){$error=\web_safe_exception_message($e,'store_taxonomy_action','The catalog-data action could not be completed.');}
            if($error==='')\storefront_cache_clear();
            $return='admin/taxonomy.php';if(!empty($_POST['return_show_archived']))$return.='?show_archived=1';\web_post_redirect($return,'admin_taxonomy',$message,$error);
        }
        $flash=\web_flash_take('admin_taxonomy');$message=$flash['message'];$error=$flash['error'];$showArchived=!empty($_GET['show_archived']);
        return ['schemaReady'=>true,'message'=>$message,'error'=>$error,'showArchived'=>$showArchived,'brands'=>$repo->brands($showArchived),'categories'=>$repo->categories($showArchived),'families'=>$repo->families($showArchived),'notes'=>$repo->notes($showArchived),'usage'=>['brand'=>$repo->taxonomyUsageCounts('brand'),'category'=>$repo->taxonomyUsageCounts('category'),'family'=>$repo->taxonomyUsageCounts('family'),'note'=>$repo->taxonomyUsageCounts('note')]];
    }
    private function uniqueSlug(\App\Repositories\CatalogRepository $repo,string $type,string $base,int $excludeId=0): string
    {
        $slug=$base;$suffix=2;
        while($repo->taxonomySlugExists($type,$slug,$excludeId)){$slug=$base.'-'.$suffix;$suffix++;if($suffix>5000)throw new PublicException('Could not create a unique URL slug.');}
        return $slug;
    }

    private function slug(string $value): string {$value=strtolower(trim($value));$value=preg_replace('/[^a-z0-9]+/','-',$value)??'';return trim($value,'-');}
}
