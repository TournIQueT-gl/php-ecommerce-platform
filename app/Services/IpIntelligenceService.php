<?php
declare(strict_types=1);

namespace App\Services;

final class IpIntelligenceService
{
    public function resolve(string $ip,bool $allowRemote=true,int $timeoutSeconds=4): array
    {
        $timeoutSeconds=max(2,min(10,$timeoutSeconds));
        if(!filter_var($ip,FILTER_VALIDATE_IP))return ['ok'=>false,'kind'=>'invalid','error'=>'Invalid IP address.'];
        if($this->isNonPublic($ip))return ['ok'=>false,'kind'=>'private','error'=>'Private/reserved addresses do not have public network intelligence.'];

        if(function_exists('geoip_record_by_name')){
            try{
                $row=@geoip_record_by_name($ip);
                if(is_array($row)&&!empty($row['country_code'])){
                    return [
                        'ok'=>true,'kind'=>'resolved','ip'=>$ip,
                        'country_code'=>strtoupper((string)$row['country_code']),
                        'country_name'=>(string)($row['country_name']??''),
                        'region'=>substr(trim((string)($row['region']??'')),0,120)?:null,
                        'city'=>substr(trim((string)($row['city']??'')),0,100)?:null,
                        'asn'=>null,'organization'=>null,'timezone_name'=>null,
                        'source'=>'php-geoip',
                    ];
                }
            }catch(\Throwable){}
        }

        if(!$allowRemote)return ['ok'=>false,'kind'=>'unavailable','error'=>'No local GeoIP provider is available.'];
        return $this->resolveRemote($ip,$timeoutSeconds);
    }

    private function resolveRemote(string $ip,int $timeoutSeconds): array
    {
        $base=defined('IP_INTELLIGENCE_REMOTE_PROVIDER')?(string)IP_INTELLIGENCE_REMOTE_PROVIDER:'https://ipwho.is/';
        if(!str_starts_with($base,'https://'))return ['ok'=>false,'kind'=>'configuration','error'=>'Remote IP intelligence provider must use HTTPS.'];
        $url=rtrim($base,'/').'/'.rawurlencode($ip);
        $raw='';
        if(function_exists('curl_init')){
            $ch=curl_init($url);
            if($ch===false)return ['ok'=>false,'kind'=>'network','error'=>'Could not initialize network lookup.'];
            curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_CONNECTTIMEOUT=>min(2,$timeoutSeconds),CURLOPT_TIMEOUT=>$timeoutSeconds,CURLOPT_FOLLOWLOCATION=>false,CURLOPT_SSL_VERIFYPEER=>true,CURLOPT_SSL_VERIFYHOST=>2,CURLOPT_USERAGENT=>'PerfumeStore-IP-Intelligence/1.0']);
            $result=curl_exec($ch);$status=(int)curl_getinfo($ch,CURLINFO_RESPONSE_CODE);$error=curl_error($ch);curl_close($ch);
            if($result===false||$status<200||$status>=300)return ['ok'=>false,'kind'=>'network','error'=>$error!==''?'Network lookup failed: '.$error:'Network lookup provider returned HTTP '.$status.'.'];
            $raw=(string)$result;
        }else{
            $context=stream_context_create(['http'=>['timeout'=>$timeoutSeconds,'ignore_errors'=>true,'user_agent'=>'PerfumeStore-IP-Intelligence/1.0'],'ssl'=>['verify_peer'=>true,'verify_peer_name'=>true]]);
            $result=@file_get_contents($url,false,$context);
            if($result===false)return ['ok'=>false,'kind'=>'network','error'=>'Network lookup is unavailable on this server.'];
            $raw=(string)$result;
        }
        if(strlen($raw)>131072)return ['ok'=>false,'kind'=>'provider','error'=>'Network lookup response was unexpectedly large.'];
        $data=json_decode($raw,true);
        if(!is_array($data)||empty($data['success']))return ['ok'=>false,'kind'=>'provider','error'=>is_array($data)&&!empty($data['message'])?substr((string)$data['message'],0,180):'Network lookup provider did not return usable data.'];
        $connection=is_array($data['connection']??null)?$data['connection']:[];
        $timezone=is_array($data['timezone']??null)?$data['timezone']:[];
        $asn=$connection['asn']??null;
        return [
            'ok'=>true,'kind'=>'resolved','ip'=>$ip,
            'country_code'=>preg_match('/^[A-Za-z]{2}$/',(string)($data['country_code']??''))?strtoupper((string)$data['country_code']):null,
            'country_name'=>substr(trim((string)($data['country']??'')),0,100)?:null,
            'region'=>substr(trim((string)($data['region']??'')),0,120)?:null,
            'city'=>substr(trim((string)($data['city']??'')),0,100)?:null,
            'asn'=>$asn!==null&&$asn!==''?'AS'.preg_replace('/\D+/','',(string)$asn):null,
            'organization'=>substr(trim((string)($connection['isp']??$connection['org']??'')),0,190)?:null,
            'timezone_name'=>substr(trim((string)($timezone['id']??'')),0,64)?:null,
            'source'=>'ipwho.is',
        ];
    }

    private function isNonPublic(string $ip): bool
    {
        return filter_var($ip,FILTER_VALIDATE_IP,FILTER_FLAG_NO_PRIV_RANGE|FILTER_FLAG_NO_RES_RANGE)===false;
    }
}
