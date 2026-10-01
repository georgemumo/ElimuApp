<?php

session_start();

require_once "../../db.php";

error_reporting(E_ALL);
ini_set('display_errors', 1);

/*
|--------------------------------------------------------------------------
| PARENT ACADEMICS DASHBOARD
|--------------------------------------------------------------------------
*/

if (
    !isset($_SESSION["logged_in"]) ||
    $_SESSION["logged_in"] !== true
) {
    header("Location: ../../login.php");
    exit;
}

if (
    !isset($_SESSION["role"]) ||
    strtoupper(trim((string) $_SESSION["role"])) !== "PARENT"
) {
    header("Location: ../../login.php");
    exit;
}

$user_id = $_SESSION["user_id"] ?? null;

if (!$user_id) {
    header("Location: ../../login.php");
    exit;
}

/*
|--------------------------------------------------------------------------
| GET PARENT INFORMATION
|--------------------------------------------------------------------------
*/

try {

    $parentStmt = $conn->prepare("
        SELECT
            u.user_id,
            u.email,
            u.phone_number,
            p.parent_id,
            p.full_name,
            p.phone,
            p.relationship,
            p.profile_pic
        FROM users u
        INNER JOIN parents p ON p.user_id = u.user_id
        WHERE u.user_id = ?
        LIMIT 1
    ");

    $parentStmt->execute([$user_id]);
    $parent = $parentStmt->fetch();

    if (!$parent) {
        die("Parent account information not found.");
    }

    $parent_id = $parent["parent_id"];

} catch (PDOException $e) {

    die(
        "Unable to load parent information: " .
        htmlspecialchars($e->getMessage())
    );
}

/*
|--------------------------------------------------------------------------
| PROFILE PICTURE
|--------------------------------------------------------------------------
*/

$parentName = $parent["full_name"] ?? "Parent";

$nameParts = preg_split('/\s+/', trim($parentName));

$initials = "";

foreach ($nameParts as $part) {
    if (!empty($part)) {
        $initials .= strtoupper(substr($part, 0, 1));
    }

    if (strlen($initials) >= 2) {
        break;
    }
}

if (empty($initials)) {
    $initials = "P";
}

$profilePic = "";

if (!empty($parent["profile_pic"])) {

    $storedPic = trim($parent["profile_pic"]);

    /*
     * Handles paths such as:
     * ./uploads/profile_pics/photo.jpg
     * uploads/profile_pics/photo.jpg
     */

    $cleanPic = preg_replace('#^\./#', '', $storedPic);

    $rootPicPath = __DIR__ . "/../../" . $cleanPic;

    if (file_exists($rootPicPath)) {
        $profilePic = "../../" . $cleanPic;
    }
}

/*
|--------------------------------------------------------------------------
| GET CHILDREN BELONGING TO THIS PARENT
|--------------------------------------------------------------------------
*/

try {

    $childrenStmt = $conn->prepare("
        SELECT
            s.student_id,
            s.adm_number,
            s.full_name,
            s.gender,
            s.date_of_birth,
            s.class_id,
            s.status,
            s.profile_pic,
            c.class_name,
            c.stream_name
        FROM parent_student_map psm
        INNER JOIN students s
            ON s.student_id = psm.student_id
        INNER JOIN classes c
            ON c.class_id = s.class_id
        WHERE psm.parent_id = ?
        ORDER BY s.full_name ASC
    ");

    $childrenStmt->execute([$parent_id]);

    $children = $childrenStmt->fetchAll();

} catch (PDOException $e) {

    die(
        "Unable to load children: " .
        htmlspecialchars($e->getMessage())
    );
}

/*
|--------------------------------------------------------------------------
| ACADEMIC INFORMATION FOR EACH CHILD
|--------------------------------------------------------------------------
*/

foreach ($children as &$child) {

    $student_id = $child["student_id"];

    /*
    |--------------------------------------------------------------------------
    | RESULTS
    |--------------------------------------------------------------------------
    */

    $resultStmt = $conn->prepare("
        SELECT
            AVG(score) AS average_score,
            COUNT(*) AS subject_count
        FROM exam_results
        WHERE student_id = ?
    ");

    $resultStmt->execute([$student_id]);

    $resultData = $resultStmt->fetch();

    $child["average_score"] = $resultData["average_score"] !== null
        ? round((float)$resultData["average_score"], 2)
        : 0;

    $child["subject_count"] = (int)($resultData["subject_count"] ?? 0);


    /*
    |--------------------------------------------------------------------------
    | ATTENDANCE
    |--------------------------------------------------------------------------
    */

    $attendanceStmt = $conn->prepare("
        SELECT
            COUNT(*) AS total_days,
            SUM(
                CASE
                    WHEN status = 'PRESENT' THEN 1
                    ELSE 0
                END
            ) AS present_days
        FROM attendance
        WHERE student_id = ?
    ");

    $attendanceStmt->execute([$student_id]);

    $attendanceData = $attendanceStmt->fetch();

    $totalDays = (int)($attendanceData["total_days"] ?? 0);
    $presentDays = (int)($attendanceData["present_days"] ?? 0);

    if ($totalDays > 0) {
        $child["attendance_percentage"] =
            round(($presentDays / $totalDays) * 100, 1);
    } else {
        $child["attendance_percentage"] = 0;
    }


    /*
    |--------------------------------------------------------------------------
    | ASSIGNMENTS
    |--------------------------------------------------------------------------
    */

    $assignmentStmt = $conn->prepare("
        SELECT COUNT(*) AS total_assignments
        FROM assignments
        WHERE class_id = ?
    ");

    $assignmentStmt->execute([$child["class_id"]]);

    $assignmentData = $assignmentStmt->fetch();

    $child["assignment_count"] =
        (int)($assignmentData["total_assignments"] ?? 0);


    /*
    |--------------------------------------------------------------------------
    | LATEST RESULTS
    |--------------------------------------------------------------------------
    */

    $latestResultsStmt = $conn->prepare("
        SELECT
            er.score,
            er.grade,
            er.term,
            er.academic_year,
            s.subject_name
        FROM exam_results er
        INNER JOIN subjects s
            ON s.subject_id = er.subject_id
        WHERE er.student_id = ?
        ORDER BY er.academic_year DESC, er.result_id DESC
        LIMIT 5
    ");

    $latestResultsStmt->execute([$student_id]);

    $child["latest_results"] = $latestResultsStmt->fetchAll();


    /*
    |--------------------------------------------------------------------------
    | CHILD PROFILE PICTURE
    |--------------------------------------------------------------------------
    */

    $child["profile_url"] = "";

    if (!empty($child["profile_pic"])) {

        $childPic = trim($child["profile_pic"]);

        $cleanChildPic = preg_replace('#^\./#', '', $childPic);

        $childPicPath =
            __DIR__ . "/../../" . $cleanChildPic;

        if (file_exists($childPicPath)) {
            $child["profile_url"] =
                "../../" . $cleanChildPic;
        }
    }
}

unset($child);


/*
|--------------------------------------------------------------------------
| OVERALL SUMMARY
|--------------------------------------------------------------------------
*/

$totalChildren = count($children);

$totalSubjects = 0;
$totalAssignments = 0;
$totalAttendance = 0;
$totalAverage = 0;

foreach ($children as $child) {

    $totalSubjects += $child["subject_count"];

    $totalAssignments += $child["assignment_count"];

    $totalAttendance += $child["attendance_percentage"];

    $totalAverage += $child["average_score"];
}

$overallAverage = $totalChildren > 0
    ? round($totalAverage / $totalChildren, 2)
    : 0;

$overallAttendance = $totalChildren > 0
    ? round($totalAttendance / $totalChildren, 1)
    : 0;


/*
|--------------------------------------------------------------------------
| PERFORMANCE LABEL
|--------------------------------------------------------------------------
*/

function getPerformanceLabel($score)
{
    if ($score >= 80) {
        return "Excellent";
    }

    if ($score >= 70) {
        return "Very Good";
    }

    if ($score >= 60) {
        return "Good";
    }

    if ($score >= 50) {
        return "Average";
    }

    if ($score > 0) {
        return "Needs Improvement";
    }

    return "No Results";
}


/*
|--------------------------------------------------------------------------
| ESCAPE HELPER
|--------------------------------------------------------------------------
*/

function e($value)
{
    return htmlspecialchars(
        (string)$value,
        ENT_QUOTES,
        'UTF-8'
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

    <title>Academic Dashboard - Parent</title>

    <link rel="stylesheet" href="../../assets/css/school.css">

    <style>

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: Arial, Helvetica, sans-serif;
            background: #f4f7fb;
            color: #1f2937;
        }

        a {
            text-decoration: none;
            color: inherit;
        }

        /* SIDEBAR */

        .sidebar {
            position: fixed;
            left: 0;
            top: 0;
            width: 250px;
            height: 100vh;
            background: linear-gradient(
                180deg,
                #0b1f3a,
                #123d6a
            );
            color: white;
            padding: 25px 15px;
            overflow-y: auto;
        }

        .school-logo {
            display: flex;
            align-items: center;
            gap: 13px;
            padding: 0 10px 20px;
        }

        .school-logo-icon {
            width: 45px;
            height: 45px;
            border-radius: 12px;
            background: rgba(255,255,255,.14);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            flex: none;
        }

        .school-logo h2 {
            font-size: 23px;
            margin-bottom: 3px;
            font-weight: 800;
        }

        .school-logo p {
            font-size: 12px;
            opacity: 0.65;
            margin: 0;
        }

        .nav-title {
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 1px;
            opacity: 0.55;
            margin: 20px 10px 8px;
        }

        .nav-link {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 13px 14px;
            border-radius: 9px;
            margin-bottom: 5px;
            font-size: 14px;
            transition: 0.2s;
        }

        .nav-link:hover {
            background: rgba(255,255,255,0.12);
        }

        .nav-link.active {
            background: rgba(255,255,255,0.18);
        }

        .nav-icon {
            width: 25px;
            text-align: center;
            font-size: 18px;
        }

        /* MAIN */

        .main {
            margin-left: 250px;
            min-height: 100vh;
        }

        /* TOP BAR */

        .topbar {
            height: 75px;
            background: white;
            border-bottom: 1px solid #e5e7eb;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 30px;
        }

        .topbar-title h1 {
            font-size: 21px;
            color: #0b1f3a;
        }

        .topbar-title p {
            font-size: 12px;
            color: #6b7280;
            margin-top: 4px;
        }

        .profile-area {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .profile-name {
            font-size: 14px;
            font-weight: bold;
        }

        .top-avatar {
            width: 42px;
            height: 42px;
            border-radius: 50%;
            overflow: hidden;
            background: #dbeafe;
            color: #0b1f3a;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
        }

        .top-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        /* CONTENT */

        .content {
            padding: 30px;
        }

        .welcome {
            background: linear-gradient(
                135deg,
                #0b1f3a,
                #155a91
            );
            color: white;
            padding: 28px;
            border-radius: 14px;
            margin-bottom: 25px;
        }

        .welcome h2 {
            font-size: 25px;
            margin-bottom: 8px;
        }

        .welcome p {
            font-size: 14px;
            opacity: 0.85;
            line-height: 1.6;
        }

        /* SUMMARY CARDS */

        .summary-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 18px;
            margin-bottom: 28px;
        }

        .summary-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            border: 1px solid #e5e7eb;
            box-shadow: 0 3px 12px rgba(0,0,0,0.04);
        }

        .summary-card .icon {
            font-size: 25px;
            margin-bottom: 12px;
        }

        .summary-card h3 {
            font-size: 25px;
            color: #0b1f3a;
            margin-bottom: 5px;
        }

        .summary-card p {
            color: #6b7280;
            font-size: 13px;
        }

        /* SECTION */

        .section-title {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 15px;
        }

        .section-title h2 {
            font-size: 19px;
            color: #0b1f3a;
        }

        .section-title a {
            font-size: 13px;
            color: #155a91;
            font-weight: bold;
        }

        /* CHILDREN */

        .children-grid {
            display: grid;
            grid-template-columns: repeat(
                auto-fit,
                minmax(300px, 1fr)
            );
            gap: 20px;
            margin-bottom: 30px;
        }

        .child-card {
            background: white;
            border-radius: 14px;
            padding: 22px;
            border: 1px solid #e5e7eb;
            box-shadow: 0 3px 12px rgba(0,0,0,0.04);
        }

        .child-header {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 20px;
        }

        .child-avatar {
            width: 58px;
            height: 58px;
            border-radius: 50%;
            overflow: hidden;
            background: #dbeafe;
            color: #0b1f3a;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 19px;
            font-weight: bold;
            flex-shrink: 0;
        }

        .child-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .child-name {
            font-size: 17px;
            font-weight: bold;
            color: #111827;
        }

        .child-class {
            font-size: 12px;
            color: #6b7280;
            margin-top: 4px;
        }

        .academic-stats {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 10px;
            margin-bottom: 18px;
        }

        .academic-stat {
            background: #f8fafc;
            border-radius: 8px;
            padding: 12px 7px;
            text-align: center;
        }

        .academic-stat strong {
            display: block;
            font-size: 18px;
            color: #0b1f3a;
            margin-bottom: 4px;
        }

        .academic-stat span {
            font-size: 10px;
            color: #6b7280;
        }

        .performance-label {
            background: #eef6ff;
            color: #155a91;
            padding: 9px;
            border-radius: 7px;
            font-size: 12px;
            text-align: center;
            margin-bottom: 15px;
        }

        .child-actions {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 8px;
        }

        .action-btn {
            padding: 10px;
            border: 1px solid #dbe3ec;
            border-radius: 7px;
            text-align: center;
            font-size: 12px;
            color: #123d6a;
            background: #f8fafc;
            transition: 0.2s;
        }

        .action-btn:hover {
            background: #eaf3fb;
        }

        /* ACADEMIC MODULES */

        .modules-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 15px;
            margin-bottom: 30px;
        }

        .module {
            background: white;
            padding: 20px;
            border-radius: 12px;
            border: 1px solid #e5e7eb;
            transition: 0.2s;
        }

        .module:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 18px rgba(0,0,0,0.07);
        }

        .module-icon {
            font-size: 27px;
            margin-bottom: 12px;
        }

        .module h3 {
            font-size: 15px;
            margin-bottom: 7px;
            color: #0b1f3a;
        }

        .module p {
            color: #6b7280;
            font-size: 12px;
            line-height: 1.5;
            margin-bottom: 12px;
        }

        .module-link {
            color: #155a91;
            font-size: 12px;
            font-weight: bold;
        }

        /* RESULTS */

        .results-box {
            background: white;
            border: 1px solid #e5e7eb;
            border-radius: 14px;
            padding: 22px;
            margin-bottom: 30px;
        }

        .result-table {
            width: 100%;
            border-collapse: collapse;
        }

        .result-table th,
        .result-table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #edf0f3;
            font-size: 13px;
        }

        .result-table th {
            color: #6b7280;
            font-size: 11px;
            text-transform: uppercase;
        }

        .grade {
            font-weight: bold;
            color: #155a91;
        }

        .empty {
            text-align: center;
            padding: 25px;
            color: #6b7280;
            font-size: 13px;
        }

        /* QUICK LINKS */

        .quick-links {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 12px;
        }

        .quick-link {
            background: white;
            border: 1px solid #e5e7eb;
            padding: 15px;
            border-radius: 10px;
            text-align: center;
            font-size: 12px;
            color: #123d6a;
        }

        /* MOBILE */

        @media (max-width: 1000px) {

            .summary-grid {
                grid-template-columns: repeat(2, 1fr);
            }

            .modules-grid {
                grid-template-columns: repeat(2, 1fr);
            }

            .quick-links {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 700px) {

            .sidebar {
                width: 70px;
                padding: 20px 8px;
            }

            .school-logo h2,
            .school-logo p,
            .nav-title,
            .nav-link span:not(.nav-icon) {
                display: none;
            }

            .nav-link {
                justify-content: center;
                padding: 13px 5px;
            }

            .main {
                margin-left: 70px;
            }

            .content {
                padding: 18px;
            }

            .topbar {
                padding: 0 15px;
            }

            .profile-name {
                display: none;
            }

            .summary-grid {
                grid-template-columns: 1fr 1fr;
                gap: 10px;
            }

            .modules-grid {
                grid-template-columns: 1fr;
            }

            .quick-links {
                grid-template-columns: 1fr 1fr;
            }
        }

        @media (max-width: 450px) {

            .summary-grid {
                grid-template-columns: 1fr;
            }

            .academic-stats {
                grid-template-columns: 1fr 1fr 1fr;
            }

            .child-actions {
                grid-template-columns: 1fr;
            }

            .quick-links {
                grid-template-columns: 1fr;
            }

            .welcome h2 {
                font-size: 20px;
            }

            .result-table {
                min-width: 550px;
            }

            .results-box {
                overflow-x: auto;
            }
        }

    </style>

</head>

<body>

<!-- ================================================================
     SIDEBAR
================================================================ -->

<aside class="sidebar">

    <div class="school-logo">

        <div class="school-logo-icon">🎓</div>

        <div>
            <h2>MySchool</h2>

            <p>Parent Portal</p>
        </div>

    </div>


    <div class="nav-title">
        Main
    </div>

    <a href="../../parent.php" class="nav-link">

        <span class="nav-icon">🏠</span>

        <span>Dashboard</span>

    </a>


    <a href="index.php" class="nav-link active">

        <span class="nav-icon">📚</span>

        <span>Academics</span>

    </a>


    <div class="nav-title">
        Academic
    </div>


    <a href="children.php" class="nav-link">

        <span class="nav-icon">👨‍👩‍👧</span>

        <span>My Children</span>

    </a>


    <a href="results.php" class="nav-link">

        <span class="nav-icon">📊</span>

        <span>Results</span>

    </a>


    <a href="performance.php" class="nav-link">

        <span class="nav-icon">📈</span>

        <span>Performance</span>

    </a>


    <a href="report_card.php" class="nav-link">

        <span class="nav-icon">📄</span>

        <span>Report Cards</span>

    </a>


    <a href="assignments.php" class="nav-link">

        <span class="nav-icon">📝</span>

        <span>Assignments</span>

    </a>


    <a href="timetable.php" class="nav-link">

        <span class="nav-icon">🗓️</span>

        <span>Timetable</span>

    </a>


    <a href="cbc.php" class="nav-link">

        <span class="nav-icon">🎯</span>

        <span>CBC Progress</span>

    </a>


    <a href="resources.php" class="nav-link">

        <span class="nav-icon">📚</span>

        <span>Resources</span>

    </a>


    <a href="achievements.php" class="nav-link">

        <span class="nav-icon">🏆</span>

        <span>Achievements</span>

    </a>

    <div class="nav-title">School</div>

    <a href="../../fees.php" class="nav-link"><span class="nav-icon">💰</span><span>Fees</span></a>
    <a href="../../messages.php" class="nav-link"><span class="nav-icon">✉️</span><span>Messages</span></a>
    <a href="../../calender.php" class="nav-link"><span class="nav-icon">🗓️</span><span>Calendar</span></a>


    <div class="nav-title">
        Account
    </div>


    <a href="../../profile.php" class="nav-link">

        <span class="nav-icon">👤</span>

        <span>Profile</span>

    </a>


    <a href="../../logout.php" class="nav-link">

        <span class="nav-icon">🚪</span>

        <span>Logout</span>

    </a>

</aside>


<!-- ================================================================
     MAIN
================================================================ -->

<main class="main">


    <!-- TOPBAR -->

    <header class="topbar">

        <div class="topbar-title">

            <h1>Academic Dashboard</h1>

            <p>
                Monitor your children's academic progress
            </p>

        </div>


        <div class="profile-area">

            <span class="profile-name">
                <?= e($parentName) ?>
            </span>


            <div class="top-avatar">

                <?php if (!empty($profilePic)): ?>

                    <img
                        src="<?= e($profilePic) ?>"
                        alt="Profile"
                    >

                <?php else: ?>

                    <?= e($initials) ?>

                <?php endif; ?>

            </div>

        </div>

    </header>


    <!-- CONTENT -->

    <div class="content">


        <!-- WELCOME -->

        <section class="welcome">

            <h2>
                Welcome, <?= e($parentName) ?> 👋
            </h2>

            <p>
                Welcome to your Academic Dashboard.
                Here you can monitor your children's results,
                performance, attendance, assignments, timetable,
                CBC progress and other academic activities.
            </p>

        </section>


        <!-- ========================================================
             SUMMARY
        ========================================================= -->

        <div class="summary-grid">


            <div class="summary-card">

                <div class="icon">👨‍👩‍👧</div>

                <h3>
                    <?= $totalChildren ?>
                </h3>

                <p>
                    Registered Children
                </p>

            </div>


            <div class="summary-card">

                <div class="icon">📊</div>

                <h3>
                    <?= e(number_format($overallAverage, 1)) ?>%
                </h3>

                <p>
                    Average Performance
                </p>

            </div>


            <div class="summary-card">

                <div class="icon">📅</div>

                <h3>
                    <?= e(number_format($overallAttendance, 1)) ?>%
                </h3>

                <p>
                    Average Attendance
                </p>

            </div>


            <div class="summary-card">

                <div class="icon">📝</div>

                <h3>
                    <?= $totalAssignments ?>
                </h3>

                <p>
                    Class Assignments
                </p>

            </div>

        </div>


        <!-- ========================================================
             CHILDREN
        ========================================================= -->

        <div class="section-title">

            <h2>
                My Children's Academic Overview
            </h2>

            <a href="children.php">
                View All →
            </a>

        </div>


        <div class="children-grid">

            <?php if (empty($children)): ?>

                <div class="child-card">

                    <div class="empty">

                        <p>
                            No children are currently linked
                            to your parent account.
                        </p>

                    </div>

                </div>

            <?php else: ?>


                <?php foreach ($children as $child): ?>

                    <?php

                    $childName = $child["full_name"];

                    $childParts =
                        preg_split(
                            '/\s+/',
                            trim($childName)
                        );

                    $childInitials = "";

                    foreach ($childParts as $part) {

                        if (!empty($part)) {

                            $childInitials .=
                                strtoupper(
                                    substr($part, 0, 1)
                                );
                        }

                        if (strlen($childInitials) >= 2) {
                            break;
                        }
                    }

                    $performance =
                        getPerformanceLabel(
                            $child["average_score"]
                        );

                    ?>

                    <div class="child-card">


                        <div class="child-header">


                            <div class="child-avatar">

                                <?php if (!empty($child["profile_url"])): ?>

                                    <img
                                        src="<?= e($child["profile_url"]) ?>"
                                        alt="<?= e($childName) ?>"
                                    >

                                <?php else: ?>

                                    <?= e($childInitials ?: "S") ?>

                                <?php endif; ?>

                            </div>


                            <div>

                                <div class="child-name">

                                    <?= e($childName) ?>

                                </div>

                                <div class="child-class">

                                    <?= e($child["class_name"]) ?>

                                    <?php if (!empty($child["stream_name"])): ?>

                                        -
                                        <?= e($child["stream_name"]) ?>

                                    <?php endif; ?>

                                    <br>

                                    Adm:
                                    <?= e($child["adm_number"]) ?>

                                </div>

                            </div>

                        </div>


                        <div class="academic-stats">


                            <div class="academic-stat">

                                <strong>
                                    <?= e(
                                        number_format(
                                            $child["average_score"],
                                            1
                                        )
                                    ) ?>%
                                </strong>

                                <span>
                                    Average
                                </span>

                            </div>


                            <div class="academic-stat">

                                <strong>
                                    <?= e(
                                        number_format(
                                            $child["attendance_percentage"],
                                            1
                                        )
                                    ) ?>%
                                </strong>

                                <span>
                                    Attendance
                                </span>

                            </div>


                            <div class="academic-stat">

                                <strong>
                                    <?= $child["subject_count"] ?>
                                </strong>

                                <span>
                                    Results
                                </span>

                            </div>

                        </div>


                        <div class="performance-label">

                            <?= e($performance) ?>

                        </div>


                        <div class="child-actions">


                            <a
                                href="results.php?student_id=<?= (int)$child["student_id"] ?>"
                                class="action-btn"
                            >
                                📊 Results
                            </a>


                            <a
                                href="performance.php?student_id=<?= (int)$child["student_id"] ?>"
                                class="action-btn"
                            >
                                📈 Performance
                            </a>


                            <a
                                href="report_card.php?student_id=<?= (int)$child["student_id"] ?>"
                                class="action-btn"
                            >
                                📄 Report Card
                            </a>


                            <a
                                href="timetable.php?student_id=<?= (int)$child["student_id"] ?>"
                                class="action-btn"
                            >
                                🗓️ Timetable
                            </a>

                        </div>


                    </div>

                <?php endforeach; ?>

            <?php endif; ?>

        </div>


        <!-- ========================================================
             ACADEMIC MODULES
        ========================================================= -->

        <div class="section-title">

            <h2>
                Academic Services
            </h2>

        </div>


        <div class="modules-grid">


            <a href="results.php" class="module">

                <div class="module-icon">
                    📊
                </div>

                <h3>
                    Examination Results
                </h3>

                <p>
                    View your children's examination
                    marks, grades, subjects and remarks.
                </p>

                <span class="module-link">
                    View Results →
                </span>

            </a>


            <a href="performance.php" class="module">

                <div class="module-icon">
                    📈
                </div>

                <h3>
                    Performance Analysis
                </h3>

                <p>
                    Track academic performance and
                    identify areas that need improvement.
                </p>

                <span class="module-link">
                    View Performance →
                </span>

            </a>


            <a href="report_card.php" class="module">

                <div class="module-icon">
                    📄
                </div>

                <h3>
                    Report Cards
                </h3>

                <p>
                    Access published academic report
                    cards for your children.
                </p>

                <span class="module-link">
                    View Report Cards →
                </span>

            </a>


            <a href="assignments.php" class="module">

                <div class="module-icon">
                    📝
                </div>

                <h3>
                    Assignments
                </h3>

                <p>
                    Monitor assignments given to your
                    children and their submission status.
                </p>

                <span class="module-link">
                    View Assignments →
                </span>

            </a>


            <a href="timetable.php" class="module">

                <div class="module-icon">
                    🗓️
                </div>

                <h3>
                    Class Timetable
                </h3>

                <p>
                    View your children's class schedules,
                    subjects, teachers and lesson times.
                </p>

                <span class="module-link">
                    View Timetable →
                </span>

            </a>


            <a href="cbc.php" class="module">

                <div class="module-icon">
                    🎯
                </div>

                <h3>
                    CBC Progress
                </h3>

                <p>
                    Monitor competency development and
                    CBC performance levels.
                </p>

                <span class="module-link">
                    View CBC Progress →
                </span>

            </a>


            <a href="resources.php" class="module">

                <div class="module-icon">
                    📚
                </div>

                <h3>
                    Learning Resources
                </h3>

                <p>
                    Access notes, worksheets, past papers,
                    videos and other learning resources.
                </p>

                <span class="module-link">
                    View Resources →
                </span>

            </a>


            <a href="achievements.php" class="module">

                <div class="module-icon">
                    🏆
                </div>

                <h3>
                    Achievements
                </h3>

                <p>
                    View academic, sports, leadership,
                    talent and competition achievements.
                </p>

                <span class="module-link">
                    View Achievements →
                </span>

            </a>


            <a href="children.php" class="module">

                <div class="module-icon">
                    👨‍👩‍👧
                </div>

                <h3>
                    My Children
                </h3>

                <p>
                    View your children's academic profiles
                    and access their academic information.
                </p>

                <span class="module-link">
                    View Children →
                </span>

            </a>


        </div>


        <!-- ========================================================
             LATEST RESULTS
        ========================================================= -->

        <div class="section-title">

            <h2>
                Latest Academic Results
            </h2>

            <a href="results.php">
                View All Results →
            </a>

        </div>


        <div class="results-box">


            <?php

            $displayResults = [];

            foreach ($children as $child) {

                foreach (
                    $child["latest_results"]
                    as $result
                ) {

                    $result["student_name"] =
                        $child["full_name"];

                    $displayResults[] = $result;
                }
            }

            ?>


            <?php if (empty($displayResults)): ?>

                <div class="empty">

                    No academic results are available yet.

                </div>

            <?php else: ?>


                <table class="result-table">

                    <thead>

                        <tr>

                            <th>
                                Student
                            </th>

                            <th>
                                Subject
                            </th>

                            <th>
                                Score
                            </th>

                            <th>
                                Grade
                            </th>

                            <th>
                                Term
                            </th>

                            <th>
                                Year
                            </th>

                        </tr>

                    </thead>


                    <tbody>

                        <?php

                        $displayResults =
                            array_slice(
                                $displayResults,
                                0,
                                10
                            );

                        ?>


                        <?php foreach (
                            $displayResults
                            as $result
                        ): ?>

                            <tr>

                                <td>
                                    <?= e(
                                        $result["student_name"]
                                    ) ?>
                                </td>

                                <td>
                                    <?= e(
                                        $result["subject_name"]
                                    ) ?>
                                </td>

                                <td>
                                    <?= e(
                                        number_format(
                                            (float)$result["score"],
                                            1
                                        )
                                    ) ?>%
                                </td>

                                <td class="grade">

                                    <?= e(
                                        $result["grade"]
                                        ?: "-"
                                    ) ?>

                                </td>

                                <td>

                                    <?= e(
                                        str_replace(
                                            "_",
                                            " ",
                                            $result["term"]
                                        )
                                    ) ?>

                                </td>

                                <td>

                                    <?= e(
                                        $result["academic_year"]
                                    ) ?>

                                </td>

                            </tr>

                        <?php endforeach; ?>

                    </tbody>

                </table>

            <?php endif; ?>

        </div>


        <!-- ========================================================
             QUICK LINKS
        ========================================================= -->

        <div class="section-title">

            <h2>
                Quick Access
            </h2>

        </div>


        <div class="quick-links">


            <a href="results.php" class="quick-link">

                📊
                <br>
                Results

            </a>


            <a href="performance.php" class="quick-link">

                📈
                <br>
                Performance

            </a>


            <a href="assignments.php" class="quick-link">

                📝
                <br>
                Assignments

            </a>


            <a href="timetable.php" class="quick-link">

                🗓️
                <br>
                Timetable

            </a>


            <a href="cbc.php" class="quick-link">

                🎯
                <br>
                CBC

            </a>


            <a href="resources.php" class="quick-link">

                📚
                <br>
                Resources

            </a>


            <a href="achievements.php" class="quick-link">

                🏆
                <br>
                Achievements

            </a>


            <a href="report_card.php" class="quick-link">

                📄
                <br>
                Report Cards

            </a>

        </div>


    </div>

</main>


</body>

</html>