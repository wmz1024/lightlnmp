<?php

final class DashboardController
{
    public function index(): void
    {
        $siteCount = (int)Db::conn()->query('SELECT COUNT(*) FROM sites')->fetchColumn();
        $certCount = (int)Db::conn()->query('SELECT COUNT(*) FROM certificates WHERE status = "issued"')->fetchColumn();
        $logs = Db::conn()->query('SELECT * FROM audit_logs ORDER BY id DESC LIMIT 10')->fetchAll();
        view('dashboard', [
            'title' => '仪表盘',
            'siteCount' => $siteCount,
            'certCount' => $certCount,
            'statuses' => (new ServiceManager())->statuses(),
            'logs' => $logs,
        ]);
    }
}
