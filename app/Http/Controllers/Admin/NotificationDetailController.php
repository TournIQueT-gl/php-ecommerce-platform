<?php
declare(strict_types=1);
namespace App\Http\Controllers\Admin;
use App\Support\NotificationTarget;
use App\Support\ServiceFactory;
use Throwable;
final class NotificationDetailController
{
    public function handle(): array
    {
        $admin=\current_user();$adminId=(int)($admin['id']??0);$allowedTypes=\admin_notification_types_for_role($admin);$id=max(0,(int)($_GET['id']??0));$error='';$notification=null;$target=['url'=>\route_url('admin/index.php'),'label'=>'Open admin overview'];
        try{
            if($id<=0)$error='Notification not found.';
            else{$repo=ServiceFactory::notifications(\db());$notification=$repo->findForUser($adminId,$id,$allowedTypes);if(!$notification)$error='Notification not found.';else{$repo->markRead($adminId,$id);$data=json_decode((string)($notification['data_json']??'{}'),true);$notification['data']=is_array($data)?$data:[];$target=NotificationTarget::resolve($notification);}}
        }catch(Throwable $e){$error=\web_safe_exception_message($e,'admin_notification_detail','Notification details are temporarily unavailable.');}
        return compact('admin','notification','target','error');
    }
}
