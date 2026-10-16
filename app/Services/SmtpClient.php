<?php
declare(strict_types=1);

namespace App\Services;

use RuntimeException;

final class SmtpClient
{
    /** @param array<string,mixed> $settings */
    public function send(array $settings,string $to,string $subject,string $text,string $html,int $timeoutSeconds=15): void
    {
        $host=trim((string)($settings['smtp_host']??''));
        $port=(int)($settings['smtp_port']??587);
        $encryption=strtolower(trim((string)($settings['smtp_encryption']??'tls')));
        $username=(string)($settings['smtp_username']??'');
        $password=(string)($settings['smtp_password']??'');
        $fromEmail=trim((string)($settings['from_email']??''));
        $fromName=trim((string)($settings['from_name']??'Online Store'));
        $replyTo=trim((string)($settings['reply_to_email']??''));
        if($host===''||$port<1||$port>65535)throw new RuntimeException('SMTP host/port is not configured.');
        if(!in_array($encryption,['none','tls','ssl'],true))throw new RuntimeException('Unsupported SMTP encryption mode.');
        foreach([$to,$fromEmail] as $email)if(!filter_var($email,FILTER_VALIDATE_EMAIL))throw new RuntimeException('A mail address is invalid.');
        if($replyTo!==''&&!filter_var($replyTo,FILTER_VALIDATE_EMAIL))throw new RuntimeException('Reply-to address is invalid.');

        $timeoutSeconds=max(2,min(30,$timeoutSeconds));
        $remote=($encryption==='ssl'?'ssl://':'').$host.':'.$port;
        $context=stream_context_create(['ssl'=>[
            'verify_peer'=>true,'verify_peer_name'=>true,'allow_self_signed'=>false,'SNI_enabled'=>true,'peer_name'=>$host,
        ]]);
        $errno=0;$errstr='';
        $socket=@stream_socket_client($remote,$errno,$errstr,$timeoutSeconds,STREAM_CLIENT_CONNECT,$context);
        if(!is_resource($socket))throw new RuntimeException('SMTP connection failed: '.($errstr!==''?$errstr:'connection error').' ('.$errno.').');
        stream_set_timeout($socket,$timeoutSeconds);
        try{
            $this->expect($socket,[220]);
            $hello=$this->hostName();
            $this->command($socket,'EHLO '.$hello,[250]);
            if($encryption==='tls'){
                $this->command($socket,'STARTTLS',[220]);
                $crypto=@stream_socket_enable_crypto($socket,true,STREAM_CRYPTO_METHOD_TLS_CLIENT);
                if($crypto!==true)throw new RuntimeException('SMTP STARTTLS negotiation failed.');
                $this->command($socket,'EHLO '.$hello,[250]);
            }
            if($username!==''){
                $this->command($socket,'AUTH LOGIN',[334]);
                $this->command($socket,base64_encode($username),[334],false);
                $this->command($socket,base64_encode($password),[235],false);
            }
            $this->command($socket,'MAIL FROM:<'.$fromEmail.'>',[250]);
            $this->command($socket,'RCPT TO:<'.$to.'>',[250,251]);
            $this->command($socket,'DATA',[354]);
            $message=$this->message($fromEmail,$fromName,$replyTo,$to,$subject,$text,$html);
            $message=preg_replace('/(?m)^\./','..',$message)??$message;
            fwrite($socket,$message."\r\n.\r\n");
            $this->expect($socket,[250]);
            $this->command($socket,'QUIT',[221]);
        } finally {
            fclose($socket);
        }
    }

    /** @param resource $socket */
    private function command($socket,string $command,array $expected,bool $logSafe=true): string
    {
        if(fwrite($socket,$command."\r\n")===false)throw new RuntimeException('SMTP write failed.');
        return $this->expect($socket,$expected);
    }

    /** @param resource $socket */
    private function expect($socket,array $expected): string
    {
        $response='';$code=0;
        while(!feof($socket)){
            $line=fgets($socket,4096);
            if($line===false)break;
            $response.=$line;
            if(preg_match('/^(\d{3})([ -])/', $line,$m)){
                $code=(int)$m[1];
                if($m[2]===' ')break;
            }
            if(strlen($response)>65536)break;
        }
        $meta=stream_get_meta_data($socket);
        if(!empty($meta['timed_out']))throw new RuntimeException('SMTP server timed out.');
        if(!in_array($code,$expected,true)){
            $safe=trim(preg_replace('/[\r\n]+/',' ',substr($response,0,500))??'');
            throw new RuntimeException('SMTP server rejected the request'.($code>0?' ('.$code.')':'').($safe!==''?': '.$safe:''));
        }
        return $response;
    }

    private function message(string $fromEmail,string $fromName,string $replyTo,string $to,string $subject,string $text,string $html): string
    {
        $fromName=$this->headerValue($fromName);
        $subject=$this->headerValue($subject);
        $to=$this->headerValue($to);$fromEmail=$this->headerValue($fromEmail);$replyTo=$this->headerValue($replyTo);
        $boundary='auraa_'.bin2hex(random_bytes(12));
        $headers=[
            'Date: '.date(DATE_RFC2822),
            'From: '.$this->encodeHeader($fromName).' <'.$fromEmail.'>',
            'To: <'.$to.'>',
            'Subject: '.$this->encodeHeader($subject),
            'Message-ID: <'.bin2hex(random_bytes(12)).'@'.$this->hostName().'>',
            'MIME-Version: 1.0',
            'Content-Type: multipart/alternative; boundary="'.$boundary.'"',
        ];
        if($replyTo!=='')$headers[]='Reply-To: <'.$replyTo.'>';
        $body='--'.$boundary."\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: quoted-printable\r\n\r\n".quoted_printable_encode($text)."\r\n";
        $body.='--'.$boundary."\r\nContent-Type: text/html; charset=UTF-8\r\nContent-Transfer-Encoding: quoted-printable\r\n\r\n".quoted_printable_encode($html)."\r\n";
        $body.='--'.$boundary."--";
        return implode("\r\n",$headers)."\r\n\r\n".$body;
    }

    private function headerValue(string $value): string
    {
        return trim(str_replace(["\r","\n"],' ',$value));
    }

    private function encodeHeader(string $value): string
    {
        if($value==='')return '';
        if(preg_match('/^[\x20-\x7E]+$/',$value))return $value;
        return '=?UTF-8?B?'.base64_encode($value).'?=';
    }

    private function hostName(): string
    {
        $host=(string)($_SERVER['HTTP_HOST']??gethostname()?:'localhost');
        $host=preg_replace('/:\d+$/','',$host)??$host;
        return preg_match('/^[A-Za-z0-9.-]+$/',$host)?$host:'localhost';
    }
}
