<?php
declare(strict_types=1);
namespace App\Http\Controllers\Admin;

use App\Support\PublicException;
use App\Support\ServiceFactory;

final class InventoryController
{
    private const FLASH='store_inventory';
    private const PER_PAGE=36;
    private const HISTORY_PER_PAGE=50;
    private const MAX_ADJUSTMENT=1000000;
    private const MAX_BULK_ROWS=100;

    public function handle(): array
    {
        $db=\db();
        $ready=$this->exists($db,'store_product_variants')&&$this->exists($db,'store_inventory_movements')&&$this->exists($db,'store_products');

        if(($_SERVER['REQUEST_METHOD']??'GET')==='GET' && (string)($_GET['_ajax']??'')==='variant-state'){
            if(!$ready)$this->json(['ok'=>false,'message'=>'Commerce migration 034 is required.'],503);
            $variantId=max(0,(int)($_GET['variant_id']??0));
            $variant=$variantId>0?$this->variantSnapshot($db,$variantId):null;
            if(!$variant)$this->json(['ok'=>false,'message'=>'Variant not found.'],404);
            $this->json(['ok'=>true,'variant'=>$variant,'summary'=>$this->summary($db)]);
        }

        if(($_SERVER['REQUEST_METHOD']??'GET')==='POST'){
            $ajax=$this->isAjax();
            try{\verify_csrf();}catch(\Throwable $e){if($ajax)$this->json(['ok'=>false,'message'=>'Your session expired. Refresh the page and try again.'],419);throw $e;}
            $error='';$message='';$updated=[];
            try{
                if(!$ready)throw new PublicException('Commerce migration 034 is required.');
                $action=(string)($_POST['action']??'adjust_variant');
                if($action==='adjust_variant'){
                    [$message,$variantId]=$this->adjustOne($db,$_POST);
                    $updated[]=$this->variantSnapshot($db,$variantId);
                }elseif($action==='bulk_adjust'){
                    [$message,$ids]=$this->bulkAdjust($db,$_POST);
                    foreach($ids as $variantId){$snapshot=$this->variantSnapshot($db,$variantId);if($snapshot)$updated[]=$snapshot;}
                }else throw new PublicException('Unknown inventory action.');
            }catch(\Throwable $e){
                if($db->inTransaction())$db->rollBack();
                $error=$e instanceof PublicException?$e->getMessage():\web_safe_exception_message($e,'inventory_update','Inventory could not be updated.');
            }
            if($ajax){
                if($error!=='')$this->json(['ok'=>false,'message'=>$error],422);
                $this->json(['ok'=>true,'message'=>$message,'variants'=>array_values(array_filter($updated)),'summary'=>$this->summary($db)]);
            }
            $redirectQuery=\admin_preserved_query(['q','stock','brand','category','page','view','movement','history_page']);
            \web_post_redirect('admin/inventory.php',self::FLASH,$message,$error,$redirectQuery);
        }

        $flash=\web_flash_take(self::FLASH);
        $rows=[];$lowStockRows=[];$movements=[];$brands=[];$categories=[];$movementTypes=[];
        $summary=['active'=>0,'healthy'=>0,'low'=>0,'out'=>0];
        $query=mb_substr(trim((string)($_GET['q']??'')),0,120);
        $stockFilter=(string)($_GET['stock']??'');
        $brandFilter=max(0,(int)($_GET['brand']??0));
        $categoryFilter=max(0,(int)($_GET['category']??0));
        $view=(string)($_GET['view']??'stock');if(!in_array($view,['stock','history','orders'],true))$view='stock';
        $movementFilter=substr(trim((string)($_GET['movement']??'')),0,32);
        $page=max(1,(int)($_GET['page']??1));$perPage=self::PER_PAGE;$total=0;$totalPages=1;
        $historyPage=max(1,(int)($_GET['history_page']??1));$historyTotal=0;$historyTotalPages=1;

        if($ready){
            $summary=$this->summary($db);
            $sellable="v.status='active' AND p.status='active' AND (p.published_at IS NULL OR p.published_at<=UTC_TIMESTAMP())";
            $lowStockRows=$db->query("SELECT v.id,v.label,v.sku,v.stock_qty,v.low_stock_threshold,p.id AS product_id,p.name AS product_name,b.name AS brand_name,p.main_image_path FROM store_product_variants v JOIN store_products p ON p.id=v.product_id LEFT JOIN store_brands b ON b.id=p.brand_id WHERE {$sellable} AND v.stock_qty<=v.low_stock_threshold ORDER BY (v.stock_qty<=0) DESC,v.stock_qty ASC,p.name LIMIT 10")->fetchAll();
            $brands=$db->query("SELECT id,name FROM store_brands WHERE status<>'archived' ORDER BY name,id")->fetchAll();
            $categories=$db->query("SELECT id,name FROM store_categories WHERE status<>'archived' ORDER BY name,id")->fetchAll();

            if($view==='stock'){
                $where=["v.status<>'archived'","p.status<>'archived'"];$args=[];
                if($query!==''){$where[]="(p.name LIKE ? ESCAPE '!' OR b.name LIKE ? ESCAPE '!' OR v.sku LIKE ? ESCAPE '!' OR v.label LIKE ? ESCAPE '!')";$like='%'.$this->escapeLike($query).'%';array_push($args,$like,$like,$like,$like);}
                if($brandFilter>0){$where[]='p.brand_id=?';$args[]=$brandFilter;}
                if($categoryFilter>0){$where[]='EXISTS(SELECT 1 FROM store_product_categories pc WHERE pc.product_id=p.id AND pc.category_id=?)';$args[]=$categoryFilter;}
                if($stockFilter==='sellable'){$where[]=$sellable;}
                elseif($stockFilter==='low'){$where[]=$sellable;$where[]='v.stock_qty>0 AND v.stock_qty<=v.low_stock_threshold';}
                elseif($stockFilter==='out'){$where[]=$sellable;$where[]='v.stock_qty<=0';}
                elseif($stockFilter==='healthy'){$where[]=$sellable;$where[]='v.stock_qty>v.low_stock_threshold';}
                elseif($stockFilter==='attention'){$where[]=$sellable;$where[]='v.stock_qty<=v.low_stock_threshold';}
                else $stockFilter='';
                $whereSql=' WHERE '.implode(' AND ',$where);
                $count=$db->prepare('SELECT COUNT(*) FROM store_product_variants v JOIN store_products p ON p.id=v.product_id LEFT JOIN store_brands b ON b.id=p.brand_id'.$whereSql);$count->execute($args);$total=(int)$count->fetchColumn();
                $totalPages=max(1,(int)ceil($total/$perPage));if($page>$totalPages)$page=$totalPages;$offset=($page-1)*$perPage;
                $stmt=$db->prepare("SELECT v.*,p.id AS product_id,p.name AS product_name,p.status AS product_status,p.published_at,p.main_image_path,b.name AS brand_name FROM store_product_variants v JOIN store_products p ON p.id=v.product_id LEFT JOIN store_brands b ON b.id=p.brand_id{$whereSql} ORDER BY p.name,v.sort_order,v.id LIMIT {$perPage} OFFSET {$offset}");$stmt->execute($args);$rows=$stmt->fetchAll();
            }else{
                $movementTypes=$db->query("SELECT DISTINCT movement_type FROM store_inventory_movements WHERE movement_type<>'' ORDER BY movement_type")->fetchAll(\PDO::FETCH_COLUMN);
                if($movementFilter!==''&&!in_array($movementFilter,$movementTypes,true))$movementFilter='';
                $historyWhere=['1=1'];$historyArgs=[];
                if($query!==''){$historyWhere[]="(p.name LIKE ? ESCAPE '!' OR b.name LIKE ? ESCAPE '!' OR v.sku LIKE ? ESCAPE '!' OR v.label LIKE ? ESCAPE '!' OR m.note LIKE ? ESCAPE '!' OR u.username LIKE ? ESCAPE '!')";$like='%'.$this->escapeLike($query).'%';array_push($historyArgs,$like,$like,$like,$like,$like,$like);}
                if($movementFilter!==''){$historyWhere[]='m.movement_type=?';$historyArgs[]=$movementFilter;}
                if($view==='orders')$historyWhere[]="m.reference_type='order' AND m.reference_id>0";
                $historyWhereSql=' WHERE '.implode(' AND ',$historyWhere);
                $orderJoin=$this->exists($db,'store_orders')?" LEFT JOIN store_orders so ON m.reference_type='order' AND so.id=m.reference_id":'';
                $orderSelect=$orderJoin!==''?'so.order_number':'NULL';
                $historyFrom=' FROM store_inventory_movements m JOIN store_product_variants v ON v.id=m.variant_id JOIN store_products p ON p.id=v.product_id LEFT JOIN store_brands b ON b.id=p.brand_id LEFT JOIN users u ON u.id=m.created_by'.$orderJoin;
                $count=$db->prepare('SELECT COUNT(*)'.$historyFrom.$historyWhereSql);$count->execute($historyArgs);$historyTotal=(int)$count->fetchColumn();
                $historyTotalPages=max(1,(int)ceil($historyTotal/self::HISTORY_PER_PAGE));if($historyPage>$historyTotalPages)$historyPage=$historyTotalPages;$offset=($historyPage-1)*self::HISTORY_PER_PAGE;
                $stockAfter="(v.stock_qty-COALESCE((SELECT SUM(m2.quantity_delta) FROM store_inventory_movements m2 WHERE m2.variant_id=m.variant_id AND (m2.created_at>m.created_at OR (m2.created_at=m.created_at AND m2.id>m.id))),0))";
                $stmt=$db->prepare('SELECT m.*,v.sku,v.label,p.id AS product_id,p.name AS product_name,b.name AS brand_name,u.username AS admin_name,'.$orderSelect.' AS order_number,'.$stockAfter.' AS stock_after'.$historyFrom.$historyWhereSql.' ORDER BY m.created_at DESC,m.id DESC LIMIT '.self::HISTORY_PER_PAGE.' OFFSET '.$offset);$stmt->execute($historyArgs);$movements=$stmt->fetchAll();
            }
        }

        return ['ready'=>$ready,'rows'=>$rows,'lowStockRows'=>$lowStockRows,'movements'=>$movements,'movementTypes'=>$movementTypes,'summary'=>$summary,'brands'=>$brands,'categories'=>$categories,'message'=>$flash['message'],'error'=>$flash['error'],'query'=>$query,'stockFilter'=>$stockFilter,'brandFilter'=>$brandFilter,'categoryFilter'=>$categoryFilter,'view'=>$view,'movementFilter'=>$movementFilter,'fallbackVariant'=>isset($_GET['adjust']) && (int)$_GET['adjust']>0 ? $this->variantSnapshot($db,(int)$_GET['adjust']) : null,'fallbackBulk'=>isset($_GET['bulk']) && $_GET['bulk']==='1','page'=>$page,'total'=>$total,'totalPages'=>$totalPages,'historyPage'=>$historyPage,'historyTotal'=>$historyTotal,'historyTotalPages'=>$historyTotalPages];
    }

