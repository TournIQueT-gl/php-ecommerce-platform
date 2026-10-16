<?php
declare(strict_types=1);
namespace App\Services;

use App\Security\SupportArchiveValidator;
use App\Events\DomainEvent;
use App\Events\EventDispatcher;
use App\Repositories\AccountActivityRepository;
use App\Repositories\SupportRepository;
use App\Support\Logger;
use App\Support\PublicException;
use Throwable;

final class SupportService
{
    private const MAX_ATTACHMENT_BYTES = 15728640; // 15 MiB
    private const ALLOWED_EXT = ['zip','log','txt','json','jpg','jpeg','png','webp','pdf'];
    public function __construct(private SupportRepository $tickets, private AccountActivityRepository $activity, private ?EventDispatcher $events = null) {}

    private function emit(string $key, ?int $subjectUserId, ?int $actorUserId, array $payload = []): void
    {
        if ($this->events === null) return;
        // EventDispatcher is already fail-soft, but keep support operations
        // independent from auxiliary notification infrastructure.
        try{$this->events->dispatch(new DomainEvent($key,$subjectUserId,$actorUserId,$payload));}
        catch(Throwable $e){Logger::warning('Support event dispatch failed',['event_key'=>$key,'message'=>$e->getMessage()]);}
    }

    private function recordActivity(int $userId,string $type,string $title,string $details,?string $ip): void
    {
        try{$this->activity->add($userId,$type,$title,$details,$ip);}
        catch(Throwable $e){Logger::warning('Support activity log failed',['user_id'=>$userId,'type'=>$type,'message'=>$e->getMessage()]);}
    }

    public function createTicket(int $userId,string $category,string $subject,string $message,array $file=[],string $priority='normal'): array
    {
        $category=strtolower(trim($category));if(!in_array($category,['order','delivery','product','return','account','payment','other'],true))$category='other';
        $subject=trim(preg_replace('/\s+/',' ',$subject)??'');$message=trim($message);
        if($subject===''||strlen($subject)>160)return ['ok'=>false,'message'=>'Enter a subject up to 160 characters.'];
        if($message===''||strlen($message)>8000)return ['ok'=>false,'message'=>'Enter a message up to 8000 characters.'];
        $priority=in_array($priority,['normal','priority'],true)?$priority:'normal';
        $attachment=$this->storeAttachment($file);
        try{
            $saved=$this->tickets->createTicketWithMessage($userId,$category,$subject,$priority,$message,$attachment);
            $id=(int)$saved['ticket_id'];
        }catch(Throwable $e){
            $this->deleteStoredAttachment($attachment);
            throw $e;
        }
        $this->recordActivity($userId,'support_ticket','Support ticket opened','#'.$id.' · '.$subject,\client_ip());
        $this->emit('support.ticket_created',$userId,$userId,['ticket_id'=>$id,'subject'=>$subject,'priority'=>$priority]);
        return ['ok'=>true,'ticket_id'=>$id,'message'=>'Support ticket opened.'];
    }

    public function userReply(int $userId,int $ticketId,string $message,array $file=[]): array
    {
        $ticket=$this->tickets->ticketForUser($ticketId,$userId);if(!$ticket)return ['ok'=>false,'message'=>'Ticket not found.'];
        if((string)$ticket['status']==='closed')return ['ok'=>false,'message'=>'This ticket is closed.'];
        $message=trim($message);
        if(strlen($message)>8000)return ['ok'=>false,'message'=>'Reply is too long.'];
        $attachment=$this->storeAttachment($file);
        if($message===''&&!$attachment)return ['ok'=>false,'message'=>'Enter a reply or attach diagnostics.'];
        try{
            $this->tickets->addUserMessageIfOpen($ticketId,$userId,$message!==''?$message:null,$attachment);
        }catch(\DomainException $e){
            $this->deleteStoredAttachment($attachment);
            return ['ok'=>false,'message'=>'This ticket is closed.'];
        }catch(Throwable $e){
            $this->deleteStoredAttachment($attachment);
            throw $e;
        }
        $this->recordActivity($userId,'support_reply','Support ticket updated','#'.$ticketId,\client_ip());
        $this->emit('support.user_replied',$userId,$userId,['ticket_id'=>$ticketId]);
        return ['ok'=>true,'message'=>'Reply added.'];
    }

    public function adminReply(int $adminId,int $ticketId,string $message,array $file=[]): array
    {
        $ticket=$this->tickets->ticketAdmin($ticketId);if(!$ticket)return ['ok'=>false,'message'=>'Ticket not found.'];
        $message=trim($message);
        if(strlen($message)>8000)return ['ok'=>false,'message'=>'Reply is too long.'];
        $attachment=$this->storeAttachment($file);
        if($message===''&&!$attachment)return ['ok'=>false,'message'=>'Enter a reply or attach a file.'];
        try{
            $replyId=$this->tickets->addMessageAndSetStatus($ticketId,$adminId,true,$message!==''?$message:null,$attachment,'waiting',$adminId);
        }catch(Throwable $e){
            $this->deleteStoredAttachment($attachment);
            throw $e;
        }
        $this->recordActivity((int)$ticket['user_id'],'support_reply','Support replied to ticket','#'.$ticketId.' · message #'.$replyId,null);
        $this->emit('support.admin_replied',(int)$ticket['user_id'],$adminId,['ticket_id'=>$ticketId,'reply_id'=>$replyId]);
        return ['ok'=>true,'message'=>'Reply sent.','reply_id'=>$replyId];
    }

    private function storeAttachment(array $file): ?array
    {
        $error=(int)($file['error']??UPLOAD_ERR_NO_FILE);if($error===UPLOAD_ERR_NO_FILE)return null;
        if($error!==UPLOAD_ERR_OK)throw new PublicException('Attachment upload failed.');
        $size=(int)($file['size']??0);if($size<=0||$size>self::MAX_ATTACHMENT_BYTES)throw new PublicException('Attachment must be 15 MB or smaller.');
        $tmp=(string)($file['tmp_name']??'');if($tmp===''||!is_uploaded_file($tmp))throw new PublicException('Invalid attachment upload.');
        $original=basename((string)($file['name']??'attachment.txt'));$ext=strtolower(pathinfo($original,PATHINFO_EXTENSION));
        if(!in_array($ext,self::ALLOWED_EXT,true))throw new PublicException('Allowed attachments: JPG, PNG, WEBP, PDF, ZIP, LOG, TXT or JSON.');
        $head=(string)@file_get_contents($tmp,false,null,0,8);
        if($ext==='zip'){
            if(!str_starts_with($head,"PK"))throw new PublicException('The ZIP attachment is not valid.');
            SupportArchiveValidator::validate($tmp,$size);
        }
        $stored=bin2hex(random_bytes(16)).'.'.$ext;$dest=\support_storage_dir().'/'.$stored;
        if(!move_uploaded_file($tmp,$dest))throw new PublicException('Could not store attachment.');
        @chmod($dest,0660);
        return ['original_name'=>substr($original,0,255),'stored_name'=>$stored,'size'=>$size,'sha256'=>hash_file('sha256',$dest)?:null];
    }

    private function deleteStoredAttachment(?array $attachment): void
    {
        $stored=basename((string)($attachment['stored_name']??''));
        if($stored==='')return;
        \private_storage_delete('support',$stored);
    }
}
