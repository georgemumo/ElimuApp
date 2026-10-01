<?php

session_start();

require_once "db.php";
require_once __DIR__ . "/message_helpers.php";


/*
 * Check login
 */

// if (!isset($_SESSION["logged_in"]) ||
//     $_SESSION["logged_in"] !== true) {

//     header("Location: login.php");

//     exit;
// }


// /*
//  * Make sure the logged-in user is a teacher
//  */

// if (!isset($_SESSION["role"]) ||
//     $_SESSION["role"] !== "TEACHER") {

//     header("Location: login.php");

//     exit;
// }


$user_id = $_SESSION["user_id"];
$unreadMessageCount = unreadMessageCount($conn, (int) $user_id);

$user = null;

$timetable = [];


/*
 * Get teacher information
 */

$stmt = $conn->prepare("
    SELECT
        u.email,
        u.phone_number AS user_phone,
        u.role,
        t.teacher_id,
        t.full_name,
        t.phone_number,
        t.email AS teacher_email,
        t.tsc_number,
        t.specialization
    FROM users u
    INNER JOIN teachers t
        ON u.user_id = t.user_id
    WHERE u.user_id = ?
    LIMIT 1
");

$stmt->execute([
    $user_id
]);

$user = $stmt->fetch();


/*
 * Make sure teacher profile exists
 */

if (!$user) {

    session_destroy();

    header("Location: login.php");

    exit;
}


$teacher_id = $user["teacher_id"];


/*
 * Get teacher timetable
 */

$stmt = $conn->prepare("
    SELECT
        tt.timetable_id,
        tt.day_of_week,
        tt.start_time,
        tt.end_time,
        c.class_name,
        c.stream_name,
        s.subject_name,
        s.subject_code
    FROM timetables tt
    INNER JOIN classes c
        ON tt.class_id = c.class_id
    INNER JOIN subjects s
        ON tt.subject_id = s.subject_id
    WHERE tt.teacher_id = ?
    ORDER BY
        FIELD(
            tt.day_of_week,
            'MONDAY',
            'TUESDAY',
            'WEDNESDAY',
            'THURSDAY',
            'FRIDAY',
            'SATURDAY'
        ),
        tt.start_time
");

$stmt->execute([
    $teacher_id
]);

$timetable = $stmt->fetchAll();

?>

<!DOCTYPE html>

<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Teacher Dashboard - MySchool</title>


    <style>

        /* =========================================================
           GLOBAL
        ========================================================= */

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }


        body {

            font-family:
                "Segoe UI",
                Arial,
                Helvetica,
                sans-serif;

            background: #f1f5f9;

            color: #102a43;

            font-size: 16px;

            line-height: 1.6;
        }


        a {
            text-decoration: none;
        }


        /* =========================================================
           DASHBOARD LAYOUT
        ========================================================= */

        .dashboard {

            min-height: 100vh;

            display: flex;

            background: #f1f5f9;
        }


        /* =========================================================
           SIDEBAR
        ========================================================= */

        .sidebar {

            width: 245px;

            background:
                linear-gradient(
                    180deg,
                    #0f3557 0%,
                    #075985 100%
                );

            color: white;

            min-height: 100vh;

            padding: 25px 15px;

            position: fixed;

            left: 0;

            top: 0;

            bottom: 0;
        }


        .school-logo {

            display: flex;

            align-items: center;

            gap: 12px;

            padding: 5px 10px 30px;

            border-bottom:
                1px solid
                rgba(255,255,255,0.12);

            margin-bottom: 25px;
        }


        .logo-icon {

            width: 46px;

            height: 46px;

            border-radius: 13px;

            background:
                rgba(255,255,255,0.15);

            display: flex;

            align-items: center;

            justify-content: center;

            font-size: 23px;
        }


        .school-logo h2 {

            font-size: 22px;

            color: white;

            font-weight: 800;
        }


        .school-logo span {

            display: block;

            color: #bfdbfe;

            font-size: 12px;

            margin-top: 1px;
        }


        .menu-title {

            font-size: 11px;

            text-transform: uppercase;

            letter-spacing: 1px;

            color: #93c5fd;

            font-weight: 700;

            padding: 0 12px;

            margin-bottom: 10px;
        }


        .nav {

            display: flex;

            flex-direction: column;

            gap: 5px;
        }


        .nav a {

            display: flex;

            align-items: center;

            gap: 13px;

            padding: 13px 14px;

            border-radius: 11px;

            color: #dbeafe;

            font-size: 15px;

            font-weight: 600;

            transition: 0.2s ease;
        }


        .nav a:hover {

            background:
                rgba(255,255,255,0.10);

            color: white;
        }


        .nav a.active {

            background:
                rgba(255,255,255,0.16);

            color: white;

            border-left:
                3px solid #38bdf8;
        }


        .nav-icon {

            width: 25px;

            text-align: center;

            font-size: 18px;
        }


        .sidebar-bottom {

            position: absolute;

            bottom: 25px;

            left: 15px;

            right: 15px;

            border-top:
                1px solid
                rgba(255,255,255,0.12);

            padding-top: 20px;
        }


        .teacher-mini {

            display: flex;

            align-items: center;

            gap: 10px;

            padding: 8px 10px 18px;
        }


        .avatar {

            width: 40px;

            height: 40px;

            border-radius: 50%;

            background: #38bdf8;

            color: #082f49;

            display: flex;

            align-items: center;

            justify-content: center;

            font-size: 15px;

            font-weight: 800;
        }


        .teacher-mini strong {

            display: block;

            font-size: 14px;

            color: white;
        }


        .teacher-mini small {

            color: #bae6fd;

            font-size: 11px;
        }


        .logout-side {

            display: block;

            color: #fecaca;

            font-size: 14px;

            font-weight: 600;

            padding: 8px 10px;
        }


        .logout-side:hover {

            color: white;
        }


        /* =========================================================
           MAIN AREA
        ========================================================= */

        .main-area {

            margin-left: 245px;

            width: calc(100% - 245px);

            min-height: 100vh;
        }


        /* =========================================================
           TOP HEADER
        ========================================================= */

        .top-header {

            height: 78px;

            background: white;

            border-bottom:
                1px solid #e2e8f0;

            display: flex;

            align-items: center;

            justify-content: space-between;

            padding: 0 30px;
        }


        .page-title h1 {

            font-size: 22px;

            color: #102a43;

            font-weight: 800;
        }


        .page-title p {

            color: #718096;

            font-size: 13px;
        }


        .header-right {

            display: flex;

            align-items: center;

            gap: 15px;
        }


        .notification {

            width: 43px;

            height: 43px;

            border:
                1px solid #e2e8f0;

            border-radius: 11px;

            display: flex;

            align-items: center;

            justify-content: center;

            background: white;

            font-size: 19px;
        }


        .header-user {

            display: flex;

            align-items: center;

            gap: 10px;
        }


        .header-avatar {

            width: 40px;

            height: 40px;

            border-radius: 10px;

            background:
                linear-gradient(
                    135deg,
                    #2563eb,
                    #0891b2
                );

            color: white;

            display: flex;

            align-items: center;

            justify-content: center;

            font-weight: 800;

            font-size: 14px;
        }


        .header-user strong {

            display: block;

            font-size: 13px;

            color: #102a43;
        }


        .header-user small {

            display: block;

            font-size: 10px;

            color: #64748b;

            font-weight: 700;
        }


        /* =========================================================
           CONTENT
        ========================================================= */

        main {

            padding: 28px 30px 50px;

            max-width: 1500px;

            margin: auto;
        }


        /* =========================================================
           WELCOME BANNER
        ========================================================= */

        .welcome {

            position: relative;

            overflow: hidden;

            background:
                linear-gradient(
                    135deg,
                    #0f3557,
                    #075985
                );

            color: white;

            border-radius: 20px;

            padding: 35px 32px;

            margin-bottom: 22px;

            box-shadow:
                0 10px 30px
                rgba(15,53,87,0.15);
        }


        .welcome::after {

            content: "";

            position: absolute;

            width: 220px;

            height: 220px;

            border-radius: 50%;

            background:
                rgba(56,189,248,0.12);

            right: -70px;

            top: -100px;
        }


        .welcome::before {

            content: "";

            position: absolute;

            width: 160px;

            height: 160px;

            border-radius: 50%;

            background:
                rgba(255,255,255,0.05);

            right: 150px;

            bottom: -100px;
        }


        .role-badge {

            display: inline-block;

            background:
                rgba(255,255,255,0.15);

            border:
                1px solid
                rgba(255,255,255,0.18);

            padding: 6px 13px;

            border-radius: 20px;

            font-size: 12px;

            font-weight: 700;

            margin-bottom: 12px;
        }


        .welcome h2 {

            font-size: 30px;

            line-height: 1.2;

            margin-bottom: 8px;

            position: relative;

            z-index: 2;
        }


        .welcome h2 span {

            color: #67e8f9;
        }


        .welcome p {

            font-size: 15px;

            color: #dbeafe;

            max-width: 700px;

            position: relative;

            z-index: 2;
        }


        /* =========================================================
           STAT CARDS
        ========================================================= */

        .stats {

            display: grid;

            grid-template-columns:
                repeat(
                    4,
                    1fr
                );

            gap: 16px;

            margin-bottom: 22px;
        }


        .stat-card {

            background: white;

            border:
                1px solid #e2e8f0;

            border-radius: 16px;

            padding: 20px;

            display: flex;

            align-items: center;

            gap: 14px;

            box-shadow:
                0 3px 12px
                rgba(15,23,42,0.03);
        }


        .stat-icon {

            width: 48px;

            height: 48px;

            border-radius: 13px;

            background: #eff6ff;

            display: flex;

            align-items: center;

            justify-content: center;

            font-size: 22px;
        }


        .stat-card small {

            display: block;

            color: #718096;

            font-size: 12px;

            margin-bottom: 2px;
        }


        .stat-card strong {

            display: block;

            color: #102a43;

            font-size: 18px;
        }


        /* =========================================================
           TWO COLUMN AREA
        ========================================================= */

        .content-grid {

            display: grid;

            grid-template-columns:
                minmax(0, 1.8fr)
                minmax(300px, 1fr);

            gap: 20px;
        }


        .card {

            background: white;

            border:
                1px solid #e2e8f0;

            border-radius: 18px;

            padding: 25px;

            margin-bottom: 20px;

            box-shadow:
                0 3px 12px
                rgba(15,23,42,0.03);
        }


        .card-header {

            display: flex;

            align-items: center;

            justify-content: space-between;

            padding-bottom: 17px;

            border-bottom:
                1px solid #e5e7eb;

            margin-bottom: 18px;
        }


        .card-title {

            display: flex;

            align-items: center;

            gap: 12px;
        }


        .card-title-icon {

            width: 42px;

            height: 42px;

            border-radius: 12px;

            background: #eff6ff;

            display: flex;

            align-items: center;

            justify-content: center;

            font-size: 20px;
        }


        .card-title h3 {

            color: #102a43;

            font-size: 18px;

            font-weight: 800;
        }


        .card-title p {

            color: #718096;

            font-size: 12px;

            margin-top: 1px;
        }


        .badge {

            background: #eff6ff;

            color: #1d4ed8;

            padding: 6px 12px;

            border-radius: 20px;

            font-size: 11px;

            font-weight: 800;
        }


        /* =========================================================
           TEACHER INFORMATION
        ========================================================= */

        .info-grid {

            display: grid;

            grid-template-columns:
                repeat(
                    2,
                    minmax(0, 1fr)
                );

            gap: 13px;
        }


        .info-item {

            background: #f8fafc;

            border:
                1px solid #edf2f7;

            border-radius: 12px;

            padding: 16px;
        }


        .info-item small {

            display: block;

            color: #718096;

            font-size: 11px;

            text-transform: uppercase;

            letter-spacing: .4px;

            font-weight: 700;

            margin-bottom: 4px;
        }


        .info-item strong {

            display: block;

            color: #16324f;

            font-size: 15px;

            word-break: break-word;
        }


        /* =========================================================
           QUICK ACTIONS
        ========================================================= */

        .actions-grid {

            display: grid;

            grid-template-columns:
                repeat(
                    2,
                    1fr
                );

            gap: 12px;
        }


        .action-card {

            border:
                1px solid #e2e8f0;

            border-radius: 13px;

            padding: 17px;

            transition: .2s ease;

            cursor: pointer;
            color: inherit;
            text-decoration: none;
        }


        .action-card:hover {

            transform: translateY(-2px);

            border-color: #93c5fd;

            box-shadow:
                0 8px 18px
                rgba(37,99,235,.08);
        }


        .action-icon {

            width: 38px;

            height: 38px;

            border-radius: 10px;

            background: #eff6ff;

            display: flex;

            align-items: center;

            justify-content: center;

            font-size: 18px;

            margin-bottom: 10px;
        }


        .action-card h4 {

            color: #16324f;

            font-size: 14px;

            margin-bottom: 4px;
        }


        .action-card p {

            color: #718096;

            font-size: 11px;

            line-height: 1.5;
        }


        /* =========================================================
           TIMETABLE
        ========================================================= */

        .timetable-list {

            display: flex;

            flex-direction: column;

            gap: 13px;
        }


        .timetable-item {

            background:
                #f8fafc;

            border:
                1px solid #e2e8f0;

            border-left:
                4px solid #2563eb;

            border-radius: 12px;

            padding: 18px;

            transition: .2s ease;
        }


        .timetable-item:hover {

            background: #f1f5f9;

            transform: translateX(2px);
        }


        .timetable-top {

            display: flex;

            justify-content: space-between;

            align-items: flex-start;

            gap: 15px;

            margin-bottom: 13px;
        }


        .subject-name {

            color: #102a43;

            font-size: 17px;

            font-weight: 800;
        }


        .subject-code {

            display: inline-block;

            margin-top: 4px;

            color: #2563eb;

            background: #dbeafe;

            padding: 3px 8px;

            border-radius: 6px;

            font-size: 10px;

            font-weight: 800;
        }


        .day {

            background: #0f3557;

            color: white;

            padding: 7px 11px;

            border-radius: 8px;

            font-size: 11px;

            font-weight: 800;

            white-space: nowrap;
        }


        .timetable-details {

            display: grid;

            grid-template-columns:
                repeat(
                    2,
                    1fr
                );

            gap: 10px;
        }


        .time-detail {

            display: flex;

            align-items: center;

            gap: 8px;

            color: #475569;

            font-size: 13px;
        }


        .time-detail strong {

            color: #16324f;
        }


        /* =========================================================
           EMPTY STATE
        ========================================================= */

        .empty {

            text-align: center;

            padding: 35px 20px;

            color: #64748b;
        }


        .empty-icon {

            font-size: 38px;

            margin-bottom: 8px;
        }


        .empty h4 {

            font-size: 17px;

            color: #334e68;

            margin-bottom: 5px;
        }


        .empty p {

            font-size: 14px;
        }


        /* =========================================================
           RESPONSIVE
        ========================================================= */

        @media (max-width: 1100px) {

            .stats {

                grid-template-columns:
                    repeat(2, 1fr);
            }


            .content-grid {

                grid-template-columns: 1fr;
            }

        }


        @media (max-width: 800px) {

            .sidebar {

                width: 75px;

                padding: 20px 10px;
            }


            .school-logo {

                justify-content: center;

                padding-left: 0;

                padding-right: 0;
            }


            .school-logo h2,
            .school-logo span,
            .menu-title,
            .nav a span,
            .teacher-mini > div,
            .logout-side span {

                display: none;
            }


            .nav a {

                justify-content: center;

                padding: 14px 5px;
            }


            .sidebar-bottom {

                left: 10px;

                right: 10px;
            }


            .teacher-mini {

                justify-content: center;

                padding-left: 0;

                padding-right: 0;
            }


            .main-area {

                margin-left: 75px;

                width: calc(100% - 75px);
            }


            .top-header {

                padding: 0 18px;
            }


            main {

                padding: 20px 18px 40px;
            }


            .welcome h2 {

                font-size: 25px;
            }

        }


        @media (max-width: 600px) {

            .stats {

                grid-template-columns: 1fr;
            }


            .info-grid {

                grid-template-columns: 1fr;
            }


            .actions-grid {

                grid-template-columns: 1fr;
            }


            .timetable-details {

                grid-template-columns: 1fr;
            }


            .header-user {

                display: none;
            }


            .top-header {

                height: 70px;
            }


            .card {

                padding: 18px;
            }


            .welcome {

                padding: 25px 22px;
            }


            .welcome h2 {

                font-size: 23px;
            }


            .welcome p {

                font-size: 14px;
            }


            .timetable-top {

                flex-direction: column;
            }

        }

    </style>

</head>


<body>


<div class="dashboard">


    <!-- =====================================================
         SIDEBAR
    ====================================================== -->

    <aside class="sidebar">


        <div class="school-logo">

            <div class="logo-icon">
                🎓
            </div>

            <div>

                <h2>
                    MySchool
                </h2>

                <span>
                    School Management System
                </span>

            </div>

        </div>


        <div class="menu-title">
            Main Menu
        </div>


        <nav class="nav">

            <a
                href="teacher.php"
                class="active"
            >

                <div class="nav-icon">
                    🏠
                </div>

                <span>
                    Dashboard
                </span>

            </a>


            <a href="academics/teacher/index.php">

                <div class="nav-icon">
                    🎓
                </div>

                <span>
                    Academic Dashboard
                </span>

            </a>


            <a href="profile.php">

                <div class="nav-icon">
                    👤
                </div>

                <span>
                    Profile
                </span>

            </a>


            <a href="#classes">

                <div class="nav-icon">
                    📚
                </div>

                <span>
                    My Classes
                </span>

            </a>


            <a href="#attendance">

                <div class="nav-icon">
                    📋
                </div>

                <span>
                    Attendance
                </span>

            </a>


            <a href="#results">

                <div class="nav-icon">
                    📝
                </div>

                <span>
                    Exam Results
                </span>

            </a>


            <a href="#timetable">

                <div class="nav-icon">
                    🗓️
                </div>

                <span>
                    Timetable
                </span>

            </a>
             <a href="messages.php">
                <span class="nav-icon">💬</span>
                <span>Messages</span>
            </a>
        </nav>


        <div class="sidebar-bottom">


            <div class="teacher-mini">

                <div class="avatar">

                    <?php

                    $name =
                        $user["full_name"]
                        ?? "Teacher";

                    $parts =
                        explode(
                            " ",
                            trim($name)
                        );

                    $initials =
                        strtoupper(
                            substr(
                                $parts[0],
                                0,
                                1
                            )
                        );

                    if (
                        isset($parts[1])
                    ) {

                        $initials .=
                            strtoupper(
                                substr(
                                    $parts[1],
                                    0,
                                    1
                                )
                            );
                    }

                    echo htmlspecialchars(
                        $initials
                    );

                    ?>

                </div>


                <div>

                    <strong>

                        <?= htmlspecialchars(
                            $user["full_name"]
                            ?? "Teacher"
                        ) ?>

                    </strong>

                    <small>
                        TEACHER
                    </small>

                </div>

            </div>


            <a
                href="logout.php"
                class="logout-side"
            >

                🚪

                <span>
                    Logout
                </span>

            </a>


        </div>


    </aside>



    <!-- =====================================================
         MAIN AREA
    ====================================================== -->

    <section class="main-area">


        <!-- TOP HEADER -->

        <header class="top-header">


            <div class="page-title">

                <h1>
                    Teacher Dashboard
                </h1>

                <p>
                    School Management System
                </p>

            </div>


            <div class="header-right">


                <a class="notification" href="messages.php" aria-label="Messages, <?= $unreadMessageCount ?> unread" style="position:relative;color:inherit;text-decoration:none">
                    🔔
                    <?php if ($unreadMessageCount > 0): ?><span style="position:absolute;top:-6px;right:-6px;min-width:18px;height:18px;padding:0 4px;border-radius:9px;background:#d84242;color:#fff;font-size:10px;font-weight:800;line-height:18px;text-align:center"><?= $unreadMessageCount > 99 ? '99+' : $unreadMessageCount ?></span><?php endif; ?>
                </a>


                <div class="header-user">


                    <div class="header-avatar">

                        <?= htmlspecialchars(
                            $initials
                        ) ?>

                    </div>


                    <div>

                        <strong>

                            <?= htmlspecialchars(
                                $user["full_name"]
                                ?? "Teacher"
                            ) ?>

                        </strong>

                        <small>
                            TEACHER
                        </small>

                    </div>


                </div>


            </div>


        </header>



        <!-- PAGE CONTENT -->

        <main>


            <!-- WELCOME -->

            <div class="welcome">


                <div class="role-badge">
                    👨‍🏫 TEACHER
                </div>


                <h2>

                    Welcome back,

                    <span>

                        <?= htmlspecialchars(
                            $user["full_name"]
                            ?? "Teacher"
                        ) ?>

                    </span>

                    👋

                </h2>


                <p>

                    Welcome to your MySchool
                    teacher dashboard. Manage your
                    classes, attendance, examination
                    results and timetable from one
                    place.

                </p>


            </div>



            <!-- STATISTICS -->

            <div class="stats">


                <div class="stat-card">

                    <div class="stat-icon">
                        👨‍🏫
                    </div>

                    <div>

                        <small>
                            Account
                        </small>

                        <strong>
                            Active
                        </strong>

                    </div>

                </div>


                <div class="stat-card">

                    <div class="stat-icon">
                        📚
                    </div>

                    <div>

                        <small>
                            Timetable
                        </small>

                        <strong>

                            <?= count(
                                $timetable
                            ) ?>

                            Classes

                        </strong>

                    </div>

                </div>


                <div class="stat-card">

                    <div class="stat-icon">
                        📝
                    </div>

                    <div>

                        <small>
                            Role
                        </small>

                        <strong>
                            Teacher
                        </strong>

                    </div>

                </div>


                <div class="stat-card">

                    <div class="stat-icon">
                        🎓
                    </div>

                    <div>

                        <small>
                            Academic
                        </small>

                        <strong>
                            Available
                        </strong>

                    </div>

                </div>


            </div>



            <!-- TWO COLUMN CONTENT -->

            <div class="content-grid">


                <!-- LEFT COLUMN -->

                <div>


                    <!-- TEACHER INFORMATION -->

                    <div
                        class="card"
                        id="profile"
                    >


                        <div class="card-header">


                            <div class="card-title">


                                <div class="card-title-icon">
                                    👨‍🏫
                                </div>


                                <div>

                                    <h3>
                                        Teacher Information
                                    </h3>

                                    <p>
                                        Your registered details
                                    </p>

                                </div>


                            </div>


                            <div class="badge">
                                👨‍🏫 TEACHER
                            </div>


                        </div>


                        <div class="info-grid">


                            <div class="info-item">

                                <small>
                                    Full Name
                                </small>

                                <strong>

                                    <?= htmlspecialchars(
                                        $user["full_name"]
                                        ?? "Not provided"
                                    ) ?>

                                </strong>

                            </div>


                            <div class="info-item">

                                <small>
                                    TSC Number
                                </small>

                                <strong>

                                    <?= htmlspecialchars(
                                        $user["tsc_number"]
                                        ?? "Not provided"
                                    ) ?>

                                </strong>

                            </div>


                            <div class="info-item">

                                <small>
                                    Specialization
                                </small>

                                <strong>

                                    <?= htmlspecialchars(
                                        $user["specialization"]
                                        ?? "Not provided"
                                    ) ?>

                                </strong>

                            </div>


                            <div class="info-item">

                                <small>
                                    Email Address
                                </small>

                                <strong>

                                    <?= htmlspecialchars(
                                        $user["teacher_email"]
                                        ?? $user["email"]
                                        ?? "Not provided"
                                    ) ?>

                                </strong>

                            </div>


                            <div class="info-item">

                                <small>
                                    Phone Number
                                </small>

                                <strong>

                                    <?= htmlspecialchars(
                                        $user["phone_number"]
                                        ?? $user["user_phone"]
                                        ?? "Not provided"
                                    ) ?>

                                </strong>

                            </div>


                            <div class="info-item">

                                <small>
                                    Account Role
                                </small>

                                <strong>
                                    Teacher
                                </strong>

                            </div>


                        </div>


                    </div>



                    <!-- TIMETABLE -->

                    <div
                        class="card"
                        id="timetable"
                    >


                        <div class="card-header">


                            <div class="card-title">


                                <div class="card-title-icon">
                                    🗓️
                                </div>


                                <div>

                                    <h3>
                                        My Timetable
                                    </h3>

                                    <p>
                                        Your scheduled classes and subjects
                                    </p>

                                </div>


                            </div>


                            <div class="badge">

                                <?= count(
                                    $timetable
                                ) ?>

                                LESSONS

                            </div>


                        </div>



                        <?php if (empty($timetable)): ?>


                            <div class="empty">


                                <div class="empty-icon">
                                    🗓️
                                </div>


                                <h4>
                                    No timetable assigned
                                </h4>


                                <p>

                                    No timetable has been
                                    assigned to you yet.

                                </p>


                            </div>


                        <?php else: ?>


                            <div class="timetable-list">


                                <?php foreach (
                                    $timetable
                                    as $item
                                ): ?>


                                    <div
                                        class="timetable-item"
                                    >


                                        <div
                                            class="timetable-top"
                                        >


                                            <div>


                                                <div
                                                    class="subject-name"
                                                >

                                                    <?= htmlspecialchars(
                                                        $item["subject_name"]
                                                    ) ?>

                                                </div>


                                                <span
                                                    class="subject-code"
                                                >

                                                    <?= htmlspecialchars(
                                                        $item["subject_code"]
                                                    ) ?>

                                                </span>


                                            </div>


                                            <div class="day">

                                                <?= htmlspecialchars(
                                                    $item["day_of_week"]
                                                ) ?>

                                            </div>


                                        </div>



                                        <div
                                            class="timetable-details"
                                        >


                                            <div
                                                class="time-detail"
                                            >

                                                📚

                                                <span>

                                                    Class:

                                                    <strong>

                                                        <?= htmlspecialchars(
                                                            $item["class_name"]
                                                        ) ?>

                                                        -

                                                        <?= htmlspecialchars(
                                                            $item["stream_name"]
                                                        ) ?>

                                                    </strong>

                                                </span>

                                            </div>


                                            <div
                                                class="time-detail"
                                            >

                                                ⏰

                                                <span>

                                                    Time:

                                                    <strong>

                                                        <?= htmlspecialchars(
                                                            date(
                                                                "h:i A",
                                                                strtotime(
                                                                    $item["start_time"]
                                                                )
                                                            )
                                                        ) ?>

                                                        -

                                                        <?= htmlspecialchars(
                                                            date(
                                                                "h:i A",
                                                                strtotime(
                                                                    $item["end_time"]
                                                                )
                                                            )
                                                        ) ?>

                                                    </strong>

                                                </span>

                                            </div>


                                        </div>


                                    </div>


                                <?php endforeach; ?>


                            </div>


                        <?php endif; ?>


                    </div>


                </div>



                <!-- RIGHT COLUMN -->

                <div>


                    <!-- QUICK ACTIONS -->

                    <div class="card">


                        <div class="card-header">


                            <div class="card-title">


                                <div class="card-title-icon">
                                    ⚡
                                </div>


                                <div>

                                    <h3>
                                        Quick Actions
                                    </h3>

                                    <p>
                                        Frequently used features
                                    </p>

                                </div>


                            </div>


                        </div>



                        <div class="actions-grid">


                            <div
                                class="action-card"
                                id="classes"
                            >

                                <div class="action-icon">
                                    📚
                                </div>

                                <h4>
                                    My Classes
                                </h4>

                                <p>
                                    View classes and streams assigned to you.
                                </p>

                            </div>


                            <div class="action-card">

                                <div class="action-icon">
                                    📖
                                </div>

                                <h4>
                                    My Subjects
                                </h4>

                                <p>
                                    Manage your teaching subjects.
                                </p>

                            </div>


                            <div
                                class="action-card"
                                id="attendance"
                            >

                                <div class="action-icon">
                                    📋
                                </div>

                                <h4>
                                    Attendance
                                </h4>

                                <p>
                                    Record and manage student attendance.
                                </p>

                            </div>


                            <div
                                class="action-card"
                                id="results"
                            >

                                <div class="action-icon">
                                    📝
                                </div>

                                <h4>
                                    Exam Results
                                </h4>

                                <p>
                                    Enter and manage examination results.
                                </p>

                            </div>


                            <a href="messages.php" class="action-card">

                                <div class="action-icon">
                                    💬
                                </div>

                                <h4>
                                    Messages
                                </h4>

                                <p>
                                    Communicate with parents and school.
                                </p>

                            </a>


                            <div class="action-card">

                                <div class="action-icon">
                                    🚪
                                </div>

                                <h4>
                                    Logout
                                </h4>

                                <p>
                                    Securely sign out of your account.
                                </p>

                            </div>


                        </div>


                    </div>



                    <!-- TEACHER STATUS -->

                    <div class="card">


                        <div class="card-header">


                            <div class="card-title">


                                <div class="card-title-icon">
                                    🔔
                                </div>


                                <div>

                                    <h3>
                                        Account Status
                                    </h3>

                                    <p>
                                        Your account information
                                    </p>

                                </div>


                            </div>


                        </div>


                        <div class="info-item">

                            <small>
                                Account Status
                            </small>

                            <strong>
                                🟢 Active
                            </strong>

                        </div>


                        <br>


                        <div class="info-item">

                            <small>
                                Access Level
                            </small>

                            <strong>
                                Teacher Portal
                            </strong>

                        </div>


                    </div>


                </div>


            </div>


        </main>


    </section>


</div>


</body>

</html>