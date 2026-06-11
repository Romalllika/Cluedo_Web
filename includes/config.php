<?php
session_start();

// include '../.env';

$dbName = 'cluedo_web';
$dbHost = 'localhost';
// $dbHost = $_ENV['DB_HOST'];
// $dbName = $_ENV['DB_NAME'];
// $dbUser = $_ENV['DB_USER'];
// $dbPass = $_ENV['DB_PASS'];

if (PHP_OS_FAMILY === 'Darwin') {
    // Mac / AMPPS
    $dbUser = 'root';
    $dbPass = 'mysql';
} else {
    // Debian server
    $dbUser = 'webuser';
    $dbPass = '12345';
}

function db(): PDO {
    global $dbHost, $dbName, $dbUser, $dbPass;

    static $pdo = null;

    if ($pdo === null) {
        $dsn = 'mysql:host=' . $dbHost . ';dbname=' . $dbName . ';charset=utf8mb4';

        $pdo = new PDO($dsn, $dbUser, $dbPass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
    }

    return $pdo;
}

function current_user_id(): ?int {
    return $_SESSION['user_id'] ?? null;
}

function require_auth(): void {
    if (!current_user_id()) {
        header('Location: index.php');
        exit;
    }
}

function json_out($data): void {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function h($s): string {
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return (string) $_SESSION['csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . h(csrf_token()) . '">';
}

function csrf_check(): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        return;
    }

    $token = (string) ($_POST['csrf_token'] ?? '');

    if ($token === '' || empty($_SESSION['csrf_token']) || !hash_equals((string) $_SESSION['csrf_token'], $token)) {
        http_response_code(403);
        echo 'Некорректный CSRF-токен';
        exit;
    }
}