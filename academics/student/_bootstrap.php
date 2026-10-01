<?php

session_start();
require_once __DIR__ . '/../../db.php';

if (empty($_SESSION['logged_in']) || strtoupper((string)($_SESSION['role'] ?? '')) !== 'STUDENT') {
    header('Location: ../../login.php');
    exit;
}

$userId = (int)($_SESSION['user_id'] ?? 0);
$studentStatement = $conn->prepare(
    'SELECT s.student_id, s.adm_number, s.full_name, s.class_id, s.profile_pic,
            c.class_name, c.stream_name
     FROM students s
     INNER JOIN classes c ON c.class_id = s.class_id
     WHERE s.user_id = ? AND s.status = ?
     LIMIT 1'
);
$studentStatement->execute([$userId, 'ACTIVE']);
$student = $studentStatement->fetch();

if (!$student) {
    http_response_code(404);
    exit('Student profile is not linked to this account.');
}

$studentId = (int)$student['student_id'];
$classId = (int)$student['class_id'];
$currentYear = (int)date('Y');
$currentTerm = 'TERM_' . min(3, max(1, (int)ceil((int)date('n') / 4)));

if (empty($_SESSION['academic_csrf'])) {
    $_SESSION['academic_csrf'] = bin2hex(random_bytes(32));
}

$csrfToken = $_SESSION['academic_csrf'];

function studentCsrfValid(): bool
{
    return isset($_POST['csrf_token'], $_SESSION['academic_csrf'])
        && hash_equals($_SESSION['academic_csrf'], (string)$_POST['csrf_token']);
}

function studentRedirect(string $route): never
{
    header('Location: ' . $route);
    exit;
}

function studentFlash(string $message, string $type = 'success'): void
{
    $_SESSION['academic_flash'] = ['message' => $message, 'type' => $type];
}

function studentTakeFlash(): ?array
{
    $flash = $_SESSION['academic_flash'] ?? null;
    unset($_SESSION['academic_flash']);
    return $flash;
}

function studentCount(PDO $conn, string $sql, array $parameters): int
{
    try {
        $statement = $conn->prepare($sql);
        $statement->execute($parameters);
        return (int)$statement->fetchColumn();
    } catch (PDOException $exception) {
        return 0;
    }
}

function studentEscape(mixed $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}