<?php
if (!defined('ADMIN_INIT')) {
  define('ADMIN_INIT', true);
}

$dataDir = __DIR__ . '/data';
if (!is_dir($dataDir)) {
  mkdir($dataDir, 0755, true);
}
$dbPath = $dataDir . '/iptrack.db';

try {
  $pdo = new PDO('sqlite:' . $dbPath, null, null, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
  ]);
} catch (PDOException $e) {
  $msg = $e->getMessage();
  if (stripos($msg, 'could not find driver') !== false) {
    $msg .= ' — увімкни розширення PHP pdo_sqlite (див. DEPLOY.md).';
  }
  if (stripos($msg, 'unable to open database file') !== false) {
    $msg .= ' — перевір права на папку admin/data (chown/chmod).';
  }
  die('DB error: ' . htmlspecialchars($msg));
}

$pdo->exec("
  CREATE TABLE IF NOT EXISTS auth (
    id INTEGER PRIMARY KEY CHECK (id = 1),
    password_hash TEXT NOT NULL
  )
");
$pdo->exec("
  CREATE TABLE IF NOT EXISTS projects (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL,
    slug TEXT NOT NULL UNIQUE,
    created_at TEXT NOT NULL DEFAULT (datetime('now'))
  )
");
$pdo->exec("
  CREATE TABLE IF NOT EXISTS monitored_urls (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL,
    url TEXT NOT NULL,
    created_at TEXT NOT NULL DEFAULT (datetime('now')),
    last_checked_at TEXT,
    status TEXT NOT NULL DEFAULT 'pending',
    last_error TEXT,
    notify_sent_at TEXT
  )
");
$pdo->exec("
  CREATE TABLE IF NOT EXISTS settings (
    key TEXT PRIMARY KEY,
    value TEXT NOT NULL
  )
");

function getPasswordHash(PDO $pdo) {
  $st = $pdo->query("SELECT password_hash FROM auth WHERE id = 1");
  $row = $st->fetch(PDO::FETCH_ASSOC);
  return $row ? $row['password_hash'] : null;
}

function setPasswordHash(PDO $pdo, string $hash) {
  $pdo->prepare("INSERT OR REPLACE INTO auth (id, password_hash) VALUES (1, ?)")->execute([$hash]);
}

function getAllProjects(PDO $pdo) {
  return $pdo->query("SELECT id, name, slug, created_at FROM projects ORDER BY created_at DESC")->fetchAll(PDO::FETCH_ASSOC);
}

function addProject(PDO $pdo, string $name, string $slug) {
  $pdo->prepare("INSERT INTO projects (name, slug) VALUES (?, ?)")->execute([$name, $slug]);
}

function deleteProject(PDO $pdo, string $slug) {
  $pdo->prepare("DELETE FROM projects WHERE slug = ?")->execute([$slug]);
}

function projectExists(PDO $pdo, string $slug) {
  $st = $pdo->prepare("SELECT 1 FROM projects WHERE slug = ?");
  $st->execute([$slug]);
  return (bool) $st->fetch();
}

function getMonitoredUrls(PDO $pdo) {
  return $pdo->query("SELECT id, name, url, last_checked_at, status, last_error FROM monitored_urls ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC);
}

function addMonitoredUrl(PDO $pdo, string $name, string $url) {
  $pdo->prepare("INSERT INTO monitored_urls (name, url) VALUES (?, ?)")->execute([$name, $url]);
}

function deleteMonitoredUrl(PDO $pdo, int $id) {
  $pdo->prepare("DELETE FROM monitored_urls WHERE id = ?")->execute([$id]);
}

function updateMonitoredResult(PDO $pdo, int $id, string $status, ?string $lastError, bool $notifySent = false) {
  $notifyAt = $notifySent ? date('Y-m-d H:i:s') : null;
  $pdo->prepare("UPDATE monitored_urls SET last_checked_at = datetime('now'), status = ?, last_error = ?, notify_sent_at = COALESCE(?, notify_sent_at) WHERE id = ?")
    ->execute([$status, $lastError ?? '', $notifyAt, $id]);
}

function getMonitoredUrlsToCheck(PDO $pdo) {
  return $pdo->query("SELECT id, name, url, status, notify_sent_at FROM monitored_urls ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
}

function getSetting(PDO $pdo, string $key): ?string {
  $st = $pdo->prepare("SELECT value FROM settings WHERE key = ?");
  $st->execute([$key]);
  $row = $st->fetch(PDO::FETCH_ASSOC);
  return $row ? $row['value'] : null;
}

function setSetting(PDO $pdo, string $key, string $value) {
  $pdo->prepare("INSERT OR REPLACE INTO settings (key, value) VALUES (?, ?)")->execute([$key, $value]);
}

/** Повертає [token, chat_id] для Telegram. Спочатку з admin/data/telegram.local.php (якщо є), інакше з БД. */
function getTelegramCredentials(PDO $pdo): array {
  $file = __DIR__ . '/data/telegram.local.php';
  if (is_file($file)) {
    $c = @include $file;
    if (is_array($c) && !empty($c['token']) && !empty($c['chat_id'])) {
      return [trim((string) $c['token']), trim((string) $c['chat_id'])];
    }
  }
  return [
    trim((string) (getSetting($pdo, 'telegram_bot_token') ?? '')),
    trim((string) (getSetting($pdo, 'telegram_chat_id') ?? '')),
  ];
}
