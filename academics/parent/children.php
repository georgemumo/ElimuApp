<?php

session_start();

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once "../../db.php";

if (
    !isset($_SESSION["logged_in"]) ||
    $_SESSION["logged_in"] !== true
) {
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
        SELECT
            p.parent_id,
            p.full_name,
            p.phone,
            p.relationship,
            p.profile_pic,
            u.email
        FROM parents p
        INNER JOIN users u ON u.user_id = p.user_id
        WHERE p.user_id = ?
        LIMIT 1
    ");

    $parentStmt->execute([$user_id]);

    $parent = $parentStmt->fetch();

    if (!$parent) {
        die("Parent account not found.");
    }

    $parent_id = $parent["parent_id"];


    $childrenStmt = $conn->prepare("
        SELECT
            s.student_id,
            s.adm_number,
            s.full_name,
            s.gender,
            s.date_of_birth,
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

    die("Database error: " . e($e->getMessage()));
}

$parentName = $parent["full_name"];

?>

<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">

<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>My Children - Smart School</title>
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
    overflow-y: auto;
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
    letter-spacing: 1px;
    opacity: .55;
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
    color: white;
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
    font-size: 21px;
    color: #0b1f3a;
}

.topbar p {
    font-size: 12px;
    color: #6b7280;
    margin-top: 4px;
}

.profile-name {
    font-size: 14px;
    font-weight: bold;
}

.content {
    padding: 30px;
}

.page-header {
    background: linear-gradient(135deg,#0b1f3a,#155a91);
    color: white;
    padding: 28px;
    border-radius: 14px;
    margin-bottom: 25px;
}

.page-header h2 {
    font-size: 25px;
    margin-bottom: 8px;
}

.page-header p {
    font-size: 14px;
    opacity: .85;
}

.children-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit,minmax(300px,1fr));
    gap: 20px;
}

.child-card {
    background: white;
    border-radius: 14px;
    padding: 22px;
    border: 1px solid #e5e7eb;
    box-shadow: 0 3px 12px rgba(0,0,0,.04);
}

.child-top {
    display: flex;
    align-items: center;
    gap: 15px;
    margin-bottom: 20px;
}

.child-avatar {
    width: 65px;
    height: 65px;
    border-radius: 50%;
    background: #dbeafe;
    color: #0b1f3a;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 20px;
    font-weight: bold;
}

.child-info h3 {
    font-size: 18px;
    color: #0b1f3a;
}

.child-info p {
    color: #6b7280;
    font-size: 12px;
    margin-top: 5px;
}

.details {
    border-top: 1px solid #edf0f3;
    padding-top: 15px;
}

.detail {
    display: flex;
    justify-content: space-between;
    padding: 8px 0;
    font-size: 13px;
}

.detail span:first-child {
    color: #6b7280;
}

.detail strong {
    color: #123d6a;
}

.actions {
    display: grid;
    grid-template-columns: repeat(2,1fr);
    gap: 8px;
    margin-top: 18px;
}

.btn {
    background: #f1f5f9;
    color: #123d6a;
    border: 1px solid #dbe3ec;
    border-radius: 7px;
    padding: 10px;
    text-align: center;
    font-size: 12px;
}

.btn:hover {
    background: #e5eff8;
}

.empty {
    background: white;
    border-radius: 12px;
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

<a href="index.php" class="nav-link active">
    <span class="nav-icon">📚</span>
    <span>Academics</span>
</a>

<div class="nav-title">Academic</div>

<a href="children.php" class="nav-link active">
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
    <h1>My Children</h1>
    <p>View your children's academic profiles</p>
</div>

<div class="profile-name">
    <?= e($parentName) ?>
</div>

</header>

<div class="content">

<section class="page-header">

<h2>My Children 👨‍👩‍👧</h2>

<p>
View your children's class information and quickly access
their academic records.
</p>

</section>

<?php if (empty($children)): ?>

<div class="empty">

<h3>No Children Found</h3>

<p>
There are currently no students linked to your parent account.
Please contact the school administration.
</p>

</div>

<?php else: ?>

<div class="children-grid">

<?php foreach ($children as $child): ?>

<?php

$parts = preg_split('/\s+/', trim($child["full_name"]));

$initials = "";

foreach ($parts as $part) {

    if (!empty($part)) {
        $initials .= strtoupper(substr($part,0,1));
    }

    if (strlen($initials) >= 2) {
        break;
    }
}

?>

<div class="child-card">

<div class="child-top">

<div class="child-avatar">
    <?= e($initials ?: "S") ?>
</div>

<div class="child-info">

<h3>
    <?= e($child["full_name"]) ?>
</h3>

<p>
    <?= e($child["class_name"]) ?>
    -
    <?= e($child["stream_name"]) ?>
</p>

</div>

</div>

<div class="details">

<div class="detail">
    <span>Admission Number</span>
    <strong><?= e($child["adm_number"]) ?></strong>
</div>

<div class="detail">
    <span>Gender</span>
    <strong><?= e($child["gender"]) ?></strong>
</div>

<div class="detail">
    <span>Date of Birth</span>
    <strong><?= e($child["date_of_birth"]) ?></strong>
</div>

<div class="detail">
    <span>Status</span>
    <strong><?= e($child["status"]) ?></strong>
</div>

</div>

<div class="actions">

<a
    href="results.php?student_id=<?= (int)$child["student_id"] ?>"
    class="btn"
>
📊 Results
</a>

<a
    href="performance.php?student_id=<?= (int)$child["student_id"] ?>"
    class="btn"
>
📈 Performance
</a>

<a
    href="report_card.php?student_id=<?= (int)$child["student_id"] ?>"
    class="btn"
>
📄 Report Card
</a>

<a
    href="timetable.php?student_id=<?= (int)$child["student_id"] ?>"
    class="btn"
>
🗓️ Timetable
</a>

</div>

</div>

<?php endforeach; ?>

</div>

<?php endif; ?>

</div>

</main>

</body>

</html>