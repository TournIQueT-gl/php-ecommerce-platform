<?php
declare(strict_types=1);
namespace App\Storefront;

/** Public merchandising preferences; essential commerce and security cannot be disabled. */
final class StoreModulesConfig
{
    private const MODULES = [
        'wishlist'=>['label'=>'Wishlist','description'=>'Save products to an account wishlist.'],
        'reviews'=>['label'=>'Verified reviews','description'=>'Display reviews and allow verified customers to submit reviews.'],
        'quick_add'=>['label'=>'Quick add','description'=>'Show single-SKU Add to Bag controls on catalog cards.'],
        'recommendations'=>['label'=>'Related products','description'=>'Show related product suggestions on product detail pages.'],
    ];

    public static function definitions(): array {return self::MODULES;}
    public static function defaults(): array {return array_fill_keys(array_keys(self::MODULES),true);}

    public static function sanitize(array $input): array
    {
        $unknown=array_diff(array_keys($input),array_keys(self::MODULES));
        if($unknown)throw new \InvalidArgumentException('Unknown commerce module: '.(string)reset($unknown));
        $clean=[];
        foreach(self::MODULES as $key=>$definition){
            $value=$input[$key]??false;
            if(!in_array($value,[true,false,0,1,'0','1','on'],true))throw new \InvalidArgumentException('Invalid status for '.$definition['label'].'.');
            $clean[$key]=in_array($value,[true,1,'1','on'],true);
        }
        return $clean;
    }

    public static function fromPost(array $post): array
    {
        // Never pass unrelated form fields (including CSRF data) to the module validator.
        $values=[];
        foreach(self::MODULES as $key=>$definition)$values[$key]=isset($post['module'][$key])?$post['module'][$key]:false;
        if(isset($post['module'])&&!is_array($post['module']))throw new \InvalidArgumentException('Invalid module configuration.');
        if(isset($post['module']))foreach(array_keys($post['module']) as $key){
            if(!array_key_exists($key,self::MODULES))throw new \InvalidArgumentException('Unknown commerce module.');
        }
        return self::sanitize($values);
    }

    public static function decode(mixed $raw): ?array
    {
        if(!is_string($raw)||trim($raw)==='')return null;
        $data=json_decode($raw,true);
        if(!is_array($data))return null;
        try {return self::sanitize(array_merge(self::defaults(),$data));}
        catch(\InvalidArgumentException){return null;}
    }

    public static function active(array $settings): array
    {
        return self::decode($settings['v5_modules_published']??'')??self::defaults();
    }

    public static function enabled(array $settings,string $key): bool
    {
        if(!array_key_exists($key,self::MODULES))throw new \InvalidArgumentException('Unknown commerce module.');
        return self::active($settings)[$key];
    }
}
