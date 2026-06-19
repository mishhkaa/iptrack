<?php
/**
 * SQLite storage helpers: paths, directory permissions and user-facing DB errors.
 */
function iptrack_php_process_user(): string {
  if (function_exists('posix_geteuid')) {
    $info = posix_getpwuid(posix_geteuid());
    if (is_array($info) && !empty($info['name'])) {
      return (string) $info['name'];
    }
  }
  $user = get_current_user();
  return $user !== '' ? $user : 'www-data';
}

function iptrack_storage_fix_hint(string $relativeDataDir = 'admin/data'): string {
  $user = iptrack_php_process_user();
  return 'sudo chown -R ' . $user . ':' . $user . ' ' . $relativeDataDir . ' && sudo chmod -R u+rwX ' . $relativeDataDir;
}

function iptrack_resolve_sqlite_paths(string $defaultDataDir): array {
  $defaultDataDir = rtrim($defaultDataDir, '/');
  $defaultPath = $defaultDataDir . '/iptrack.db';
  $localConfig = $defaultDataDir . '/db.local.php';

  if (is_file($localConfig)) {
    $cfg = @include $localConfig;
    if (is_array($cfg) && !empty($cfg['db_path']) && is_string($cfg['db_path'])) {
      $dbPath = trim($cfg['db_path']);
      if ($dbPath !== '') {
        return [
          'dir' => dirname($dbPath),
          'path' => $dbPath,
          'custom' => true,
        ];
      }
    }
  }

  return [
    'dir' => $defaultDataDir,
    'path' => $defaultPath,
    'custom' => false,
  ];
}

function iptrack_prepare_sqlite_dir(string $dataDir): void {
  $dataDir = rtrim($dataDir, '/');
  if (!is_dir($dataDir)) {
    if (!@mkdir($dataDir, 0775, true) && !is_dir($dataDir)) {
      return;
    }
    @chmod($dataDir, 0775);
  }
}

function iptrack_path_permissions(string $path): ?array {
  if (!file_exists($path)) {
    return null;
  }
  $perms = @fileperms($path);
  if ($perms === false) {
    return null;
  }
  $owner = function_exists('posix_getpwuid') ? (posix_getpwuid(@fileowner($path))['name'] ?? '?') : '?';
  $group = function_exists('posix_getgrgid') ? (posix_getgrgid(@filegroup($path))['name'] ?? '?') : '?';
  return [
    'mode' => substr(sprintf('%o', $perms), -4),
    'owner' => $owner,
    'group' => $group,
    'writable' => is_writable($path),
  ];
}

function iptrack_sqlite_storage_error(string $dataDir, string $dbPath): ?string {
  $dataDir = rtrim($dataDir, '/');
  $hint = iptrack_storage_fix_hint();
  $phpUser = iptrack_php_process_user();
  $diag = ' Діагностика: /admin/db_diag.php';

  if (!is_dir($dataDir)) {
    return 'Папка для бази не існує і не може бути створена. PHP працює під користувачем «' . $phpUser . '».' . $diag;
  }
  if (!is_writable($dataDir)) {
    return 'Папка бази недоступна для запису (PHP: «' . $phpUser . '»). SQLite потребує запису в папку (файли -wal/-shm). На сервері: ' . $hint . '.' . $diag;
  }
  if (is_file($dbPath) && !is_writable($dbPath)) {
    return 'Файл бази лише для читання (PHP: «' . $phpUser . '»). На сервері: ' . $hint . '.' . $diag;
  }
  return null;
}

function iptrack_format_pdo_error(PDOException $e): string {
  $msg = $e->getMessage();
  $hint = iptrack_storage_fix_hint();
  $phpUser = iptrack_php_process_user();
  $diag = ' Деталі: /admin/db_diag.php';

  if (stripos($msg, 'readonly database') !== false || stripos($msg, 'read-only') !== false) {
    return 'База даних лише для читання (PHP: «' . $phpUser . '»). На сервері: ' . $hint . '.' . $diag;
  }
  if (stripos($msg, 'unable to open database file') !== false) {
    return 'Не вдалося відкрити базу — перевір права (PHP: «' . $phpUser . '»). ' . $hint . '.' . $diag;
  }
  if (stripos($msg, 'database is locked') !== false) {
    return 'База даних тимчасово заблокована. Спробуйте ще раз через кілька секунд.';
  }
  return 'Помилка бази даних: ' . $msg;
}

function iptrack_append_pdo_open_hint(string $msg): string {
  if (stripos($msg, 'could not find driver') !== false) {
    return $msg . ' — увімкни розширення PHP pdo_sqlite (див. DEPLOY.md).';
  }
  if (stripos($msg, 'unable to open database file') !== false) {
    return $msg . ' — ' . iptrack_storage_fix_hint();
  }
  if (stripos($msg, 'readonly database') !== false || stripos($msg, 'read-only') !== false) {
    return $msg . ' — ' . iptrack_storage_fix_hint();
  }
  return $msg;
}
