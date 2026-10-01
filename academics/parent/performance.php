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

if (!$user_id) {
    header("Location: ../../login.php");
    exit;
}

function e($value)
{
    return htmlspecialchars((string)$value, ENT_QUOTES, "UTF-8");
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

    /*
    |--------------------------------------------------------------------------
    | GET ONLY CHILDREN BELONGING TO THIS PARENT
    |--------------------------------------------------------------------------
    */

    $childrenStmt = $conn->prepare("
        SELECT
            s.student_id,
            s.full_name,
            s.adm_number,
            c.class_name,
            c.stream_name
        FROM parent_student_map psm
        INNER JOIN students s
            ON s.student_id = psm.student_id
        INNER JOIN classes c
            ON c.class_id = s.class_id
        WHERE psm.parent_id = ?
        ORDER BY s.full_name
    ");

    $childrenStmt->execute([$parent_id]);

    $children = $childrenStmt->fetchAll();

    $student_id = isset($_GET["student_id"])
        ? (int)$_GET["student_id"]
        : 0;

    /*
    |--------------------------------------------------------------------------
    | SECURITY CHECK
    |--------------------------------------------------------------------------
    */

    if ($student_id > 0) {

        $verifyStmt = $conn->prepare("
            SELECT s.student_id
            FROM parent_student_map psm
            INNER JOIN students s
                ON s.student_id = psm.student_id
            WHERE psm.parent_id = ?
            AND s.student_id = ?
            LIMIT 1
        ");

        $verifyStmt->execute([
            $parent_id,
            $student_id
        ]);

        if (!$verifyStmt->fetch()) {
            $student_id = 0;
        }
    }

    /*
    |--------------------------------------------------------------------------
    | RESULTS
    |--------------------------------------------------------------------------
    */

    $results = [];

    if ($student_id > 0) {

        $resultStmt = $conn->prepare("
            SELECT
                er.result_id,
                er.score,
                er.grade,
                er.remarks,
                er.term,
                er.academic_year,
                s.subject_name,
                s.subject_code
            FROM exam_results er
            INNER JOIN subjects s
                ON s.subject_id = er.subject_id
            WHERE er.student_id = ?
            ORDER BY
                er.academic_year DESC,
                er.term DESC,
                s.subject_name ASC
        ");

        $resultStmt->execute([$student_id]);

        $results = $resultStmt->fetchAll();

    } else {

        $resultStmt = $conn->prepare("
            SELECT
                er.result_id,
                er.score,
                er.grade,
                er.remarks,
                er.term,
                er.academic_year,
                s.subject_name,
                s.subject_code,
                st.full_name AS student_name,
                st.student_id
            FROM exam_results er
            INNER JOIN subjects s
                ON s.subject_id = er.subject_id
            INNER JOIN students st
                ON st.student_id = er.student_id
            INNER JOIN parent_student_map psm
                ON psm.student_id = st.student_id
            WHERE psm.parent_id = ?
            ORDER BY
                er.academic_year DESC,
                er.result_id DESC
        ");

        $resultStmt->execute([$parent_id]);

        $results = $resultStmt->fetchAll();
    }

} catch (PDOException $e) {

    die("Database error: " . e($e->getMessage()));
}

$selectedStudent = null;

foreach ($children as $child) {
    if ((int)$child["student_id"] === $student_id) {
        $selectedStudent = $child;
        break;
    }
}

?>

<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">

<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Results - Parent</title>
<link rel="stylesheet" href="../../assets/css/school.css">

<style>

* {
    box-sizing: border-box;
    margin: 0;
    padding: 0;
}

body {
    font-family: Arial, sans-serif;
    background: #f4f7fb;
    color: #1f2937;
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
    margin-top: 5px;
}

.nav-title {
    font-size: 11px;
    text-transform: uppercase;
    opacity: .55;
    margin: 20px 10px 8px;
}

.nav-link {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 13px 14px;
    color: white;
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
    min-height: 100vh;
}

.topbar {
    height: 75px;
    background: white;
    border-bottom: 1px solid #e5e7eb;
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0 30px;
}

.topbar h1 {
    color: #0b1f3a;
    font-size: 21px;
}

.topbar p {
    color: #6b7280;
    font-size: 12px;
    margin-top: 4px;
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
    margin-bottom: 25px;
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
    border: 1px solid #e5e7eb;
}

.selector label {
    display: block;
    font-size: 13px;
    font-weight: bold;
    margin-bottom: 8px;
}

.selector select {
    width: 100%;
    max-width: 450px;
    padding: 11px;
    border: 1px solid #d1d5db;
    border-radius: 7px;
}

.results-box {
    background: white;
    border-radius: 14px;
    border: 1px solid #e5e7eb;
    overflow-x: auto;
}

table {
    width: 100%;
    border-collapse: collapse;
    min-width: 700px;
}

th,
td {
    padding: 13px;
    border-bottom: 1px solid #edf0f3;
    text-align: left;
    font-size: 13px;
}

th {
    font-size: 11px;
    color: #6b7280;
    text-transform: uppercase;
}

.grade {
    font-weight: bold;
    color: #155a91;
}

.empty {
    padding: 40px;
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

<a href="results.php" class="nav-link active">
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
<h1>Examination Results</h1>
<p>View your children's academic results</p>
</div>

<div class="profile-name">
<?= e($parent["full_name"]) ?>
</div>

</header>

<div class="content">

<div class="header">

<h2>📊 Examination Results</h2>

<p>
View marks, grades, remarks, terms and academic years
for your children.
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
-
<?= e($child["class_name"]) ?>

</option>

<?php endforeach; ?>

</select>

</form>

</div>

<div class="results-box">

<?php if (empty($results)): ?>

<div class="empty">

<h3>No Results Available</h3>

<p>
There are no examination results available yet.
</p>

</div>

<?php else: ?>

<table>

<thead>

<tr>

<th>Student</th>
<th>Subject</th>
<th>Score</th>
<th>Grade</th>
<th>Term</th>
<th>Year</th>
<th>Remarks</th>

</tr>

</thead>

<tbody>

<?php foreach ($results as $result): ?>

<tr>

<td>
<?= e($result["student_name"] ?? $selectedStudent["full_name"] ?? "") ?>
</td>

<td>
<?= e($result["subject_name"]) ?>
</td>

<td>
<?= e(number_format((float)$result["score"], 1)) ?>%
</td>

<td class="grade">
<?= e($result["grade"] ?: "-") ?>
</td>

<td>
<?= e(str_replace("_"," ",$result["term"])) ?>
</td>

<td>
<?= e($result["academic_year"]) ?>
</td>

<td>
<?= e($result["remarks"] ?: "-") ?>
</td>

</tr>

<?php endforeach; ?>

</tbody>

</table>

<?php endif; ?>

</div>

</div>

</main>

</body>

</html>