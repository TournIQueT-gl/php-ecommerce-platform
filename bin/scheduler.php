<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../includes/bootstrap.php';

use App\Support\ServiceFactory;

try {
    $result=ServiceFactory::scheduler(db())->runDue();
    echo json_encode(['time_utc'=>gmdate('c'),'tasks'=>$result],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES).PHP_EOL;
    $failed=array_filter($result,static fn(array $r):bool=>($r['status']??'')==='failed');
    exit($failed?1:0);
} catch (Throwable $e) {
    fwrite(STDERR,'Scheduler failed: '.$e->getMessage().PHP_EOL);
    exit(1);
}
