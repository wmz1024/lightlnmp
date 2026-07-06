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
        $manager = new SiteManager();
        $pager = paginate($manager->all(), 'sites_page');
        view('sites', ['title' => '站点管理', 'sites' => $pager['items'], 'sitesPager' => $pager, 'rewriteRules' => $manager->rewriteRules()]);
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
            'rewrite' => $manager->setRewriteRule($id, $_POST['rewrite_rule'] ?? 'default'),
            default => ['ok' => false, 'output' => 'Unknown action'],
        };
        flash($run['ok'] ? '操作完成' : $run['output'], $run['ok'] ? 'success' : 'danger');
        redirect('sites');
    }

    public function logs(): void
    {
        $manager = new SiteManager();
        $id = (int)($_GET['id'] ?? 0);
        $type = $_GET['type'] ?? 'access';
        $site = $manager->find($id);
        if (!$site) {
            flash('站点不存在', 'danger');
            redirect('sites');
        }
        $run = $manager->logs($id, $type);
        view('site_logs', ['title' => '站点日志', 'site' => $site, 'type' => $type, 'logOutput' => $run['output'], 'ok' => $run['ok']]);
    }
}
