<?php

session_start();

/*
|--------------------------------------------------------------------------
| DATABASE
|--------------------------------------------------------------------------
*/

require_once "db.php";
require_once __DIR__ . "/message_helpers.php";

/*
|--------------------------------------------------------------------------
| SHOW PHP ERRORS WHILE DEVELOPING
|--------------------------------------------------------------------------
| Remove/comment these 3 lines after the system is working.
|--------------------------------------------------------------------------
*/

error_reporting(E_ALL);
ini_set("display_errors", "1");
ini_set("display_startup_errors", "1");

/*
|--------------------------------------------------------------------------
| CHECK LOGIN
|--------------------------------------------------------------------------
*/

if (
    !isset($_SESSION["logged_in"]) ||
    $_SESSION["logged_in"] !== true
) {
    header("Location: login.php");
    exit;
}

/*
|--------------------------------------------------------------------------
| MAKE SURE USER IS A PARENT
|--------------------------------------------------------------------------
*/

if (
    !isset($_SESSION["role"]) ||
    strtoupper((string)$_SESSION["role"]) !== "PARENT"
) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION["user_id"] ?? null;
$unreadMessageCount = unreadMessageCount($conn, (int) $user_id);

if (!$user_id) {
    session_destroy();
    header("Location: login.php");
    exit;
}

$user = null;
$children = [];

/*
|--------------------------------------------------------------------------
| GET PARENT INFORMATION
|--------------------------------------------------------------------------
*/


