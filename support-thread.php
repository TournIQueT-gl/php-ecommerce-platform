<?php
declare(strict_types=1);
require_once __DIR__.'/includes/auth.php';
require_once __DIR__.'/includes/support.php';

use App\Support\ServiceFactory;

require_login();
web_private_response_headers();
header('Content-Type: application/json; charset=utf-8');

$user=current_user();
if(!$user){
    http_response_code(401);
    echo json_encode(['ok'=>false,'error'=>'Authentication required.']);
    exit;
}
$ticketId=max(0,(int)($_GET['id']??0));
$after=max(0,(int)($_GET['after']??0));
if($ticketId<=0){
    http_response_code(400);
    echo json_encode(['ok'=>false,'error'=>'Invalid ticket.']);
    exit;
}
$repo=ServiceFactory::supportRepository(db());
$ticket=$repo->ticketForUser($ticketId,(int)$user['id']);
if(!$ticket){
    http_response_code(404);
    echo json_encode(['ok'=>false,'error'=>'Ticket not found.']);
    exit;
}
$rows=$repo->messagesForUserAfter($ticketId,(int)$user['id'],$after,50);
$out=[];
$last=$after;
foreach($rows as $row){
    $id=(int)$row['id'];$last=max($last,$id);
    $stored=(string)($row['attachment_stored_name']??'');
    $out[]=[
        'id'=>$id,
        'sender_is_admin'=>!empty($row['sender_is_admin']),
        'username'=>(string)($row['username']??'USER'),
        'message'=>$row['message']!==null?(string)$row['message']:null,
        'created_at_epoch'=>utc_epoch_from_db((string)($row['created_at']??'')),
        'attachment_name'=>$stored!==''?(string)($row['attachment_original_name']??'attachment.bin'):null,
        'attachment_url'=>$stored!==''?route_url('support-attachment.php').'?id='.$id:null,
    ];
}
if($last>$after){
    try{
        ServiceFactory::supportReads(db())->markLatest((int)$user['id'],$ticketId);
        ServiceFactory::notifications(db())->markSupportTicketRead((int)$user['id'],$ticketId);
    }catch(Throwable $e){App\Support\Logger::warning('Support poll read-state update failed',['ticket_id'=>$ticketId,'user_id'=>(int)$user['id'],'message'=>$e->getMessage()]);}
}
echo json_encode(['ok'=>true,'last_message_id'=>$last,'messages'=>$out],JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
