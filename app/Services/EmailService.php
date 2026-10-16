<?php
declare(strict_types=1);

namespace App\Services;

use App\Repositories\EmailRepository;
use App\Repositories\UserRepository;
use App\Repositories\FeatureFlagRepository;
use App\Support\MailSecretStore;
use App\Support\Logger;
use PDO;
use RuntimeException;
use Throwable;

final class EmailService
{
    private const CATEGORIES=['security','support','orders','marketing','maintenance','announcements','test'];

    public function __construct(
        private PDO $db,
        private EmailRepository $emails,
        private UserRepository $users,
        private SmtpClient $smtp,
        private ?FeatureFlagRepository $flags=null
    ) {}

    public function schemaReady(): bool { return $this->emails->schemaReady(); }

    public function publicSettings(): array
    {
        $s=$this->emails->settings();
        return [
            'enabled'=>!empty($s['enabled']),
            'smtp_host'=>(string)($s['smtp_host']??''),
            'smtp_port'=>(int)($s['smtp_port']??587),
            'smtp_encryption'=>(string)($s['smtp_encryption']??'tls'),
            'smtp_username'=>(string)($s['smtp_username']??''),
            'password_configured'=>trim((string)($s['smtp_password_cipher']??''))!=='',
            'from_email'=>(string)($s['from_email']??''),
            'from_name'=>(string)($s['from_name']??$this->brandName()),
            'reply_to_email'=>(string)($s['reply_to_email']??''),
            'site_url'=>(string)CANONICAL_SITE_URL,
            'updated_at'=>$s['updated_at']??null,
            'secret_store_available'=>MailSecretStore::available(),
        ];
    }

    public function deliveryEnabled(): bool
    {
        if(!$this->schemaReady())return false;
        if($this->flags!==null){
            try{if($this->flags->exists('email_delivery') && !$this->flags->defaultEnabled('email_delivery'))return false;}catch(Throwable){}
        }
        $s=$this->emails->settings();
        return !empty($s['enabled']) && trim((string)($s['smtp_host']??''))!=='' && filter_var((string)($s['from_email']??''),FILTER_VALIDATE_EMAIL)!==false;
    }

    public function saveSettings(array $input,int $adminId,?string $password=null): array
    {
        if(!$this->schemaReady())return ['ok'=>false,'message'=>'Email schema is missing. Open Admin → Health and run the required database migrations.'];
        $current=$this->emails->settings();
        $host=trim((string)($input['smtp_host']??''));
        $port=(int)($input['smtp_port']??587);
        $encryption=strtolower(trim((string)($input['smtp_encryption']??'tls')));
        $username=trim((string)($input['smtp_username']??''));
        $fromEmail=strtolower(trim((string)($input['from_email']??'')));
        $fromName=trim((string)($input['from_name']??$this->brandName()));
        $replyTo=strtolower(trim((string)($input['reply_to_email']??'')));
        $siteUrl=rtrim((string)CANONICAL_SITE_URL,'/');
        $enabled=!empty($input['enabled']);
        if($host===''||strlen($host)>255)return ['ok'=>false,'message'=>'Enter a valid SMTP host.'];
        if($port<1||$port>65535)return ['ok'=>false,'message'=>'SMTP port must be between 1 and 65535.'];
        if(!in_array($encryption,['none','tls','ssl'],true))return ['ok'=>false,'message'=>'Choose None, STARTTLS, or SSL/TLS encryption.'];
        if(!filter_var($fromEmail,FILTER_VALIDATE_EMAIL))return ['ok'=>false,'message'=>'Enter a valid From email address.'];
        if($replyTo!==''&&!filter_var($replyTo,FILTER_VALIDATE_EMAIL))return ['ok'=>false,'message'=>'Enter a valid Reply-To email address.'];
        if($fromName===''||strlen($fromName)>160)return ['ok'=>false,'message'=>'From name must be 1-160 characters.'];
        $cipher=(string)($current['smtp_password_cipher']??'');
        if($password!==null && $password!==''){
            if(strlen($password)>1000)return ['ok'=>false,'message'=>'SMTP password is too long.'];
            $cipher=MailSecretStore::encrypt($password);
        }
        if(!empty($input['clear_password']))$cipher='';
        $this->emails->saveSettings([
            'enabled'=>$enabled,'smtp_host'=>$host,'smtp_port'=>$port,'smtp_encryption'=>$encryption,
            'smtp_username'=>$username,'smtp_password_cipher'=>$cipher,'from_email'=>$fromEmail,'from_name'=>$fromName,
            'reply_to_email'=>$replyTo,'site_url'=>$siteUrl,
        ],$adminId);
        return ['ok'=>true,'message'=>'Email settings saved.'];
    }

