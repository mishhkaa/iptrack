<?php
session_start();
define('ADMIN_INIT', true);
require __DIR__ . '/db.php';

$baseUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? '');
$basePath = dirname(__DIR__);

function isLoggedIn(): bool {
  return !empty($_SESSION['admin_logged']);
}

function requireLogin(): void {
  if (!isLoggedIn()) {
    header('Location: ' . (strtok($_SERVER['REQUEST_URI'], '?') ?: '/admin/') . '?login=1');
    exit;
  }
}

// ----- Logout
if (isset($_GET['logout'])) {
  $_SESSION = [];
  session_destroy();
  header('Location: ' . $baseUrl . '/admin/');
  exit;
}

// ----- Setup password (first time)
if (getPasswordHash($pdo) === null) {
  if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['password']) && isset($_POST['password2'])) {
    $p1 = (string) $_POST['password'];
    $p2 = (string) $_POST['password2'];
    if (strlen($p1) < 6) {
      $setupError = 'Пароль має бути не коротшим за 6 символів.';
    } elseif ($p1 !== $p2) {
      $setupError = 'Паролі не збігаються.';
    } else {
      setPasswordHash($pdo, password_hash($p1, PASSWORD_DEFAULT));
      $_SESSION['admin_logged'] = true;
      header('Location: ' . $baseUrl . '/admin/');
      exit;
    }
  }
  header('Content-Type: text/html; charset=UTF-8');
  echo '<!DOCTYPE html><html lang="uk"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Встановити пароль</title>';
  echo '<style>body{font-family:system-ui;max-width:320px;margin:40px auto;padding:20px;background:#1a1a1a;color:#e0e0e0;} input{width:100%;padding:10px;margin:8px 0;background:#2d2d2d;border:1px solid #444;color:#fff;border-radius:6px;} button{width:100%;padding:12px;background:#3b82f6;color:#fff;border:0;border-radius:6px;cursor:pointer;} .err{color:#f87171;font-size:14px;margin-top:8px;} h1{font-size:1.2rem;}</style></head><body>';
  echo '<h1>Встановити пароль адмінки</h1>';
  if (!empty($setupError)) echo '<p class="err">' . htmlspecialchars($setupError) . '</p>';
  echo '<form method="post"><input type="password" name="password" placeholder="Пароль" required minlength="6"><input type="password" name="password2" placeholder="Повторити пароль" required><button type="submit">Зберегти</button></form></body></html>';
  exit;
}

// ----- Login
if (!isLoggedIn()) {
  if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['password'])) {
    $hash = getPasswordHash($pdo);
    if ($hash && password_verify((string) $_POST['password'], $hash)) {
      $_SESSION['admin_logged'] = true;
      header('Location: ' . $baseUrl . '/admin/');
      exit;
    }
    $loginError = 'Невірний пароль.';
  }
  header('Content-Type: text/html; charset=UTF-8');
  echo '<!DOCTYPE html><html lang="uk"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Вхід</title>';
  echo '<style>body{font-family:system-ui;max-width:320px;margin:40px auto;padding:20px;background:#1a1a1a;color:#e0e0e0;} input{width:100%;padding:10px;margin:8px 0;background:#2d2d2d;border:1px solid #444;color:#fff;border-radius:6px;} button{width:100%;padding:12px;background:#3b82f6;color:#fff;border:0;border-radius:6px;cursor:pointer;} .err{color:#f87171;font-size:14px;margin-top:8px;} h1{font-size:1.2rem;}</style></head><body>';
  echo '<h1>Вхід</h1>';
  if (!empty($loginError)) echo '<p class="err">' . htmlspecialchars($loginError) . '</p>';
  echo '<form method="post"><input type="password" name="password" placeholder="Пароль" required><button type="submit">Увійти</button></form></body></html>';
  exit;
}

requireLogin();
require __DIR__ . '/create_project_files.php';

$message = '';
$error = '';

// ----- Delete project
if (isset($_GET['delete']) && is_string($_GET['delete'])) {
  $slug = preg_replace('/[^a-z0-9\-_]/', '', $_GET['delete']);
  if ($slug !== '' && projectExists($pdo, $slug)) {
    deleteProject($pdo, $slug);
    deleteProjectDir($basePath, $slug);
    $message = 'Проєкт видалено.';
  }
  header('Location: ' . $baseUrl . '/admin/?msg=' . urlencode($message));
  exit;
}

