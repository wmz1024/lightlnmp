<?php

final class SiteManager
{
    private array $rewriteRules = [
        'default' => '默认 PHP 入口',
        'wordpress' => 'WordPress',
        'thinkphp' => 'ThinkPHP',
        'laravel' => 'Laravel',
        'static' => '静态站点',
    ];

    public function all(): array
    {
        return Db::conn()->query('SELECT * FROM sites ORDER BY id DESC')->fetchAll();
    }

    public function find(int $id): ?array
    {
        $stmt = Db::conn()->prepare('SELECT * FROM sites WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $site = $stmt->fetch();
        return $site ?: null;
    }

    public function create(string $name, string $domain): array
    {
        if (!Security::siteName($name) || !Security::domainOrPublicIp($domain)) {
            return ['ok' => false, 'output' => 'Invalid site name or domain/IP'];
        }
        $root = rtrim(setting('web_root', '/www/wwwroot'), '/') . '/' . $name;
        $run = SystemCommand::run(['site-create', $name, $domain]);
        if ($run['ok']) {
            $db = Db::conn();
            $stmt = $db->prepare('INSERT OR IGNORE INTO sites(name, root, enabled) VALUES(:name, :root, 1)');
            $stmt->execute(['name' => $name, 'root' => $root]);
            $siteId = (int)$db->lastInsertId();
            if ($siteId === 0) {
                $siteId = (int)$db->query('SELECT id FROM sites WHERE name = ' . $db->quote($name))->fetchColumn();
            }
            $stmt = $db->prepare('INSERT OR IGNORE INTO domains(site_id, domain, is_primary) VALUES(:site_id, :domain, 1)');
            $stmt->execute(['site_id' => $siteId, 'domain' => $domain]);
            Db::audit('site.create', $name, 'ok', $domain);
        }
        return $run;
    }

    public function rewriteRules(): array
    {
        return $this->rewriteRules;
    }

    public function primaryDomain(int $siteId): string
    {
        $stmt = Db::conn()->prepare('SELECT domain FROM domains WHERE site_id = :id ORDER BY is_primary DESC, id ASC LIMIT 1');
        $stmt->execute(['id' => $siteId]);
        return (string)($stmt->fetchColumn() ?: '');
    }

    public function setRewriteRule(int $id, string $rule): array
    {
        $site = $this->find($id);
        if (!$site) {
            return ['ok' => false, 'output' => 'Site not found'];
        }
        return $this->updateConfig(
            $id,
            (int)($site['http_port'] ?? 80),
            (int)($site['https_port'] ?? 443),
            'preset',
            $rule,
            (string)($site['rewrite_custom'] ?? '')
        );
    }

    public function updateConfig(int $id, int|string $httpPort, int|string $httpsPort, string $rewriteMode, string $rewriteRule, string $customRewrite): array
    {
        $site = $this->find($id);
        if (!$site) {
            return ['ok' => false, 'output' => 'Site not found'];
        }
        if (!Security::port($httpPort) || !Security::port($httpsPort)) {
            return ['ok' => false, 'output' => 'Invalid HTTP or HTTPS port'];
        }
        if (!Security::rewriteMode($rewriteMode)) {
            return ['ok' => false, 'output' => 'Invalid rewrite mode'];
        }
        if ($rewriteMode === 'preset' && !isset($this->rewriteRules[$rewriteRule])) {
            return ['ok' => false, 'output' => 'Invalid rewrite rule'];
        }
        if ($rewriteMode === 'custom' && !Security::customRewrite($customRewrite)) {
            return ['ok' => false, 'output' => 'Invalid custom rewrite config'];
        }

        $domain = $this->primaryDomain($id) ?: $site['name'];
        $effectiveRule = $rewriteMode === 'custom' ? 'custom' : $rewriteRule;
        $customFile = '';
        if ($rewriteMode === 'custom') {
            $customFile = $this->writeCustomRewriteTemp($site['name'], $customRewrite);
        }

        $run = SystemCommand::run(['site-config', $site['name'], $domain, $effectiveRule, (string)$httpPort, (string)$httpsPort, $customFile]);
        if ($run['ok']) {
            foreach ($this->issuedCertificates($id) as $cert) {
                $sslRun = SystemCommand::run(['ssl', 'config', $site['name'], $cert['identifier'], (string)($site['force_https'] ?? 0), $effectiveRule, (string)$httpsPort, (string)$httpPort, $customFile]);
                if (!$sslRun['ok']) {
                    $run = $sslRun;
                    break;
                }
            }
        }
        if ($customFile !== '') {
            @unlink($customFile);
        }
        if ($run['ok']) {
            Db::conn()->prepare('UPDATE sites SET http_port = :http_port, https_port = :https_port, rewrite_mode = :rewrite_mode, rewrite_rule = :rewrite_rule, rewrite_custom = :rewrite_custom WHERE id = :id')->execute([
                'http_port' => (int)$httpPort,
                'https_port' => (int)$httpsPort,
                'rewrite_mode' => $rewriteMode,
                'rewrite_rule' => $rewriteRule,
                'rewrite_custom' => $customRewrite,
                'id' => $id,
            ]);
            Db::audit('site.config', $site['name'], 'ok', $rewriteMode . ':' . $effectiveRule);
        }
        return $run;
    }

    private function writeCustomRewriteTemp(string $siteName, string $content): string
    {
        $dir = STORAGE_PATH . '/rewrite';
        if (!is_dir($dir)) {
            mkdir($dir, 0750, true);
        }
        $file = $dir . '/' . preg_replace('/[^A-Za-z0-9._-]/', '_', $siteName) . '-' . bin2hex(random_bytes(4)) . '.conf';
        file_put_contents($file, trim($content) . "\n", LOCK_EX);
        return $file;
    }

    private function issuedCertificates(int $siteId): array
    {
        $stmt = Db::conn()->prepare("SELECT * FROM certificates WHERE site_id = :site_id AND status = 'issued'");
        $stmt->execute(['site_id' => $siteId]);
        return $stmt->fetchAll();
    }

    public function logs(int $id, string $type): array
    {
        $site = $this->find($id);
        if (!$site) {
            return ['ok' => false, 'output' => 'Site not found'];
        }
        return SystemCommand::run(['site-log', $site['name'], $type]);
    }

    public function delete(int $id): array
    {
        $site = $this->find($id);
        if (!$site) {
            return ['ok' => false, 'output' => 'Site not found'];
        }
        $run = SystemCommand::run(['site-delete', $site['name']]);
        if ($run['ok']) {
            $db = Db::conn();
            $db->prepare('DELETE FROM domains WHERE site_id = :id')->execute(['id' => $id]);
            $db->prepare('DELETE FROM certificates WHERE site_id = :id')->execute(['id' => $id]);
            $db->prepare('DELETE FROM sites WHERE id = :id')->execute(['id' => $id]);
            Db::audit('site.delete', $site['name'], 'ok');
        }
        return $run;
    }

    public function setEnabled(int $id, bool $enabled): array
    {
        $site = $this->find($id);
        if (!$site) {
            return ['ok' => false, 'output' => 'Site not found'];
        }
        $run = SystemCommand::run([$enabled ? 'site-enable' : 'site-disable', $site['name']]);
        if ($run['ok']) {
            Db::conn()->prepare('UPDATE sites SET enabled = :enabled WHERE id = :id')->execute(['enabled' => $enabled ? 1 : 0, 'id' => $id]);
            Db::audit($enabled ? 'site.enable' : 'site.disable', $site['name'], 'ok');
        }
        return $run;
    }

    public function batch(array $ids, string $action): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
        if (!$ids) {
            return ['ok' => false, 'output' => 'No sites selected'];
        }

        $errors = [];
        foreach ($ids as $id) {
            $run = match ($action) {
                'enable' => $this->setEnabled($id, true),
                'disable' => $this->setEnabled($id, false),
                'delete' => $this->delete($id),
                default => ['ok' => false, 'output' => 'Unknown action'],
            };
            if (!$run['ok']) {
                $errors[] = '#' . $id . ': ' . $run['output'];
            }
        }

        return $errors ? ['ok' => false, 'output' => implode("\n", $errors)] : ['ok' => true, 'output' => 'ok'];
    }
}
