<?php
/**
 * Shared SQLite connector for tracking events (clicks/visits).
 * Uses the same DB file as admin: public_html/admin/data/iptrack.db
 */
date_default_timezone_set('Europe/Kyiv');

$dbPath = __DIR__ . '/../admin/data/iptrack.db';
$dbDir = dirname($dbPath);
if (!is_dir($dbDir)) {
  mkdir($dbDir, 0755, true);
}

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

// Main events table.
$pdo->exec("
  CREATE TABLE IF NOT EXISTS events (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    project_slug TEXT NOT NULL,
    created_at TEXT NOT NULL,
    ip TEXT NOT NULL,
    tag TEXT,
    text TEXT,
    href TEXT,
    element_id TEXT,
    classes TEXT,
    page TEXT,
    type TEXT NOT NULL,
    referrer TEXT,
    fingerprint TEXT NOT NULL UNIQUE
  )
");

$pdo->exec("CREATE INDEX IF NOT EXISTS idx_events_project_created_at ON events(project_slug, created_at)");
$pdo->exec("CREATE INDEX IF NOT EXISTS idx_events_type_created_at ON events(type, created_at)");

function iptrack_insert_event(PDO $pdo, array $payload, string $projectSlug): bool {
  $type = strtolower(trim((string) ($payload['type'] ?? 'click')));
  if ($type !== 'click' && $type !== 'visit') {
    $type = 'click';
  }

  $ip =
    $_SERVER['HTTP_CF_CONNECTING_IP'] ??
    $_SERVER['HTTP_X_FORWARDED_FOR'] ??
    $_SERVER['REMOTE_ADDR'] ??
    '';

  // Normalize X-Forwarded-For: may contain comma-separated list.
  if (is_string($ip) && strpos($ip, ',') !== false) {
    $ip = trim(explode(',', $ip)[0]);
  }

  $row = [
    'created_at' => date('Y-m-d H:i:s'),
    'ip' => (string) $ip,
    'tag' => (string) ($payload['tag'] ?? ''),
    'text' => (string) ($payload['text'] ?? ''),
    'href' => (string) ($payload['href'] ?? ''),
    'element_id' => (string) ($payload['id'] ?? ''),
    'classes' => (string) ($payload['classes'] ?? ''),
    'page' => (string) ($payload['page'] ?? ''),
    'type' => $type,
    'referrer' => (string) ($payload['referrer'] ?? ''),
  ];

  $fingerprint = sha1(
    $projectSlug . '|' .
    $row['created_at'] . '|' .
    $row['ip'] . '|' .
    $row['type'] . '|' .
    $row['page'] . '|' .
    $row['href'] . '|' .
    $row['element_id'] . '|' .
    $row['classes'] . '|' .
    $row['text']
  );

  $st = $pdo->prepare("
    INSERT OR IGNORE INTO events
      (project_slug, created_at, ip, tag, text, href, element_id, classes, page, type, referrer, fingerprint)
    VALUES
      (:project_slug, :created_at, :ip, :tag, :text, :href, :element_id, :classes, :page, :type, :referrer, :fingerprint)
  ");

  return $st->execute([
    ':project_slug' => $projectSlug,
    ':created_at' => $row['created_at'],
    ':ip' => $row['ip'],
    ':tag' => $row['tag'],
    ':text' => $row['text'],
    ':href' => $row['href'],
    ':element_id' => $row['element_id'],
    ':classes' => $row['classes'],
    ':page' => $row['page'],
    ':type' => $row['type'],
    ':referrer' => $row['referrer'],
    ':fingerprint' => $fingerprint,
  ]);
}

