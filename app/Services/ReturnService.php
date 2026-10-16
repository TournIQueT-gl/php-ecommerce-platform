<?php
declare(strict_types=1);
namespace App\Services;

use App\Events\DomainEvent;
use App\Events\EventDispatcher;
use App\Support\PublicException;
use PDO;

final class ReturnService
{
    public function __construct(private PDO $db,private InventoryService $inventory,private ?EventDispatcher $events=null) {}

    public function request(int $userId,int $orderId,array $quantities,string $reason,string $note=''): int
    {
        $reason=trim($reason);$note=trim($note);
        if($userId<=0||$orderId<=0)throw new PublicException('Order not found.');
        if($reason===''||mb_strlen($reason)>500)throw new PublicException('Tell us why you want to return these items.');
        if(mb_strlen($note)>1000)throw new PublicException('Return note is too long.');
        $this->db->beginTransaction();
        try{
            $o=$this->db->prepare("SELECT o.id,o.order_number,o.user_id,o.status,o.payment_status,o.fulfillment_status,o.grand_total,o.currency,o.completed_at,o.placed_at,(SELECT MIN(h.created_at) FROM store_order_status_history h WHERE h.order_id=o.id AND (h.fulfillment_status='fulfilled' OR h.status='completed')) AS delivered_at FROM store_orders o WHERE o.id=? FOR UPDATE");$o->execute([$orderId]);$order=$o->fetch();
            if(!is_array($order)||(int)$order['user_id']!==$userId)throw new PublicException('Order not found.');
            if((string)$order['status']==='cancelled')throw new PublicException('Cancelled orders cannot be returned.');
            if((string)$order['fulfillment_status']!=='fulfilled'&&(string)$order['status']!=='completed')throw new PublicException('A return can be requested after the order has been delivered.');
            $deliveredAt=(string)($order['delivered_at']?:$order['completed_at']?:$order['placed_at']?:'');$deliveredEpoch=$deliveredAt!==''?\utc_epoch_from_db($deliveredAt):null;$windowDays=$this->returnWindowDays();
            if($deliveredEpoch!==null&&time()>$deliveredEpoch+($windowDays*86400))throw new PublicException('The '.$windowDays.'-day return window for this order has ended.');
            $q=$this->db->prepare('SELECT id,product_name,variant_label,quantity FROM store_order_items WHERE order_id=? ORDER BY id FOR UPDATE');$q->execute([$orderId]);$items=$q->fetchAll();
            if(!$items)throw new PublicException('This order has no returnable items.');
            $previous=$this->db->prepare("SELECT ri.order_item_id,COALESCE(SUM(ri.quantity),0) qty FROM store_return_items ri JOIN store_returns r ON r.id=ri.return_id WHERE r.order_id=? AND r.status IN ('requested','approved','completed') GROUP BY ri.order_item_id");$previous->execute([$orderId]);$used=[];foreach($previous->fetchAll() as $row)$used[(int)$row['order_item_id']]=(int)$row['qty'];
            $selected=[];
            foreach($items as $item){$iid=(int)$item['id'];$max=max(0,(int)$item['quantity']-(int)($used[$iid]??0));$qty=max(0,(int)($quantities[$iid]??0));if($qty>$max)throw new PublicException('Return quantity is higher than the remaining quantity for '.$item['product_name'].'.');if($qty>0)$selected[$iid]=$qty;}
            if(!$selected)throw new PublicException('Choose at least one item to return.');
            $r=$this->db->prepare("INSERT INTO store_returns(order_id,user_id,status,reason,customer_note) VALUES(?,?,'requested',?,?)");$r->execute([$orderId,$userId,$reason,$note!==''?$note:null]);$returnId=(int)$this->db->lastInsertId();
            $ri=$this->db->prepare('INSERT INTO store_return_items(return_id,order_item_id,quantity,restock) VALUES(?,?,?,0)');foreach($selected as $itemId=>$qty)$ri->execute([$returnId,$itemId,$qty]);
            $this->db->commit();
            try{$this->events?->dispatch(new DomainEvent('return.requested',$userId,null,['return_id'=>$returnId,'order_id'=>$orderId,'order_number'=>(string)$order['order_number'],'reason'=>$reason]));}
            catch(\Throwable $e){\App\Support\Logger::warning('Return-requested event dispatch failed',['return_id'=>$returnId,'message'=>$e->getMessage()]);}
            return $returnId;
        }catch(\Throwable $e){if($this->db->inTransaction())$this->db->rollBack();throw $e;}
    }

    public function cancelByCustomer(int $userId,int $returnId): void
    {
        $s=$this->db->prepare("UPDATE store_returns SET status='cancelled',completed_at=UTC_TIMESTAMP() WHERE id=? AND user_id=? AND status='requested'");$s->execute([$returnId,$userId]);if($s->rowCount()!==1)throw new PublicException('Only a pending return request can be cancelled.');
    }

