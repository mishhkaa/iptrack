<?php
if (!defined('ADMIN_INIT')) {
  define('ADMIN_INIT', true);
}

$dataDir = __DIR__ . '/data';
if (!is_dir($dataDir)) {
  mkdir($dataDir, 0755, true);
}
$dbPath = $dataDir . '/iptrack.db';

try {
  $pdo = new PDO('sqlite:' . $dbPath, null, null, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
  ]);
} catch (PDOException $e) {
  die('DB error: ' . htmlspecialchars($e->getMessage()));
}

$pdo->exec("
  CREATE TABLE IF NOT EXISTS auth (
    id INTEGER PRIMARY KEY CHECK (id = 1),
    password_hash TEXT NOT NULL
  )
");
$pdo->exec("
  CREATE TABLE IF NOT EXISTS projects (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL,
    slug TEXT NOT NULL UNIQUE,
    created_at TEXT NOT NULL DEFAULT (datetime('now'))
  )
");

function getPasswordHash(PDO $pdo) {
  $st = $pdo->query("SELECT password_hash FROM auth WHERE id = 1");
  $row = $st->fetch(PDO::FETCH_ASSOC);
  return $row ? $row['password_hash'] : null;
}

function setPasswordHash(PDO $pdo, string $hash) {
  $pdo->prepare("INSERT OR REPLACE INTO auth (id, password_hash) VALUES (1, ?)")->execute([$hash]);
}

function getAllProjects(PDO $pdo) {
  return $pdo->query("SELECT id, name, slug, created_at FROM projects ORDER BY created_at DESC")->fetchAll(PDO::FETCH_ASSOC);
}

function addProject(PDO $pdo, string $name, string $slug) {
  $pdo->prepare("INSERT INTO projects (name, slug) VALUES (?, ?)")->execute([$name, $slug]);
}

function deleteProject(PDO $pdo, string $slug) {
  $pdo->prepare("DELETE FROM projects WHERE slug = ?")->execute([$slug]);
}

function projectExists(PDO $pdo, string $slug) {
  $st = $pdo->prepare("SELECT 1 FROM projects WHERE slug = ?");
  $st->execute([$slug]);
  return (bool) $st->fetch();
}
