<?php
declare(strict_types=1);
namespace App\Http\Controllers;
use App\Support\ServiceFactory;
use App\Support\PublicException;
final class SupportController
{
    public function handle(): array
    {
        $user=\current_user();if(!$user){header('Location: '.\route_url('login.php'));exit;}
        $flags=ServiceFactory::featureFlags(\db());
        $canDiagnostics=$flags->enabled((int)$user['id'],'diagnostic_uploads');
        $priority=$flags->enabled((int)$user['id'],'priority_support')?'priority':'normal';
        $message='';$error='';
        if(($_SERVER['REQUEST_METHOD']??'GET')==='POST'){
            \verify_csrf();
            try{$r=ServiceFactory::support(\db())->createTicket((int)$user['id'],(string)($_POST['category']??'other'),(string)($_POST['subject']??''),(string)($_POST['message']??''),$canDiagnostics?($_FILES['diagnostics']??[]):[],$priority);if($r['ok']){header('Location: '.\route_url('support-ticket.php').'?id='.(int)$r['ticket_id']);exit;}$error=(string)$r['message'];}catch(PublicException $e){$error=$e->getMessage();}catch(\Throwable $e){$error=\web_safe_exception_message($e,'support_ticket_create','The support ticket could not be created.');}
        }
        $tickets=ServiceFactory::supportRepository(\db())->ticketsForUser((int)$user['id']);
        return compact('user','message','error','tickets','canDiagnostics','priority');
    }
}
