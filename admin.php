```php
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
| AUTHENTICATION
|--------------------------------------------------------------------------
*/

if (
    !isset($_SESSION['logged_in']) ||
    $_SESSION['logged_in'] !== true
) {
    header("Location: login.php");
    exit;
}

if (!isset($_SESSION['role'])) {
    header("Location: login.php");
    exit;
}

$role = strtoupper(trim((string)$_SESSION['role']));

if ($role !== 'SCHOOL_ADMIN' && $role !== 'SUPER_ADMIN') {
    header("Location: login.php");
    exit;
}

/*
|--------------------------------------------------------------------------
| ADMIN INFORMATION
|--------------------------------------------------------------------------
*/

$userId = (int)($_SESSION['user_id'] ?? 0);
$unreadMessageCount = unreadMessageCount($conn, $userId);

$adminName = "Administrator";

try {

    $stmt = $conn->prepare("
        SELECT email, phone_number, role
        FROM users
        WHERE user_id = ?
        LIMIT 1
    ");

    $stmt->execute([$userId]);

    $admin = $stmt->fetch();

    if ($admin) {

        if (!empty($admin['email'])) {
            $adminName = $admin['email'];
        } elseif (!empty($admin['phone_number'])) {
            $adminName = $admin['phone_number'];
        }
    }

} catch (PDOException $e) {

    $adminName = "Administrator";
}

/*
|--------------------------------------------------------------------------
| DASHBOARD STATISTICS
|--------------------------------------------------------------------------
*/

function getCount(PDO $conn, string $sql): int
{
    try {

        return (int)$conn->query($sql)->fetchColumn();

    } catch (PDOException $e) {

        return 0;
    }
}

$studentCount = getCount(
    $conn,
    "SELECT COUNT(*) FROM students WHERE status = 'ACTIVE'"
);

$teacherCount = getCount(
    $conn,
    "SELECT COUNT(*) FROM teachers"
);

$parentCount = getCount(
    $conn,
    "SELECT COUNT(*) FROM parents"
);

$classCount = getCount(
    $conn,
    "SELECT COUNT(*) FROM classes"
);

$subjectCount = getCount(
    $conn,
    "SELECT COUNT(*) FROM subjects"
);

$pendingUsers = getCount(
    $conn,
    "SELECT COUNT(*) FROM users WHERE status = 'PENDING'"
);

$pendingAssessments = getCount(
    $conn,
    "SELECT COUNT(*) FROM assessments WHERE status = 'SUBMITTED'"
);

$publishedAssessments = getCount(
    $conn,
    "SELECT COUNT(*) FROM assessments WHERE status = 'PUBLISHED'"
);

/*
|--------------------------------------------------------------------------
| FEE INFORMATION
|--------------------------------------------------------------------------
*/

$totalBilled = 0;
$totalPaid = 0;
$totalBalance = 0;

try {

    $feeSummary = $conn->query("
        SELECT
            COALESCE(SUM(total_billed), 0) AS total_billed,
            COALESCE(SUM(total_paid), 0) AS total_paid,
            COALESCE(SUM(current_balance), 0) AS total_balance
        FROM fee_accounts
    ")->fetch();

    if ($feeSummary) {

        $totalBilled = (float)$feeSummary['total_billed'];
        $totalPaid = (float)$feeSummary['total_paid'];
        $totalBalance = (float)$feeSummary['total_balance'];
    }

} catch (PDOException $e) {
    // Keep dashboard working if finance tables are not yet populated.
}

/*
|--------------------------------------------------------------------------
| ATTENDANCE SUMMARY
|--------------------------------------------------------------------------
*/

$attendancePresent = 0;
$attendanceAbsent = 0;

try {

    $attendanceSummary = $conn->query("
        SELECT
            COALESCE(SUM(status = 'PRESENT'), 0) AS present_count,
            COALESCE(SUM(status = 'ABSENT'), 0) AS absent_count
        FROM attendance
        WHERE date = CURDATE()
    ")->fetch();

    if ($attendanceSummary) {

        $attendancePresent = (int)$attendanceSummary['present_count'];
        $attendanceAbsent = (int)$attendanceSummary['absent_count'];
    }

} catch (PDOException $e) {
    // Attendance table may not have today's records.
}

/*
|--------------------------------------------------------------------------
| RECENT STUDENTS
|--------------------------------------------------------------------------
*/

$recentStudents = [];

try {

    $recentStudents = $conn->query("
        SELECT
            st.adm_number,
            st.full_name,
            c.class_name,
            c.stream_name,
            st.created_at
        FROM students st
        LEFT JOIN classes c
            ON c.class_id = st.class_id
        ORDER BY st.created_at DESC
        LIMIT 8
    ")->fetchAll();

} catch (PDOException $e) {
    $recentStudents = [];
}

/*
|--------------------------------------------------------------------------
| RECENT ASSESSMENTS
|--------------------------------------------------------------------------
*/

$recentAssessments = [];

try {

    $recentAssessments = $conn->query("
        SELECT
            a.assessment_name,
            a.status,
            a.term,
            a.academic_year,
            c.class_name,
            c.stream_name,
            s.subject_name
        FROM assessments a
        JOIN classes c
            ON c.class_id = a.class_id
        JOIN subjects s
            ON s.subject_id = a.subject_id
        ORDER BY a.created_at DESC
        LIMIT 6
    ")->fetchAll();

} catch (PDOException $e) {
    $recentAssessments = [];
}

/*
|--------------------------------------------------------------------------
| HELPER
|--------------------------------------------------------------------------
*/

function money(float $amount): string
{
    return "KES " . number_format($amount, 2);
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

<title>School Management System - Admin Dashboard</title>

<style>

/* =========================================================
   GLOBAL
========================================================= */

* {
    box-sizing: border-box;
}

body {
    margin: 0;
    font-family: Arial, Helvetica, sans-serif;
    background: #f4f7fb;
    color: #1f2937;
}

a {
    text-decoration: none;
}

/* =========================================================
   SIDEBAR
========================================================= */

.sidebar {
    position: fixed;
    left: 0;
    top: 0;
    width: 270px;
    height: 100vh;

    background: linear-gradient(
        180deg,
        #061b41 0%,
        #0b2e6b 55%,
        #10458f 100%
    );

    color: white;
    padding: 20px 15px;
    overflow-y: auto;
    z-index: 1000;
}

.school-logo {
    text-align: center;
    padding: 10px 5px 22px;
    border-bottom: 1px solid rgba(255,255,255,.15);
    margin-bottom: 15px;
}

.school-logo .logo-icon {
    font-size: 38px;
    margin-bottom: 5px;
}

.school-logo h2 {
    margin: 0;
    font-size: 20px;
}

.school-logo p {
    margin: 5px 0 0;
    font-size: 12px;
    opacity: .75;
}

/* =========================================================
   NAVIGATION
========================================================= */

.nav-section {
    margin-top: 18px;
    margin-bottom: 8px;

    font-size: 11px;
    text-transform: uppercase;
    letter-spacing: 1px;

    color: rgba(255,255,255,.55);
    padding-left: 10px;
}

.nav-link {
    display: flex;
    align-items: center;
    gap: 12px;

    color: white;

    padding: 11px 12px;
    margin: 3px 0;

    border-radius: 8px;

    font-size: 14px;

    transition: .2s;
}

.nav-link:hover {
    background: rgba(255,255,255,.12);
}

.nav-link.active {
    background: rgba(255,255,255,.18);
}

.nav-icon {
    width: 25px;
    text-align: center;
    font-size: 18px;
}

.nav-link .arrow {
    margin-left: auto;
    opacity: .5;
    font-size: 11px;
}

.nav-toggle {
    width: 100%;
    border: 0;
    font: inherit;
    text-align: left;
    cursor: pointer;
    background: transparent;
}

.nav-toggle .arrow {
    transition: transform .2s ease;
}

.nav-toggle[aria-expanded="true"] .arrow {
    transform: rotate(90deg);
}

.submenu {
    display: none;
    margin: -1px 0 6px 37px;
    padding-left: 10px;
    border-left: 1px solid rgba(255,255,255,.2);
}

.submenu.is-open {
    display: block;
}

.submenu a {
    display: block;
    color: rgba(255,255,255,.78);
    padding: 8px 10px;
    border-radius: 6px;
    font-size: 13px;
}

.submenu a:hover {
    background: rgba(255,255,255,.12);
    color: white;
}

/* =========================================================
   MAIN
========================================================= */

.main {
    margin-left: 270px;
    min-height: 100vh;
    padding: 25px;
}

/* =========================================================
   TOP BAR
========================================================= */

.topbar {
    background: white;
    border-radius: 14px;

    padding: 17px 22px;

    display: flex;
    align-items: center;
    justify-content: space-between;

    margin-bottom: 22px;

    box-shadow: 0 3px 15px rgba(0,0,0,.05);
}

.topbar-left h1 {
    margin: 0;
    font-size: 24px;
}

.topbar-left p {
    margin: 5px 0 0;
    color: #6b7280;
    font-size: 13px;
}

.admin-profile {
    display: flex;
    align-items: center;
    gap: 12px;
}

.admin-avatar {
    width: 43px;
    height: 43px;

    border-radius: 50%;

    background: #0d3478;
    color: white;

    display: flex;
    align-items: center;
    justify-content: center;

    font-weight: bold;
}

.admin-details strong {
    display: block;
    font-size: 14px;
}

.admin-details span {
    display: block;
    color: #6b7280;
    font-size: 12px;
    margin-top: 3px;
}

/* =========================================================
   WELCOME
========================================================= */

.welcome {
    background: linear-gradient(
        120deg,
        #09265c,
        #1452a0
    );

    color: white;

    padding: 27px;

    border-radius: 15px;

    margin-bottom: 22px;

    position: relative;
    overflow: hidden;
}

.welcome::after {
    content: "🏫";

    position: absolute;
    right: 45px;
    top: 10px;

    font-size: 100px;

    opacity: .10;
}

.welcome h2 {
    margin: 0 0 7px;
    font-size: 25px;
}

.welcome p {
    margin: 0;
    opacity: .85;
}

/* =========================================================
   STATISTICS
========================================================= */

.stats {
    display: grid;

    grid-template-columns:
        repeat(4, minmax(0, 1fr));

    gap: 16px;

    margin-bottom: 22px;
}

.stat-card {
    background: white;

    border-radius: 13px;

    padding: 20px;

    box-shadow: 0 3px 15px rgba(0,0,0,.05);

    position: relative;
}

.stat-card .icon {
    width: 44px;
    height: 44px;

    display: flex;
    align-items: center;
    justify-content: center;

    border-radius: 10px;

    background: #eef4ff;

    font-size: 21px;

    margin-bottom: 13px;
}

.stat-card h3 {
    margin: 0;

    font-size: 13px;

    color: #6b7280;

    font-weight: normal;
}

.stat-number {
    margin-top: 6px;

    font-size: 27px;

    font-weight: bold;

    color: #0b2e6b;
}

.stat-link {
    display: inline-block;

    margin-top: 9px;

    font-size: 12px;

    color: #1452a0;
}

/* =========================================================
   TWO COLUMN
========================================================= */

.dashboard-grid {
    display: grid;

    grid-template-columns:
        minmax(0, 1.65fr)
        minmax(300px, .85fr);

    gap: 20px;

    margin-bottom: 22px;
}

/* =========================================================
   PANEL
========================================================= */

.panel {
    background: white;

    border-radius: 14px;

    padding: 22px;

    box-shadow: 0 3px 15px rgba(0,0,0,.05);
}

.panel-header {
    display: flex;
    align-items: center;
    justify-content: space-between;

    margin-bottom: 18px;
}

.panel-header h2 {
    margin: 0;

    font-size: 18px;
}

.view-all {
    color: #1452a0;
    font-size: 13px;
}

/* =========================================================
   QUICK ACTIONS
========================================================= */

.quick-actions {
    display: grid;

    grid-template-columns:
        repeat(3, minmax(0, 1fr));

    gap: 12px;
}

.action {
    border: 1px solid #e5e7eb;

    border-radius: 10px;

    padding: 16px;

    color: #1f2937;

    transition: .2s;
}

.action:hover {
    border-color: #1452a0;
    background: #f7faff;
    transform: translateY(-2px);
}

.action-icon {
    font-size: 24px;
    margin-bottom: 8px;
}

.action strong {
    display: block;
    font-size: 14px;
}

.action span {
    display: block;
    font-size: 11px;
    color: #6b7280;
    margin-top: 4px;
}

/* =========================================================
   FEE PANEL
========================================================= */

.finance-row {
    display: flex;
    justify-content: space-between;

    padding: 13px 0;

    border-bottom: 1px solid #edf0f4;
}

.finance-row:last-child {
    border-bottom: 0;
}

.finance-row span {
    color: #6b7280;
    font-size: 13px;
}

.finance-row strong {
    font-size: 14px;
}

/* =========================================================
   TABLE
========================================================= */

.table-wrapper {
    overflow-x: auto;
}

table {
    width: 100%;

    border-collapse: collapse;

    min-width: 650px;
}

th {
    text-align: left;

    background: #f8fafc;

    color: #64748b;

    font-size: 11px;

    text-transform: uppercase;

    padding: 12px;
}

td {
    padding: 12px;

    border-bottom: 1px solid #edf0f4;

    font-size: 13px;
}

tbody tr:hover {
    background: #fafcff;
}

/* =========================================================
   BADGES
========================================================= */

.badge {
    display: inline-block;

    padding: 5px 8px;

    border-radius: 20px;

    font-size: 10px;

    font-weight: bold;
}

.badge-success {
    background: #dcfce7;
    color: #166534;
}

.badge-warning {
    background: #fef3c7;
    color: #92400e;
}

.badge-danger {
    background: #fee2e2;
    color: #991b1b;
}

.badge-info {
    background: #dbeafe;
    color: #1e40af;
}

/* =========================================================
   ATTENDANCE
========================================================= */

.attendance-box {
    display: grid;

    grid-template-columns: 1fr 1fr;

    gap: 12px;
}

.attendance-item {
    padding: 18px;

    border-radius: 10px;

    background: #f8fafc;
}

.attendance-item strong {
    display: block;

    font-size: 25px;

    color: #0b2e6b;

    margin-bottom: 5px;
}

.attendance-item span {
    color: #6b7280;

    font-size: 12px;
}

/* =========================================================
   MODULES
========================================================= */

.modules {
    display: grid;

    grid-template-columns:
        repeat(4, minmax(0, 1fr));

    gap: 15px;
}

.module {
    background: white;

    border-radius: 12px;

    padding: 19px;

    border: 1px solid #e8edf4;

    color: #1f2937;

    transition: .2s;
}

.module:hover {
    transform: translateY(-2px);

    box-shadow: 0 7px 20px rgba(0,0,0,.07);

    border-color: #cddbf3;
}

.module-icon {
    font-size: 28px;

    margin-bottom: 10px;
}

.module strong {
    display: block;

    font-size: 14px;
}

.module span {
    display: block;

    color: #6b7280;

    font-size: 11px;

    margin-top: 5px;

    line-height: 1.5;
}

/* =========================================================
   FOOTER
========================================================= */

.footer {
    text-align: center;

    color: #94a3b8;

    font-size: 11px;

    padding: 25px 0 10px;
}

/* =========================================================
   RESPONSIVE
========================================================= */

@media(max-width:1200px) {

    .stats {
        grid-template-columns:
            repeat(2, minmax(0, 1fr));
    }

    .modules {
        grid-template-columns:
            repeat(3, minmax(0, 1fr));
    }
}

@media(max-width:950px) {

    .dashboard-grid {
        grid-template-columns: 1fr;
    }

    .modules {
        grid-template-columns:
            repeat(2, minmax(0, 1fr));
    }
}

@media(max-width:750px) {

    .sidebar {
        position: relative;

        width: 100%;

        height: auto;

        max-height: none;
    }

    .main {
        margin-left: 0;

        padding: 15px;
    }

    .topbar {
        flex-direction: column;

        align-items: flex-start;

        gap: 15px;
    }

    .stats {
        grid-template-columns: 1fr;
    }

    .quick-actions {
        grid-template-columns: 1fr 1fr;
    }

    .modules {
        grid-template-columns: 1fr 1fr;
    }
}

@media(max-width:480px) {

    .quick-actions,
    .modules {
        grid-template-columns: 1fr;
    }

    .welcome h2 {
        font-size: 21px;
    }
}

</style>

</head>

<body>

<!-- =====================================================
     SIDEBAR
===================================================== -->

<aside class="sidebar">

    <div class="school-logo">

        <div class="logo-icon">🏫</div>

        <h2>School SMS</h2>

        <p>Administration Portal</p>

    </div>


    <!-- DASHBOARD -->

    <a href="admin.php" class="nav-link active">

        <span class="nav-icon">📊</span>

        <span>Dashboard</span>

    </a>


    <!-- ACADEMICS -->

    <div class="nav-section">
        Academic Management
    </div>

    <button type="button" class="nav-link nav-toggle" aria-expanded="false" aria-controls="admin-academics-menu">

        <span class="nav-icon">📚</span>

        <span>Academics</span>

        <span class="arrow">›</span>

    </button>

    <div class="submenu" id="admin-academics-menu">
        <a href="academics/admin/classes.php">Classes</a>
        <a href="academics/admin/subjects.php">Subjects</a>
        <a href="academics/admin/teacher_assignments.php">Teacher Assignments</a>
        <a href="academics/admin/timetable.php">Timetable</a>
        <a href="academics/admin/assignments.php">Assignments</a>
        <a href="academics/admin/approve_marks.php">Approve Marks</a>
        <a href="academics/admin/publish_results.php">Publish Results</a>
        <a href="academics/admin/report_cards.php">Report Cards</a>
        <a href="academics/admin/reports.php">Academic Reports</a>
        <a href="academics/admin/class_performance.php">Class Performance</a>
        <a href="academics/admin/student_perfomance.php">Student Performance</a>
        <a href="academics/admin/cbc.php">CBC</a>
    </div>


    <!-- STUDENTS -->

    <div class="nav-section">
        Student Management
    </div>

    <a href="students.php" class="nav-link">

        <span class="nav-icon">👨‍🎓</span>

        <span>Students</span>

    </a>

    <a href="admissions.php" class="nav-link">

        <span class="nav-icon">📝</span>

        <span>Admissions</span>

    </a>

    <a href="parents.php" class="nav-link">

        <span class="nav-icon">👨‍👩‍👧</span>

        <span>Parents</span>

    </a>


    <!-- FINANCE -->

    <div class="nav-section">
        Finance
    </div>

    <a href="finance.php" class="nav-link">

        <span class="nav-icon">💰</span>

        <span>Fee Management</span>

    </a>

    <a href="mpesa.php" class="nav-link">

        <span class="nav-icon">📱</span>

        <span>M-Pesa Payments</span>

    </a>

    <a href="expenses.php" class="nav-link">

        <span class="nav-icon">💳</span>

        <span>Expenses</span>

    </a>


    <!-- PEOPLE -->

    <div class="nav-section">
        Staff & HR
    </div>

    <a href="teachers.php" class="nav-link">

        <span class="nav-icon">👨‍🏫</span>

        <span>Teachers</span>

    </a>

    <a href="staff.php" class="nav-link">

        <span class="nav-icon">👥</span>

        <span>Staff</span>

    </a>

    <a href="payroll.php" class="nav-link">

        <span class="nav-icon">💼</span>

        <span>Payroll & HR</span>

    </a>


    <!-- OPERATIONS -->

    <div class="nav-section">
        School Operations
    </div>

    <a href="attendance.php" class="nav-link">

        <span class="nav-icon">🕐</span>

        <span>Attendance</span>

    </a>

    <a href="transport.php" class="nav-link">

        <span class="nav-icon">🚌</span>

        <span>Transport</span>

    </a>

    <a href="library.php" class="nav-link">

        <span class="nav-icon">📖</span>

        <span>Library</span>

    </a>

    <a href="inventory.php" class="nav-link">

        <span class="nav-icon">📦</span>

        <span>Stores & Inventory</span>

    </a>

    <a href="visitors.php" class="nav-link">

        <span class="nav-icon">🚪</span>

        <span>Visitors & Gate</span>

    </a>


    <!-- COMMUNICATION -->

    <div class="nav-section">
        Communication
    </div>

    <a href="communication.php" class="nav-link">

        <span class="nav-icon">💬</span>

        <span>Communication</span>

    </a>

    <a href="messages.php" class="nav-link">

        <span class="nav-icon">✉️</span>

        <span>Messages<?= $unreadMessageCount > 0 ? ' (' . min(99, $unreadMessageCount) . ')' : '' ?></span>

    </a>


    <!-- E-LEARNING -->

    <div class="nav-section">
        Digital Learning
    </div>

    <a href="elearning.php" class="nav-link">

        <span class="nav-icon">💻</span>

        <span>E-Learning</span>

    </a>

    <a href="resources.php" class="nav-link">

        <span class="nav-icon">📂</span>

        <span>Resources</span>

    </a>


    <!-- STUDENT LIFE -->

    <div class="nav-section">
        Student Life
    </div>

    <a href="discipline.php" class="nav-link">

        <span class="nav-icon">⚖️</span>

        <span>Discipline</span>

    </a>

    <a href="achievements.php" class="nav-link">

        <span class="nav-icon">🏆</span>

        <span>Achievements</span>

    </a>


    <!-- SYSTEM -->

    <div class="nav-section">
        System
    </div>

    <a href="users.php" class="nav-link">

        <span class="nav-icon">👤</span>

        <span>User Accounts</span>

    </a>

    <a href="reports.php" class="nav-link">

        <span class="nav-icon">📑</span>

        <span>System Reports</span>

    </a>

    <a href="settings.php" class="nav-link">

        <span class="nav-icon">⚙️</span>

        <span>Settings</span>

    </a>

    <a href="logout.php" class="nav-link">

        <span class="nav-icon">🚪</span>

        <span>Logout</span>

    </a>

</aside>


<!-- =====================================================
     MAIN CONTENT
===================================================== -->

<main class="main">


    <!-- TOP BAR -->

    <div class="topbar">

        <div class="topbar-left">

            <h1>Administrator Dashboard</h1>

            <p>
                School Management System Control Centre
            </p>

        </div>


        <div class="admin-profile">

            <div class="admin-avatar">
                <?=strtoupper(substr($adminName, 0, 1))?>
            </div>

            <div class="admin-details">

                <strong>
                    <?=htmlspecialchars($adminName)?>
                </strong>

                <span>
                    <?=htmlspecialchars(str_replace('_', ' ', $role))?>
                </span>

            </div>

        </div>

    </div>


    <!-- WELCOME -->

    <section class="welcome">

        <h2>
            Welcome, Administrator 👋
        </h2>

        <p>
            Manage students, academics, finances, staff and
            all school operations from one central dashboard.
        </p>

    </section>


    <!-- =================================================
         MAIN STATISTICS
    ================================================= -->

    <section class="stats">


        <div class="stat-card">

            <div class="icon">👨‍🎓</div>

            <h3>Active Students</h3>

            <div class="stat-number">
                <?=number_format($studentCount)?>
            </div>

            <a href="students.php" class="stat-link">
                Manage students →
            </a>

        </div>


        <div class="stat-card">

            <div class="icon">👨‍🏫</div>

            <h3>Teachers</h3>

            <div class="stat-number">
                <?=number_format($teacherCount)?>
            </div>

            <a href="teachers.php" class="stat-link">
                Manage teachers →
            </a>

        </div>


        <div class="stat-card">

            <div class="icon">👨‍👩‍👧</div>

            <h3>Parents</h3>

            <div class="stat-number">
                <?=number_format($parentCount)?>
            </div>

            <a href="parents.php" class="stat-link">
                Manage parents →
            </a>

        </div>


        <div class="stat-card">

            <div class="icon">🏫</div>

            <h3>Classes</h3>

            <div class="stat-number">
                <?=number_format($classCount)?>
            </div>

            <a href="academics/admin/classes.php" class="stat-link">
                Manage classes →
            </a>

        </div>


        <div class="stat-card">

            <div class="icon">📚</div>

            <h3>Subjects</h3>

            <div class="stat-number">
                <?=number_format($subjectCount)?>
            </div>

            <a href="academics/admin/subjects.php" class="stat-link">
                Manage subjects →
            </a>

        </div>


        <div class="stat-card">

            <div class="icon">👤</div>

            <h3>Pending Accounts</h3>

            <div class="stat-number">
                <?=number_format($pendingUsers)?>
            </div>

            <a href="users.php" class="stat-link">
                Review accounts →
            </a>

        </div>


        <div class="stat-card">

            <div class="icon">📝</div>

            <h3>Marks Awaiting Approval</h3>

            <div class="stat-number">
                <?=number_format($pendingAssessments)?>
            </div>

            <a href="academics/admin/approve_marks.php" class="stat-link">
                Review marks →
            </a>

        </div>


        <div class="stat-card">

            <div class="icon">📢</div>

            <h3>Published Assessments</h3>

            <div class="stat-number">
                <?=number_format($publishedAssessments)?>
            </div>

            <a href="academics/admin/publish_results.php" class="stat-link">
                View results →
            </a>

        </div>


    </section>


    <!-- =================================================
         QUICK ACTIONS + FINANCE
    ================================================= -->

    <section class="dashboard-grid">


        <div class="panel">

            <div class="panel-header">

                <h2>Quick Actions</h2>

            </div>


            <div class="quick-actions">


                <a href="academics/academics.php" class="action">

                    <div class="action-icon">📚</div>

                    <strong>Academic Management</strong>

                    <span>
                        Exams, marks, CBC, timetable & reports
                    </span>

                </a>


                <a href="students.php" class="action">

                    <div class="action-icon">👨‍🎓</div>

                    <strong>Students</strong>

                    <span>
                        Register and manage students
                    </span>

                </a>


                <a href="admissions.php" class="action">

                    <div class="action-icon">📝</div>

                    <strong>Admissions</strong>

                    <span>
                        Manage new admissions
                    </span>

                </a>


                <a href="finance.php" class="action">

                    <div class="action-icon">💰</div>

                    <strong>Fee Management</strong>

                    <span>
                        Fees, balances and payments
                    </span>

                </a>


                <a href="teachers.php" class="action">

                    <div class="action-icon">👨‍🏫</div>

                    <strong>Teachers</strong>

                    <span>
                        Staff and teaching assignments
                    </span>

                </a>


                <a href="attendance.php" class="action">

                    <div class="action-icon">🕐</div>

                    <strong>Attendance</strong>

                    <span>
                        Monitor student attendance
                    </span>

                </a>


                <a href="transport.php" class="action">

                    <div class="action-icon">🚌</div>

                    <strong>Transport</strong>

                    <span>
                        Routes, vehicles and students
                    </span>

                </a>


                <a href="communication.php" class="action">

                    <div class="action-icon">📢</div>

                    <strong>Communication</strong>

                    <span>
                        SMS, email and announcements
                    </span>

                </a>


                <a href="reports.php" class="action">

                    <div class="action-icon">📑</div>

                    <strong>Reports</strong>

                    <span>
                        Generate school reports
                    </span>

                </a>


            </div>

        </div>


        <div class="panel">

            <div class="panel-header">

                <h2>Finance Overview</h2>

                <a href="finance.php" class="view-all">
                    Open Finance
                </a>

            </div>


            <div class="finance-row">

                <span>Total Billed</span>

                <strong>
                    <?=money($totalBilled)?>
                </strong>

            </div>


            <div class="finance-row">

                <span>Total Paid</span>

                <strong>
                    <?=money($totalPaid)?>
                </strong>

            </div>


            <div class="finance-row">

                <span>Outstanding Balance</span>

                <strong>
                    <?=money($totalBalance)?>
                </strong>

            </div>


            <div class="finance-row">

                <span>Collection Rate</span>

                <strong>

                    <?php

                    $collectionRate = 0;

                    if ($totalBilled > 0) {
                        $collectionRate =
                            ($totalPaid / $totalBilled) * 100;
                    }

                    echo number_format($collectionRate, 1) . "%";

                    ?>

                </strong>

            </div>


            <div style="margin-top:18px;">

                <a
                    href="mpesa.php"
                    style="
                        display:block;
                        background:#0d3478;
                        color:white;
                        text-align:center;
                        padding:11px;
                        border-radius:8px;
                        font-size:13px;
                    "
                >
                    📱 Manage M-Pesa Payments
                </a>

            </div>

        </div>


    </section>


    <!-- =================================================
         ATTENDANCE + RECENT ASSESSMENTS
    ================================================= -->

    <section class="dashboard-grid">


        <div class="panel">

            <div class="panel-header">

                <h2>Today's Attendance</h2>

                <a href="attendance.php" class="view-all">
                    View attendance
                </a>

            </div>


            <div class="attendance-box">

                <div class="attendance-item">

                    <strong>
                        <?=number_format($attendancePresent)?>
                    </strong>

                    <span>
                        Students Present
                    </span>

                </div>


                <div class="attendance-item">

                    <strong>
                        <?=number_format($attendanceAbsent)?>
                    </strong>

                    <span>
                        Students Absent
                    </span>

                </div>

            </div>


            <div style="
                margin-top:18px;
                background:#f8fafc;
                padding:13px;
                border-radius:8px;
                font-size:12px;
                color:#64748b;
            ">

                Attendance records are updated by teachers
                through the attendance module.

            </div>

        </div>


        <div class="panel">

            <div class="panel-header">

                <h2>Recent Assessments</h2>

                <a
                    href="academics/admin/teacher_assignments.php"
                    class="view-all"
                >
                    View all
                </a>

            </div>


            <?php if ($recentAssessments): ?>

                <?php foreach ($recentAssessments as $assessment): ?>

                    <div style="
                        padding:11px 0;
                        border-bottom:1px solid #edf0f4;
                    ">

                        <strong style="
                            display:block;
                            font-size:13px;
                        ">

                            <?=htmlspecialchars(
                                $assessment['assessment_name']
                            )?>

                        </strong>

                        <span style="
                            display:block;
                            color:#64748b;
                            font-size:11px;
                            margin-top:4px;
                        ">

                            <?=htmlspecialchars(
                                $assessment['subject_name']
                            )?>

                            ·

                            <?=htmlspecialchars(
                                $assessment['class_name']
                            )?>

                        </span>

                        <span class="badge
                            <?=
                            $assessment['status'] === 'PUBLISHED'
                            ? 'badge-success'
                            :
                            (
                                $assessment['status'] === 'SUBMITTED'
                                ? 'badge-warning'
                                : 'badge-info'
                            )
                            ?>"
                            style="margin-top:5px;"
                        >

                            <?=htmlspecialchars(
                                $assessment['status']
                            )?>

                        </span>

                    </div>

                <?php endforeach; ?>

            <?php else: ?>

                <p style="
                    color:#64748b;
                    font-size:13px;
                ">
                    No assessments found.
                </p>

            <?php endif; ?>

        </div>


    </section>


    <!-- =================================================
         RECENT STUDENTS
    ================================================= -->

    <section class="panel" style="margin-bottom:22px;">

        <div class="panel-header">

            <h2>Recently Added Students</h2>

            <a href="students.php" class="view-all">
                View all students
            </a>

        </div>


        <div class="table-wrapper">

            <table>

                <thead>

                    <tr>

                        <th>Admission No.</th>

                        <th>Student Name</th>

                        <th>Class</th>

                        <th>Stream</th>

                        <th>Date Added</th>

                    </tr>

                </thead>


                <tbody>

                <?php if ($recentStudents): ?>

                    <?php foreach ($recentStudents as $student): ?>

                        <tr>

                            <td>
                                <strong>
                                    <?=htmlspecialchars(
                                        $student['adm_number']
                                    )?>
                                </strong>
                            </td>

                            <td>
                                <?=htmlspecialchars(
                                    $student['full_name']
                                )?>
                            </td>

                            <td>
                                <?=htmlspecialchars(
                                    $student['class_name'] ?? '-'
                                )?>
                            </td>

                            <td>
                                <?=htmlspecialchars(
                                    $student['stream_name'] ?? '-'
                                )?>
                            </td>

                            <td>
                                <?=htmlspecialchars(
                                    $student['created_at'] ?? '-'
                                )?>
                            </td>

                        </tr>

                    <?php endforeach; ?>

                <?php else: ?>

                    <tr>

                        <td colspan="5">
                            No students found.
                        </td>

                    </tr>

                <?php endif; ?>

                </tbody>

            </table>

        </div>

    </section>


    <!-- =================================================
         SYSTEM MODULES
    ================================================= -->

    <section class="panel">

        <div class="panel-header">

            <h2>School Management Modules</h2>

        </div>


        <div class="modules">


            <a href="academics/academics.php" class="module">

                <div class="module-icon">📚</div>

                <strong>Academics</strong>

                <span>
                    Exams, marks, CBC, report cards,
                    assignments and timetables.
                </span>

            </a>


            <a href="students.php" class="module">

                <div class="module-icon">👨‍🎓</div>

                <strong>Student Registry</strong>

                <span>
                    Complete student profiles,
                    admission numbers and classes.
                </span>

            </a>


            <a href="admissions.php" class="module">

                <div class="module-icon">📝</div>

                <strong>Admissions</strong>

                <span>
                    New student applications and
                    admission processing.
                </span>

            </a>


            <a href="finance.php" class="module">

                <div class="module-icon">💰</div>

                <strong>Finance</strong>

                <span>
                    Fees, balances, receipts,
                    payments and financial reports.
                </span>

            </a>


            <a href="communication.php" class="module">

                <div class="module-icon">📢</div>

                <strong>Communication</strong>

                <span>
                    SMS, email, announcements and
                    parent communication.
                </span>

            </a>


            <a href="transport.php" class="module">

                <div class="module-icon">🚌</div>

                <strong>Transport</strong>

                <span>
                    Vehicles, drivers, routes,
                    tracking and transport students.
                </span>

            </a>


            <a href="library.php" class="module">

                <div class="module-icon">📖</div>

                <strong>Library</strong>

                <span>
                    Books, borrowing, returns,
                    fines and library members.
                </span>

            </a>


            <a href="attendance.php" class="module">

                <div class="module-icon">🕐</div>

                <strong>Attendance</strong>

                <span>
                    Daily attendance, lateness,
                    absence and reports.
                </span>

            </a>


            <a href="elearning.php" class="module">

                <div class="module-icon">💻</div>

                <strong>E-Learning</strong>

                <span>
                    Digital lessons, resources,
                    videos and online learning.
                </span>

            </a>


            <a href="discipline.php" class="module">

                <div class="module-icon">⚖️</div>

                <strong>Discipline</strong>

                <span>
                    Behaviour records, incidents,
                    actions and interventions.
                </span>

            </a>


            <a href="inventory.php" class="module">

                <div class="module-icon">📦</div>

                <strong>Stores & Inventory</strong>

                <span>
                    Stock, supplies, assets,
                    issues and inventory reports.
                </span>

            </a>


            <a href="visitors.php" class="module">

                <div class="module-icon">🚪</div>

                <strong>Visitors & Gate</strong>

                <span>
                    Visitor registration, gate
                    passes and security records.
                </span>

            </a>


            <a href="payroll.php" class="module">

                <div class="module-icon">💼</div>

                <strong>Payroll & HR</strong>

                <span>
                    Staff records, payroll,
                    leave and HR management.
                </span>

            </a>


            <a href="achievements.php" class="module">

                <div class="module-icon">🏆</div>

                <strong>Achievements</strong>

                <span>
                    Academic, sports, talent,
                    leadership and competitions.
                </span>

            </a>


            <a href="reports.php" class="module">

                <div class="module-icon">📑</div>

                <strong>Reports</strong>

                <span>
                    Academic, finance, attendance,
                    student and system reports.
                </span>

            </a>


            <a href="settings.php" class="module">

                <div class="module-icon">⚙️</div>

                <strong>System Settings</strong>

                <span>
                    School configuration, users,
                    permissions and system settings.
                </span>

            </a>


        </div>

    </section>


    <!-- =================================================
         FOOTER
    ================================================= -->

    <div class="footer">

        School Management System

        &nbsp;•&nbsp;

        Administrator Portal

        &nbsp;•&nbsp;

        <?=date('Y')?>

    </div>


</main>

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
```
