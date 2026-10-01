<?php

session_start();
require_once __DIR__ . '/../../db.php';

if (empty($_SESSION['logged_in']) || strtoupper((string)($_SESSION['role'] ?? '')) !== 'TEACHER') {
    header('Location: ../../login.php');
    exit;
}

$userId = (int)($_SESSION['user_id'] ?? 0);
$teacherStatement = $conn->prepare(
    'SELECT teacher_id, full_name, email, phone_number, profile_pic
     FROM teachers
     WHERE user_id = ?
     LIMIT 1'
);
$teacherStatement->execute([$userId]);
$teacher = $teacherStatement->fetch();

if (!$teacher) {
    http_response_code(404);
    exit('Teacher profile is not linked to this account.');
}

$teacherId = (int)$teacher['teacher_id'];
$currentYear = (int)date('Y');

if (empty($_SESSION['academic_csrf'])) {
    $_SESSION['academic_csrf'] = bin2hex(random_bytes(32));
}

$csrfToken = $_SESSION['academic_csrf'];

function teacherCsrfValid(): bool
{
    return isset($_POST['csrf_token'], $_SESSION['academic_csrf'])
        && hash_equals($_SESSION['academic_csrf'], (string)$_POST['csrf_token']);
}

function teacherRedirect(string $route): never
{
    header('Location: ' . $route);
    exit;
}

function teacherFlash(string $message, string $type = 'success'): void
{
    $_SESSION['academic_flash'] = ['message' => $message, 'type' => $type];
}

function teacherTakeFlash(): ?array
{
    $flash = $_SESSION['academic_flash'] ?? null;
    unset($_SESSION['academic_flash']);
    return $flash;
}

function teacherCount(PDO $conn, string $sql, array $parameters): int
{
    try {
        $statement = $conn->prepare($sql);
        $statement->execute($parameters);
        return (int)$statement->fetchColumn();
    } catch (PDOException $exception) {
        return 0;
    }
}

function teacherEscape(mixed $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}