$stmt = $conn->prepare("
    SELECT
        u.email,
        u.phone_number,
        u.role,
        p.parent_id,
        p.full_name,
        p.national_id,
        p.relationship,
        p.profile_pic
    FROM users u
    INNER JOIN parents p
        ON u.user_id = p.user_id
    WHERE u.user_id = ?
    LIMIT 1
");

$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

/*
|--------------------------------------------------------------------------
| MAKE SURE PARENT PROFILE EXISTS
|--------------------------------------------------------------------------
*/

if (!$user) {
    session_destroy();
    header("Location: login.php");
    exit;
}

$parent_id = $user["parent_id"];

/*
|--------------------------------------------------------------------------
| GET ONLY CHILDREN LINKED TO THIS PARENT
|--------------------------------------------------------------------------
*/

$stmt = $conn->prepare("
    SELECT
        s.student_id,
        s.adm_number,
        s.full_name,
        s.gender,
        s.date_of_birth,
        s.status,
        c.class_name,
        c.stream_name
    FROM parent_student_map psm
    INNER JOIN students s
        ON psm.student_id = s.student_id
    INNER JOIN classes c
        ON s.class_id = c.class_id
    WHERE psm.parent_id = ?
    ORDER BY s.full_name ASC
");

$stmt->execute([$parent_id]);
$children = $stmt->fetchAll(PDO::FETCH_ASSOC);

/*
|--------------------------------------------------------------------------
| GET ACADEMIC, ATTENDANCE AND FEE INFORMATION
|--------------------------------------------------------------------------
*/

foreach ($children as &$child) {

    /*
    |----------------------------------------------------------------------
    | ACADEMIC PROGRESS
    |----------------------------------------------------------------------
    */

    $stmt = $conn->prepare("
        SELECT
            COUNT(*) AS total_subjects,
            COALESCE(AVG(score), 0) AS average_score
        FROM exam_results
        WHERE student_id = ?
    ");

    $stmt->execute([$child["student_id"]]);
    $academic = $stmt->fetch(PDO::FETCH_ASSOC);

    $child["total_subjects"] = (int)($academic["total_subjects"] ?? 0);
    $child["average_score"] = (float)($academic["average_score"] ?? 0);

    /*
    |----------------------------------------------------------------------
    | ATTENDANCE
    |----------------------------------------------------------------------
    */

    $stmt = $conn->prepare("
        SELECT
            COUNT(*) AS total_days,
            SUM(
                CASE
                    WHEN status = 'PRESENT'
                    THEN 1
                    ELSE 0
                END
            ) AS present_days
        FROM attendance
        WHERE student_id = ?
    ");

    $stmt->execute([$child["student_id"]]);
    $attendance = $stmt->fetch(PDO::FETCH_ASSOC);

    $total_days = (int)($attendance["total_days"] ?? 0);
    $present_days = (int)($attendance["present_days"] ?? 0);

    if ($total_days > 0) {
        $child["attendance_percentage"] =
            round(($present_days / $total_days) * 100, 1);
    } else {
        $child["attendance_percentage"] = 0;
    }

    /*
    |----------------------------------------------------------------------
    | FEES
    |----------------------------------------------------------------------
    */

    $stmt = $conn->prepare("
        SELECT
            total_billed,
            total_paid,
            current_balance
        FROM fee_accounts
        WHERE student_id = ?
        LIMIT 1
    ");

    $stmt->execute([$child["student_id"]]);
    $fees = $stmt->fetch(PDO::FETCH_ASSOC);

    $child["total_billed"] = (float)($fees["total_billed"] ?? 0);
    $child["total_paid"] = (float)($fees["total_paid"] ?? 0);
    $child["current_balance"] = (float)($fees["current_balance"] ?? 0);
}

unset($child);

/*
|--------------------------------------------------------------------------
| PARENT NAME
|--------------------------------------------------------------------------
*/

$parent_name = !empty($user["full_name"])
    ? $user["full_name"]
    : "Parent";

/*
|--------------------------------------------------------------------------
| PROFILE PICTURE
|--------------------------------------------------------------------------
|
| The profile.php saves the picture in:
| parents.profile_pic
|
*/

$profile_picture = '';

if (!empty($user["profile_pic"])) {
    $profile_picture = trim($user["profile_pic"]);
}

/*
|--------------------------------------------------------------------------
| CHECK PROFILE PICTURE PATH
|--------------------------------------------------------------------------
*/

$profile_picture_url = '';

if (!empty($profile_picture)) {

    $profile_picture_url = preg_replace(
        '#^\./#',
        '',
        $profile_picture
    );

    $profile_picture_url = ltrim($profile_picture_url, '/');

    $profile_picture_file = __DIR__ . '/' . $profile_picture_url;

    if (!file_exists($profile_picture_file)) {
        $profile_picture_url = '';
    }
}

/*
|--------------------------------------------------------------------------
| INITIALS
|--------------------------------------------------------------------------
*/

$name_parts = preg_split('/\s+/', trim($parent_name));

$initials = "";

if (!empty($name_parts[0])) {
    $initials .= strtoupper(substr($name_parts[0], 0, 1));
}

if (
    count($name_parts) > 1 &&
    !empty($name_parts[count($name_parts) - 1])
) {
    $initials .= strtoupper(
        substr($name_parts[count($name_parts) - 1], 0, 1)
    );
}

if ($initials === "") {
    $initials = "P";
}

/*
|--------------------------------------------------------------------------
| SMALL HELPER
|--------------------------------------------------------------------------
*/

function e($value): string
{
    return htmlspecialchars(
        (string)($value ?? ""),
        ENT_QUOTES,
        "UTF-8"
    );
}

?>

<!DOCTYPE html>

<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Parent Dashboard - MySchool</title>

    <style>

        * {
            box-sizing: border-box;
        }

        html {
            scroll-behavior: smooth;
        }

        body {
            margin: 0;
            font-family: "Segoe UI", Arial, sans-serif;
            background: #eef3f8;
            color: #132f4c;
            font-size: 16px;
        }

        a {
            text-decoration: none;
        }

        /* LAYOUT */

        .app {
            display: flex;
            min-height: 100vh;
        }

        /* SIDEBAR */

        .sidebar {
            width: 235px;
           background: linear-gradient(180deg,#0b1f3a,#123d6a);
            color: white;
    
            position: fixed;
            left: 0;
            top: 0;
            bottom: 0;
            display: flex;
            flex-direction: column;
            padding: 20px 15px;
            z-index: 100;
        }

        .brand {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 10px 7px 28px;
        }

        .brand-icon {
            width: 40px;
            height: 40px;
            border-radius: 12px;
            background: rgba(255,255,255,0.15);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 21px;
        }

        .brand-name {
            font-size: 19px;
            font-weight: 800;
        }

        .menu-title {
            color: rgba(255,255,255,0.48);
            font-size: 10px;
            font-weight: 800;
            letter-spacing: 1px;
            padding: 0 10px 10px;
            text-transform: uppercase;
        }

        .nav {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }

        .nav a,
        .nav-toggle {
            color: rgba(255,255,255,0.82);
            padding: 12px 13px;
            border-radius: 11px;
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 14px;
            font-weight: 600;
            transition: 0.2s ease;
        }

        .nav a:hover,
        .nav-toggle:hover {
            background: rgba(255,255,255,0.09);
            color: white;
        }

        .nav a.active {
            background: rgba(255,255,255,0.14);
            color: white;
            border-left: 3px solid #24c6f5;
            padding-left: 10px;
        }

        .nav-toggle {
            width: 100%;
            border: 0;
            font: inherit;
            text-align: left;
            cursor: pointer;
            background: transparent;
        }

        .nav-toggle .nav-chevron {
            margin-left: auto;
            transition: transform 0.2s ease;
        }

        .nav-toggle[aria-expanded="true"] .nav-chevron {
            transform: rotate(180deg);
        }

        .submenu {
            display: none;
            margin: -2px 0 5px 34px;
            padding-left: 10px;
            border-left: 1px solid rgba(255,255,255,0.2);
        }

        .submenu.is-open {
            display: block;
        }

        .submenu a {
            padding: 8px 10px;
            border-radius: 7px;
            font-size: 13px;
            font-weight: 500;
        }

        .nav-icon {
            width: 22px;
            text-align: center;
            font-size: 16px;
        }

        .sidebar-bottom {
            margin-top: auto;
            border-top: 1px solid rgba(255,255,255,0.10);
            padding-top: 15px;
        }

        .user-mini {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 8px;
            margin-bottom: 8px;
        }

        .avatar {
    width: 36px;
    height: 36px;
    border-radius: 50%;
    overflow: hidden;
    display: flex;
    align-items: center;
    justify-content: center;
}

.avatar img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    border-radius: 50%;
    display: block;
}

.top-avatar {
    width: 36px;
    height: 36px;
    border-radius: 50%;
    overflow: hidden;
    display: flex;
    align-items: center;
    justify-content: center;
}

.top-avatar img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    border-radius: 50%;
    display: block;
}

        .user-mini-name {
            font-size: 12px;
            font-weight: 700;
        }

        .user-mini-role {
            color: rgba(255,255,255,0.55);
            font-size: 9px;
            margin-top: 2px;
        }

        .logout-link {
            color: rgba(255,255,255,0.80);
            display: flex;
            gap: 12px;
            align-items: center;
            padding: 11px 10px;
            border-radius: 10px;
            font-size: 14px;
            font-weight: 600;
        }

        .logout-link:hover {
            background: rgba(255,255,255,0.08);
            color: white;
        }

        /* MAIN */

        .main {
            margin-left: 235px;
            width: calc(100% - 235px);
            min-height: 100vh;
        }

        /* TOPBAR */

        .topbar {
            min-height: 78px;
            background: white;
            border-bottom: 1px solid #dfe7ef;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 15px 28px;
        }

        .page-title {
            font-size: 18px;
            font-weight: 800;
            color: #102e4b;
            margin-bottom: 3px;
        }

        .page-subtitle {
            font-size: 14px;
            color: #000000;
        }

        .top-user {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .notification {
            width: 40px;
            height: 40px;
            border: 1px solid #e3eaf1;
            border-radius: 11px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: white;
            margin-right: 3px;
        }

        .top-avatar {
            width: 36px;
            height: 36px;
            border-radius: 10px;
            background: linear-gradient(135deg,#1476e8,#0c91c9);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 11px;
            font-weight: 800;
            overflow: hidden;
        }

        .top-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
        }

        .top-user-info {
            line-height: 1.2;
        }

        .top-user-name {
            font-size: 12px;
            font-weight: 700;
        }

        .top-user-role {
            font-size: 11px;
            color: #000000;
            margin-top: 2px;
        }

        /* CONTENT */

        .content {
            padding: 27px 28px 40px;
        }

        /* WELCOME */

        .welcome {
            min-height: 170px;
            border-radius: 19px;
            overflow: hidden;
            position: relative;

            background-image:
                linear-gradient(
                    90deg,
                    rgba(6,45,76,0.96),
                    rgba(6,76,112,0.84),
                    rgba(6,76,112,0.42)
                ),
                url("https://images.unsplash.com/photo-1588072432836-e10032774350?auto=format&fit=crop&w=1600&q=85");

            background-size: cover;
            background-position: center;
            color: white;
            padding: 28px 31px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            margin-bottom: 19px;
        }

        .role-badge {
            display: inline-flex;
            align-items: center;
            width: fit-content;
            padding: 6px 12px;
            border-radius: 30px;
            background: rgba(255,255,255,0.15);
            border: 1px solid rgba(255,255,255,0.17);
            font-size: 11px;
            font-weight: 800;
            margin-bottom: 13px;
        }

        .welcome h1 {
            margin: 0 0 7px;
            font-size: 27px;
            letter-spacing: -0.5px;
        }

        .welcome h1 span {
            color: #63d4ff;
        }

        .welcome p {
            margin: 0;
            color: rgba(255,255,255,0.90);
            max-width: 750px;
            line-height: 1.6;
            font-size: 13px;
        }

        /* SUMMARY */

        .summary-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 15px;
            margin-bottom: 19px;
        }

        .summary-card {
            background: white;
            border: 1px solid #e0e8f0;
            border-radius: 16px;
            padding: 17px;
            display: flex;
            align-items: center;
            gap: 13px;
            box-shadow: 0 4px 15px rgba(25,54,84,0.03);
        }

        .summary-icon {
            width: 42px;
            height: 42px;
            flex-shrink: 0;
            border-radius: 11px;
            background: #eef6ff;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
        }

        .summary-label {
            font-size: 13px;
            color: #000000;
            margin-bottom: 3px;
        }

        .summary-value {
            font-size: 17px;
            font-weight: 800;
            color: #112f4c;
        }

        /* MAIN GRID */

        .main-grid {
            display: grid;
            grid-template-columns: minmax(0, 2fr) minmax(300px, 1fr);
            gap: 18px;
        }

        /* CARD */

        .card {
            background: white;
            border: 1px solid #dfe7ef;
            border-radius: 17px;
            padding: 20px;
            margin-bottom: 18px;
            box-shadow: 0 4px 15px rgba(25,54,84,0.025);
        }

        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding-bottom: 15px;
            border-bottom: 1px solid #e7edf3;
            margin-bottom: 16px;
            gap: 12px;
        }

        .card-title {
            display: flex;
            align-items: center;
            gap: 11px;
        }

        .card-title-icon {
            width: 38px;
            height: 38px;
            flex-shrink: 0;
            border-radius: 10px;
            background: #edf5ff;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 17px;
        }

        .card-title h2 {
            margin: 0;
            font-size: 15px;
            color: #173b5d;
        }

        .card-title p {
            margin: 3px 0 0;
            font-size: 14px;
            color: #000000;
        }

        .parent-badge {
            padding: 7px 11px;
            border-radius: 20px;
            background: #eef6ff;
            color: #176ac0;
            font-size: 11px;
            font-weight: 800;
            white-space: nowrap;
        }

        /* ACCOUNT */

        .account-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 11px;
        }

        .account-item {
            background: #f7f9fb;
            border: 1px solid #edf1f5;
            border-radius: 12px;
            padding: 14px;
            min-width: 0;
        }

        .account-item small {
            display: block;
            color: #000000;
            font-size: 12px;
            text-transform: uppercase;
            font-weight: 700;
            margin-bottom: 6px;
            letter-spacing: 0.3px;
        }

        .account-item strong {
            display: block;
            font-size: 14px;
            color: #173a5b;
            line-height: 1.5;
            overflow-wrap: anywhere;
        }

        .parent-notice {
            margin-top: 16px;
            padding: 17px;
            border-radius: 13px;
            background: linear-gradient(135deg,#edf7ff,#f4fbff);
            border: 1px solid #cce7ff;
        }

        .parent-notice h3 {
            margin: 0 0 7px;
            font-size: 14px;
            color: #15456d;
        }

        .parent-notice p {
            margin: 0;
            color: #000000;
            font-size: 15px;
            line-height: 1.6;
        }

        /* CHILDREN */

        .children-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit,minmax(260px,1fr));
            gap: 14px;
        }

        .child-card {
            border: 1px solid #e1e9f0;
            border-radius: 15px;
            padding: 16px;
            background: #fbfcfd;
            transition: 0.2s ease;
        }

        .child-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(25,54,84,0.06);
        }

        .child-top {
            display: flex;
            align-items: center;
            gap: 11px;
            margin-bottom: 14px;
        }

        .child-avatar {
            width: 42px;
            height: 42px;
            flex-shrink: 0;
            border-radius: 12px;
            background: linear-gradient(135deg,#e8f2ff,#d7edff);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
        }

        .child-name {
            font-size: 14px;
            font-weight: 800;
            color: #153a5c;
            margin-bottom: 3px;
        }

        .child-adm {
            font-size: 13px;
            color: #000000;
        }

        .status {
            margin-left: auto;
            padding: 5px 8px;
            border-radius: 20px;
            font-size: 10px;
            font-weight: 800;
        }

        .status-active {
            background: #e7f8ee;
            color: #168149;
        }

        .status-other {
            background: #fff1e9;
            color: #bd6028;
        }

        .child-details {
            display: grid;
            grid-template-columns: repeat(2,1fr);
            gap: 8px;
        }

        .child-detail {
            padding: 10px;
            border-radius: 9px;
            background: white;
            border: 1px solid #edf1f5;
            min-width: 0;
        }

        .child-detail small {
            display: block;
            font-size: 12px;
            text-transform: uppercase;
            color: #000000;
            margin-bottom: 4px;
        }

        .child-detail strong {
            display: block;
            font-size: 13px;
            color: #24445f;
            line-height: 1.4;
            overflow-wrap: anywhere;
        }

        /* PROGRESS */

        .progress-card {
            border: 1px solid #e1e9f0;
            border-radius: 15px;
            padding: 17px;
            background: white;
            margin-bottom: 12px;
        }

        .progress-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }

        .progress-name {
            font-size: 14px;
            font-weight: 800;
            color: #173a5a;
        }

        .progress-adm {
            font-size: 13px;
            color: #000000;
            margin-top: 3px;
        }

        .progress-grid {
            display: grid;
            grid-template-columns: repeat(4,1fr);
            gap: 9px;
        }

        .progress-item {
            background: #f7f9fb;
            border-radius: 10px;
            padding: 11px;
            min-width: 0;
        }

        .progress-item small {
            display: block;
            font-size: 12px;
            color: #000000;
            margin-bottom: 4px;
        }

        .progress-item strong {
            font-size: 14px;
            color: #173a5a;
        }

        .progress-bar {
            height: 6px;
            background: #e8eef4;
            border-radius: 20px;
            margin-top: 10px;
            overflow: hidden;
        }

        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg,#1579ed,#12a6d8);
            border-radius: 20px;
        }

        /* QUICK ACTIONS */

        .quick-grid {
            display: grid;
            grid-template-columns: repeat(2,1fr);
            gap: 10px;
        }

        .quick-card {
            border: 1px solid #e0e8ef;
            border-radius: 13px;
            padding: 14px;
            min-height: 110px;
            transition: 0.2s ease;
            display: block;
            background: white;
        }

        .quick-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 7px 17px rgba(20,50,80,0.06);
        }

        .quick-icon {
            width: 32px;
            height: 32px;
            border-radius: 9px;
            background: #eef5ff;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 15px;
            margin-bottom: 9px;
        }

        .quick-card h3 {
            margin: 0 0 5px;
            font-size: 12px;
            color: #173a5a;
        }

        .quick-card p {
            margin: 0;
            color: #000000;
            font-size: 13px;
            line-height: 1.5;
        }

        /* EMPTY STATE */

        .empty-state {
            text-align: center;
            padding: 35px 15px;
            color: #000000;
        }

        .empty-state-icon {
            font-size: 35px;
            margin-bottom: 10px;
        }

        .empty-state h3 {
            color: #264864;
            font-size: 15px;
            margin: 0 0 6px;
        }

        .empty-state p {
            font-size: 13px;
            margin: 0;
        }

        /* DEBUG NOTICE */

        .debug-notice {
            margin-bottom: 18px;
            padding: 12px 15px;
            background: #fff8e1;
            border: 1px solid #f0d98c;
            border-radius: 10px;
            color: #765b00;
            font-size: 12px;
        }

        /* RESPONSIVE */

        @media (max-width: 1100px) {

            .summary-grid {
                grid-template-columns: repeat(2,1fr);
            }

            .main-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 800px) {

            .sidebar {
                width: 70px;
                padding: 15px 9px;
            }

            .brand-name,
            .menu-title,
            .nav span:not(.nav-icon),
            .user-mini-info,
            .logout-link span:not(.nav-icon) {
                display: none;
            }

            .brand {
                justify-content: center;
                padding-bottom: 25px;
            }

            .nav a {
                justify-content: center;
                padding: 12px;
            }

            .nav a.active {
                border-left: none;
            }

            .user-mini {
                justify-content: center;
            }

            .logout-link {
                justify-content: center;
            }

            .main {
                margin-left: 70px;
                width: calc(100% - 70px);
            }

            .content {
                padding: 18px;
            }

            .topbar {
                padding: 0 18px;
            }

            .top-user-info {
                display: none;
            }
        }

        @media (max-width: 600px) {

            .summary-grid {
                grid-template-columns: 1fr;
            }

            .account-grid {
                grid-template-columns: 1fr;
            }

            .progress-grid {
                grid-template-columns: repeat(2,1fr);
            }

            .welcome {
                padding: 23px;
            }

            .welcome h1 {
                font-size: 22px;
            }

            .topbar {
                min-height: 68px;
            }

            .card {
                padding: 15px;
            }
        }

    </style>

