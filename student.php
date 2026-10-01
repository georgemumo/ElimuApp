<?php

session_start();

require_once "db.php";
require_once __DIR__ . "/message_helpers.php";


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
| CHECK ROLE
|--------------------------------------------------------------------------
*/

if ($_SESSION["role"] !== "STUDENT") {

    header("Location: login.php");

    exit;
}


/*
|--------------------------------------------------------------------------
| GET USER ID
|--------------------------------------------------------------------------
*/

$user_id = $_SESSION["user_id"];
$unreadMessageCount = unreadMessageCount($conn, (int) $user_id);


/*
|--------------------------------------------------------------------------
| GET STUDENT INFORMATION
|--------------------------------------------------------------------------
*/

$stmt = $conn->prepare("
    SELECT
        s.student_id,
        s.full_name,
        s.adm_number,
        s.gender,
        s.date_of_birth,
        s.status,
        c.class_name,
        c.stream_name,
        u.email,
        u.phone_number
    FROM students s

    INNER JOIN classes c
        ON s.class_id = c.class_id

    INNER JOIN users u
        ON s.user_id = u.user_id

    WHERE s.user_id = ?

    LIMIT 1
");


$stmt->execute([
    $user_id
]);


$student = $stmt->fetch(PDO::FETCH_ASSOC);


/*
|--------------------------------------------------------------------------
| DEFAULT VALUES
|--------------------------------------------------------------------------
*/

$student_name =
    $student["full_name"] ?? "Student";

$admission_number =
    $student["adm_number"] ?? "Not available";

$class_name =
    $student["class_name"] ?? "Not assigned";

$stream_name =
    $student["stream_name"] ?? "Not assigned";

$gender =
    $student["gender"] ?? "Not provided";

$status =
    $student["status"] ?? "ACTIVE";

$email =
    $student["email"] ?? "Not provided";

$phone =
    $student["phone_number"] ?? "Not provided";


/*
|--------------------------------------------------------------------------
| FORMAT STATUS
|--------------------------------------------------------------------------
*/

$status_display =
    ucfirst(
        strtolower($status)
    );


/*
|--------------------------------------------------------------------------
| FORMAT GENDER
|--------------------------------------------------------------------------
*/

$gender_display =
    ucfirst(
        strtolower($gender)
    );


/*
|--------------------------------------------------------------------------
| CLASS DISPLAY
|--------------------------------------------------------------------------
*/

$class_display =
    $class_name . " - " . $stream_name;


/*
|--------------------------------------------------------------------------
| DATE OF BIRTH
|--------------------------------------------------------------------------
*/

$date_of_birth = "Not provided";

if (
    !empty($student["date_of_birth"])
) {

    $date_of_birth =
        date(
            "d M Y",
            strtotime(
                $student["date_of_birth"]
            )
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

    <title>
        Student Dashboard - MySchool
    </title>


    <style>

        /*
        |--------------------------------------------------------------------------
        | RESET
        |--------------------------------------------------------------------------
        */

        * {

            margin: 0;

            padding: 0;

            box-sizing: border-box;

        }


        /*
        |--------------------------------------------------------------------------
        | BODY
        |--------------------------------------------------------------------------
        */

        body {

            font-family:
                "Segoe UI",
                Arial,
                sans-serif;

            background:
                #eef3f8;

            color:
                #102d47;

            min-height: 100vh;

            font-size: 15px;

        }


        /*
        |--------------------------------------------------------------------------
        | MAIN LAYOUT
        |--------------------------------------------------------------------------
        */

        .app {

            display: flex;

            min-height: 100vh;

        }


        /*
        |--------------------------------------------------------------------------
        | SIDEBAR
        |--------------------------------------------------------------------------
        */

        .sidebar {

            width: 250px;

            background:
                linear-gradient(
                    180deg,
                    #103553 0%,
                    #0b5578 100%
                );

            color: white;

            position: fixed;

            left: 0;

            top: 0;

            bottom: 0;

            display: flex;

            flex-direction: column;

            z-index: 100;

        }


        /*
        |--------------------------------------------------------------------------
        | LOGO
        |--------------------------------------------------------------------------
        */

        .logo {

            height: 100px;

            display: flex;

            align-items: center;

            padding:
                0 25px;

            gap: 13px;

        }


        .logo-icon {

            width: 45px;

            height: 45px;

            border-radius: 12px;

            background:
                rgba(
                    255,
                    255,
                    255,
                    .14
                );

            display: flex;

            align-items: center;

            justify-content: center;

            font-size: 24px;

        }


        .logo-text {

            font-size: 23px;

            font-weight: 800;

            letter-spacing: -.5px;

        }


        /*
        |--------------------------------------------------------------------------
        | MENU TITLE
        |--------------------------------------------------------------------------
        */

        .menu-title {

            font-size: 12px;

            font-weight: 800;

            color:
                rgba(
                    255,
                    255,
                    255,
                    .55
                );

            letter-spacing: 1px;

            padding:
                0 27px;

            margin-bottom: 10px;

        }


        /*
        |--------------------------------------------------------------------------
        | NAVIGATION
        |--------------------------------------------------------------------------
        */

        .nav {

            padding:
                0 15px;

        }


        .nav a,
        .nav-toggle {

            text-decoration: none;

            color:
                rgba(
                    255,
                    255,
                    255,
                    .82
                );

            display: flex;

            align-items: center;

            gap: 15px;

            padding:
                14px 15px;

            border-radius: 11px;

            margin-bottom: 5px;

            font-size: 15px;

            font-weight: 600;

            transition:
                .2s ease;

        }


        .nav a:hover,
        .nav-toggle:hover {

            background:
                rgba(
                    255,
                    255,
                    255,
                    .10
                );

            color: white;

        }


        .nav a.active {

            background:
                rgba(
                    255,
                    255,
                    255,
                    .14
                );

            color: white;

            border-left:
                3px solid #35c9ff;

            padding-left:
                12px;

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
            transition: transform .2s ease;
        }

        .nav-toggle[aria-expanded="true"] .nav-chevron {
            transform: rotate(180deg);
        }

        .submenu {
            display: none;
            margin: -2px 0 5px 42px;
            padding-left: 10px;
            border-left: 1px solid rgba(255,255,255,.2);
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

            width: 24px;

            text-align: center;

            font-size: 19px;

        }


        /*
        |--------------------------------------------------------------------------
        | SIDEBAR BOTTOM
        |--------------------------------------------------------------------------
        */

        .sidebar-bottom {

            margin-top: auto;

            padding:
                20px 17px 25px;

            border-top:
                1px solid
                rgba(
                    255,
                    255,
                    255,
                    .12
                );

        }


        .user-mini {

            display: flex;

            align-items: center;

            gap: 11px;

            padding:
                0 7px;

            margin-bottom: 20px;

        }


        .avatar {

            width: 42px;

            height: 42px;

            border-radius: 10px;

            background:
                #20b8ec;

            color: white;

            display: flex;

            align-items: center;

            justify-content: center;

            font-weight: 800;

            font-size: 14px;

        }


        .user-mini-name {

            font-size: 14px;

            font-weight: 700;

        }


        .user-mini-role {

            font-size: 11px;

            color:
                rgba(
                    255,
                    255,
                    255,
                    .65
                );

            margin-top: 3px;

        }


        .logout-link {

            color:
                rgba(
                    255,
                    255,
                    255,
                    .82
                );

            text-decoration: none;

            display: flex;

            gap: 13px;

            align-items: center;

            font-size: 15px;

            font-weight: 600;

            padding:
                8px;

        }


        /*
        |--------------------------------------------------------------------------
        | MAIN AREA
        |--------------------------------------------------------------------------
        */

        .main {

            margin-left: 250px;

            width:
                calc(
                    100% - 250px
                );

            min-height: 100vh;

        }


        /*
        |--------------------------------------------------------------------------
        | TOPBAR
        |--------------------------------------------------------------------------
        */

        .topbar {

            height: 82px;

            background:
                white;

            border-bottom:
                1px solid #e1e8ef;

            display: flex;

            align-items: center;

            justify-content:
                space-between;

            padding:
                0 32px;

        }


        .page-title h1 {

            font-size: 23px;

            color:
                #102d47;

            margin-bottom: 4px;

        }


        .page-title p {

            font-size: 13px;

            color:
                #7a8da1;

        }


        .top-right {

            display: flex;

            align-items: center;

            gap: 14px;

        }


        .notification {

            width: 43px;

            height: 43px;

            border-radius: 11px;

            background:
                #f8fafc;

            border:
                1px solid #e5ebf1;

            display: flex;

            align-items: center;

            justify-content: center;

            font-size: 20px;

        }


        .top-user {

            display: flex;

            align-items: center;

            gap: 10px;

        }


        .top-avatar {

            width: 42px;

            height: 42px;

            border-radius: 10px;

            background:
                #2678e8;

            color: white;

            display: flex;

            align-items: center;

            justify-content: center;

            font-size: 13px;

            font-weight: 800;

        }


        .top-user-name {

            font-size: 14px;

            font-weight: 700;

            color:
                #263f58;

        }


        .top-user-role {

            font-size: 11px;

            color:
                #8a9bad;

            margin-top: 3px;

        }


        /*
        |--------------------------------------------------------------------------
        | CONTENT
        |--------------------------------------------------------------------------
        */

        .content {

            padding:
                30px 32px 40px;

        }


        /*
        |--------------------------------------------------------------------------
        | WELCOME
        |--------------------------------------------------------------------------
        */

        .welcome {

            min-height: 185px;

            border-radius: 19px;

            padding:
                30px 32px;

            margin-bottom: 21px;

            position: relative;

            overflow: hidden;

            background:
                linear-gradient(
                    115deg,
                    #103c5d,
                    #07628b
                );

            color: white;

        }


        .welcome::after {

            content: "";

            position: absolute;

            width: 280px;

            height: 280px;

            border-radius: 50%;

            right: -90px;

            top: -140px;

            background:
                rgba(
                    72,
                    193,
                    255,
                    .13
                );

        }


        .welcome::before {

            content: "";

            position: absolute;

            width: 180px;

            height: 180px;

            border-radius: 50%;

            right: 70px;

            bottom: -140px;

            background:
                rgba(
                    255,
                    255,
                    255,
                    .05
                );

        }


        .role-badge {

            display: inline-flex;

            align-items: center;

            gap: 7px;

            padding:
                6px 13px;

            border-radius: 20px;

            background:
                rgba(
                    255,
                    255,
                    255,
                    .15
                );

            font-size: 12px;

            font-weight: 800;

            margin-bottom: 15px;

        }


        .welcome h2 {

            font-size: 31px;

            margin-bottom: 10px;

            position: relative;

            z-index: 2;

        }


        .welcome h2 span {

            color:
                #66d1ff;

        }


        .welcome p {

            font-size: 15px;

            line-height: 1.7;

            color:
                rgba(
                    255,
                    255,
                    255,
                    .82
                );

            max-width: 750px;

            position: relative;

            z-index: 2;

        }


        /*
        |--------------------------------------------------------------------------
        | STATS
        |--------------------------------------------------------------------------
        */

        .stats {

            display: grid;

            grid-template-columns:
                repeat(
                    4,
                    1fr
                );

            gap: 17px;

            margin-bottom: 21px;

        }


        .stat-card {

            background:
                white;

            border:
                1px solid #e1e8ef;

            border-radius: 15px;

            min-height: 90px;

            display: flex;

            align-items: center;

            gap: 13px;

            padding:
                16px 18px;

            box-shadow:
                0 3px 12px
                rgba(
                    16,
                    45,
                    71,
                    .035
                );

        }


        .stat-icon {

            width: 45px;

            height: 45px;

            border-radius: 11px;

            background:
                #edf5ff;

            display: flex;

            align-items: center;

            justify-content: center;

            font-size: 20px;

            flex-shrink: 0;

        }


        .stat-label {

            font-size: 11px;

            color:
                #8b9baa;

            margin-bottom: 5px;

            font-weight: 700;

        }


        .stat-value {

            font-size: 17px;

            font-weight: 800;

            color:
                #102d47;

            word-break: break-word;

        }


        /*
        |--------------------------------------------------------------------------
        | LOWER GRID
        |--------------------------------------------------------------------------
        */

        .lower-grid {

            display: grid;

            grid-template-columns:
                minmax(
                    0,
                    1.65fr
                )
                minmax(
                    310px,
                    .9fr
                );

            gap: 21px;

        }


        /*
        |--------------------------------------------------------------------------
        | CARD
        |--------------------------------------------------------------------------
        */

        .card {

            background:
                white;

            border:
                1px solid #e1e8ef;

            border-radius: 17px;

            padding:
                22px;

            box-shadow:
                0 3px 12px
                rgba(
                    16,
                    45,
                    71,
                    .035
                );

        }


        .card-header {

            display: flex;

            justify-content:
                space-between;

            align-items: center;

            border-bottom:
                1px solid #e7edf3;

            padding-bottom:
                16px;

            margin-bottom:
                18px;

        }


        .card-title {

            display: flex;

            align-items: center;

            gap: 12px;

        }


        .card-title-icon {

            width: 43px;

            height: 43px;

            border-radius: 11px;

            background:
                #edf5ff;

            display: flex;

            align-items: center;

            justify-content: center;

            font-size: 20px;

        }


        .card-title h3 {

            font-size: 17px;

            margin-bottom: 4px;

            color:
                #163852;

        }


        .card-title p {

            font-size: 12px;

            color:
                #8d9dab;

        }


        .badge {

            background:
                #edf5ff;

            color:
                #1764c0;

            padding:
                6px 11px;

            border-radius: 15px;

            font-size: 11px;

            font-weight: 800;

        }


        /*
        |--------------------------------------------------------------------------
        | INFORMATION GRID
        |--------------------------------------------------------------------------
        */

        .info-grid {

            display: grid;

            grid-template-columns:
                repeat(
                    2,
                    1fr
                );

            gap: 13px;

        }


        .info-item {

            background:
                #f7f9fb;

            border:
                1px solid #edf1f5;

            border-radius: 11px;

            padding:
                15px;

        }


        .info-item small {

            display: block;

            color:
                #7f909f;

            font-size: 11px;

            font-weight: 700;

            text-transform:
                uppercase;

            margin-bottom: 7px;

            letter-spacing: .3px;

        }


        .info-item strong {

            font-size: 15px;

            color:
                #1a3a57;

            word-break:
                break-word;

            line-height: 1.4;

        }


        /*
        |--------------------------------------------------------------------------
        | ACADEMIC PROGRESS
        |--------------------------------------------------------------------------
        */

        .progress-box {

            margin-top: 19px;

            padding:
                18px;

            background:
                linear-gradient(
                    135deg,
                    #eff8ff,
                    #f5fbff
                );

            border:
                1px solid #dceeff;

            border-radius: 13px;

        }


        .progress-top {

            display: flex;

            justify-content:
                space-between;

            align-items: center;

            margin-bottom: 10px;

        }


        .progress-title {

            font-size: 14px;

            font-weight: 800;

            color:
                #163c5c;

        }


        .progress-text {

            font-size: 12px;

            color:
                #64819b;

        }


        .progress-line {

            height: 8px;

            background:
                #dce9f4;

            border-radius: 10px;

            overflow: hidden;

        }


        .progress-line span {

            display: block;

            height: 100%;

            width: 0%;

            background:
                linear-gradient(
                    90deg,
                    #2678e8,
                    #20b8ec
                );

            border-radius: 10px;

        }


        .progress-note {

            font-size: 12px;

            color:
                #6f8498;

            margin-top: 10px;

            line-height: 1.5;

        }


        /*
        |--------------------------------------------------------------------------
        | QUICK ACTIONS
        |--------------------------------------------------------------------------
        */

        .actions-grid {

            display: grid;

            grid-template-columns:
                repeat(
                    2,
                    1fr
                );

            gap: 12px;

        }


        .action {

            border:
                1px solid #e3eaf1;

            border-radius: 12px;

            padding:
                16px;

            text-decoration: none;

            color:
                #183a58;

            transition:
                .2s ease;

        }


        .action:hover {

            transform:
                translateY(-2px);

            border-color:
                #c6dff4;

            box-shadow:
                0 5px 15px
                rgba(
                    30,
                    100,
                    160,
                    .08
                );

        }


        .action-icon {

            width: 38px;

            height: 38px;

            border-radius: 10px;

            background:
                #edf5ff;

            display: flex;

            align-items: center;

            justify-content: center;

            margin-bottom: 10px;

            font-size: 18px;

        }


        .action h4 {

            font-size: 14px;

            margin-bottom: 6px;

        }


        .action p {

            font-size: 12px;

            color:
                #8799aa;

            line-height: 1.5;

        }


        /*
        |--------------------------------------------------------------------------
        | RESPONSIVE
        |--------------------------------------------------------------------------
        */

        @media (max-width: 1100px) {

            .stats {

                grid-template-columns:
                    repeat(
                        2,
                        1fr
                    );

            }

            .lower-grid {

                grid-template-columns:
                    1fr;

            }

        }


        @media (max-width: 760px) {

            .sidebar {

                width: 75px;

            }

            .logo {

                justify-content:
                    center;

                padding: 0;

            }

            .logo-text,
            .menu-title,
            .nav a span,
            .user-mini > div,
            .logout-link span {

                display: none;

            }

            .nav a {

                justify-content:
                    center;

                padding:
                    14px;

            }

            .nav-icon {

                width: auto;

            }

            .sidebar-bottom {

                padding:
                    15px 10px;

            }

            .user-mini {

                justify-content:
                    center;

            }

            .logout-link {

                justify-content:
                    center;

            }

            .main {

                margin-left: 75px;

                width:
                    calc(
                        100% - 75px
                    );

            }

            .content {

                padding:
                    20px;

            }

            .topbar {

                padding:
                    0 20px;

            }

            .info-grid {

                grid-template-columns:
                    1fr;

            }

        }


        @media (max-width: 560px) {

            .stats {

                grid-template-columns:
                    1fr;

            }

            .welcome {

                padding:
                    25px;

            }

            .welcome h2 {

                font-size: 25px;

            }

            .welcome p {

                font-size: 14px;

            }

            .actions-grid {

                grid-template-columns:
                    1fr;

            }

            .top-user-name,
            .top-user-role {

                display: none;

            }

            .page-title h1 {

                font-size: 20px;

            }

            .card {

                padding: 18px;

            }

        }

    </style>

</head>


<body>


<div class="app">


    <!-- =========================================================
         SIDEBAR
    ========================================================== -->

    <aside class="sidebar">


        <div class="logo">

            <div class="logo-icon">

                🎓

            </div>

            <div class="logo-text">

                MySchool

            </div>

        </div>


        <div class="menu-title">

            MAIN MENU

        </div>


        <nav class="nav">


            <a
                href="student.php"
                class="active"
            >

                <div class="nav-icon">
                    🏠
                </div>

                <span>
                    Dashboard
                </span>

            </a>


            <a href="#profile">

                <div class="nav-icon">
                    👤
                </div>

                <span>
                    Profile
                </span>

            </a>


            <button type="button" class="nav-toggle" aria-expanded="false" aria-controls="student-academics-menu">

                <div class="nav-icon">
                    📚
                </div>

                <span>
                    Academics
                </span>

                <span class="nav-chevron">⌄</span>

            </button>

            <div class="submenu" id="student-academics-menu">
                <a href="academics/student/index.php">Academic Dashboard</a>
                <a href="academics/student/timetable.php">Timetable</a>
                <a href="academics/student/assignments.php">Assignments</a>
                <a href="academics/student/submit_assignment.php">Submit Assignment</a>
                <a href="academics/student/resources.php">Learning Resources</a>
                <a href="academics/student/results.php">Results</a>
                <a href="academics/student/performance.php">Performance</a>
                <a href="academics/student/exams.php">Exams</a>
                <a href="academics/student/cbc.php">CBC Progress</a>
                <a href="academics/student/achievements.php">Achievements</a>
            </div>


            <a href="#fees">

                <div class="nav-icon">
                    💰
                </div>

                <span>
                    Fees
                </span>

            </a>


            <a href="messages.php">

                <div class="nav-icon">
                    💬
                </div>

                <span>
                    Messages
                </span>

            </a>


            <a href="#calendar">

                <div class="nav-icon">
                    📅
                </div>

                <span>
                    Calendar
                </span>

            </a>


        </nav>


        <div class="sidebar-bottom">


            <div class="user-mini">


                <div class="avatar">

                    <?php

                    echo strtoupper(
                        substr(
                            $student_name,
                            0,
                            2
                        )
                    );

                    ?>

                </div>


                <div>

                    <div class="user-mini-name">

                        <?= htmlspecialchars(
                            $student_name
                        ) ?>

                    </div>


                    <div class="user-mini-role">

                        STUDENT

                    </div>

                </div>


            </div>


            <a
                href="logout.php"
                class="logout-link"
            >

                <span>
                    🚪
                </span>

                <span>
                    Logout
                </span>

            </a>


        </div>


    </aside>


    <!-- =========================================================
         MAIN
    ========================================================== -->

    <section class="main">


        <!-- =====================================================
             TOPBAR
        ====================================================== -->

        <header class="topbar">


            <div class="page-title">

                <h1>
                    Dashboard
                </h1>

                <p>
                    School Management System
                </p>

            </div>


            <div class="top-right">


                <a class="notification" href="messages.php" aria-label="Messages, <?= $unreadMessageCount ?> unread" style="position:relative;color:inherit;text-decoration:none">
                    🔔
                    <?php if ($unreadMessageCount > 0): ?><span style="position:absolute;top:-6px;right:-6px;min-width:18px;height:18px;padding:0 4px;border-radius:9px;background:#d84242;color:#fff;font-size:10px;font-weight:800;line-height:18px;text-align:center"><?= $unreadMessageCount > 99 ? '99+' : $unreadMessageCount ?></span><?php endif; ?>
                </a>


                <div class="top-user">


                    <div class="top-avatar">

                        <?php

                        echo strtoupper(
                            substr(
                                $student_name,
                                0,
                                2
                            )
                        );

                        ?>

                    </div>


                    <div>

                        <div class="top-user-name">

                            <?= htmlspecialchars(
                                $student_name
                            ) ?>

                        </div>


                        <div class="top-user-role">

                            STUDENT

                        </div>

                    </div>


                </div>


            </div>


        </header>


        <!-- =====================================================
             CONTENT
        ====================================================== -->

        <main class="content">


            <!-- =================================================
                 WELCOME
            ================================================== -->

            <section class="welcome">


                <div class="role-badge">

                    🎓 STUDENT

                </div>


                <h2>

                    Welcome back,
                    <span>
                        <?= htmlspecialchars(
                            $student_name
                        ) ?>
                    </span>
                    👋

                </h2>


                <p>

                    Welcome to your MySchool student
                    dashboard. View your academic
                    information, attendance, fees and
                    other school activities from one place.

                </p>


            </section>


            <!-- =================================================
                 STATS
            ================================================== -->

            <section class="stats">


                <div class="stat-card">


                    <div class="stat-icon">

                        👤

                    </div>


                    <div>

                        <div class="stat-label">

                            ACCOUNT

                        </div>

                        <div class="stat-value">

                            <?= htmlspecialchars(
                                $status_display
                            ) ?>

                        </div>

                    </div>


                </div>


                <div class="stat-card">


                    <div class="stat-icon">

                        🎓

                    </div>


                    <div>

                        <div class="stat-label">

                            ADMISSION NUMBER

                        </div>

                        <div class="stat-value">

                            <?= htmlspecialchars(
                                $admission_number
                            ) ?>

                        </div>

                    </div>


                </div>


                <div class="stat-card">


                    <div class="stat-icon">

                        📚

                    </div>


                    <div>

                        <div class="stat-label">

                            CLASS

                        </div>

                        <div class="stat-value">

                            <?= htmlspecialchars(
                                $class_name
                            ) ?>

                        </div>

                    </div>


                </div>


                <div class="stat-card">


                    <div class="stat-icon">

                        📊

                    </div>


                    <div>

                        <div class="stat-label">

                            ACADEMICS

                        </div>

                        <div class="stat-value">

                            Available

                        </div>

                    </div>


                </div>


            </section>


            <!-- =================================================
                 LOWER CONTENT
            ================================================== -->

            <div class="lower-grid">


                <!-- =============================================
                     LEFT CARD
                ============================================== -->

                <section
                    class="card"
                    id="profile"
                >


                    <div class="card-header">


                        <div class="card-title">


                            <div class="card-title-icon">

                                👤

                            </div>


                            <div>

                                <h3>

                                    Student Information

                                </h3>


                                <p>

                                    Your registered details

                                </p>

                            </div>


                        </div>


                        <div class="badge">

                            🎓 STUDENT

                        </div>


                    </div>


                    <div class="info-grid">


                        <div class="info-item">


                            <small>
                                Full Name
                            </small>


                            <strong>

                                <?= htmlspecialchars(
                                    $student_name
                                ) ?>

                            </strong>


                        </div>


                        <div class="info-item">


                            <small>
                                Admission Number
                            </small>


                            <strong>

                                <?= htmlspecialchars(
                                    $admission_number
                                ) ?>

                            </strong>


                        </div>


                        <div class="info-item">


                            <small>
                                Email Address
                            </small>


                            <strong>

                                <?= htmlspecialchars(
                                    $email
                                ) ?>

                            </strong>


                        </div>


                        <div class="info-item">


                            <small>
                                Phone Number
                            </small>


                            <strong>

                                <?= htmlspecialchars(
                                    $phone
                                ) ?>

                            </strong>


                        </div>


                        <div class="info-item">


                            <small>
                                Class
                            </small>


                            <strong>

                                <?= htmlspecialchars(
                                    $class_display
                                ) ?>

                            </strong>


                        </div>


                        <div class="info-item">


                            <small>
                                Gender
                            </small>


                            <strong>

                                <?= htmlspecialchars(
                                    $gender_display
                                ) ?>

                            </strong>


                        </div>


                        <div class="info-item">


                            <small>
                                Date of Birth
                            </small>


                            <strong>

                                <?= htmlspecialchars(
                                    $date_of_birth
                                ) ?>

                            </strong>


                        </div>


                        <div class="info-item">


                            <small>
                                Account Status
                            </small>


                            <strong>

                                <?= htmlspecialchars(
                                    $status_display
                                ) ?>

                            </strong>


                        </div>


                    </div>


                    <!-- =========================================
                         ACADEMIC PROGRESS
                    ========================================== -->

                    <div
                        class="progress-box"
                        id="academics"
                    >


                        <div class="progress-top">


                            <div class="progress-title">

                                📚 Academic Progress

                            </div>


                            <div class="progress-text">

                                Results available

                            </div>


                        </div>


                        <div class="progress-line">

                            <span></span>

                        </div>


                        <p class="progress-note">

                            Your examination results,
                            attendance and academic
                            performance will appear here.

                        </p>


                    </div>


                </section>


                <!-- =============================================
                     RIGHT CARD
                ============================================== -->

                <section class="card">


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


                        <a
                            href="#profile"
                            class="action"
                        >

                            <div class="action-icon">

                                👤

                            </div>


                            <h4>

                                Profile

                            </h4>


                            <p>

                                View your personal
                                information.

                            </p>

                        </a>


                        <a
                            href="#academics"
                            class="action"
                        >

                            <div class="action-icon">

                                📚

                            </div>


                            <h4>

                                Academics

                            </h4>


                            <p>

                                View your academic
                                information.

                            </p>

                        </a>


                        <a
                            href="#fees"
                            class="action"
                        >

                            <div class="action-icon">

                                💰

                            </div>


                            <h4>

                                Fees

                            </h4>


                            <p>

                                View your fee
                                information.

                            </p>

                        </a>


                        <a
                            href="messages.php"
                            class="action"
                        >

                            <div class="action-icon">

                                💬

                            </div>


                            <h4>

                                Messages

                            </h4>


                            <p>

                                View your latest
                                messages.

                            </p>

                        </a>


                        <a
                            href="#calendar"
                            class="action"
                        >

                            <div class="action-icon">

                                📅

                            </div>


                            <h4>

                                Calendar

                            </h4>


                            <p>

                                View school events
                                and activities.

                            </p>

                        </a>


                        <a
                            href="logout.php"
                            class="action"
                        >

                            <div class="action-icon">

                                🚪

                            </div>


                            <h4>

                                Logout

                            </h4>


                            <p>

                                Securely sign out
                                of your account.

                            </p>

                        </a>


                    </div>


                </section>


            </div>


        </main>


    </section>


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