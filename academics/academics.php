<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();

/*
|--------------------------------------------------------------------------
| ACADEMICS MAIN ROUTER
|--------------------------------------------------------------------------
*/

if (
    !isset($_SESSION['logged_in']) ||
    $_SESSION['logged_in'] !== true
) {
    header("Location: ../login.php");
    exit;
}

if (!isset($_SESSION['role']) || empty($_SESSION['role'])) {
    die("ERROR: User role is not set in the session.");
}

$role = strtoupper(trim((string) $_SESSION['role']));

switch ($role) {

    case 'PARENT':

        header("Location: parent/index.php");
        exit;

    case 'STUDENT':

        header("Location: student/index.php");
        exit;

    case 'TEACHER':

        header("Location: teacher/index.php");
        exit;

    case 'SCHOOL_ADMIN':

        header("Location: admin/index.php");
        exit;

    case 'SUPER_ADMIN':

        header("Location: admin/index.php");
        exit;

    default:

        die(
            "ERROR: Your account role is not supported.<br>" .
            "Current role: " . htmlspecialchars($role)
        );
}