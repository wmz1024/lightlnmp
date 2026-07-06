<?php

define('BASE_PATH', dirname(__DIR__));
define('APP_PATH', BASE_PATH . '/app');
define('STORAGE_PATH', BASE_PATH . '/storage');
define('DB_PATH', STORAGE_PATH . '/panel.sqlite');
define('INSTALL_DIR', dirname(BASE_PATH));

if (!is_dir(STORAGE_PATH . '/logs')) {
    mkdir(STORAGE_PATH . '/logs', 0750, true);
}

session_name('LIGHTLNMPSESSID');
session_start();

foreach ([
    'Db.php', 'Auth.php', 'Csrf.php', 'Security.php', 'Router.php',
    'Services/SystemCommand.php', 'Services/ServiceManager.php', 'Services/SiteManager.php',
    'Services/FileManager.php', 'Services/DatabaseManager.php', 'Services/AcmeManager.php',
    'Controllers/AuthController.php', 'Controllers/DashboardController.php', 'Controllers/SiteController.php',
    'Controllers/FileController.php', 'Controllers/DatabaseController.php', 'Controllers/SslController.php',
    'Controllers/ServiceController.php',
] as $file) {
    require APP_PATH . '/' . $file;
}

Db::migrate();

function h(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

function setting(string $key, ?string $default = null): ?string
{
    $stmt = Db::conn()->prepare('SELECT value FROM settings WHERE key = :key');
    $stmt->execute(['key' => $key]);
    $value = $stmt->fetchColumn();
    return $value === false ? $default : $value;
}

function redirect(string $route): void
{
    header('Location: ?r=' . $route);
    exit;
}

function flash(?string $message = null, string $type = 'info'): ?array
{
    if ($message !== null) {
        $_SESSION['flash'] = ['message' => $message, 'type' => $type];
        return null;
    }
    $flash = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return $flash;
}

function view(string $template, array $data = []): void
{
    extract($data, EXTR_SKIP);
    $viewFile = APP_PATH . '/Views/' . $template . '.php';
    require APP_PATH . '/Views/layout.php';
}
