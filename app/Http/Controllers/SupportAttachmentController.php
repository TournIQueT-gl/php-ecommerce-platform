<?php
declare(strict_types=1);
namespace App\Http\Controllers;
use App\Support\ServiceFactory;
final class SupportAttachmentController
{
    public function handle(): never
    {
        $user=\current_user();if(!$user){header('Location: '.\route_url('login.php'));exit;}
        $id=(int)($_GET['id']??0);$row=ServiceFactory::supportRepository(\db())->attachmentMessage($id);
        $isAdmin=!empty($user['is_admin']) && function_exists('admin_can') && \admin_can('support',$user);
        if(!$row||(!$isAdmin&&(int)$row['ticket_user_id']!==(int)$user['id'])){http_response_code(404);exit('Attachment not found.');}
        $path=\support_attachment_path((string)($row['attachment_stored_name']??''));if(!$path){http_response_code(404);exit('Attachment missing.');}
        $name=str_replace(["\r","\n"],'',basename((string)($row['attachment_original_name']??'attachment.bin')));
        header('Content-Type: application/octet-stream');header('Content-Length: '.filesize($path));header('Content-Disposition: attachment; filename="'.addcslashes($name,'"\\').'"');header('Cache-Control: private, no-store, max-age=0');header('X-Content-Type-Options: nosniff');header('Cross-Origin-Resource-Policy: same-origin');if(session_status()===PHP_SESSION_ACTIVE)session_write_close();readfile($path);exit;
    }
}
