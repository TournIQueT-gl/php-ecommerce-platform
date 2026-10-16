<?php
declare(strict_types=1);
namespace App\Http\Controllers\Admin;

use App\Support\ServiceFactory;

final class OverviewController
{
    public function handle(): array
    {
        $db=\db();$isSupport=\admin_current_role()==='support';$commerceReady=$this->tableExists($db,'store_products');
        $metrics=['active_products'=>0,'low_stock'=>0,'orders_30d'=>0,'pending_orders'=>0,'pending_returns'=>0,'revenue_30d'=>0.0,'customers'=>0];
        $recentOrders=[];$supportStats=['open'=>0,'waiting'=>0];
        if($commerceReady&&!$isSupport){
            $metrics['active_products']=(int)$db->query("SELECT COUNT(*) FROM store_products WHERE status='active' AND (published_at IS NULL OR published_at<=UTC_TIMESTAMP())")->fetchColumn();
            $metrics['low_stock']=(int)$db->query("SELECT COUNT(*) FROM store_product_variants v JOIN store_products p ON p.id=v.product_id WHERE v.status='active' AND p.status='active' AND (p.published_at IS NULL OR p.published_at<=UTC_TIMESTAMP()) AND v.stock_qty<=v.low_stock_threshold")->fetchColumn();
            $metrics['orders_30d']=(int)$db->query("SELECT COUNT(*) FROM store_orders WHERE status<>'cancelled' AND placed_at>=UTC_TIMESTAMP()-INTERVAL 30 DAY")->fetchColumn();
            $metrics['pending_orders']=(int)$db->query("SELECT COUNT(*) FROM store_orders WHERE status IN ('pending','confirmed','processing')")->fetchColumn();
            $metrics['revenue_30d']=(float)$db->query("SELECT COALESCE(SUM(GREATEST(o.grand_total-COALESCE((SELECT SUM(r.refund_amount) FROM store_returns r WHERE r.order_id=o.id AND r.status='completed'),0),0)),0) FROM store_orders o WHERE o.status<>'cancelled' AND o.payment_status IN ('paid','partially_refunded','refunded') AND o.placed_at>=UTC_TIMESTAMP()-INTERVAL 30 DAY")->fetchColumn();
            if($this->tableExists($db,'store_returns'))$metrics['pending_returns']=(int)$db->query("SELECT COUNT(*) FROM store_returns WHERE status IN ('requested','approved')")->fetchColumn();
            $recentOrders=$db->query("SELECT id,order_number,customer_name,status,payment_status,fulfillment_status,grand_total,currency,placed_at FROM store_orders ORDER BY placed_at DESC,id DESC LIMIT 5")->fetchAll();
        }
        try{$metrics['customers']=(int)$db->query("SELECT COUNT(*) FROM users WHERE is_active=1 AND is_admin=0")->fetchColumn();}catch(\Throwable){}
        try{$supportStats=ServiceFactory::supportRepository($db)->stats();}catch(\Throwable){}
        $openSupport=(int)($supportStats['open']??0)+(int)($supportStats['waiting']??0);
        return compact('commerceReady','isSupport','metrics','recentOrders','supportStats','openSupport');
    }

    private function tableExists(\PDO $db,string $table): bool
    {
        try{$stmt=$db->prepare("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?");$stmt->execute([$table]);return (int)$stmt->fetchColumn()===1;}catch(\Throwable){return false;}
    }
}
