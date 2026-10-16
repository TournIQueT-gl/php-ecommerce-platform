<?php
declare(strict_types=1);
require_once __DIR__.'/includes/auth.php';
require_once __DIR__.'/includes/account_layout.php';
require_once __DIR__.'/includes/public_layout.php';
require_once __DIR__.'/includes/support.php';

use App\Http\Controllers\SupportController;
use App\Support\ServiceFactory;
use App\Support\View;

init_web_request();
$user=current_user();
if($user)web_private_response_headers();
if($user){
    View::render('account/support.php',(new SupportController())->handle());
    exit;
}

$settings=store_runtime_settings();
$mail=[];
try{$mail=ServiceFactory::emailRepository(db())->settings();}catch(\Throwable){}
$contactEmail=trim((string)($mail['reply_to_email']??''));
if($contactEmail===''||filter_var($contactEmail,FILTER_VALIDATE_EMAIL)===false)$contactEmail=trim((string)($mail['from_email']??''));
if(filter_var($contactEmail,FILTER_VALIDATE_EMAIL)===false)$contactEmail='';
$whatsapp=trim((string)($settings['social_whatsapp']??''));
View::render('public/support.php',['settings'=>$settings,'contactEmail'=>$contactEmail,'whatsapp'=>$whatsapp]);
