<?php
declare(strict_types=1);

function visitor_analytics_cookie_value(string $name,int $bytes=32): string
{
    $value=strtolower(trim((string)($_COOKIE[$name]??'')));
    if(!preg_match('/^[a-f0-9]{64}$/',$value))$value=bin2hex(random_bytes($bytes));
    return $value;
}

function visitor_analytics_set_cookie(string $name,string $value,int $expires): void
{
    if(headers_sent())return;
    setcookie($name,$value,[
        'expires'=>$expires,
        'path'=>BASE_PATH===''?'/':BASE_PATH,
        'secure'=>web_request_is_https() || (REQUIRE_HTTPS && !web_is_local_host()),
        'httponly'=>true,
        'samesite'=>'Lax',
    ]);
    $_COOKIE[$name]=$value;
}

function visitor_analytics_identity(): array
{
    static $identity=null;
    if(is_array($identity))return $identity;
    $visitor=visitor_analytics_cookie_value('sm_vid');
    $session=visitor_analytics_cookie_value('sm_vsid');
    $settings=function_exists('analytics_settings')?analytics_settings():['retention_days'=>90];
    $retention=(int)($settings['retention_days']??90);
    if(!in_array($retention,[30,60,90,180],true))$retention=90;
    visitor_analytics_set_cookie('sm_vid',$visitor,time()+($retention*86400));
    visitor_analytics_set_cookie('sm_vsid',$session,time()+1800);
    return $identity=['visitor_key'=>$visitor,'session_key'=>$session];
}

function visitor_analytics_is_bot(string $ua): bool
{
    if($ua==='')return true;
    return (bool)preg_match('/bot\b|crawler|spider|slurp|bingpreview|facebookexternalhit|discordbot|telegrambot|whatsapp|curl\/|wget\/|python-requests|httpclient|uptime|monitoring/i',$ua);
}

function visitor_analytics_user_agent(string $ua): array
{
    $browser='Other';
    if(stripos($ua,'Edg/')!==false)$browser='Edge';
    elseif(stripos($ua,'OPR/')!==false||stripos($ua,'Opera')!==false)$browser='Opera';
    elseif(stripos($ua,'Firefox/')!==false)$browser='Firefox';
    elseif(stripos($ua,'Chrome/')!==false)$browser='Chrome';
    elseif(stripos($ua,'Safari/')!==false)$browser='Safari';

    $os='Other';
    if(stripos($ua,'Windows')!==false)$os='Windows';
    elseif(stripos($ua,'Android')!==false)$os='Android';
    elseif(stripos($ua,'iPhone')!==false||stripos($ua,'iPad')!==false)$os='iOS';
    elseif(stripos($ua,'Mac OS X')!==false)$os='macOS';
    elseif(stripos($ua,'Linux')!==false)$os='Linux';

    $device='Desktop';
    if(stripos($ua,'Mobile')!==false||stripos($ua,'Android')!==false||stripos($ua,'iPhone')!==false)$device='Mobile';
    elseif(stripos($ua,'iPad')!==false||stripos($ua,'Tablet')!==false)$device='Tablet';
    return compact('browser','os','device');
}

function visitor_analytics_referrer_host(): ?string
{
    $ref=trim((string)($_SERVER['HTTP_REFERER']??''));
    if($ref==='')return null;
    $host=strtolower((string)(parse_url($ref,PHP_URL_HOST)??''));
    if($host==='')return null;
    $siteHost=strtolower((string)(parse_url(CANONICAL_SITE_URL,PHP_URL_HOST)??''));
    if($siteHost!==''&&hash_equals($siteHost,$host))return null;
    return substr($host,0,190);
}

function visitor_analytics_country_code(): ?string
{
    // Server-populated GeoIP variables are not client-controlled HTTP headers.
    foreach(['GEOIP_COUNTRY_CODE','GEOIP_COUNTRY_CODE3'] as $serverKey){
        $code=strtoupper(trim((string)($_SERVER[$serverKey]??'')));
        if($serverKey==='GEOIP_COUNTRY_CODE3'&&strlen($code)===3)$code=''; // store ISO alpha-2 only
        if(preg_match('/^[A-Z]{2}$/',$code))return $code;
    }
    $remote=\App\Support\Request::remoteIp();
    if($remote!=='unknown'&&\App\Support\Request::isTrustedProxy($remote)){
        foreach(['HTTP_CF_IPCOUNTRY','HTTP_X_COUNTRY_CODE','HTTP_X_GEO_COUNTRY'] as $header){
            $code=strtoupper(trim((string)($_SERVER[$header]??'')));
            if(preg_match('/^[A-Z]{2}$/',$code))return $code;
        }
    }
    $ip=client_ip();
    if($ip!=='unknown'&&function_exists('geoip_country_code_by_name')){
        try{$code=strtoupper(trim((string)@geoip_country_code_by_name($ip)));if(preg_match('/^[A-Z]{2}$/',$code))return $code;}catch(\Throwable){}
    }
    return null;
}

