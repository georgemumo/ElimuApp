<?php

session_start();

require_once "../../db.php";

error_reporting(E_ALL);
ini_set('display_errors', 1);

if (
    !isset($_SESSION['logged_in']) ||
    $_SESSION['logged_in'] !== true
) {
    header("Location: ../../login.php");
    exit;
}

if (
    !isset($_SESSION['role']) ||
    strtoupper($_SESSION['role']) !== 'PARENT'
) {
    header("Location: ../../login.php");
    exit;
}

$user_id = $_SESSION['user_id'];

/* GET PARENT */
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

$parent_id = $parent['parent_id'];

/* GET CHILDREN */
$stmt = $conn->prepare("
    SELECT
        s.student_id,
        s.full_name,
        s.class_id,
        c.class_name
    FROM parent_student_map psm
    INNER JOIN students s
        ON s.student_id = psm.student_id
    INNER JOIN classes c
        ON c.class_id = s.class_id
    WHERE psm.parent_id = ?
    ORDER BY s.full_name
");

$stmt->execute([$parent_id]);
$children = $stmt->fetchAll();

/* SELECT CHILD */
$student_id = isset($_GET['student_id'])
    ? (int)$_GET['student_id']
    : 0;

$selected_child = null;

foreach ($children as $child) {

    if ((int)$child['student_id'] === $student_id) {
        $selected_child = $child;
        break;
    }

}

if (!$selected_child && !empty($children)) {

    $selected_child = $children[0];

    $student_id = (int)$selected_child['student_id'];

}

/* GET RESOURCES */
$resources = [];

if ($selected_child) {

    $stmt = $conn->prepare("
        SELECT
            lr.resource_id,
            lr.title,
            lr.description,
            lr.resource_type,
            lr.file_url,
            lr.external_url,
            lr.created_at,
            sub.subject_name,
            sub.subject_code,
            t.full_name AS teacher_name
        FROM learning_resources lr

        LEFT JOIN subjects sub
            ON sub.subject_id = lr.subject_id

        INNER JOIN teachers t
            ON t.teacher_id = lr.teacher_id

        WHERE
            lr.class_id = ?
            OR lr.class_id IS NULL

        ORDER BY lr.created_at DESC
    ");

    $stmt->execute([
        $selected_child['class_id']
    ]);

    $resources = $stmt->fetchAll();
}

?>
<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Learning Resources | Parent Academics</title>
<link rel="stylesheet" href="../../assets/css/school.css">

<style>

* {
    box-sizing: border-box;
}

body {
    margin: 0;
    font-family: Arial, sans-serif;
    background: #f4f7fb;
    color: #1f2937;
}

.sidebar {
    position: fixed;
    left: 0;
    top: 0;
    width: 240px;
    height: 100vh;
    background: linear-gradient(180deg, #071b4a, #0b3d91);
    padding: 25px 15px;
    color: white;
}

.logo {
    text-align: center;
    font-size: 22px;
    font-weight: bold;
    margin-bottom: 30px;
}

.nav a {
    display: block;
    color: white;
    text-decoration: none;
    padding: 12px;
    border-radius: 8px;
    margin-bottom: 7px;
}

.nav a:hover,
.nav a.active {
    background: rgba(255,255,255,.15);
}

.main {
    margin-left: 240px;
    padding: 30px;
}

.topbar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 25px;
}

.back {
    text-decoration: none;
    color: #0b3d91;
    font-weight: bold;
}

.selector {
    background: white;
    padding: 20px;
    border-radius: 12px;
    margin-bottom: 25px;
}

.selector select {
    padding: 12px;
    width: 100%;
    max-width: 450px;
    border: 1px solid #ddd;
    border-radius: 7px;
}

.resources {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
    gap: 20px;
}

.resource {
    background: white;
    padding: 22px;
    border-radius: 12px;
    box-shadow: 0 4px 15px rgba(0,0,0,.05);
}

.resource-type {
    display: inline-block;
    padding: 6px 10px;
    background: #eaf1ff;
    color: #0b3d91;
    border-radius: 20px;
    font-size: 12px;
    font-weight: bold;
}

.resource h3 {
    margin-bottom: 8px;
}

.resource p {
    color: #6b7280;
    line-height: 1.5;
}

.resource a {
    display: inline-block;
    margin-top: 10px;
    background: #0b3d91;
    color: white;
    padding: 9px 14px;
    border-radius: 7px;
    text-decoration: none;
}

.meta {
    font-size: 13px;
    color: #6b7280;
}

.empty {
    background: white;
    padding: 25px;
    border-radius: 12px;
}

@media(max-width:800px) {

    .sidebar {
        position: static;
        width: 100%;
        height: auto;
    }

    .main {
        margin-left: 0;
        padding: 20px;
    }
}

</style>

</head>

<body>

<div class="sidebar">

    <div class="logo">📚 School SMS</div>

    <div class="nav">

        <a href="../../parent.php">🏠 Dashboard</a>

        <a href="index.php">📚 Academics</a>

        <a href="children.php">👨‍👩‍👧 My Children</a>

        <a href="results.php">📊 Results</a>

        <a href="performance.php">📈 Performance</a>

        <a href="report_card.php">📄 Report Cards</a>

        <a href="assignments.php">📝 Assignments</a>

        <a href="timetable.php">🕐 Timetable</a>

        <a href="cbc.php">🎓 CBC</a>

        <a href="resources.php" class="active">
            📚 Resources
        </a>

        <a href="achievements.php">🏆 Achievements</a>

        <a href="../../fees.php">💰 Fees</a>
        <a href="../../messages.php">✉️ Messages</a>
        <a href="../../calender.php">🗓️ Calendar</a>

    </div>

</div>

<div class="main">

    <div class="topbar">

        <div>
            <h1>Learning Resources</h1>
            <p>Resources shared by teachers for your child.</p>
        </div>

        <a href="index.php" class="back">
            ← Back
        </a>

    </div>

    <div class="selector">

        <strong>Select Child</strong>

        <form method="GET">

            <select
                name="student_id"
                onchange="this.form.submit()">

                <?php foreach ($children as $child): ?>

                    <option
                        value="<?= (int)$child['student_id'] ?>"
                        <?= $student_id == $child['student_id'] ? 'selected' : '' ?>>

                        <?= htmlspecialchars($child['full_name']) ?>
                        -
                        <?= htmlspecialchars($child['class_name']) ?>

                    </option>

                <?php endforeach; ?>

            </select>

        </form>

    </div>

    <?php if (empty($resources)): ?>

        <div class="empty">

            <h3>No Resources Available</h3>

            <p>
                There are currently no learning resources available
                for this child's class.
            </p>

        </div>

    <?php else: ?>

        <div class="resources">

            <?php foreach ($resources as $resource): ?>

                <div class="resource">

                    <span class="resource-type">

                        <?= htmlspecialchars(
                            $resource['resource_type']
                        ) ?>

                    </span>

                    <h3>
                        <?= htmlspecialchars($resource['title']) ?>
                    </h3>

                    <p>
                        <?= nl2br(
                            htmlspecialchars(
                                $resource['description'] ?? ''
                            )
                        ) ?>
                    </p>

                    <div class="meta">

                        Subject:
                        <?= htmlspecialchars(
                            $resource['subject_name'] ?? 'General'
                        ) ?>

                        <br>

                        Teacher:
                        <?= htmlspecialchars(
                            $resource['teacher_name']
                        ) ?>

                    </div>

                    <?php if (!empty($resource['file_url'])): ?>

                        <a
                            href="../../<?= htmlspecialchars(
                                ltrim($resource['file_url'], '/')
                            ) ?>"
                            target="_blank">

                            📥 Open Resource

                        </a>

                    <?php elseif (!empty($resource['external_url'])): ?>

                        <a
                            href="<?= htmlspecialchars(
                                $resource['external_url']
                            ) ?>"
                            target="_blank">

                            🔗 Open Link

                        </a>

                    <?php endif; ?>

                </div>

            <?php endforeach; ?>

        </div>

    <?php endif; ?>

</div>

</body>
</html>