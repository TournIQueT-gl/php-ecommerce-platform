<?php
declare(strict_types=1);
namespace App\Http\Controllers\Admin;

use App\Support\ServiceFactory;
use App\Support\Logger;

final class AnalyticsController
{
    public function handle(): array
    {
        $db=\db();$period=(int)($_GET['period']??30);if(!in_array($period,[7,30,90],true))$period=30;
        $metrics=['orders'=>0,'paid_orders'=>0,'revenue'=>0.0,'avg_order'=>0.0,'customers'=>0,'products'=>0,'low_stock'=>0,'conversion'=>0.0];$topProducts=[];$statuses=[];$daily=[];
        try{
            $netRevenue="GREATEST(o.grand_total-COALESCE((SELECT SUM(r.refund_amount) FROM store_returns r WHERE r.order_id=o.id AND r.status='completed'),0),0)";
            $settled="('paid','partially_refunded','refunded')";
            $metrics['orders']=(int)$db->query("SELECT COUNT(*) FROM store_orders WHERE status<>'cancelled' AND placed_at>=UTC_TIMESTAMP()-INTERVAL {$period} DAY")->fetchColumn();
            $metrics['paid_orders']=(int)$db->query("SELECT COUNT(*) FROM store_orders WHERE status<>'cancelled' AND payment_status IN {$settled} AND placed_at>=UTC_TIMESTAMP()-INTERVAL {$period} DAY")->fetchColumn();
            $metrics['revenue']=(float)$db->query("SELECT COALESCE(SUM({$netRevenue}),0) FROM store_orders o WHERE o.status<>'cancelled' AND o.payment_status IN {$settled} AND o.placed_at>=UTC_TIMESTAMP()-INTERVAL {$period} DAY")->fetchColumn();
            $metrics['avg_order']=$metrics['paid_orders']>0?$metrics['revenue']/$metrics['paid_orders']:0.0;
            $metrics['customers']=(int)$db->query("SELECT COUNT(DISTINCT CASE WHEN user_id IS NOT NULL THEN CONCAT('u:',user_id) WHEN NULLIF(TRIM(guest_email),'') IS NOT NULL THEN CONCAT('g:',LOWER(TRIM(guest_email))) ELSE NULL END) FROM store_orders WHERE status<>'cancelled' AND placed_at>=UTC_TIMESTAMP()-INTERVAL {$period} DAY")->fetchColumn();
            $metrics['products']=(int)$db->query("SELECT COUNT(*) FROM store_products WHERE status<>'archived'")->fetchColumn();
            $metrics['low_stock']=(int)$db->query("SELECT COUNT(*) FROM store_product_variants v JOIN store_products p ON p.id=v.product_id WHERE v.status='active' AND p.status='active' AND (p.published_at IS NULL OR p.published_at<=UTC_TIMESTAMP()) AND v.stock_qty<=v.low_stock_threshold")->fetchColumn();
            $topProducts=$db->query("SELECT oi.product_name AS name,oi.brand_name AS brand,SUM(oi.quantity) AS units,SUM(oi.line_total) AS revenue FROM store_order_items oi JOIN store_orders o ON o.id=oi.order_id WHERE o.status<>'cancelled' AND o.payment_status IN {$settled} AND o.placed_at>=UTC_TIMESTAMP()-INTERVAL {$period} DAY GROUP BY oi.product_name,oi.brand_name ORDER BY units DESC,revenue DESC LIMIT 10")->fetchAll();
            $statuses=$db->query("SELECT status,COUNT(*) AS total FROM store_orders WHERE placed_at>=UTC_TIMESTAMP()-INTERVAL {$period} DAY GROUP BY status ORDER BY total DESC")->fetchAll();
            $daily=$db->query("SELECT DATE(o.placed_at) AS day,SUM(CASE WHEN o.status<>'cancelled' THEN 1 ELSE 0 END) AS orders,COALESCE(SUM(CASE WHEN o.status<>'cancelled' AND o.payment_status IN {$settled} THEN {$netRevenue} ELSE 0 END),0) AS revenue FROM store_orders o WHERE o.placed_at>=UTC_TIMESTAMP()-INTERVAL {$period} DAY GROUP BY DATE(o.placed_at) ORDER BY day ASC")->fetchAll();
        }catch(\Throwable $e){Logger::warning('Commerce analytics query unavailable',['message'=>$e->getMessage()]);}

        $visitorsReady=false;$visitorSummary=[];$trafficOverview=[];$topPages=[];$topReferrers=[];$countries=[];$devices=[];$campaigns=[];
        try{
            $repo=ServiceFactory::visitorAnalytics($db);$visitorsReady=$repo->schemaReady();
            if($visitorsReady){$visitorSummary=$repo->summary($period);$trafficOverview=$repo->trafficOverview($period);$topPages=$repo->topPages($period,10);$topReferrers=$repo->topReferrers($period,10);$countries=$repo->countryBreakdown($period,10);$devices=$repo->deviceBreakdown($period);$campaigns=$repo->campaignSummary($period,10);}
        }catch(\Throwable){}
        $sessions=(int)($visitorSummary['sessions']??0);$metrics['conversion']=$sessions>0?round(($metrics['orders']/$sessions)*100,2):0.0;
        return compact('period','metrics','topProducts','statuses','daily','visitorsReady','visitorSummary','trafficOverview','topPages','topReferrers','countries','devices','campaigns');
    }
}
