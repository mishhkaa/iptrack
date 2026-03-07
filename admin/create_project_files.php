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

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
  http_response_code(200);
  exit;
}

$data = json_decode(file_get_contents("php://input"), true);
if (!$data) exit;

$ip =
  $_SERVER['HTTP_CF_CONNECTING_IP'] ??
  $_SERVER['HTTP_X_FORWARDED_FOR'] ??
  $_SERVER['REMOTE_ADDR'];

$file = __DIR__ . "/clicks.csv";
$isNew = !file_exists($file);

$fp = @fopen($file, "a");
if ($fp === false) {
  http_response_code(503);
  header("Content-Type: text/plain; charset=UTF-8");
  echo "Cannot write to " . basename(__DIR__) . "/clicks.csv. Check folder permissions (chown/chmod).";
  exit;
}

if ($isNew) {
  fputcsv($fp, [
    "date",
    "ip",
    "tag",
    "text",
    "href",
    "id",
    "classes",
    "page",
    "type",
    "referrer"
  ]);
}

$type = $data['type'] ?? 'click';
$referrer = $data['referrer'] ?? '';

fputcsv($fp, [
  date("d.m.Y H:i:s"),
  $ip,
  $data['tag'] ?? '',
  $data['text'] ?? '',
  $data['href'] ?? '',
  $data['id'] ?? '',
  $data['classes'] ?? '',
  $data['page'] ?? '',
  $type,
  $referrer
]);

fclose($fp);

echo "ok";
LOG;

  $downloadPhp = <<<'DOW'
<?php
require __DIR__ . '/../vendor/autoload.php';

session_start();
if (empty($_SESSION['admin_logged'])) {
  http_response_code(403);
  header('Content-Type: text/plain; charset=UTF-8');
  exit('Доступ заборонено. Скачування лише через Дашборд.');
}

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;

$csvFile = __DIR__ . '/clicks.csv';
if (!file_exists($csvFile)) {
  http_response_code(404);
  exit('No data');
}

$raw = array_map('str_getcsv', file($csvFile));
$header = array_shift($raw);
$header = array_pad($header, 10, '');
if (count($header) < 10) {
  $header[8] = 'type';
  $header[9] = 'referrer';
}

$clicks = [];
$visits = [];
foreach ($raw as $row) {
  $row = array_pad($row, 10, '');
  $type = isset($row[8]) ? strtolower(trim($row[8])) : 'click';
  if ($type === 'visit') {
    $visits[] = $row;
  } else {
    $clicks[] = $row;
  }
}

$spreadsheet = new Spreadsheet();
$projectName = 'SLUG_PLACEHOLDER';

function writeSheet($sheet, $title, $header, $rows) {
  $sheet->setTitle($title);
  $colCount = count($header);
  $rowNum = 1;
  foreach ($header as $i => $val) {
    $sheet->setCellValue(Coordinate::stringFromColumnIndex($i + 1) . $rowNum, $val);
  }
  $sheet->getStyle(Coordinate::stringFromColumnIndex(1) . '1:' . Coordinate::stringFromColumnIndex($colCount) . '1')->getFont()->setBold(true);
  $rowNum = 2;
  foreach ($rows as $row) {
    foreach ($row as $i => $val) {
      if ($i < $colCount) {
        $sheet->setCellValue(Coordinate::stringFromColumnIndex($i + 1) . $rowNum, $val);
      }
    }
    $rowNum++;
  }
  foreach (range(1, $colCount) as $c) {
    $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($c))->setAutoSize(true);
  }
}

writeSheet($spreadsheet->getActiveSheet(), 'Clicks', $header, $clicks);
$sheetVisits = $spreadsheet->createSheet(1);
writeSheet($sheetVisits, 'Visits', $header, $visits);

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="clicks_' . $projectName . '.xlsx"');
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;
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
  // Пустий clicks.csv з заголовком — щоб проєкт одразу з'являвся у фільтрі на сторінці логів
  $csvHeader = ["date", "ip", "tag", "text", "href", "id", "classes", "page", "type", "referrer"];
  $csvLine = '';
  $fh = fopen('php://memory', 'r+');
  if ($fh !== false) {
    fputcsv($fh, $csvHeader);
    rewind($fh);
    $csvLine = stream_get_contents($fh);
    fclose($fh);
  }
  if ($csvLine !== '' && file_put_contents($dir . '/clicks.csv', $csvLine) === false) {
    $errors[] = 'Не вдалося створити clicks.csv';
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
