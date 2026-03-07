<?php
/**
 * Перевірка відстежуваних посилань: доступність, SSL.
 * Викликається cron кожні 60 хв або кнопкою "Перевірити зараз" в адмінці.
 * Cron: 0 * * * * cd /path/to/public_html/admin && php check_monitored.php
 */
$isCli = php_sapi_name() === 'cli';
if (!$isCli) {
  session_start();
  require __DIR__ . '/db.php';
  if (empty($_SESSION['admin_logged'])) {
    header('HTTP/1.1 403 Forbidden');
    exit('Forbidden');
  }
} else {
  define('ADMIN_INIT', true);
  require __DIR__ . '/db.php';
}

$rows = getMonitoredUrlsToCheck($pdo);
list($token, $chatId) = getTelegramCredentials($pdo);

function checkUrl(string $url): array {
  $url = trim($url);
  if ($url === '') return ['status' => 'error', 'error' => 'Порожня URL'];
  if (!preg_match('#^https?://#i', $url)) $url = 'https://' . $url;
  $parsed = parse_url($url);
  $scheme = $parsed['scheme'] ?? 'https';
  $host = $parsed['host'] ?? '';
  if ($host === '') return ['status' => 'error', 'error' => 'Невірна URL'];

  $ctx = stream_context_create([
    'http' => [
      'timeout' => 15,
      'follow_location' => 1,
      'ignore_errors' => true,
      'user_agent' => 'IPTrack-Monitor/1.0',
    ],
    'ssl' => [
      'verify_peer' => true,
      'verify_peer_name' => true,
    ],
  ]);

  $body = @file_get_contents($url, false, $ctx);
  if ($body === false) {
    $err = error_get_last();
    $errStr = $err['message'] ?? 'Не вдалося підключитися';
    if (stripos($errStr, 'SSL') !== false || stripos($errStr, 'certificate') !== false) {
      return ['status' => 'ssl_error', 'error' => $errStr];
    }
    if (stripos($errStr, 'timeout') !== false || stripos($errStr, 'timed out') !== false) {
      return ['status' => 'timeout', 'error' => 'Таймаут'];
    }
    return ['status' => 'down', 'error' => $errStr];
  }

  if ($scheme === 'https' && $host !== '') {
    $sslCtx = stream_context_create([
      'ssl' => [
        'capture_peer_cert' => true,
        'verify_peer' => true,
        'verify_peer_name' => true,
      ],
    ]);
    $port = $parsed['port'] ?? 443;
    $sslUrl = "ssl://{$host}:{$port}";
    $sock = @stream_socket_client($sslUrl, $errNum, $errStr, 10, STREAM_CLIENT_CONNECT, $sslCtx);
    if (!$sock) {
      return ['status' => 'ssl_error', 'error' => $errStr ?: 'Помилка SSL'];
    }
    $params = stream_context_get_params($sock);
    fclose($sock);
    if (!empty($params['options']['ssl']['peer_certificate'])) {
      $cert = openssl_x509_parse($params['options']['ssl']['peer_certificate']);
      if ($cert && isset($cert['validTo_time_t']) && $cert['validTo_time_t'] < time()) {
        return ['status' => 'ssl_expired', 'error' => 'SSL сертифікат протерміновано'];
      }
    }
  }

  return ['status' => 'ok', 'error' => null];
}

function sendTelegram(string $token, string $chatId, string $text): bool {
  if ($token === '' || $chatId === '') return false;
  $url = 'https://api.telegram.org/bot' . rawurlencode($token) . '/sendMessage';
  $payload = json_encode([
    'chat_id' => $chatId,
    'text' => $text,
    'disable_web_page_preview' => true,
  ]);
  $ctx = stream_context_create([
    'http' => [
      'method' => 'POST',
      'header' => "Content-Type: application/json\r\nContent-Length: " . strlen($payload),
      'content' => $payload,
      'timeout' => 10,
    ],
  ]);
  $r = @file_get_contents($url, false, $ctx);
  return $r !== false && strpos($r, '"ok":true') !== false;
}

foreach ($rows as $r) {
  $id = (int) $r['id'];
  $name = $r['name'];
  $url = $r['url'];
  $notifySentAt = $r['notify_sent_at'];

  $result = checkUrl($url);
  $status = $result['status'];
  $error = $result['error'] ?? '';

  $notifySent = false;
  if ($status !== 'ok' && $status !== 'pending') {
    $shouldNotify = empty($notifySentAt) || (time() - strtotime($notifySentAt) > 86400);
    if ($shouldNotify && $token !== '' && $chatId !== '') {
      $text = "⚠️ Моніторинг IPTrack\n\n";
      $text .= "Сайт: " . $name . "\n";
      $text .= "URL: " . $url . "\n";
      $text .= "Статус: " . $status . "\n";
      if ($error !== '') $text .= "Помилка: " . $error . "\n";
      if (sendTelegram($token, $chatId, $text)) {
        $notifySent = true;
      }
    }
  }

  $notifyAt = ($status === 'ok') ? null : ($notifySent ? date('Y-m-d H:i:s') : $notifySentAt);
  $pdo->prepare("UPDATE monitored_urls SET last_checked_at = datetime('now'), status = ?, last_error = ?, notify_sent_at = ? WHERE id = ?")
    ->execute([$status, $error, $notifyAt, $id]);
}

if ($isCli) {
  echo "Check done.\n";
  exit(0);
}

$baseUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? '');
header('Location: ' . $baseUrl . '/admin/?tab=monitoring&msg=' . urlencode('Перевірку виконано.'));
exit;