    public function test(string $recipient): array
    {
        $recipient=strtolower(trim($recipient));
        if(!filter_var($recipient,FILTER_VALIDATE_EMAIL))return ['ok'=>false,'message'=>'Enter a valid test recipient.'];
        try{
            $settings=$this->smtpSettings();
            $brand=$this->brandName();
            $subject=$brand.' email test';
            $text=$brand." SMTP delivery is working.\n\nSent: ".gmdate('Y-m-d H:i:s').' UTC';
            $html=$this->template('EMAIL TEST','SMTP delivery is working.','This message confirms that the configured SMTP server accepted a test email.',null,null);
            $this->smtp->send($settings,$recipient,$subject,$text,$html);
            $rowId=$this->emails->queue(null,$recipient,'test',$subject,'','',null);
            if($rowId>0)$this->emails->markSent($rowId);
            return ['ok'=>true,'message'=>'Test email accepted by the SMTP server.'];
        }catch(Throwable $e){
            Logger::warning('SMTP test failed',['message'=>$e->getMessage()]);
            return ['ok'=>false,'message'=>'SMTP test failed: '.$this->safeError($e->getMessage())];
        }
    }

    public function profile(int $userId): array
    {
        return ['identity'=>$this->emails->userEmail($userId),'preferences'=>$this->emails->preferences($userId),'delivery_enabled'=>$this->deliveryEnabled()];
    }

    public function changeUserEmail(int $userId,string $email,string $currentPassword): array
    {
        if(!$this->schemaReady())return ['ok'=>false,'message'=>'Email features are not installed yet.'];
        if(!$this->emails->pendingEmailSchemaReady())return ['ok'=>false,'message'=>'Run the pending database migrations before changing the recovery email.'];
        $email=strtolower(trim($email));
        if(!filter_var($email,FILTER_VALIDATE_EMAIL)||strlen($email)>254)return ['ok'=>false,'message'=>'Enter a valid email address.'];
        $reauthIdentity=(string)$userId;
        $retry=\web_rate_limit_retry('account_email_reauth',8,900,$reauthIdentity);
        if($retry>0)return ['ok'=>false,'message'=>'Too many incorrect password attempts. Try again in a few minutes.'];
        $hash=$this->users->passwordHash($userId);
        if($hash===''||!password_verify($currentPassword,$hash)){
            \web_rate_limit_failure('account_email_reauth',8,900,$reauthIdentity);
            return ['ok'=>false,'message'=>'Current password is incorrect.'];
        }
        \web_rate_limit_clear('account_email_reauth',$reauthIdentity);
        if($this->emails->emailExists($email,$userId))return ['ok'=>false,'message'=>'That email address is already used or pending on another account.'];
        $current=$this->emails->userEmail($userId);
        if(strtolower((string)($current['email']??''))===$email && !empty($current['email_verified_at'])){
            $this->emails->clearPendingEmail($userId);
            return ['ok'=>true,'message'=>'That email address is already verified.'];
        }
        $this->emails->setPendingEmail($userId,$email);
        $queued=$this->requestVerification($userId,$email);
        return ['ok'=>true,'message'=>$queued
            ? 'Verification sent to the new address. Your current verified recovery email stays active until the replacement is verified.'
            : 'The new address is pending, but verification could not be queued. Your current verified recovery email remains active; check SMTP configuration or retry later.'];
    }

