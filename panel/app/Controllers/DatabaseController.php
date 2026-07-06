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
                'create' => $manager->createWithCharset(trim($_POST['name'] ?? ''), $_POST['charset'] ?? 'utf8mb4'),
                'create-user-db' => $manager->createUserAndDatabase(trim($_POST['database'] ?? ''), trim($_POST['user'] ?? ''), $_POST['password'] ?? '', $_POST['charset'] ?? 'utf8mb4'),
                'delete' => $manager->delete(trim($_POST['name'] ?? '')),
                'batch-delete' => $manager->batchDelete($_POST['names'] ?? []),
                'user-create' => $manager->createUser(trim($_POST['user'] ?? ''), $_POST['password'] ?? '', trim($_POST['database'] ?? '')),
                'user-delete' => $manager->deleteUser(trim($_POST['user'] ?? '')),
                'user-password' => $manager->changePassword(trim($_POST['user'] ?? ''), $_POST['password'] ?? ''),
                'grant' => $manager->grant(trim($_POST['user'] ?? ''), trim($_POST['database'] ?? '')),
                default => ['ok' => false, 'output' => 'Unknown action'],
            };
            flash($run['ok'] ? '数据库操作完成' : $run['output'], $run['ok'] ? 'success' : 'danger');
            redirect('databases');
        }
        $databases = $manager->databases();
        $dbPager = paginate($databases, 'db_page');
        $userPager = paginate($manager->users(), 'db_user_page');
        view('databases', [
            'title' => '数据库管理',
            'databases' => $dbPager['items'],
            'allDatabases' => $databases,
            'dbPager' => $dbPager,
            'dbUsers' => $userPager['items'],
            'dbUserPager' => $userPager,
            'charsets' => $manager->charsets(),
        ]);
    }
}
