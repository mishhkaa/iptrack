<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Content-Type: text/plain; charset=UTF-8");

date_default_timezone_set('Europe/Kyiv');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
  http_response_code(200);
  exit;
}

$data = json_decode(file_get_contents("php://input"), true);
if (!$data) exit;

require __DIR__ . '/../inc/db.php';

if (iptrack_insert_event($pdo, $data, basename(__DIR__))) {
  echo "ok";
  exit;
}

http_response_code(500);
echo "db_error";