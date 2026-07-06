<?php

final class SiteManager
{
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
