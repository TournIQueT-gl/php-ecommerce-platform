<?php
declare(strict_types=1);

namespace App\Repositories;

use PDO;
use App\Support\DepartmentVisibility;
use App\Support\VariantOffer;

final class CommerceRepository
{
    public function __construct(private PDO $db) {}

    public function schemaReady(): bool
    {
        return $this->checkoutSchemaReady() && $this->wishlistSchemaReady() && $this->reviewsSchemaReady();
    }

    public function cartSchemaReady(): bool
    {
        return $this->tablesReady(['store_products','store_product_variants']);
    }

    public function checkoutSchemaReady(): bool
    {
        return $this->tablesReady(['store_products','store_product_variants','store_orders','store_order_items','store_payments','store_inventory_movements','store_order_status_history','store_coupons','store_coupon_redemptions','shipping_zones']) && $this->columnReady('store_orders','guest_access_hash');
    }

    public function reviewsSchemaReady(): bool
    {
        return $this->tablesReady(['users','store_products','store_orders','store_order_items','store_reviews']);
    }

    public function wishlistSchemaReady(): bool
    {
        try {
            if(!$this->tablesReady(['users','store_products','store_wishlists','store_wishlist_items']))return false;
            $cols=$this->db->query("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND ((TABLE_NAME='store_wishlists' AND COLUMN_NAME IN ('id','user_id','created_at')) OR (TABLE_NAME='store_wishlist_items' AND COLUMN_NAME IN ('wishlist_id','product_id','created_at')))");
            return (int)$cols->fetchColumn()===6;
        } catch (\Throwable) {
            return false;
        }
    }

    private function columnReady(string $table,string $column): bool
    {
        try{$s=$this->db->prepare('SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=?');$s->execute([$table,$column]);return (int)$s->fetchColumn()===1;}catch(\Throwable){return false;}
    }

    private function tablesReady(array $tables): bool
    {
        if(!$tables)return true;
        try{
            $placeholders=implode(',',array_fill(0,count($tables),'?'));
            $stmt=$this->db->prepare("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME IN ({$placeholders})");
            $stmt->execute(array_values($tables));$count=(int)$stmt->fetchColumn();$stmt->closeCursor();
            return $count===count($tables);
        }catch(\Throwable){return false;}
    }

    public function cartSnapshot(array $cart,bool $forUpdate=false): array
    {
        $rows=[];
        $sql="SELECT p.id AS product_id,p.name,p.slug,p.main_image_path,p.status AS product_status,b.name AS brand_name,
                    p.published_at,
                    v.id AS variant_id,v.label,v.sku,v.cost_price,v.price,v.sale_price,v.currency,v.stock_qty,v.status AS variant_status,
                    v.stock_qty AS available_qty
             FROM store_products p
             JOIN store_product_variants v ON v.product_id=p.id
             LEFT JOIN store_brands b ON b.id=p.brand_id
             WHERE p.id=? AND v.id=? AND ".DepartmentVisibility::product('p')." LIMIT 1".($forUpdate?' FOR UPDATE':'');
        $stmt=$this->db->prepare($sql);
        foreach($cart as $key=>$item){
            $productId=(int)($item['product_id']??0);$variantId=(int)($item['variant_id']??0);
            if($productId<=0||$variantId<=0)continue;
            $stmt->execute([$productId,$variantId]);$row=$stmt->fetch();
            if(!is_array($row))continue;
            $publishedAt=!empty($row['published_at'])?\utc_epoch_from_db((string)$row['published_at']):null;
            if((string)$row['product_status']!=='active'||(string)$row['variant_status']!=='active'||($publishedAt!==null&&$publishedAt>time()))continue;
            $qty=max(1,min(10,(int)($item['quantity']??1)));
            $sale=(float)($row['sale_price']??0);$regular=(float)($row['price']??0);$unit=($sale>0&&$sale<$regular)?$sale:$regular;
            $rows[(string)$key]=[
                'key'=>(string)$key,
                'slug'=>(string)$row['slug'],
                'product_id'=>(int)$row['product_id'],
                'variant_id'=>(int)$row['variant_id'],
                'name'=>(string)$row['name'],
                'brand'=>(string)($row['brand_name']??''),
                'label'=>(string)$row['label'],
                'sku'=>(string)$row['sku'],
                'unit_cost'=>$row['cost_price']!==null?(float)$row['cost_price']:null,
                'unit_price'=>$unit,
                'currency'=>(string)($row['currency']??'EGP'),
                'quantity'=>$qty,
                'available_qty'=>(int)($row['available_qty']??0),
                'product_status'=>(string)$row['product_status'],
                'published_at'=>$row['published_at']??null,
                'variant_status'=>(string)$row['variant_status'],
                'image'=>(string)($row['main_image_path']??''),
            ];
        }
        return $rows;
    }

