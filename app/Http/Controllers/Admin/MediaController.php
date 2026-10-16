<?php
declare(strict_types=1);
namespace App\Http\Controllers\Admin;

use App\Support\PublicException;
use App\Support\ServiceFactory;

final class MediaController
{
    public function handle(): array
    {
        $db=\db();$media=ServiceFactory::media();$message='';$error='';
        if(($_SERVER['REQUEST_METHOD']??'GET')==='POST'){
            \verify_csrf();$action=(string)($_POST['action']??'');
            try{
                if($action==='upload'){
                    $folder=trim((string)($_POST['folder']??'library'));if(!in_array($folder,['library','products','homepage','brands','categories','notes'],true))$folder='library';
                    $paths=$media->storeMany($_FILES['media_files']??null,$folder);
                    if(!$paths)throw new PublicException('Choose at least one image to upload.');
                    \admin_audit('media_uploaded',count($paths).' media files uploaded');$message=count($paths).' image'.(count($paths)===1?'':'s').' uploaded.';
                }elseif($action==='optimize_existing'){
                    $stats=$media->optimizeExisting(5000);
                    \admin_audit('media_variants_built',(int)$stats['generated'].' responsive media variants generated from '.(int)$stats['scanned'].' source images');
                    $message='Media optimization complete: '.(int)$stats['generated'].' responsive file'.((int)$stats['generated']===1?'':'s').' generated';
                    if((int)$stats['already_ready']>0)$message.=', '.(int)$stats['already_ready'].' image'.((int)$stats['already_ready']===1?' was':'s were').' already optimized';
                    if((int)$stats['skipped']>0)$message.=', '.(int)$stats['skipped'].' unsupported/animated file'.((int)$stats['skipped']===1?' skipped':'s skipped');
                    if((int)$stats['failed']>0)$message.='. '.(int)$stats['failed'].' image'.((int)$stats['failed']===1?' needs':'s need').' attention';else$message.='.';
                }elseif($action==='bulk_delete'){
                    if(!\admin_require_password_from_post($error))throw new PublicException($error);
                    $raw=$_POST['paths']??[];if(!is_array($raw))$raw=[];$paths=array_values(array_unique(array_filter(array_map(static fn($v):string=>trim((string)$v),$raw),static fn(string $v):bool=>$v!=='')));if(!$paths)throw new PublicException('Select at least one unused image.');if(count($paths)>100)throw new PublicException('Bulk deletion is limited to 100 images at a time.');
                    $usageMap=$this->usageMap($db,$paths);foreach($paths as $path){$usage=$usageMap[$path]??[];if($usage)throw new PublicException(basename($path).' is still in use. Remove it from '.implode(', ',array_slice($usage,0,3)).' before deleting.');}
                    $deleted=0;foreach($paths as $path){if($media->delete($path))$deleted++;else throw new PublicException('One selected media file could not be deleted. Refresh the library and try again.');}
                    \admin_audit('media_bulk_deleted',$deleted.' media files deleted');$message=$deleted.' unused image'.($deleted===1?'':'s').' deleted.';
                }elseif($action==='delete'){
                    if(!\admin_require_password_from_post($error))throw new PublicException($error);
                    $path=trim((string)($_POST['path']??''));if($path==='')throw new PublicException('Choose a media file.');
                    $usage=($this->usageMap($db,[$path])[$path]??[]);if($usage)throw new PublicException('This image is still in use: '.implode(', ',array_slice($usage,0,4)).'. Replace it there before deleting it.');
                    if(!$media->delete($path))throw new PublicException('The media file could not be deleted.');
                    \admin_audit('media_deleted',$path);$message='Media file deleted.';
                }else throw new PublicException('Unknown media action.');
            }catch(PublicException $e){$error=$e->getMessage();}catch(\Throwable $e){$error=\web_safe_exception_message($e,'media_action','The media action could not be completed.');}
            $params=[];$rq=substr(trim((string)($_POST['return_q']??'')),0,120);$rf=trim((string)($_POST['return_folder']??''));$rp=max(1,(int)($_POST['return_page']??1));if($rq!=='')$params['q']=$rq;if($rf!=='')$params['folder']=$rf;if($rp>1)$params['page']=$rp;$return='admin/media.php'.($params?'?'.http_build_query($params):'');\web_post_redirect($return,'admin_media',$message,$error);
        }

        $all=$media->list(5000);$folders=[];foreach($all as $item){$folder=(string)($item['folder']??'general');$folders[$folder]=($folders[$folder]??0)+1;}ksort($folders);
        $query=substr(trim((string)($_GET['q']??'')),0,120);$folder=trim((string)($_GET['folder']??''));
        $filtered=array_values(array_filter($all,static function(array $item)use($query,$folder):bool{
            if($folder!==''&&(string)($item['folder']??'')!==$folder)return false;
            if($query==='')return true;
            $hay=strtolower((string)($item['filename']??'').' '.(string)($item['path']??''));return str_contains($hay,strtolower($query));
        }));
        $total=count($filtered);$perPage=24;$page=max(1,(int)($_GET['page']??1));$totalPages=max(1,(int)ceil($total/$perPage));if($page>$totalPages)$page=$totalPages;
        $items=array_slice($filtered,($page-1)*$perPage,$perPage);$usages=$this->usageMap($db,array_values(array_filter(array_map(static fn(array $item):string=>(string)($item['path']??''),$items))));
        $flash=\web_flash_take('admin_media');return ['message'=>$flash['message'],'error'=>$flash['error'],'items'=>$items,'folders'=>$folders,'query'=>$query,'folderFilter'=>$folder,'page'=>$page,'perPage'=>$perPage,'total'=>$total,'totalPages'=>$totalPages,'usages'=>$usages,'optimizerAvailable'=>$media->optimizerAvailable()];
    }

