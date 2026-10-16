<?php
declare(strict_types=1);
namespace App\Http\Controllers\Admin;

use App\Support\PublicException;
use App\Support\ServiceFactory;

final class CustomersController
{
    private const FLASH='admin_customers';
    private const RESET_FLASH='_admin_customer_reset_link';

    public function handle(): array
    {
        $db=\db();
        $users=ServiceFactory::users($db);
        $recovery=ServiceFactory::passwordRecovery($db);
        $recoveryRepo=ServiceFactory::recoveryRepository($db);
        $role=\admin_current_role();
        $targetId=max(0,(int)($_GET['id']??$_POST['user_id']??0));

        if(($_SERVER['REQUEST_METHOD']??'GET')==='POST'){
            \verify_csrf();
            $message='';$error='';
            try{
                if($targetId<=0)throw new PublicException('Customer not found.');
                $target=$users->findForAdmin($targetId);
                if(!$target)throw new PublicException('Customer not found.');
                $this->assertRecoveryPermission($target);
                if(!\admin_require_password_from_post($error))throw new PublicException($error);

                $action=(string)($_POST['action']??'');
                if($action==='generate_recovery_link'){
                    $result=$recovery->createAdminReset($targetId,(int)($_SESSION['user_id']??0),\client_ip(),\web_user_agent());
                    if(empty($result['ok']))throw new PublicException((string)($result['message']??'Reset link could not be created.'));
                    $token=(string)($result['token']??'');
                    if($token==='')throw new PublicException('Reset token could not be created.');
                    $_SESSION[self::RESET_FLASH]=[
                        'user_id'=>$targetId,
                        'url'=>rtrim((string)CANONICAL_SITE_URL,'/').\route_url('forgot-password.php').'?token='.rawurlencode($token),
                        'expires_at'=>time()+300,
                    ];
                    \admin_audit('customer_password_reset_link_created','Fresh one-time reset link generated for '.$target['username'],$targetId);
                    $message='Fresh one-time reset link created. Copy it now; it is only shown once here.';
                }elseif($action==='cancel_recovery'){
                    $requestId=max(0,(int)($_POST['request_id']??0));
                    if($requestId<=0)throw new PublicException('Recovery request not found.');
                    $result=$recovery->cancel($requestId,$targetId);
                    if(empty($result['ok']))throw new PublicException((string)$result['message']);
                    \admin_audit('customer_password_recovery_cancelled','Recovery request #'.$requestId.' cancelled for '.$target['username'],$targetId);
                    $message=(string)$result['message'];
                }else{
                    throw new PublicException('Unknown customer action.');
                }
            }catch(PublicException $e){$error=$e->getMessage();}
            catch(\Throwable $e){$error=\web_safe_exception_message($e,'admin_customer_action','The customer action could not be completed.');}
            \web_post_redirect('admin/customers.php',self::FLASH,$message,$error,'?id='.$targetId.'#recovery');
        }

        $flash=\web_flash_take(self::FLASH);
        $oneTimeReset='';
        $stored=$_SESSION[self::RESET_FLASH]??null;
        if(is_array($stored) && (int)($stored['user_id']??0)===$targetId && (int)($stored['expires_at']??0)>=time()){
            $oneTimeReset=(string)($stored['url']??'');
            unset($_SESSION[self::RESET_FLASH]);
        }elseif($stored!==null){
            unset($_SESSION[self::RESET_FLASH]);
        }

        $detail=null;$detailOrders=[];$detailActivity=[];$detailRecoveries=[];$detailSessions=[];
        if($targetId>0){
            $detail=$users->findForAdmin($targetId);
            if($detail && $role==='support' && !empty($detail['is_admin'])){
                $detail=null;
                if(($flash['error']??'')==='')$flash['error']='Administrator profiles are restricted to store administrators and owners.';
            }
            if($detail){
                try{
                    $s=$db->prepare('SELECT id,order_number,status,payment_status,fulfillment_status,currency,grand_total,placed_at FROM store_orders WHERE user_id=? ORDER BY placed_at DESC,id DESC LIMIT 20');
                    $s->execute([$targetId]);$detailOrders=$s->fetchAll();
                }catch(\Throwable){}
                try{$detailActivity=ServiceFactory::accountActivity($db)->forUser($targetId,20);}catch(\Throwable){}
                try{$detailRecoveries=$recoveryRepo->pendingForUser($targetId,20);}catch(\Throwable){}
                try{$detailSessions=ServiceFactory::webSessions($db)->listForUser($targetId,10);}catch(\Throwable){}
            }
        }

        $q=substr(trim((string)($_GET['q']??'')),0,80);
        $page=max(1,(int)($_GET['page']??1));
        $perPage=40;
        $params=[];
        $where='u.is_admin=0';
        if($q!==''){
            $where.=' AND (u.username LIKE ? OR u.email LIKE ?)';
            $needle='%'.$q.'%';
            $params=[$needle,$needle];
        }
        $count=$db->prepare("SELECT COUNT(*) FROM users u WHERE {$where}");
        $count->execute($params);
        $total=(int)$count->fetchColumn();
        $totalPages=max(1,(int)ceil($total/$perPage));
        $page=min($page,$totalPages);
        $offset=($page-1)*$perPage;

        $orderSummary="LEFT JOIN (SELECT o.user_id,SUM(CASE WHEN o.status<>'cancelled' THEN 1 ELSE 0 END) AS order_count,COALESCE(SUM(CASE WHEN o.status<>'cancelled' AND o.payment_status IN ('paid','partially_refunded','refunded') THEN GREATEST(o.grand_total-COALESCE((SELECT SUM(r.refund_amount) FROM store_returns r WHERE r.order_id=o.id AND r.status='completed'),0),0) ELSE 0 END),0) AS lifetime_value FROM store_orders o WHERE o.user_id IS NOT NULL GROUP BY o.user_id) os ON os.user_id=u.id";
        $sql="SELECT u.id,u.username,u.email,u.email_verified_at,u.is_active,u.is_admin,u.admin_role,u.last_login_at,u.created_at,COALESCE(os.order_count,0) AS order_count,COALESCE(os.lifetime_value,0) AS lifetime_value FROM users u {$orderSummary} WHERE {$where} ORDER BY u.created_at DESC,u.id DESC LIMIT {$perPage} OFFSET {$offset}";
        try{
            $s=$db->prepare($sql);$s->execute($params);$rows=$s->fetchAll();
        }catch(\Throwable){
            $sql="SELECT u.id,u.username,u.email,u.email_verified_at,u.is_active,u.is_admin,u.admin_role,u.last_login_at,u.created_at,0 AS order_count,0 AS lifetime_value FROM users u WHERE {$where} ORDER BY u.created_at DESC,u.id DESC LIMIT {$perPage} OFFSET {$offset}";
            $s=$db->prepare($sql);$s->execute($params);$rows=$s->fetchAll();
        }
        return compact('q','rows','page','perPage','total','totalPages','detail','detailOrders','detailActivity','detailRecoveries','detailSessions','oneTimeReset')+['message'=>$flash['message'],'error'=>$flash['error']];
    }

    private function assertRecoveryPermission(array $target): void
    {
        $role=\admin_current_role();
        if(!in_array($role,['owner','admin'],true))throw new PublicException('Your role cannot manage password recovery.');
        if(!empty($target['is_admin']) && $role!=='owner')throw new PublicException('Only an owner can manage recovery for another administrator.');
    }
}
