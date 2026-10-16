<?php
declare(strict_types=1);


function ip_access_cache_file(): string
{
    return sys_get_temp_dir().DIRECTORY_SEPARATOR.'auraa_ip_blocks_'.substr(hash('sha256',dirname(__DIR__)),0,16).'.json';
}

function ip_access_cache_clear(): void
{
    @unlink(ip_access_cache_file());
    unset($GLOBALS['_ip_access_blocks_request_cache']);
}

function ip_access_active_blocks_cached(int $ttlSeconds=10): array
{
    if(isset($GLOBALS['_ip_access_blocks_request_cache'])&&is_array($GLOBALS['_ip_access_blocks_request_cache']))return $GLOBALS['_ip_access_blocks_request_cache'];
    $file=ip_access_cache_file();$now=time();$stale=null;
    if(is_file($file)){
        $decoded=json_decode((string)@file_get_contents($file),true);
        if(is_array($decoded)){
            $stale=$decoded;
            if(($now-(int)@filemtime($file))<=max(1,$ttlSeconds))return $GLOBALS['_ip_access_blocks_request_cache']=$decoded;
        }
    }
    try{$rows=\App\Support\ServiceFactory::ipAccess(db())->activeEnforcementBlocks();}
    catch(\Throwable $e){
        // Access control should degrade to the last known-good rule set, not to
        // an empty allow-list, when MySQL is temporarily unavailable.
        \App\Support\Logger::warning('IP access-control lookup unavailable',['message'=>$e->getMessage(),'stale_cache_used'=>is_array($stale)]);
        return $GLOBALS['_ip_access_blocks_request_cache']=is_array($stale)?$stale:[];
    }
    $json=json_encode($rows,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
    if($json!==false){$tmp=$file.'.'.bin2hex(random_bytes(3)).'.tmp';if(@file_put_contents($tmp,$json,LOCK_EX)!==false){@chmod($tmp,0600);@rename($tmp,$file);}else @unlink($tmp);}
    return $GLOBALS['_ip_access_blocks_request_cache']=$rows;
}

function ip_access_match(string $ip): ?array
{
    if(!filter_var($ip,FILTER_VALIDATE_IP))return null;
    foreach(ip_access_active_blocks_cached() as $row){$cidr=(string)($row['cidr']??'');if($cidr!==''&&\App\Support\Request::ipInCidr($ip,$cidr))return $row;}
    return null;
}

function ip_access_enforce_request(): void
{
    if(PHP_SAPI==='cli')return;
    $ip=client_ip();if(!filter_var($ip,FILTER_VALIDATE_IP))return;
    $block=ip_access_match($ip);if(!$block)return;

    try{\App\Support\ServiceFactory::ipAccess(db())->recordHit((int)$block['id']);}catch(\Throwable){}
    try{
        if(visitor_analytics_filtered_request_allowed('blocked|'.$ip,15)){
            $repo=\App\Support\ServiceFactory::visitorAnalytics(db());
            if($repo->schemaReady())$repo->trackEvent('ip_blocked_request',null,null,null,substr(web_request_path(),0,255),['block_id'=>(int)$block['id'],'cidr'=>(string)$block['cidr'],'reason'=>(string)$block['reason'],'ip'=>$ip,'method'=>strtoupper((string)($_SERVER['REQUEST_METHOD']??'GET')),'user_agent'=>substr((string)($_SERVER['HTTP_USER_AGENT']??''),0,500)]);
        }
    }catch(\Throwable){}

    http_response_code(403);header('Cache-Control: no-store');header('X-Robots-Tag: noindex, nofollow');
    require_once __DIR__.'/error_page.php';render_error_page(403,'Access denied','ACCESS BLOCKED.','This network address is blocked from accessing this service. If you believe this is an error, contact support.','BACK TO HOME',url());
}
