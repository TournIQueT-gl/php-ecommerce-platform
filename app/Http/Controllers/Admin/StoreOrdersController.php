<?php
declare(strict_types=1);
namespace App\Http\Controllers\Admin;

use App\Events\DomainEvent;
use App\Support\PublicException;
use App\Support\ServiceFactory;

final class StoreOrdersController
{
    private const FLASH='store_orders';

    public function handle(): array
    {
        $db=\db();
        $ready=$this->exists($db,'store_orders');

        if(($_SERVER['REQUEST_METHOD']??'GET')==='POST'){
            \verify_csrf();
            $error='';$message='';$id=0;
            if(!$ready){
                $error='Commerce migration 034 is required.';
            } else {
                $id=max(0,(int)($_POST['id']??0));
                $status=(string)($_POST['status']??'pending');
                $payment=(string)($_POST['payment_status']??'pending');
                $fulfillment=(string)($_POST['fulfillment_status']??'unfulfilled');
                $note=substr(trim((string)($_POST['admin_note']??'')),0,2000);
                if($id<=0){
                    $error='Order not found.';
                } else {
                    $eventPayload=null;$eventUserId=null;$dispatch=[];
                    try{
                        $db->beginTransaction();
                        $currentStmt=$db->prepare('SELECT o.id,o.order_number,o.user_id,o.guest_email,o.status,o.payment_status,o.fulfillment_status,o.currency,o.grand_total,(SELECT p.method FROM store_payments p WHERE p.order_id=o.id ORDER BY p.id DESC LIMIT 1) AS payment_method FROM store_orders o WHERE o.id=? FOR UPDATE');
                        $currentStmt->execute([$id]);
                        $current=$currentStmt->fetch();
                        if(!$current)throw new PublicException('Order not found.');
                        $currentStatus=(string)$current['status'];
                        $currentPayment=(string)$current['payment_status'];
                        $currentFulfillment=(string)$current['fulfillment_status'];
                        if($currentStatus!=='cancelled'&&$status==='cancelled'&&!\admin_require_password_from_post($error)){
                            throw new PublicException($error);
                        }
                        ServiceFactory::orderState()->validate($currentStatus,$status,$payment,$fulfillment,$currentFulfillment,$currentPayment,(string)($current['payment_method']??'cod'));

                        if($currentStatus!=='cancelled'&&$status==='cancelled'){
                            $items=$db->prepare('SELECT variant_id,quantity FROM store_order_items WHERE order_id=? AND variant_id IS NOT NULL FOR UPDATE');
                            $items->execute([$id]);
                            $stock=$db->prepare('UPDATE store_product_variants SET stock_qty=stock_qty+? WHERE id=?');
                            $movement=$db->prepare("INSERT INTO store_inventory_movements(variant_id,quantity_delta,movement_type,reference_type,reference_id,note,created_by) VALUES(?,?,'order_cancel_restock','order',?,'Automatic restock after pre-shipment order cancellation',?)");
                            $adminId=(int)($_SESSION['user_id']??0);
                            foreach($items->fetchAll() as $item){
                                $variantId=(int)$item['variant_id'];$qty=(int)$item['quantity'];
                                if($variantId<=0||$qty<=0)continue;
                                $stock->execute([$qty,$variantId]);
                                if($stock->rowCount()!==1)throw new PublicException('A product variant from this order no longer exists. Inventory was not changed.');
                                $movement->execute([$variantId,$qty,(string)$id,$adminId>0?$adminId:null]);
                            }
                        }

                        $stmt=$db->prepare("UPDATE store_orders SET status=?,payment_status=?,fulfillment_status=?,admin_note=?,cancelled_at=CASE WHEN ?='cancelled' THEN COALESCE(cancelled_at,UTC_TIMESTAMP()) ELSE cancelled_at END,completed_at=CASE WHEN ?='completed' THEN COALESCE(completed_at,UTC_TIMESTAMP()) ELSE completed_at END WHERE id=?");
                        $stmt->execute([$status,$payment,$fulfillment,$note,$status,$status,$id]);
                        $pay=$db->prepare("UPDATE store_payments SET status=?,paid_at=CASE WHEN ?='paid' THEN COALESCE(paid_at,UTC_TIMESTAMP()) ELSE paid_at END,refunded_at=CASE WHEN ?='refunded' THEN COALESCE(refunded_at,UTC_TIMESTAMP()) ELSE refunded_at END WHERE order_id=?");
                        $pay->execute([$payment,$payment,$payment,$id]);
                        $h=$db->prepare('INSERT INTO store_order_status_history(order_id,status,payment_status,fulfillment_status,note,changed_by) VALUES(?,?,?,?,?,?)');
                        $h->execute([$id,$status,$payment,$fulfillment,$note!==''?$note:null,(int)($_SESSION['user_id']??0)]);
                        // Keep the security audit in the same transaction as the order
                        // mutation. If audit persistence fails, the order/history/stock
                        // mutation rolls back instead of reporting a false failure after commit.
                        \admin_audit('store_order_updated','Order #'.$id.' -> '.$status.' / '.$payment.' / '.$fulfillment);
                        $db->commit();
                        if($currentStatus!=='cancelled'&&$status==='cancelled')\storefront_cache_clear();

                        $eventUserId=(int)($current['user_id']??0);if($eventUserId<=0)$eventUserId=null;
                        $eventPayload=['order_id'=>$id,'order_number'=>(string)$current['order_number'],'guest_email'=>(string)($current['guest_email']??''),'status'=>$status,'payment_status'=>$payment,'fulfillment_status'=>$fulfillment,'previous_status'=>$currentStatus,'previous_payment_status'=>$currentPayment,'previous_fulfillment_status'=>$currentFulfillment,'grand_total'=>(float)$current['grand_total'],'currency'=>(string)$current['currency']];
                        if($status!==$currentStatus||$payment!==$currentPayment||$fulfillment!==$currentFulfillment)$dispatch[]='order.status_changed';
                        if(!in_array($currentFulfillment,['shipped','out_for_delivery'],true)&&in_array($fulfillment,['shipped','out_for_delivery'],true))$dispatch[]='order.shipped';
                        if(!in_array($currentPayment,['refunded','partially_refunded'],true)&&in_array($payment,['refunded','partially_refunded'],true))$dispatch[]='order.refunded';

                        $message=$status==='cancelled'&&$currentStatus!=='cancelled'?'Order cancelled before shipment and inventory restored.':'Order updated.';
                    } catch(PublicException $e){
                        if($db->inTransaction())$db->rollBack();
                        $error=$e->getMessage();
                    } catch(\Throwable $e){
                        if($db->inTransaction())$db->rollBack();
                        $error=\web_safe_exception_message($e,'store_order_update','The order could not be updated.');
                    }
                    if($error===''&&$eventPayload!==null){
                        foreach(array_values(array_unique($dispatch)) as $eventKey){
                            try{ServiceFactory::events($db)->dispatch(new DomainEvent($eventKey,$eventUserId,(int)($_SESSION['user_id']??0),$eventPayload));}
                            catch(\Throwable $e){\App\Support\Logger::warning('Order event dispatch failed',['order_id'=>$id,'event_key'=>$eventKey,'message'=>$e->getMessage()]);}
                        }
                    }
                }
            }
            $redirectQuery=\admin_preserved_query(['q','status','payment','method','fulfillment','page'],['order'=>$id>0?$id:null]);
            \web_post_redirect('admin/orders.php',self::FLASH,$message,$error,$redirectQuery);
        }

        $flash=\web_flash_take(self::FLASH);$orders=[];$itemsByOrder=[];
        $query=substr(trim((string)($_GET['q']??'')),0,120);$status=trim((string)($_GET['status']??''));$payment=trim((string)($_GET['payment']??''));$paymentMethod=trim((string)($_GET['method']??''));$fulfillment=trim((string)($_GET['fulfillment']??''));$page=max(1,(int)($_GET['page']??1));$perPage=40;$total=0;$totalPages=1;
        if($ready){
            $where=[];$args=[];
            if($query!==''){$where[]='(order_number LIKE ? OR customer_name LIKE ? OR customer_phone LIKE ? OR guest_email LIKE ?)';$like='%'.$query.'%';array_push($args,$like,$like,$like,$like);}
            if($status==='in_progress'){$where[]="status IN ('pending','confirmed','processing')";}elseif(in_array($status,['pending','confirmed','processing','completed','cancelled'],true)){$where[]='status=?';$args[]=$status;}else{$status='';}
            if(in_array($payment,['pending','paid','failed','refunded','partially_refunded'],true)){$where[]='payment_status=?';$args[]=$payment;}else{$payment='';}
            if(in_array($paymentMethod,['cod','manual_transfer'],true)){$where[]='EXISTS(SELECT 1 FROM store_payments filter_payment WHERE filter_payment.order_id=store_orders.id AND filter_payment.method=?)';$args[]=$paymentMethod;}else{$paymentMethod='';}
            if(in_array($fulfillment,['unfulfilled','preparing','shipped','out_for_delivery','fulfilled','returned'],true)){$where[]='fulfillment_status=?';$args[]=$fulfillment;}else{$fulfillment='';}
            $whereSql=$where?' WHERE '.implode(' AND ',$where):'';
            $count=$db->prepare('SELECT COUNT(*) FROM store_orders'.$whereSql);$count->execute($args);$total=(int)$count->fetchColumn();$totalPages=max(1,(int)ceil($total/$perPage));if($page>$totalPages)$page=$totalPages;$offset=($page-1)*$perPage;
            $stmt=$db->prepare('SELECT o.*,(SELECT p.method FROM store_payments p WHERE p.order_id=o.id ORDER BY p.id DESC LIMIT 1) AS payment_method FROM store_orders o'.$whereSql.' ORDER BY placed_at DESC,id DESC LIMIT '.$perPage.' OFFSET '.$offset);$stmt->execute($args);$orders=$stmt->fetchAll();
            $ids=array_values(array_filter(array_map(static fn($row)=>(int)($row['id']??0),$orders)));
            if($ids){
                $placeholders=implode(',',array_fill(0,count($ids),'?'));
                $itemStmt=$db->prepare("SELECT * FROM store_order_items WHERE order_id IN ({$placeholders}) ORDER BY order_id,id");
                $itemStmt->execute($ids);
                foreach($itemStmt->fetchAll() as $item)$itemsByOrder[(int)$item['order_id']][]=$item;
            }
        }
        return ['ready'=>$ready,'orders'=>$orders,'itemsByOrder'=>$itemsByOrder,'message'=>$flash['message'],'error'=>$flash['error'],'query'=>$query,'statusFilter'=>$status,'paymentFilter'=>$payment,'paymentMethodFilter'=>$paymentMethod,'fulfillmentFilter'=>$fulfillment,'page'=>$page,'perPage'=>$perPage,'total'=>$total,'totalPages'=>$totalPages];
    }

    private function exists(\PDO $db,string $table):bool
    {
        $s=$db->prepare('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?');
        $s->execute([$table]);
        return (int)$s->fetchColumn()===1;
    }
}
