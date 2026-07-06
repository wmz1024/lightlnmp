<?php

final class DatabaseManager
{
    public function databases(): array
    {
        $run = SystemCommand::run(['db', 'list']);
        return $run['ok'] && $run['output'] !== '' ? explode("\n", trim($run['output'])) : [];
    }

    public function create(string $name): array
    {
        if (!Security::dbName($name)) {
            return ['ok' => false, 'output' => 'Invalid database name'];
        }
        $run = SystemCommand::run(['db', 'create', $name]);
        Db::audit('db.create', $name, $run['ok'] ? 'ok' : 'fail', $run['output']);
        return $run;
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
}
