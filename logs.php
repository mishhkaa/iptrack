<?php
/**
 * Перегляд логів (clicks + visits) з усіх проєктів одразу.
 * Доступно лише після входу в адмінку.
 */
$isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
  || (isset($_SERVER['SERVER_PORT']) && (string) $_SERVER['SERVER_PORT'] === '443')
  || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower((string) $_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https');
$cookieParams = session_get_cookie_params();
session_set_cookie_params([
  'lifetime' => $cookieParams['lifetime'] ?? 0,
  'path' => '/',
  'domain' => $cookieParams['domain'] ?? '',
  'secure' => $isHttps,
  'httponly' => $cookieParams['httponly'] ?? true,
  'samesite' => $cookieParams['samesite'] ?? 'Lax',
]);
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
$dateFrom = isset($_GET['date_from']) ? trim((string) $_GET['date_from']) : null;
$dateTo   = isset($_GET['date_to'])   ? trim((string) $_GET['date_to'])   : null;
$filterId   = isset($_GET['filter_id'])   ? trim((string) $_GET['filter_id'])   : null;
$filterHref = isset($_GET['filter_href']) ? trim((string) $_GET['filter_href']) : null;
$filterText    = isset($_GET['filter_text'])    ? trim((string) $_GET['filter_text'])    : null;
$filterClasses = isset($_GET['filter_classes']) ? trim((string) $_GET['filter_classes']) : null;
$filterPage    = isset($_GET['filter_page'])    ? trim((string) $_GET['filter_page'])    : null;

function filterRowsBySubstring(array $rows, int $colIndex, ?string $needle): array {
  if ($needle === null || $needle === '') {
    return $rows;
  }
  $n = mb_strtolower($needle);
  return array_filter($rows, function ($row) use ($n, $colIndex) {
    return isset($row[$colIndex]) && mb_strpos(mb_strtolower((string) $row[$colIndex]), $n) !== false;
  });
}

$navBase = [];
if ($filterProject) {
  $navBase['project'] = $filterProject;
}
if ($limit !== 500) {
  $navBase['limit'] = $limit;
}
if ($dateFrom !== null && $dateFrom !== '') {
  $navBase['date_from'] = $dateFrom;
}
if ($dateTo !== null && $dateTo !== '') {
  $navBase['date_to'] = $dateTo;
}
if ($filterId !== null && $filterId !== '') {
  $navBase['filter_id'] = $filterId;
}
if ($filterHref !== null && $filterHref !== '') {
  $navBase['filter_href'] = $filterHref;
}
if ($filterText !== null && $filterText !== '') {
  $navBase['filter_text'] = $filterText;
}
if ($filterClasses !== null && $filterClasses !== '') {
  $navBase['filter_classes'] = $filterClasses;
}
if ($filterPage !== null && $filterPage !== '') {
  $navBase['filter_page'] = $filterPage;
}
$navQs = http_build_query($navBase);

function parseRowDate($dateStr) {
  if ($dateStr === '') return null;
  $parts = explode(' ', trim($dateStr));
  $d = isset($parts[0]) ? explode('.', $parts[0]) : [];
  if (count($d) !== 3) {
    $t = strtotime(str_replace('.', '-', $dateStr));
    return $t ?: null;
  }
  return mktime(0, 0, 0, (int) $d[1], (int) $d[0], (int) $d[2]);
}

$projects = [];
foreach (scandir($baseDir) ?: [] as $name) {
  if ($name[0] === '.' || $name === 'vendor' || $name === 'admin' || !is_dir($baseDir . '/' . $name)) {
    continue;
  }
  $dirPath = $baseDir . '/' . $name;
  $csvPath = $dirPath . '/clicks.csv';
  $hasLog = is_file($dirPath . '/log.php');
  if (!is_file($csvPath) && !$hasLog) {
    continue;
  }
  if ($filterProject !== null && $filterProject !== '') {
    if ($name !== $filterProject) {
      continue;
    }
  }
  $projects[$name] = is_file($csvPath) ? $csvPath : null;
}

$allRows = [];
$header = ['project', 'date', 'ip', 'tag', 'text', 'href', 'id', 'classes', 'page', 'type', 'referrer'];

foreach ($projects as $projectName => $csvPath) {
  if ($csvPath === null) {
    continue;
  }
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
    $type = isset($row[8]) ? strtolower(trim((string) $row[8])) : '';
    if ($type === '') {
      $type = 'click';
    }
    if ($filterType !== null && $type !== $filterType) {
      continue;
    }
    $allRows[] = array_merge([$projectName], $row);
  }
}

