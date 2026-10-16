<?php
declare(strict_types=1);
namespace App\Http\Controllers\Admin;

use App\Support\PublicException;

final class ShippingController
{
    private const FLASH='admin_shipping';

    public function handle(): array
    {
        $db=\db();$ready=$this->exists($db,'shipping_zones');
        if(($_SERVER['REQUEST_METHOD']??'GET')==='POST'){
            \verify_csrf();$message='';$error='';$redirectZoneId=0;
            try{
                if(!$ready)throw new PublicException('Shipping zones are not installed yet. Run the commerce migration first.');
                $action=(string)($_POST['action']??'save');
                $id=max(0,(int)($_POST['id']??0));$redirectZoneId=$id;
                if($action==='save'){
                    $name=trim((string)($_POST['name']??''));$governorate=trim((string)($_POST['governorate']??''));
                    $fee=$this->money($_POST['fee']??0,false);$threshold=$this->money($_POST['free_shipping_threshold']??null,true);
                    $daysMin=$this->intOrNull($_POST['estimated_days_min']??null);$daysMax=$this->intOrNull($_POST['estimated_days_max']??null);
                    $sort=(int)($_POST['sort_order']??0);$status=(string)($_POST['status']??'active');$cod=!empty($_POST['cod_enabled'])?1:0;
                    if($name===''||mb_strlen($name)>140)throw new PublicException('Zone name is required and must be 140 characters or fewer.');
                    if(mb_strlen($governorate)>120)throw new PublicException('Governorate is too long.');
                    if($governorate!==''&&!in_array($governorate,\store_egypt_governorates(),true))throw new PublicException('Choose a governorate from the supported Egypt list or use Rest of Egypt / default.');
                    if(!in_array($status,['active','inactive'],true))$status='active';
                    if($daysMin!==null&&($daysMin<1||$daysMin>90))throw new PublicException('Minimum delivery days must be between 1 and 90.');
                    if($daysMax!==null&&($daysMax<1||$daysMax>90))throw new PublicException('Maximum delivery days must be between 1 and 90.');
                    if($daysMin!==null&&$daysMax!==null&&$daysMax<$daysMin)throw new PublicException('Maximum delivery days cannot be lower than the minimum.');
                    $dupe=$db->prepare("SELECT id FROM shipping_zones WHERE LOWER(TRIM(COALESCE(governorate,'')))=LOWER(TRIM(?)) AND id<>? LIMIT 1");$dupe->execute([$governorate,$id]);
                    if($dupe->fetchColumn())throw new PublicException($governorate===''?'A default shipping zone already exists.':'That governorate already has a shipping zone. Edit the existing zone instead.');
                    if($id>0){
                        $s=$db->prepare('UPDATE shipping_zones SET name=?,governorate=?,city_pattern=NULL,fee=?,free_shipping_threshold=?,cod_enabled=?,estimated_days_min=?,estimated_days_max=?,sort_order=?,status=? WHERE id=?');
                        $s->execute([$name,$governorate!==''?$governorate:null,$fee,$threshold,$cod,$daysMin,$daysMax,$sort,$status,$id]);
                        if($s->rowCount()===0){$check=$db->prepare('SELECT id FROM shipping_zones WHERE id=?');$check->execute([$id]);if(!$check->fetchColumn())throw new PublicException('Shipping zone not found.');}
                        \admin_audit('shipping_zone_updated','Shipping zone #'.$id.' · '.$name);$message='Shipping zone updated.';
                    }else{
                        $s=$db->prepare('INSERT INTO shipping_zones(name,governorate,city_pattern,fee,free_shipping_threshold,cod_enabled,estimated_days_min,estimated_days_max,sort_order,status) VALUES(?,?,NULL,?,?,?,?,?,?,?)');
                        $s->execute([$name,$governorate!==''?$governorate:null,$fee,$threshold,$cod,$daysMin,$daysMax,$sort,$status]);$id=(int)$db->lastInsertId();$redirectZoneId=$id;
                        \admin_audit('shipping_zone_created','Shipping zone #'.$id.' · '.$name);$message='Shipping zone created.';
                    }
                }elseif($action==='bulk_shipping'){$redirectZoneId=0;
                    $rawIds=$_POST['shipping_ids']??[];if(!is_array($rawIds))$rawIds=[];$ids=array_values(array_unique(array_filter(array_map('intval',$rawIds),static fn(int $v):bool=>$v>0)));
                    if(!$ids)throw new PublicException('Select at least one shipping zone.');if(count($ids)>100)throw new PublicException('Bulk actions are limited to 100 shipping zones at a time.');
                    $bulk=(string)($_POST['bulk_action']??'');if(!in_array($bulk,['enable','disable','delete'],true))throw new PublicException('Choose a bulk action.');
                    $marks=implode(',',array_fill(0,count($ids),'?'));$q=$db->prepare('SELECT id,name,status FROM shipping_zones WHERE id IN ('.$marks.')');$q->execute($ids);$targets=$q->fetchAll();if(count($targets)!==count($ids))throw new PublicException('One or more selected shipping zones no longer exist. Refresh the page and try again.');
                    if($bulk==='delete'){
                        if(!\admin_require_password_from_post($error))throw new PublicException($error);
                        foreach($targets as $target){if((string)($target['status']??'')!=='inactive')throw new PublicException('Disable every selected shipping zone before permanently deleting it.');}
                        $db->beginTransaction();try{$d=$db->prepare('DELETE FROM shipping_zones WHERE id=?');foreach($ids as $zoneId){$d->execute([$zoneId]);if($d->rowCount()!==1)throw new PublicException('One selected shipping zone could not be deleted.');}$db->commit();}catch(\Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
                        \admin_audit('shipping_zones_bulk_deleted',count($ids).' shipping zones deleted');$message=count($ids).' shipping zone'.(count($ids)===1?'':'s').' permanently deleted.';
                    }else{
                        $next=$bulk==='enable'?'active':'inactive';$db->beginTransaction();try{$u=$db->prepare('UPDATE shipping_zones SET status=? WHERE id=?');$changed=0;foreach($ids as $zoneId){$u->execute([$next,$zoneId]);$changed+=$u->rowCount();}$db->commit();}catch(\Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
                        \admin_audit('shipping_zones_bulk_status',$next.' · '.$changed.' shipping zones');$message=count($ids).' shipping zone'.(count($ids)===1?'':'s').' '.($next==='active'?'enabled.':'disabled.');
                    }
                }elseif($action==='enable'){
                    if($id<=0)throw new PublicException('Shipping zone not found.');
                    $s=$db->prepare("UPDATE shipping_zones SET status='active' WHERE id=?");$s->execute([$id]);
                    \admin_audit('shipping_zone_enabled','Shipping zone #'.$id);$message='Shipping zone enabled.';
                }elseif($action==='archive'){
                    if($id<=0)throw new PublicException('Shipping zone not found.');
                    $s=$db->prepare("UPDATE shipping_zones SET status='inactive' WHERE id=?");$s->execute([$id]);
                    \admin_audit('shipping_zone_archived','Shipping zone #'.$id);$message='Shipping zone disabled.';
                }elseif($action==='delete'){
                    if(!\admin_require_password_from_post($error))throw new PublicException($error);
                    if($id<=0)throw new PublicException('Shipping zone not found.');
                    $check=$db->prepare('SELECT status FROM shipping_zones WHERE id=?');$check->execute([$id]);$zoneStatus=$check->fetchColumn();if($zoneStatus===false)throw new PublicException('Shipping zone not found.');if((string)$zoneStatus!=='inactive')throw new PublicException('Disable this shipping zone before deleting it permanently.');
                    $s=$db->prepare('DELETE FROM shipping_zones WHERE id=?');$s->execute([$id]);if($s->rowCount()!==1)throw new PublicException('Shipping zone not found.');
                    \admin_audit('shipping_zone_deleted','Shipping zone #'.$id);$message='Shipping zone deleted.';$redirectZoneId=0;
                }else throw new PublicException('Unknown shipping action.');
            }catch(PublicException $e){$error=$e->getMessage();}
            catch(\Throwable $e){$error=\web_safe_exception_message($e,'shipping_zone_action','The shipping zone could not be saved.');}
            $redirectQuery=$redirectZoneId>0?http_build_query(['open_zone'=>$redirectZoneId]):'';
            \web_post_redirect('admin/shipping.php',self::FLASH,$message,$error,$redirectQuery);
        }
        $flash=\web_flash_take(self::FLASH);$rows=[];$openZoneId=max(0,(int)($_GET['open_zone']??0));
        if($ready)$rows=$db->query("SELECT * FROM shipping_zones ORDER BY (governorate IS NULL OR governorate='') DESC,sort_order,name,id")->fetchAll();
        return ['ready'=>$ready,'rows'=>$rows,'openZoneId'=>$openZoneId,'message'=>$flash['message'],'error'=>$flash['error'],'currency'=>strtoupper((string)(\store_runtime_settings()['currency']??'EGP')),'governorates'=>\store_egypt_governorates()];
    }

    private function exists(\PDO $db,string $table):bool{$s=$db->prepare('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?');$s->execute([$table]);return (int)$s->fetchColumn()===1;}
    private function money(mixed $value,bool $nullable): ?string{$raw=trim((string)$value);if($raw===''&&$nullable)return null;if($raw===''||!is_numeric($raw)||(float)$raw<0)throw new PublicException('Shipping amounts must be zero or greater.');return number_format((float)$raw,2,'.','');}
    private function intOrNull(mixed $value): ?int{$raw=trim((string)$value);if($raw==='')return null;if(!ctype_digit($raw))throw new PublicException('Delivery days must be whole numbers.');return (int)$raw;}
}
