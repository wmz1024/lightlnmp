<?php

final class FileController
{
    public function index(string $method): void
    {
        $site = $this->site();
        $path = trim($_GET['path'] ?? '', '/');
        $manager = new FileManager();

        if ($method === 'POST') {
            Csrf::verify();
            try {
                $action = $_POST['action'] ?? '';
                if ($action === 'upload') {
                    $manager->upload($site, $path, $_FILES['upload'] ?? []);
                } elseif ($action === 'create') {
                    $manager->create($site, $path, trim($_POST['name'] ?? ''), $_POST['type'] ?? 'file');
                } elseif ($action === 'delete') {
                    $manager->delete($site, trim($_POST['target'] ?? ''));
                } elseif ($action === 'rename') {
                    $manager->rename($site, trim($_POST['target'] ?? ''), trim($_POST['new_name'] ?? ''));
                } elseif ($action === 'save') {
                    $manager->save($site, trim($_POST['target'] ?? ''), $_POST['content'] ?? '');
                }
                flash('文件操作完成', 'success');
            } catch (Throwable $e) {
                flash($e->getMessage(), 'danger');
            }
            redirect('files&site_id=' . (int)$site['id'] . '&path=' . rawurlencode($path));
        }

        $editPath = trim($_GET['edit'] ?? '', '/');
        $editContent = null;
        if ($editPath !== '') {
            try {
                $editContent = $manager->read($site, $editPath);
            } catch (Throwable $e) {
                flash($e->getMessage(), 'danger');
            }
        }

        view('files', [
            'title' => '文件管理',
            'sites' => (new SiteManager())->all(),
            'site' => $site,
            'path' => $path,
            'items' => $manager->items($site, $path),
            'editPath' => $editPath,
            'editContent' => $editContent,
        ]);
    }

    public function download(): void
    {
        Auth::requireLogin();
        $site = $this->site();
        $file = (new FileManager())->resolve($site, trim($_GET['path'] ?? '', '/'), true);
        if (!is_file($file)) {
            http_response_code(404);
            exit('Not found');
        }
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . basename($file) . '"');
        readfile($file);
        exit;
    }

    private function site(): array
    {
        $manager = new SiteManager();
        $sites = $manager->all();
        if (!$sites) {
            flash('请先创建站点', 'warning');
            redirect('sites');
        }
        $id = (int)($_GET['site_id'] ?? $sites[0]['id']);
        return $manager->find($id) ?: $sites[0];
    }
}
