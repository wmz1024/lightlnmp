<?php

final class AuthController
{
    public function login(string $method): void
    {
        if ($method === 'POST') {
            Csrf::verify();
            $username = trim($_POST['username'] ?? '');
            $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
            if (Auth::isLocked($username, $ip)) {
                flash('登录失败次数过多，请 10 分钟后再试', 'danger');
                view('login', ['title' => '登录']);
                return;
            }
            if (Auth::attempt($username, $_POST['password'] ?? '')) {
                Auth::clearFailures($username, $ip);
                redirect('dashboard');
            }
            Auth::recordFailure($username, $ip);
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