    public function updatePreferences(int $userId,array $input): array
    {
        if(!$this->schemaReady())return ['ok'=>false,'message'=>'Email preferences are not installed yet.'];
        $this->emails->savePreferences($userId,[
            'support'=>!empty($input['support']),
            'orders'=>!empty($input['orders']),
            'marketing'=>!empty($input['marketing']),
            'maintenance'=>!empty($input['maintenance']),
            'announcements'=>!empty($input['announcements']),
        ]);
        return ['ok'=>true,'message'=>'Email preferences updated. Security and recovery emails remain enabled.'];
    }

    public function requestVerification(int $userId,?string $email=null): bool
    {
        if(!$this->schemaReady()||!$this->deliveryEnabled())return false;
        $identity=$this->emails->userEmail($userId);
        $defaultTarget=(string)($identity['pending_email']??'');
        if($defaultTarget==='')$defaultTarget=(string)($identity['email']??'');
        $email=strtolower(trim($email??$defaultTarget));
        if(!filter_var($email,FILTER_VALIDATE_EMAIL))return false;
        $token=bin2hex(random_bytes(32));
        $id=$this->emails->createVerificationToken($userId,$email,hash('sha256',$token),24);
        $url=$this->absolute('verify-email.php','?token='.rawurlencode($token));
        $brand=$this->brandName();
        $subject='Verify your '.$brand.' email';
        $text="Verify your email address for {$brand}:\n\n{$url}\n\nThis link expires in 24 hours.";
        $html=$this->template('VERIFY EMAIL','Confirm your email address.','Use the button below to verify this address for recovery, orders, and account notifications.','VERIFY EMAIL',$url);
        return $this->emails->queue($userId,$email,'security',$subject,$text,$html,'verify-email:'.$id)>0;
    }

    public function verifyEmail(string $token): array
    {
        $token=trim($token);
        if(!preg_match('/^[a-f0-9]{64}$/i',$token))return ['ok'=>false,'message'=>'This verification link is invalid.'];
        $row=$this->emails->verificationByHash(hash('sha256',strtolower($token)));
        if(!$row||!empty($row['used_at'])||!empty($row['cancelled_at'])||strtotime((string)$row['expires_at'].' UTC')<=time())return ['ok'=>false,'message'=>'This verification link has expired or was already used.'];
        $target=strtolower((string)$row['email']);
        $matchesPending=strtolower((string)($row['pending_email']??''))===$target;
        $matchesCurrent=strtolower((string)($row['current_email']??''))===$target;
        if(empty($row['is_active'])||(!$matchesPending&&!$matchesCurrent))return ['ok'=>false,'message'=>'This verification link no longer matches the account email address.'];
        $this->db->beginTransaction();
        try{
            $updated=$matchesPending ? $this->emails->promotePendingEmail((int)$row['user_id'],$target) : $this->emails->markEmailVerified((int)$row['user_id'],$target);
            if(!$updated)throw new RuntimeException('Email verification could not update the account.');
            if(!$this->emails->markVerificationUsed((int)$row['id']))throw new RuntimeException('Email verification token could not be completed.');
            $this->db->commit();
        }catch(Throwable $e){if($this->db->inTransaction())$this->db->rollBack();throw $e;}
        return ['ok'=>true,'message'=>'Email address verified. You can now receive recovery and account emails.','user_id'=>(int)$row['user_id'],'email'=>$target];
    }

