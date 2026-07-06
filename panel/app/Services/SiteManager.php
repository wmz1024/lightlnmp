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
        if (!isset($this->rewriteRules[$rule])) {
            return ['ok' => false, 'output' => 'Invalid rewrite rule'];
        }
        $site = $this->find($id);
        if (!$site) {
            return ['ok' => false, 'output' => 'Site not found'];
        }
        $domain = $this->primaryDomain($id) ?: $site['name'];
        $run = SystemCommand::run(['site-rewrite', $site['name'], $domain, $rule]);
        if ($run['ok']) {
            Db::conn()->prepare('UPDATE sites SET rewrite_rule = :rule WHERE id = :id')->execute(['rule' => $rule, 'id' => $id]);
            Db::audit('site.rewrite', $site['name'], 'ok', $rule);
        }
        return $run;
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
