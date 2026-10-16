<?php
declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Support\ServiceFactory;

final class AnalyticsIntelligenceController
{
    public function handle(): array
    {
        if(($_SERVER['REQUEST_METHOD']??'GET')!=='POST'){
            http_response_code(405);
            return ['ok'=>false,'error'=>'Method not allowed.'];
        }
        \verify_csrf();
        $repo=ServiceFactory::visitorAnalytics(\db());
        if(!$repo->intelligenceLifecycleReady()){
            http_response_code(409);
            return ['ok'=>false,'error'=>'Apply database update 032 before automatic country/network resolution.'];
        }

        $limit=defined('IP_INTELLIGENCE_BACKGROUND_BATCH')?(int)IP_INTELLIGENCE_BACKGROUND_BATCH:5;
        $limit=max(1,min(3,$limit));$candidates=$repo->ipIntelligenceCandidates($limit);
        if(!$candidates)return ['ok'=>true,'processed'=>0,'resolved'=>0,'failed'=>0,'private'=>0,'stats'=>$repo->intelligenceStats()];
        $retry=\web_rate_limit_consume('admin_ip_intelligence_async',12,3600,(string)($_SESSION['user_id']??0));
        if($retry>0){header('Retry-After: '.$retry);http_response_code(429);return ['ok'=>false,'error'=>'Network intelligence resolver is rate limited.','retry_after'=>$retry];}

        $resolved=0;$failed=0;$private=0;$processed=0;
        foreach($candidates as $ip){
            $processed++;
            try{
                $result=ServiceFactory::ipIntelligence()->resolve($ip,true,3);
                if(!empty($result['ok'])){$repo->saveIpIntelligence($result);$resolved++;continue;}
                $kind=(string)($result['kind']??'error');$permanent=in_array($kind,['private','invalid'],true);
                $repo->markIpIntelligenceFailure($ip,(string)($result['error']??'Lookup failed.'),$permanent);
                if($permanent)$private++;else$failed++;
            }catch(\Throwable $e){$repo->markIpIntelligenceFailure($ip,$e->getMessage(),false);$failed++;}
        }
        return ['ok'=>true,'processed'=>$processed,'resolved'=>$resolved,'failed'=>$failed,'private'=>$private,'stats'=>$repo->intelligenceStats()];
    }
}