    public function queueForUser(int $userId,string $category,string $subject,string $body,?string $ctaLabel=null,?string $ctaUrl=null,?string $dedupeKey=null): int
    {
        if(!$this->deliveryEnabled()||!in_array($category,self::CATEGORIES,true))return 0;
        $recipient=$this->emails->verifiedRecipient($userId);if(!$recipient)return 0;
        if(!$this->preferenceAllows($userId,$category))return 0;
        $email=(string)$recipient['email'];
        $text=$body.($ctaUrl?"\n\n".$ctaUrl:'');
        $html=$this->template(strtoupper(str_replace('_',' ',$category)),$subject,$body,$ctaLabel,$ctaUrl);
        return $this->emails->queue($userId,$email,$category,$subject,$text,$html,$dedupeKey);
    }

    public function queueRaw(?int $userId,string $recipient,string $category,string $subject,string $body,?string $ctaLabel=null,?string $ctaUrl=null,?string $dedupeKey=null): int
    {
        if(!$this->deliveryEnabled()||!filter_var($recipient,FILTER_VALIDATE_EMAIL))return 0;
        $text=$body.($ctaUrl?"\n\n".$ctaUrl:'');
        $html=$this->template(strtoupper(str_replace('_',' ',$category)),$subject,$body,$ctaLabel,$ctaUrl);
        return $this->emails->queue($userId,$recipient,$category,$subject,$text,$html,$dedupeKey);
    }

    public function queueAdmins(string $category,string $subject,string $body,?string $ctaLabel=null,?string $ctaUrl=null,?string $dedupeKey=null): int
    {
        if(!$this->deliveryEnabled())return 0;$count=0;
        foreach($this->emails->verifiedRecipients('admins') as $row){
            $uid=(int)$row['id'];if(!$this->preferenceAllows($uid,$category))continue;
            $key=$dedupeKey!==null?$dedupeKey.':admin:'.$uid:null;
            $count += $this->queueRaw($uid,(string)$row['email'],$category,$subject,$body,$ctaLabel,$ctaUrl,$key)>0?1:0;
        }
        return $count;
    }

    public function queueAdminRoles(array $roles,string $category,string $subject,string $body,?string $ctaLabel=null,?string $ctaUrl=null,?string $dedupeKey=null): int
    {
        if(!$this->deliveryEnabled())return 0;$count=0;
        foreach($this->emails->verifiedAdminRecipients($roles) as $row){
            $uid=(int)$row['id'];if(!$this->preferenceAllows($uid,$category))continue;
            $key=$dedupeKey!==null?$dedupeKey.':admin:'.$uid:null;
            $count += $this->queueRaw($uid,(string)$row['email'],$category,$subject,$body,$ctaLabel,$ctaUrl,$key)>0?1:0;
        }
        return $count;
    }

    public function queueAudience(string $audience,string $category,string $subject,string $body,?string $ctaLabel=null,?string $ctaUrl=null,?string $dedupeKey=null): int
    {
        if(!$this->deliveryEnabled())return 0;$count=0;
        foreach($this->emails->verifiedRecipients($audience) as $row){
            $uid=(int)$row['id'];if(!$this->preferenceAllows($uid,$category))continue;
            $key=$dedupeKey!==null?$dedupeKey.':user:'.$uid:null;
            $count += $this->queueRaw($uid,(string)$row['email'],$category,$subject,$body,$ctaLabel,$ctaUrl,$key)>0?1:0;
        }
        return $count;
    }

    public function queuePasswordReset(int $userId,string $token,int $requestId): bool
    {
        $recipient=$this->emails->verifiedRecipient($userId);if(!$recipient||!$this->deliveryEnabled())return false;
        $url=$this->absolute('forgot-password.php','?token='.rawurlencode($token));
        $brand=$this->brandName();
        return $this->queueRaw($userId,(string)$recipient['email'],'security','Reset your '.$brand.' password','A password reset was requested for your account. This one-time link expires in 30 minutes. If you did not request it, ignore this email.','RESET PASSWORD',$url,'password-reset:'.$requestId)>0;
    }

