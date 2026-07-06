<?php

final class SiteController
{
    public function index(string $method): void
    {
        if ($method === 'POST') {
            Csrf::verify();
            $run = (new SiteManager())->create(trim($_POST['name'] ?? ''), trim($_POST['domain'] ?? ''));
            flash($run['ok'] ? '站点已创建' : $run['output'], $run['ok'] ? 'success' : 'danger');
            redirect('sites');
        }
        view('sites', ['title' => '站点管理', 'sites' => (new SiteManager())->all()]);
    }

    public function action(string $method): void
    {
        if ($method !== 'POST') {
            redirect('sites');
        }
        Csrf::verify();
        $id = (int)($_POST['id'] ?? 0);
        $action = $_POST['action'] ?? '';
        $manager = new SiteManager();
        $run = match ($action) {
            'enable' => $manager->setEnabled($id, true),
            'disable' => $manager->setEnabled($id, false),
            'delete' => $manager->delete($id),
            default => ['ok' => false, 'output' => 'Unknown action'],
        };
        flash($run['ok'] ? '操作完成' : $run['output'], $run['ok'] ? 'success' : 'danger');
        redirect('sites');
    }
}
