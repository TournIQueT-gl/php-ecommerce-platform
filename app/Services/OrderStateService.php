<?php
declare(strict_types=1);

namespace App\Services;

use App\Support\PublicException;

final class OrderStateService
{
    public const ORDER=['pending','confirmed','processing','completed','cancelled'];
    public const PAYMENT=['pending','paid','failed','refunded','partially_refunded'];
    public const FULFILLMENT=['unfulfilled','preparing','shipped','out_for_delivery','fulfilled','returned'];

    public function validate(string $currentStatus,string $status,string $payment,string $fulfillment,string $currentFulfillment='',string $currentPayment='',string $paymentMethod='cod'): void
    {
        if(!in_array($status,self::ORDER,true)||!in_array($payment,self::PAYMENT,true)||!in_array($fulfillment,self::FULFILLMENT,true)){
            throw new PublicException('Invalid order state.');
        }
        if($currentStatus==='cancelled'&&$status!=='cancelled'){
            throw new PublicException('Cancelled orders cannot be reopened. Create a new order instead.');
        }
        if($currentStatus==='completed'&&$status==='cancelled'){
            throw new PublicException('Completed orders cannot be cancelled. Use a verified return/refund workflow instead.');
        }
        $settledPayments=['paid','partially_refunded','refunded'];
        if($status==='completed'&&(!in_array($payment,$settledPayments,true)||!in_array($fulfillment,['fulfilled','returned'],true))){
            throw new PublicException('A completed order must have a settled payment and fulfilled/returned fulfillment.');
        }
        if($paymentMethod==='cod'&&$payment==='paid'&&$fulfillment!=='fulfilled'){
            throw new PublicException('Cash on Delivery can only be marked paid when the order is fulfilled.');
        }
        if($paymentMethod==='manual_transfer'&&!in_array($payment,$settledPayments,true)&&($fulfillment!=='unfulfilled'||in_array($status,['processing','completed'],true))){
            throw new PublicException('Bank transfer / InstaPay must be marked received before preparing or shipping the order.');
        }
        if($currentPayment==='paid'&&!in_array($payment,['paid','refunded','partially_refunded'],true)){
            throw new PublicException('A received payment cannot be moved back to pending or failed from the order editor.');
        }
        if($status==='cancelled'&&$payment==='paid'){
            throw new PublicException('A cancelled order cannot remain paid. Handle the payment through a supported refund workflow before cancelling it.');
        }
        if($fulfillment==='fulfilled'&&$status!=='completed'){
            throw new PublicException('Fulfilled orders must be marked completed.');
        }
        if(in_array($fulfillment,['shipped','out_for_delivery'],true)&&!in_array($status,['confirmed','processing'],true)){
            throw new PublicException('Shipped orders must be confirmed or processing.');
        }
        if($fulfillment==='preparing'&&!in_array($status,['confirmed','processing'],true)){
            throw new PublicException('Preparing orders must be confirmed or processing.');
        }
        if($fulfillment==='returned'){
            if($currentFulfillment!=='returned'){
                throw new PublicException('Returned fulfillment can only be set by the Returns & Refunds workflow.');
            }
            if(!in_array($status,['completed','cancelled'],true)){
                throw new PublicException('Returned orders must remain completed or cancelled.');
            }
        }
        if($payment==='failed'&&in_array($status,['processing','completed'],true)){
            throw new PublicException('An order with failed payment cannot be processing or completed.');
        }
        if(!in_array($currentPayment,['refunded','partially_refunded'],true)&&in_array($payment,['refunded','partially_refunded'],true)){
            throw new PublicException('Refund states can only be created by the Returns & Refunds workflow, where the amount and inventory decision are recorded.');
        }
        if(in_array($currentPayment,['refunded','partially_refunded'],true)&&$payment!==$currentPayment){
            throw new PublicException('Refunded payment states are locked in the order editor. Record any further refund through Returns & Refunds.');
        }
        if(in_array($payment,['refunded','partially_refunded'],true)&&$status==='pending'){
            throw new PublicException('A pending order cannot have a refund state.');
        }
        if($status==='cancelled'&&in_array($fulfillment,['preparing','shipped','out_for_delivery','fulfilled'],true)){
            throw new PublicException('A cancelled order cannot remain in an active fulfillment state.');
        }
        if($status==='cancelled'&&in_array($currentFulfillment,['shipped','out_for_delivery','fulfilled','returned'],true)){
            throw new PublicException('This order has already left pre-shipment fulfillment and cannot be cancelled/restocked from this screen. Use a verified return workflow instead.');
        }

        $rank=['unfulfilled'=>0,'preparing'=>1,'shipped'=>2,'out_for_delivery'=>3,'fulfilled'=>4];
        if(isset($rank[$currentFulfillment],$rank[$fulfillment])&&$rank[$currentFulfillment]>=2&&$rank[$fulfillment]<$rank[$currentFulfillment]){
            throw new PublicException('Fulfillment cannot be moved backward after shipment. Use a dedicated exception/return workflow.');
        }
        if($currentFulfillment==='fulfilled'&&$fulfillment!=='fulfilled'&&$fulfillment!=='returned'){
            throw new PublicException('A fulfilled order cannot be moved back into active delivery states.');
        }
        if($currentPayment==='refunded'&&$payment!=='refunded'){
            throw new PublicException('A fully refunded payment cannot be reopened from the order editor.');
        }
    }
}
