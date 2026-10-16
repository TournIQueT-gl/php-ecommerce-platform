<?php
declare(strict_types=1);

namespace App\Services;

use App\Repositories\UserRepository;
use App\Support\Validation;
use PDOException;

final class AuthenticationService
{
    private const DUMMY_PASSWORD_HASH = '$2y$12$1AfUpEvJyTHZ1dICrPJzTej1QycsnAPQkiD1Wn3saAfpmdZNZoZ2W';

    public function __construct(private UserRepository $users) {}

    public function attempt(string $username, string $password): array
    {
        $username = trim($username);
        if (!Validation::loginCredentialsValid($username, $password)) {
            return ['ok' => false, 'error' => 'Invalid username or password.', 'user' => null];
        }

        $user = $this->users->findCredentialsByUsername($username);
        $hash=$user?(string)$user['password_hash']:self::DUMMY_PASSWORD_HASH;
        $passwordOk=password_verify($password,$hash);
        if (!$user || !$passwordOk || !(bool)$user['is_active']) {
            return ['ok' => false, 'error' => 'Invalid username or password.', 'user' => null];
        }

        if (password_needs_rehash((string)$user['password_hash'], PASSWORD_DEFAULT)) {
            $this->users->updatePasswordHash((int)$user['id'], password_hash($password, PASSWORD_DEFAULT));
        }

        return ['ok' => true, 'error' => '', 'user' => $user];
    }

    public function register(string $username, string $password, string $confirmPassword, ?string $email = null): array
    {
        $username = trim($username);
        $validationError = Validation::registrationError($username, $password, $confirmPassword);
        if ($validationError !== null) {
            return ['ok' => false, 'error' => $validationError, 'user_id' => null];
        }
        if ($this->users->usernameExists($username)) {
            return ['ok' => false, 'error' => 'Username or email already exists.', 'user_id' => null];
        }
        $email = $email !== null ? strtolower(trim($email)) : null;
        if ($email === '') $email = null;
        $emailError = Validation::emailError($email, false);
        if ($emailError !== null) return ['ok'=>false,'error'=>$emailError,'user_id'=>null];
        if ($email !== null && !$this->users->emailColumnsReady()) {
            return ['ok'=>false,'error'=>'Email registration is temporarily unavailable because the database schema is incomplete. You can leave email blank and contact the site administrator.','user_id'=>null];
        }

        try {
            $userId = $this->users->create($username, password_hash($password, PASSWORD_DEFAULT), $email);
            $this->users->touchLastLogin($userId);
            return ['ok' => true, 'error' => '', 'user_id' => $userId];
        } catch (PDOException $e) {
            if ((string)$e->getCode() === '23000') {
                return ['ok' => false, 'error' => 'Username or email already exists.', 'user_id' => null];
            }
            throw $e;
        }
    }
}
