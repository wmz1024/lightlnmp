<?php

final class SiteController
{
    public function index(string $method): void
    {
        if ($method === 'POST') {
            Csrf::verify();
            $manager = new SiteManager();
            $action = $_POST['action'] ?? 'create';
            $run = $action === 'batch'
                ? $manager->batch($_POST['ids'] ?? [], $_POST['batch_action'] ?? '')
                : $manager->create(trim($_POST['name'] ?? ''), trim($_POST['domain'] ?? ''));
            flash($run['ok'] ? ($action === 'batch' ? '批量操作完成' : '站点已创建') : $run['output'], $run['ok'] ? 'success' : 'danger');
            redirect('sites');
        }
        $pager = paginate((new SiteManager())->all(), 'sites_page');
        view('sites', ['title' => '站点管理', 'sites' => $pager['items'], 'sitesPager' => $pager]);
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
