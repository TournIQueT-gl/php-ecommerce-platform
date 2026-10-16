<?php
declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Repositories\IpAccessRepository;
use App\Support\Request;
use App\Support\ServiceFactory;

final class VisitorController
{
    private const FLASH='admin_visitor';

    public function handle(): array
    {
        $key=strtolower(trim((string)($_GET['id']??$_POST['visitor_id']??'')));
        if(!preg_match('/^[a-f0-9]{64}$/',$key)){http_response_code(404);return $this->emptyResult();}

        $db=\db();$repo=ServiceFactory::visitorAnalytics($db);$ipRepo=ServiceFactory::ipAccess($db);
        $ready=$repo->schemaReady();$visitor=$ready?$repo->visitorSummary($key):null;
        if(!$visitor){http_response_code(404);return $this->emptyResult($ready);}

        $sessionPage=max(1,(int)($_GET['session_page']??1));$journeyPage=max(1,(int)($_GET['journey_page']??1));$journeyKind=(string)($_GET['journey_kind']??'all');
        if(!in_array($journeyKind,['all','pages','events','conversions'],true))$journeyKind='all';

        if(($_SERVER['REQUEST_METHOD']??'GET')==='POST'){
            \verify_csrf();$action=(string)($_POST['action']??'');$message='';$error='';
            $ipHistory=$repo->visitorIpHistory($key,100);$visitorIps=[];
            foreach($ipHistory as $row){$ip=trim((string)($row['ip_address']??''));if(filter_var($ip,FILTER_VALIDATE_IP))$visitorIps[]=$ip;}

            if($action==='resolve_network'){
                $ip=trim((string)($_POST['ip_address']??''));$retry=\web_rate_limit_consume('admin_ip_intelligence',30,3600,(string)($_SESSION['user_id']??0));
                if($retry>0){header('Retry-After: '.$retry);http_response_code(429);$error='Too many network-intelligence lookups. Try again later.';}
                elseif(!$repo->intelligenceReady())$error='Apply database update 031 before resolving visitor network intelligence.';
                elseif(!in_array($ip,$visitorIps,true))$error='That IP address is not part of this visitor history.';
                else{
                    $result=ServiceFactory::ipIntelligence()->resolve($ip,true);
                    if(empty($result['ok'])){
                        if($repo->intelligenceLifecycleReady())$repo->markIpIntelligenceFailure($ip,(string)($result['error']??'Network intelligence could not be resolved.'),in_array((string)($result['kind']??''),['private','invalid'],true));
                        $error=(string)($result['error']??'Network intelligence could not be resolved.');
                    }else{$repo->saveIpIntelligence($result);\admin_audit('visitor_network_resolved','Resolved visitor network intelligence for '.$ip);$message='Network intelligence resolved and cached for '.$ip.'.';}
                }
            }elseif($action==='block_ip'){
                if(!$ipRepo->schemaReady())$error='Apply database update 031 before using IP access control.';
                elseif(!\admin_require_password_from_post($error)){}
                else{
                    $target=trim((string)($_POST['target']??''));$cidr=IpAccessRepository::normalizeCidr($target);$matchesVisitor=false;
                    if($cidr!==null){foreach($visitorIps as $ip){if(Request::ipInCidr($ip,$cidr)){$matchesVisitor=true;break;}}}
                    if($cidr===null)$error='Enter a valid IPv4/IPv6 address or CIDR network.';
                    elseif(!$matchesVisitor)$error='The block target does not match an IP in this visitor history.';
                    elseif(Request::ipInCidr(\client_ip(),$cidr))$error='This block would include your current Admin IP. It was refused to prevent locking you out.';
                    else{$expiresAt=$this->expiryFromPost($error);if($error===''){$reason=(string)($_POST['reason']??'manual');$note=(string)($_POST['admin_note']??'');try{$id=$ipRepo->create($cidr,$reason,$note,$expiresAt,(int)($_SESSION['user_id']??0));\ip_access_cache_clear();\admin_audit('ip_block_created','Created IP block #'.$id.' for '.$cidr.' · '.$reason);$message='IP access block created for '.$cidr.'.';}catch(\DomainException|\InvalidArgumentException $e){$error=$e->getMessage();}}}
                }
            }elseif($action==='unblock_ip'){
                if(!\admin_require_password_from_post($error)){}
                else{$blockId=max(0,(int)($_POST['block_id']??0));if($blockId<=0||!$ipRepo->deactivate($blockId))$error='That IP block is already inactive or could not be found.';else{\ip_access_cache_clear();\admin_audit('ip_block_removed','Removed IP block #'.$blockId);$message='IP block removed.';}}
            }else $error='Unknown visitor action.';
            $query=['id'=>$key];foreach(['session_page','journey_page','journey_kind'] as $param){$value=trim((string)($_POST[$param]??''));if($value!=='')$query[$param]=$value;}
            \web_post_redirect('admin/visitor.php',self::FLASH,$message,$error,http_build_query($query));
        }

        $flash=\web_flash_take(self::FLASH);$sessionData=$repo->visitorSessionsPage($key,$sessionPage,15);$journeyData=$repo->visitorJourneyPage($key,$journeyPage,50,$journeyKind);
        $sessions=$sessionData['rows'];$journey=$journeyData['rows'];$acquisition=$repo->visitorAcquisition($key);$ipHistory=$repo->visitorIpHistory($key,100);$engagement=$repo->visitorEngagementSummary($key);
        $accountSummary=!empty($visitor['user_id'])?$repo->visitorAccountSummary((int)$visitor['user_id']):[];
        $accessReady=$ipRepo->schemaReady();$activeBlocks=$accessReady?$ipRepo->activeBlocks():[];
        foreach($ipHistory as &$row){$row['active_block']=null;$ip=(string)($row['ip_address']??'');foreach($activeBlocks as $block){if($ip!==''&&Request::ipInCidr($ip,(string)$block['cidr'])){$row['active_block']=$block;break;}}}unset($row);
        $lastIp=(string)($visitor['last_ip']??'');$lastIpBlock=null;foreach($activeBlocks as $block){if($lastIp!==''&&Request::ipInCidr($lastIp,(string)$block['cidr'])){$lastIpBlock=$block;break;}}
        $countrySet=[];foreach($ipHistory as $row){$code=strtoupper(trim((string)($row['resolved_country_code']??$row['country_code']??'')));if(preg_match('/^[A-Z]{2}$/',$code))$countrySet[$code]=true;}
        $securitySignals=['unique_ips'=>(int)($visitor['unique_ips']??count($ipHistory)),'countries'=>count($countrySet),'blocked_ips'=>count(array_filter($ipHistory,static fn(array $r)=>!empty($r['active_block']))),'network_changes'=>max(0,count($ipHistory)-1)];

        return compact('ready','visitor','sessions','sessionData','acquisition','ipHistory','accountSummary','engagement','journey','journeyData','journeyKind','lastIpBlock','activeBlocks','securitySignals')+[
            'accessReady'=>$accessReady,'intelligenceReady'=>$repo->intelligenceReady(),'intelligenceLifecycleReady'=>$repo->intelligenceLifecycleReady(),'message'=>$flash['message'],'error'=>$flash['error'],
        ];
    }

