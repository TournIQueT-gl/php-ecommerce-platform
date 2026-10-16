<?php
declare(strict_types=1);
namespace App\Services;
use App\Support\PublicException;
final class StoreSettingsService
{
    private const LINK_TARGETS=['home','catalog','men','women','unisex','niche','arabian','gifts','offers','new','bestselling','wishlist','account','support','shipping','returns','faq','privacy','custom'];

    public function normalize(array $in): array
    {
        $name=trim((string)($in['store_name']??''));if($name===''||mb_strlen($name)>80)throw new PublicException('Store name is required and must be 80 characters or fewer.');
        $tagline=$this->text($in['store_tagline']??'',180);$currency=strtoupper(trim((string)($in['currency']??'EGP')));if(!preg_match('/^[A-Z]{3}$/',$currency))throw new PublicException('Currency must be a three-letter code such as EGP.');
        $threshold=trim((string)($in['free_shipping_threshold']??'0'));if(!is_numeric($threshold)||(float)$threshold<0)throw new PublicException('Free-shipping threshold must be zero or greater.');
        $shippingFee=trim((string)($in['standard_shipping_fee']??'0'));if(!is_numeric($shippingFee)||(float)$shippingFee<0)throw new PublicException('Standard delivery fee must be zero or greater.');
        $returnWindow=trim((string)($in['return_window_days']??'14'));if(!ctype_digit($returnWindow)||(int)$returnWindow<1||(int)$returnWindow>90)throw new PublicException('Return window must be between 1 and 90 days.');
        $codEnabled=!empty($in['cod_enabled']);$manualTransferEnabled=!empty($in['manual_transfer_enabled']);
        if(!$codEnabled&&!$manualTransferEnabled)throw new PublicException('Keep at least one checkout payment method enabled.');
        $social=[];foreach(['instagram','facebook','tiktok','youtube'] as $network){$value=trim((string)($in['social_'.$network]??''));if($value!==''&&!filter_var($value,FILTER_VALIDATE_URL))throw new PublicException(ucfirst($network).' must be a valid URL.');if($value!==''&&!preg_match('~^https://~i',$value))throw new PublicException(ucfirst($network).' must use https://.');$social['social_'.$network]=$value;}
        $whatsapp=trim((string)($in['social_whatsapp']??''));if($whatsapp!==''&&filter_var($whatsapp,FILTER_VALIDATE_URL)){if(!preg_match('~^https://~i',$whatsapp))throw new PublicException('WhatsApp URL must use https://.');}elseif($whatsapp!==''){$digits=preg_replace('/\D+/','',$whatsapp);if($digits===''||strlen($digits)<8||strlen($digits)>15)throw new PublicException('WhatsApp must be a wa.me URL or a valid international phone number.');$whatsapp='https://wa.me/'.$digits;}$social['social_whatsapp']=$whatsapp;

        $navigation=$this->links($in,'navigation',14,'primary navigation');
        $hasLegacyNavigation=array_key_exists('navigation_label',$in);
        if($hasLegacyNavigation&&!$navigation)throw new PublicException('Keep at least one storefront navigation link.');
        $footer=[
            'shop'=>$this->links($in,'footer_shop',12,'Shop footer'),
            'discover'=>$this->links($in,'footer_discover',12,'Discover footer'),
            'help'=>$this->links($in,'footer_help',12,'Help footer'),
        ];

        $design=[
            'logo_subtitle'=>$this->text($in['logo_subtitle']??'ONLINE STORE',30),
            'utility_delivery_text'=>$this->text($in['utility_delivery_text']??'',100),
            'utility_authentic_text'=>$this->text($in['utility_authentic_text']??'100% Authentic Products',100),
            'utility_returns_text'=>$this->text($in['utility_returns_text']??'Order Support',100),
            // Legacy labels remain for old deployments/settings exports. New rendering uses navigation_json.
            'nav_men_label'=>$this->text($in['nav_men_label']??'Men',40)?:'Men','nav_women_label'=>$this->text($in['nav_women_label']??'Women',40)?:'Women','nav_unisex_label'=>$this->text($in['nav_unisex_label']??'Unisex',40)?:'Unisex','nav_niche_label'=>$this->text($in['nav_niche_label']??'Niche',40)?:'Niche','nav_arabian_label'=>$this->text($in['nav_arabian_label']??'Arabian Perfumes',40)?:'Arabian Perfumes','nav_gifts_label'=>$this->text($in['nav_gifts_label']??'Gifts',40)?:'Gifts','nav_offers_label'=>$this->text($in['nav_offers_label']??'Offers',40)?:'Offers',
            // Legacy navigation is now read-only; managed by Navigation Builder.

            'footer_links_json'=>json_encode($footer,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE),
            'footer_about'=>$this->text($in['footer_about']??'Discover something great for every occasion.',240),'footer_shop_title'=>$this->text($in['footer_shop_title']??'SHOP',40),'footer_discover_title'=>$this->text($in['footer_discover_title']??'DISCOVER',40),'footer_help_title'=>$this->text($in['footer_help_title']??'HELP',40),'footer_country_label'=>$this->text($in['footer_country_label']??'Egypt',60),
            'copyright_owner'=>$this->text($in['copyright_owner']??$name,100)?:$name,
            'copyright_suffix'=>$this->text($in['copyright_suffix']??'All rights reserved.',120),
            'copyright_auto_year'=>!empty($in['copyright_auto_year'])?'1':'0',
            'copyright_year'=>(string)$this->boundedYear($in['copyright_year']??date('Y')),
            'developer_name'=>$this->text($in['developer_name']??'',100),
            'developer_url'=>$this->optionalUrl((string)($in['developer_url']??''),'Developer link'),
            'show_developer_credit'=>!empty($in['show_developer_credit'])?'1':'0',
            'show_utility_bar'=>!empty($in['show_utility_bar'])?'1':'0',
            'footer_show_brand'=>!empty($in['footer_show_brand'])?'1':'0',
            'footer_show_shop'=>!empty($in['footer_show_shop'])?'1':'0',
            'footer_show_discover'=>!empty($in['footer_show_discover'])?'1':'0',
            'footer_show_help'=>!empty($in['footer_show_help'])?'1':'0',
            'footer_show_locale'=>!empty($in['footer_show_locale'])?'1':'0',
            'footer_show_copyright'=>!empty($in['footer_show_copyright'])?'1':'0',
            'design_accent'=>$this->color($in['design_accent']??'#b08a4c','#b08a4c'),'design_background'=>$this->color($in['design_background']??'#f7f4ee','#f7f4ee'),'design_text'=>$this->color($in['design_text']??'#181613','#181613'),'design_footer'=>$this->color($in['design_footer']??'#171512','#171512'),
            'card_radius'=>$this->choice($in['card_radius']??'2',['0','2','8','16'],'2'),
            'search_suggestions'=>$this->list($in['search_suggestions']??'',8,60),
            'page_shipping_heading'=>$this->text($in['page_shipping_heading']??'Shipping, without the guessing.',120)?:'Shipping, without the guessing.',
            'page_shipping_intro'=>$this->text($in['page_shipping_intro']??'See the delivery rules currently configured for the store before you place an order.',240)?:'See the delivery rules currently configured for the store before you place an order.',
            'page_returns_heading'=>$this->text($in['page_returns_heading']??'Clear steps when an order needs to come back.',120)?:'Clear steps when an order needs to come back.',
            'page_returns_intro'=>$this->text($in['page_returns_intro']??'',240),
            'page_faq_heading'=>$this->text($in['page_faq_heading']??'Useful answers, without the fine-print maze.',120)?:'Useful answers, without the fine-print maze.',
            'page_faq_intro'=>$this->text($in['page_faq_intro']??'Shopping, delivery, accounts and support in plain language.',240)?:'Shopping, delivery, accounts and support in plain language.',
            'page_privacy_heading'=>$this->text($in['page_privacy_heading']??'Your information, handled with restraint.',120)?:'Your information, handled with restraint.',
            'page_privacy_intro'=>$this->text($in['page_privacy_intro']??'We use customer and first-party operational data to run the store, fulfill orders, protect accounts and understand how the storefront performs.',300)?:'We use customer and first-party operational data to run the store, fulfill orders, protect accounts and understand how the storefront performs.',
            'page_support_heading'=>$this->text($in['page_support_heading']??'How can we help?',120)?:'How can we help?',
            'page_support_intro'=>$this->text($in['page_support_intro']??'Guest checkout is supported. You do not need an account to contact us.',240)?:'Guest checkout is supported. You do not need an account to contact us.',
            'page_shipping_blocks_json'=>$this->contentBlocks($in,'page_shipping',8),
            'page_returns_blocks_json'=>$this->contentBlocks($in,'page_returns',8),
            'page_privacy_blocks_json'=>$this->contentBlocks($in,'page_privacy',10),
            'page_support_blocks_json'=>$this->contentBlocks($in,'page_support',8),
            'page_faq_items_json'=>$this->faqItems($in,16),
        ];
        return array_merge(['store_name'=>$name,'store_tagline'=>$tagline,'currency'=>$currency,'free_shipping_threshold'=>number_format((float)$threshold,2,'.',''),'standard_shipping_fee'=>number_format((float)$shippingFee,2,'.',''),'return_window_days'=>(string)(int)$returnWindow,'cod_enabled'=>$codEnabled?'1':'0','manual_transfer_enabled'=>$manualTransferEnabled?'1':'0','catalog_enabled'=>!empty($in['catalog_enabled'])?'1':'0'],$social,$design);
    }

