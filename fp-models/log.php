<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Content-Type: text/plain; charset=UTF-8");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
  http_response_code(200);
  exit;
}

$data = json_decode(file_get_contents("php://input"), true);
if (!$data) exit;

$ip =
  $_SERVER['HTTP_CF_CONNECTING_IP'] ??
  $_SERVER['HTTP_X_FORWARDED_FOR'] ??
  $_SERVER['REMOTE_ADDR'];

$file = __DIR__ . "/clicks.csv";
$isNew = !file_exists($file);

$fp = fopen($file, "a");

if ($isNew) {
  fputcsv($fp, [
    "date",
    "ip",
    "tag",
    "text",
    "href",
    "id",
    "classes",
    "page"
  ]);
}

fputcsv($fp, [
  date("d.m.Y H:i:s"),
  $ip,
  $data['tag'] ?? '',
  $data['text'] ?? '',
  $data['href'] ?? '',
  $data['id'] ?? '',
  $data['classes'] ?? '',
  $data['page'] ?? ''
]);

fclose($fp);

echo "ok";
