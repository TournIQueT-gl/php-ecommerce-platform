<?php
declare(strict_types=1);
namespace App\Http\Controllers\Admin;
use App\Support\ServiceFactory;
use App\Support\PublicException;
final class AnnouncementsController
{
    public function handle(): array
    {
        $repo=ServiceFactory::announcements(\db());
        $adminId=(int)$_SESSION['user_id'];

        if(($_SERVER['REQUEST_METHOD']??'GET')==='POST'){
            \verify_csrf();
            $action=(string)($_POST['action']??'');
            $message='';
            $error='';
            try{
                if($action==='create'){
                    $title=trim((string)($_POST['title']??''));
                    $body=trim((string)($_POST['message']??''));
                    $type=(string)($_POST['type']??'info');
                    if(!in_array($type,['info','warning','maintenance','update'],true))$type='info';
                    if($title===''||strlen($title)>160||$body===''||strlen($body)>5000)throw new PublicException('Enter a title and message within the limits.');
                    $browserTz=\admin_browser_timezone_from_post();
                    $audience=(string)($_POST['audience']??'all');
                    if(!in_array($audience,['all','customers','admins'],true))$audience='all';
                    $priority=(string)($_POST['priority']??'normal');
                    if(!in_array($priority,['low','normal','high','critical'],true))$priority='normal';
                    $pinned=!empty($_POST['is_pinned']);
                    $starts=\admin_parse_utc_datetime((string)($_POST['starts_at']??''),$browserTz);
                    $ends=\admin_parse_utc_datetime((string)($_POST['ends_at']??''),$browserTz);
                    if($starts!==null&&$ends!==null&&strtotime($ends)<=strtotime($starts))throw new PublicException('Announcement end time must be after its start time.');
                    $id=$repo->create($adminId,$title,$body,$type,$starts,$ends,$audience,$priority,$pinned);
                    \admin_audit('announcement_created','Announcement #'.$id);
                    $message=$starts!==null&&strtotime($starts)>time()?'Announcement scheduled.':'Announcement published.';
                } elseif($action==='toggle'){
                    $id=(int)($_POST['id']??0);
                    if($id<=0||!$repo->toggle($id))throw new PublicException('Announcement could not be updated. It may already have been removed.');
                    \admin_audit('announcement_toggled','Announcement #'.$id);
                    $message='Announcement status updated.';
                } elseif($action==='bulk'){
                    $ids=array_values(array_unique(array_filter(array_map('intval',(array)($_POST['announcement_ids']??[])),static fn(int $id):bool=>$id>0)));
                    if(count($ids)>100)throw new PublicException('Bulk actions are limited to 100 announcements at a time.');
                    $bulk=(string)($_POST['bulk_action']??'');
                    if(!$ids)throw new PublicException('Select at least one announcement.');
                    if(!in_array($bulk,['enable','disable','delete'],true))throw new PublicException('Choose a valid bulk action.');
                    if($bulk==='delete'){
                        if(!\admin_require_password_from_post($error))throw new PublicException($error);
                        $affected=$repo->deleteMany($ids);if($affected===0)throw new PublicException('The selected announcements were already removed.');\admin_audit('announcements_bulk_deleted',$affected.' announcements');$message=$affected.' announcement'.($affected===1?'':'s').' deleted.';
                    }else{
                        $affected=$repo->setActiveMany($ids,$bulk==='enable');\admin_audit('announcements_bulk_status',$bulk.' · '.$affected.' announcements');$message=$affected.' announcement'.($affected===1?'':'s').' updated.';
                    }
                } elseif($action==='delete'){
                    if(!\admin_require_password_from_post($error)){
                        // Re-authentication error already set.
                    } else {
                        $id=(int)($_POST['id']??0);
                        if($id<=0||!$repo->delete($id))throw new PublicException('Announcement could not be deleted. It may already have been removed.');
                        \admin_audit('announcement_deleted','Announcement #'.$id);
                        $message='Announcement deleted.';
                    }
                } else {
                    throw new PublicException('Unknown announcement action.');
                }
            }catch(PublicException $e){$error=$e->getMessage();}catch(\Throwable $e){$error=\web_safe_exception_message($e,'admin_announcement_action','The announcement action could not be completed.');}

            \web_post_redirect('admin/announcements.php','admin_announcements',$message,$error,\admin_preserved_query(['page']));
        }

        $flash=\web_flash_take('admin_announcements');
        $message=$flash['message'];
        $error=$flash['error'];
        $page=max(1,(int)($_GET['page']??1));$paged=$repo->adminPage($page,30);$announcements=$paged['rows'];$total=(int)$paged['total'];$page=(int)$paged['page'];$pages=(int)$paged['pages'];
        return compact('announcements','message','error','total','page','pages');
    }
}
