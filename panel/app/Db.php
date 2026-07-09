<?php

final class Db
{
    private static ?PDO $conn = null;

    public static function conn(): PDO
    {
        if (self::$conn === null) {
            self::$conn = new PDO('sqlite:' . DB_PATH);
            self::$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            self::$conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        }
        return self::$conn;
    }

    public static function migrate(): void
    {
        self::conn()->exec(<<<SQL
CREATE TABLE IF NOT EXISTS users (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  username TEXT NOT NULL UNIQUE,
  password_hash TEXT NOT NULL,
  created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE TABLE IF NOT EXISTS settings (key TEXT PRIMARY KEY, value TEXT NOT NULL);
CREATE TABLE IF NOT EXISTS sites (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  name TEXT NOT NULL UNIQUE,
  root TEXT NOT NULL,
  enabled INTEGER NOT NULL DEFAULT 1,
  force_https INTEGER NOT NULL DEFAULT 0,
  rewrite_rule TEXT NOT NULL DEFAULT 'default',
  rewrite_mode TEXT NOT NULL DEFAULT 'preset',
  rewrite_custom TEXT,
  http_port INTEGER NOT NULL DEFAULT 80,
  https_port INTEGER NOT NULL DEFAULT 443,
  created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE TABLE IF NOT EXISTS domains (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  site_id INTEGER NOT NULL,
  domain TEXT NOT NULL,
  is_primary INTEGER NOT NULL DEFAULT 0,
  UNIQUE(site_id, domain)
);
CREATE TABLE IF NOT EXISTS certificates (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  site_id INTEGER NOT NULL,
  identifier TEXT NOT NULL,
  identifier_type TEXT NOT NULL,
  ca TEXT NOT NULL DEFAULT 'letsencrypt',
  status TEXT NOT NULL DEFAULT 'unknown',
  expires_at TEXT,
  updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE(site_id, identifier)
);
CREATE TABLE IF NOT EXISTS audit_logs (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  actor TEXT NOT NULL,
  action TEXT NOT NULL,
  target TEXT NOT NULL,
  result TEXT NOT NULL,
  detail TEXT,
  created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE TABLE IF NOT EXISTS login_attempts (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  username TEXT NOT NULL,
  ip TEXT NOT NULL,
  failed_count INTEGER NOT NULL DEFAULT 0,
  locked_until INTEGER NOT NULL DEFAULT 0,
  updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE(username, ip)
);
SQL);
        self::addColumn('sites', 'rewrite_rule', "TEXT NOT NULL DEFAULT 'default'");
        self::addColumn('sites', 'rewrite_mode', "TEXT NOT NULL DEFAULT 'preset'");
        self::addColumn('sites', 'rewrite_custom', 'TEXT');
        self::addColumn('sites', 'http_port', 'INTEGER NOT NULL DEFAULT 80');
        self::addColumn('sites', 'https_port', 'INTEGER NOT NULL DEFAULT 443');
    }

    private static function addColumn(string $table, string $column, string $definition): void
    {
        $stmt = self::conn()->query('PRAGMA table_info(' . $table . ')');
        foreach ($stmt->fetchAll() as $row) {
            if (($row['name'] ?? '') === $column) {
                return;
            }
        }
        self::conn()->exec('ALTER TABLE ' . $table . ' ADD COLUMN ' . $column . ' ' . $definition);
    }

    public static function audit(string $action, string $target, string $result, string $detail = ''): void
    {
        $stmt = self::conn()->prepare('INSERT INTO audit_logs(actor, action, target, result, detail) VALUES(:actor, :action, :target, :result, :detail)');
        $stmt->execute([
            'actor' => Auth::user()['username'] ?? 'system',
            'action' => $action,
            'target' => $target,
            'result' => $result,
            'detail' => $detail,
        ]);
    }
}