    public function processPending(int $limit=20,int $networkTimeoutSeconds=15): array
    {
        if(!$this->deliveryEnabled())return ['status'=>'disabled','sent'=>0,'failed'=>0];
        $networkTimeoutSeconds=max(2,min(30,$networkTimeoutSeconds));
        $settings=$this->smtpSettings();$sent=0;$failed=0;
        foreach($this->emails->pending($limit) as $row){
            $id=(int)$row['id'];
            if(!$this->emails->claim($id))continue;
            $dedupe=(string)($row['dedupe_key']??'');
            if(str_starts_with($dedupe,'password-reset:')){
                $created=strtotime((string)($row['created_at']??'').' UTC');
                if($created!==false && $created<=time()-(29*60)){$this->emails->markExpired($id,'Password-reset link expired before SMTP delivery succeeded.');continue;}
            }
            try{
                $this->smtp->send($settings,(string)$row['recipient'],(string)$row['subject'],(string)($row['body_text']??''),(string)($row['body_html']??''),$networkTimeoutSeconds);
                $this->emails->markSent($id);$sent++;
            }catch(Throwable $e){
                $attempt=(int)$row['attempts']+1;$delay=match(true){$attempt<=1=>60,$attempt===2=>300,$attempt===3=>900,$attempt===4=>3600,default=>21600};
                if($attempt>=5)$this->emails->markExpired($id,'Permanent delivery failure after 5 attempts: '.$this->safeError($e->getMessage()));
                else $this->emails->markFailed($id,$this->safeError($e->getMessage()),$delay);
                $failed++;Logger::warning('Email delivery failed',['delivery_id'=>$id,'attempt'=>$attempt,'message'=>$e->getMessage()]);
            }
        }
        return ['status'=>'ok','sent'=>$sent,'failed'=>$failed];
    }

    public function link(string $route,string $suffix=''): string { return $this->absolute($route,$suffix); }
    public function retry(int $id): bool { return $this->emails->retry($id); }
    public function recent(int $limit=50): array { return $this->emails->recent($limit); }
    public function stats(): array { return $this->emails->stats(); }
    public function prune(int $days=90): int { return $this->emails->prune($days); }

    public function emailEvent(string $eventKey,?int $subjectUserId,array $payload,int $eventId): int
    {
        $dedupe='event-email:'.$eventId;
        return match($eventKey){
            'support.admin_replied' => $subjectUserId?($this->queueForUser($subjectUserId,'support','Support replied','Ticket #'.(int)($payload['ticket_id']??0).' has a new response.','OPEN TICKET',$this->absolute('support-ticket.php','?id='.(int)($payload['ticket_id']??0)),$dedupe)>0?1:0):0,
            'support.user_replied','support.ticket_created' => $this->queueAdmins('support',$eventKey==='support.ticket_created'?'New support ticket':'New support reply','Ticket #'.(int)($payload['ticket_id']??0).' needs attention.','OPEN SUPPORT',$this->absolute('admin/support-ticket.php','?id='.(int)($payload['ticket_id']??0)),$dedupe),
            'order.created' => $this->emailOrderCreated($subjectUserId,$payload,$dedupe),
            'order.status_changed','order.shipped','order.refunded' => $this->emailOrderUpdate($eventKey,$subjectUserId,$payload,$dedupe),
            'return.requested' => $this->queueAdminRoles(['owner','admin'],'orders','Return request #'.(int)($payload['return_id']??0),'Order '.(string)($payload['order_number']??('#'.(int)($payload['order_id']??0))).' has a return request waiting for review.','REVIEW RETURNS',$this->absolute('admin/returns.php','?status=requested'),$dedupe),
            'return.approved','return.rejected','return.completed' => $subjectUserId?($this->queueForUser($subjectUserId,'orders',$eventKey==='return.approved'?'Return approved':($eventKey==='return.rejected'?'Return update':'Return completed'),'Return #'.(int)($payload['return_id']??0).' for order '.(string)($payload['order_number']??('#'.(int)($payload['order_id']??0))).' was '.str_replace('return.','',$eventKey).'.','VIEW ORDERS',$this->absolute('account-orders.php'),$dedupe)>0?1:0):0,
            'security.password_recovery_requested' => $this->queueAdminRoles(['owner','admin'],'security','Password recovery requested',(string)($payload['username']??'A customer').' requested password recovery.','REVIEW REQUEST',$this->absolute('admin/customers.php','?id='.(int)($payload['user_id']??0).'#recovery'),$dedupe),
            'security.password_changed','security.password_reset','security.web_session_revoked','security.web_sessions_revoked' => $subjectUserId?($this->queueForUser($subjectUserId,'security','Security alert',$this->securityEventBody($eventKey,$payload),'OPEN SECURITY',$this->absolute('account-security.php'),$dedupe)>0?1:0):0,
            default => 0,
        };
    }

