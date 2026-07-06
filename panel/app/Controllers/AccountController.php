<?php

final class AccountController
{
    public function index(string $method): void
    {
        if ($method === 'POST') {
            Csrf::verify();
            $run = Auth::changePassword($_POST['current_password'] ?? '', $_POST['new_password'] ?? '');
            flash($run['ok'] ? '密码已修改' : $run['output'], $run['ok'] ? 'success' : 'danger');
            redirect('account');
        }

        view('account', ['title' => '账户设置']);
    }
}
