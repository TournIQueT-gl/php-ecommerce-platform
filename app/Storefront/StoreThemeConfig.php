<?php
declare(strict_types=1);
namespace App\Storefront;

/** Whitelisted presentation-only theme profile. Never controls sales, payments, or access. */
final class StoreThemeConfig
{
    private const PRESETS = [
        'neutral'=>['accent'=>'#8e694c','background'=>'#f7f5f2','text'=>'#211d19','footer'=>'#201c1a','radius'=>8,'font'=>'modern','density'=>'comfortable'],
        'perfume'=>['accent'=>'#a27c4b','background'=>'#f7f4ee','text'=>'#181613','footer'=>'#171512','radius'=>2,'font'=>'editorial','density'=>'comfortable'],
        'fashion'=>['accent'=>'#353535','background'=>'#f7f7f5','text'=>'#181818','footer'=>'#171717','radius'=>0,'font'=>'modern','density'=>'spacious'],
        'beauty'=>['accent'=>'#9e5970','background'=>'#fbf5f5','text'=>'#2e2428','footer'=>'#35252d','radius'=>16,'font'=>'editorial','density'=>'comfortable'],
        'electronics'=>['accent'=>'#315bc0','background'=>'#f3f6fc','text'=>'#17233d','footer'=>'#131d30','radius'=>8,'font'=>'modern','density'=>'compact'],
    ];
    public static function presets(): array { return self::PRESETS; }
    public static function defaults(array $settings=[]): array
    {
        return [
            'preset'=>'neutral',
            'store_name'=>self::text($settings['store_name']??'Your Store',70,'Your Store'),
            'tagline'=>self::text($settings['store_tagline']??'Discover more, every day.',180,'Discover more, every day.'),
            'logo_subtitle'=>self::text($settings['logo_subtitle']??'',30,''),
            'show_utility_bar'=>(string)($settings['show_utility_bar']??'1')==='1',
            'accent'=>self::colorOr($settings['design_accent']??null,'#8e694c'),
            'background'=>self::colorOr($settings['design_background']??null,'#f7f5f2'),
            'text'=>self::colorOr($settings['design_text']??null,'#211d19'),
            'footer'=>self::colorOr($settings['design_footer']??null,'#201c1a'),
            'radius'=>in_array((int)($settings['card_radius']??8),[0,2,8,16],true)?(int)($settings['card_radius']??8):8,
            'font'=>'editorial', 'density'=>'comfortable', 'storefront_layout'=>'classic',
            'content_width'=>'standard', 'catalog_columns'=>0, 'product_image_shape'=>'square', 'product_image_fit'=>'contain', 'navigation_style'=>'minimal',
            'header_layout'=>'balanced', 'header_density'=>'comfortable', 'sticky_header'=>true,
        ];
    }
    public static function decode(mixed $raw): ?array
    {
        if(!is_string($raw)||trim($raw)==='')return null;
        $input=json_decode($raw,true);
        if(!is_array($input))return null;
        try {return self::sanitize($input);} catch(\InvalidArgumentException) {return null;}
    }
    public static function sanitize(array $input): array
    {
        $preset=(string)($input['preset']??'neutral');
        if(!isset(self::PRESETS[$preset]))throw new \InvalidArgumentException('Select a valid theme preset.');
        $base=self::PRESETS[$preset];
        $name=self::text($input['store_name']??'',70,'');
        if($name==='')throw new \InvalidArgumentException('Store name is required.');
        $tagline=self::text($input['tagline']??'',180,'');
        $subtitle=self::text($input['logo_subtitle']??'',30,'');
        $colors=[];
        foreach(['accent','background','text','footer'] as $field){
            $value=strtolower(trim((string)($input[$field]??$base[$field])));
            if(!preg_match('/^#[0-9a-f]{6}$/D',$value))throw new \InvalidArgumentException('Invalid '.$field.' colour. Use a six-digit hex value.');
            $colors[$field]=$value;
        }
        // Contrast safety for body text against its background (WCAG AA for normal text).
        if(self::contrast($colors['text'],$colors['background'])<4.5)throw new \InvalidArgumentException('Text/background contrast must be at least 4.5:1.');
        $radius=(int)($input['radius']??$base['radius']);
        if(!in_array($radius,[0,2,8,16],true))throw new \InvalidArgumentException('Unsupported card corner size.');
        $font=(string)($input['font']??$base['font']);
        if(!in_array($font,['editorial','modern','system'],true))throw new \InvalidArgumentException('Unsupported font style.');
        $density=(string)($input['density']??$base['density']);
        if(!in_array($density,['compact','comfortable','spacious'],true))throw new \InvalidArgumentException('Unsupported layout density.');
        $storefrontLayout=(string)($input['storefront_layout']??'classic');
        if(!in_array($storefrontLayout,['classic','editorial','boutique','catalog'],true))throw new \InvalidArgumentException('Unsupported storefront layout.');
        $contentWidth=(string)($input['content_width']??'standard');
        if(!in_array($contentWidth,['standard','contained','wide'],true))throw new \InvalidArgumentException('Unsupported content width.');
        $catalogColumns=(int)($input['catalog_columns']??0);
        if(!in_array($catalogColumns,[0,3,4,5],true))throw new \InvalidArgumentException('Unsupported catalog column count.');
        $imageShape=(string)($input['product_image_shape']??'square');
        if(!in_array($imageShape,['square','portrait','landscape'],true))throw new \InvalidArgumentException('Unsupported product image shape.');
        $imageFit=(string)($input['product_image_fit']??'contain');
        if(!in_array($imageFit,['contain','cover'],true))throw new \InvalidArgumentException('Unsupported product image fit.');
        $navigationStyle=(string)($input['navigation_style']??'minimal');
        if(!in_array($navigationStyle,['minimal','underline'],true))throw new \InvalidArgumentException('Unsupported navigation style.');
        $headerLayout=(string)($input['header_layout']??'balanced');
        if(!in_array($headerLayout,['balanced','compact','centered'],true))throw new \InvalidArgumentException('Unsupported header layout.');
        $headerDensity=(string)($input['header_density']??'comfortable');
        if(!in_array($headerDensity,['compact','comfortable'],true))throw new \InvalidArgumentException('Unsupported navigation density.');
        return array_merge(['content_width'=>$contentWidth,'catalog_columns'=>$catalogColumns,'product_image_shape'=>$imageShape,'product_image_fit'=>$imageFit,'navigation_style'=>$navigationStyle,'storefront_layout'=>$storefrontLayout,'header_layout'=>$headerLayout,'header_density'=>$headerDensity,'sticky_header'=>self::flag($input['sticky_header']??true),'preset'=>$preset,'store_name'=>$name,'tagline'=>$tagline,'logo_subtitle'=>$subtitle,'show_utility_bar'=>self::flag($input['show_utility_bar']??false)],$colors,['radius'=>$radius,'font'=>$font,'density'=>$density]);
    }
    public static function fromPost(array $post): array
    {
        return self::sanitize(['preset'=>$post['preset']??'neutral','store_name'=>$post['store_name']??'',
            'tagline'=>$post['tagline']??'','logo_subtitle'=>$post['logo_subtitle']??'',
            'show_utility_bar'=>isset($post['show_utility_bar']),
            'accent'=>$post['accent']??'', 'background'=>$post['background']??'', 'text'=>$post['text']??'',
            'footer'=>$post['footer']??'', 'radius'=>$post['radius']??'', 'font'=>$post['font']??'', 'density'=>$post['density']??'',
            'storefront_layout'=>$post['storefront_layout']??'classic',
            'content_width'=>$post['content_width']??'standard','catalog_columns'=>$post['catalog_columns']??0,'product_image_shape'=>$post['product_image_shape']??'square','product_image_fit'=>$post['product_image_fit']??'contain','navigation_style'=>$post['navigation_style']??'minimal',
            'header_layout'=>$post['header_layout']??'balanced','header_density'=>$post['header_density']??'comfortable',
            'sticky_header'=>isset($post['sticky_header'])]);
    }
    /** Runtime view of a published theme; legacy database settings remain untouched. */
    public static function overlaySettings(array $settings,?array $theme): array
    {
        if($theme===null)return $settings;
        return array_merge($settings,[
            'store_name'=>$theme['store_name'], 'store_tagline'=>$theme['tagline'],
            'logo_subtitle'=>$theme['logo_subtitle'],
            'design_accent'=>$theme['accent'],'design_background'=>$theme['background'],
            'design_text'=>$theme['text'],'design_footer'=>$theme['footer'],
            'card_radius'=>(string)$theme['radius'],
            'show_utility_bar'=>$theme['show_utility_bar']?'1':'0',
        ]);
    }
    public static function cssVars(array $theme): string
    {
        // Only call with sanitized data. No arbitrary CSS/HTML is accepted.
        return '--gold:'.$theme['accent'].';--ivory:'.$theme['background'].';--ink:'.$theme['text'].';--dark:'.$theme['footer'].';--store-card-radius:'.$theme['radius'].'px';
    }
    private static function flag(mixed $v): bool {return $v===true||$v===1||$v==='1'||$v==='on';}
    private static function text(mixed $v,int $limit,string $fallback): string
    {
        $s=trim((string)$v);$s=preg_replace('/[\x00-\x1f\x7f]/u','',$s)??'';
        $s=function_exists('mb_substr')?mb_substr($s,0,$limit):substr($s,0,$limit);
        return $s!==''?$s:$fallback;
    }
    private static function colorOr(mixed $value,string $fallback):string
    {
        $s=strtolower(trim((string)$value));return preg_match('/^#[0-9a-f]{6}$/D',$s)?$s:$fallback;
    }
    private static function contrast(string $a,string $b):float
    {
        $lum=static function(string $hex):float {
            $c=[];foreach([1,3,5] as $offset){$v=hexdec(substr($hex,$offset,2))/255;$c[]=$v<=.04045?$v/12.92:(($v+.055)/1.055)**2.4;}
            return .2126*$c[0]+.7152*$c[1]+.0722*$c[2];
        };
        $x=$lum($a);$y=$lum($b);return (max($x,$y)+.05)/(min($x,$y)+.05);
    }
}
