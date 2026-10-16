<?php
declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Support\ServiceFactory;

final class ActivityController
{
    public function handle(): array
    {
        $q = substr(trim((string)($_GET['q'] ?? '')), 0, 80);
        $action = substr(trim((string)($_GET['action'] ?? '')), 0, 80);
        $page = max(1, (int)($_GET['page'] ?? 1));
        $perPage = 50;

        $auditRepository = ServiceFactory::audit(\db());
        $result = $auditRepository->page($q, $action, $page, $perPage);
        $rows = $result['rows'];
        $total = (int)$result['total'];
        $page = (int)$result['page'];
        $totalPages = (int)$result['pages'];
        $actions = $auditRepository->actions();
        $pageUrl = static function(int $targetPage) use ($q,$action): string {
            $p = ['page' => max(1,$targetPage)];
            if ($q !== '') $p['q'] = $q;
            if ($action !== '') $p['action'] = $action;
            return \route_url('admin/activity.php') . '?' . http_build_query($p);
        };
        return compact('q','action','page','perPage','rows','total','totalPages','actions','pageUrl');
    }
}
