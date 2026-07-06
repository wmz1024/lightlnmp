<?php

final class Auth
{
    public static function attempt(string $username, string $password): bool
    {
        $stmt = Db::conn()->prepare('SELECT * FROM users WHERE username = :username');
        $stmt->execute(['username' => $username]);
        $user = $stmt->fetch();
        if (!$user || !password_verify($password, $user['password_hash'])) {
            return false;
        }
        session_regenerate_id(true);
        $_SESSION['user'] = ['id' => $user['id'], 'username' => $user['username']];
        return true;
    }

    public static function check(): bool
    {
        return isset($_SESSION['user']);
    }

    public static function user(): ?array
    {
        return $_SESSION['user'] ?? null;
    }

    public static function requireLogin(): void
    {
        if (!self::check()) {
            redirect('login');
        }
    }

    public static function logout(): void
    {
        $_SESSION = [];
        session_destroy();
    }
}
