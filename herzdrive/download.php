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
$projectName = 'herzdrive';

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