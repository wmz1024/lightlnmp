<?php

final class AuthController
{
    public function login(string $method): void
    {
        if ($method === 'POST') {
            Csrf::verify();
            if (Auth::attempt($_POST['username'] ?? '', $_POST['password'] ?? '')) {
                redirect('dashboard');
            }
            flash('用户名或密码错误', 'danger');
        }
        view('login', ['title' => '登录']);
    }

    public function logout(): void
    {
        Auth::logout();
        redirect('login');
    }
}
