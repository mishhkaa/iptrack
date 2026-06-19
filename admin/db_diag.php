<?php
$isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
  || (isset($_SERVER['SERVER_PORT']) && (string) $_SERVER['SERVER_PORT'] === '443')
  || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower((string) $_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https');
$cookieParams = session_get_cookie_params();
$sessionSameSite = $cookieParams['samesite'] ?? 'Lax';
if (!in_array($sessionSameSite, ['Lax', 'Strict', 'None'], true)) {
  $sessionSameSite = 'Lax';
}
session_set_cookie_params([
  'lifetime' => $cookieParams['lifetime'] ?? 0,
  'path' => '/',
  'domain' => $cookieParams['domain'] ?? '',
  'secure' => $isHttps,
  'httponly' => $cookieParams['httponly'] ?? true,
  'samesite' => $sessionSameSite,
]);
session_start();

if (empty($_SESSION['admin_logged'])) {
  header('Location: /admin/');
  exit;
}

require __DIR__ . '/../inc/sqlite_storage.php';

$defaultDataDir = __DIR__ . '/data';
$paths = iptrack_resolve_sqlite_paths($defaultDataDir);
$dataDir = $paths['dir'];
$dbPath = $paths['path'];
$phpUser = iptrack_php_process_user();
$fixHint = iptrack_storage_fix_hint();

$testFile = $dataDir . '/.write_test_' . getmypid();
$writeTest = @file_put_contents($testFile, 'ok');
if ($writeTest !== false) {
  @unlink($testFile);
}

$writeProbe = $writeTest !== false ? 'ok' : 'fail';

$insertTest = 'skip';
if ($writeProbe === 'ok') {
  try {
    $pdo = new PDO('sqlite:' . $dbPath, null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $pdo->exec('CREATE TABLE IF NOT EXISTS _diag_probe (id INTEGER PRIMARY KEY)');
    $pdo->exec('INSERT INTO _diag_probe (id) VALUES (1) ON CONFLICT(id) DO NOTHING');
    $insertTest = 'ok';
  } catch (Throwable $e) {
    $insertTest = $e->getMessage();
  }
}

header('Content-Type: text/html; charset=UTF-8');
?>
<!DOCTYPE html>
<html lang="uk">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Діагностика бази — IPTrack</title>
  <style>
    body { font-family: system-ui, sans-serif; margin: 24px; background: #0b1220; color: #e5e7eb; line-height: 1.5; }
    a { color: #60a5fa; }
    .card { max-width: 900px; background: #111827; border: 1px solid #334155; border-radius: 12px; padding: 20px; margin-bottom: 16px; }
    h1 { font-size: 1.25rem; margin: 0 0 16px; }
    table { width: 100%; border-collapse: collapse; font-size: 14px; }
    th, td { text-align: left; padding: 10px 12px; border-bottom: 1px solid #334155; vertical-align: top; }
    th { color: #94a3b8; width: 220px; }
    code, pre { background: #0f172a; padding: 2px 6px; border-radius: 6px; font-size: 13px; }
    pre { padding: 12px; overflow-x: auto; white-space: pre-wrap; word-break: break-all; }
    .ok { color: #86efac; }
    .fail { color: #f87171; }
    .alert { background: rgba(248, 113, 113, 0.12); border: 1px solid rgba(248, 113, 113, 0.45); border-radius: 10px; padding: 14px 16px; margin-bottom: 16px; color: #fecaca; }
    .alert strong { color: #fff; }
  </style>
</head>
<body>
  <p><a href="/admin/">← Назад до дашборду</a></p>
  <?php
  $dirPerms = iptrack_path_permissions($dataDir);
  $dbPerms = iptrack_path_permissions($dbPath);
  $ownerMismatch = $dirPerms && $dirPerms['owner'] !== $phpUser;
  if ($ownerMismatch): ?>
  <div class="alert">
    <strong>Знайдено причину:</strong> PHP працює під <code><?php echo htmlspecialchars($phpUser); ?></code>,
    але папка/база належать <code><?php echo htmlspecialchars($dirPerms['owner']); ?></code>.
    Попередній <code>chown www-data</code> зробив ситуацію гіршою — поверни власника на <code><?php echo htmlspecialchars($phpUser); ?></code> (команда нижче).
  </div>
  <?php endif; ?>
  <div class="card">
    <h1>Діагностика SQLite</h1>
    <table>
      <tr><th>PHP користувач</th><td><code><?php echo htmlspecialchars($phpUser); ?></code> (саме йому потрібні права на запис, не обовʼязково www-data)</td></tr>
      <tr><th>Шлях до БД</th><td><code><?php echo htmlspecialchars($dbPath); ?></code><?php if ($paths['custom']): ?> <span class="ok">(db.local.php)</span><?php endif; ?></td></tr>
      <tr><th>Папка БД</th><td><code><?php echo htmlspecialchars($dataDir); ?></code></td></tr>
      <tr><th>Папка існує</th><td class="<?php echo is_dir($dataDir) ? 'ok' : 'fail'; ?>"><?php echo is_dir($dataDir) ? 'так' : 'ні'; ?></td></tr>
      <tr><th>is_writable(папка)</th><td class="<?php echo is_writable($dataDir) ? 'ok' : 'fail'; ?>"><?php echo is_writable($dataDir) ? 'так' : 'ні'; ?></td></tr>
      <tr><th>Тест запису файлу</th><td class="<?php echo $writeProbe === 'ok' ? 'ok' : 'fail'; ?>"><?php echo htmlspecialchars($writeProbe); ?></td></tr>
      <tr><th>Файл БД існує</th><td><?php echo is_file($dbPath) ? 'так' : 'ні'; ?></td></tr>
      <tr><th>is_writable(БД)</th><td class="<?php echo is_file($dbPath) && is_writable($dbPath) ? 'ok' : (is_file($dbPath) ? 'fail' : ''); ?>"><?php echo is_file($dbPath) ? (is_writable($dbPath) ? 'так' : 'ні') : '—'; ?></td></tr>
      <tr><th>Тест INSERT у SQLite</th><td class="<?php echo $insertTest === 'ok' ? 'ok' : 'fail'; ?>"><?php echo htmlspecialchars($insertTest); ?></td></tr>
      <?php
      if ($dirPerms): ?>
      <tr><th>Права папки</th><td><code><?php echo htmlspecialchars($dirPerms['owner'] . ':' . $dirPerms['group'] . ' ' . $dirPerms['mode']); ?></code></td></tr>
      <?php endif;
      if ($dbPerms): ?>
      <tr><th>Права файлу БД</th><td><code><?php echo htmlspecialchars($dbPerms['owner'] . ':' . $dbPerms['group'] . ' ' . $dbPerms['mode']); ?></code></td></tr>
      <?php endif; ?>
    </table>
  </div>

  <div class="card">
    <h1>Команда для SSH</h1>
    <p>Виконай у <code>public_html</code> (підстав свій шлях, якщо інший):</p>
    <pre><?php echo htmlspecialchars($fixHint); ?></pre>
    <p>Якщо не допомагає — перенеси базу в writable-папку. Створи <code>admin/data/db.local.php</code>:</p>
    <pre><?php echo htmlspecialchars("<?php\nreturn [\n  'db_path' => '/home/" . $phpUser . "/private/iptrack.db',\n];"); ?></pre>
    <p>Потім:</p>
    <pre>mkdir -p /home/<?php echo htmlspecialchars($phpUser); ?>/private
cp admin/data/iptrack.db /home/<?php echo htmlspecialchars($phpUser); ?>/private/iptrack.db
chmod 664 /home/<?php echo htmlspecialchars($phpUser); ?>/private/iptrack.db</pre>
  </div>
</body>
</html>
