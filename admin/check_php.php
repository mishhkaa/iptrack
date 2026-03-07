<?php
/**
 * Одноразовий скрипт: показати версію PHP і чи є pdo_sqlite.
 * Видали після перевірки: rm admin/check_php.php
 */
header('Content-Type: text/plain; charset=UTF-8');
echo 'PHP version: ' . PHP_VERSION . "\n";
echo 'pdo_sqlite loaded: ' . (extension_loaded('pdo_sqlite') ? 'yes' : 'no') . "\n";
echo 'PHP binary (if CLI): ' . (defined('PHP_BINARY') ? PHP_BINARY : 'n/a') . "\n";