    private function emailOrderCreated(?int $subjectUserId,array $payload,string $dedupe): int
    {
        $number=(string)($payload['order_number']??('#'.(int)($payload['order_id']??0)));
        $total=number_format((float)($payload['grand_total']??0),2).' '.strtoupper((string)($payload['currency']??'EGP'));
        $count=$this->queueAdminRoles(['owner','admin'],'orders','New store order','Order '.$number.' was placed for '.$total.' and needs processing.','OPEN ORDERS',$this->absolute('admin/orders.php','?q='.rawurlencode($number)),$dedupe);
        $method=(string)($payload['payment_method']??'cod');
        $body=$method==='manual_transfer'?'We received order '.$number.' for '.$total.'. You selected Bank transfer / InstaPay. Our team will contact you to arrange payment; no online payment was taken.':'We received order '.$number.' for '.$total.'. Payment is Cash on Delivery. We will notify you when its status changes.';
        if($subjectUserId!==null&&$subjectUserId>0){
            $count += $this->queueForUser($subjectUserId,'orders','Order '.$number.' received',$body,'VIEW ORDERS',$this->absolute('account-orders.php'),$dedupe.':customer')>0?1:0;
        }else{
            $guest=strtolower(trim((string)($payload['guest_email']??'')));
            $token=trim((string)($payload['guest_access_token']??''));
            $trackUrl=$token!==''?$this->absolute('track-order.php','?order='.rawurlencode($number).'&token='.rawurlencode($token)):null;
            if(filter_var($guest,FILTER_VALIDATE_EMAIL))$count += $this->queueRaw(null,$guest,'orders','Order '.$number.' received',$body,$trackUrl!==null?'TRACK ORDER':null,$trackUrl,$dedupe.':guest')>0?1:0;
        }
        return $count;
    }

    private function emailOrderUpdate(string $eventKey,?int $subjectUserId,array $payload,string $dedupe): int
    {
        $number=(string)($payload['order_number']??('#'.(int)($payload['order_id']??0)));
        $label=match($eventKey){'order.shipped'=>'shipped','order.refunded'=>'refund updated',default=>str_replace('_',' ',(string)($payload['status']??'updated'))};
        $subject=$eventKey==='order.shipped'?'Order '.$number.' shipped':($eventKey==='order.refunded'?'Refund update for '.$number:'Order '.$number.' updated');
        $body='Order '.$number.' has been '.$label.'.';
        if($subjectUserId!==null&&$subjectUserId>0)return $this->queueForUser($subjectUserId,'orders',$subject,$body,'VIEW ORDERS',$this->absolute('account-orders.php'),$dedupe)>0?1:0;
        $guest=strtolower(trim((string)($payload['guest_email']??'')));
        return filter_var($guest,FILTER_VALIDATE_EMAIL)?($this->queueRaw(null,$guest,'orders',$subject,$body,null,null,$dedupe.':guest')>0?1:0):0;
    }