    private function contentBlocks(array $in,string $prefix,int $max): string
    {
        $heads=$in[$prefix.'_block_heading']??[];$bodies=$in[$prefix.'_block_body']??[];
        if(!is_array($heads)||!is_array($bodies))return '[]';
        $out=[];$count=min($max,max(count($heads),count($bodies)));
        for($i=0;$i<$count;$i++){
            $heading=$this->text($heads[$i]??'',120);$body=$this->text($bodies[$i]??'',1800);
            if($heading===''&&$body==='')continue;
            if($heading===''||$body==='')throw new PublicException('Every public-page section needs both a heading and content.');
            $out[]=['heading'=>$heading,'body'=>$body];
        }
        return json_encode($out,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE) ?: '[]';
    }
    private function faqItems(array $in,int $max): string
    {
        $questions=$in['faq_question']??[];$answers=$in['faq_answer']??[];
        if(!is_array($questions)||!is_array($answers))return '[]';
        $out=[];$count=min($max,max(count($questions),count($answers)));
        for($i=0;$i<$count;$i++){
            $question=$this->text($questions[$i]??'',180);$answer=$this->text($answers[$i]??'',1800);
            if($question===''&&$answer==='')continue;
            if($question===''||$answer==='')throw new PublicException('Every FAQ item needs both a question and an answer.');
            $out[]=['question'=>$question,'answer'=>$answer];
        }
        return json_encode($out,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE) ?: '[]';
    }
    private function boundedYear(mixed $value): int
    {
        $year=(int)$value;$current=(int)date('Y');return ($year>=2000&&$year<=$current+2)?$year:$current;
    }
    private function optionalUrl(string $url,string $context): string
    {
        $url=trim($url);return $url===''?'':$this->customUrl($url,$context);
    }

