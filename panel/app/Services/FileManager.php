<?php

final class FileManager
{
    private array $editable = ['php', 'html', 'htm', 'css', 'js', 'json', 'txt', 'md', 'xml', 'ini', 'conf', 'env'];
    private array $archives = ['zip', 'tar', 'tar.gz', 'tgz'];

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

    public function extractArchive(array $site, string $archivePath, string $destinationPath, bool $overwrite, callable $log): void
    {
        if ($archivePath === '') {
            throw new RuntimeException('Missing archive path');
        }

        $archive = $this->resolve($site, $archivePath, true);
        if (!is_file($archive) || !$this->isArchive($archive)) {
            throw new RuntimeException('Unsupported archive file');
        }

        if ($destinationPath === '') {
            $destinationPath = $this->defaultExtractPath($archivePath);
        }

        $destination = $this->resolve($site, $destinationPath, false);
        if (!is_dir($destination) && !mkdir($destination, 0750, true)) {
            throw new RuntimeException('Unable to create destination directory');
        }

        $log('归档文件：' . $archivePath);
        $log('目标目录：/' . trim($destinationPath, '/'));
        $log($overwrite ? '覆盖模式：开启' : '覆盖模式：关闭，已有文件将跳过');

        $type = $this->archiveType($archive);
        if ($type === 'zip') {
            $this->extractZip($site, $archive, $destination, $overwrite, $log);
        } else {
            $this->extractTar($site, $archive, $destination, $overwrite, $log);
        }

        Db::audit('file.extract', $site['name'] . ':' . $archivePath, 'ok', $destinationPath);
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

    private function isArchive(string $file): bool
    {
        return in_array($this->archiveType($file), $this->archives, true);
    }

    private function archiveType(string $file): string
    {
        $name = strtolower(basename($file));
        if (str_ends_with($name, '.tar.gz')) {
            return 'tar.gz';
        }
        if (str_ends_with($name, '.tgz')) {
            return 'tgz';
        }
        return strtolower(pathinfo($name, PATHINFO_EXTENSION));
    }

    private function defaultExtractPath(string $archivePath): string
    {
        $dir = dirname($archivePath);
        $dir = $dir === '.' ? '' : trim($dir, '/');
        $name = preg_replace('/\.(tar\.gz|tgz|zip|tar)$/i', '', basename($archivePath));
        return trim(($dir === '' ? '' : $dir . '/') . ($name ?: 'archive'), '/');
    }

    private function extractZip(array $site, string $archive, string $destination, bool $overwrite, callable $log): void
    {
        if (!class_exists('ZipArchive')) {
            throw new RuntimeException('PHP ZipArchive extension is not installed');
        }

        $zip = new ZipArchive();
        if ($zip->open($archive) !== true) {
            throw new RuntimeException('Unable to open zip archive');
        }

        try {
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $entry = $zip->getNameIndex($i);
                if ($entry === false) {
                    continue;
                }
                $relative = $this->normalizeArchiveEntry($entry);
                if ($relative === '') {
                    continue;
                }

                if (str_ends_with($entry, '/')) {
                    $this->ensureExtractDirectory($site, $destination, $relative);
                    $log('目录：' . $relative);
                    continue;
                }

                $target = $this->extractTarget($site, $destination, $relative);
                if (file_exists($target) && !$overwrite) {
                    $log('跳过：' . $relative);
                    continue;
                }

                $this->ensureParentDirectory($site, $target);
                $in = $zip->getStream($entry);
                if (!$in) {
                    throw new RuntimeException('Unable to read archive entry: ' . $relative);
                }
                $this->writeStream($in, $target);
                $log('解压：' . $relative);
            }
        } finally {
            $zip->close();
        }
    }

