<?php

final class SslController
{
    public function index(string $method): void
    {
        $manager = new AcmeManager();
        if ($method === 'POST') {
            Csrf::verify();
            $action = $_POST['action'] ?? '';
            if ($action === 'issue') {
                $run = $manager->issue((int)($_POST['site_id'] ?? 0), trim($_POST['identifier'] ?? ''), isset($_POST['force_https']));
            } elseif ($action === 'renew-all') {
                $run = $manager->renewAll();
            } elseif ($action === 'renew-selected') {
                $run = $manager->renewSelected($_POST['ids'] ?? []);
            } else {
                $run = ['ok' => false, 'output' => 'Unknown action'];
            }
            flash($run['ok'] ? 'SSL 操作完成' : $run['output'], $run['ok'] ? 'success' : 'danger');
            redirect('ssl');
        }
        $certPager = paginate($manager->certificates(), 'cert_page');
        view('ssl', [
            'title' => 'SSL 证书',
            'sites' => (new SiteManager())->all(),
            'certificates' => $certPager['items'],
            'certPager' => $certPager,
        ]);
    }
}
