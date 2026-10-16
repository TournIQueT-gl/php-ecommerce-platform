<?php
declare(strict_types=1);
namespace App\Http\Controllers;
use App\Support\ServiceFactory;
use App\Support\PublicException;
final class SupportTicketController
{
    public function handle(): array
    {
        $user=\current_user();
        if(!$user){header('Location: '.\route_url('login.php'));exit;}
        $canDiagnostics=ServiceFactory::featureFlags(\db())->enabled((int)$user['id'],'diagnostic_uploads');
        $id=(int)($_GET['id']??$_POST['id']??0);
        if($id<=0){http_response_code(404);exit('Ticket not found.');}
        $repo=ServiceFactory::supportRepository(\db());
        $ticket=$repo->ticketForUser($id,(int)$user['id']);
        if(!$ticket){http_response_code(404);exit('Ticket not found.');}

        if(($_SERVER['REQUEST_METHOD']??'GET')==='POST'){
            \verify_csrf();
            $message='';$error='';
            try{
                $r=ServiceFactory::support(\db())->userReply((int)$user['id'],$id,(string)($_POST['message']??''),$canDiagnostics?($_FILES['diagnostics']??[]):[]);
                if($r['ok'])$message=(string)$r['message'];else$error=(string)$r['message'];
            }catch(PublicException $e){$error=$e->getMessage();}catch(\Throwable $e){$error=\web_safe_exception_message($e,'support_ticket_reply','Your reply could not be sent.');}
            \web_post_redirect('support-ticket.php','support_ticket_'.$id,$message,$error,'id='.$id);
        }

        $flash=\web_flash_take('support_ticket_'.$id);
        $message=$flash['message'];
        $error=$flash['error'];
        $ticket=$repo->ticketForUser($id,(int)$user['id']);
        if(!$ticket){http_response_code(404);exit('Ticket not found.');}
        $messages=$repo->messagesForUser($id,(int)$user['id']);
        try{
            ServiceFactory::supportReads(\db())->markLatest((int)$user['id'],$id);
            ServiceFactory::notifications(\db())->markSupportTicketRead((int)$user['id'],$id);
        }catch(\Throwable $e){\App\Support\Logger::warning('Could not update support read state',['ticket_id'=>$id,'user_id'=>(int)$user['id'],'message'=>$e->getMessage()]);}
        return compact('user','ticket','messages','message','error','canDiagnostics');
    }
}