    public function review(int $returnId,string $decision,string $adminNote,int $adminId): void
    {
        if(!in_array($decision,['approved','rejected'],true))throw new PublicException('Choose approve or reject.');
        $adminNote=trim($adminNote);if(mb_strlen($adminNote)>2000)throw new PublicException('Admin note is too long.');
        $this->db->beginTransaction();
        try{
            $s=$this->db->prepare('SELECT r.*,o.order_number,o.user_id AS order_user_id FROM store_returns r JOIN store_orders o ON o.id=r.order_id WHERE r.id=? FOR UPDATE');$s->execute([$returnId]);$r=$s->fetch();if(!is_array($r))throw new PublicException('Return request not found.');
            if((string)$r['status']!=='requested')throw new PublicException('Only a pending return request can be reviewed.');
            $u=$this->db->prepare('UPDATE store_returns SET status=?,admin_note=?,reviewed_at=UTC_TIMESTAMP(),reviewed_by=? WHERE id=?');$u->execute([$decision,$adminNote!==''?$adminNote:null,$adminId,$returnId]);
            $this->db->commit();
            try{$this->events?->dispatch(new DomainEvent('return.'.$decision,(int)($r['user_id']??$r['order_user_id']??0)?:null,$adminId,['return_id'=>$returnId,'order_id'=>(int)$r['order_id'],'order_number'=>(string)$r['order_number']]));}
            catch(\Throwable $e){\App\Support\Logger::warning('Return-review event dispatch failed',['return_id'=>$returnId,'decision'=>$decision,'message'=>$e->getMessage()]);}
        }catch(\Throwable $e){if($this->db->inTransaction())$this->db->rollBack();throw $e;}
    }

