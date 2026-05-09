<?php
/**
 * Create project folder and log.php, download.php, google-ads/tracker.js.
 * Called from admin with base path = parent of admin (public_html).
 */
function createProjectFiles(string $basePath, string $slug): array {
  $errors = [];
  $dir = rtrim($basePath, '/') . '/' . $slug;
  if (file_exists($dir) && !is_dir($dir)) {
    return ['Папка не може бути створена: ім\'я зайняте файлом.'];
  }
  if (!file_exists($dir)) {
    if (!mkdir($dir, 0755, true)) {
      return ['Не вдалося створити папку проєкту.'];
    }
  }
  $logPhp = <<<'LOG'
<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Content-Type: text/plain; charset=UTF-8");

date_default_timezone_set('Europe/Kyiv');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
  http_response_code(200);
  exit;
}

$data = json_decode(file_get_contents("php://input"), true);
if (!$data) exit;

require __DIR__ . '/../inc/db.php';

if (iptrack_insert_event($pdo, $data, basename(__DIR__))) {
  echo "ok";
  exit;
}

http_response_code(500);
echo "db_error";
LOG;

  $downloadPhp = <<<'DOW'
<?php
require __DIR__ . '/../inc/export.php';

session_start();
if (empty($_SESSION['admin_logged'])) {
  http_response_code(403);
  header('Content-Type: text/plain; charset=UTF-8');
  exit('Доступ заборонено. Скачування лише через Дашборд.');
}

iptrack_export_project_excel($pdo, 'SLUG_PLACEHOLDER');
DOW;

  $trackerJs = <<<'TRACK'
(function () {
  var script = document.currentScript;
  var logUrl = (script && script.src) ? script.src.replace(/\/google-ads\/tracker\.js$/i, "/log.php") : "../log.php";

  function send(data) {
    fetch(logUrl, {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify(data)
    });
  }

  document.addEventListener("click", function (e) {
    var el = e.target.closest("button, a");
    if (!el) return;
    send({
      type: "click",
      tag: el.tagName,
      text: (el.innerText || "").trim().slice(0, 120),
      href: el.getAttribute("href") || "",
      id: el.id || "",
      classes: (el.className || "").trim().slice(0, 120),
      page: location.href
    });
  });

  if (typeof document.hidden !== "undefined" && document.hidden) {
    document.addEventListener("visibilitychange", function () {
      if (!document.hidden) send({ type: "visit", page: location.href, referrer: document.referrer });
    });
  } else {
    send({ type: "visit", page: location.href, referrer: document.referrer });
  }
})();
TRACK;

  if (file_put_contents($dir . '/log.php', $logPhp) === false) {
    $errors[] = 'Не вдалося записати log.php';
  }
  if (file_put_contents($dir . '/download.php', str_replace('SLUG_PLACEHOLDER', $slug, $downloadPhp)) === false) {
    $errors[] = 'Не вдалося записати download.php';
  }
  $trackerDir = $dir . '/google-ads';
  if (!is_dir($trackerDir)) {
    mkdir($trackerDir, 0755, true);
  }
  if (file_put_contents($trackerDir . '/tracker.js', $trackerJs) === false) {
    $errors[] = 'Не вдалося записати google-ads/tracker.js';
  }
  return $errors;
}

function deleteProjectDir(string $basePath, string $slug): bool {
  $dir = rtrim($basePath, '/') . '/' . $slug;
  if (!is_dir($dir)) {
    return true;
  }
  $files = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS),
    RecursiveIteratorIterator::CHILD_FIRST
  );
  foreach ($files as $f) {
    if ($f->isDir()) {
      rmdir($f->getPathname());
    } else {
      unlink($f->getPathname());
    }
  }
  return rmdir($dir);
}
