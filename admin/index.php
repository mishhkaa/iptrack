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
    body { font-family: system-ui, sans-serif; margin: 0; padding: 16px; background: #1a1a1a; color: #e0e0e0; }
    .wrap { max-width: 900px; margin: 0 auto; }
    h1 { font-size: 1.35rem; margin: 0 0 20px; }
    a { color: #7dd3fc; text-decoration: none; }
    a:hover { text-decoration: underline; }
    .toolbar { margin-bottom: 20px; display: flex; align-items: center; gap: 12px; flex-wrap: wrap; }
    .card { background: #252525; border-radius: 8px; padding: 16px; margin-bottom: 16px; }
    .card h2 { font-size: 1rem; margin: 0 0 12px; }
    table { width: 100%; border-collapse: collapse; font-size: 14px; }
    th, td { padding: 10px 12px; text-align: left; border-bottom: 1px solid #333; }
    th { color: #94a3b8; font-weight: 500; }
    input[type="text"] { padding: 8px 12px; background: #2d2d2d; border: 1px solid #444; color: #e0e0e0; border-radius: 6px; width: 100%; max-width: 240px; }
    button, .btn { display: inline-block; padding: 8px 14px; background: #3b82f6; color: #fff; border: 0; border-radius: 6px; cursor: pointer; font-size: 14px; text-decoration: none; }
    button:hover, .btn:hover { background: #2563eb; }
    .btn-danger { background: #dc2626; }
    .btn-danger:hover { background: #b91c1c; }
    .msg { color: #86efac; margin-bottom: 12px; }
    .err { color: #f87171; margin-bottom: 12px; }
    .script-box { background: #0f172a; padding: 12px; border-radius: 6px; font-family: ui-monospace, monospace; font-size: 13px; overflow-x: auto; white-space: nowrap; }
    .script-box code { color: #e2e8f0; }
    .copy-btn { margin-top: 8px; font-size: 12px; padding: 6px 10px; }
  </style>
</head>
<body>
  <div class="wrap">
    <h1>Дашборд — кліки та візити</h1>
    <div class="toolbar">
      <a href="<?php echo htmlspecialchars($baseUrl); ?>/logs.php">Переглянути логи</a>
      <a href="<?php echo htmlspecialchars($baseUrl); ?>/admin/?import=1">Імпортувати з диска</a>
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
      <form method="post" style="display:flex;flex-wrap:wrap;gap:12px;align-items:flex-end;">
        <input type="hidden" name="add_project" value="1">
        <div>
          <label style="display:block;font-size:12px;color:#94a3b8;margin-bottom:4px;">Назва</label>
          <input type="text" name="name" placeholder="Мій сайт" required>
        </div>
        <div>
          <label style="display:block;font-size:12px;color:#94a3b8;margin-bottom:4px;">Slug (латиниця, цифри, дефіс)</label>
          <input type="text" name="slug" placeholder="my-site" pattern="[a-z0-9\-_]+" required>
        </div>
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
                <td>
                  <div class="script-box"><code><?php echo htmlspecialchars($scriptHtml); ?></code></div>
                  <button type="button" class="copy-btn" data-copy="<?php echo htmlspecialchars($scriptHtml); ?>">Копіювати</button>
                </td>
                <td>
                  <a href="<?php echo htmlspecialchars($trackerBase . $p['slug'] . '/download.php'); ?>" class="btn">↓ Excel</a>
                  <a href="<?php echo htmlspecialchars($baseUrl . '/admin/?delete=' . $p['slug']); ?>" class="btn btn-danger" onclick="return confirm('Видалити проєкт <?php echo htmlspecialchars($p['name']); ?>?');">Видалити</a>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>
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
  </script>
</body>
</html>
