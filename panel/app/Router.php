<?php

final class Router
{
    public function dispatch(string $method, string $route): void
    {
        if ($route === 'login') {
            (new AuthController())->login($method);
            return;
        }
        if ($route === 'logout') {
            (new AuthController())->logout();
            return;
        }

        Auth::requireLogin();

        match ($route) {
            'dashboard' => (new DashboardController())->index(),
            'account' => (new AccountController())->index($method),
            'sites' => (new SiteController())->index($method),
            'sites/action' => (new SiteController())->action($method),
            'sites/logs' => (new SiteController())->logs(),
            'files' => (new FileController())->index($method),
            'files/download' => (new FileController())->download(),
            'files/extract' => (new FileController())->extract($method),
            'databases' => (new DatabaseController())->index($method),
            'databases/export' => (new DatabaseController())->export(),
            'ssl' => (new SslController())->index($method),
            'services' => (new ServiceController())->index($method),
            default => (new DashboardController())->index(),
        };
    }
}
