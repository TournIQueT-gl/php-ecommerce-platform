<?php
declare(strict_types=1);

namespace App\Http\Controllers;

use App\Support\NotificationTarget;
use App\Support\ServiceFactory;
use Throwable;

final class NotificationDetailController
{
    public function handle(): array
    {
        $user=\current_user();
        if(!$user){header('Location: '.\route_url('login.php'));exit;}
        $userId=(int)$user['id'];$excludedTypes=\web_notification_excluded_types_for_user($user);
        $id=max(0,(int)($_GET['id']??0));
        $error='';$notification=null;$target=['url'=>\route_url('dashboard.php'),'label'=>'Open dashboard'];

        try{
            $enabled=ServiceFactory::featureFlags(\db())->enabled($userId,'notification_center');
            if(!$enabled){$error='Notification center is not enabled for this account.';}
            elseif($id<=0){$error='Notification not found.';}
            else{
                $repo=ServiceFactory::notifications(\db());
                $notification=$repo->findForUser($userId,$id,null,$excludedTypes);
                if(!$notification){$error='Notification not found.';}
                else{
                    $repo->markRead($userId,$id);
                    $data=json_decode((string)($notification['data_json']??'{}'),true);
                    $notification['data']=is_array($data)?$data:[];
                    $target=NotificationTarget::resolve($notification);
                }
            }
        }catch(Throwable $e){
            $error=\web_safe_exception_message($e,'notification_detail_load','Notification details are temporarily unavailable.');
        }
        return compact('user','notification','target','error');
    }
}
