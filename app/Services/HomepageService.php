<?php
declare(strict_types=1);
namespace App\Services;
use App\Support\PublicException;
final class HomepageService
{
    private const TYPES=['hero','category_grid','product_carousel','scent_grid','editorial_banner','notes_grid','wardrobe_grid','brand_grid','gift_banner','custom','video','department_grid','audience_grid'];
    public function sectionData(array $in): array
    {
        $type=(string)($in['section_type']??'custom');if(!in_array($type,self::TYPES,true))$type='custom';
        $key=$this->slug((string)($in['section_key']??''));if($key==='')$key=$this->slug((string)($in['title']??$type));if($key==='')$key='section';
        $status=(string)($in['status']??'active');if(!in_array($status,['draft','active','hidden','scheduled','archived'],true))$status='active';
        $settings=$this->decode((string)($in['settings_json']??''));
        if(!empty($in['builder_settings_present']))$settings=$this->sectionSettings($settings,$in,$type);
        return ['section_key'=>$key,'section_type'=>$type,'title'=>$this->text($in['title']??null,180),'title_ar'=>$this->text($in['title_ar']??null,180),'subtitle'=>$this->text($in['subtitle']??null,500),'subtitle_ar'=>$this->text($in['subtitle_ar']??null,500),'settings_json'=>$this->encode($settings),'sort_order'=>(int)($in['sort_order']??0),'status'=>$status,'starts_at'=>\admin_parse_utc_datetime((string)($in['starts_at']??''),\admin_browser_timezone_from_post()),'ends_at'=>\admin_parse_utc_datetime((string)($in['ends_at']??''),\admin_browser_timezone_from_post())];
    }
    public function itemData(array $in): array
    {
        $status=(string)($in['status']??'active');if(!in_array($status,['active','hidden','archived'],true))$status='active';
        $settings=$this->decode((string)($in['settings_json']??''));
        if(!empty($in['builder_item_settings_present']))$settings=$this->itemSettings($settings,$in);
        return ['product_id'=>$this->id($in['product_id']??null),'brand_id'=>$this->id($in['brand_id']??null),'category_id'=>$this->id($in['category_id']??null),'title'=>$this->text($in['title']??null,180),'title_ar'=>$this->text($in['title_ar']??null,180),'subtitle'=>$this->text($in['subtitle']??null,500),'subtitle_ar'=>$this->text($in['subtitle_ar']??null,500),'image_path'=>$this->text($in['image_path']??null,255),'mobile_image_path'=>$this->text($in['mobile_image_path']??null,255),'eyebrow'=>$this->text($in['eyebrow']??null,120),'cta_label'=>$this->text($in['cta_label']??null,100),'cta_url'=>$this->safeUrl($in['cta_url']??null),'settings_json'=>$this->encode($settings),'sort_order'=>(int)($in['sort_order']??0),'status'=>$status];
    }
    private function sectionSettings(array $s,array $in,string $type): array
    {
        $s['eyebrow']=$this->text($in['setting_eyebrow']??'',120);
        $s['view_all_label']=$this->text($in['setting_view_all_label']??'',100);
        $s['view_all_url']=$this->safeUrl($in['setting_view_all_url']??'');
        $s['theme']=$this->choice($in['setting_theme']??'paper',['paper','ivory','dark','transparent'],'paper');
        $s['align']=$this->choice($in['setting_align']??'left',['left','center'],'left');
        $s['layout']=$this->choice($in['setting_layout']??'grid',$type==='brand_grid'||$type==='notes_grid'?['grid','carousel','marquee']:['grid','carousel'],'grid');
        if($type==='video'){
            $videoUrl=trim((string)($in['setting_video_url']??($s['video_url']??'')));
            $posterUrl=trim((string)($in['setting_video_poster']??($s['video_poster']??'')));
            if($videoUrl!=='' && preg_match('~\.(?:mp4|webm)(?:\?.*)?$~i',$videoUrl)!==1)throw new PublicException('Video URL must point directly to an MP4 or WebM file.');
            $s['video_url']=$this->safeUrl($videoUrl);
            $s['video_poster']=$this->safeUrl($posterUrl);
            $s['video_loop']=!empty($in['setting_video_loop']);
            $s['video_autoplay']=!empty($in['setting_video_autoplay']);
            $s['video_controls']=!empty($in['setting_video_controls']);
        }
        $s['content_width']=$this->choice($in['setting_content_width']??'standard',['standard','wide','full'],'standard');
        $s['image_ratio']=$this->choice($in['setting_image_ratio']??'square',['square','portrait','landscape','wide'],'square');
        $s['card_style']=$this->choice($in['setting_card_style']??'clean',['clean','bordered','overlay'],'clean');
        $s['max_items']=$this->clampInt($in['setting_max_items']??$this->defaultMax($type),1,24,$this->defaultMax($type));
        $s['columns_desktop']=$this->clampInt($in['setting_columns_desktop']??$this->defaultColumns($type),1,6,$this->defaultColumns($type));
        $s['columns_tablet']=$this->clampInt($in['setting_columns_tablet']??3,1,4,3);
        $s['columns_mobile']=$this->clampInt($in['setting_columns_mobile']??2,1,2,2);
        $s['spacing_top']=$this->clampInt($in['setting_spacing_top']??54,0,180,54);
        $s['spacing_bottom']=$this->clampInt($in['setting_spacing_bottom']??54,0,180,54);
        if(in_array($type,['brand_grid','notes_grid'],true))$s['marquee_mobile_autoplay']=!empty($in['setting_marquee_mobile_autoplay']);
        $s['product_source']=$this->choice($in['setting_product_source']??'manual',['manual','featured','bestselling','new','sale'],'manual');
        foreach(['show_eyebrow','show_heading','show_subtitle','show_view_all','show_brand','show_price','show_badges','show_wishlist','show_quick_add'] as $k)$s[$k]=!empty($in['setting_'.$k]);
        if(!empty($in['setting_use_custom_colors'])){
            $s['background']=$this->color($in['setting_background']??'') ?: '#fffdfa';
            $s['text_color']=$this->color($in['setting_text_color']??'') ?: '#181613';
        }else{unset($s['background'],$s['text_color']);}
        return array_filter($s,static fn($v,$key)=>($v!==null&&$v!=='')||in_array($key,['eyebrow','view_all_label','view_all_url'],true),ARRAY_FILTER_USE_BOTH);
    }
    private function itemSettings(array $s,array $in): array
    {
        $type=(string)($in['section_type']??'custom');
        $campaign=in_array($type,['hero','editorial_banner','gift_banner'],true);
        $imageCard=in_array($type,['hero','editorial_banner','gift_banner','category_grid','scent_grid','notes_grid','wardrobe_grid','custom'],true);
        $actionCard=in_array($type,['category_grid','scent_grid','custom'],true);
        unset($s['alt_text']);
        if($imageCard){
            $s['focal_position']=$this->choice($in['item_focal_position']??($s['focal_position']??'center'),['center','top','bottom','left','right','top-left','top-right','bottom-left','bottom-right'],'center');
            [$fallbackX,$fallbackY]=$this->focalPreset((string)$s['focal_position']);
            $s['focal_x']=$this->clampInt($in['item_focal_x']??($s['focal_x']??$fallbackX),0,100,$fallbackX);
            $s['focal_y']=$this->clampInt($in['item_focal_y']??($s['focal_y']??$fallbackY),0,100,$fallbackY);
            $s['zoom']=$this->clampInt($in['item_zoom']??($s['zoom']??100),100,220,100);
            if($type!=='notes_grid'){
                $s['mobile_focal_position']=$this->choice($in['item_mobile_focal_position']??($s['mobile_focal_position']??'center'),['center','top','bottom','left','right','top-left','top-right','bottom-left','bottom-right'],'center');
                [$mobileFallbackX,$mobileFallbackY]=$this->focalPreset((string)$s['mobile_focal_position']);
                $s['mobile_focal_x']=$this->clampInt($in['item_mobile_focal_x']??($s['mobile_focal_x']??$mobileFallbackX),0,100,$mobileFallbackX);
                $s['mobile_focal_y']=$this->clampInt($in['item_mobile_focal_y']??($s['mobile_focal_y']??$mobileFallbackY),0,100,$mobileFallbackY);
                $s['mobile_zoom']=$this->clampInt($in['item_mobile_zoom']??($s['mobile_zoom']??100),100,220,100);
            }else unset($s['mobile_focal_position'],$s['mobile_focal_x'],$s['mobile_focal_y']);
        }else unset($s['focal_position'],$s['mobile_focal_position'],$s['focal_x'],$s['focal_y'],$s['mobile_focal_x'],$s['mobile_focal_y'],$s['zoom'],$s['mobile_zoom']);
        if($campaign){
            $s['literal_copy']=true;
            $s['overlay']=$this->clampInt($in['item_overlay']??45,0,80,45);
            $s['text_align']=$this->choice($in['item_text_align']??'left',['left','center','right'],'left');
            $s['text_vertical']=$this->choice($in['item_text_vertical']??'middle',['top','middle','bottom'],'middle');
            $s['desktop_height']=$this->clampInt($in['item_desktop_height']??560,360,900,560);
            $s['mobile_height']=$this->clampInt($in['item_mobile_height']??560,360,900,560);
            $s['text_color']=$this->color($in['item_text_color']??'') ?: '#ffffff';
            $s['button_style']=$this->choice($in['item_button_style']??'gold',['gold','dark','light','paper'],'gold');
            $secondLabel=$this->text($in['item_second_cta_label']??'',100);$secondUrl=$this->safeUrl($in['item_second_cta_url']??'');
            if(($secondLabel===null)!==($secondUrl===null))throw new PublicException('The second button needs both text and a link.');
            $s['second_cta_label']=$secondLabel;$s['second_cta_url']=$secondUrl;
        }else unset($s['literal_copy'],$s['overlay'],$s['text_align'],$s['text_vertical'],$s['desktop_height'],$s['mobile_height'],$s['text_color'],$s['button_style'],$s['second_cta_label'],$s['second_cta_url']);
        if($type==='notes_grid')$s['note_id']=$this->clampInt($in['item_note_id']??($s['note_id']??0),0,PHP_INT_MAX,0);else unset($s['note_id']);
        if($actionCard)$s['show_action']=!array_key_exists('item_show_action',$in)||!empty($in['item_show_action']);
        else unset($s['show_action']);
        return array_filter($s,static fn($v)=>$v!==null&&$v!=='');
    }

