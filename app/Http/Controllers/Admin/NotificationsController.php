<?php
declare(strict_types=1);
namespace App\Http\Controllers\Admin;
use App\Support\NotificationTarget;
use App\Support\ServiceFactory;
use Throwable;
final class NotificationsController
{
    private const FLASH='admin_notifications';
    public function handle(): array
    {
        $admin=\current_user();$adminId=(int)($admin['id']??0);$allowedTypes=\admin_notification_types_for_role($admin);$message='';$error='';
        if(($_SERVER['REQUEST_METHOD']??'GET')==='POST'){
            \verify_csrf();$action=(string)($_POST['action']??'');
            try{
                $repo=ServiceFactory::notifications(\db());
                if($action==='mark_all'){\web_post_redirect('admin/notifications.php',self::FLASH,$repo->markAllRead($adminId,$allowedTypes)>0?'All notifications marked as read.':'No unread notifications.');}
                if($action==='mark_read'){$id=(int)($_POST['notification_id']??0);if($id<=0||!$repo->findForUser($adminId,$id,$allowedTypes))\web_post_redirect('admin/notifications.php',self::FLASH,'','Notification not found.');$repo->markRead($adminId,$id);\web_post_redirect('admin/notifications.php',self::FLASH,'Notification marked as read.');}
                \web_post_redirect('admin/notifications.php',self::FLASH,'','Unknown notification action.');
            }catch(Throwable $e){\web_post_redirect('admin/notifications.php',self::FLASH,'',\web_safe_exception_message($e,'admin_notification_action','The notification action could not be completed.'));}
        }
        $flash=\web_flash_take(self::FLASH);$message=$flash['message'];$error=$flash['error'];$page=max(1,(int)($_GET['page']??1));$rows=[];$total=0;$pages=1;$unread=0;
        try{$repo=ServiceFactory::notifications(\db());$result=$repo->page($adminId,$page,25,$allowedTypes);$rows=$result['rows'];$total=(int)$result['total'];$page=(int)$result['page'];$pages=(int)$result['pages'];$unread=$repo->unreadCount($adminId,$allowedTypes);}catch(Throwable $e){$error=\web_safe_exception_message($e,'admin_notification_load','Notifications are temporarily unavailable.');}
        foreach($rows as &$row){$data=json_decode((string)($row['data_json']??'{}'),true);$row['data']=is_array($data)?$data:[];$row['target']=NotificationTarget::resolve($row);}unset($row);
        return compact('admin','message','error','rows','total','page','pages','unread');
    }
}