// Фільтр по даті та полях рядка (text, href, id, classes, page)
if ($dateFrom !== null && $dateFrom !== '') {
  $tsFrom = strtotime($dateFrom);
  if ($tsFrom) {
    $allRows = array_filter($allRows, function ($row) use ($tsFrom) {
      $t = parseRowDate($row[1] ?? '');
      return $t !== null && $t >= $tsFrom;
    });
  }
}
if ($dateTo !== null && $dateTo !== '') {
  $tsTo = strtotime($dateTo . ' 23:59:59');
  if ($tsTo) {
    $allRows = array_filter($allRows, function ($row) use ($tsTo) {
      $t = parseRowDate($row[1] ?? '');
      return $t !== null && $t <= $tsTo;
    });
  }
}
$allRows = filterRowsBySubstring($allRows, 4, $filterText);
$allRows = filterRowsBySubstring($allRows, 5, $filterHref);
$allRows = filterRowsBySubstring($allRows, 6, $filterId);
$allRows = filterRowsBySubstring($allRows, 7, $filterClasses);
$allRows = filterRowsBySubstring($allRows, 8, $filterPage);
$allRows = array_values($allRows);

// newest first (by date column index 1 in $allRows)
usort($allRows, function ($a, $b) {
  $t1 = strtotime(str_replace('.', '-', $a[1] ?? ''));
  $t2 = strtotime(str_replace('.', '-', $b[1] ?? ''));
  return $t2 - $t1;
});
$allRows = array_slice($allRows, 0, $limit);

