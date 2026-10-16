<?php
declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Support\ServiceFactory;

final class SecurityController
{
    private const FLASH_KEY = 'admin_security';

    public function handle(): array
    {
        $twoFactorService = ServiceFactory::twoFactor(\db());
        $admin = \current_user();
        $adminId = (int)($admin['id'] ?? 0);
        $schemaReady = \admin_2fa_schema_ready();
        $encryptedStorageReady = $schemaReady && $twoFactorService->encryptedStorageReady();

        if ($schemaReady) {
            $candidate = (string)($_SESSION['admin_2fa_setup_secret'] ?? '');
            if (!\totp_secret_is_valid($candidate)) {
                $_SESSION['admin_2fa_setup_secret'] = \totp_generate_secret();
            }
        }

        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            \verify_csrf();
            $action = (string)($_POST['action'] ?? '');
            $message = '';
            $error = '';

            if (!$schemaReady) {
                $error = 'Admin 2FA database migration has not been applied yet.';
            } elseif ($action === 'enable_2fa' && !$encryptedStorageReady) {
                $error = 'Run migration 025 and verify OpenSSL before enabling encrypted authenticator 2FA.';
            } elseif ($action === 'new_setup_secret') {
                $_SESSION['admin_2fa_setup_secret'] = \totp_generate_secret();
                $message = 'A new setup key was generated.';
            } elseif ($action === 'enable_2fa') {
                $secret = strtoupper(trim((string)($_SESSION['admin_2fa_setup_secret'] ?? '')));
                $code = trim((string)($_POST['totp_code'] ?? ''));
                if (!\web_request_is_https()) {
                    $error = 'Enable admin 2FA only over HTTPS so the setup key is never sent over plain HTTP.';
                } elseif (!\admin_require_password_from_post($error)) {
                    // Error already set.
                } elseif ($secret === '' || !\totp_verify_code($secret, $code)) {
                    $error = 'The authenticator code is invalid. Check the setup key and device time, then try again.';
                } else {
                    $codes = \totp_generate_recovery_codes();
                    $twoFactorService->enable($adminId, $secret, \totp_encode_recovery_hashes($codes));
                    $_SESSION['admin_2fa_verified_for'] = $adminId;
                    $_SESSION['admin_2fa_verified_at'] = time();
                    $_SESSION['admin_2fa_recovery_display'] = $codes;
                    unset($_SESSION['admin_2fa_setup_secret']);
                    \admin_audit('admin_2fa_enabled', 'Admin enabled TOTP two-factor authentication');
                    $message = 'Two-factor authentication enabled. Save the recovery codes below now.';
                }
            } elseif ($action === 'disable_2fa') {
                $code = trim((string)($_POST['totp_code'] ?? ''));
                if (!\admin_require_password_from_post($error)) {
                    // Error already set.
                } else {
                    $verified = \admin_2fa_verify_or_consume($adminId, $code);
                    if (!$verified['ok']) {
                        $error = 'The authenticator or recovery code is invalid.';
                    } else {
                        $twoFactorService->disable($adminId);
                        unset($_SESSION['admin_2fa_verified_for'], $_SESSION['admin_2fa_verified_at']);
                        $_SESSION['admin_2fa_setup_secret'] = \totp_generate_secret();
                        \admin_audit('admin_2fa_disabled', 'Admin disabled TOTP two-factor authentication');
                        $message = 'Two-factor authentication disabled.';
                    }
                }
            } elseif ($action === 'regenerate_recovery') {
                $code = trim((string)($_POST['totp_code'] ?? ''));
                if (!\admin_require_password_from_post($error)) {
                    // Error already set.
                } else {
                    $verified = \admin_2fa_verify_or_consume($adminId, $code);
                    if (!$verified['ok']) {
                        $error = 'The authenticator or recovery code is invalid.';
                    } else {
                        $codes = \totp_generate_recovery_codes();
                        $twoFactorService->updateRecoveryHashes($adminId, \totp_encode_recovery_hashes($codes));
                        $_SESSION['admin_2fa_recovery_display'] = $codes;
                        \admin_audit('admin_2fa_recovery_regenerated', 'Admin generated a new recovery-code set');
                        $message = 'New recovery codes generated. Previous recovery codes no longer work.';
                    }
                }
            } else {
                $error = 'Unknown security action.';
            }

            \web_post_redirect('admin/security.php', self::FLASH_KEY, $message, $error);
        }

        $flash = \web_flash_take(self::FLASH_KEY);
        $state = $schemaReady ? \admin_2fa_state($adminId) : ['enabled'=>false,'secret'=>null,'recovery_codes'=>[],'enabled_at'=>null];
        $setupSecret = (!$state['enabled'] && $schemaReady) ? (string)($_SESSION['admin_2fa_setup_secret'] ?? '') : '';
        $recoveryDisplay = $_SESSION['admin_2fa_recovery_display'] ?? [];
        unset($_SESSION['admin_2fa_recovery_display']);
        $otpauth = ($setupSecret !== '') ? \totp_otpauth_uri($setupSecret, (string)($admin['username'] ?? 'admin')) : '';
        return [
            'admin' => $admin,
            'adminId' => $adminId,
            'message' => $flash['message'],
            'error' => $flash['error'],
            'schemaReady' => $schemaReady,
            'encryptedStorageReady' => $encryptedStorageReady,
            'state' => $state,
            'setupSecret' => $setupSecret,
            'recoveryDisplay' => $recoveryDisplay,
            'otpauth' => $otpauth,
        ];
    }
}