    private function preferenceAllows(int $userId,string $category): bool
    {
        if(in_array($category,['security','test'],true))return true;
        $p=$this->emails->preferences($userId);
        return match($category){
            'support'=>!empty($p['support_enabled']),
            'orders'=>!empty($p['orders_enabled']),
            'marketing'=>!empty($p['marketing_enabled']),
            'maintenance'=>!empty($p['maintenance_enabled']),
            'announcements'=>!empty($p['announcements_enabled']),
            default=>true,
        };
    }

    private function smtpSettings(): array
    {
        $s=$this->emails->settings();
        if(trim((string)($s['smtp_host']??''))===''||!filter_var((string)($s['from_email']??''),FILTER_VALIDATE_EMAIL))throw new RuntimeException('SMTP settings are incomplete.');
        $s['smtp_password']=MailSecretStore::decrypt((string)($s['smtp_password_cipher']??''));
        return $s;
    }

    private function absolute(string $route,string $suffix=''): string
    {
        $base=rtrim((string)CANONICAL_SITE_URL,'/');
        return $base.\route_url($route).$suffix;
    }

    private function template(string $eyebrow,string $title,string $body,?string $ctaLabel,?string $ctaUrl): string
    {
        $e=htmlspecialchars($eyebrow,ENT_QUOTES,'UTF-8');$t=htmlspecialchars($title,ENT_QUOTES,'UTF-8');
        $b=nl2br(htmlspecialchars($body,ENT_QUOTES,'UTF-8'));
        $button='';
        if($ctaLabel&&$ctaUrl){
            $button='<p style="margin:28px 0 4px"><a href="'.htmlspecialchars($ctaUrl,ENT_QUOTES,'UTF-8').'" style="display:inline-block;background:#b69a67;color:#171614;text-decoration:none;padding:13px 20px;border-radius:4px;font-weight:700">'.htmlspecialchars($ctaLabel,ENT_QUOTES,'UTF-8').'</a></p>';
        }
        $brand=htmlspecialchars($this->brandName(),ENT_QUOTES,'UTF-8');
        return '<!doctype html><html><body style="margin:0;background:#f7f4ee;color:#171614;font-family:Arial,sans-serif"><div style="max-width:620px;margin:0 auto;padding:40px 24px"><div style="font-family:Georgia,serif;font-size:22px;letter-spacing:.16em;color:#171614">'.$brand.'</div><div style="font-size:11px;letter-spacing:.22em;color:#9a8258;margin-top:4px">STORE · '.$e.'</div><h1 style="font-family:Georgia,serif;font-weight:500;font-size:30px;margin:24px 0 14px;color:#171614">'.$t.'</h1><div style="font-size:16px;line-height:1.75;color:#625d54">'.$b.'</div>'.$button.'<div style="margin-top:38px;padding-top:18px;border-top:1px solid #e3ddd2;color:#918a80;font-size:12px">'.$brand.' · account notification</div></div></body></html>';
    }

    private function brandName(): string
    {
        try{if(function_exists('\\store_runtime_name')){$name=trim((string)\store_runtime_name());if($name!=='')return $name;}}catch(Throwable){}
        return 'Online Store';
    }

    private function securityEventBody(string $key,array $payload): string
    {
        return match($key){
            'security.password_changed'=>'Your website password was changed. Other browser sessions were closed.',
            'security.password_reset'=>'Your password was reset. Previous browser sessions were closed.',
            'security.web_session_revoked'=>'A browser session was revoked from your account.',
            'security.web_sessions_revoked'=>max(1,(int)($payload['count']??1)).' browser sessions were revoked from your account.',
            default=>'A security change was made on your account.',
        };
    }

    private function safeError(string $message): string
    {
        $message=preg_replace('/[\r\n]+/',' ',trim($message))??'';
        return substr($message,0,900);
    }
}
