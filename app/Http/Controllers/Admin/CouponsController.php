<?php
declare(strict_types=1);
namespace App\Http\Controllers\Admin;

use App\Support\PublicException;

final class CouponsController
{
    private const FLASH='store_coupons';
    public function handle(): array
    {
        $db=\db();$ready=$this->exists($db,'store_coupons');
        if(($_SERVER['REQUEST_METHOD']??'GET')==='POST'){
            \verify_csrf();$action=(string)($_POST['action']??'');$error='';$message='';
            try{
                if(!$ready)throw new PublicException('Commerce migration 034 is required.');
                if(in_array($action,['create','edit'],true)){
                    $data=$this->validatedCoupon($_POST);$id=$action==='edit'?max(0,(int)($_POST['id']??0)):0;
                    if($action==='edit'&&$id<=0)throw new PublicException('Choose a valid coupon.');
                    try{
                        if($action==='create'){
                            $s=$db->prepare("INSERT INTO store_coupons(code,discount_type,discount_value,minimum_order,maximum_discount,usage_limit,per_user_limit,starts_at,ends_at,status,created_by) VALUES(?,?,?,?,?,?,?,?,?,?,?)");
                            $s->execute([$data['code'],$data['type'],$data['value'],$data['minimum_order'],$data['maximum_discount'],$data['usage_limit'],$data['per_user_limit'],$data['starts_at'],$data['ends_at'],'active',(int)($_SESSION['user_id']??0)]);
                            \admin_audit('coupon_created',$data['code'].' · '.$data['type'].' · '.$data['value']);$message='Coupon created.';
                        }else{
                            $s=$db->prepare('UPDATE store_coupons SET code=?,discount_type=?,discount_value=?,minimum_order=?,maximum_discount=?,usage_limit=?,per_user_limit=?,starts_at=?,ends_at=? WHERE id=?');
                            $s->execute([$data['code'],$data['type'],$data['value'],$data['minimum_order'],$data['maximum_discount'],$data['usage_limit'],$data['per_user_limit'],$data['starts_at'],$data['ends_at'],$id]);
                            $q=$db->prepare('SELECT id FROM store_coupons WHERE id=?');$q->execute([$id]);if(!$q->fetchColumn())throw new PublicException('Coupon not found.');
                            \admin_audit('coupon_edited','Coupon #'.$id.' · '.$data['code']);$message='Coupon changes saved.';
                        }
                    }catch(\PDOException $e){if((string)$e->getCode()==='23000')throw new PublicException('That coupon code already exists.');throw $e;}
                }elseif($action==='bulk'){
                    $ids=array_values(array_unique(array_filter(array_map('intval',(array)($_POST['coupon_ids']??[])),static fn(int $id):bool=>$id>0)));if(count($ids)>100)throw new PublicException('Bulk actions are limited to 100 coupons at a time.');
                    $bulk=(string)($_POST['bulk_action']??'');$status=match($bulk){'activate'=>'active','hide'=>'hidden','archive'=>'archived',default=>''};
                    if(!$ids)throw new PublicException('Select at least one coupon.');if($status==='')throw new PublicException('Choose a valid bulk action.');
                    $placeholders=implode(',',array_fill(0,count($ids),'?'));$s=$db->prepare('UPDATE store_coupons SET status=? WHERE id IN ('.$placeholders.')');$s->execute(array_merge([$status],$ids));$affected=$s->rowCount();\admin_audit('coupons_bulk_status',$status.' · '.$affected.' coupons');$message=$affected.' coupon'.($affected===1?'':'s').' updated.';
                }elseif($action==='toggle'){
                    $id=max(0,(int)($_POST['id']??0));$status=(string)($_POST['status']??'');if($id<=0||!in_array($status,['active','hidden','archived'],true))throw new PublicException('Choose a valid coupon action.');
                    $s=$db->prepare('UPDATE store_coupons SET status=? WHERE id=?');$s->execute([$status,$id]);$q=$db->prepare('SELECT id FROM store_coupons WHERE id=?');$q->execute([$id]);if(!$q->fetchColumn())throw new PublicException('Coupon not found.');\admin_audit('coupon_status_changed','Coupon #'.$id.' -> '.$status);$message='Coupon updated.';
                }else throw new PublicException('Unknown coupon action.');
            }catch(PublicException $e){$error=$e->getMessage();}catch(\Throwable $e){$error=\web_safe_exception_message($e,'admin_coupon','Coupon could not be saved.');}
            $return='admin/coupons.php';$returnPage=max(1,(int)($_POST['return_page']??1));if($returnPage>1)$return.='?page='.$returnPage;\web_post_redirect($return,self::FLASH,$message,$error);
        }
        $flash=\web_flash_take(self::FLASH);$page=max(1,(int)($_GET['page']??1));$perPage=50;$total=0;$pages=1;$rows=[];if($ready){$total=(int)$db->query('SELECT COUNT(*) FROM store_coupons')->fetchColumn();$pages=max(1,(int)ceil($total/$perPage));$page=min($page,$pages);$offset=($page-1)*$perPage;$rows=$db->query("SELECT c.*,(SELECT COUNT(*) FROM store_coupon_redemptions r JOIN store_orders o ON o.id=r.order_id WHERE r.coupon_id=c.id AND o.status<>'cancelled') AS redemptions FROM store_coupons c ORDER BY c.created_at DESC,c.id DESC LIMIT {$perPage} OFFSET {$offset}")->fetchAll();}
        return ['ready'=>$ready,'rows'=>$rows,'message'=>$flash['message'],'error'=>$flash['error'],'page'=>$page,'perPage'=>$perPage,'total'=>$total,'pages'=>$pages];
    }
    private function validatedCoupon(array $input): array
    {
        $rawCode=strtoupper(trim((string)($input['code']??'')));if(!preg_match('/^[A-Z0-9_-]{2,80}$/',$rawCode))throw new PublicException('Coupon code must be 2–80 characters using letters, numbers, hyphens or underscores.');
        $type=(string)($input['discount_type']??'');if(!in_array($type,['percent','fixed','free_shipping'],true))throw new PublicException('Choose a valid coupon type.');
        $value=$type==='free_shipping'?0.0:$this->requiredMoney($input['discount_value']??null,'discount value');if($type==='percent'&&$value>100)throw new PublicException('Percentage discounts cannot exceed 100%.');
        $minimum=$this->optionalMoney($input['minimum_order']??null,'minimum order');$maximum=$this->optionalMoney($input['maximum_discount']??null,'maximum discount');if($type!=='percent')$maximum=null;
        $usage=$this->optionalPositiveInt($input['usage_limit']??null,'total usage limit');$perUser=$this->optionalPositiveInt($input['per_user_limit']??null,'per-customer limit');if($usage!==null&&$perUser!==null&&$perUser>$usage)throw new PublicException('Per-customer limit cannot exceed the total usage limit.');
        $browserTz=\admin_browser_timezone_from_post();$starts=\admin_parse_utc_datetime((string)($input['starts_at']??''),$browserTz);$ends=\admin_parse_utc_datetime((string)($input['ends_at']??''),$browserTz);if($starts!==null&&$ends!==null&&strtotime($ends.' UTC')<=strtotime($starts.' UTC'))throw new PublicException('Coupon end time must be after its start time.');
        return ['code'=>$rawCode,'type'=>$type,'value'=>$value,'minimum_order'=>$minimum,'maximum_discount'=>$maximum,'usage_limit'=>$usage,'per_user_limit'=>$perUser,'starts_at'=>$starts,'ends_at'=>$ends];
    }
    private function requiredMoney(mixed $v,string $label): float{$s=trim((string)$v);if($s===''||!is_numeric($s))throw new PublicException('Enter a valid '.$label.'.');$n=(float)$s;if(!is_finite($n)||$n<=0||$n>100000000)throw new PublicException('Enter a valid '.$label.'.');return round($n,2);}
    private function optionalMoney(mixed $v,string $label): ?float{$s=trim((string)$v);if($s==='')return null;if(!is_numeric($s))throw new PublicException('Enter a valid '.$label.'.');$n=(float)$s;if(!is_finite($n)||$n<0||$n>100000000)throw new PublicException('Enter a valid '.$label.'.');return round($n,2);}
    private function optionalPositiveInt(mixed $v,string $label): ?int{$s=trim((string)$v);if($s==='')return null;if(!preg_match('/^[0-9]+$/',$s))throw new PublicException('Enter a valid '.$label.'.');$n=(int)$s;if($n<1||$n>100000000)throw new PublicException('Enter a valid '.$label.'.');return $n;}
    private function exists(\PDO $db,string $table):bool{$s=$db->prepare('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?');$s->execute([$table]);return (int)$s->fetchColumn()===1;}
}
