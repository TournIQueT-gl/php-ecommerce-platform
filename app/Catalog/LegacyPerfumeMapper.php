<?php
declare(strict_types=1);

namespace App\Catalog;

use InvalidArgumentException;

/** Pure, deterministic value mapping for an opt-in legacy perfume backfill. */
final class LegacyPerfumeMapper
{
    public const SCORE_COLUMNS = [
        'longevity_score','projection_score','sillage_score','fresh_score',
        'sweet_score','woody_score','spicy_score',
    ];

    /** 50, 50.00 and 050.0000 all map to the same option. */
    public static function volume(?string $raw): ?array
    {
        if ($raw === null || trim($raw) === '') return null;
        $value = trim($raw);
        if (!preg_match('/^\d{1,8}(?:\.\d{1,4})?$/D', $value)) throw new InvalidArgumentException('Invalid legacy volume');
        [$whole, $fraction] = array_pad(explode('.', $value, 2), 2, '');
        $whole = ltrim($whole, '0');
        $whole = $whole === '' ? '0' : $whole;
        $fraction = rtrim($fraction, '0');
        $normalized = $whole . ($fraction !== '' ? '.'.$fraction : '');
        if ($normalized === '0') return null;
        return ['code' => 'ml_'.str_replace('.', '_', $normalized),
                'label' => $normalized.' ml', 'numeric_value' => $normalized];
    }

    public static function textOption(string $prefix, string $value): array
    {
        $value = trim($value);
        if ($value === '') throw new InvalidArgumentException('Blank option label');
        if ((function_exists('mb_strlen') ? mb_strlen($value, 'UTF-8') : (preg_match_all('/./us', $value) ?: 0)) > 160) throw new InvalidArgumentException('Option label exceeds 160 characters');
        return ['code' => $prefix.'_'.substr(hash('sha256', $value), 0, 28), 'label' => $value, 'numeric_value' => null];
    }

    /**
     * Analyze existing variant identifiers without a database. Duplicate volumes on
     * the same product cannot be assigned a unique signature until another axis
     * (for example color or packaging) has been identified by a human.
     * @param array<int,array<string,mixed>> $variants
     * @return array{mapped:array<int,array<string,mixed>>,conflicts:list<string>}
     */
    public static function analyzeLegacyVariants(array $variants): array
    {
        $mapped=[];$conflicts=[];$seen=[];$existing=[];
        foreach ($variants as $v) {
            $id=(int)$v['id'];$productId=(int)$v['product_id'];
            if (!empty($v['option_signature'])) $existing[$productId.':'.$v['option_signature']]=$id;
        }
        foreach ($variants as $v) {
            $id=(int)$v['id'];$productId=(int)$v['product_id'];
            try {$option=self::volume($v['size_ml']!==null?(string)$v['size_ml']:null);}
            catch (InvalidArgumentException) {$conflicts[]='Variant #'.$id.': invalid legacy volume';continue;}
            if ($option===null)continue;
            $signature=self::signature(['volume_ml'=>$option['code']]);
            $key=$productId.':'.$signature;
            if (isset($seen[$key]) && $seen[$key]!==$id) {
                $conflicts[]='Same-volume variants #'.$seen[$key].' and #'.$id.' (product #'.$productId.'); another variant axis is required';
            }
            if (isset($existing[$key]) && $existing[$key]!==$id) {
                $conflicts[]='Variant #'.$id.': signature used by variant #'.$existing[$key];
            }
            if (!empty($v['option_signature']) && $v['option_signature']!==$signature) {
                $conflicts[]='Variant #'.$id.': existing canonical signature differs';
            }
            $seen[$key]=$id;
            $mapped[$id]=['product_id'=>$productId,'option'=>$option,'signature'=>$signature];
        }
        return ['mapped'=>$mapped,'conflicts'=>$conflicts];
    }

    /** Stable, collision-resistant variant signature, independent of UI labels/order. */
    public static function signature(array $axisCodes): string
    {
        if (!$axisCodes) throw new InvalidArgumentException('At least one variant axis required');
        ksort($axisCodes, SORT_STRING);
        foreach ($axisCodes as $key => $code) {
            if (!is_string($key) || !is_string($code) || $key === '' || $code === '') {
                throw new InvalidArgumentException('Invalid variant axis');
            }
        }
        return hash('sha256', json_encode($axisCodes, JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR));
    }
}
