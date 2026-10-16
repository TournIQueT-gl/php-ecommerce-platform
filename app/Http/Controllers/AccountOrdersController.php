<?php
declare(strict_types=1);
namespace App\Http\Controllers;

use App\Support\PublicException;
use App\Support\ServiceFactory;

final class AccountOrdersController
{
    private const FLASH='account_orders';

    public function handle(): array
    {
        $user=\current_user();
        if(!$user){header('Location: '.\route_url('login.php'));exit;}
        if(($_SERVER['REQUEST_METHOD']??'GET')==='POST'){
            \verify_csrf();$message='';$error='';
            try{
                $action=(string)($_POST['action']??'');$returns=ServiceFactory::returns(\db());
                if($action==='request_return'){
                    $returnId=$returns->request((int)$user['id'],(int)($_POST['order_id']??0),is_array($_POST['return_qty']??null)?$_POST['return_qty']:[],(string)($_POST['reason']??''),(string)($_POST['customer_note']??''));
                    $message='Return request #'.$returnId.' was submitted for review.';
                }elseif($action==='cancel_return'){
                    $returns->cancelByCustomer((int)$user['id'],(int)($_POST['return_id']??0));$message='Return request cancelled.';
                }else throw new PublicException('Unknown order action.');
            }catch(PublicException $e){$error=$e->getMessage();}catch(\Throwable $e){$error=\web_safe_exception_message($e,'account_return_action','Your return request could not be updated.');}
            $_SESSION['_flash_'.self::FLASH]=['message'=>$message,'error'=>$error];
            header('Location: '.\route_url('account-orders.php'),true,303);exit;
        }
        $rows=[];$itemsByOrder=[];$historyByOrder=[];$returnsByOrder=[];$returnUsedByItem=[];$page=max(1,(int)($_GET['page']??1));$perPage=20;$total=0;$totalPages=1;$returnWindowDays=14;
        try{
            $db=\db();
            $count=$db->prepare('SELECT COUNT(*) FROM store_orders WHERE user_id=?');$count->execute([(int)$user['id']]);$total=(int)$count->fetchColumn();$totalPages=max(1,(int)ceil($total/$perPage));$page=min($page,$totalPages);$offset=($page-1)*$perPage;
            try{$rw=$db->prepare("SELECT setting_value FROM store_settings WHERE setting_key='return_window_days' LIMIT 1");$rw->execute();$candidate=(int)$rw->fetchColumn();if($candidate>=1&&$candidate<=90)$returnWindowDays=$candidate;}catch(\Throwable){}
            $s=$db->prepare("SELECT o.*,(SELECT p.method FROM store_payments p WHERE p.order_id=o.id ORDER BY p.id DESC LIMIT 1) AS payment_method,(SELECT MIN(h.created_at) FROM store_order_status_history h WHERE h.order_id=o.id AND (h.fulfillment_status='fulfilled' OR h.status='completed')) AS delivered_at FROM store_orders o WHERE o.user_id=? ORDER BY o.placed_at DESC,o.id DESC LIMIT {$perPage} OFFSET {$offset}");
            $s->execute([(int)$user['id']]);$rows=$s->fetchAll();
            $ids=array_values(array_filter(array_map(static fn($row)=>(int)($row['id']??0),$rows)));
            if($ids){
                $placeholders=implode(',',array_fill(0,count($ids),'?'));
                $q=$db->prepare("SELECT id,order_id,product_name,brand_name,variant_label,sku,image_path,quantity,unit_price,line_total FROM store_order_items WHERE order_id IN ({$placeholders}) ORDER BY order_id,id");
                $q->execute($ids);foreach($q->fetchAll() as $item)$itemsByOrder[(int)$item['order_id']][]=$item;
                $h=$db->prepare("SELECT order_id,status,payment_status,fulfillment_status,note,created_at FROM store_order_status_history WHERE order_id IN ({$placeholders}) ORDER BY created_at,id");
                $h->execute($ids);foreach($h->fetchAll() as $entry)$historyByOrder[(int)$entry['order_id']][]=$entry;
                $r=$db->prepare("SELECT id,order_id,status,reason,customer_note,admin_note,refund_amount,requested_at,reviewed_at,completed_at FROM store_returns WHERE user_id=? AND order_id IN ({$placeholders}) ORDER BY requested_at DESC,id DESC");$r->execute(array_merge([(int)$user['id']],$ids));foreach($r->fetchAll() as $entry)$returnsByOrder[(int)$entry['order_id']][]=$entry;
                $used=$db->prepare("SELECT ri.order_item_id,COALESCE(SUM(ri.quantity),0) qty FROM store_return_items ri JOIN store_returns r ON r.id=ri.return_id WHERE r.user_id=? AND r.order_id IN ({$placeholders}) AND r.status IN ('requested','approved','completed') GROUP BY ri.order_item_id");$used->execute(array_merge([(int)$user['id']],$ids));foreach($used->fetchAll() as $entry)$returnUsedByItem[(int)$entry['order_item_id']]=(int)$entry['qty'];
            }
        }catch(\Throwable $e){\App\Support\Logger::warning('Account orders unavailable',['user_id'=>(int)$user['id'],'message'=>$e->getMessage()]);}
        $flash=$_SESSION['_flash_'.self::FLASH]??['message'=>'','error'=>''];unset($_SESSION['_flash_'.self::FLASH]);
        return ['user'=>$user,'rows'=>$rows,'itemsByOrder'=>$itemsByOrder,'historyByOrder'=>$historyByOrder,'returnsByOrder'=>$returnsByOrder,'returnUsedByItem'=>$returnUsedByItem,'page'=>$page,'perPage'=>$perPage,'total'=>$total,'totalPages'=>$totalPages,'returnWindowDays'=>$returnWindowDays,'message'=>(string)($flash['message']??''),'error'=>(string)($flash['error']??'')];
    }
}
