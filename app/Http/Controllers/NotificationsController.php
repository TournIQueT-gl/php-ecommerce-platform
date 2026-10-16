<?php
declare(strict_types=1);

namespace App\Http\Controllers;

use App\Support\ServiceFactory;
use App\Support\NotificationTarget;
use Throwable;

final class NotificationsController
{
    public function handle(): array
    {
        $user=\current_user();
        if(!$user){header('Location: '.\route_url('login.php'));exit;}
        $userId=(int)$user['id'];$excludedTypes=\web_notification_excluded_types_for_user($user);
        $enabled=false;$message='';$error='';
        try{$enabled=ServiceFactory::featureFlags(\db())->enabled($userId,'notification_center');}catch(Throwable){}

        if(($_SERVER['REQUEST_METHOD']??'GET')==='POST'){
            \verify_csrf();
            if(!$enabled){\web_post_redirect('notifications.php','notifications','','Notification center is not enabled for this account.');}
            $action=(string)($_POST['action']??'');
            try{
                $repo=ServiceFactory::notifications(\db());
                if($action==='mark_all'){
                    $changed=$repo->markAllRead($userId,null,$excludedTypes);
                    \web_post_redirect('notifications.php','notifications',$changed>0?'All notifications marked as read.':'No unread notifications.');
                }
                if($action==='mark_read'){
                    $id=(int)($_POST['notification_id']??0);
                    if($id<=0||!$repo->findForUser($userId,$id,null,$excludedTypes))\web_post_redirect('notifications.php','notifications','','Notification not found.');
                    $repo->markRead($userId,$id);
                    \web_post_redirect('notifications.php','notifications','Notification marked as read.');
                }
                \web_post_redirect('notifications.php','notifications','','Unknown notification action.');
            }catch(Throwable $e){
                \web_post_redirect('notifications.php','notifications','',\web_safe_exception_message($e,'notification_action','The notification action could not be completed.'));
            }
        }

        $flash=\web_flash_take('notifications');
        $message=$flash['message'];$error=$flash['error'];
        $page=max(1,(int)($_GET['page']??1));
        $notifications=[];$total=0;$pages=1;$unread=0;
        if($enabled){
            try{
                $repo=ServiceFactory::notifications(\db());
                $result=$repo->page($userId,$page,25,null,$excludedTypes);
                $notifications=$result['rows'];$total=(int)$result['total'];$page=(int)$result['page'];$pages=(int)$result['pages'];
                $unread=$repo->unreadCount($userId,null,$excludedTypes);
            }catch(Throwable $e){$error=\web_safe_exception_message($e,'notification_center_load','Notifications are temporarily unavailable.');}
        }
        foreach($notifications as &$notification){
            $data=json_decode((string)($notification['data_json']??'{}'),true);
            $notification['data']=is_array($data)?$data:[];
            $notification['target']=NotificationTarget::resolve($notification);
        }
        unset($notification);
        return compact('user','enabled','message','error','notifications','total','page','pages','unread');
    }

}
