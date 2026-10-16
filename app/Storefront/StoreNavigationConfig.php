<?php
declare(strict_types=1);
namespace App\Storefront;

/** Validated, per-installation navigation configuration. Hidden links are not catalogue deletion. */
final class StoreNavigationConfig
{
    private const TYPES=['home','shop','catalog','department','new','offers','men','women','unisex'];
    public static function defaults(array $departments): array
    {
        return ['style'=>'auto','shop_label'=>'Shop','departments'=>array_values(array_map(static fn($d)=>(string)$d['code'],$departments)),
            'landing'=>[],
            'audiences'=>self::audienceDepartments($departments)?['men','women','unisex']:[],
            'audience_departments'=>array_values(array_map(static fn($d)=>(string)$d['code'],self::audienceDepartments($departments))),
            'links'=>[
                ['type'=>'shop','label'=>'Departments','code'=>'','desktop'=>true,'mobile'=>true],
                ['type'=>'new','label'=>'New arrivals','code'=>'','desktop'=>true,'mobile'=>true],
                ['type'=>'offers','label'=>'Offers','code'=>'','desktop'=>true,'mobile'=>true],
            ]];
    }
    public static function audienceDepartments(array $departments): array
    {
        return array_values(array_filter($departments,static function(array $dep):bool{
            $code=strtolower((string)($dep['code']??''));
            return in_array($code,['perfume','perfumes','fragrance','fragrances','fashion','clothing','apparel'],true);
        }));
    }
    public static function decode(mixed $raw,array $departments): ?array
    {
        if(!is_string($raw)||trim($raw)==='')return null;
        $data=json_decode($raw,true);
        if(!is_array($data))return null;
        try{return self::sanitize($data,$departments);}catch(\InvalidArgumentException){return null;}
    }
    public static function sanitize(array $input,array $departments): array
    {
        $allowed=array_column($departments,'code');$allowed=array_map('strval',$allowed);
        $selected=$input['departments']??[];if(!is_array($selected))throw new \InvalidArgumentException('Invalid departments.');
        $selected=array_values(array_unique(array_filter(array_map('strval',$selected),static fn($c)=>in_array($c,$allowed,true))));
        $style=(string)($input['style']??'auto');if(!in_array($style,['auto','compact','mega'],true))$style='auto';
        $shopLabel=self::label($input['shop_label']??'Departments','Departments');
        $audienceInput=$input['audiences']??(self::audienceDepartments($departments)?['men','women','unisex']:[]);
        if(!is_array($audienceInput))throw new \InvalidArgumentException('Invalid audience navigation.');
        $audiences=array_values(array_unique(array_filter(array_map('strval',$audienceInput),static fn($v)=>in_array($v,['men','women','unisex'],true))));
        $possible=array_column(self::audienceDepartments($departments),'code');
        $audienceDeptInput=$input['audience_departments']??$possible;
        if(!is_array($audienceDeptInput))throw new \InvalidArgumentException('Invalid audience departments.');
        $audienceDepartments=array_values(array_unique(array_filter(array_map('strval',$audienceDeptInput),static fn($c)=>in_array($c,$selected,true)&&in_array($c,$possible,true))));
        if(!$audienceDepartments)$audiences=[];
        $rows=$input['links']??[];if(!is_array($rows))throw new \InvalidArgumentException('Invalid navigation items.');
        $links=[];$ids=[];
        foreach(array_slice($rows,0,12) as $row){
            if(!is_array($row))continue;
            $type=(string)($row['type']??'');if(!in_array($type,self::TYPES,true))throw new \InvalidArgumentException('Invalid navigation destination.');
            $code=$type==='department'?(string)($row['code']??''):'';
            if($type==='department'&&!in_array($code,$selected,true))continue;
            $desktop=self::flag($row['desktop']??false);$mobile=self::flag($row['mobile']??false);
            if(!$desktop&&!$mobile)continue;
            $key=$type.':'.$code; if(isset($ids[$key]))continue; $ids[$key]=true;
            $links[]=['type'=>$type,'code'=>$code,'label'=>self::label($row['label']??'',$type==='shop'?$shopLabel:ucfirst($type)),'desktop'=>$desktop,'mobile'=>$mobile];
        }
        if(!$links)throw new \InvalidArgumentException('Show at least one navigation item on desktop or mobile.');
        $landing=[];$incoming=$input['landing']??[];
        if(is_array($incoming))foreach($departments as $dep){
            $code=(string)$dep['code'];$row=$incoming[$code]??null;
            if(!is_array($row))continue;
            $heading=self::label($row['heading']??'',(string)$dep['name']);
            $intro=trim((string)($row['intro']??''));$intro=function_exists('mb_substr')?mb_substr($intro,0,220):substr($intro,0,220);
            if($intro!==''||$heading!==(string)$dep['name'])$landing[$code]=['heading'=>$heading,'intro'=>$intro];
        }
        return ['style'=>$style,'shop_label'=>$shopLabel,'departments'=>$selected,'audiences'=>$audiences,'audience_departments'=>$audienceDepartments,'landing'=>$landing,'links'=>$links];
    }
    /** Only one top-level control for each audience. Keep stored direct links for when the dropdown is disabled. */
    public static function visibleHeaderLinks(array $config): array
    {
        $audiences=(array)($config['audiences']??[]);
        $visible=[];
        foreach((array)($config['links']??[]) as $link){
            if(!is_array($link))continue;
            $type=(string)($link['type']??'');
            // A published audience dropdown replaces a standalone audience link.
            if(in_array($type,['men','women','unisex'],true)&&in_array($type,$audiences,true))continue;
            $visible[]=$link;
        }
        return $visible;
    }
    public static function fromPost(array $post,array $departments): array
    {
        $types=$post['link_type']??[];$labels=$post['link_label']??[];$codes=$post['link_code']??[];
        $desktop=$post['link_desktop']??[];$mobile=$post['link_mobile']??[];
        if(!is_array($types)||!is_array($labels)||!is_array($codes)||!is_array($desktop)||!is_array($mobile))throw new \InvalidArgumentException('Invalid navigation form.');
        $rows=[];
        foreach(array_slice($types,0,12,true) as $key=>$type){
            $rows[]=['type'=>$type,'label'=>$labels[$key]??'','code'=>$codes[$key]??'','desktop'=>isset($desktop[$key]),'mobile'=>isset($mobile[$key])];
        }
        return self::sanitize(['style'=>$post['style']??'auto','shop_label'=>$post['shop_label']??'Departments','audiences'=>$post['audiences']??[],'audience_departments'=>$post['audience_departments']??[],'departments'=>$post['departments']??[],'links'=>$rows,'landing'=>$post['landing']??[]],$departments);
    }
    private static function flag(mixed $value):bool{return $value===true||$value===1||$value==='1'||$value==='on';}
    private static function label(mixed $value,string $default):string
    {
        $s=trim((string)$value);$s=preg_replace('/[\x00-\x1f\x7f]/u','',$s)??'';
        $s=function_exists('mb_substr')?mb_substr($s,0,36):substr($s,0,36);
        return $s!==''?$s:$default;
    }
}
