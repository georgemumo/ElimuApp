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

    $stmt = $conn->prepare("
        SELECT parent_id, full_name
        FROM parents
        WHERE user_id = ?
        LIMIT 1
    ");

    $stmt->execute([$user_id]);

    $parent = $stmt->fetch();

    if (!$parent) {
        die("Parent account not found.");
    }

    $parent_id = $parent["parent_id"];

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

    $student_id = isset($_GET["student_id"])
        ? (int)$_GET["student_id"]
        : 0;

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

    $cbc = [];

    if ($student_id > 0) {

        $stmt = $conn->prepare("
            SELECT
                ca.performance_level,
                ca.teacher_comment,
                ca.term,
                ca.academic_year,
                sub.subject_name,
                comp.competency_name,
                comp.description,
                t.full_name AS teacher_name
            FROM cbc_assessments ca
            INNER JOIN subjects sub
                ON sub.subject_id = ca.subject_id
            INNER JOIN competencies comp
                ON comp.competency_id = ca.competency_id
            INNER JOIN teachers t
                ON t.teacher_id = ca.teacher_id
            WHERE ca.student_id = ?
            ORDER BY
                ca.academic_year DESC,
                ca.term DESC,
                sub.subject_name,
                comp.competency_name
        ");

        $stmt->execute([$student_id]);

        $cbc = $stmt->fetchAll();
    }

} catch (PDOException $e) {

    die("Database error: " . e($e->getMessage()));
}

?>

<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">

<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>CBC Progress - Parent</title>
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
    justify-content: space-between;
    align-items: center;
    padding: 0 30px;
    border-bottom: 1px solid #e5e7eb;
}

.topbar h1 {
    font-size: 21px;
    color: #0b1f3a;
}

.topbar p {
    color: #6b7280;
    font-size: 12px;
}

.profile-name {
    font-size: 14px;
    font-weight: bold;
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

.cbc-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit,minmax(300px,1fr));
    gap: 18px;
}

.cbc-card {
    background: white;
    padding: 20px;
    border-radius: 12px;
    border: 1px solid #e5e7eb;
}

.cbc-card h3 {
    color: #0b1f3a;
    margin-bottom: 8px;
}

.subject {
    color: #155a91;
    font-weight: bold;
    font-size: 12px;
}

.competency {
    color: #374151;
    font-size: 13px;
    margin: 15px 0;
}

.level {
    display: inline-block;
    background: #eaf3fb;
    color: #155a91;
    padding: 8px 12px;
    border-radius: 6px;
    font-weight: bold;
    font-size: 12px;
}

.comment {
    margin-top: 15px;
    background: #f8fafc;
    padding: 12px;
    border-radius: 6px;
    font-size: 12px;
    color: #6b7280;
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

<a href="assignments.php" class="nav-link">
<span class="nav-icon">📝</span>
<span>Assignments</span>
</a>

<a href="timetable.php" class="nav-link">
<span class="nav-icon">🗓️</span>
<span>Timetable</span>
</a>

<a href="cbc.php" class="nav-link active">
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
<h1>CBC Progress</h1>
<p>Monitor competency development</p>
</div>

<div class="profile-name">
<?= e($parent["full_name"]) ?>
</div>

</header>

<div class="content">

<div class="header">

<h2>🎯 CBC Progress</h2>

<p>
Monitor your child's competency development and teacher
assessment comments.
</p>

</div>

<div class="selector">

<form method="GET">

<label>Select Child</label>

<select name="student_id" onchange="this.form.submit()">

<option value="0">
Select a child
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

<?php if ($student_id <= 0): ?>

<div class="empty">

<h3>Select a Child</h3>

<p>
Choose a child to view CBC progress.
</p>

</div>

<?php elseif (empty($cbc)): ?>

<div class="empty">

<h3>No CBC Assessments</h3>

<p>
There are currently no CBC assessments available.
</p>

</div>

<?php else: ?>

<div class="cbc-grid">

<?php foreach ($cbc as $item): ?>

<div class="cbc-card">

<div class="subject">

<?= e($item["subject_name"]) ?>

</div>

<h3>
<?= e($item["competency_name"]) ?>
</h3>

<div class="competency">

<?= e(
    $item["description"]
    ?: "Competency assessment"
) ?>

</div>

<span class="level">

<?= e($item["performance_level"]) ?>

</span>

<div class="comment">

<strong>Teacher:</strong>
<?= e($item["teacher_name"]) ?>

<br><br>

<strong>Term:</strong>
<?= e(str_replace("_"," ",$item["term"])) ?>

-
<?= e($item["academic_year"]) ?>

<br><br>

<strong>Comment:</strong>

<?= nl2br(
    e(
        $item["teacher_comment"]
        ?: "No comment provided."
    )
) ?>

</div>

</div>

<?php endforeach; ?>

</div>

<?php endif; ?>

</div>

</main>

</body>

</html>