function visitor_country_flag(?string $code): string
{
    $code=strtoupper(trim((string)$code));
    if(!preg_match('/^[A-Z]{2}$/',$code))return '';
    $out='';
    foreach(str_split($code) as $c){$cp=127397+ord($c);if(function_exists('mb_chr'))$out.=mb_chr($cp,'UTF-8');}
    return $out;
}

function visitor_country_label(?string $code,?string $name=null): string
{
    $code=strtoupper(trim((string)$code));$name=trim((string)$name);
    if($code===''||$code==='??')return 'Unknown';
    if($name===''){try{if(class_exists('Locale')){$label=\Locale::getDisplayRegion('-'.$code,'en');if(is_string($label)&&$label!==''&&$label!==$code)$name=$label;}}catch(\Throwable){}}
    $flag=visitor_country_flag($code);
    return trim(($flag!==''?$flag.' ':'').($name!==''?$name:$code));
}

function visitor_analytics_is_noise_path(string $path): bool
{
    $path=strtolower('/'.trim($path,'/'));
    return in_array($path,['/favicon.ico','/apple-touch-icon.png','/apple-touch-icon-precomposed.png','/browserconfig.xml','/site.webmanifest'],true);
}

function visitor_analytics_campaign(string $name,int $max): ?string
{
    $value=trim((string)($_GET[$name]??''));
    if($value==='')return null;
    $value=preg_replace('/[^A-Za-z0-9._~\- ]/','',$value)??'';
    $value=trim($value);
    return $value!==''?substr($value,0,$max):null;
}

function visitor_analytics_path_is_internal(string $path): bool
{
    $path=strtolower(rtrim($path,'/'));
    if($path==='')$path='/';
    foreach(['/admin','/assets','/storage','/sitemap','/robots.txt','/analytics-event','/support-attachment','/logout','/maintenance','/403','/404','/419','/500'] as $prefix){
        if($path===$prefix||str_starts_with($path,$prefix.'/')||str_starts_with($path,$prefix.'.'))return true;
    }
    return false;
}

function visitor_analytics_real_page_paths(): array
{
    static $paths=null;
    if(is_array($paths))return $paths;
    $paths=['/'];
    foreach(glob(dirname(__DIR__).DIRECTORY_SEPARATOR.'*.php')?:[] as $file){
        $name=basename($file,'.php');
        $path='/'.$name;
        if(visitor_analytics_path_is_internal($path))continue;
        if(in_array($name,['verify-email'],true))continue;
        $paths[]=$path;
    }
    $paths=array_values(array_unique($paths));
    sort($paths,SORT_STRING);
    return $paths;
}

function visitor_analytics_path_is_real_page(string $path): bool
{
    $normalized='/' . trim($path,'/');
    if($normalized==='/'||$normalized==='')return true;
    if(visitor_analytics_path_is_internal($normalized))return false;
    if(!preg_match('#^/[A-Za-z0-9][A-Za-z0-9_-]*$#',$normalized))return false;
    $name=ltrim($normalized,'/');
    $file=dirname(__DIR__).DIRECTORY_SEPARATOR.$name.'.php';
    return is_file($file);
}

function visitor_analytics_filtered_request_allowed(string $ip,int $intervalSeconds=15): bool
{
    $ip=trim($ip);
    if($ip==='')$ip='unknown';
    $file=sys_get_temp_dir().DIRECTORY_SEPARATOR.'sm_filtered_'.substr(hash('sha256',dirname(__DIR__).'|'.$ip),0,24).'.tick';
    $handle=@fopen($file,'c+');
    if($handle===false)return true; // diagnostics must never break the request
    if(!@flock($handle,LOCK_EX|LOCK_NB)){@fclose($handle);return false;}
    @rewind($handle);
    $last=(int)trim((string)stream_get_contents($handle));
    $now=time();
    if($last>0&&($now-$last)<$intervalSeconds){@flock($handle,LOCK_UN);@fclose($handle);return false;}
    @ftruncate($handle,0);@rewind($handle);@fwrite($handle,(string)$now);@fflush($handle);@flock($handle,LOCK_UN);@fclose($handle);
    return true;
}