    private function links(array $in,string $prefix,int $max,string $context): array
    {
        $labels=$in[$prefix.'_label']??[];$targets=$in[$prefix.'_target']??[];$urls=$in[$prefix.'_url']??[];
        if(!is_array($labels)||!is_array($targets)||!is_array($urls))return [];
        $out=[];$count=min($max,max(count($labels),count($targets),count($urls)));
        for($i=0;$i<$count;$i++){
            $label=$this->text($labels[$i]??'',50);$target=(string)($targets[$i]??'catalog');$url=trim((string)($urls[$i]??''));
            if($label==='')continue;
            if(!in_array($target,self::LINK_TARGETS,true))throw new PublicException($context.' contains an invalid destination.');
            if($target==='custom')$url=$this->customUrl($url,$context.' link “'.$label.'”');else $url='';
            $out[]=['label'=>$label,'target'=>$target,'url'=>$url];
        }
        return $out;
    }

    private function customUrl(string $url,string $context): string
    {
        $url=trim(str_replace(["\r","\n"],'',$url));
        if($url==='')throw new PublicException($context.' needs a URL.');
        if(mb_strlen($url)>500)throw new PublicException($context.' URL is too long.');
        if(preg_match('~^https://~i',$url)){if(filter_var($url,FILTER_VALIDATE_URL)===false)throw new PublicException($context.' must use a valid HTTPS URL.');return $url;}
        if(str_contains($url,'://')||str_starts_with($url,'//'))throw new PublicException($context.' must use a local path or HTTPS URL.');
        if(!preg_match('~^/?[A-Za-z0-9._~/%+?=&;,#-]+$~',$url))throw new PublicException($context.' contains unsupported URL characters.');
        return $url;
    }

    private function text(mixed $v,int $max): string {$v=trim((string)$v);return mb_substr($v,0,$max);}
    private function color(mixed $v,string $fallback): string {$v=trim((string)$v);return preg_match('/^#[0-9a-fA-F]{6}$/',$v)?strtolower($v):$fallback;}
    private function choice(mixed $v,array $allowed,string $fallback): string {$v=(string)$v;return in_array($v,$allowed,true)?$v:$fallback;}
    private function list(mixed $v,int $maxItems,int $maxLen): string {
        $parts=preg_split('/[\r\n,]+/',(string)$v)?:[];$out=[];
        foreach($parts as $part){$part=trim($part);if($part==='')continue;$out[]=mb_substr($part,0,$maxLen);if(count($out)>=$maxItems)break;}
        return implode("\n",array_values(array_unique($out)));
    }
}
