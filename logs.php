<?php
/**
 * Перегляд логів (clicks + visits) з усіх проєктів одразу.
 * Доступно лише після входу в адмінку.
 */
session_start();
if (empty($_SESSION['admin_logged'])) {
  $baseUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? '');
  header('Location: ' . $baseUrl . '/admin/');
  exit;
}

$baseDir = __DIR__;
$limit = isset($_GET['limit']) ? min(2000, max(50, (int) $_GET['limit'])) : 500;
$filterProject = isset($_GET['project']) ? preg_replace('/[^a-z0-9\-_]/', '', $_GET['project']) : null;
$filterType = isset($_GET['type']) ? strtolower($_GET['type']) : null;
if ($filterType && !in_array($filterType, ['click', 'visit'], true)) {
  $filterType = null;
}

$projects = [];
foreach (scandir($baseDir) ?: [] as $name) {
  if ($name[0] === '.' || $name === 'vendor' || $name === 'admin' || !is_dir($baseDir . '/' . $name)) {
    continue;
  }
  $csvPath = $baseDir . '/' . $name . '/clicks.csv';
  if (!is_file($csvPath)) {
    continue;
  }
  if ($filterProject !== null && $filterProject !== '') {
    if ($name !== $filterProject) {
      continue;
    }
  }
  $projects[$name] = $csvPath;
}

$allRows = [];
$header = ['project', 'date', 'ip', 'tag', 'text', 'href', 'id', 'classes', 'page', 'type', 'referrer'];

foreach ($projects as $projectName => $csvPath) {
  $raw = array_map('str_getcsv', file($csvPath));
  if (empty($raw)) {
    continue;
  }
  $fileHeader = array_shift($raw);
  $fileHeader = array_pad($fileHeader, 10, '');
  if (count($fileHeader) < 10) {
    $fileHeader[8] = 'type';
    $fileHeader[9] = 'referrer';
  }
  foreach ($raw as $row) {
    $row = array_pad($row, 10, '');
    $type = isset($row[8]) ? strtolower(trim($row[8])) : 'click';
    if ($filterType !== null && $type !== $filterType) {
      continue;
    }
    $allRows[] = array_merge([$projectName], $row);
  }
}

// newest first (by date column index 1 in $allRows)
usort($allRows, function ($a, $b) {
  $t1 = strtotime(str_replace('.', '-', $a[1] ?? ''));
  $t2 = strtotime(str_replace('.', '-', $b[1] ?? ''));
  return $t2 - $t1;
});
$allRows = array_slice($allRows, 0, $limit);

header('Content-Type: text/html; charset=UTF-8');
?>
<!DOCTYPE html>
<html lang="uk">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Логи — всі проєкти</title>
  <style>
    * { box-sizing: border-box; }
    body { font-family: system-ui, sans-serif; margin: 0; padding: 12px; background: #1a1a1a; color: #e0e0e0; }
    h1 { font-size: 1.25rem; margin: 0 0 12px; }
    .toolbar { display: flex; flex-wrap: wrap; gap: 8px; align-items: center; margin-bottom: 12px; }
    .toolbar a, .toolbar span { color: #7dd3fc; text-decoration: none; }
    .toolbar a:hover { text-decoration: underline; }
    .toolbar select, .toolbar input { background: #2d2d2d; color: #e0e0e0; border: 1px solid #444; padding: 6px 8px; border-radius: 4px; }
    table { width: 100%; border-collapse: collapse; font-size: 13px; }
    th, td { padding: 6px 8px; text-align: left; border-bottom: 1px solid #333; overflow: hidden; text-overflow: ellipsis; max-width: 200px; }
    th { background: #2d2d2d; position: sticky; top: 0; }
    tr:hover td { background: #2a2a2a; }
    .type-click { color: #86efac; }
    .type-visit { color: #fcd34d; }
    .project { font-weight: 600; color: #a78bfa; max-width: 100px; }
    .empty { color: #666; padding: 24px; }
  </style>
</head>
<body>
  <h1>Логи (clicks + visits) — всі проєкти</h1>
  <div class="toolbar">
    <a href="?">Всі</a>
    <span>|</span>
    <a href="<?php echo (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? '') . '/admin/'; ?>">← Дашборд</a>
    <span>|</span>
    <a href="?type=click">Тільки кліки</a>
    <a href="?type=visit">Тільки візити</a>
    <span>|</span>
    <form method="get" style="display:inline;">
      <?php if ($filterType): ?><input type="hidden" name="type" value="<?php echo htmlspecialchars($filterType); ?>"><?php endif; ?>
      <label>Проєкт:
        <select name="project" onchange="this.form.submit()">
          <option value="">— всі —</option>
          <?php foreach (array_keys($projects) as $p): ?>
            <option value="<?php echo htmlspecialchars($p); ?>" <?php echo $filterProject === $p ? 'selected' : ''; ?>><?php echo htmlspecialchars($p); ?></option>
          <?php endforeach; ?>
        </select>
      </label>
    </form>
    <span>|</span>
    <form method="get" style="display:inline;">
      <?php if ($filterProject): ?><input type="hidden" name="project" value="<?php echo htmlspecialchars($filterProject); ?>"><?php endif; ?>
      <?php if ($filterType): ?><input type="hidden" name="type" value="<?php echo htmlspecialchars($filterType); ?>"><?php endif; ?>
      <label>Показати
        <input type="number" name="limit" value="<?php echo (int) $limit; ?>" min="50" max="2000" step="50" style="width:70px;" onchange="this.form.submit()">
        записів
      </label>
    </form>
    <?php foreach (array_keys($projects) as $p): ?>
      <a href="<?php echo htmlspecialchars($p); ?>/download.php">↓ <?php echo htmlspecialchars($p); ?>.xlsx</a>
    <?php endforeach; ?>
  </div>
  <?php if (empty($allRows)): ?>
    <p class="empty">Немає записів<?php echo $filterProject ? ' у проєкті ' . htmlspecialchars($filterProject) : ''; ?>.</p>
  <?php else: ?>
    <div style="overflow-x: auto;">
      <table>
        <thead>
          <tr>
            <?php foreach ($header as $h): ?>
              <th><?php echo htmlspecialchars($h); ?></th>
            <?php endforeach; ?>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($allRows as $row): ?>
            <?php
            $type = isset($row[9]) ? strtolower(trim($row[9])) : 'click';
            $rowClass = $type === 'visit' ? 'type-visit' : 'type-click';
            ?>
            <tr class="<?php echo $rowClass; ?>">
              <?php foreach ($row as $i => $val): ?>
                <td class="<?php echo $i === 0 ? 'project' : ''; ?>"><?php echo htmlspecialchars($val ?? ''); ?></td>
              <?php endforeach; ?>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</body>
</html>
