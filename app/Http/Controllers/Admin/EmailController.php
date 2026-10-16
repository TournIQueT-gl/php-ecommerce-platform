<?php
declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Support\ServiceFactory;

final class EmailController
{
    private const FLASH_KEY='admin_email';

    public function handle(): array
    {
        $admin=\current_user();$adminId=(int)($admin['id']??0);
        $service=ServiceFactory::email(\db());
        $schemaReady=$service->schemaReady();

        if(($_SERVER['REQUEST_METHOD']??'GET')==='POST'){
            \verify_csrf();$action=(string)($_POST['action']??'');$message='';$error='';
            try{
                if(!$schemaReady){$error='Historical email schema is missing. Open Admin → Health and follow the database recovery guidance.';}
                elseif($action==='save_settings'){
                    if(!\admin_require_password_from_post($error)){}
                    else{
                        $result=$service->saveSettings($_POST,$adminId,(string)($_POST['smtp_password']??''));
                        if(!empty($result['ok'])){$message=(string)$result['message'];\admin_audit('email_settings_updated','SMTP/email delivery settings updated');}else$error=(string)$result['message'];
                    }
                }elseif($action==='send_test'){
                    if(!\admin_require_password_from_post($error)){}
                    else{
                        $result=$service->test((string)($_POST['recipient']??''));
                        if(!empty($result['ok'])){$message=(string)$result['message'];\admin_audit('email_test_sent','SMTP test email sent');}else$error=(string)$result['message'];
                    }
                }elseif($action==='retry'){
                    if($service->retry((int)($_POST['delivery_id']??0)))$message='Failed delivery returned to the queue.';else$error='That delivery cannot be retried.';
                }elseif($action==='process_queue'){
                    $run=$service->processPending(10);$message='Email queue processed: '.(int)($run['sent']??0).' sent, '.(int)($run['failed']??0).' failed.';
                }else{$error='Unknown email action.';}
            }catch(\Throwable $e){$error=\web_safe_exception_message($e,'admin_email_action','Email operation failed.');}
            \web_post_redirect('admin/email.php',self::FLASH_KEY,$message,$error);
        }

        $flash=\web_flash_take(self::FLASH_KEY);
        $settings=$schemaReady?$service->publicSettings():[];
        $stats=$schemaReady?$service->stats():['pending'=>0,'sent_24h'=>0,'failed'=>0];
        $deliveries=$schemaReady?$service->recent(60):[];
        $schedulerMode=['configured'=>'auto','effective'=>'web','cli_healthy'=>false,'web_enabled'=>true];
        try{$schedulerMode=ServiceFactory::scheduler(\db())->modeStatus();}catch(\Throwable){}
        return ['admin'=>$admin,'message'=>$flash['message'],'error'=>$flash['error'],'schemaReady'=>$schemaReady,'settings'=>$settings,'stats'=>$stats,'deliveries'=>$deliveries,'schedulerMode'=>$schedulerMode];
    }
}
