<?php
declare(strict_types=1);
namespace App\Support;

/** Mapped products in paused departments are not saleable or publicly discoverable.
 * Unmapped legacy products are allowed during controlled migration.
 * Query aliases are PHP-controlled identifiers, never request parameters.
 */
final class DepartmentVisibility
{
    public static function product(string $alias = 'p'): string
    {
        if (!preg_match('/^[a-z_][a-z0-9_]*$/i', $alias)) throw new \InvalidArgumentException('Unsafe SQL alias');
        return "NOT EXISTS (SELECT 1 FROM commerce_product_departments pd_vis JOIN commerce_departments d_vis ON d_vis.id=pd_vis.department_id WHERE pd_vis.product_id={$alias}.id AND d_vis.status<>'active')";
    }
    public static function category(string $alias = 'c'): string
    {
        if (!preg_match('/^[a-z_][a-z0-9_]*$/i', $alias)) throw new \InvalidArgumentException('Unsafe SQL alias');
        return "NOT EXISTS (SELECT 1 FROM commerce_department_categories dc_vis JOIN commerce_departments d_vis ON d_vis.id=dc_vis.department_id WHERE dc_vis.category_id={$alias}.id AND d_vis.status<>'active')";
    }
}