// ----- Import existing projects from disk (folders that have log.php but not in DB)
if (isset($_GET['import']) && $_GET['import'] === '1') {
  $imported = 0;
  foreach (scandir($basePath) ?: [] as $name) {
    if ($name[0] === '.' || $name === 'admin' || $name === 'vendor' || !is_dir($basePath . '/' . $name)) continue;
    if (!is_file($basePath . '/' . $name . '/log.php')) continue;
    if (projectExists($pdo, $name)) continue;
    addProject($pdo, $name, $name);
    $imported++;
  }
  header('Location: ' . $baseUrl . '/admin/?msg=' . urlencode('Імпортовано проєктів: ' . $imported));
  exit;
}

// ----- Add monitored URL
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_monitored'])) {
  $mName = trim((string) ($_POST['monitored_name'] ?? ''));
  $mUrl = trim((string) ($_POST['monitored_url'] ?? ''));
  if ($mName !== '' && $mUrl !== '') {
    addMonitoredUrl($pdo, $mName, $mUrl);
    header('Location: ' . $baseUrl . '/admin/?tab=monitoring&msg=' . urlencode('Посилання додано до відстеження.'));
    exit;
  }
  $error = 'Вкажіть назву та URL.';
}

// ----- Delete monitored URL
if (isset($_GET['delete_monitored']) && is_numeric($_GET['delete_monitored'])) {
  $mid = (int) $_GET['delete_monitored'];
  deleteMonitoredUrl($pdo, $mid);
  header('Location: ' . $baseUrl . '/admin/?tab=monitoring&msg=' . urlencode('Посилання видалено з відстеження.'));
  exit;
}

// ----- Save Telegram settings
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_telegram'])) {
  setSetting($pdo, 'telegram_bot_token', trim((string) ($_POST['telegram_bot_token'] ?? '')));
  setSetting($pdo, 'telegram_chat_id', trim((string) ($_POST['telegram_chat_id'] ?? '')));
  header('Location: ' . $baseUrl . '/admin/?tab=monitoring&msg=' . urlencode('Налаштування Telegram збережено.'));
  exit;
}

// ----- Add project
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_project'])) {
  $name = trim((string) ($_POST['name'] ?? ''));
  $slug = strtolower(trim((string) ($_POST['slug'] ?? '')));
  $slug = preg_replace('/[^a-z0-9\-_]/', '', $slug);
  if ($name === '' || $slug === '') {
    $error = 'Заповніть назву та slug (латиниця, цифри, дефіс).';
  } elseif (projectExists($pdo, $slug)) {
    $error = 'Такий slug вже існує.';
  } elseif (in_array($slug, ['admin', 'vendor', 'data'], true)) {
    $error = 'Цей slug заборонено.';
  } else {
    $createErrors = createProjectFiles($basePath, $slug);
    if (!empty($createErrors)) {
      $error = implode(' ', $createErrors);
    } else {
      addProject($pdo, $name, $slug);
      $message = 'Проєкт «' . htmlspecialchars($name) . '» створено.';
      header('Location: ' . $baseUrl . '/admin/?msg=' . urlencode($message));
      exit;
    }
  }
}

if (isset($_GET['msg'])) {
  $message = (string) $_GET['msg'];
}

