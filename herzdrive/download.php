<?php
require __DIR__ . '/../inc/export.php';

session_start();
if (empty($_SESSION['admin_logged'])) {
  http_response_code(403);
  header('Content-Type: text/plain; charset=UTF-8');
  exit('Доступ заборонено. Скачування лише через Дашборд.');
}

iptrack_export_project_excel($pdo, 'herzdrive');