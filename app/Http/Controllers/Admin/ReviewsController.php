<?php
declare(strict_types=1);
namespace App\Http\Controllers\Admin;

use App\Support\PublicException;

final class ReviewsController
{
    private const FLASH='store_reviews_admin';
    public function handle(): array
    {
        $db=\db();$ready=$this->exists($db,'store_reviews');
        if(($_SERVER['REQUEST_METHOD']??'GET')==='POST'){
            \verify_csrf();$message='';$error='';
            try{
                if(!$ready)throw new PublicException('Commerce migration 034 is required.');
                $id=max(0,(int)($_POST['id']??0));$action=(string)($_POST['action']??'');
                if($id<=0)throw new PublicException('Choose a valid review.');
                if(in_array($action,['approve','hide'],true)){
                    $status=$action==='approve'?'approved':'hidden';$s=$db->prepare('UPDATE store_reviews SET status=? WHERE id=?');$s->execute([$status,$id]);
                    if($s->rowCount()===0){$q=$db->prepare('SELECT id FROM store_reviews WHERE id=?');$q->execute([$id]);if(!$q->fetchColumn())throw new PublicException('Review not found.');}
                    \admin_audit('review_'.$status,'Review #'.$id.' -> '.$status);$message=$status==='approved'?'Review published.':'Review hidden.';
                }elseif($action==='delete'){
                    $s=$db->prepare('DELETE FROM store_reviews WHERE id=?');$s->execute([$id]);if($s->rowCount()!==1)throw new PublicException('Review not found.');
                    \admin_audit('review_deleted','Review #'.$id.' permanently deleted');$message='Review deleted.';
                }else throw new PublicException('Unknown review action.');
            }catch(PublicException $e){$error=$e->getMessage();}catch(\Throwable $e){$error=\web_safe_exception_message($e,'admin_reviews','Review could not be updated.');}
            $return='admin/reviews.php';$status=trim((string)($_POST['return_status']??''));if(in_array($status,['pending','approved','hidden'],true))$return.='?status='.rawurlencode($status);\web_post_redirect($return,self::FLASH,$message,$error);
        }
        $flash=\web_flash_take(self::FLASH);$status=trim((string)($_GET['status']??''));if(!in_array($status,['','pending','approved','hidden'],true))$status='';$rows=[];$counts=['pending'=>0,'approved'=>0,'hidden'=>0];
        if($ready){
            foreach($db->query("SELECT status,COUNT(*) c FROM store_reviews GROUP BY status")->fetchAll() as $c){$k=(string)$c['status'];if(isset($counts[$k]))$counts[$k]=(int)$c['c'];}
            $where=$status!==''?'WHERE r.status=?':'';$sql="SELECT r.id,r.product_id,r.user_id,r.order_id,r.rating,r.title,r.body,r.status,r.created_at,r.updated_at,p.name product_name,p.slug product_slug,u.username,u.email,o.order_number FROM store_reviews r JOIN store_products p ON p.id=r.product_id JOIN users u ON u.id=r.user_id LEFT JOIN store_orders o ON o.id=r.order_id {$where} ORDER BY CASE r.status WHEN 'pending' THEN 0 WHEN 'approved' THEN 1 ELSE 2 END,r.updated_at DESC,r.id DESC LIMIT 200";$q=$db->prepare($sql);$q->execute($status!==''?[$status]:[]);$rows=$q->fetchAll();
        }
        return ['ready'=>$ready,'rows'=>$rows,'counts'=>$counts,'status'=>$status,'message'=>$flash['message'],'error'=>$flash['error']];
    }
    private function exists(\PDO $db,string $table):bool{$s=$db->prepare('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?');$s->execute([$table]);return (int)$s->fetchColumn()===1;}
}