$projects = getAllProjects($pdo);
$monitoredUrls = getMonitoredUrls($pdo);
$telegramToken = getSetting($pdo, 'telegram_bot_token') ?: '';
$telegramChatId = getSetting($pdo, 'telegram_chat_id') ?: '';
$trackerBase = $baseUrl . '/';
header('Content-Type: text/html; charset=UTF-8');
?>
<!DOCTYPE html>
<html lang="uk">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Дашборд — IPTrack</title>
  <style>
    * { box-sizing: border-box; }
    body { font-family: 'Segoe UI', system-ui, sans-serif; margin: 0; padding: 20px 16px; background: #111827; color: #e5e7eb; line-height: 1.5; }
    .wrap { max-width: 960px; margin: 0 auto; }
    h1 { font-size: 1.5rem; font-weight: 600; margin: 0 0 24px; color: #f9fafb; letter-spacing: -0.02em; }
    a { color: #60a5fa; text-decoration: none; }
    a:hover { text-decoration: underline; color: #93c5fd; }
    .toolbar { margin-bottom: 24px; display: flex; align-items: center; gap: 16px; flex-wrap: wrap; }
    .toolbar a { padding: 8px 14px; border-radius: 8px; font-size: 14px; background: rgba(30, 41, 59, 0.8); border: 1px solid rgba(71, 85, 105, 0.5); transition: background 0.15s, border-color 0.15s; }
    .toolbar a:hover { background: rgba(51, 65, 85, 0.8); border-color: rgba(96, 165, 250, 0.4); text-decoration: none; }
    .card { background: #1f2937; border: 1px solid #374151; border-radius: 12px; padding: 20px 24px; margin-bottom: 20px; }
    .card h2 { font-size: 1rem; font-weight: 600; margin: 0 0 16px; color: #d1d5db; }
    table { width: 100%; border-collapse: collapse; font-size: 14px; }
    .card table { margin-top: 12px; }
    .card table thead th { padding: 12px 14px; text-align: left; border-bottom: 1px solid #374151; color: #9ca3af; font-weight: 500; font-size: 12px; text-transform: uppercase; letter-spacing: 0.03em; }
    .card table tbody td { padding: 14px; border-bottom: 1px solid #374151; vertical-align: top; }
    .card table tbody tr:last-child td { border-bottom: none; }
    .card table tbody tr:hover td { background: rgba(55, 65, 81, 0.3); }
    .script-cell { min-width: 320px; }
    .script-wrap { background: #0f172a; border: 1px solid #1e3a5f; border-radius: 8px; overflow: hidden; }
    .script-box { padding: 12px 16px; font-family: ui-monospace, 'SF Mono', monospace; font-size: 13px; overflow-x: auto; white-space: nowrap; }
    .script-box code { color: #e2e8f0; }
    .script-actions { display: flex; align-items: center; gap: 8px; padding: 8px 16px; background: rgba(15, 23, 42, 0.6); border-top: 1px solid #1e3a5f; }
    .script-actions .copy-btn { margin: 0; }
    th, td { text-align: left; }
    th { color: #94a3b8; }
    input[type="text"], input[type="url"] { padding: 10px 14px; background: #111827; border: 1px solid #374151; color: #e5e7eb; border-radius: 8px; font-size: 14px; width: 100%; max-width: 260px; }
    input:focus { outline: none; border-color: #60a5fa; box-shadow: 0 0 0 2px rgba(96, 165, 250, 0.2); }
    button, .btn { display: inline-block; padding: 10px 16px; border-radius: 8px; font-size: 14px; font-weight: 500; cursor: pointer; border: none; text-decoration: none; transition: opacity 0.15s, transform 0.05s; }
    button:hover, .btn:hover { opacity: 0.95; }
    button:not(.copy-btn), .btn { background: #3b82f6; color: #fff; }
    button:not(.copy-btn):hover, .btn:hover { background: #2563eb; }
    .btn-danger { background: #dc2626; }
    .btn-danger:hover { background: #b91c1c; }
    .btn + .btn { margin-left: 8px; }
    .msg { color: #86efac; margin-bottom: 16px; padding: 12px 16px; background: rgba(34, 197, 94, 0.1); border-radius: 8px; border: 1px solid rgba(34, 197, 94, 0.25); font-size: 14px; }
    .err { color: #f87171; margin-bottom: 16px; padding: 12px 16px; background: rgba(248, 113, 113, 0.08); border-radius: 8px; border: 1px solid rgba(248, 113, 113, 0.25); font-size: 14px; }
    .copy-btn { margin-top: 0; font-size: 12px; padding: 6px 12px; background: #374151; color: #e5e7eb; }
    .copy-btn:hover { background: #4b5563; }
    .form-row { display: flex; flex-wrap: wrap; gap: 16px; align-items: flex-end; }
    .form-row label { display: flex; flex-direction: column; gap: 6px; font-size: 12px; color: #9ca3af; }
    .form-row label input { max-width: none; }
    .card p { margin: 0 0 12px; }
    .card p:last-of-type { margin-bottom: 16px; }
    .cron-code { background: #0f172a; padding: 10px 14px; border-radius: 8px; font-size: 12px; color: #94a3b8; margin-top: 8px; overflow-x: auto; border: 1px solid #1e3a5f; }
    .status-ok { color: #86efac; }
    .status-down, .status-timeout, .status-ssl_error, .status-ssl_expired, .status-error { color: #f87171; }
    .status-pending { color: #9ca3af; }
  </style>
</head>
<body>
  <div class="wrap">
    <h1>Дашборд — кліки та візити</h1>
    <div class="toolbar">
      <a href="<?php echo htmlspecialchars($baseUrl); ?>/logs.php">Переглянути логи</a>
      <a href="<?php echo htmlspecialchars($baseUrl); ?>/admin/?import=1">Імпортувати з диска</a>
      <a href="<?php echo htmlspecialchars($baseUrl); ?>/admin/#monitoring">Моніторинг</a>
      <a href="<?php echo htmlspecialchars($baseUrl); ?>/admin/?logout=1">Вийти</a>
    </div>
    <?php if ($message !== ''): ?>
      <p class="msg"><?php echo htmlspecialchars($message); ?></p>
    <?php endif; ?>
    <?php if ($error !== ''): ?>
      <p class="err"><?php echo htmlspecialchars($error); ?></p>
    <?php endif; ?>

    <div class="card">
      <h2>Додати проєкт</h2>
      <form method="post" class="form-row">
        <input type="hidden" name="add_project" value="1">
        <label>
          Назва
          <input type="text" name="name" placeholder="Мій сайт" required>
        </label>
        <label>
          Slug (латиниця, цифри, дефіс)
          <input type="text" name="slug" placeholder="my-site" pattern="[a-z0-9\-_]+" required>
        </label>
        <button type="submit">Створити проєкт</button>
      </form>
    </div>

    <div class="card">
      <h2>Проєкти та скрипт для вставки</h2>
      <?php if (empty($projects)): ?>
        <p style="color:#94a3b8;">Ще немає проєктів. Додайте проєкт вище — з'явиться посилання на трекер і код для вставки на сайт.</p>
      <?php else: ?>
        <table>
          <thead>
            <tr>
              <th>Назва</th>
              <th>Slug</th>
              <th>Скрипт</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($projects as $p): ?>
              <?php
              $scriptUrl = $trackerBase . $p['slug'] . '/google-ads/tracker.js';
              $scriptHtml = '<script src="' . htmlspecialchars($scriptUrl) . '"></script>';
              ?>
              <tr>
                <td><?php echo htmlspecialchars($p['name']); ?></td>
                <td><code><?php echo htmlspecialchars($p['slug']); ?></code></td>
                <td class="script-cell">
                  <div class="script-wrap">
                    <div class="script-box"><code><?php echo htmlspecialchars($scriptHtml); ?></code></div>
                    <div class="script-actions">
                      <button type="button" class="copy-btn" data-copy="<?php echo htmlspecialchars($scriptHtml); ?>">Копіювати</button>
                    </div>
                  </div>
                </td>
                <td style="white-space:nowrap;">
                  <a href="<?php echo htmlspecialchars($trackerBase . $p['slug'] . '/download.php'); ?>" class="btn">↓ Excel</a>
                  <a href="<?php echo htmlspecialchars($baseUrl . '/admin/?delete=' . $p['slug']); ?>" class="btn btn-danger" onclick="return confirm('Видалити проєкт <?php echo htmlspecialchars($p['name']); ?>?');">Видалити</a>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>
    </div>

    <div class="card" id="monitoring">
      <h2>Відстеження проектів (моніторинг)</h2>
      <p style="color:#9ca3af;font-size:14px;margin-bottom:16px;">Додайте посилання — перевірка кожні 60 хв: чи сайт доступний, чи SSL активний. При збої — сповіщення в Telegram.</p>
      <form method="post" class="form-row" style="margin-bottom:20px;">
        <input type="hidden" name="add_monitored" value="1">
        <label style="min-width:160px;">
          Назва
          <input type="text" name="monitored_name" placeholder="Мій сайт">
        </label>
        <label style="flex:1;min-width:220px;">
          Посилання (URL)
          <input type="url" name="monitored_url" placeholder="https://example.com">
        </label>
        <button type="submit">Додати відстеження</button>
      </form>
      <p style="color:#9ca3af;font-size:13px;">Перевірка: сайт вгорі/впав, SSL дійсний/протермінований, таймаут.</p>
      <p style="color:#6b7280;font-size:12px;margin-bottom:4px;">Cron кожні 60 хв:</p>
      <div class="cron-code">0 * * * * php /шлях/до/public_html/admin/check_monitored.php</div>
      <p style="margin-top:16px;margin-bottom:0;"><a href="<?php echo htmlspecialchars($baseUrl); ?>/admin/check_monitored.php" class="btn">Перевірити зараз</a></p>
      <?php if (!empty($monitoredUrls)): ?>
        <table style="margin-top:20px;">
          <thead>
            <tr>
              <th>Назва</th>
              <th>URL</th>
              <th>Статус</th>
              <th>Остання перевірка</th>
              <th>Помилка</th>
              <th>Дії</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($monitoredUrls as $m): ?>
              <tr>
                <td><?php echo htmlspecialchars($m['name']); ?></td>
                <td><a href="<?php echo htmlspecialchars($m['url']); ?>" target="_blank" rel="noopener"><?php echo htmlspecialchars($m['url']); ?></a></td>
                <td><span class="status-<?php echo htmlspecialchars($m['status']); ?>"><?php echo htmlspecialchars($m['status']); ?></span></td>
                <td><?php echo $m['last_checked_at'] ? htmlspecialchars($m['last_checked_at']) : '—'; ?></td>
                <td style="max-width:180px;overflow:hidden;text-overflow:ellipsis;"><?php echo $m['last_error'] ? htmlspecialchars($m['last_error']) : '—'; ?></td>
                <td><a href="<?php echo htmlspecialchars($baseUrl . '/admin/?delete_monitored=' . $m['id']); ?>" class="btn btn-danger" onclick="return confirm('Видалити з відстеження?');">Видалити</a></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php else: ?>
        <p style="color:#94a3b8;">Посилань для відстеження ще немає. Додайте вище.</p>
      <?php endif; ?>
    </div>

    <div class="card">
      <h2>Telegram — сповіщення при збоях</h2>
      <p style="color:#9ca3af;font-size:14px;margin-bottom:16px;">Створіть бота через @BotFather, отримайте токен. Chat ID: напишіть боту /start, потім відкрийте <code style="background:#0f172a;padding:2px 6px;border-radius:4px;">https://api.telegram.org/bot&lt;TOKEN&gt;/getUpdates</code> — у відповіді буде chat.id.</p>
      <form method="post" style="max-width:480px;">
        <input type="hidden" name="save_telegram" value="1">
        <label class="form-row" style="margin-bottom:14px;display:block;">
          <span style="display:block;font-size:12px;color:#9ca3af;margin-bottom:6px;">Bot Token</span>
          <input type="text" name="telegram_bot_token" value="<?php echo htmlspecialchars($telegramToken); ?>" placeholder="123456:ABC..." style="max-width:100%;">
        </label>
        <label class="form-row" style="margin-bottom:14px;display:block;">
          <span style="display:block;font-size:12px;color:#9ca3af;margin-bottom:6px;">Chat ID</span>
          <input type="text" name="telegram_chat_id" value="<?php echo htmlspecialchars($telegramChatId); ?>" placeholder="-1001234567890" style="max-width:100%;">
        </label>
        <button type="submit">Зберегти</button>
      </form>
    </div>
  </div>
  <script>
    document.querySelectorAll('[data-copy]').forEach(function(btn) {
      btn.addEventListener('click', function() {
        var t = this.getAttribute('data-copy');
        if (navigator.clipboard && navigator.clipboard.writeText) {
          navigator.clipboard.writeText(t).then(function() { btn.textContent = 'Скопійовано'; setTimeout(function(){ btn.textContent = 'Копіювати'; }, 1500); });
        } else {
          var ta = document.createElement('textarea'); ta.value = t; document.body.appendChild(ta); ta.select(); document.execCommand('copy'); document.body.removeChild(ta);
          btn.textContent = 'Скопійовано'; setTimeout(function(){ btn.textContent = 'Копіювати'; }, 1500);
        }
      });
    });
    if (window.location.search.indexOf('tab=monitoring') !== -1) {
      var el = document.getElementById('monitoring');
      if (el) el.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }
  </script>
</body>
</html>
