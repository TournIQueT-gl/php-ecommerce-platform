<?php
declare(strict_types=1);
namespace App\Catalog;

use App\Support\PublicException;

/** Pure, bounded, deterministic Cartesian option combinations. */
final class VariantMatrix
{
    public static function expand(array $selections, int $limit=60): array
    {
        if ($limit<1 || $limit>100) throw new PublicException('Invalid combination limit.');
        if (!$selections) return [[]]; // Standard (single-SKU) product
        if (count($selections)>6) throw new PublicException('Maximum six variant attributes per category.');
        $normalized=[];$total=1;
        foreach ($selections as $attributeId=>$ids) {
            $attributeId=(int)$attributeId;
            if($attributeId<1 || !is_array($ids)) throw new PublicException('Invalid variant attribute selection.');
            $values=[];
            foreach ($ids as $optionId) {
                if(!is_scalar($optionId) || !preg_match('/^[1-9][0-9]*$/',(string)$optionId))throw new PublicException('Invalid option selection.');
                $values[]=(int)$optionId;
            }
            $values=array_values(array_unique($values));sort($values,SORT_NUMERIC);
            if(!$values)throw new PublicException('Choose at least one value for every variant attribute.');
            $total*=count($values);
            if($total>$limit)throw new PublicException('Too many variant combinations (maximum '.$limit.' per action).');
            $normalized[$attributeId]=$values;
        }
        ksort($normalized,SORT_NUMERIC);
        $rows=[[]];
        foreach($normalized as $attributeId=>$values){
            $next=[];
            foreach($rows as $row)foreach($values as $id)$next[]=$row+[$attributeId=>$id];
            $rows=$next;
        }
        return $rows;
    }
    public static function signature(array $codeOptions): string
    {
        if (!$codeOptions) return hash('sha256','universal:standard:v1');
        return LegacyPerfumeMapper::signature($codeOptions);
    }
}