    public function complete(int $returnId,float $refundAmount,array $restockOrderItemIds,string $adminNote,int $adminId,string $refundMethod='',string $refundReference=''): array
    {
        if($refundAmount<0)throw new PublicException('Refund amount cannot be negative.');
        $adminNote=trim($adminNote);if(mb_strlen($adminNote)>2000)throw new PublicException('Admin note is too long.');
        $refundMethod=strtolower(trim($refundMethod));$refundReference=trim($refundReference);
        $allowedMethods=['cash','bank_transfer','instapay','wallet','other'];
        if($refundAmount>0){
            if(!in_array($refundMethod,$allowedMethods,true))throw new PublicException('Choose how the refund was paid.');
            if($refundMethod!=='cash'&&$refundReference==='')throw new PublicException('Enter a refund transaction/reference number.');
            if(mb_strlen($refundReference)>160)throw new PublicException('Refund reference is too long.');
        }else{$refundMethod='';$refundReference='';}
        $restockIds=array_fill_keys(array_values(array_unique(array_filter(array_map('intval',$restockOrderItemIds),static fn(int $v):bool=>$v>0))),true);
        $this->db->beginTransaction();
        try{
            $s=$this->db->prepare('SELECT r.*,o.order_number,o.user_id AS order_user_id,o.guest_email,o.grand_total,o.subtotal,o.discount_total,o.currency,o.status AS order_status,o.payment_status,o.fulfillment_status FROM store_returns r JOIN store_orders o ON o.id=r.order_id WHERE r.id=? FOR UPDATE');$s->execute([$returnId]);$r=$s->fetch();if(!is_array($r))throw new PublicException('Return request not found.');
            if((string)$r['status']!=='approved')throw new PublicException('Approve the return before completing it.');
            $grand=(float)$r['grand_total'];
            $priorRefunds=$this->db->prepare("SELECT COALESCE(SUM(refund_amount),0) FROM store_returns WHERE order_id=? AND status='completed' AND id<>?");$priorRefunds->execute([(int)$r['order_id'],$returnId]);$alreadyRefunded=(float)$priorRefunds->fetchColumn();$remainingRefund=max(0.0,$grand-$alreadyRefunded);
            $i=$this->db->prepare('SELECT ri.order_item_id,ri.quantity,oi.variant_id,oi.product_name,oi.quantity AS ordered_quantity,oi.unit_price,oi.line_total FROM store_return_items ri JOIN store_order_items oi ON oi.id=ri.order_item_id WHERE ri.return_id=? AND oi.order_id=? FOR UPDATE');$i->execute([$returnId,(int)$r['order_id']]);$items=$i->fetchAll();if(!$items)throw new PublicException('Return request has no items.');
            $subtotal=max(0.0,(float)$r['subtotal']);$netMerchandise=max(0.0,$subtotal-(float)$r['discount_total']);$discountFactor=$subtotal>0?min(1.0,$netMerchandise/$subtotal):1.0;$selectedValue=0.0;
            foreach($items as $item){$orderedQty=max(1,(int)$item['ordered_quantity']);$line=(float)$item['line_total'];$selectedValue+=($line/$orderedQty)*(int)$item['quantity']*$discountFactor;}
            $maxItemRefund=min($remainingRefund,max(0.0,$selectedValue));
            if($refundAmount>$maxItemRefund+0.005)throw new PublicException('Refund amount exceeds the value of the returned items ('.number_format($maxItemRefund,2).' '.(string)$r['currency'].').');
            $mark=$this->db->prepare('UPDATE store_return_items SET restock=? WHERE return_id=? AND order_item_id=?');
            foreach($items as $item){$itemId=(int)$item['order_item_id'];$restock=isset($restockIds[$itemId]);if($restock){$variantId=(int)($item['variant_id']??0);if($variantId<=0)throw new PublicException('A selected returned item no longer has a stock variant and cannot be restocked automatically.');$this->inventory->adjust($variantId,(int)$item['quantity'],'return_restock','Return #'.$returnId.' completed',$adminId,'return',(string)$returnId);}$mark->execute([$restock?1:0,$returnId,$itemId]);}
            $u=$this->db->prepare("UPDATE store_returns SET status='completed',refund_amount=?,refund_method=?,refund_reference=?,refunded_at=?,admin_note=?,completed_at=UTC_TIMESTAMP(),reviewed_at=COALESCE(reviewed_at,UTC_TIMESTAMP()),reviewed_by=COALESCE(reviewed_by,?) WHERE id=?");
            $u->execute([number_format($refundAmount,2,'.',''),$refundMethod!==''?$refundMethod:null,$refundReference!==''?$refundReference:null,$refundAmount>0?gmdate('Y-m-d H:i:s'):null,$adminNote!==''?$adminNote:null,$adminId,$returnId]);
            $paymentStatus=(string)$r['payment_status'];
            if($refundAmount>0){$cumulativeRefund=$alreadyRefunded+$refundAmount;$paymentStatus=$cumulativeRefund+0.005>=$grand?'refunded':'partially_refunded';$p=$this->db->prepare("UPDATE store_payments SET status=?,refunded_at=UTC_TIMESTAMP() WHERE order_id=?");$p->execute([$paymentStatus,(int)$r['order_id']]);$po=$this->db->prepare('UPDATE store_orders SET payment_status=? WHERE id=?');$po->execute([$paymentStatus,(int)$r['order_id']]);}
            $total=$this->db->prepare('SELECT COALESCE(SUM(quantity),0) FROM store_order_items WHERE order_id=?');$total->execute([(int)$r['order_id']]);$orderedQty=(int)$total->fetchColumn();
            $returned=$this->db->prepare("SELECT COALESCE(SUM(ri.quantity),0) FROM store_return_items ri JOIN store_returns rr ON rr.id=ri.return_id WHERE rr.order_id=? AND rr.status='completed'");$returned->execute([(int)$r['order_id']]);$returnedQty=(int)$returned->fetchColumn();
            $fulfillment=$returnedQty>=$orderedQty&&$orderedQty>0?'returned':(string)$r['fulfillment_status'];
            if($fulfillment==='returned'){$f=$this->db->prepare("UPDATE store_orders SET fulfillment_status='returned' WHERE id=?");$f->execute([(int)$r['order_id']]);}
            $refundNote=$refundAmount>0?' with refund '.number_format($refundAmount,2).' '.(string)$r['currency'].' via '.str_replace('_',' ',$refundMethod):'';
            $hist=$this->db->prepare('INSERT INTO store_order_status_history(order_id,status,payment_status,fulfillment_status,note,changed_by) VALUES(?,?,?,?,?,?)');$hist->execute([(int)$r['order_id'],(string)$r['order_status'],$paymentStatus,$fulfillment,'Return #'.$returnId.' completed'.$refundNote,$adminId]);
            $this->db->commit();
            $subject=(int)($r['user_id']??$r['order_user_id']??0)?:null;$payload=['return_id'=>$returnId,'order_id'=>(int)$r['order_id'],'order_number'=>(string)$r['order_number'],'guest_email'=>(string)($r['guest_email']??''),'refund_amount'=>$refundAmount,'refund_method'=>$refundMethod,'currency'=>(string)$r['currency'],'status'=>(string)$r['order_status'],'fulfillment_status'=>$fulfillment];
            try{$this->events?->dispatch(new DomainEvent('return.completed',$subject,$adminId,$payload));if($refundAmount>0)$this->events?->dispatch(new DomainEvent('order.refunded',$subject,$adminId,$payload));}
            catch(\Throwable $e){\App\Support\Logger::warning('Return-completed event dispatch failed',['return_id'=>$returnId,'message'=>$e->getMessage()]);}
            return ['payment_status'=>$paymentStatus,'fulfillment_status'=>$fulfillment,'max_item_refund'=>$maxItemRefund];
        }catch(\Throwable $e){if($this->db->inTransaction())$this->db->rollBack();throw $e;}
    }

    private function returnWindowDays(): int
    {
        try{$q=$this->db->prepare("SELECT setting_value FROM store_settings WHERE setting_key='return_window_days' LIMIT 1");$q->execute();$value=(int)$q->fetchColumn();if($value>=1&&$value<=90)return $value;}catch(\Throwable){}
        return 14;
    }
}
