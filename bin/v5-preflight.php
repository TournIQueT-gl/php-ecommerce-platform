<?php
declare(strict_types=1);
/**
 * Read-only inventory/order integrity fingerprint for a cloned Repair 18 database.
 * No PII, raw row data, credentials, or SQL writes are emitted.
 * Run: php bin/v5-preflight.php > preflight-before.json
 * Do NOT place report in a web-accessible directory.
 */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__.'/../config/database.php';

try {
    $conn=db();
    $scalar=static function(string $sql) use ($conn): string {return (string)$conn->query($sql)->fetchColumn();};
    $tables=['store_products','store_product_variants','store_orders','store_order_items','store_inventory_movements','store_reviews','store_categories','store_brands'];
    $tablesQuoted="'".implode("','",$tables)."'";
    $available=$conn->query("SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME IN ({$tablesQuoted})")->fetchAll(PDO::FETCH_COLUMN);
    $missing=array_values(array_diff($tables,$available));
    if ($missing) throw new RuntimeException('Missing required commerce tables: '.implode(', ',$missing));

    $counts=[];
    foreach($tables as $table)$counts[$table]=(int)$scalar("SELECT COUNT(*) FROM {$table}");
    $status=[];
    foreach(['store_products','store_product_variants'] as $table){
        $status[$table]=[];
        foreach($conn->query("SELECT status,COUNT(*) AS count FROM {$table} GROUP BY status ORDER BY status")->fetchAll(PDO::FETCH_ASSOC) as $row){
            $status[$table][(string)$row['status']]=(int)$row['count'];
        }
    }
    $checks=[
        'variants_without_product'=>(int)$scalar('SELECT COUNT(*) FROM store_product_variants v LEFT JOIN store_products p ON p.id=v.product_id WHERE p.id IS NULL'),
        'movements_without_variant'=>(int)$scalar('SELECT COUNT(*) FROM store_inventory_movements m LEFT JOIN store_product_variants v ON v.id=m.variant_id WHERE v.id IS NULL'),
        'duplicate_variant_skus'=>(int)$scalar('SELECT COUNT(*) FROM (SELECT sku FROM store_product_variants GROUP BY sku HAVING COUNT(*)>1) duplicates'),
        'negative_stock_variants'=>(int)$scalar('SELECT COUNT(*) FROM store_product_variants WHERE stock_qty<0 OR reserved_qty<0'),
        'reserved_exceeds_stock'=>(int)$scalar('SELECT COUNT(*) FROM store_product_variants WHERE reserved_qty>stock_qty'),
        'orders_without_items'=>(int)$scalar('SELECT COUNT(*) FROM store_orders o WHERE NOT EXISTS (SELECT 1 FROM store_order_items i WHERE i.order_id=o.id)'),
    ];
    // Preserve exact money amounts as strings, to avoid floating point drift in before/after comparisons.
    $sums=[
        'stock_units'=>$scalar('SELECT COALESCE(SUM(stock_qty),0) FROM store_product_variants'),
        'reserved_units'=>$scalar('SELECT COALESCE(SUM(reserved_qty),0) FROM store_product_variants'),
        'inventory_movement_units'=>$scalar('SELECT COALESCE(SUM(quantity_delta),0) FROM store_inventory_movements'),
    ];
    $byCurrency=[];
    $rows=$conn->query('SELECT currency,COUNT(*) AS count,COALESCE(SUM(grand_total),0) AS amount FROM store_orders GROUP BY currency ORDER BY currency')->fetchAll(PDO::FETCH_ASSOC);
    foreach($rows as $row)$byCurrency[(string)$row['currency']]=['orders'=>(int)$row['count'],'order_total'=>(string)$row['amount']];
    $schemaTracked=(int)$scalar("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='schema_migrations'")===1;
    $auditPayload=['counts'=>$counts,'statuses'=>$status,'balances'=>$sums,'orders_by_currency'=>$byCurrency,'integrity_warnings'=>$checks];
    $result=[
        'report_version'=>'v5-alpha1-preflight-1',
        'generated_utc'=>gmdate('c'),
        'schema'=>['migration_count'=>$schemaTracked?(int)$scalar('SELECT COUNT(*) FROM schema_migrations'):0,
                   'latest_migration'=>$schemaTracked?$scalar('SELECT COALESCE(MAX(version),\'\') FROM schema_migrations'):null],
        'counts'=>$counts,
        'statuses'=>$status,
        'balances'=>$sums,
        'orders_by_currency'=>$byCurrency,
        'integrity_warnings'=>$checks,
        'integrity_fingerprint_sha256'=>hash('sha256',json_encode($auditPayload,JSON_THROW_ON_ERROR)),
        'note'=>'Counts and aggregate values only. Compare before/after on the SAME database clone. No changes were made.',
    ];
    echo json_encode($result,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR).PHP_EOL;
} catch (Throwable $e) {
    fwrite(STDERR,'Preflight failed: '.$e->getMessage().PHP_EOL);
    exit(1);
}
