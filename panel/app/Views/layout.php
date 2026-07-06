<?php
$flash = flash();
$currentRoute = explode('/', $_GET['r'] ?? 'dashboard')[0];
$navItems = [
    'dashboard' => ['label' => '概览', 'icon' => 'ti-layout-dashboard'],
    'sites' => ['label' => '网站', 'icon' => 'ti-world-www'],
    'files' => ['label' => '文件', 'icon' => 'ti-folder'],
    'databases' => ['label' => '数据库', 'icon' => 'ti-database'],
    'ssl' => ['label' => '证书', 'icon' => 'ti-shield-lock'],
    'services' => ['label' => '服务', 'icon' => 'ti-server'],
];
?>
<!doctype html>
<html lang="zh-CN">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= h($title ?? 'LightLNMP') ?></title>
    <link href="https://cdn.jsdelivr.net/npm/@tabler/core@1.0.0-beta20/dist/css/tabler.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@latest/dist/tabler-icons.min.css" rel="stylesheet">
    <link href="/assets/css/app.css" rel="stylesheet">
</head>
<body class="<?= Auth::check() ? 'layout-fluid' : 'login-page' ?>">
<?php if (Auth::check()): ?>
<div class="page">
    <aside class="navbar navbar-vertical navbar-expand-lg navbar-dark bg-dark app-sidebar">
        <div class="container-fluid">
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#sidebar-menu">
                <span class="navbar-toggler-icon"></span>
            </button>
            <h1 class="navbar-brand navbar-brand-autodark">
                <a href="?r=dashboard" class="d-flex align-items-center gap-2 text-decoration-none">
                    <span>LightLNMP</span>
                </a>
            </h1>
            <div class="collapse navbar-collapse" id="sidebar-menu">
                <ul class="navbar-nav pt-lg-3">
                    <?php foreach ($navItems as $route => $item): ?>
                    <li class="nav-item <?= $currentRoute === $route ? 'active' : '' ?>">
                        <a class="nav-link" href="?r=<?= h($route) ?>">
                            <span class="nav-link-icon d-md-none d-lg-inline-block"><i class="ti <?= h($item['icon']) ?>"></i></span>
                            <span class="nav-link-title"><?= h($item['label']) ?></span>
                        </a>
                    </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>
    </aside>
    <div class="page-wrapper">
        <header class="navbar navbar-expand-md d-print-none app-topbar">
            <div class="container-xl">
                <div class="navbar-nav flex-row order-md-last ms-auto">
                    <div class="nav-item d-none d-md-flex me-3">
                        <span class="status-dot status-dot-animated bg-green"></span>
                        <span class="ms-2 text-secondary">Alpine / OpenRC</span>
                    </div>
                    <div class="nav-item dropdown">
                        <a href="#" class="nav-link d-flex lh-1 text-reset p-0" data-bs-toggle="dropdown">
                            <span class="avatar avatar-sm avatar-rounded bg-primary-lt"><?= h(strtoupper(substr(Auth::user()['username'] ?? 'A', 0, 1))) ?></span>
                            <div class="d-none d-xl-block ps-2">
                                <div><?= h(Auth::user()['username'] ?? 'admin') ?></div>
                                <div class="mt-1 small text-secondary">管理员</div>
                            </div>
                        </a>
                        <div class="dropdown-menu dropdown-menu-end dropdown-menu-arrow">
                            <a class="dropdown-item" href="?r=logout"><i class="ti ti-logout me-2"></i>退出登录</a>
                        </div>
                    </div>
                </div>
                <div>
                    <div class="text-secondary small">WebHosting 控制台</div>
                    <div class="fw-semibold"><?= h($title ?? 'LightLNMP') ?></div>
                </div>
            </div>
        </header>
        <main class="page-body">
            <div class="container-xl">
                <div class="page-header d-print-none">
                    <div class="row align-items-center">
                        <div class="col">
                            <div class="page-pretitle">LightLNMP</div>
                            <h2 class="page-title"><?= h($title ?? '控制台') ?></h2>
                        </div>
                    </div>
                </div>
                <?php if ($flash): ?>
                    <div class="alert alert-<?= h($flash['type']) ?> alert-dismissible fade show" role="alert">
                        <pre class="m-0 alert-pre"><?= h($flash['message']) ?></pre>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                <?php require $viewFile; ?>
            </div>
        </main>
    </div>
</div>
<?php else: ?>
<?php require $viewFile; ?>
<?php endif; ?>
<script src="https://cdn.jsdelivr.net/npm/@tabler/core@1.0.0-beta20/dist/js/tabler.min.js"></script>
<script src="/assets/js/app.js"></script>
</body>
</html>
