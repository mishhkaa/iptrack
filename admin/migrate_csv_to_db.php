<?php
/**
 * One-time (re-runnable) migration: move all project "<slug>/clicks.csv" into SQLite events table.
 *
 * Usage:
 *   php migrate_csv_to_db.php
 *
 * Safe to re-run: uses unique fingerprint + INSERT OR IGNORE.
 */
define('ADMIN_INIT', true);
require __DIR__ . '/../inc/db.php';

$baseDir = realpath(__DIR__ . '/..') ?: (__DIR__ . '/..');

function parseCsvDateToSql(string $dateStr): ?string {
  $dateStr = trim($dateStr);
  if ($dateStr === '') return null;
  // Expected: d.m.Y H:i:s
  if (preg_match('/^(\d{2})\.(\d{2})\.(\d{4})\s+(\d{2}):(\d{2}):(\d{2})$/', $dateStr, $m)) {
    return sprintf('%04d-%02d-%02d %02d:%02d:%02d', (int) $m[3], (int) $m[2], (int) $m[1], (int) $m[4], (int) $m[5], (int) $m[6]);
  }
  $t = strtotime(str_replace('.', '-', $dateStr));
  if ($t === false) return null;
  return date('Y-m-d H:i:s', $t);
}

function computeFingerprint(string $project, array $row): string {
  // Row keys: created_at, ip, tag, text, href, element_id, classes, page, type, referrer
  return sha1(
    $project . '|' .
    ($row['created_at'] ?? '') . '|' .
    ($row['ip'] ?? '') . '|' .
    ($row['type'] ?? '') . '|' .
    ($row['page'] ?? '') . '|' .
    ($row['href'] ?? '') . '|' .
    ($row['element_id'] ?? '') . '|' .
    ($row['classes'] ?? '') . '|' .
    ($row['text'] ?? '')
  );
}

$insert = $pdo->prepare("
  INSERT OR IGNORE INTO events
    (project_slug, created_at, ip, tag, text, href, element_id, classes, page, type, referrer, fingerprint)
  VALUES
    (:project_slug, :created_at, :ip, :tag, :text, :href, :element_id, :classes, :page, :type, :referrer, :fingerprint)
");

$totalFiles = 0;
$totalRows = 0;
$inserted = 0;

foreach (scandir($baseDir) ?: [] as $name) {
  if ($name === '.' || $name === '..') continue;
  if ($name === 'admin' || $name === 'vendor' || $name === 'inc') continue;
  $dir = $baseDir . '/' . $name;
  if (!is_dir($dir)) continue;
  if (!is_file($dir . '/log.php')) continue;

  $csvPath = $dir . '/clicks.csv';
  if (!is_file($csvPath)) continue;

  $totalFiles++;
  $fh = fopen($csvPath, 'r');
  if ($fh === false) continue;

  // header
  $header = fgetcsv($fh);
  $lineNo = 1;
  while (($cols = fgetcsv($fh)) !== false) {
    $lineNo++;
    if (!is_array($cols) || count($cols) === 0) continue;

    // Some old rows may have fewer columns. Expected:
    // date, ip, tag, text, href, id, classes, page, type, referrer
    $cols = array_pad($cols, 10, '');

    $createdAt = parseCsvDateToSql((string) ($cols[0] ?? ''));
    if ($createdAt === null) {
      // Skip malformed date rows.
      continue;
    }

    $row = [
      'created_at' => $createdAt,
      'ip' => (string) ($cols[1] ?? ''),
      'tag' => (string) ($cols[2] ?? ''),
      'text' => (string) ($cols[3] ?? ''),
      'href' => (string) ($cols[4] ?? ''),
      'element_id' => (string) ($cols[5] ?? ''),
      'classes' => (string) ($cols[6] ?? ''),
      'page' => (string) ($cols[7] ?? ''),
      'type' => strtolower(trim((string) ($cols[8] ?? 'click'))) ?: 'click',
      'referrer' => (string) ($cols[9] ?? ''),
    ];
    if ($row['type'] !== 'click' && $row['type'] !== 'visit') {
      $row['type'] = 'click';
    }

    $fingerprint = computeFingerprint($name, $row);
    $totalRows++;

    $ok = $insert->execute([
      ':project_slug' => $name,
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
    if ($ok && $insert->rowCount() > 0) {
      $inserted++;
    }
  }
  fclose($fh);
}

echo "Done.\n";
echo "Files: {$totalFiles}\n";
echo "Rows scanned: {$totalRows}\n";
echo "Inserted: {$inserted}\n";

