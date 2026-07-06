<?php

final class DatabaseController
{
    public function index(string $method): void
    {
        $manager = new DatabaseManager();
        if ($method === 'POST') {
            Csrf::verify();
            $action = $_POST['action'] ?? '';
            $run = match ($action) {
                'create' => $manager->create(trim($_POST['name'] ?? '')),
                'delete' => $manager->delete(trim($_POST['name'] ?? '')),
                'user-create' => $manager->createUser(trim($_POST['user'] ?? ''), $_POST['password'] ?? '', trim($_POST['database'] ?? '')),
                default => ['ok' => false, 'output' => 'Unknown action'],
            };
            flash($run['ok'] ? '数据库操作完成' : $run['output'], $run['ok'] ? 'success' : 'danger');
            redirect('databases');
        }
        view('databases', ['title' => '数据库管理', 'databases' => $manager->databases()]);
    }
}