    private function focalPreset(string $preset): array
    {
        return match($preset){
            'top'=>[50,15],'bottom'=>[50,85],'left'=>[15,50],'right'=>[85,50],
            'top-left'=>[15,15],'top-right'=>[85,15],'bottom-left'=>[15,85],'bottom-right'=>[85,85],
            default=>[50,50],
        };
    }
    private function defaultMax(string $type): int {return match($type){'category_grid','scent_grid','wardrobe_grid'=>8,'notes_grid','brand_grid'=>12,'product_carousel'=>5,'custom'=>6,'department_grid'=>12,'audience_grid'=>3,default=>1};}
    private function defaultColumns(string $type): int {return match($type){'category_grid'=>6,'product_carousel','scent_grid','wardrobe_grid'=>5,'notes_grid','brand_grid'=>6,'custom'=>4,'department_grid'=>4,'audience_grid'=>3,default=>1};}
    private function text(mixed $v,int $m): ?string {$s=trim((string)$v);return $s===''?null:mb_substr($s,0,$m);}
    private function id(mixed $v): ?int {$i=(int)$v;return $i>0?$i:null;}
    private function slug(string $v): string {$v=strtolower(trim($v));$v=preg_replace('/[^a-z0-9]+/','-',$v)??'';return trim($v,'-');}
    private function choice(mixed $v,array $allowed,string $fallback): string {$v=(string)$v;return in_array($v,$allowed,true)?$v:$fallback;}
    private function clampInt(mixed $v,int $min,int $max,int $fallback): int {if(!is_numeric($v))return $fallback;return max($min,min($max,(int)$v));}
    private function color(mixed $v): ?string {$v=trim((string)$v);if($v==='')return null;return preg_match('/^#[0-9a-fA-F]{6}$/',$v)?strtolower($v):null;}
    private function safeUrl(mixed $v): ?string {
        $url=trim((string)$v);if($url==='')return null;$url=mb_substr($url,0,500);
        if(str_starts_with($url,'/')&&!str_starts_with($url,'//'))return $url;
        if(preg_match('#^https://#i',$url)===1&&filter_var($url,FILTER_VALIDATE_URL)!==false)return $url;
        throw new PublicException('Homepage links must use a local /path or a full https:// URL.');
    }
    private function decode(string $v): array {$v=trim($v);if($v==='')return [];$d=json_decode($v,true);if(!is_array($d))throw new PublicException('Saved design settings are invalid.');return $d;}
    private function encode(array $v): ?string {return $v?json_encode($v,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE):null;}
}
