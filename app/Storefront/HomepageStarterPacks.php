<?php
declare(strict_types=1);
namespace App\Storefront;
/** Opt-in homepage section plans. They never overwrite an existing homepage. */
final class HomepageStarterPacks
{
    private const PLANS=[
        'classic'=>[['hero','Hero'],['department_grid','Shop by department'],['audience_grid','Shop by audience'],['category_grid','Shop by category'],['product_carousel','Featured products'],['editorial_banner','Editorial banner'],['brand_grid','Shop by brand'],['gift_banner','Gifts']],
        'editorial'=>[['hero','Our story'],['brand_grid','Fragrance houses'],['product_carousel','Signature collection'],['editorial_banner','Behind the collection'],['video','Our story in motion'],['notes_grid','Fragrance notes']],
        'boutique'=>[['hero','New season'],['audience_grid','Shop for everyone'],['category_grid','Explore the collection'],['product_carousel','Just arrived'],['wardrobe_grid','Styled for you'],['editorial_banner','Style journal']],
        'catalog'=>[['department_grid','Shop by department'],['category_grid','Browse categories'],['product_carousel','Popular products'],['brand_grid','Featured brands'],['product_carousel','New arrivals'],['custom','Featured offers']],
    ];
    public static function choices(): array {return ['classic'=>'Classic / General','editorial'=>'Editorial / Perfume','boutique'=>'Boutique / Fashion','catalog'=>'Catalog / Electronics'];}
    public static function plan(string $pack): array
    {
        if(!isset(self::PLANS[$pack]))throw new \InvalidArgumentException('Choose a valid starter homepage layout.');
        $rows=[];
        foreach(self::PLANS[$pack] as $index=>[$type,$title]){
            $rows[]=['section_type'=>$type,'title'=>$title,'section_key'=>$type.'-'.($index+1),'status'=>'draft','sort_order'=>($index+1)*10,'subtitle'=>'','settings_json'=>'','starts_at'=>'','ends_at'=>''];
        }
        return $rows;
    }
}
