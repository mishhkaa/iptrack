<?php
require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/db.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;

function iptrack_export_project_excel(PDO $pdo, string $projectSlug): void {
  $projectSlug = trim($projectSlug);
  if ($projectSlug === '' || !preg_match('/^[a-z0-9\-_]+$/i', $projectSlug)) {
    http_response_code(400);
    exit('Invalid project');
  }

  $header = ['date','ip','tag','text','href','id','classes','page','type','referrer'];

  $st = $pdo->prepare("SELECT created_at, ip, tag, text, href, element_id, classes, page, type, referrer FROM events WHERE project_slug = :p ORDER BY created_at DESC");
  $st->execute([':p' => $projectSlug]);
  $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
  if (empty($rows)) {
    http_response_code(404);
    exit('No data');
  }

  $clicks = [];
  $visits = [];
  foreach ($rows as $r) {
    $dt = (string) ($r['created_at'] ?? '');
    $pretty = $dt !== '' ? date('d.m.Y H:i:s', strtotime($dt)) : '';
    $row = [
      $pretty,
      (string) ($r['ip'] ?? ''),
      (string) ($r['tag'] ?? ''),
      (string) ($r['text'] ?? ''),
      (string) ($r['href'] ?? ''),
      (string) ($r['element_id'] ?? ''),
      (string) ($r['classes'] ?? ''),
      (string) ($r['page'] ?? ''),
      (string) ($r['type'] ?? ''),
      (string) ($r['referrer'] ?? ''),
    ];
    $type = strtolower(trim((string) ($r['type'] ?? 'click')));
    if ($type === 'visit') {
      $visits[] = $row;
    } else {
      $clicks[] = $row;
    }
  }

  $spreadsheet = new Spreadsheet();

  $writeSheet = function ($sheet, string $title, array $header, array $rows) {
    $sheet->setTitle($title);
    $colCount = count($header);
    foreach ($header as $i => $val) {
      $sheet->setCellValue(Coordinate::stringFromColumnIndex($i + 1) . '1', $val);
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
  };

  $writeSheet($spreadsheet->getActiveSheet(), 'Clicks', $header, $clicks);
  $sheetVisits = $spreadsheet->createSheet(1);
  $writeSheet($sheetVisits, 'Visits', $header, $visits);

  header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
  header('Content-Disposition: attachment; filename="clicks_' . $projectSlug . '.xlsx"');
  header('Cache-Control: max-age=0');

  $writer = new Xlsx($spreadsheet);
  $writer->save('php://output');
  exit;
}

