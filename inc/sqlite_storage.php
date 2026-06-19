<?php
/**
 * SQLite storage helpers: directory permissions and user-facing DB errors.
 */
function iptrack_prepare_sqlite_dir(string $dataDir): void {
  $dataDir = rtrim($dataDir, '/');
  if (!is_dir($dataDir)) {
    if (!@mkdir($dataDir, 0775, true) && !is_dir($dataDir)) {
      return;
    }
    @chmod($dataDir, 0775);
  }
}

function iptrack_sqlite_storage_error(string $dataDir, string $dbPath): ?string {
  $dataDir = rtrim($dataDir, '/');
  if (!is_dir($dataDir)) {
    return 'Папка admin/data не існує і не може бути створена. Перевірте права на admin/.';
  }
  if (!is_writable($dataDir)) {
    return 'Папка admin/data недоступна для запису. SQLite потребує запису в папку (файли -wal/-shm). На сервері: sudo chown -R www-data:www-data admin/data && sudo chmod -R u+rwX,g+rwX admin/data';
  }
  if (is_file($dbPath) && !is_writable($dbPath)) {
    return 'Файл admin/data/iptrack.db лише для читання. На сервері: sudo chown www-data:www-data admin/data/iptrack.db && sudo chmod 664 admin/data/iptrack.db';
  }
  return null;
}

function iptrack_format_pdo_error(PDOException $e): string {
  $msg = $e->getMessage();
  if (stripos($msg, 'readonly database') !== false || stripos($msg, 'read-only') !== false) {
    return 'База даних лише для читання. На сервері (SSH): sudo chown -R www-data:www-data admin/data && sudo chmod -R u+rwX,g+rwX admin/data';
  }
  if (stripos($msg, 'unable to open database file') !== false) {
    return 'Не вдалося відкрити admin/data/iptrack.db — перевір права на папку admin/data (chown/chmod).';
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
    return $msg . ' — перевір права на папку admin/data (chown/chmod).';
  }
  if (stripos($msg, 'readonly database') !== false || stripos($msg, 'read-only') !== false) {
    return $msg . ' — sudo chown -R www-data:www-data admin/data && sudo chmod -R u+rwX,g+rwX admin/data';
  }
  return $msg;
}
