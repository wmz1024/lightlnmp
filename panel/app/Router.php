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
            'sites' => (new SiteController())->index($method),
            'sites/action' => (new SiteController())->action($method),
            'files' => (new FileController())->index($method),
            'files/download' => (new FileController())->download(),
            'databases' => (new DatabaseController())->index($method),
            'ssl' => (new SslController())->index($method),
            'services' => (new ServiceController())->index($method),
            default => (new DashboardController())->index(),
        };
    }
}
