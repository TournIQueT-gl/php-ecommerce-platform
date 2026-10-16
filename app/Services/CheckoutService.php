<?php
declare(strict_types=1);

namespace App\Services;

use App\Events\DomainEvent;
use App\Events\EventDispatcher;
use App\Repositories\CommerceRepository;
use App\Repositories\NotificationRepository;
use App\Support\PublicException;
use PDO;

final class CheckoutService
{
    public function __construct(
        private PDO $db,
        private CommerceRepository $repo,
        private ?EventDispatcher $events=null,
        private ?NotificationRepository $notifications=null
    ) {}

    public function quoteCodOrder(array $input,array $cart,array $settings,?array $user): array
    {
        return $this->buildQuote($input,$cart,$settings,$user,false);
    }

    public function placeCodOrder(array $input,array $cart,array $settings,?array $user,?array $expectedQuote=null,string $idempotencyKey=''): array
    {
        $idempotencyKey=strtolower(trim($idempotencyKey));
        if(!preg_match('/^[a-f0-9]{48}$/',$idempotencyKey))throw new PublicException('This order form expired. Refresh the page and try again.');
        $idempotencyHash=hash('sha256',$idempotencyKey);
        $existing=$this->repo->orderByPaymentIdempotency($idempotencyHash);
        if($existing!==null)return $this->idempotentResult($existing,$idempotencyKey);
        $this->db->beginTransaction();
        try{
            $quote=$this->buildQuote($input,$cart,$settings,$user,true);
            if($expectedQuote!==null&&!$this->quoteMatches($expectedQuote,$quote)){
                throw new PublicException('Your order total changed while you were reviewing it. Review the updated total before confirming the order.');
            }
            $number=$this->newOrderNumber();
            $shipping=$quote['shipping'];
            $address=$quote['address'];
            $addressJson=json_encode([
                'governorate'=>$address['governorate'],'city'=>$address['city'],'address'=>$address['address'],'building'=>$address['building'],
                'shipping_zone'=>$shipping['zone_name'],'estimated_days_min'=>$shipping['days_min'],'estimated_days_max'=>$shipping['days_max']
            ],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
            if(!is_string($addressJson))throw new PublicException('Delivery address could not be saved.');
            // Reuse the one-use 192-bit checkout key as the guest bearer token. Only
            // its SHA-256 hash is persisted, so a DB dump still does not reveal the
            // private tracking credential. It also lets a crash-safe retry reconstruct
            // the same guest access token without storing it in plaintext.
            $guestAccessToken=$quote['user_id']===null?$idempotencyKey:null;
            $orderId=$this->repo->insertOrder([
                'order_number'=>$number,'user_id'=>$quote['user_id'],'guest_email'=>$quote['email']!==''?$quote['email']:null,'guest_access_hash'=>$guestAccessToken!==null?hash('sha256',$guestAccessToken):null,'customer_name'=>$quote['customer_name'],'customer_phone'=>$quote['phone'],'shipping_address_json'=>$addressJson,'currency'=>$quote['currency'],
                'subtotal'=>number_format($quote['subtotal'],2,'.',''),'discount_total'=>number_format($quote['discount_total'],2,'.',''),'shipping_total'=>number_format($quote['shipping_total'],2,'.',''),'grand_total'=>number_format($quote['grand_total'],2,'.',''),'coupon_code'=>$quote['coupon_code']!==''?$quote['coupon_code']:null,'customer_note'=>$quote['customer_note']!==''?$quote['customer_note']:null,
            ]);
            foreach($quote['items'] as $item){$this->repo->insertOrderItem($orderId,$item);$this->repo->decrementStock((int)$item['variant_id'],(int)$item['quantity'],$orderId);}
            $this->repo->insertPayment($orderId,$quote['grand_total'],$quote['currency'],$quote['payment_method'],$idempotencyHash);
            if($quote['coupon']!==null)$this->repo->insertCouponRedemption((int)$quote['coupon']['id'],$orderId,$quote['user_id'],$quote['discount_total']);
            $this->repo->insertOrderHistory($orderId,$quote['payment_method']==='manual_transfer'?'Order placed via storefront · Bank transfer / InstaPay requested':'Order placed via storefront · Cash on Delivery');
            $this->db->commit();
            \storefront_cache_clear();

            $result=$quote+['order_id'=>$orderId,'order_number'=>$number,'guest_access_token'=>$guestAccessToken];
            $eventPayload=[
                'order_id'=>$orderId,'order_number'=>$number,'guest_email'=>$quote['email'],'customer_name'=>$quote['customer_name'],
                'grand_total'=>$quote['grand_total'],'currency'=>$quote['currency'],'payment_method'=>$quote['payment_method'],'status'=>'pending','fulfillment_status'=>'unfulfilled','guest_access_token'=>$guestAccessToken
            ];
            // A new order is operationally urgent. Create the owner/admin in-app
            // notification immediately after the commerce transaction commits. The
            // durable event still handles email/customer delivery and retries.
            try{
                if($this->notifications!==null){
                    $adminPayload=$eventPayload;unset($adminPayload['guest_access_token']);$adminPayload['admin_target']=true;
                    $this->notifications->broadcastToAdminRoles(
                        ['owner','admin'],'order.created','New store order',
                        'Order '.$number.' was placed and needs processing.',
                        $adminPayload,'order-created-admin:'.$orderId
                    );
                }
            }catch(\Throwable $e){\App\Support\Logger::warning('Immediate order notification failed',['order_id'=>$orderId,'message'=>$e->getMessage()]);}
            try{
                $this->events?->dispatch(new DomainEvent('order.created',$quote['user_id'],null,$eventPayload));
            }catch(\Throwable $e){\App\Support\Logger::warning('Order-created event dispatch failed',['order_id'=>$orderId,'message'=>$e->getMessage()]);}
            unset($result['coupon'],$result['user_id'],$result['customer_note']);
            return $result;
        }catch(PublicException $e){if($this->db->inTransaction())$this->db->rollBack();throw $e;}
        catch(\Throwable $e){
            if($this->db->inTransaction())$this->db->rollBack();
            // Two simultaneous submissions can race until the unique payment
            // idempotency key is inserted. After rollback, resolve the winning order
            // instead of surfacing a false failure or creating another order.
            $existing=$this->repo->orderByPaymentIdempotency($idempotencyHash);
            if($existing!==null)return $this->idempotentResult($existing,$idempotencyKey);
            throw $e;
        }
    }

    private function idempotentResult(array $order,string $idempotencyKey): array
    {
        $guest=(int)($order['user_id']??0)<=0;
        return [
            'order_id'=>(int)$order['id'],
            'order_number'=>(string)$order['order_number'],
            'customer_name'=>(string)$order['customer_name'],
            'email'=>(string)($order['guest_email']??''),
            'phone'=>(string)$order['customer_phone'],
            'currency'=>(string)$order['currency'],
            'grand_total'=>(float)$order['grand_total'],
            'payment_method'=>(string)($order['payment_method']??'cod'),
            'guest_access_token'=>$guest?$idempotencyKey:null,
        ];
    }

    public function inputFingerprint(array $input): string
    {
        $normalized=[
            'customer_name'=>trim((string)($input['customer_name']??'')),
            'email'=>strtolower(trim((string)($input['email']??''))),
            'phone'=>trim((string)($input['phone']??'')),
            'governorate'=>trim((string)($input['governorate']??'')),
            'city'=>trim((string)($input['city']??'')),
            'address'=>trim((string)($input['address']??'')),
            'building'=>trim((string)($input['building']??'')),
            'coupon_code'=>strtoupper(substr((string)preg_replace('/[^A-Z0-9_-]/i','',trim((string)($input['coupon_code']??''))),0,80)),
            'customer_note'=>trim((string)($input['customer_note']??'')),
            'payment_method'=>trim((string)($input['payment_method']??'cod')),
        ];
        return hash('sha256',(string)json_encode($normalized,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES));
    }

    private function buildQuote(array $input,array $cart,array $settings,?array $user,bool $forUpdate): array
    {
        if(!$cart)throw new PublicException('Your bag is empty.');
        if((string)($settings['catalog_enabled']??'1')!=='1')throw new PublicException('Checkout is temporarily unavailable.');
        $paymentMethod=trim((string)($input['payment_method']??'cod'));
        if(!in_array($paymentMethod,['cod','manual_transfer'],true))throw new PublicException('Choose a valid payment method.');
        $codEnabled=(string)($settings['cod_enabled']??'1')==='1';
        $manualTransferEnabled=(string)($settings['manual_transfer_enabled']??'1')==='1';
        if($paymentMethod==='cod'&&!$codEnabled)throw new PublicException('Cash on Delivery is currently unavailable.');
        if($paymentMethod==='manual_transfer'&&!$manualTransferEnabled)throw new PublicException('Bank transfer / InstaPay is currently unavailable.');
        $name=trim((string)($input['customer_name']??''));
        $email=strtolower(trim((string)($input['email']??($user['email']??''))));
        $phone=trim((string)($input['phone']??''));
        $governorate=trim((string)($input['governorate']??''));
        $city=trim((string)($input['city']??''));
        $address=trim((string)($input['address']??''));
        $building=trim((string)($input['building']??''));
        $note=trim((string)($input['customer_note']??''));
        if($name===''||mb_strlen($name)>160)throw new PublicException('Enter your full name.');
        if($email!==''&&!filter_var($email,FILTER_VALIDATE_EMAIL))throw new PublicException('Enter a valid email address.');
        $phoneDigits=(string)preg_replace('/\D+/','',$phone);
        if($phone===''||mb_strlen($phone)>40||strlen($phoneDigits)<10||strlen($phoneDigits)>15)throw new PublicException('Enter a valid phone number.');
        if(str_starts_with($phoneDigits,'01')&&strlen($phoneDigits)!==11)throw new PublicException('Enter a valid Egyptian mobile number.');
        if(str_starts_with($phoneDigits,'20')&&strlen($phoneDigits)!==12)throw new PublicException('Enter a valid Egyptian phone number.');
        if($governorate===''||mb_strlen($governorate)>120||!in_array($governorate,\store_egypt_governorates(),true))throw new PublicException('Choose a valid Egyptian governorate.');
        if($city===''||mb_strlen($city)>120)throw new PublicException('Enter your city or area.');
        if($address===''||mb_strlen($address)>500)throw new PublicException('Enter the delivery address.');
        if(mb_strlen($building)>160)throw new PublicException('Building details are too long.');
        if(mb_strlen($note)>1000)throw new PublicException('Order note is too long.');
        $couponCode=strtoupper(substr((string)preg_replace('/[^A-Z0-9_-]/i','',trim((string)($input['coupon_code']??''))),0,80));
        $userId=$user?max(1,(int)$user['id']):null;

        $items=$this->repo->cartSnapshot($cart,$forUpdate);
        if(count($items)!==count($cart))throw new PublicException('One or more items in your bag are no longer available. Refresh your bag and try again.');
        $currency='';$subtotal=0.0;
        foreach($items as $item){
            $publishedAt=!empty($item['published_at'])?\utc_epoch_from_db((string)$item['published_at']):null;
            if($item['product_status']!=='active'||$item['variant_status']!=='active'||($publishedAt!==null&&$publishedAt>time()))throw new PublicException($item['name'].' is no longer available.');
            if($item['available_qty']<$item['quantity'])throw new PublicException($item['name'].' · '.$item['label'].' only has '.$item['available_qty'].' left in stock.');
            if($item['unit_price']<=0)throw new PublicException('A product in your bag has an invalid price.');
            if($currency==='')$currency=strtoupper($item['currency']);
            elseif($currency!==strtoupper($item['currency']))throw new PublicException('All items in one order must use the same currency.');
            $subtotal+=$item['unit_price']*$item['quantity'];
        }
        if($currency==='')$currency=strtoupper((string)($settings['currency']??'EGP'));
        $shipping=$this->repo->shippingQuote($governorate,$subtotal,$settings);
        if(!($shipping['covered']??true))throw new PublicException('Delivery is not available for the selected governorate.');
        if($paymentMethod==='cod'&&!$shipping['cod_enabled'])throw new PublicException('Cash on Delivery is not available for this delivery area. Choose Bank transfer / InstaPay or another delivery area.');
        $shippingTotal=(float)$shipping['fee'];$discount=0.0;$coupon=null;
        if($couponCode!==''){
            $coupon=$forUpdate?$this->repo->couponForUpdate($couponCode):$this->repo->couponByCode($couponCode);
            if(!$coupon)throw new PublicException('That coupon is not active.');
            $now=time();$starts=!empty($coupon['starts_at'])?\utc_epoch_from_db((string)$coupon['starts_at']):null;$ends=!empty($coupon['ends_at'])?\utc_epoch_from_db((string)$coupon['ends_at']):null;
            if($starts!==null&&$starts>$now)throw new PublicException('That coupon is not active yet.');
            if($ends!==null&&$ends<$now)throw new PublicException('That coupon has expired.');
            if($coupon['minimum_order']!==null&&$subtotal<(float)$coupon['minimum_order'])throw new PublicException('Your order does not meet this coupon’s minimum spend.');
            if($coupon['usage_limit']!==null&&$this->repo->couponUsageCount((int)$coupon['id'])>=(int)$coupon['usage_limit'])throw new PublicException('That coupon has reached its usage limit.');
            if($coupon['per_user_limit']!==null){
                if($userId===null)throw new PublicException('Sign in to use this limited coupon.');
                if($this->repo->couponUserUsageCount((int)$coupon['id'],$userId)>=(int)$coupon['per_user_limit'])throw new PublicException('You have already used that coupon the maximum number of times.');
            }
            $type=(string)$coupon['discount_type'];$value=(float)$coupon['discount_value'];
            if($type==='percent'){$discount=$subtotal*min(100,max(0,$value))/100;if($coupon['maximum_discount']!==null)$discount=min($discount,(float)$coupon['maximum_discount']);}
            elseif($type==='fixed')$discount=min($subtotal,max(0,$value));
            elseif($type==='free_shipping')$shippingTotal=0.0;
        }
        $grand=max(0,$subtotal-$discount+$shippingTotal);
        return [
            'items'=>$items,'currency'=>$currency,'subtotal'=>$subtotal,'discount_total'=>$discount,'shipping_total'=>$shippingTotal,'grand_total'=>$grand,
            'customer_name'=>$name,'email'=>$email,'phone'=>$phone,'shipping'=>$shipping,'address'=>['governorate'=>$governorate,'city'=>$city,'address'=>$address,'building'=>$building],
            'coupon_code'=>$couponCode,'coupon'=>$coupon,'customer_note'=>$note,'user_id'=>$userId,'payment_method'=>$paymentMethod,
        ];
    }

    private function quoteMatches(array $expected,array $actual): bool
    {
        foreach(['subtotal','discount_total','shipping_total','grand_total'] as $key){
            if(abs((float)($expected[$key]??-1)-(float)($actual[$key]??-2))>0.005)return false;
        }
        if((string)($expected['currency']??'')!==(string)($actual['currency']??''))return false;
        if((string)($expected['coupon_code']??'')!==(string)($actual['coupon_code']??''))return false;
        if((string)($expected['payment_method']??'cod')!==(string)($actual['payment_method']??'cod'))return false;
        $expectedShipping=is_array($expected['shipping']??null)?$expected['shipping']:[];
        $actualShipping=is_array($actual['shipping']??null)?$actual['shipping']:[];
        $nullableInt=static fn(mixed $value):?int=>$value===null||$value===''?null:(int)$value;
        if($nullableInt($expectedShipping['zone_id']??null)!==$nullableInt($actualShipping['zone_id']??null))return false;
        if((string)($expectedShipping['zone_name']??'')!==(string)($actualShipping['zone_name']??''))return false;
        if(abs((float)($expectedShipping['standard_fee']??-1)-(float)($actualShipping['standard_fee']??-2))>0.005)return false;
        if((bool)($expectedShipping['free_shipping_applied']??false)!==(bool)($actualShipping['free_shipping_applied']??false))return false;
        if($nullableInt($expectedShipping['days_min']??null)!==$nullableInt($actualShipping['days_min']??null))return false;
        if($nullableInt($expectedShipping['days_max']??null)!==$nullableInt($actualShipping['days_max']??null))return false;
        $left=[];$right=[];
        foreach(($expected['items']??[]) as $item)$left[]=[(int)($item['variant_id']??0),(int)($item['quantity']??0),number_format((float)($item['unit_price']??0),2,'.','')];
        foreach(($actual['items']??[]) as $item)$right[]=[(int)($item['variant_id']??0),(int)($item['quantity']??0),number_format((float)($item['unit_price']??0),2,'.','')];
        return $left===$right;
    }

    private function newOrderNumber(): string
    {
        for($i=0;$i<8;$i++){
            $number='ORD'.gmdate('ymd').'-'.strtoupper(substr(bin2hex(random_bytes(4)),0,8));
            if(!$this->repo->orderNumberExists($number))return $number;
        }
        throw new \RuntimeException('Could not allocate a unique order number.');
    }
}