    private function extractTar(array $site, string $archive, string $destination, bool $overwrite, callable $log): void
    {
        if (!class_exists('PharData')) {
            throw new RuntimeException('PHP PharData support is not available');
        }

        try {
            $phar = new PharData($archive);
        } catch (Throwable $e) {
            throw new RuntimeException('Unable to open tar archive: ' . $e->getMessage());
        }

        $prefix = 'phar://' . str_replace('\\', '/', $archive) . '/';
        $iterator = new RecursiveIteratorIterator($phar, RecursiveIteratorIterator::SELF_FIRST);
        foreach ($iterator as $entry) {
            $path = str_replace('\\', '/', $entry->getPathName());
            if (!str_starts_with($path, $prefix)) {
                throw new RuntimeException('Unexpected archive entry path');
            }
            $relative = $this->normalizeArchiveEntry(substr($path, strlen($prefix)));
            if ($relative === '') {
                continue;
            }

            if (method_exists($entry, 'isLink') && $entry->isLink()) {
                $log('跳过链接：' . $relative);
                continue;
            }

            if ($entry->isDir()) {
                $this->ensureExtractDirectory($site, $destination, $relative);
                $log('目录：' . $relative);
                continue;
            }

            $target = $this->extractTarget($site, $destination, $relative);
            if (file_exists($target) && !$overwrite) {
                $log('跳过：' . $relative);
                continue;
            }

            $this->ensureParentDirectory($site, $target);
            $in = fopen($entry->getPathName(), 'rb');
            if (!$in) {
                throw new RuntimeException('Unable to read archive entry: ' . $relative);
            }
            $this->writeStream($in, $target);
            $log('解压：' . $relative);
        }
    }

    private function normalizeArchiveEntry(string $entry): string
    {
        $entry = str_replace('\\', '/', $entry);
        if (str_starts_with($entry, '/') || preg_match('#^[A-Za-z]:#', $entry)) {
            throw new RuntimeException('Unsafe archive entry: ' . $entry);
        }
        $entry = trim($entry, '/');
        if ($entry === '') {
            return '';
        }
        if (preg_match('#(^|/)\.\.(/|$)#', $entry)) {
            throw new RuntimeException('Unsafe archive entry: ' . $entry);
        }
        return $entry;
    }

    private function extractTarget(array $site, string $destination, string $relative): string
    {
        $target = $destination . '/' . $relative;
        $this->assertInsideBase($site, $target);
        return $target;
    }

    private function ensureExtractDirectory(array $site, string $destination, string $relative): void
    {
        $dir = $this->extractTarget($site, $destination, $relative);
        if (!is_dir($dir) && !mkdir($dir, 0750, true)) {
            throw new RuntimeException('Unable to create directory: ' . $relative);
        }
    }

    private function ensureParentDirectory(array $site, string $target): void
    {
        $dir = dirname($target);
        $this->assertInsideBase($site, $dir);
        if (!is_dir($dir) && !mkdir($dir, 0750, true)) {
            throw new RuntimeException('Unable to create directory: ' . basename($dir));
        }
    }

    private function writeStream($input, string $target): void
    {
        $output = fopen($target, 'wb');
        if (!$output) {
            if (is_resource($input)) {
                fclose($input);
            }
            throw new RuntimeException('Unable to write file: ' . basename($target));
        }
        stream_copy_to_stream($input, $output);
        fclose($input);
        fclose($output);
        @chmod($target, 0640);
    }

    private function assertInsideBase(array $site, string $target): void
    {
        $base = realpath($site['root']);
        if (!$base) {
            throw new RuntimeException('Site root does not exist');
        }
        $base = rtrim(str_replace('\\', '/', $base), '/') . '/';
        $target = str_replace('\\', '/', $target);
        $parts = [];
        foreach (explode('/', $target) as $part) {
            if ($part === '' || $part === '.') {
                continue;
            }
            if ($part === '..') {
                array_pop($parts);
                continue;
            }
            $parts[] = $part;
        }
        $normalized = '/' . implode('/', $parts);
        if (!str_starts_with(rtrim($normalized, '/') . '/', $base)) {
            throw new RuntimeException('Path is outside site root');
        }
    }

    private function validName(string $name): bool
    {
        return $name !== '' && basename($name) === $name && !str_contains($name, '..');
    }
}
