<?php
declare(strict_types=1);

namespace App\Support;

/**
 * Backward-compatible SMTP secret facade.
 *
 * v1.7.2 centralizes secret encryption in SecretStore while deliberately keeping
 * the same v1 AES-GCM payload format while allowing the active mail.key to live
 * in persistent storage outside the replaceable website tree. Legacy
 * storage/private/mail.key remains readable during the transition.
 */
final class MailSecretStore
{
    public static function keyPath(): string
    {
        return SecretStore::keyPath('mail');
    }

    public static function available(): bool
    {
        return SecretStore::available();
    }

    public static function encrypt(string $plain): string
    {
        return SecretStore::encrypt($plain, 'mail');
    }

    public static function decrypt(?string $encoded): string
    {
        return SecretStore::decrypt($encoded, 'mail');
    }
}