    private function usageMap(\PDO $db,array $paths): array
    {
        $paths=array_values(array_unique(array_filter(array_map('strval',$paths),static fn(string $path):bool=>$path!=='')));
        $out=[];foreach($paths as $path)$out[$path]=[];
        if(!$paths)return $out;
        $add=static function(array &$map,string $path,string $label):void{if(isset($map[$path])&&!in_array($label,$map[$path],true))$map[$path][]=$label;};
        $marks=implode(',',array_fill(0,count($paths),'?'));
        try{
            if($this->exists($db,'store_products')){
                $s=$db->prepare("SELECT name,main_image_path,gallery_json FROM store_products WHERE main_image_path IN ($marks) OR (gallery_json IS NOT NULL AND gallery_json<>'')");$s->execute($paths);
                foreach($s->fetchAll() as $row){
                    $name=(string)($row['name']??'Product');$main=(string)($row['main_image_path']??'');if($main!=='')$add($out,$main,'Product: '.$name);
                    $gallery=json_decode((string)($row['gallery_json']??''),true);if(is_array($gallery))foreach($gallery as $image){$image=(string)$image;if($image!=='')$add($out,$image,'Product gallery: '.$name);}
                }
            }
            if($this->exists($db,'homepage_section_items')){
                $sql="SELECT id,title,image_path,mobile_image_path FROM homepage_section_items WHERE image_path IN ($marks) OR mobile_image_path IN ($marks)";$s=$db->prepare($sql);$s->execute(array_merge($paths,$paths));
                foreach($s->fetchAll() as $row){$label='Homepage item #'.(int)$row['id'].(!empty($row['title'])?' ('.(string)$row['title'].')':'');$add($out,(string)($row['image_path']??''),$label);$add($out,(string)($row['mobile_image_path']??''),$label);}
            }
            if($this->exists($db,'store_brands')){
                $sql="SELECT name,logo_path,cover_path FROM store_brands WHERE logo_path IN ($marks) OR cover_path IN ($marks)";$s=$db->prepare($sql);$s->execute(array_merge($paths,$paths));
                foreach($s->fetchAll() as $row){$label='Brand: '.(string)($row['name']??'');$add($out,(string)($row['logo_path']??''),$label);$add($out,(string)($row['cover_path']??''),$label);}
            }
            if($this->exists($db,'store_categories')){
                $s=$db->prepare("SELECT name,image_path FROM store_categories WHERE image_path IN ($marks)");$s->execute($paths);foreach($s->fetchAll() as $row)$add($out,(string)($row['image_path']??''),'Category: '.(string)($row['name']??''));
            }
            if($this->exists($db,'fragrance_notes')){
                $s=$db->prepare("SELECT name,image_path FROM fragrance_notes WHERE image_path IN ($marks)");$s->execute($paths);foreach($s->fetchAll() as $row)$add($out,(string)($row['image_path']??''),'Note: '.(string)($row['name']??''));
            }
            if($this->exists($db,'store_collections')){
                $s=$db->prepare("SELECT name,image_path FROM store_collections WHERE image_path IN ($marks)");$s->execute($paths);foreach($s->fetchAll() as $row)$add($out,(string)($row['image_path']??''),'Collection: '.(string)($row['name']??''));
            }
            if($this->exists($db,'store_order_items')){
                $s=$db->prepare("SELECT order_id,product_name,image_path FROM store_order_items WHERE image_path IN ($marks)");$s->execute($paths);
                foreach($s->fetchAll() as $row)$add($out,(string)($row['image_path']??''),'Historical order #'.(int)($row['order_id']??0).' · '.(string)($row['product_name']??'Product'));
            }
        }catch(\Throwable $e){\App\Support\Logger::warning('Media usage lookup unavailable',['message'=>$e->getMessage()]);}
        return $out;
    }

    private function exists(\PDO $db,string $table): bool
    {
        static $cache=[];$key=spl_object_id($db).':'.$table;if(array_key_exists($key,$cache))return $cache[$key];
        try{$s=$db->prepare('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?');$s->execute([$table]);return $cache[$key]=((int)$s->fetchColumn()===1);}catch(\Throwable){return $cache[$key]=false;}
    }

}
