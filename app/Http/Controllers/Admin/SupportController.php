<?php
declare(strict_types=1);
namespace App\Http\Controllers\Admin;
use App\Support\ServiceFactory;
final class SupportController
{
    public function handle(): array
    {
        $status=(string)($_GET['status']??'all');$q=substr(trim((string)($_GET['q']??'')),0,80);$page=max(1,(int)($_GET['page']??1));
        $repo=ServiceFactory::supportRepository(\db());$adminId=(int)($_SESSION['user_id']??0);$r=$repo->adminPage($status,$q,$page,25,$adminId);$stats=$repo->stats();
        return ['tickets'=>$r['rows'],'total'=>$r['total'],'page'=>$r['page'],'pages'=>$r['pages'],'status'=>$status,'q'=>$q,'stats'=>$stats];
    }
}