</head>

<body>

<div class="app">

    <!-- SIDEBAR -->

    <aside class="sidebar">

        <div class="brand">

            <div class="brand-icon">🎓</div>

            <div class="brand-name">MySchool</div>

        </div>

        <div class="menu-title">Main Menu</div>

        <nav class="nav">

            <a href="parent.php" class="active">
                <span class="nav-icon">🏠</span>
                <span>Dashboard</span>
            </a>

            <a href="profile.php">
                <span class="nav-icon">👤</span>
                <span>Profile</span>
            </a>

            <button type="button" class="nav-toggle" aria-expanded="false" aria-controls="parent-academics-menu">
                <span class="nav-icon">📚</span>
                <span>Academics</span>
                <span class="nav-chevron">⌄</span>
            </button>

            <div class="submenu" id="parent-academics-menu">
                <a href="academics/parent/children.php">Children</a>
                <a href="academics/parent/timetable.php">Timetable</a>
                <a href="academics/parent/assignments.php">Assignments</a>
                <a href="academics/parent/resources.php">Learning Resources</a>
                <a href="academics/parent/results.php">Results</a>
                <a href="academics/parent/performance.php">Performance</a>
                <a href="academics/parent/report_card.php">Report Card</a>
                <a href="academics/parent/cbc.php">CBC Progress</a>
                <a href="academics/parent/achievements.php">Achievements</a>
            </div>

            <a href="fees.php">
                <span class="nav-icon">💰</span>
                <span>Fees</span>
            </a>

            <a href="messages.php">
                <span class="nav-icon">💬</span>
                <span>Messages</span>
            </a>

            <a href="children.php">
                <span class="nav-icon">📅</span>
                <span>Calendar</span>
            </a>

        </nav>

        <div class="sidebar-bottom">

            <div class="user-mini">

                <div class="avatar">

                    <?php if (!empty($profile_picture_url)): ?>

                        <img
                            src="<?= e($profile_picture_url) ?>"
                            alt="Profile Picture"
                        >

                    <?php else: ?>

                        <?= e($initials) ?>

                    <?php endif; ?>

                </div>

                <div class="user-mini-info">

                    <div class="user-mini-name">
                        <?= e($parent_name) ?>
                    </div>

                    <div class="user-mini-role">
                        PARENT
                    </div>

                </div>

            </div>

            <a href="logout.php" class="logout-link">

                <span class="nav-icon">🚪</span>
                <span>Logout</span>

            </a>

        </div>

    </aside>

    <!-- MAIN -->

    <div class="main">

        <!-- TOPBAR -->

        <header class="topbar">

            <div>

                <div class="page-title">
                    Parent Dashboard
                </div>

                <div class="page-subtitle">
                    School Management System
                </div>

            </div>

            <div class="top-user">

               


            
                <div class="top-avatar">

                    <?php if (!empty($profile_picture_url)): ?>

                        <img
                            src="<?= e($profile_picture_url) ?>"
                            alt="Profile Picture"
                        >

                    <?php else: ?>

                        <?= e($initials) ?>

                    <?php endif; ?>

                </div>

                <div class="top-user-info">

                    <div class="top-user-name">
                        <?= e($parent_name) ?>
                    </div>

                    <div class="top-user-role">
                        PARENT
                    </div>

                </div>

            </div>

        </header>

        <!-- CONTENT -->

        <main class="content">

            <section class="welcome">

                <div class="role-badge">
                    👨‍👩‍👧 PARENT
                </div>

                <h1>
                    Welcome back,
                    <span><?= e($parent_name) ?></span> 👋
                </h1>

                <p>
                    Welcome to your MySchool parent dashboard.
                    Monitor your children's academic progress,
                    attendance, fees and school information
                    from one place.
                </p>

            </section>

            <!-- SUMMARY -->

            <section class="summary-grid">

                <div class="summary-card">

                    <div class="summary-icon">👤</div>

                    <div>

                        <div class="summary-label">
                            Account
                        </div>

                        <div class="summary-value">
                            Active
                        </div>

                    </div>

                </div>

                <div class="summary-card">

                    <div class="summary-icon">👨‍👩‍👧</div>

                    <div>

                        <div class="summary-label">
                            Role
                        </div>

                        <div class="summary-value">
                            PARENT
                        </div>

                    </div>

                </div>

                <div class="summary-card">

                    <div class="summary-icon">👨‍🎓</div>

                    <div>

                        <div class="summary-label">
                            Children
                        </div>

                        <div class="summary-value">
                            <?= count($children) ?> Linked
                        </div>

                    </div>

                </div>

                <div class="summary-card">

                    <div class="summary-icon">🔔</div>

                    <div>

                        <div class="summary-label">
                            Notifications
                        </div>

                        <div class="summary-value">
                            <?= $unreadMessageCount ?> unread
                        </div>

                    </div>

                </div>

            </section>

            <div class="main-grid">

                <!-- LEFT -->

                <div>

                    <!-- ACCOUNT -->

                    <section class="card" id="account">

                        <div class="card-header">

                            <div class="card-title">

                                <div class="card-title-icon">
                                    👤
                                </div>

                                <div>

                                    <h2>
                                        Account Information
                                    </h2>

                                    <p>
                                        Your registered parent details
                                    </p>

                                </div>

                            </div>

                            <div class="parent-badge">
                                👨‍👩‍👧 PARENT
                            </div>

                        </div>

                        <div class="account-grid">

                            <div class="account-item">

                                <small>
                                    Email Address
                                </small>

                                <strong>
                                    <?= e($user["email"] ?? "Not provided") ?>
                                </strong>

                            </div>

                            <div class="account-item">

                                <small>
                                    Phone Number
                                </small>

                                <strong>
                                    <?= e($user["phone_number"] ?? "Not provided") ?>
                                </strong>

                            </div>

                            <div class="account-item">

                                <small>
                                    Full Name
                                </small>

                                <strong>
                                    <?= e($user["full_name"] ?? "Not provided") ?>
                                </strong>

                            </div>

                            <div class="account-item">

                                <small>
                                    National ID
                                </small>

                                <strong>
                                    <?= e($user["national_id"] ?? "Not provided") ?>
                                </strong>

                            </div>

                            <div class="account-item">

                                <small>
                                    Relationship
                                </small>

                                <strong>
                                    <?= e($user["relationship"] ?? "Parent") ?>
                                </strong>

                            </div>

                            <div class="account-item">

                                <small>
                                    Account Role
                                </small>

                                <strong>
                                    <?= e($user["role"] ?? "PARENT") ?>
                                </strong>

                            </div>

                        </div>

                        <div class="parent-notice">

                            <h3>
                                👨‍👩‍👧 Parent Account
                            </h3>

                            <p>
                                Your account gives you access to the
                                academic progress, attendance, fee
                                information and other school information
                                of the children linked to your account.
                            </p>

                        </div>

                    </section>

                    <!-- CHILDREN -->

                    <section class="card" id="children">

                        <div class="card-header">

                            <div class="card-title">

                                <div class="card-title-icon">
                                    👨‍🎓
                                </div>

                                <div>

                                    <h2>
                                        My Children
                                    </h2>

                                    <p>
                                        Students linked to your account
                                    </p>

                                </div>

                            </div>

                        </div>

                        <?php if (empty($children)): ?>

                            <div class="empty-state">

                                <div class="empty-state-icon">
                                    👨‍🎓
                                </div>

                                <h3>
                                    No Children Linked
                                </h3>

                                <p>
                                    No students are currently linked
                                    to your parent account.
                                </p>

                            </div>

                        <?php else: ?>

                            <div class="children-grid">

                                <?php foreach ($children as $child): ?>

                                    <div class="child-card">

                                        <div class="child-top">

                                            <div class="child-avatar">
                                                🎓
                                            </div>

                                            <div style="min-width:0;">

                                                <div class="child-name">
                                                    <?= e($child["full_name"]) ?>
                                                </div>

                                                <div class="child-adm">
                                                    Admission No:
                                                    <?= e($child["adm_number"]) ?>
                                                </div>

                                            </div>

                                            <?php

                                            $status_class =
                                                strtoupper((string)$child["status"]) === "ACTIVE"
                                                ? "status-active"
                                                : "status-other";

                                            ?>

                                            <div class="status <?= $status_class ?>">

                                                <?= e($child["status"]) ?>

                                            </div>

                                        </div>

                                        <div class="child-details">

                                            <div class="child-detail">

                                                <small>
                                                    Class
                                                </small>

                                                <strong>
                                                    <?= e($child["class_name"]) ?>
                                                    -
                                                    <?= e($child["stream_name"]) ?>
                                                </strong>

                                            </div>

                                            <div class="child-detail">

                                                <small>
                                                    Gender
                                                </small>

                                                <strong>
                                                    <?= e($child["gender"]) ?>
                                                </strong>

                                            </div>

                                            <div class="child-detail">

                                                <small>
                                                    Date of Birth
                                                </small>

                                                <strong>
                                                    <?= !empty($child["date_of_birth"])
                                                        ? e($child["date_of_birth"])
                                                        : "Not provided" ?>
                                                </strong>

                                            </div>

                                            <div class="child-detail">

                                                <small>
                                                    Average Score
                                                </small>

                                                <strong>
                                                    <?= number_format(
                                                        (float)$child["average_score"],
                                                        2
                                                    ) ?>
                                                </strong>

                                            </div>

                                            <div class="child-detail">

                                                <small>
                                                    Attendance
                                                </small>

                                                <strong>
                                                    <?= number_format(
                                                        (float)$child["attendance_percentage"],
                                                        1
                                                    ) ?>%
                                                </strong>

                                            </div>

                                            <div class="child-detail">

                                                <small>
                                                    Fee Balance
                                                </small>

                                                <strong>
                                                    KES
                                                    <?= number_format(
                                                        (float)$child["current_balance"],
                                                        2
                                                    ) ?>
                                                </strong>

                                            </div>

                                        </div>

                                    </div>

                                <?php endforeach; ?>

                            </div>

                        <?php endif; ?>

                    </section>

                    <!-- ACADEMIC / PROGRESS -->

                    <section class="card" id="academic">

                        <div class="card-header">

                            <div class="card-title">

                                <div class="card-title-icon">
                                    📊
                                </div>

                                <div>

                                    <h2>
                                        Children's Progress
                                    </h2>

                                    <p>
                                        Academic performance,
                                        attendance and fees
                                    </p>

                                </div>

                            </div>

                        </div>

                        <?php if (empty($children)): ?>

                            <div class="empty-state">

                                <div class="empty-state-icon">
                                    📊
                                </div>

                                <h3>
                                    No Progress Available
                                </h3>

                                <p>
                                    Progress information will appear
                                    when a student is linked to your account.
                                </p>

                            </div>

                        <?php else: ?>

                            <?php foreach ($children as $child): ?>

                                <div class="progress-card">

                                    <div class="progress-header">

                                        <div>

                                            <div class="progress-name">
                                                <?= e($child["full_name"]) ?>
                                            </div>

                                            <div class="progress-adm">
                                                Admission Number:
                                                <?= e($child["adm_number"]) ?>
                                            </div>

                                        </div>

                                    </div>

                                    <div class="progress-grid">

                                        <div class="progress-item">

                                            <small>
                                                Average Score
                                            </small>

                                            <strong>
                                                <?= number_format(
                                                    (float)$child["average_score"],
                                                    2
                                                ) ?>
                                            </strong>

                                        </div>

                                        <div class="progress-item">

                                            <small>
                                                Attendance
                                            </small>

                                            <strong>
                                                <?= number_format(
                                                    (float)$child["attendance_percentage"],
                                                    1
                                                ) ?>%
                                            </strong>

                                            <div class="progress-bar">

                                                <div
                                                    class="progress-fill"
                                                    style="width: <?= min(
                                                        100,
                                                        max(
                                                            0,
                                                            (float)$child["attendance_percentage"]
                                                        )
                                                    ) ?>%;"
                                                ></div>

                                            </div>

                                        </div>

                                        <div class="progress-item">

                                            <small>
                                                Subjects
                                            </small>

                                            <strong>
                                                <?= e($child["total_subjects"]) ?>
                                            </strong>

                                        </div>

                                        <div
                                            class="progress-item"
                                            id="fees"
                                        >

                                            <small>
                                                Fee Balance
                                            </small>

                                            <strong>
                                                KES
                                                <?= number_format(
                                                    (float)$child["current_balance"],
                                                    2
                                                ) ?>
                                            </strong>

                                        </div>

                                    </div>

                                </div>

                            <?php endforeach; ?>

                        <?php endif; ?>

                    </section>

                </div>

                <!-- RIGHT -->

                <div>

                    <!-- QUICK ACTIONS -->

                    <section class="card">

                        <div class="card-header">

                            <div class="card-title">

                                <div class="card-title-icon">
                                    ⚡
                                </div>

                                <div>

                                    <h2>
                                        Quick Actions
                                    </h2>

                                    <p>
                                        Frequently used features
                                    </p>

                                </div>

                            </div>

                        </div>

                        <div class="quick-grid">

                            <a href="#account" class="quick-card">

                                <div class="quick-icon">
                                    👤
                                </div>

                                <h3>
                                    Profile
                                </h3>

                                <p>
                                    View your personal information.
                                </p>

                            </a>

                            <a href="#academic" class="quick-card">

                                <div class="quick-icon">
                                    📚
                                </div>

                                <h3>
                                    Academics
                                </h3>

                                <p>
                                    View your children's academic progress.
                                </p>

                            </a>

                            <a href="#fees" class="quick-card">

                                <div class="quick-icon">
                                    💰
                                </div>

                                <h3>
                                    Fees
                                </h3>

                                <p>
                                    View fee balances and payments.
                                </p>

                            </a>

                            <a
                                href="messages.php"
                                class="quick-card"
                                id="messages"
                            >

                                <div class="quick-icon">
                                    💬
                                </div>

                                <h3>
                                    Messages
                                </h3>

                                <p>
                                    Communicate with teachers and school.
                                </p>

                            </a>

                            <a href="#children" class="quick-card">

                                <div class="quick-icon">
                                    📅
                                </div>

                                <h3>
                                    Calendar
                                </h3>

                                <p>
                                    View school events and activities.
                                </p>

                            </a>

                            <a href="logout.php" class="quick-card">

                                <div class="quick-icon">
                                    🚪
                                </div>

                                <h3>
                                    Logout
                                </h3>

                                <p>
                                    Securely sign out of your account.
                                </p>

                            </a>

                        </div>

                    </section>

                    <!-- SUPPORT -->

                    <section class="card">

                        <div class="card-header">

                            <div class="card-title">

                                <div class="card-title-icon">
                                    💬
                                </div>

                                <div>

                                    <h2>
                                        Parent Support
                                    </h2>

                                    <p>
                                        Need assistance?
                                    </p>

                                </div>

                            </div>

                        </div>

                        <p
                            style="
                                font-size:13px;
                                color:#74879a;
                                line-height:1.7;
                                margin:0;
                            "
                        >
                            Use the Messages section to communicate with
                            your children's teachers or contact the school
                            administration for assistance.
                        </p>

                    </section>

                </div>

            </div>

        </main>

    </div>

</div>

<script>
    document.querySelectorAll('.nav-toggle').forEach(function (toggle) {
        toggle.addEventListener('click', function () {
            const menu = document.getElementById(toggle.getAttribute('aria-controls'));
            const isOpen = toggle.getAttribute('aria-expanded') === 'true';

            toggle.setAttribute('aria-expanded', String(!isOpen));
            menu.classList.toggle('is-open', !isOpen);
        });
    });
</script>

</body>

</html>