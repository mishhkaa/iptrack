<?php
require __DIR__ . '/vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

$project = isset($_GET['project']) ? trim($_GET['project']) : '';
if ($project === '' || !preg_match('/^[a-z0-9\-]+$/i', $project)) {
  http_response_code(400);
  exit('Invalid project');
}

$projectDir = __DIR__ . '/' . $project;
$txt = $projectDir . '/clicks.txt';
if (!file_exists($txt)) {
  exit('clicks.txt not found for project: ' . $project);
}

$lines = file($txt, FILE_IGNORE_NEW_LINES);
$rows = [];

$current = [];

foreach ($lines as $line) {

  // ----- старий формат -----
  if (strpos($line, '|') !== false && preg_match('/^\d{4}-\d{2}-\d{2}/', $line)) {
    $p = array_map('trim', explode('|', $line));
    $rows[] = [
      $p[0] ?? '',
      $p[1] ?? '',
      $p[2] ?? '',
      $p[3] ?? '',
      $p[4] ?? '',
      $p[5] ?? '',
      $p[6] ?? '',
      $p[7] ?? ''
    ];
    continue;
  }

  // ----- новий формат -----
  if (preg_match('/^\[(.+?)\]/', $line, $m)) {
    $current = [
      'date' => $m[1],
      'ip' => '',
      'tag' => '',
      'text' => '',
      'href' => '',
      'id' => '',
      'classes' => '',
      'page' => ''
    ];
  }

  if (str_starts_with($line, 'IP:'))    $current['ip']   = trim(substr($line, 3));
  if (str_starts_with($line, 'TAG:'))   $current['tag']  = trim(substr($line, 4));
  if (str_starts_with($line, 'TEXT:'))  $current['text'] = trim(substr($line, 5));
  if (str_starts_with($line, 'HREF:'))  $current['href'] = trim(substr($line, 5));
  if (str_starts_with($line, 'ID:'))      $current['id']      = trim(substr($line, 3));
  if (str_starts_with($line, 'CLASSES:')) $current['classes'] = trim(substr($line, 8));
  if (str_starts_with($line, 'PAGE:'))    $current['page']    = trim(substr($line, 5));

  if (str_contains($line, '-----') && $current) {
    $rows[] = array_values($current);
    $current = [];
  }
}

$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('Clicks');

$sheet->fromArray([
  ['date','ip','tag','text','href','id','classes','page']
], null, 'A1');

$sheet->fromArray($rows, null, 'A2');

foreach (range('A','H') as $c) {
  $sheet->getColumnDimension($c)->setAutoSize(true);
}

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="clicks_from_txt_' . basename($project) . '.xlsx"');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;
