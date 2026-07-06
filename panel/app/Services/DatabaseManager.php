<?php

final class DatabaseManager
{
    private array $charsets = [
        'utf8mb4' => 'utf8mb4_unicode_ci',
        'utf8' => 'utf8_general_ci',
        'latin1' => 'latin1_swedish_ci',
        'gbk' => 'gbk_chinese_ci',
    ];

    public function databases(): array
    {
        $run = SystemCommand::run(['db', 'list']);
        return $run['ok'] && $run['output'] !== '' ? explode("\n", trim($run['output'])) : [];
    }

    public function create(string $name): array
    {
        return $this->createWithCharset($name, 'utf8mb4');
    }

    public function createWithCharset(string $name, string $charset): array
    {
        if (!Security::dbName($name)) {
            return ['ok' => false, 'output' => 'Invalid database name'];
        }
        if (!isset($this->charsets[$charset])) {
            return ['ok' => false, 'output' => 'Invalid charset'];
        }
        $run = SystemCommand::run(['db', 'create', $name, $charset, $this->charsets[$charset]]);
        Db::audit('db.create', $name, $run['ok'] ? 'ok' : 'fail', $run['output']);
        return $run;
    }

    public function charsets(): array
    {
        return $this->charsets;
    }

    public function delete(string $name): array
    {
        if (!Security::dbName($name)) {
            return ['ok' => false, 'output' => 'Invalid database name'];
        }
        $run = SystemCommand::run(['db', 'delete', $name]);
        Db::audit('db.delete', $name, $run['ok'] ? 'ok' : 'fail', $run['output']);
        return $run;
    }

    public function createUser(string $user, string $password, string $database): array
    {
        if (!Security::dbName($user) || !Security::dbPassword($password) || ($database !== '' && !Security::dbName($database))) {
            return ['ok' => false, 'output' => 'Invalid database user, password, or database'];
        }
        $args = ['db', 'user-create', $user, $password];
        if ($database !== '') {
            $args[] = $database;
        }
        $run = SystemCommand::run($args);
        Db::audit('db.user-create', $user, $run['ok'] ? 'ok' : 'fail', $run['output']);
        return $run;
    }

    public function createUserAndDatabase(string $database, string $user, string $password, string $charset): array
    {
        $created = $this->createWithCharset($database, $charset);
        if (!$created['ok']) {
            return $created;
        }
        return $this->createUser($user, $password, $database);
    }

    public function users(): array
    {
        $run = SystemCommand::run(['db', 'user-list']);
        if (!$run['ok'] || trim($run['output']) === '') {
            return [];
        }
        $users = [];
        foreach (explode("\n", trim($run['output'])) as $line) {
            [$user, $host] = array_pad(explode("\t", $line, 2), 2, '');
            if ($user !== '') {
                $users[] = ['user' => $user, 'host' => $host ?: 'localhost'];
            }
        }
        return $users;
    }

    public function deleteUser(string $user): array
    {
        if (!Security::dbName($user)) {
            return ['ok' => false, 'output' => 'Invalid database user'];
        }
        $run = SystemCommand::run(['db', 'user-delete', $user]);
        Db::audit('db.user-delete', $user, $run['ok'] ? 'ok' : 'fail', $run['output']);
        return $run;
    }

    public function changePassword(string $user, string $password): array
    {
        if (!Security::dbName($user) || !Security::dbPassword($password)) {
            return ['ok' => false, 'output' => 'Invalid database user or password'];
        }
        $run = SystemCommand::run(['db', 'user-password', $user, $password]);
        Db::audit('db.user-password', $user, $run['ok'] ? 'ok' : 'fail', $run['output']);
        return $run;
    }

    public function grant(string $user, string $database): array
    {
        if (!Security::dbName($user) || !Security::dbName($database)) {
            return ['ok' => false, 'output' => 'Invalid database user or database'];
        }
        $run = SystemCommand::run(['db', 'grant', $user, $database]);
        Db::audit('db.grant', $user, $run['ok'] ? 'ok' : 'fail', $database . "\n" . $run['output']);
        return $run;
    }

    public function batchDelete(array $names): array
    {
        $names = array_values(array_unique(array_map('trim', $names)));
        if (!$names) {
            return ['ok' => false, 'output' => 'No databases selected'];
        }

        $errors = [];
        foreach ($names as $name) {
            $run = $this->delete($name);
            if (!$run['ok']) {
                $errors[] = $name . ': ' . $run['output'];
            }
        }
        return $errors ? ['ok' => false, 'output' => implode("\n", $errors)] : ['ok' => true, 'output' => 'ok'];
    }

    public function export(string $name): array
    {
        if (!Security::dbName($name)) {
            return ['ok' => false, 'output' => 'Invalid database name'];
        }
        $run = SystemCommand::run(['db', 'export', $name]);
        Db::audit('db.export', $name, $run['ok'] ? 'ok' : 'fail', $run['ok'] ? '' : $run['output']);
        return $run;
    }

    public function import(string $name, array $file): array
    {
        if (!Security::dbName($name)) {
            return ['ok' => false, 'output' => 'Invalid database name'];
        }
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'] ?? '')) {
            return ['ok' => false, 'output' => 'SQL upload failed'];
        }
        $ext = strtolower(pathinfo($file['name'] ?? '', PATHINFO_EXTENSION));
        if ($ext !== 'sql') {
            return ['ok' => false, 'output' => 'Only .sql files can be imported'];
        }
        $dir = STORAGE_PATH . '/imports';
        if (!is_dir($dir)) {
            mkdir($dir, 0750, true);
        }
        $target = $dir . '/' . date('YmdHis') . '-' . bin2hex(random_bytes(4)) . '.sql';
        if (!move_uploaded_file($file['tmp_name'], $target)) {
            return ['ok' => false, 'output' => 'Unable to store SQL file'];
        }
        $run = SystemCommand::run(['db', 'import', $name, $target]);
        @unlink($target);
        Db::audit('db.import', $name, $run['ok'] ? 'ok' : 'fail', $run['output']);
        return $run;
    }
}
