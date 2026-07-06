<?php

final class Auth
{
    private const MAX_LOGIN_FAILURES = 5;
    private const LOGIN_LOCK_SECONDS = 600;

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

    public static function isLocked(string $username, string $ip): bool
    {
        $stmt = Db::conn()->prepare('SELECT locked_until FROM login_attempts WHERE username = :username AND ip = :ip');
        $stmt->execute(['username' => $username, 'ip' => $ip]);
        $lockedUntil = (int)($stmt->fetchColumn() ?: 0);
        if ($lockedUntil > time()) {
            return true;
        }
        if ($lockedUntil > 0) {
            self::clearFailures($username, $ip);
        }
        return false;
    }

    public static function recordFailure(string $username, string $ip): void
    {
        $stmt = Db::conn()->prepare('SELECT failed_count, locked_until FROM login_attempts WHERE username = :username AND ip = :ip');
        $stmt->execute(['username' => $username, 'ip' => $ip]);
        $row = $stmt->fetch() ?: ['failed_count' => 0, 'locked_until' => 0];
        $failed = ((int)$row['locked_until'] > 0 && (int)$row['locked_until'] <= time() ? 0 : (int)$row['failed_count']) + 1;
        $lockedUntil = $failed >= self::MAX_LOGIN_FAILURES ? time() + self::LOGIN_LOCK_SECONDS : 0;
        $stmt = Db::conn()->prepare('INSERT INTO login_attempts(username, ip, failed_count, locked_until, updated_at) VALUES(:username, :ip, :failed, :locked_until, CURRENT_TIMESTAMP) ON CONFLICT(username, ip) DO UPDATE SET failed_count = excluded.failed_count, locked_until = excluded.locked_until, updated_at = CURRENT_TIMESTAMP');
        $stmt->execute(['username' => $username, 'ip' => $ip, 'failed' => $failed, 'locked_until' => $lockedUntil]);
    }

    public static function clearFailures(string $username, string $ip): void
    {
        Db::conn()->prepare('DELETE FROM login_attempts WHERE username = :username AND ip = :ip')->execute(['username' => $username, 'ip' => $ip]);
    }

    public static function changePassword(string $currentPassword, string $newPassword): array
    {
        $user = self::user();
        if (!$user) {
            return ['ok' => false, 'output' => 'Not logged in'];
        }
        if (strlen($newPassword) < 8 || strlen($newPassword) > 128) {
            return ['ok' => false, 'output' => '新密码长度必须为 8-128 位'];
        }
        $stmt = Db::conn()->prepare('SELECT password_hash FROM users WHERE id = :id');
        $stmt->execute(['id' => (int)$user['id']]);
        $hash = $stmt->fetchColumn();
        if (!$hash || !password_verify($currentPassword, $hash)) {
            return ['ok' => false, 'output' => '当前密码错误'];
        }
        $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
        Db::conn()->prepare('UPDATE users SET password_hash = :hash WHERE id = :id')->execute(['hash' => $newHash, 'id' => (int)$user['id']]);
        Db::audit('account.password', $user['username'], 'ok');
        return ['ok' => true, 'output' => 'ok'];
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