function visitor_analytics_record_filtered_request(string $reason): void
{
    try{
        $settings=function_exists('analytics_settings')?analytics_settings():['enabled'=>true];
        if(empty($settings['enabled']))return;
        $ip=substr(client_ip(),0,45);
        if(!visitor_analytics_filtered_request_allowed($ip))return;
        $repo=\App\Support\ServiceFactory::visitorAnalytics(db());
        if(!$repo->schemaReady())return;
        $path=substr(web_request_path(),0,255);
        $ua=substr((string)($_SERVER['HTTP_USER_AGENT']??''),0,500);
        $agent=visitor_analytics_user_agent($ua);
        $repo->trackEvent('automated_request',null,null,null,$path,[
            'reason'=>substr($reason,0,64),
            'method'=>substr(strtoupper((string)($_SERVER['REQUEST_METHOD']??'GET')),0,12),
            'ip'=>$ip,
            'user_agent'=>$ua,
            'browser'=>$agent['browser'],
            'os'=>$agent['os'],
            'device'=>$agent['device'],
        ]);
    }catch(\Throwable $e){
        \App\Support\Logger::warning('Visitor analytics filtered-request event unavailable',['reason'=>$reason,'message'=>$e->getMessage()]);
    }
}

function visitor_analytics_should_track(): bool
{
    if(PHP_SAPI==='cli')return false;
    $ua=(string)($_SERVER['HTTP_USER_AGENT']??'');
    if(trim((string)($_SERVER['HTTP_DNT']??''))==='1'||trim((string)($_SERVER['HTTP_SEC_GPC']??''))==='1')return false;
    $settings=function_exists('analytics_settings')?analytics_settings():['enabled'=>true];
    if(empty($settings['enabled']))return false;
    return true;
}

function visitor_analytics_track_request(): void
{
    if(!visitor_analytics_should_track())return;

    $method=strtoupper((string)($_SERVER['REQUEST_METHOD']??'GET'));
    $path=web_request_path();
    $ua=substr((string)($_SERVER['HTTP_USER_AGENT']??''),0,500);

    // Internal/admin/static traffic is operational traffic, not website visitor analytics.
    if(visitor_analytics_path_is_internal($path))return;

    // HEAD probes and command-line/known bot clients never receive visitor cookies.
    if($method==='HEAD'){
        visitor_analytics_record_filtered_request('head_request');
        return;
    }
    if($method!=='GET')return;
    if(visitor_analytics_is_bot($ua)){
        visitor_analytics_record_filtered_request('bot_user_agent');
        return;
    }

    // Routine browser asset discovery is neither a human pageview nor useful security traffic.
    if(visitor_analytics_is_noise_path($path))return;

    // Unknown routes (scanner probes, typos and arbitrary 404 paths) are separated
    // from human visitor/pageview statistics and never get analytics cookies.
    if(!visitor_analytics_path_is_real_page($path)){
        visitor_analytics_record_filtered_request('unknown_route');
        return;
    }

    try{
        $repo=\App\Support\ServiceFactory::visitorAnalytics(db());
        if(!$repo->schemaReady())return;
        $identity=visitor_analytics_identity();
        $agent=visitor_analytics_user_agent($ua);
        $data=[
            'visitor_key'=>$identity['visitor_key'],
            'session_key'=>$identity['session_key'],
            'user_id'=>!empty($_SESSION['user_id'])?(int)$_SESSION['user_id']:null,
            'ip_address'=>substr(client_ip(),0,45),
            'user_agent'=>$ua,
            'browser'=>$agent['browser'],
            'os_name'=>$agent['os'],
            'device_type'=>$agent['device'],
            'country_code'=>visitor_analytics_country_code(),
            'referrer_host'=>visitor_analytics_referrer_host(),
            'path'=>substr($path,0,255),
            'utm_source'=>visitor_analytics_campaign('utm_source',100),
            'utm_medium'=>visitor_analytics_campaign('utm_medium',100),
            'utm_campaign'=>visitor_analytics_campaign('utm_campaign',150),
            'is_bot'=>0,
        ];
        // Wait until the controller/view has completed so routes that end as 4xx/5xx
        // do not become successful human pageviews.
        register_shutdown_function(static function() use($data): void {
            $status=http_response_code();
            if($status<200||$status>=400)return;
            try{
                $repo=\App\Support\ServiceFactory::visitorAnalytics(db());
                if($repo->schemaReady())$repo->trackPageview($data);
            }catch(\Throwable $e){
                \App\Support\Logger::warning('Visitor analytics pageview unavailable',['message'=>$e->getMessage()]);
            }
        });
    }catch(\Throwable $e){
        \App\Support\Logger::warning('Visitor analytics pageview preparation unavailable',['message'=>$e->getMessage()]);
    }
}



