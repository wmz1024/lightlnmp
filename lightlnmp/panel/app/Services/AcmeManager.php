<?php

final class AcmeManager
{
    public function certificates(): array
    {
        return Db::conn()->query('SELECT c.*, s.name AS site_name FROM certificates c JOIN sites s ON s.id = c.site_id ORDER BY c.updated_at DESC')->fetchAll();
    }

    public function issue(int $siteId, string $identifier, bool $forceHttps): array
    {
        $siteManager = new SiteManager();
        $site = $siteManager->find($siteId);
        if (!$site) {
            return ['ok' => false, 'output' => 'Site not found'];
        }
        if (!Security::domainOrPublicIp($identifier)) {
            return ['ok' => false, 'output' => 'Only public domains or public IP addresses are allowed'];
        }
        $type = Security::ip($identifier) ? 'ip' : 'domain';
        $run = SystemCommand::run(['ssl', 'issue', $site['name'], $identifier, $type, $forceHttps ? '1' : '0']);
        $stmt = Db::conn()->prepare('INSERT INTO certificates(site_id, identifier, identifier_type, status, updated_at) VALUES(:site_id, :identifier, :type, :status, CURRENT_TIMESTAMP) ON CONFLICT(site_id, identifier) DO UPDATE SET status = excluded.status, updated_at = CURRENT_TIMESTAMP');
        $stmt->execute(['site_id' => $siteId, 'identifier' => $identifier, 'type' => $type, 'status' => $run['ok'] ? 'issued' : 'failed']);
        if ($forceHttps) {
            Db::conn()->prepare('UPDATE sites SET force_https = 1 WHERE id = :id')->execute(['id' => $siteId]);
        }
        Db::audit('ssl.issue', $site['name'] . ':' . $identifier, $run['ok'] ? 'ok' : 'fail', $run['output']);
        return $run;
    }

    public function renewAll(): array
    {
        $run = SystemCommand::run(['ssl', 'renew-all']);
        Db::audit('ssl.renew-all', 'all', $run['ok'] ? 'ok' : 'fail', $run['output']);
        return $run;
    }
}
