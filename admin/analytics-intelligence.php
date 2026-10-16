<?php
declare(strict_types=1);
require_once __DIR__.'/../includes/admin.php';

use App\Http\Controllers\Admin\AnalyticsIntelligenceController;

require_admin_capability('analytics_manage');
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
try{
    echo json_encode((new AnalyticsIntelligenceController())->handle(),JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);
}catch(Throwable $e){
    http_response_code(500);
    echo json_encode(['ok'=>false,'error'=>'Visitor intelligence resolver failed.'],JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
}
