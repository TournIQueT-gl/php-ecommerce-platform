<?php
declare(strict_types=1);
namespace App\Http\Controllers\Admin;
use App\Support\ServiceFactory;
use App\Support\PublicException;
final class SupportTicketController
{
    public function handle(): array
    {
        $id=(int)($_GET['id']??$_POST['id']??0);
        $repo=ServiceFactory::supportRepository(\db());
        $ticket=$repo->ticketAdmin($id);
        if(!$ticket)exit('Ticket not found.');
        $adminId=(int)($_SESSION['user_id']??0);

        if(($_SERVER['REQUEST_METHOD']??'GET')==='POST'){
            \verify_csrf();
            $action=(string)($_POST['action']??'reply');
            $message='';$error='';
            try{
                if($action==='reply'){
                    $r=ServiceFactory::support(\db())->adminReply($adminId,$id,(string)($_POST['message']??''),$_FILES['diagnostics']??[]);
                    if($r['ok']){
                        $replyId=(int)($r['reply_id']??0);
                        $message=$replyId>0?'Reply #'.$replyId.' sent and verified.':(string)$r['message'];
                    }else$error=(string)$r['message'];
                } elseif($action==='status'){
                    $status=(string)($_POST['status']??'open');
                    if(!in_array($status,['open','waiting','closed'],true))$status='open';
                    $repo->setStatus($id,$status,$adminId);
                    \admin_audit('support_status','Ticket #'.$id.' -> '.$status,(int)$ticket['user_id']);
                    $message='Ticket status updated.';
                } else {
                    $error='Unknown support action.';
                }
            }catch(PublicException $e){$error=$e->getMessage();}catch(\Throwable $e){$error=\web_safe_exception_message($e,'admin_support_ticket_action','The ticket action could not be completed.');}
            \web_post_redirect('admin/support-ticket.php','admin_support_ticket_'.$id,$message,$error,'id='.$id);
        }

        $flash=\web_flash_take('admin_support_ticket_'.$id);
        $message=$flash['message'];
        $error=$flash['error'];
        $ticket=$repo->ticketAdmin($id);
        if(!$ticket)exit('Ticket not found.');
        $messages=$repo->messages($id);
        try{ServiceFactory::supportReads(\db())->markLatest($adminId,$id);}catch(\Throwable $e){\App\Support\Logger::warning('Could not update admin support read state',['ticket_id'=>$id,'admin_id'=>$adminId,'message'=>$e->getMessage()]);}
        $user=ServiceFactory::users(\db())->findForAdmin((int)$ticket['user_id']);
        $orders=[];
        try{$stmt=\db()->prepare('SELECT order_number,status,grand_total,currency,placed_at FROM store_orders WHERE user_id=? ORDER BY placed_at DESC,id DESC LIMIT 5');$stmt->execute([(int)$ticket['user_id']]);$orders=$stmt->fetchAll();}catch(\Throwable){}
        return compact('id','ticket','messages','user','orders','message','error');
    }
}
