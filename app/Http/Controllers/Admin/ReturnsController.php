<?php
declare(strict_types=1);
namespace App\Http\Controllers\Admin;

use App\Support\PublicException;
use App\Support\ServiceFactory;

final class ReturnsController
{
    private const FLASH='admin_returns';

    public function handle(): array
    {
        $db=\db();$message='';$error='';
        if(($_SERVER['REQUEST_METHOD']??'GET')==='POST'){
            \verify_csrf();$redirectReturnId=0;
            try{
                $action=(string)($_POST['action']??'');$id=max(0,(int)($_POST['id']??0));$redirectReturnId=$id;if($id<=0)throw new PublicException('Return request not found.');
                $service=ServiceFactory::returns($db);$adminId=(int)(\current_user()['id']??0);
                if($action==='review'||$action==='approve'||$action==='reject'){
                    $decisionRaw=$action==='review'?(string)($_POST['decision']??''):$action;
                    if(!in_array($decisionRaw,['approve','reject'],true))throw new PublicException('Choose Approve or Reject for this return.');
                    $decision=$decisionRaw==='approve'?'approved':'rejected';$service->review($id,$decision,(string)($_POST['admin_note']??''),$adminId);\admin_audit('return_'.$decision,'Return #'.$id.' '.$decision);$message='Return request '.$decision.'.';
                }elseif($action==='complete'){
                    $passwordError='';if(!\admin_require_password_from_post($passwordError))throw new PublicException($passwordError);
                    $refundRaw=trim((string)($_POST['refund_amount']??'0'));if($refundRaw===''||!is_numeric($refundRaw))throw new PublicException('Enter a valid refund amount.');
                    $restock=is_array($_POST['restock']??null)?array_keys($_POST['restock']):[];$service->complete($id,(float)$refundRaw,$restock,(string)($_POST['admin_note']??''),$adminId,(string)($_POST['refund_method']??''),(string)($_POST['refund_reference']??''));\admin_audit('return_completed','Return #'.$id.' completed with refund '.number_format((float)$refundRaw,2));$message='Return completed. Stock and refund state were updated from the reviewed choices.';
                }else throw new PublicException('Unknown return action.');
            }catch(PublicException $e){$error=$e->getMessage();}catch(\Throwable $e){$error=\web_safe_exception_message($e,'admin_return_action','The return request could not be updated.');}
            $redirectQuery=\admin_preserved_query(['status','page'],['open_return'=>$redirectReturnId>0?$redirectReturnId:null]);
            \web_post_redirect('admin/returns.php',self::FLASH,$message,$error,$redirectQuery);
        }
        $flash=\web_flash_take(self::FLASH);$status=trim((string)($_GET['status']??''));$allowed=['requested','approved','rejected','completed','cancelled'];$where='';$args=[];if(in_array($status,$allowed,true)){$where=' WHERE r.status=?';$args[]=$status;}else{$status='';}
        $openReturnId=max(0,(int)($_GET['open_return']??0));
        $page=max(1,(int)($_GET['page']??1));$perPage=30;$count=$db->prepare("SELECT COUNT(*) FROM store_returns r{$where}");$count->execute($args);$total=(int)$count->fetchColumn();$totalPages=max(1,(int)ceil($total/$perPage));$page=min($page,$totalPages);$offset=($page-1)*$perPage;
        $s=$db->prepare("SELECT r.*,o.order_number,o.customer_name,o.guest_email,o.grand_total,o.subtotal,o.discount_total,o.currency,o.payment_status,o.fulfillment_status,u.username FROM store_returns r JOIN store_orders o ON o.id=r.order_id LEFT JOIN users u ON u.id=r.user_id{$where} ORDER BY CASE r.status WHEN 'requested' THEN 0 WHEN 'approved' THEN 1 ELSE 2 END,r.requested_at DESC,r.id DESC LIMIT {$perPage} OFFSET {$offset}");$s->execute($args);$rows=$s->fetchAll();$itemsByReturn=[];$returnValues=[];$maxRefunds=[];
        if($rows){
            $ids=array_map(static fn($r)=>(int)$r['id'],$rows);$marks=implode(',',array_fill(0,count($ids),'?'));$q=$db->prepare("SELECT ri.return_id,ri.order_item_id,ri.quantity,ri.restock,oi.product_id,oi.product_name,oi.variant_label,oi.image_path,oi.variant_id,oi.quantity AS ordered_quantity,oi.unit_price,oi.line_total,p.main_image_path AS current_image_path FROM store_return_items ri JOIN store_order_items oi ON oi.id=ri.order_item_id LEFT JOIN store_products p ON p.id=oi.product_id WHERE ri.return_id IN ({$marks}) ORDER BY ri.return_id,oi.id");$q->execute($ids);foreach($q->fetchAll() as $item)$itemsByReturn[(int)$item['return_id']][]=$item;
            $orderIds=array_values(array_unique(array_map(static fn($r)=>(int)$r['order_id'],$rows)));$refundedByOrder=[];
            if($orderIds){$orderMarks=implode(',',array_fill(0,count($orderIds),'?'));$rf=$db->prepare("SELECT order_id,COALESCE(SUM(refund_amount),0) AS refunded FROM store_returns WHERE status='completed' AND order_id IN ({$orderMarks}) GROUP BY order_id");$rf->execute($orderIds);foreach($rf->fetchAll() as $rr)$refundedByOrder[(int)$rr['order_id']]=(float)$rr['refunded'];}
            foreach($rows as $row){$rid=(int)$row['id'];$subtotal=max(0.0,(float)$row['subtotal']);$netMerchandise=max(0.0,$subtotal-(float)$row['discount_total']);$discountFactor=$subtotal>0?min(1.0,$netMerchandise/$subtotal):1.0;$value=0.0;foreach(($itemsByReturn[$rid]??[]) as $item){$orderedQty=max(1,(int)$item['ordered_quantity']);$value+=((float)$item['line_total']/$orderedQty)*(int)$item['quantity']*$discountFactor;}$value=max(0.0,$value);$returnValues[$rid]=$value;$already=(float)($refundedByOrder[(int)$row['order_id']]??0);if((string)$row['status']==='completed')$already=max(0.0,$already-(float)($row['refund_amount']??0));$remaining=max(0.0,(float)$row['grand_total']-$already);$maxRefunds[$rid]=min($remaining,$value);}
        }
        $counts=[];try{foreach($db->query('SELECT status,COUNT(*) c FROM store_returns GROUP BY status')->fetchAll() as $row)$counts[(string)$row['status']]=(int)$row['c'];}catch(\Throwable){}
        return ['rows'=>$rows,'itemsByReturn'=>$itemsByReturn,'returnValues'=>$returnValues,'maxRefunds'=>$maxRefunds,'counts'=>$counts,'statusFilter'=>$status,'openReturnId'=>$openReturnId,'page'=>$page,'perPage'=>$perPage,'total'=>$total,'totalPages'=>$totalPages,'message'=>$flash['message'],'error'=>$flash['error']];
    }
}
