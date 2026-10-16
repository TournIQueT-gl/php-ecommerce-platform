<?php
declare(strict_types=1);
namespace App\Support;

/** Keep the displayed selling price, struck-through price, and currency on ONE SKU. */
final class VariantOffer
{
    public static function effectivePriceSql(string $alias='v'): string
    {
        return "CASE WHEN {$alias}.sale_price IS NOT NULL AND {$alias}.sale_price>0 AND {$alias}.sale_price<{$alias}.price THEN {$alias}.sale_price ELSE {$alias}.price END";
    }

    /** $productAlias is a trusted SQL identifier supplied by repository code, not user input. */
    public static function sql(string $productAlias,string $field,bool $inStockOnly=false): string
    {
        if (!in_array($productAlias,['p'],true))throw new \InvalidArgumentException('Unexpected product alias.');
        $effective=self::effectivePriceSql();
        $expression=match($field){
            'price'=>$effective,
            'regular_price'=>'v.price',
            'currency'=>'v.currency',
            'variant_id'=>'v.id',
            'variant_label'=>'v.label',
            default=>throw new \InvalidArgumentException('Unexpected offer field.'),
        };
        $stockFilter=$inStockOnly?' AND v.stock_qty>0':'';
        return "(SELECT {$expression} FROM store_product_variants v WHERE v.product_id={$productAlias}.id AND v.status='active'{$stockFilter} ORDER BY CASE WHEN v.stock_qty>0 THEN 0 ELSE 1 END, {$effective}, v.sort_order, v.id LIMIT 1)";
    }

    /** Pure implementation of the SQL offer rule, for behavioral tests. */
    public static function choose(array $variants): ?array
    {
        $valid=array_values(array_filter($variants,static fn(array $v):bool=>($v['status']??'active')==='active' && (float)($v['price']??0)>0));
        usort($valid,static function(array $a,array $b):int {
            $available=(int)((int)($b['stock_qty']??0)>0)<=>(int)((int)($a['stock_qty']??0)>0);
            if($available!==0)return $available;
            $unit=static function(array $v):float{$p=(float)$v['price'];$s=(float)($v['sale_price']??0);return $s>0&&$s<$p?$s:$p;};
            return ($unit($a)<=>$unit($b)) ?: ((int)($a['sort_order']??0)<=>(int)($b['sort_order']??0)) ?: ((int)($a['id']??0)<=>(int)($b['id']??0));
        });
        if(!$valid)return null;
        $v=$valid[0];$regular=(float)$v['price'];$sale=(float)($v['sale_price']??0);
        return ['variant_id'=>(int)($v['id']??0),'price'=>($sale>0&&$sale<$regular)?$sale:$regular,'regular_price'=>$regular,'currency'=>(string)($v['currency']??'EGP')];
    }
}