function visitor_analytics_track_client_pageview(array $input): void
{
    if(!visitor_analytics_should_track())return;
    $ua=substr((string)($_SERVER['HTTP_USER_AGENT']??''),0,500);
    if(visitor_analytics_is_bot($ua))return;

    $path=trim((string)($input['path']??''));
    if(BASE_PATH!==''&&str_starts_with($path,BASE_PATH.'/'))$path=substr($path,strlen(BASE_PATH));
    if($path===BASE_PATH)$path='/';
    $path=preg_replace('/[?#].*$/','',$path)??$path;
    if($path==='')$path='/';
    if(!str_starts_with($path,'/'))$path='/'.ltrim($path,'/');
    if(!visitor_analytics_path_is_real_page($path))return;

    try{
        $repo=\App\Support\ServiceFactory::visitorAnalytics(db());
        if(!$repo->schemaReady())return;
        $identity=visitor_analytics_identity();
        $agent=visitor_analytics_user_agent($ua);
        $ref=trim((string)($input['referrer']??''));
        $refHost=$ref!==''?strtolower((string)(parse_url($ref,PHP_URL_HOST)??'')):'';
        $siteHost=strtolower((string)(parse_url(CANONICAL_SITE_URL,PHP_URL_HOST)??''));
        if($refHost===''||($siteHost!==''&&hash_equals($siteHost,$refHost)))$refHost=null;
        $cleanCampaign=static function(mixed $value,int $max): ?string {$v=trim((string)$value);return $v===''?null:substr($v,0,$max);};
        $data=[
            'visitor_key'=>$identity['visitor_key'],
            'session_key'=>$identity['session_key'],
            'user_id'=>!empty($_SESSION['user_id'])?(int)$_SESSION['user_id']:null,
            'ip_address'=>substr(client_ip(),0,45),
            'user_agent'=>$ua,
            'browser'=>$agent['browser'],
            'os_name'=>$agent['os'],
            'device_type'=>$agent['device'],
            'country_code'=>visitor_analytics_country_code(),
            'referrer_host'=>$refHost!==null?substr($refHost,0,190):null,
            'path'=>substr($path,0,255),
            'utm_source'=>$cleanCampaign($input['utm_source']??null,100),
            'utm_medium'=>$cleanCampaign($input['utm_medium']??null,100),
            'utm_campaign'=>$cleanCampaign($input['utm_campaign']??null,150),
            'is_bot'=>0,
        ];
        $repo->trackPageview($data);
    }catch(\Throwable $e){\App\Support\Logger::warning('Visitor analytics client pageview unavailable',['message'=>$e->getMessage()]);}
}

function visitor_analytics_event(string $eventKey,?int $userId=null,array $metadata=[],?string $pathOverride=null): void
{
    if(!preg_match('/^[a-z0-9._-]{2,64}$/',$eventKey))return;
    try{
        $settings=function_exists('analytics_settings')?analytics_settings():['enabled'=>true];
        if(empty($settings['enabled']))return;
        $repo=\App\Support\ServiceFactory::visitorAnalytics(db());
        if(!$repo->schemaReady())return;
        $identity=visitor_analytics_identity();
        $eventPath=$pathOverride!==null?trim($pathOverride):web_request_path();
        if($eventPath===''||!str_starts_with($eventPath,'/'))$eventPath=web_request_path();
        $eventPath=preg_replace('/[?#].*$/','',$eventPath)??web_request_path();
        $repo->trackEvent($eventKey,$userId,$identity['visitor_key'],$identity['session_key'],substr($eventPath,0,255),$metadata);
    }catch(\Throwable $e){
        \App\Support\Logger::warning('Visitor analytics event unavailable',['event'=>$eventKey,'message'=>$e->getMessage()]);
    }
}
