<?php

final class FileManager
{
    private array $editable = ['php', 'html', 'htm', 'css', 'js', 'json', 'txt', 'md', 'xml', 'ini', 'conf', 'env'];

    public function items(array $site, string $path): array
    {
        $dir = $this->resolve($site, $path, true);
        if (!is_dir($dir)) {
            throw new RuntimeException('Not a directory');
        }
        $items = [];
        foreach (scandir($dir) ?: [] as $name) {
            if ($name === '.' || $name === '..') {
                continue;
            }
            $full = $dir . DIRECTORY_SEPARATOR . $name;
            $items[] = [
                'name' => $name,
                'type' => is_dir($full) ? 'dir' : 'file',
                'size' => is_file($full) ? filesize($full) : 0,
                'mtime' => date('Y-m-d H:i:s', filemtime($full) ?: time()),
            ];
        }
        usort($items, fn($a, $b) => [$a['type'] !== 'dir', $a['name']] <=> [$b['type'] !== 'dir', $b['name']]);
        return $items;
    }

    public function read(array $site, string $path): string
    {
        if ($path === '') {
            throw new RuntimeException('Missing file path');
        }
        $file = $this->resolve($site, $path, true);
        if (!is_file($file) || !$this->isEditable($file) || filesize($file) > 2 * 1024 * 1024) {
            throw new RuntimeException('File cannot be edited');
        }
        return file_get_contents($file) ?: '';
    }

    public function save(array $site, string $path, string $content): void
    {
        if ($path === '') {
            throw new RuntimeException('Missing file path');
        }
        $file = $this->resolve($site, $path, true);
        if (!is_file($file) || !$this->isEditable($file)) {
            throw new RuntimeException('File cannot be edited');
        }
        $backupDir = rtrim($site['root'], '/') . '/.lightlnmp-backup';
        if (!is_dir($backupDir)) {
            mkdir($backupDir, 0750, true);
        }
        copy($file, $backupDir . '/' . basename($file) . '.' . date('YmdHis'));
        file_put_contents($file, $content, LOCK_EX);
        Db::audit('file.save', $site['name'] . ':' . $path, 'ok');
    }

    public function upload(array $site, string $path, array $file): void
    {
        $dir = $this->resolve($site, $path, true);
        if (!is_dir($dir) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            throw new RuntimeException('Upload failed');
        }
        $name = basename($file['name']);
        if ($name === '' || str_contains($name, '..')) {
            throw new RuntimeException('Invalid file name');
        }
        move_uploaded_file($file['tmp_name'], $dir . '/' . $name);
        Db::audit('file.upload', $site['name'] . ':' . $path . '/' . $name, 'ok');
    }

    public function create(array $site, string $path, string $name, string $type): void
    {
        if (!$this->validName($name)) {
            throw new RuntimeException('Invalid name');
        }
        $dir = $this->resolve($site, $path, true);
        $target = $this->resolve($site, trim($path . '/' . basename($name), '/'), false);
        if ($type === 'dir') {
            mkdir($target, 0750);
        } else {
            file_put_contents($target, '');
        }
        Db::audit('file.create', $site['name'] . ':' . $target, 'ok');
    }

    public function delete(array $site, string $path): void
    {
        if ($path === '') {
            throw new RuntimeException('Refusing to delete site root');
        }
        $target = $this->resolve($site, $path, true);
        if (is_dir($target)) {
            rmdir($target);
        } else {
            unlink($target);
        }
        Db::audit('file.delete', $site['name'] . ':' . $path, 'ok');
    }

    public function rename(array $site, string $path, string $newName): void
    {
        if ($path === '' || !$this->validName($newName)) {
            throw new RuntimeException('Invalid rename target');
        }
        $target = $this->resolve($site, $path, true);
        $dest = dirname($target) . '/' . basename($newName);
        $this->assertInside($site, $dest, false);
        rename($target, $dest);
        Db::audit('file.rename', $site['name'] . ':' . $path, 'ok', $newName);
    }

    public function resolve(array $site, string $path, bool $mustExist): string
    {
        $base = realpath($site['root']);
        if (!$base) {
            throw new RuntimeException('Site root does not exist');
        }
        $path = trim(str_replace('\\', '/', $path), '/');
        $target = $base . ($path === '' ? '' : '/' . $path);
        return $this->assertInside($site, $target, $mustExist);
    }

    private function assertInside(array $site, string $target, bool $mustExist): string
    {
        $base = realpath($site['root']);
        $resolved = $mustExist ? realpath($target) : realpath(dirname($target));
        if (!$base || !$resolved || !str_starts_with($resolved, $base)) {
            throw new RuntimeException('Path is outside site root');
        }
        return $mustExist ? $resolved : $resolved . '/' . basename($target);
    }

    private function isEditable(string $file): bool
    {
        $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
        return in_array($ext, $this->editable, true);
    }

    private function validName(string $name): bool
    {
        return $name !== '' && basename($name) === $name && !str_contains($name, '..');
    }
}
