<?php
declare(strict_types=1);

namespace App\Support;

final class Validation
{
    public static function loginCredentialsValid(string $username, string $password): bool
    {
        $username = trim($username);
        return $username !== '' && strlen($username) <= 32 && $password !== '' && strlen($password) <= 256;
    }

    public static function registrationError(string $username, string $password, string $confirmPassword): ?string
    {
        $username = trim($username);
        if (!preg_match('/^[A-Za-z0-9_]{3,32}$/', $username)) {
            return 'Username must be 3-32 characters: letters, numbers, underscore.';
        }
        if (strlen($password) < 8 || strlen($password) > 256) {
            return 'Password must be 8-256 characters.';
        }
        if ($password !== $confirmPassword) {
            return 'Passwords do not match.';
        }
        return null;
    }


    public static function emailError(?string $email, bool $required = false): ?string
    {
        $email = trim((string)$email);
        if ($email === '') return $required ? 'Email address is required.' : null;
        if (strlen($email) > 254 || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            return 'Enter a valid email address.';
        }
        return null;
    }

    public static function passwordChangeError(string $currentPassword, string $newPassword, string $confirmPassword): ?string
    {
        if (strlen($currentPassword) > 256 || strlen($newPassword) < 8 || strlen($newPassword) > 256) {
            return 'New password must be 8-256 characters.';
        }
        if ($newPassword !== $confirmPassword) {
            return 'New passwords do not match.';
        }
        return null;
    }

    public static function passwordResetError(string $newPassword, string $confirmPassword): ?string
    {
        if (strlen($newPassword) < 8 || strlen($newPassword) > 256) return 'New password must be 8-256 characters.';
        if ($newPassword !== $confirmPassword) return 'New passwords do not match.';
        return null;
    }
}