    private function adjustOne(\PDO $db,array $input): array
    {
        $variantId=max(0,(int)($input['variant_id']??0));
        $quantityRaw=trim((string)($input['quantity']??''));
        if($quantityRaw===''||preg_match('/^\d+$/',$quantityRaw)!==1)throw new PublicException('Enter a whole-number stock quantity.');
        $quantity=(int)$quantityRaw;if($quantity<0||$quantity>self::MAX_ADJUSTMENT)throw new PublicException('Enter a stock quantity between 0 and '.number_format(self::MAX_ADJUSTMENT).'.');
        $operation=(string)($input['stock_operation']??'receive');
        $thresholdRaw=trim((string)($input['low_stock_threshold']??''));
        if($thresholdRaw===''||preg_match('/^\d+$/',$thresholdRaw)!==1)throw new PublicException('Enter a whole-number low-stock alert threshold.');
        $threshold=(int)$thresholdRaw;if($threshold>999999)throw new PublicException('Low-stock alert threshold is too large.');
        $note=mb_substr(trim((string)($input['note']??'')),0,500);
        $operations=['receive'=>[1,'stock_receive'],'correction_add'=>[1,'stock_correction'],'correction_remove'=>[-1,'stock_correction'],'damage'=>[-1,'stock_damage']];
        if($variantId<=0)throw new PublicException('Choose a valid product variant.');
        if($operation!=='set_count'&&!isset($operations[$operation]))throw new PublicException('Choose a valid stock operation.');
        if($quantity>0&&in_array($operation,['correction_add','correction_remove','damage'],true)&&$note==='')throw new PublicException('Add a reason for corrections, removals and damaged/lost stock.');
        $db->beginTransaction();
        try{
            $lock=$db->prepare('SELECT id,stock_qty,low_stock_threshold FROM store_product_variants WHERE id=? FOR UPDATE');$lock->execute([$variantId]);$current=$lock->fetch();if(!is_array($current))throw new PublicException('Variant not found.');
            $currentThreshold=(int)$current['low_stock_threshold'];$currentStock=(int)$current['stock_qty'];$next=$currentStock;$stockChanged=false;
            if($operation==='set_count'){
                if($quantity!==$currentStock){if($note==='')throw new PublicException('Add a reason for the physical stock count correction.');$next=ServiceFactory::inventory($db)->setStock($variantId,$quantity,'stock_count',$note,(int)($_SESSION['user_id']??0),'admin_inventory',null);$stockChanged=true;}
            }elseif($quantity>0){[$direction,$movementType]=$operations[$operation];$next=ServiceFactory::inventory($db)->adjust($variantId,$quantity*$direction,$movementType,$note,(int)($_SESSION['user_id']??0),'admin_inventory',null);$stockChanged=true;}
            if(!$stockChanged&&$threshold===$currentThreshold)throw new PublicException($operation==='set_count'?'The counted stock already matches the saved stock.':'Enter a quantity or change the low-stock alert threshold.');
            if($threshold!==$currentThreshold){$u=$db->prepare('UPDATE store_product_variants SET low_stock_threshold=? WHERE id=?');$u->execute([$threshold,$variantId]);$movement=$db->prepare("INSERT INTO store_inventory_movements(variant_id,quantity_delta,movement_type,reference_type,reference_id,note,created_by) VALUES(?,0,'threshold_update','admin_inventory',NULL,?,?)");$movement->execute([$variantId,'Low-stock alert changed from '.$currentThreshold.' to '.$threshold,(int)($_SESSION['user_id']??0)?:null]);}
            $parts=[];if($stockChanged)$parts[]='stock now '.$next;if($threshold!==$currentThreshold)$parts[]='alert threshold '.$threshold;\admin_audit('inventory_updated','Variant #'.$variantId.' · '.implode(' · ',$parts));
            $db->commit();\storefront_cache_clear();
            $message=$stockChanged&&$threshold!==$currentThreshold?'Stock and alert threshold updated.':($stockChanged?($operation==='set_count'?'Physical stock count recorded.':'Stock movement recorded.'):'Low-stock alert threshold updated.');
            return [$message,$variantId];
        }catch(\Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
    }

    private function bulkAdjust(\PDO $db,array $input): array
    {
        $mode=(string)($input['bulk_mode']??'receive');if(!in_array($mode,['receive','set_count'],true))throw new PublicException('Choose Receive or Physical count.');
        $ids=is_array($input['bulk_variant_id']??null)?$input['bulk_variant_id']:[];$values=is_array($input['bulk_value']??null)?$input['bulk_value']:[];
        if(count($ids)>self::MAX_BULK_ROWS)throw new PublicException('Bulk inventory is limited to '.self::MAX_BULK_ROWS.' variants at once.');
        $note=mb_substr(trim((string)($input['bulk_note']??'')),0,500);if($mode==='set_count'&&$note==='')throw new PublicException('Add a reason for the physical stock count.');
        $changes=[];foreach($ids as $i=>$rawId){$id=max(0,(int)$rawId);$raw=trim((string)($values[$i]??''));if($id<=0||$raw==='')continue;if(preg_match('/^\d+$/',$raw)!==1)throw new PublicException('Bulk quantities must be whole numbers.');$value=(int)$raw;if($value<0||$value>self::MAX_ADJUSTMENT)throw new PublicException('A bulk quantity is outside the allowed range.');if($mode==='receive'&&$value===0)continue;$changes[$id]=$value;}
        if(!$changes)throw new PublicException('Enter at least one stock quantity.');
        $db->beginTransaction();$changed=[];
        try{
            $inventory=ServiceFactory::inventory($db);$userId=(int)($_SESSION['user_id']??0);
            foreach($changes as $id=>$value){
                $lock=$db->prepare('SELECT stock_qty FROM store_product_variants WHERE id=? FOR UPDATE');$lock->execute([$id]);$current=$lock->fetchColumn();if($current===false)throw new PublicException('One of the selected variants no longer exists.');$current=(int)$current;
                if($mode==='receive'){$inventory->adjust($id,$value,'stock_receive',$note,$userId,'admin_inventory_bulk',null);$changed[]=$id;}
                elseif($value!==$current){$inventory->setStock($id,$value,'stock_count',$note,$userId,'admin_inventory_bulk',null);$changed[]=$id;}
            }
            if(!$changed)throw new PublicException('The counted quantities already match saved stock.');
            \admin_audit('inventory_bulk_updated',ucfirst(str_replace('_',' ',$mode)).' · '.count($changed).' variants');$db->commit();\storefront_cache_clear();
            return [$mode==='receive'?'Supplier stock received.':'Physical stock count recorded.',$changed];
        }catch(\Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
    }

    private function variantSnapshot(\PDO $db,int $variantId): ?array
    {
        $s=$db->prepare("SELECT v.id,v.product_id,v.label,v.sku,v.stock_qty,v.low_stock_threshold,v.status,p.name AS product_name,p.status AS product_status,p.published_at,p.main_image_path,b.name AS brand_name FROM store_product_variants v JOIN store_products p ON p.id=v.product_id LEFT JOIN store_brands b ON b.id=p.brand_id WHERE v.id=? LIMIT 1");$s->execute([$variantId]);$row=$s->fetch();if(!is_array($row))return null;
        $stock=(int)$row['stock_qty'];$threshold=(int)$row['low_stock_threshold'];$sellable=(string)$row['status']==='active'&&(string)$row['product_status']==='active'&&(empty($row['published_at'])||strtotime((string)$row['published_at'])<=time());
        $state=!$sellable?'Not sellable':($stock<=0?'Out of stock':($stock<=$threshold?'Low stock':'Healthy'));$stateClass=!$sellable?'muted':($stock<=0?'out':($stock<=$threshold?'low':'healthy'));
        return ['id'=>(int)$row['id'],'product_id'=>(int)$row['product_id'],'product_name'=>(string)$row['product_name'],'brand_name'=>(string)($row['brand_name']??''),'label'=>(string)$row['label'],'sku'=>(string)$row['sku'],'stock_qty'=>$stock,'low_stock_threshold'=>$threshold,'state'=>$state,'state_class'=>$stateClass];
    }

    private function summary(\PDO $db): array
    {
        $sellable="v.status='active' AND p.status='active' AND (p.published_at IS NULL OR p.published_at<=UTC_TIMESTAMP())";
        $row=$db->query("SELECT COUNT(*) AS active_count,
            SUM(CASE WHEN v.stock_qty>v.low_stock_threshold THEN 1 ELSE 0 END) AS healthy_count,
            SUM(CASE WHEN v.stock_qty>0 AND v.stock_qty<=v.low_stock_threshold THEN 1 ELSE 0 END) AS low_count,
            SUM(CASE WHEN v.stock_qty<=0 THEN 1 ELSE 0 END) AS out_count,
            COALESCE(SUM(GREATEST(0,v.stock_qty)),0) AS units_count,
            COALESCE(SUM(CASE WHEN v.currency='EGP' THEN GREATEST(0,v.stock_qty)*v.price ELSE 0 END),0) AS retail_value,
            COALESCE(SUM(CASE WHEN v.currency='EGP' THEN GREATEST(0,v.stock_qty)*COALESCE(v.cost_price,0) ELSE 0 END),0) AS cost_value
            FROM store_product_variants v JOIN store_products p ON p.id=v.product_id WHERE {$sellable}")->fetch();
        return is_array($row)?['active'=>(int)($row['active_count']??0),'healthy'=>(int)($row['healthy_count']??0),'low'=>(int)($row['low_count']??0),'out'=>(int)($row['out_count']??0),'units'=>(int)($row['units_count']??0),'retail_value'=>(float)($row['retail_value']??0),'cost_value'=>(float)($row['cost_value']??0)]:['active'=>0,'healthy'=>0,'low'=>0,'out'=>0,'units'=>0,'retail_value'=>0,'cost_value'=>0];
    }

    private function isAjax(): bool
    {
        return (string)($_POST['_ajax']??'')==='inventory' || str_contains(strtolower((string)($_SERVER['HTTP_ACCEPT']??'')),'application/json') || strtolower((string)($_SERVER['HTTP_X_REQUESTED_WITH']??''))==='xmlhttprequest';
    }

    private function json(array $payload,int $status=200): never
    {
        http_response_code($status);header('Content-Type: application/json; charset=UTF-8');header('Cache-Control: no-store, private');echo json_encode($payload,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);exit;
    }

    private function exists(\PDO $db,string $table):bool
    {
        $s=$db->prepare("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?");$s->execute([$table]);return (int)$s->fetchColumn()===1;
    }

    private function escapeLike(string $value): string
    {
        return strtr($value,['!'=>'!!','%'=>'!%','_'=>'!_']);
    }
}