    public function wishlistProductIdBySlug(string $slug): ?int
    {
        $stmt=$this->db->prepare("SELECT p.id FROM store_products p WHERE p.slug=? AND p.status='active' AND (p.published_at IS NULL OR p.published_at<=UTC_TIMESTAMP()) AND ".DepartmentVisibility::product('p')." LIMIT 1");
        $stmt->execute([$slug]);$id=$stmt->fetchColumn();return $id!==false?(int)$id:null;
    }

    public function wishlistItems(int $userId): array
    {
        $stmt=$this->db->prepare("SELECT p.*,b.name AS brand_name,
          ".VariantOffer::sql('p','price')." AS price,
          ".VariantOffer::sql('p','regular_price')." AS regular_price,
          ".VariantOffer::sql('p','currency')." AS currency,
          (SELECT SUM(GREATEST(v3.stock_qty,0)) FROM store_product_variants v3 WHERE v3.product_id=p.id AND v3.status='active') AS available_stock,
          (SELECT COUNT(*) FROM store_product_variants v4 WHERE v4.product_id=p.id AND v4.status='active') AS active_variant_count,
          (SELECT v5.label FROM store_product_variants v5 WHERE v5.product_id=p.id AND v5.status='active' ORDER BY v5.sort_order,v5.id LIMIT 1) AS first_available_variant_label
          FROM store_wishlist_items wi
          JOIN store_wishlists w ON w.id=wi.wishlist_id
          JOIN store_products p ON p.id=wi.product_id
          LEFT JOIN store_brands b ON b.id=p.brand_id
          WHERE w.user_id=? AND p.status='active' AND (p.published_at IS NULL OR p.published_at<=UTC_TIMESTAMP()) AND ".DepartmentVisibility::product('p')." ORDER BY wi.created_at DESC");
        $stmt->execute([$userId]);return $stmt->fetchAll();
    }

    public function addWishlist(int $userId,int $productId): void
    {
        $this->db->beginTransaction();
        try{
            $product=$this->db->prepare("SELECT p.id FROM store_products p WHERE p.id=? AND p.status='active' AND (p.published_at IS NULL OR p.published_at<=UTC_TIMESTAMP()) AND ".DepartmentVisibility::product('p')." LIMIT 1 FOR UPDATE");
            $product->execute([$productId]);
            $productExists=(bool)$product->fetchColumn();$product->closeCursor();
            if(!$productExists)throw new \RuntimeException('Wishlist product is not publicly available.');
            $q=$this->db->prepare('SELECT id FROM store_wishlists WHERE user_id=? LIMIT 1 FOR UPDATE');
            $q->execute([$userId]);
            $wishlistId=(int)($q->fetchColumn()?:0);
            if($wishlistId<=0){
                try{
                    $s=$this->db->prepare('INSERT INTO store_wishlists(user_id) VALUES(?)');
                    $s->execute([$userId]);
                    $wishlistId=(int)$this->db->lastInsertId();
                }catch(\PDOException $e){
                    // A concurrent request may create the one-per-user wishlist first.
                    if((string)$e->getCode()!=='23000')throw $e;
                    $q=$this->db->prepare('SELECT id FROM store_wishlists WHERE user_id=? LIMIT 1 FOR UPDATE');
                    $q->execute([$userId]);
                    $wishlistId=(int)($q->fetchColumn()?:0);
                }
            }
            if($wishlistId<=0)throw new \RuntimeException('Wishlist record could not be resolved.');
            $i=$this->db->prepare('INSERT IGNORE INTO store_wishlist_items(wishlist_id,product_id) VALUES(?,?)');
            $i->execute([$wishlistId,$productId]);
            $this->db->commit();
        }catch(\Throwable $e){if($this->db->inTransaction())$this->db->rollBack();throw $e;}
    }

    public function removeWishlist(int $userId,int $productId): void
    {
        $s=$this->db->prepare('DELETE wi FROM store_wishlist_items wi JOIN store_wishlists w ON w.id=wi.wishlist_id WHERE w.user_id=? AND wi.product_id=?');$s->execute([$userId,$productId]);
    }

    public function isWishlisted(int $userId,int $productId): bool
    {
        $s=$this->db->prepare('SELECT 1 FROM store_wishlist_items wi JOIN store_wishlists w ON w.id=wi.wishlist_id WHERE w.user_id=? AND wi.product_id=? LIMIT 1');$s->execute([$userId,$productId]);return (bool)$s->fetchColumn();
    }

    public function wishlistedProductIds(int $userId,array $productIds): array
    {
        $ids=array_values(array_unique(array_filter(array_map('intval',$productIds),static fn(int $id):bool=>$id>0)));
        if(!$ids)return [];
        $ids=array_slice($ids,0,100);
        $placeholders=implode(',',array_fill(0,count($ids),'?'));
        $params=array_merge([$userId],$ids);
        $s=$this->db->prepare("SELECT wi.product_id FROM store_wishlist_items wi JOIN store_wishlists w ON w.id=wi.wishlist_id JOIN store_products p ON p.id=wi.product_id WHERE w.user_id=? AND wi.product_id IN ({$placeholders}) AND p.status='active' AND (p.published_at IS NULL OR p.published_at<=UTC_TIMESTAMP()) AND ".DepartmentVisibility::product('p'));
        $s->execute($params);
        return array_values(array_map('intval',$s->fetchAll(\PDO::FETCH_COLUMN)));
    }

    public function productReviews(int $productId,int $limit=20): array
    {
        $limit=max(1,min(100,$limit));
        $s=$this->db->prepare("SELECT r.*,u.username FROM store_reviews r JOIN users u ON u.id=r.user_id WHERE r.product_id=? AND r.status='approved' ORDER BY r.created_at DESC,r.id DESC LIMIT {$limit}");$s->execute([$productId]);return $s->fetchAll();
    }

    public function reviewSummary(int $productId): array
    {
        $s=$this->db->prepare("SELECT COUNT(*) review_count,ROUND(AVG(rating),1) rating_avg FROM store_reviews WHERE product_id=? AND status='approved'");$s->execute([$productId]);$r=$s->fetch()?:[];return ['count'=>(int)($r['review_count']??0),'average'=>(float)($r['rating_avg']??0)];
    }

    public function verifiedPurchaseOrderId(int $userId,int $productId): ?int
    {
        $s=$this->db->prepare("SELECT o.id FROM store_order_items oi JOIN store_orders o ON o.id=oi.order_id WHERE o.user_id=? AND oi.product_id=? AND o.status<>'cancelled' AND o.payment_status IN ('paid','partially_refunded','refunded') AND (o.fulfillment_status='fulfilled' OR o.status='completed') ORDER BY o.placed_at DESC,o.id DESC LIMIT 1");
        $s->execute([$userId,$productId]);$id=$s->fetchColumn();return $id!==false?(int)$id:null;
    }

    public function upsertReview(int $productId,int $userId,int $orderId,int $rating,?string $title,?string $body): void
    {
        $s=$this->db->prepare("INSERT INTO store_reviews(product_id,user_id,order_id,rating,title,body,status) VALUES(?,?,?,?,?,?,'pending') ON DUPLICATE KEY UPDATE order_id=VALUES(order_id),rating=VALUES(rating),title=VALUES(title),body=VALUES(body),status='pending',updated_at=CURRENT_TIMESTAMP");
        $s->execute([$productId,$userId,$orderId,$rating,$title,$body]);
    }


    public function shippingZones(): array
    {
        try{
            return $this->db->query("SELECT id,name,governorate,fee,free_shipping_threshold,cod_enabled,estimated_days_min,estimated_days_max,sort_order FROM shipping_zones WHERE status='active' AND governorate IS NOT NULL AND TRIM(governorate)<>'' ORDER BY sort_order,name,id")->fetchAll();
        }catch(\Throwable){return [];}
    }

    public function shippingQuote(string $governorate,float $subtotal,array $settings): array
    {
        $governorate=trim($governorate);$row=null;$hasSpecificZones=false;
        try{
            $specific=$this->db->query("SELECT COUNT(*) FROM shipping_zones WHERE status='active' AND governorate IS NOT NULL AND TRIM(governorate)<>''");
            $hasSpecificZones=(int)$specific->fetchColumn()>0;
            $s=$this->db->prepare("SELECT * FROM shipping_zones WHERE status='active' AND LOWER(TRIM(governorate))=LOWER(?) ORDER BY sort_order,id LIMIT 1");
            $s->execute([$governorate]);$found=$s->fetch();if(is_array($found))$row=$found;
            if($row===null){
                $fallback=$this->db->query("SELECT * FROM shipping_zones WHERE status='active' AND (governorate IS NULL OR TRIM(governorate)='') ORDER BY sort_order,id LIMIT 1")->fetch();
                if(is_array($fallback))$row=$fallback;
            }
        }catch(\Throwable){}
        if($row===null&&$hasSpecificZones){
            return ['covered'=>false,'fee'=>0.0,'cod_enabled'=>false,'zone_id'=>null,'zone_name'=>'','days_min'=>null,'days_max'=>null];
        }
        $standardFee=$row!==null?(float)$row['fee']:max(0,(float)($settings['standard_shipping_fee']??0));
        $fee=$standardFee;
        $threshold=$row!==null&&$row['free_shipping_threshold']!==null?(float)$row['free_shipping_threshold']:max(0,(float)($settings['free_shipping_threshold']??0));
        $freeShippingApplied=$threshold>0&&$subtotal>=$threshold;
        if($freeShippingApplied)$fee=0.0;
        $codEnabled=$row!==null?(bool)$row['cod_enabled']:(($settings['cod_enabled']??'1')==='1');
        return ['covered'=>true,'fee'=>$fee,'standard_fee'=>$standardFee,'free_shipping_threshold'=>$threshold,'free_shipping_applied'=>$freeShippingApplied,'cod_enabled'=>$codEnabled,'zone_id'=>$row!==null?(int)$row['id']:null,'zone_name'=>$row!==null?(string)$row['name']:'Standard delivery','days_min'=>$row!==null?($row['estimated_days_min']!==null?(int)$row['estimated_days_min']:null):null,'days_max'=>$row!==null?($row['estimated_days_max']!==null?(int)$row['estimated_days_max']:null):null];
    }

    public function couponByCode(string $code): ?array
    {
        $s=$this->db->prepare("SELECT * FROM store_coupons WHERE code=? AND status='active' LIMIT 1");$s->execute([$code]);$r=$s->fetch();return is_array($r)?$r:null;
    }

    public function couponForUpdate(string $code): ?array
    {
        $s=$this->db->prepare("SELECT * FROM store_coupons WHERE code=? AND status='active' LIMIT 1 FOR UPDATE");$s->execute([$code]);$r=$s->fetch();return is_array($r)?$r:null;
    }

    public function couponUsageCount(int $couponId): int
    {
        $s=$this->db->prepare("SELECT COUNT(*) FROM store_coupon_redemptions r JOIN store_orders o ON o.id=r.order_id WHERE r.coupon_id=? AND o.status<>'cancelled'");$s->execute([$couponId]);return (int)$s->fetchColumn();
    }

    public function couponUserUsageCount(int $couponId,int $userId): int
    {
        $s=$this->db->prepare("SELECT COUNT(*) FROM store_coupon_redemptions r JOIN store_orders o ON o.id=r.order_id WHERE r.coupon_id=? AND r.user_id=? AND o.status<>'cancelled'");$s->execute([$couponId,$userId]);return (int)$s->fetchColumn();
    }

    public function insertOrder(array $o): int
    {
        $s=$this->db->prepare('INSERT INTO store_orders(order_number,user_id,guest_email,guest_access_hash,customer_name,customer_phone,shipping_address_json,status,payment_status,fulfillment_status,currency,subtotal,discount_total,shipping_total,tax_total,grand_total,coupon_code,customer_note) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
        $s->execute([$o['order_number'],$o['user_id'],$o['guest_email'],$o['guest_access_hash']??null,$o['customer_name'],$o['customer_phone'],$o['shipping_address_json'],'pending','pending','unfulfilled',$o['currency'],$o['subtotal'],$o['discount_total'],$o['shipping_total'],0,$o['grand_total'],$o['coupon_code'],$o['customer_note']]);
        return (int)$this->db->lastInsertId();
    }

    public function insertOrderItem(int $orderId,array $item): int
    {
        $s=$this->db->prepare('INSERT INTO store_order_items(order_id,product_id,variant_id,product_name,brand_name,variant_label,sku,image_path,quantity,unit_price,unit_cost,discount_total,line_total) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?)');
        $s->execute([$orderId,$item['product_id'],$item['variant_id'],$item['name'],$item['brand'],$item['label'],$item['sku'],$item['image'],$item['quantity'],$item['unit_price'],$item['unit_cost'],0,$item['unit_price']*$item['quantity']]);
        return (int)$this->db->lastInsertId();
    }

    public function decrementStock(int $variantId,int $qty,int $orderId): void
    {
        $s=$this->db->prepare("UPDATE store_product_variants SET stock_qty=stock_qty-? WHERE id=? AND status='active' AND stock_qty>=?");$s->execute([$qty,$variantId,$qty]);
        if($s->rowCount()!==1)throw new \RuntimeException('Stock changed while the order was being placed.');
        $m=$this->db->prepare("INSERT INTO store_inventory_movements(variant_id,quantity_delta,movement_type,reference_type,reference_id,note) VALUES(?,?,'sale','order',?,'Stock deducted when order was placed')");$m->execute([$variantId,-$qty,(string)$orderId]);
    }

    public function insertPayment(int $orderId,float $amount,string $currency,string $method='cod',?string $idempotencyKey=null): void
    {
        $method=in_array($method,['cod','manual_transfer'],true)?$method:'cod';
        $provider=$method==='manual_transfer'?'manual':'cash_on_delivery';
        $idempotencyKey=trim((string)$idempotencyKey);
        $s=$this->db->prepare("INSERT INTO store_payments(order_id,provider,method,amount,currency,status,idempotency_key) VALUES(?,?,?,?,?,'pending',?)");
        $s->execute([$orderId,$provider,$method,$amount,$currency,$idempotencyKey!==''?$idempotencyKey:null]);
    }

    public function orderByPaymentIdempotency(string $idempotencyKey): ?array
    {
        $idempotencyKey=trim($idempotencyKey);if($idempotencyKey==='')return null;
        $s=$this->db->prepare("SELECT o.*,p.method AS payment_method FROM store_payments p JOIN store_orders o ON o.id=p.order_id WHERE p.idempotency_key=? LIMIT 1");
        $s->execute([$idempotencyKey]);$row=$s->fetch();return is_array($row)?$row:null;
    }

    public function insertCouponRedemption(int $couponId,int $orderId,?int $userId,float $discount): void
    {
        $s=$this->db->prepare('INSERT INTO store_coupon_redemptions(coupon_id,order_id,user_id,discount_amount) VALUES(?,?,?,?)');$s->execute([$couponId,$orderId,$userId,$discount]);
    }

    public function insertOrderHistory(int $orderId,string $note='Order placed via storefront'): void
    {
        $s=$this->db->prepare("INSERT INTO store_order_status_history(order_id,status,payment_status,fulfillment_status,note) VALUES(?,'pending','pending','unfulfilled',?)");$s->execute([$orderId,$note]);
    }

    public function orderNumberExists(string $number): bool
    {
        $s=$this->db->prepare('SELECT 1 FROM store_orders WHERE order_number=? LIMIT 1');$s->execute([$number]);return (bool)$s->fetchColumn();
    }

    public function guestOrderByAccess(string $orderNumber,string $token): ?array
    {
        $orderNumber=trim($orderNumber);$token=trim($token);if($orderNumber===''||$token==='')return null;
        $s=$this->db->prepare('SELECT * FROM store_orders WHERE order_number=? AND user_id IS NULL LIMIT 1');$s->execute([$orderNumber]);$row=$s->fetch();if(!is_array($row))return null;
        $stored=(string)($row['guest_access_hash']??'');$candidate=hash('sha256',$token);
        return $stored!==''&&hash_equals($stored,$candidate)?$row:null;
    }

    public function claimGuestOrderByAccess(string $orderNumber,string $token,int $userId,?string $expectedEmail=null): bool
    {
        $row=$this->guestOrderByAccess($orderNumber,$token);if(!$row||$userId<=0)return false;
        $expectedEmail=strtolower(trim((string)$expectedEmail));$guestEmail=strtolower(trim((string)($row['guest_email']??'')));
        if($expectedEmail!==''&&$guestEmail!==''&&!hash_equals($guestEmail,$expectedEmail))return false;
        $s=$this->db->prepare('UPDATE store_orders SET user_id=?,guest_access_hash=NULL WHERE id=? AND user_id IS NULL');
        $s->execute([$userId,(int)$row['id']]);return $s->rowCount()===1;
    }

    public function claimGuestOrdersByVerifiedEmail(int $userId,string $email): int
    {
        $email=strtolower(trim($email));if($userId<=0||!filter_var($email,FILTER_VALIDATE_EMAIL))return 0;
        $s=$this->db->prepare('UPDATE store_orders SET user_id=?,guest_access_hash=NULL WHERE user_id IS NULL AND LOWER(TRIM(guest_email))=?');
        $s->execute([$userId,$email]);return $s->rowCount();
    }
}
