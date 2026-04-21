<?php
declare(strict_types=1);

namespace Stain\Services;

use Stain\Auth\Jwt;
use Stain\Repositories\UserRepository;

final class AuthService
{
    private const NICKNAME_PATTERN = '/^[a-zA-Z0-9_\x{0410}-\x{044F}\x{0451}]{3,32}$/u';

    public function __construct(
        private readonly UserRepository $users,
        private readonly Jwt $jwt,
        private readonly int $ttlSeconds
    ) {}

    public function register(string $email, string $password, string $nickname): array
    {
        $email = strtolower(trim($email));
        $nickname = trim($nickname);
        $err = $this->validateNickname($nickname);
        if ($err !== null) {
            throw new \InvalidArgumentException($err);
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException('Укажите корректный email');
        }
        if (strlen($password) < 8) {
            throw new \InvalidArgumentException('Пароль не короче 8 символов');
        }
        if ($this->users->findByEmail($email) !== null) {
            throw new \InvalidArgumentException('Этот email уже зарегистрирован');
        }
        if ($this->users->findByNicknameIgnoreCase($nickname) !== null) {
            throw new \InvalidArgumentException('Этот никнейм уже занят');
        }

        $passwordHash = password_hash($password, PASSWORD_ARGON2ID);
        $user = $this->users->create($email, $passwordHash, 'author', $nickname);

        return [
            'user' => [
                'id' => (int) $user['id'],
                'email' => (string) $user['email'],
                'nickname' => (string) $user['nickname'],
                'role' => (string) $user['role'],
            ],
            'token' => $this->jwt->issue(
                [
                    'sub' => (int) $user['id'],
                    'role' => $user['role'],
                    'nickname' => (string) $user['nickname'],
                ],
                $this->ttlSeconds
            ),
        ];
    }

    public function login(string $email, string $password): array
    {
        $user = $this->users->findByEmail(strtolower(trim($email)));
        if ($user === null || !password_verify($password, $user['password_hash'])) {
            throw new \InvalidArgumentException('Неверный email или пароль');
        }

        return [
            'user' => [
                'id' => (int) $user['id'],
                'email' => (string) $user['email'],
                'nickname' => (string) ($user['nickname'] ?? ''),
                'role' => (string) $user['role'],
            ],
            'token' => $this->jwt->issue(
                [
                    'sub' => (int) $user['id'],
                    'role' => $user['role'],
                    'nickname' => (string) ($user['nickname'] ?? ''),
                ],
                $this->ttlSeconds
            ),
        ];
    }

    /**
     * Проверка занятости email и ника для формы регистрации (без сохранения).
     *
     * @return array{email_available: bool, nickname_available: bool, email_error: ?string, nickname_error: ?string}
     */
    public function checkRegistrationAvailability(string $email, string $nickname): array
    {
        $email = strtolower(trim($email));
        $nickname = trim($nickname);

        $emailError = null;
        if ($email === '') {
            $emailOk = true;
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $emailOk = false;
            $emailError = 'Некорректный email';
        } else {
            $emailOk = $this->users->isEmailAvailable($email);
            if (!$emailOk) {
                $emailError = 'Этот email уже зарегистрирован';
            }
        }

        $nicknameError = null;
        if ($nickname !== '') {
            $nicknameError = $this->validateNickname($nickname);
            if ($nicknameError === null && !$this->users->isNicknameAvailable($nickname)) {
                $nicknameError = 'Этот никнейм уже занят';
            }
        }

        return [
            'email_available' => $emailError === null && $emailOk,
            'nickname_available' => $nicknameError === null,
            'email_error' => $emailError,
            'nickname_error' => $nicknameError,
        ];
    }

    /**
     * Возвращает текст ошибки или null, если ник допустим (при непустом вводе).
     */
    public function validateNickname(string $nickname): ?string
    {
        $nickname = trim($nickname);
        if ($nickname === '') {
            return 'Укажите никнейм';
        }
        $len = mb_strlen($nickname);
        if ($len < 3 || $len > 32) {
            return 'Никнейм: от 3 до 32 символов';
        }
        if (!preg_match(self::NICKNAME_PATTERN, $nickname)) {
            return 'Никнейм: буквы, цифры и «_» (латиница или кириллица)';
        }

        return null;
    }
}
