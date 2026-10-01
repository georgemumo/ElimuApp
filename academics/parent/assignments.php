<?php

session_start();

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once "../../db.php";

if (!isset($_SESSION["logged_in"]) || $_SESSION["logged_in"] !== true) {
    header("Location: ../../login.php");
    exit;
}

if (
    !isset($_SESSION["role"]) ||
    strtoupper(trim((string)$_SESSION["role"])) !== "PARENT"
) {
    header("Location: ../../login.php");
    exit;
}

$user_id = $_SESSION["user_id"] ?? null;

function e($value)
{
    return htmlspecialchars((string)$value, ENT_QUOTES, "UTF-8");
}

if (!$user_id) {
    header("Location: ../../login.php");
    exit;
}

try {

    $parentStmt = $conn->prepare("
        SELECT parent_id, full_name
        FROM parents
        WHERE user_id = ?
        LIMIT 1
    ");

    $parentStmt->execute([$user_id]);

    $parent = $parentStmt->fetch();

    if (!$parent) {
        die("Parent account not found.");
    }

    $parent_id = $parent["parent_id"];

    $student_id = isset($_GET["student_id"])
        ? (int)$_GET["student_id"]
        : 0;

    $childrenStmt = $conn->prepare("
        SELECT
            s.student_id,
            s.full_name,
            c.class_name,
            c.stream_name
        FROM parent_student_map psm
        INNER JOIN students s ON s.student_id = psm.student_id
        INNER JOIN classes c ON c.class_id = s.class_id
        WHERE psm.parent_id = ?
        ORDER BY s.full_name
    ");

    $childrenStmt->execute([$parent_id]);

    $children = $childrenStmt->fetchAll();

    if ($student_id > 0) {

        $verify = $conn->prepare("
            SELECT student_id
            FROM parent_student_map
            WHERE parent_id = ?
            AND student_id = ?
            LIMIT 1
        ");

        $verify->execute([
            $parent_id,
            $student_id
        ]);

        if (!$verify->fetch()) {
            $student_id = 0;
        }
    }

    if ($student_id > 0) {

        $stmt = $conn->prepare("
            SELECT
                a.*,
                sub.subject_name,
                t.full_name AS teacher_name,
                ass.status AS submission_status,
                ass.submitted_at,
                ass.marks,
                ass.teacher_feedback
            FROM assignments a
            INNER JOIN subjects sub
                ON sub.subject_id = a.subject_id
            INNER JOIN teachers t
                ON t.teacher_id = a.teacher_id
            LEFT JOIN assignment_submissions ass
                ON ass.assignment_id = a.assignment_id
                AND ass.student_id = ?
            WHERE a.class_id = (
                SELECT class_id
                FROM students
                WHERE student_id = ?
            )
            ORDER BY a.due_date DESC
        ");

        $stmt->execute([
            $student_id,
            $student_id
        ]);

    } else {

        $stmt = $conn->prepare("
            SELECT
                a.*,
                sub.subject_name,
                t.full_name AS teacher_name,
                s.full_name AS student_name,
                ass.status AS submission_status,
                ass.submitted_at,
                ass.marks,
                ass.teacher_feedback
            FROM assignments a
            INNER JOIN subjects sub
                ON sub.subject_id = a.subject_id
            INNER JOIN teachers t
                ON t.teacher_id = a.teacher_id
            INNER JOIN students s
                ON s.class_id = a.class_id
            INNER JOIN parent_student_map psm
                ON psm.student_id = s.student_id
            LEFT JOIN assignment_submissions ass
                ON ass.assignment_id = a.assignment_id
                AND ass.student_id = s.student_id
            WHERE psm.parent_id = ?
            ORDER BY a.due_date DESC
        ");

        $stmt->execute([$parent_id]);
    }

    $assignments = $stmt->fetchAll();

} catch (PDOException $e) {

    die("Database error: " . e($e->getMessage()));
}

?>

<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">

<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Assignments - Parent</title>
<link rel="stylesheet" href="../../assets/css/school.css">

<style>

* {
    box-sizing: border-box;
}

body {
    margin: 0;
    font-family: Arial,sans-serif;
    background: #f4f7fb;
}

a {
    text-decoration: none;
}

.sidebar {
    position: fixed;
    left: 0;
    top: 0;
    width: 250px;
    height: 100vh;
    background: linear-gradient(180deg,#0b1f3a,#123d6a);
    color: white;
    padding: 25px 15px;
}

.school-logo {
    text-align: center;
    margin-bottom: 30px;
}

.school-logo h2 {
    font-size: 21px;
}

.school-logo p {
    font-size: 12px;
    opacity: .7;
}

.nav-title {
    font-size: 11px;
    opacity: .55;
    margin: 20px 10px 8px;
}

.nav-link {
    display: flex;
    align-items: center;
    gap: 12px;
    color: white;
    padding: 13px 14px;
    border-radius: 9px;
    margin-bottom: 5px;
    font-size: 14px;
}

.nav-link:hover,
.nav-link.active {
    background: rgba(255,255,255,.15);
}

.nav-icon {
    width: 25px;
    text-align: center;
}

.main {
    margin-left: 250px;
}

.topbar {
    height: 75px;
    background: white;
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0 30px;
    border-bottom: 1px solid #e5e7eb;
}

.topbar h1 {
    color: #0b1f3a;
    font-size: 21px;
}

.topbar p {
    color: #6b7280;
    font-size: 12px;
}

.profile-name {
    font-weight: bold;
    font-size: 14px;
}

.content {
    padding: 30px;
}

.header {
    background: linear-gradient(135deg,#0b1f3a,#155a91);
    color: white;
    padding: 28px;
    border-radius: 14px;
    margin-bottom: 20px;
}

.header h2 {
    margin-bottom: 8px;
}

.header p {
    font-size: 13px;
    opacity: .85;
}

.selector {
    background: white;
    padding: 20px;
    border-radius: 12px;
    margin-bottom: 20px;
}

.selector label {
    display: block;
    font-size: 13px;
    font-weight: bold;
    margin-bottom: 8px;
}

select {
    width: 100%;
    max-width: 450px;
    padding: 11px;
    border: 1px solid #d1d5db;
    border-radius: 7px;
}

.assignment-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit,minmax(300px,1fr));
    gap: 18px;
}

.assignment-card {
    background: white;
    border-radius: 12px;
    border: 1px solid #e5e7eb;
    padding: 20px;
}

.assignment-card h3 {
    color: #0b1f3a;
    font-size: 17px;
    margin-bottom: 7px;
}

.subject {
    color: #155a91;
    font-size: 12px;
    font-weight: bold;
    margin-bottom: 12px;
}

.description {
    color: #6b7280;
    font-size: 13px;
    line-height: 1.5;
    margin-bottom: 15px;
}

.info {
    border-top: 1px solid #edf0f3;
    padding-top: 12px;
}

.info p {
    font-size: 12px;
    margin-bottom: 7px;
}

.status {
    display: inline-block;
    padding: 6px 9px;
    border-radius: 5px;
    font-size: 11px;
    margin-top: 8px;
}

.submitted {
    background: #dcfce7;
    color: #166534;
}

.pending {
    background: #fef3c7;
    color: #92400e;
}

.late {
    background: #fee2e2;
    color: #991b1b;
}

.empty {
    background: white;
    padding: 40px;
    border-radius: 12px;
    text-align: center;
    color: #6b7280;
}

@media(max-width:700px) {

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
}

</style>

</head>

<body>

<aside class="sidebar">

<div class="school-logo">
<h2>SMART SCHOOL</h2>
<p>Parent Portal</p>
</div>

<div class="nav-title">Main</div>

<a href="../../parent.php" class="nav-link">
<span class="nav-icon">🏠</span>
<span>Dashboard</span>
</a>

<a href="index.php" class="nav-link">
<span class="nav-icon">📚</span>
<span>Academics</span>
</a>

<div class="nav-title">Academic</div>

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

<a href="assignments.php" class="nav-link active">
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

<div class="nav-title">Account</div>

<a href="../../profile.php" class="nav-link">
<span class="nav-icon">👤</span>
<span>Profile</span>
</a>

<a href="../../logout.php" class="nav-link">
<span class="nav-icon">🚪</span>
<span>Logout</span>
</a>

</aside>

<main class="main">

<header class="topbar">

<div>
<h1>Assignments</h1>
<p>Monitor your children's assignments</p>
</div>

<div class="profile-name">
<?= e($parent["full_name"]) ?>
</div>

</header>

<div class="content">

<div class="header">

<h2>📝 Assignments</h2>

<p>
See assignments given to your children and monitor their
submission status.
</p>

</div>

<div class="selector">

<form method="GET">

<label>Select Child</label>

<select name="student_id" onchange="this.form.submit()">

<option value="0">
All Children
</option>

<?php foreach ($children as $child): ?>

<option
value="<?= (int)$child["student_id"] ?>"
<?= $student_id == $child["student_id"] ? "selected" : "" ?>
>

<?= e($child["full_name"]) ?>

</option>

<?php endforeach; ?>

</select>

</form>

</div>

<?php if (empty($assignments)): ?>

<div class="empty">

<h3>No Assignments</h3>

<p>
There are currently no assignments available.
</p>

</div>

<?php else: ?>

<div class="assignment-grid">

<?php foreach ($assignments as $assignment): ?>

<div class="assignment-card">

<h3>
<?= e($assignment["title"]) ?>
</h3>

<div class="subject">

<?= e($assignment["subject_name"]) ?>

</div>

<div class="description">

<?= nl2br(e($assignment["description"] ?? "")) ?>

</div>

<div class="info">

<p>
<strong>Teacher:</strong>
<?= e($assignment["teacher_name"]) ?>
</p>

<?php if (!empty($assignment["student_name"])): ?>

<p>
<strong>Student:</strong>
<?= e($assignment["student_name"]) ?>
</p>

<?php endif; ?>

<p>
<strong>Assigned:</strong>
<?= e($assignment["assigned_date"]) ?>
</p>

<p>
<strong>Due:</strong>
<?= e($assignment["due_date"]) ?>
</p>

<?php

$status =
$assignment["submission_status"]
?? "NOT_SUBMITTED";

if ($status === "SUBMITTED" || $status === "GRADED") {

    $class = "submitted";

} elseif ($status === "LATE") {

    $class = "late";

} else {

    $class = "pending";
}

?>

<span class="status <?= $class ?>">

<?= e(str_replace("_"," ",$status)) ?>

</span>

<?php if (
    $assignment["marks"] !== null
): ?>

<p style="margin-top:10px;">

<strong>Marks:</strong>

<?= e($assignment["marks"]) ?>

</p>

<?php endif; ?>

</div>

</div>

<?php endforeach; ?>

</div>

<?php endif; ?>

</div>

</main>

</body>

</html>