<?php
require __DIR__ . '/vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;

$project = isset($_GET['project']) ? trim($_GET['project']) : '';
if ($project === '' || !preg_match('/^[a-z0-9\-]+$/i', $project)) {
  http_response_code(400);
  exit('Invalid project');
}

$projectDir = __DIR__ . '/' . $project;
$csvFile = $projectDir . '/clicks.csv';
if (!file_exists($csvFile)) {
  http_response_code(404);
  exit('No data');
}

$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('Clicks');

$rows = array_map('str_getcsv', file($csvFile));

$rowNum = 1;
foreach ($rows as $row) {
  $colNum = 1;
  foreach ($row as $value) {
    $cell = Coordinate::stringFromColumnIndex($colNum) . $rowNum;
    $sheet->setCellValue($cell, $value);
    $colNum++;
  }
  $rowNum++;
}

// трохи краси
$sheet->getStyle('A1:H1')->getFont()->setBold(true);
foreach (range('A','H') as $col) {
  $sheet->getColumnDimension($col)->setAutoSize(true);
}

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="clicks_' . basename($project) . '.xlsx"');
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;