$ipsForCopy = implode(', ', array_map(function ($row) { return $row[2] ?? ''; }, $allRows));

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
    body { font-family: 'Segoe UI', system-ui, -apple-system, sans-serif; margin: 0; padding: 0; background: linear-gradient(160deg, #0f172a 0%, #1e293b 50%, #0f172a 100%); min-height: 100vh; color: #e2e8f0; }
    .page { max-width: 1200px; margin: 0 auto; padding: 24px 16px; }
    .top-bar { display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 16px; margin-bottom: 24px; padding-bottom: 20px; border-bottom: 1px solid rgba(148, 163, 184, 0.2); }
    .top-bar h1 { font-size: 1.5rem; font-weight: 600; margin: 0; letter-spacing: -0.02em; color: #f8fafc; }
    .top-bar .nav { display: flex; align-items: center; gap: 12px; flex-wrap: wrap; }
    .top-bar a { color: #7dd3fc; text-decoration: none; font-size: 14px; padding: 8px 12px; border-radius: 8px; transition: background 0.2s, color 0.2s; }
    .nav a.active { background: rgba(56, 189, 248, 0.2); color: #38bdf8; }
    .card { background: rgba(30, 41, 59, 0.6); border: 1px solid rgba(148, 163, 184, 0.12); border-radius: 12px; padding: 20px; margin-bottom: 20px; backdrop-filter: blur(8px); }
    .card-title { font-size: 13px; font-weight: 600; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.05em; margin: 0 0 16px; }
    .filters { display: flex; flex-wrap: wrap; align-items: flex-end; gap: 12px; }
    .filters label { display: flex; flex-direction: column; gap: 4px; font-size: 12px; color: #94a3b8; }
    .filters input, .filters select { background: rgba(15, 23, 42, 0.8); color: #e2e8f0; border: 1px solid rgba(148, 163, 184, 0.25); padding: 8px 12px; border-radius: 8px; font-size: 14px; }
    .filters input:focus, .filters select:focus { outline: none; border-color: #38bdf8; box-shadow: 0 0 0 2px rgba(56, 189, 248, 0.2); }
    .btn { padding: 8px 16px; border-radius: 8px; font-size: 14px; font-weight: 500; cursor: pointer; border: none; transition: transform 0.05s, box-shadow 0.2s; }
    .btn-primary { background: linear-gradient(180deg, #3b82f6, #2563eb); color: #fff; }
    .btn-primary:hover { background: linear-gradient(180deg, #2563eb, #1d4ed8); box-shadow: 0 4px 12px rgba(37, 99, 235, 0.4); }
    .btn-copy-ips { background: linear-gradient(180deg, #10b981, #059669); color: #fff; }
    .btn-copy-ips:hover { background: linear-gradient(180deg, #059669, #047857); box-shadow: 0 4px 12px rgba(5, 150, 105, 0.4); }
    .sep { color: rgba(148, 163, 184, 0.4); font-weight: 300; user-select: none; }
    .download-hint { font-size: 13px; color: #94a3b8; padding: 10px 14px; background: rgba(15, 23, 42, 0.5); border-radius: 8px; border: 1px dashed rgba(148, 163, 184, 0.3); }
    .download-hint a { color: #38bdf8; text-decoration: none; font-weight: 500; }
    .download-hint a:hover { text-decoration: underline; }
    .table-wrap { overflow-x: auto; border-radius: 10px; border: 1px solid rgba(148, 163, 184, 0.12); }
    table { width: 100%; border-collapse: collapse; font-size: 13px; }
    th, td { padding: 10px 14px; text-align: left; border-bottom: 1px solid rgba(148, 163, 184, 0.08); }
    th { background: rgba(15, 23, 42, 0.7); color: #94a3b8; font-weight: 500; font-size: 11px; text-transform: uppercase; letter-spacing: 0.04em; position: sticky; top: 0; }
    tbody tr { transition: background 0.15s; }
    tbody tr:hover td { background: rgba(56, 189, 248, 0.06); }
    .type-click { color: #86efac; }
    .type-visit { color: #fcd34d; }
    .project { font-weight: 600; color: #a78bfa; max-width: 100px; }
    td { max-width: 200px; overflow: hidden; text-overflow: ellipsis; color: #cbd5e1; }
    .empty { color: #64748b; padding: 40px 24px; text-align: center; font-size: 15px; }
  </style>
</head>
<body>
  <div class="page">
    <div class="top-bar">
      <h1>Логи — кліки та візити</h1>
      <div class="nav">
        <a href="?<?php echo htmlspecialchars($navQs); ?>" class="<?php echo $filterType === null ? 'active' : ''; ?>">Всі</a>
        <span class="sep">|</span>
        <a href="?<?php echo htmlspecialchars(http_build_query(array_merge($navBase, ['type' => 'click']))); ?>" class="<?php echo $filterType === 'click' ? 'active' : ''; ?>">Кліки</a>
        <a href="?<?php echo htmlspecialchars(http_build_query(array_merge($navBase, ['type' => 'visit']))); ?>" class="<?php echo $filterType === 'visit' ? 'active' : ''; ?>">Візити</a>
        <span class="sep">|</span>
        <a href="<?php echo (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? '') . '/admin/'; ?>">← Дашборд</a>
      </div>
    </div>
    <div class="card">
      <div class="card-title">Фільтри</div>
      <div class="filters">
        <form method="get" style="display:flex;flex-wrap:wrap;align-items:flex-end;gap:12px;">
          <?php if ($filterProject): ?><input type="hidden" name="project" value="<?php echo htmlspecialchars($filterProject); ?>"><?php endif; ?>
          <?php if ($filterType): ?><input type="hidden" name="type" value="<?php echo htmlspecialchars($filterType); ?>"><?php endif; ?>
          <label>Від <input type="date" name="date_from" value="<?php echo $dateFrom !== null && $dateFrom !== '' ? htmlspecialchars($dateFrom) : ''; ?>" style="width:140px;"></label>
          <label>По <input type="date" name="date_to" value="<?php echo $dateTo !== null && $dateTo !== '' ? htmlspecialchars($dateTo) : ''; ?>" style="width:140px;"></label>
          <label>Text <input type="text" name="filter_text" value="<?php echo $filterText !== null ? htmlspecialchars($filterText) : ''; ?>" placeholder="фрагмент тексту" style="width:120px;"></label>
          <label>ID <input type="text" name="filter_id" value="<?php echo $filterId !== null ? htmlspecialchars($filterId) : ''; ?>" placeholder="фрагмент id" style="width:110px;"></label>
          <label>Href <input type="text" name="filter_href" value="<?php echo $filterHref !== null ? htmlspecialchars($filterHref) : ''; ?>" placeholder="фрагмент посилання" style="width:130px;"></label>
          <label>Classes <input type="text" name="filter_classes" value="<?php echo $filterClasses !== null ? htmlspecialchars($filterClasses) : ''; ?>" placeholder="фрагмент класів" style="width:120px;"></label>
          <label>Page <input type="text" name="filter_page" value="<?php echo $filterPage !== null ? htmlspecialchars($filterPage) : ''; ?>" placeholder="фрагмент URL" style="width:160px;"></label>
          <input type="hidden" name="limit" value="<?php echo (int) $limit; ?>">
          <button type="submit" class="btn btn-primary">Застосувати</button>
        </form>
        <?php if (!empty($allRows)): ?>
          <span class="sep">|</span>
          <button type="button" class="btn btn-copy-ips" data-ips="<?php echo htmlspecialchars($ipsForCopy); ?>">Копіювати IP</button>
        <?php endif; ?>
      </div>
      <div style="display:flex;flex-wrap:wrap;align-items:center;gap:12px;margin-top:16px;">
        <form method="get" style="display:inline-flex;align-items:center;gap:8px;">
          <?php if ($filterType): ?><input type="hidden" name="type" value="<?php echo htmlspecialchars($filterType); ?>"><?php endif; ?>
          <?php if ($dateFrom !== null && $dateFrom !== ''): ?><input type="hidden" name="date_from" value="<?php echo htmlspecialchars($dateFrom); ?>"><?php endif; ?>
          <?php if ($dateTo !== null && $dateTo !== ''): ?><input type="hidden" name="date_to" value="<?php echo htmlspecialchars($dateTo); ?>"><?php endif; ?>
          <?php if ($filterId !== null && $filterId !== ''): ?><input type="hidden" name="filter_id" value="<?php echo htmlspecialchars($filterId); ?>"><?php endif; ?>
          <?php if ($filterHref !== null && $filterHref !== ''): ?><input type="hidden" name="filter_href" value="<?php echo htmlspecialchars($filterHref); ?>"><?php endif; ?>
          <?php if ($filterText !== null && $filterText !== ''): ?><input type="hidden" name="filter_text" value="<?php echo htmlspecialchars($filterText); ?>"><?php endif; ?>
          <?php if ($filterClasses !== null && $filterClasses !== ''): ?><input type="hidden" name="filter_classes" value="<?php echo htmlspecialchars($filterClasses); ?>"><?php endif; ?>
          <?php if ($filterPage !== null && $filterPage !== ''): ?><input type="hidden" name="filter_page" value="<?php echo htmlspecialchars($filterPage); ?>"><?php endif; ?>
          <input type="hidden" name="limit" value="<?php echo (int) $limit; ?>">
          <label style="flex-direction:row;align-items:center;">Проєкт <select name="project" onchange="this.form.submit()" style="width:140px;margin-left:6px;"><option value="">— всі —</option><?php foreach (array_keys($projects) as $p): ?><option value="<?php echo htmlspecialchars($p); ?>" <?php echo $filterProject === $p ? 'selected' : ''; ?>><?php echo htmlspecialchars($p); ?></option><?php endforeach; ?></select></label>
        </form>
        <span class="sep">|</span>
        <form method="get" style="display:inline-flex;align-items:center;gap:8px;">
          <?php if ($filterProject): ?><input type="hidden" name="project" value="<?php echo htmlspecialchars($filterProject); ?>"><?php endif; ?>
          <?php if ($filterType): ?><input type="hidden" name="type" value="<?php echo htmlspecialchars($filterType); ?>"><?php endif; ?>
          <?php if ($dateFrom !== null && $dateFrom !== ''): ?><input type="hidden" name="date_from" value="<?php echo htmlspecialchars($dateFrom); ?>"><?php endif; ?>
          <?php if ($dateTo !== null && $dateTo !== ''): ?><input type="hidden" name="date_to" value="<?php echo htmlspecialchars($dateTo); ?>"><?php endif; ?>
          <?php if ($filterId !== null && $filterId !== ''): ?><input type="hidden" name="filter_id" value="<?php echo htmlspecialchars($filterId); ?>"><?php endif; ?>
          <?php if ($filterHref !== null && $filterHref !== ''): ?><input type="hidden" name="filter_href" value="<?php echo htmlspecialchars($filterHref); ?>"><?php endif; ?>
          <?php if ($filterText !== null && $filterText !== ''): ?><input type="hidden" name="filter_text" value="<?php echo htmlspecialchars($filterText); ?>"><?php endif; ?>
          <?php if ($filterClasses !== null && $filterClasses !== ''): ?><input type="hidden" name="filter_classes" value="<?php echo htmlspecialchars($filterClasses); ?>"><?php endif; ?>
          <?php if ($filterPage !== null && $filterPage !== ''): ?><input type="hidden" name="filter_page" value="<?php echo htmlspecialchars($filterPage); ?>"><?php endif; ?>
          <label style="flex-direction:row;align-items:center;">Показати <input type="number" name="limit" value="<?php echo (int) $limit; ?>" min="50" max="2000" step="50" style="width:72px;margin:0 6px;" onchange="this.form.submit()"> записів</label>
        </form>
        <span class="sep">|</span>
        <span class="download-hint">Скачати Excel — тільки в <a href="<?php echo (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? '') . '/admin/'; ?>">Дашборді</a></span>
      </div>
    </div>
  <?php if (empty($allRows)): ?>
    <p class="empty">Немає записів<?php echo $filterProject ? ' у проєкті ' . htmlspecialchars($filterProject) : ''; ?>.</p>
  <?php else: ?>
    <div class="table-wrap">
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
  </div>
  <script>
    document.querySelectorAll('.btn-copy-ips').forEach(function(btn) {
      btn.addEventListener('click', function() {
        var ips = this.getAttribute('data-ips') || '';
        if (!ips.trim()) return;
        if (navigator.clipboard && navigator.clipboard.writeText) {
          navigator.clipboard.writeText(ips).then(function() { btn.textContent = 'Скопійовано'; setTimeout(function(){ btn.textContent = 'Копіювати IP'; }, 2000); });
        } else {
          var ta = document.createElement('textarea'); ta.value = ips; document.body.appendChild(ta); ta.select(); document.execCommand('copy'); document.body.removeChild(ta);
          btn.textContent = 'Скопійовано'; setTimeout(function(){ btn.textContent = 'Копіювати IP'; }, 2000);
        }
      });
    });
  </script>
</body>
</html>
