<?php
declare(strict_types=1);
namespace App\Http\Controllers\Admin;

use App\Repositories\HomepageRepository;
use App\Support\PublicException;
use App\Support\ServiceFactory;

final class HomepageController
{
    public function handle(): array
    {
        $db=\db();$repo=ServiceFactory::homepageRepository($db);$service=ServiceFactory::homepage();$media=ServiceFactory::media();$adminId=(int)($_SESSION['user_id']??0);
        if(!$repo->schemaReady())return ['schemaReady'=>false,'sections'=>[],'selectedSection'=>null,'items'=>[],'products'=>[],'brands'=>[],'categories'=>[],'notes'=>[],'mediaFiles'=>[],'publishIssues'=>[],'message'=>'','error'=>'Commerce schema is not installed yet. Run the database migration first.'];

        if(($_SERVER['REQUEST_METHOD']??'GET')==='POST'){
            \verify_csrf();$action=(string)($_POST['action']??'');$message='';$error='';$newUploads=[];$redirectSection=0;
            $orderAjax=in_array($action,['move_section','reorder_sections','move_item','reorder_items'],true)&&(((string)($_POST['_ajax']??''))==='builder-order'||strcasecmp((string)($_SERVER['HTTP_X_REQUESTED_WITH']??''),'PerfumeStore-Builder')===0);
            $addAjax=$action==='add_item'&&((string)($_POST['_ajax']??''))==='builder-content'&&strcasecmp((string)($_SERVER['HTTP_X_REQUESTED_WITH']??''),'PerfumeStore-Builder')===0;
            try{
                if($action==='create_section'){
                    $_POST['sort_order']=$repo->nextSectionSort();$_POST['status']='draft';$data=$service->sectionData($_POST);$data=$this->normalizeSchedule($data);$data['section_key']=$repo->uniqueSectionKey($data['section_key']);$id=$repo->createSection($data,$adminId);$redirectSection=$id;\admin_audit('homepage_section_created','Homepage section #'.$id);$message='Homepage block added.';
                }
                elseif($action==='create_starter'){
                    $pack=(string)($_POST['starter_pack']??'classic');
                    $rows=[];
                    foreach(\App\Storefront\HomepageStarterPacks::plan($pack) as $input)$rows[]=$service->sectionData($input);

                    if(!$repo->createSectionsAtomically($rows,$adminId))throw new PublicException('Starter layout is only available when the homepage has no sections.');
                    \admin_audit('homepage_starter_created','Starter homepage layout: '.$pack);$message='Starter homepage created as drafts. Edit, preview, then publish each block.';
                }
                elseif($action==='update_section'){
                    $id=(int)($_POST['id']??0);$existing=$id>0?$repo->findSection($id):null;if(!$existing)throw new PublicException('Section not found.');
                    // Preserve older Arabic copy when submitting a legacy form without these inputs.
                    $_POST['title_ar']=$_POST['title_ar']??(string)($existing['title_ar']??'');
                    $_POST['subtitle_ar']=$_POST['subtitle_ar']??(string)($existing['subtitle_ar']??'');
                    $_POST['section_key']=(string)$existing['section_key'];$_POST['section_type']=(string)$existing['section_type'];$_POST['sort_order']=(int)$existing['sort_order'];$_POST['settings_json']=(string)($existing['settings_json']??'');$_POST['builder_settings_present']='1';
                    $data=$this->normalizeSchedule($service->sectionData($_POST));$data['section_key']=$repo->uniqueSectionKey($data['section_key'],$id);
                    if(in_array((string)$data['status'],['active','scheduled'],true))$this->assertPublishReady($repo,$id,$data);
                    $repo->updateSection($id,$data,$adminId);$redirectSection=$id;\admin_audit('homepage_section_updated','Homepage section #'.$id);
                    $message=match((string)$data['status']){'active'=>'Homepage block published.','scheduled'=>'Homepage block scheduled.','hidden'=>'Homepage block hidden.','draft'=>'Homepage block saved as draft.',default=>'Homepage block saved.'};
                }
                elseif($action==='move_section'){$id=(int)($_POST['id']??0);$repo->moveSection($id,(string)($_POST['direction']??'down'));$redirectSection=$id;$message='Homepage order updated.';}
                elseif($action==='reorder_sections'){$ids=preg_split('/\s*,\s*/',(string)($_POST['order']??''),-1,PREG_SPLIT_NO_EMPTY)?:[];$repo->reorderSections($ids);$redirectSection=(int)($_POST['selected_id']??0);$message='Homepage order updated.';}
                elseif($action==='duplicate_section'){$id=(int)($_POST['id']??0);$newId=$repo->duplicateSection($id,$adminId);if(!$newId)throw new PublicException('Section could not be duplicated.');$redirectSection=$newId;\admin_audit('homepage_section_duplicated','Homepage section #'.$id.' duplicated as #'.$newId);$message='Block duplicated as a draft.';}
                elseif($action==='toggle_section'){
                    $id=(int)($_POST['id']??0);$current=$repo->findSection($id);if(!$current)throw new PublicException('Section not found.');$currentStatus=(string)($current['status']??'draft');
                    if($currentStatus==='active'){$repo->setSectionStatus($id,'hidden');$message='Homepage block hidden.';}
                    elseif($currentStatus==='archived'){$repo->setSectionStatus($id,'draft');$message='Homepage block restored as a draft.';}
                    else{
                        $end=(string)($current['ends_at']??'');if($end!==''&&strtotime($end.' UTC')!==false&&strtotime($end.' UTC')<time())throw new PublicException('This section has already passed its end time. Edit the schedule before publishing it again.');
                        $this->assertPublishReady($repo,$id,$current);$repo->publishSectionNow($id);$message='Homepage block published now.';
                    }
                    $redirectSection=$id;
                }
                elseif($action==='delete_section'){if(!\admin_require_password_from_post($error))throw new PublicException($error);$id=(int)($_POST['id']??0);if($id<=0||!$repo->deleteSection($id))throw new PublicException('Section could not be deleted.');\admin_audit('homepage_section_deleted','Homepage section #'.$id);$message='Homepage section deleted.';}
                elseif($action==='add_item' || $action==='update_item'){
                    $sid=(int)($_POST['section_id']??0);$sectionRow=$sid>0?$repo->findSection($sid):null;if(!$sectionRow)throw new PublicException('Section not found.');$redirectSection=$sid;
                    $sectionType=(string)($sectionRow['section_type']??'custom');$campaign=in_array($sectionType,['hero','editorial_banner','gift_banner'],true);
                    $existing=null;$itemId=(int)($_POST['id']??0);
                    if($action==='update_item'){$existing=$repo->findItem($itemId);if(!$existing||!$repo->itemBelongsToSection($itemId,$sid))throw new PublicException('Content item does not belong to this section.');$_POST['title_ar']=$_POST['title_ar']??(string)($existing['title_ar']??'');$_POST['subtitle_ar']=$_POST['subtitle_ar']??(string)($existing['subtitle_ar']??'');}
                    elseif($campaign&&$repo->items($sid)){throw new PublicException('This banner uses one content block. Edit the existing content instead.');}

                    $this->prepareAndValidateReference($repo,$sectionType,$sid,$itemId>0?$itemId:null,$_POST);
                    if($sectionType==='notes_grid'){
                        $noteId=(int)($_POST['item_note_id']??0);$note=$noteId>0?$repo->findNote($noteId):null;
                        if(!$note)throw new PublicException('Choose a valid fragrance note for this section.');
                        $_POST['title']=(string)$note['name'];$_POST['image_path']=(string)($note['image_path']??'');$_POST['cta_url']=\store_catalog_url(['note'=>(string)$note['slug']]);
                    }
                    $desktop=$sectionType==='notes_grid'?trim((string)($_POST['image_path']??'')):trim((string)($existing['image_path']??$_POST['image_path']??''));$mobile=trim((string)($existing['mobile_image_path']??$_POST['mobile_image_path']??''));
                    if(!empty($_POST['remove_image']))$desktop='';if(!empty($_POST['remove_mobile_image']))$mobile='';
                    if(!empty($_POST['media_image_path']))$desktop=$media->validateExisting((string)$_POST['media_image_path'],$campaign?1200:600,$campaign?600:500);if(!empty($_POST['media_mobile_image_path']))$mobile=$media->validateExisting((string)$_POST['media_mobile_image_path'],$campaign?600:600,$campaign?800:500);
                    $up=$media->storeOne($_FILES['image_upload']??null,'homepage',$campaign?1200:600,$campaign?600:500,false);if($up!==null){$desktop=$up;$newUploads[]=$up;}
                    $upm=$media->storeOne($_FILES['mobile_image_upload']??null,'homepage',$campaign?600:600,$campaign?800:500,false);if($upm!==null){$mobile=$upm;$newUploads[]=$upm;}
                    $_POST['image_path']=$desktop;$_POST['mobile_image_path']=$mobile;$_POST['settings_json']=(string)($existing['settings_json']??'');$_POST['builder_item_settings_present']='1';$_POST['section_type']=$sectionType;if($action==='add_item')$_POST['sort_order']=$repo->nextItemSort($sid);$data=$service->itemData($_POST);$this->assertItemContent($sectionType,$data);
                    $db->beginTransaction();
                    try{
                        if($action==='add_item'){$id=$repo->addItem($sid,$data);$auditAction='homepage_item_created';$auditDetail='Homepage item #'.$id.' in section #'.$sid;$message='Content added.';}
                        else{$repo->updateItem($itemId,$data);$auditAction='homepage_item_updated';$auditDetail='Homepage item #'.$itemId;$message='Content updated.';}
                        if(in_array((string)($sectionRow['status']??'draft'),['active','scheduled'],true))$this->assertPublishReady($repo,$sid,$sectionRow);
                        \admin_audit($auditAction,$auditDetail);$db->commit();$newUploads=[];
                    }catch(\Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
                }
                elseif($action==='move_item'){$sid=(int)($_POST['section_id']??0);$id=(int)($_POST['id']??0);$repo->moveItem($sid,$id,(string)($_POST['direction']??'down'));$redirectSection=$sid;$message='Content order updated.';}
                elseif($action==='reorder_items'){$sid=(int)($_POST['section_id']??0);$ids=preg_split('/\s*,\s*/',(string)($_POST['order']??''),-1,PREG_SPLIT_NO_EMPTY)?:[];$repo->reorderItems($sid,$ids);$redirectSection=$sid;$message='Content order updated.';}
                elseif($action==='delete_item'){
                    if(!\admin_require_password_from_post($error))throw new PublicException($error);$id=(int)($_POST['id']??0);$sid=(int)($_POST['section_id']??0);$sectionRow=$sid>0?$repo->findSection($sid):null;
                    if($id<=0||$sid<=0||!$sectionRow||!$repo->itemBelongsToSection($id,$sid))throw new PublicException('Content item could not be deleted.');$redirectSection=$sid;
                    $db->beginTransaction();
                    try{
                        if(!$repo->deleteItem($id))throw new PublicException('Content item could not be deleted.');
                        if(in_array((string)($sectionRow['status']??'draft'),['active','scheduled'],true))$this->assertPublishReady($repo,$sid,$sectionRow);
                        \admin_audit('homepage_item_deleted','Homepage item #'.$id);$db->commit();$message='Homepage item deleted.';
                    }catch(\Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
                }
                else throw new PublicException('Unknown homepage action.');
            }catch(PublicException $e){foreach($newUploads as $path)$media->delete((string)$path);$error=$e->getMessage();}catch(\Throwable $e){foreach($newUploads as $path)$media->delete((string)$path);$error=\web_safe_exception_message($e,'homepage_cms_action','The homepage action could not be completed.');}
            if($error==='')\storefront_cache_clear();
            if($orderAjax || $addAjax){
                header('Content-Type: application/json; charset=utf-8');header('Cache-Control: no-store, private');http_response_code($error===''?200:422);
                echo json_encode(['ok'=>$error==='','message'=>$error!==''?$error:$message,'section_id'=>$redirectSection,'refresh_url'=>\route_url('admin/homepage.php').($redirectSection>0?'?section='.$redirectSection:'')],JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);exit;
            }
            if($redirectSection<=0)$redirectSection=(int)($_POST['section_id']??$_POST['id']??0);\web_post_redirect('admin/homepage.php'.($redirectSection>0?'?section='.$redirectSection:''),'admin_homepage',$message,$error);
        }

        $flash=\web_flash_take('admin_homepage');$message=$flash['message'];$error=$flash['error'];$sections=$repo->sections();$selectedId=(int)($_GET['section']??0);$selectedSection=$selectedId>0?$repo->findSection($selectedId):null;if(!$selectedSection)$selectedSection=$sections[0]??null;$items=$selectedSection?$repo->items((int)$selectedSection['id']):[];
        $publishIssues=$selectedSection?$this->publishIssues($repo,(int)$selectedSection['id'],$selectedSection):[];
        $mediaFiles=[];try{$mediaFiles=$media->list(300);}catch(\Throwable){}
        return ['schemaReady'=>true,'sections'=>$sections,'selectedSection'=>$selectedSection,'items'=>$items,'products'=>[],'brands'=>$repo->brands(),'categories'=>$repo->categories(),'notes'=>$repo->notes(),'mediaFiles'=>$mediaFiles,'publishIssues'=>$publishIssues,'message'=>$message,'error'=>$error];
    }

    private function normalizeSchedule(array $data): array
    {
        $start=(string)($data['starts_at']??'');$end=(string)($data['ends_at']??'');$startTs=$start!==''?strtotime($start.' UTC'):false;$endTs=$end!==''?strtotime($end.' UTC'):false;
        if($start!==''&&$startTs===false)throw new PublicException('Choose a valid homepage start time.');
        if($end!==''&&$endTs===false)throw new PublicException('Choose a valid homepage end time.');
        if($startTs!==false&&$endTs!==false&&$endTs<=$startTs)throw new PublicException('Homepage end time must be later than the start time.');
        $status=(string)($data['status']??'draft');
        if($status==='scheduled'&&$startTs===false)throw new PublicException('Scheduled sections need a start date and time.');
        if(in_array($status,['active','scheduled'],true)&&$endTs!==false&&$endTs<=time())throw new PublicException('The section end time is already in the past. Choose a future end time or save it as a draft/hidden section.');
        if($status==='active'&&$startTs!==false&&$startTs>time())$data['status']='scheduled';
        elseif($status==='scheduled'&&$startTs!==false&&$startTs<=time())$data['status']='active';
        return $data;
    }

    private function prepareAndValidateReference(HomepageRepository $repo,string $sectionType,int $sectionId,?int $excludeItemId,array &$post): void
    {
        $rawProduct=(int)($post['product_id']??0);$rawBrand=(int)($post['brand_id']??0);$rawCategory=(int)($post['category_id']??0);
        $post['product_id']='';$post['brand_id']='';$post['category_id']='';
        $kind=null;$field=null;$label=null;$value=0;
        if($sectionType==='product_carousel'){$kind='product';$field='product_id';$label='product';$value=$rawProduct;}
        elseif($sectionType==='brand_grid'){$kind='brand';$field='brand_id';$label='brand';$value=$rawBrand;}
        elseif($sectionType==='category_grid'){$kind='category';$field='category_id';$label='category';$value=$rawCategory;}
        if($kind===null)return;
        if($value<=0||!$repo->referenceExists($kind,$value,false))throw new PublicException('Choose a valid '.$label.' for this section.');
        if($repo->duplicateReferenceExists($sectionId,$field,$value,$excludeItemId))throw new PublicException('That '.$label.' is already in this section.');
        $post[$field]=(string)$value;
    }

    private function assertItemContent(string $sectionType,array $data): void
    {
        if(in_array($sectionType,['product_carousel','brand_grid','category_grid'],true))return;
        if(in_array($sectionType,['scent_grid','notes_grid','wardrobe_grid','custom'],true)){
            if(trim((string)($data['title']??''))==='')throw new PublicException('Add a title before saving this card.');
            return;
        }
        if(in_array($sectionType,['hero','editorial_banner','gift_banner'],true)){
            foreach(['title','subtitle','eyebrow','image_path','mobile_image_path','cta_label'] as $field)if(trim((string)($data[$field]??''))!=='')return;
            throw new PublicException('Add a headline, supporting text, image or button before saving this banner.');
        }
    }

    private function assertPublishReady(HomepageRepository $repo,int $sectionId,array $section): void
    {
        $issues=$this->publishIssues($repo,$sectionId,$section);if($issues)throw new PublicException($issues[0]);
    }

    private function publishIssues(HomepageRepository $repo,int $sectionId,array $section): array
    {
        $type=(string)($section['section_type']??'custom');$settings=json_decode((string)($section['settings_json']??''),true);if(!is_array($settings))$settings=[];
        if(in_array($type,['department_grid','audience_grid'],true))return [];
        if($type==='video')return trim((string)($settings['video_url']??''))!==''?[]:['Enter an MP4/WebM video URL before publishing.'];
        if($type==='product_carousel'&&(string)($settings['product_source']??'manual')!=='manual'){
            $source=(string)$settings['product_source'];
            if($repo->automaticProductSourceCount($source)>0)return [];
            return ['No '.match($source){'featured'=>'featured','bestselling'=>'best-selling','new'=>'new-arrival','sale'=>'sale',default=>'matching'}.' products are currently available for this automatic section.'];
        }
        $count=$repo->renderableItemCount($sectionId,$type);
        if($count>0)return [];
        return [match($type){
            'hero'=>'Add visible hero content before publishing this section.',
            'editorial_banner','gift_banner'=>'Add visible banner content before publishing this section.',
            'product_carousel'=>'Choose at least one published product before publishing this section.',
            'brand_grid'=>'Choose at least one active brand before publishing this section.',
            'category_grid'=>'Choose at least one active category before publishing this section.',
            default=>'Add at least one visible card before publishing this section.',
        }];
    }
}