    private function expiryFromPost(string &$error): ?string
    {
        $duration=(string)($_POST['duration']??'24h');$seconds=['1h'=>3600,'24h'=>86400,'7d'=>604800,'30d'=>2592000];
        if(isset($seconds[$duration]))return gmdate('Y-m-d H:i:s',time()+$seconds[$duration]);if($duration==='permanent')return null;
        if($duration==='custom'){$value=(string)($_POST['custom_expires_at']??'');$tz=\admin_browser_timezone_from_post();$utc=\admin_parse_utc_datetime($value,$tz);if($utc===null||strtotime($utc.' UTC')<=time()){$error='Choose a valid future custom expiry time.';return null;}return $utc;}
        $error='Invalid block duration.';return null;
    }

    private function emptyResult(bool $ready=false): array
    {
        return ['ready'=>$ready,'visitor'=>null,'sessions'=>[],'sessionData'=>['rows'=>[],'total'=>0,'page'=>1,'pages'=>1],'acquisition'=>null,'ipHistory'=>[],'accountSummary'=>[],'engagement'=>[],'journey'=>[],'journeyData'=>['rows'=>[],'total'=>0,'page'=>1,'pages'=>1],'journeyKind'=>'all','lastIpBlock'=>null,'activeBlocks'=>[],'securitySignals'=>[],'accessReady'=>false,'intelligenceReady'=>false,'intelligenceLifecycleReady'=>false,'message'=>'','error'=>''];
    }
}
