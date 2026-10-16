<?php
declare(strict_types=1);

namespace App\Http\Controllers;

use App\Support\ServiceFactory;

final class AccountSecurityController
{
    private const FLASH_KEY = 'account_security';

    public function handle(): array
    {
        $user = \current_user();
        if (!$user) { header('Location: ' . \route_url('login.php')); exit; }
        $userId = (int)$user['id'];
        $db=\db();
        $accountService = ServiceFactory::account($db);

        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            \verify_csrf();
            $action = (string)($_POST['action'] ?? '');
            $message = '';$error = '';

            if ($action === 'logout_web_session') {
                $result=$accountService->logoutWebSession($userId,(int)($_POST['session_id']??0),session_id());
                if($result['ok'])$message=(string)$result['message'];else$error=(string)$result['message'];
            } elseif ($action === 'logout_other_web_sessions') {
                $result=$accountService->logoutOtherWebSessions($userId,session_id());
                if($result['ok'])$message=(string)$result['message'];else$error=(string)$result['message'];
            } elseif ($action === 'change_password') {
                $result = $accountService->changePassword(
                    $userId,
                    (string)($_POST['current_password'] ?? ''),
                    (string)($_POST['new_password'] ?? ''),
                    (string)($_POST['confirm_password'] ?? '')
                );
                if (!$result['ok']) $error = (string)$result['message'];
                else {
                    \destroy_web_session();
                    header('Location: ' . \route_url('login.php') . '?password=changed', true, 303);
                    exit;
                }
            } else $error = 'Unknown security action.';

            \web_post_redirect('account-security.php', self::FLASH_KEY, $message, $error);
        }

        $flash = \web_flash_take(self::FLASH_KEY);
        $webSessions=[];$loginHistory=[];$sessionSchemaReady=false;
        try{
            $sessionRepo=ServiceFactory::webSessions($db);
            $sessionSchemaReady=$sessionRepo->schemaReady();
            if($sessionSchemaReady)$webSessions=$sessionRepo->listForUser($userId,30);
            $loginHistory=ServiceFactory::loginSecurity($db)->forUser($userId,25);
        }catch(\Throwable $e){\App\Support\Logger::warning('Account security data unavailable',['user_id'=>$userId,'message'=>$e->getMessage()]);}
        $currentWebSessionHash=session_id()!==''?\App\Repositories\WebSessionRepository::hash(session_id()):'';
        $activeBrowserSessions=0;
        foreach($webSessions as $row)if(empty($row['revoked_at']))$activeBrowserSessions++;
        $lastSuccessfulLogin=null;
        foreach($loginHistory as $row){if(!empty($row['success'])){$lastSuccessfulLogin=$row;break;}}
        return [
            'user'=>$user,'message'=>$flash['message'],'error'=>$flash['error'],
            'webSessions'=>$webSessions,'loginHistory'=>$loginHistory,
            'sessionSchemaReady'=>$sessionSchemaReady,'currentWebSessionHash'=>$currentWebSessionHash,
            'activeBrowserSessions'=>$activeBrowserSessions,'lastSuccessfulLogin'=>$lastSuccessfulLogin,
        ];
    }
}
