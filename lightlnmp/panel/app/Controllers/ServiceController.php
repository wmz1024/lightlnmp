<?php

final class ServiceController
{
    public function index(string $method): void
    {
        $manager = new ServiceManager();
        if ($method === 'POST') {
            Csrf::verify();
            $run = $manager->action($_POST['service'] ?? '', $_POST['action'] ?? 'status');
            flash($run['ok'] ? '服务操作完成' : $run['output'], $run['ok'] ? 'success' : 'danger');
            redirect('services');
        }
        view('services', ['title' => '服务管理', 'statuses' => $manager->statuses()]);
    }
}
