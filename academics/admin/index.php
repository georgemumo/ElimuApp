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
        s.adm_number,
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

/* GET ACHIEVEMENTS */
$achievements = [];

if ($selected_child) {

    $stmt = $conn->prepare("
        SELECT
            achievement_id,
            title,
            description,
            achievement_type,
            achievement_date,
            certificate_url
        FROM student_achievements
        WHERE student_id = ?
        ORDER BY achievement_date DESC, created_at DESC
    ");

    $stmt->execute([
        $student_id
    ]);

    $achievements = $stmt->fetchAll();
}

?>
<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Achievements | Parent Academics</title>

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
    color: #0b3d91;
    text-decoration: none;
    font-weight: bold;
}

.selector {
    background: white;
    padding: 20px;
    border-radius: 12px;
    margin-bottom: 25px;
}

.selector select {
    display: block;
    width: 100%;
    max-width: 450px;
    margin-top: 8px;
    padding: 12px;
    border: 1px solid #ddd;
    border-radius: 7px;
}

.achievements {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
    gap: 20px;
}

.achievement {
    background: white;
    padding: 25px;
    border-radius: 12px;
    box-shadow: 0 4px 15px rgba(0,0,0,.05);
    border-top: 4px solid #0b3d91;
}

.icon {
    font-size: 35px;
    margin-bottom: 10px;
}

.type {
    display: inline-block;
    background: #eaf1ff;
    color: #0b3d91;
    padding: 6px 10px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: bold;
}

.achievement h2 {
    margin-bottom: 8px;
}

.achievement p {
    color: #6b7280;
    line-height: 1.5;
}

.date {
    font-size: 13px;
    color: #6b7280;
}

.certificate {
    display: inline-block;
    margin-top: 12px;
    padding: 9px 14px;
    background: #0b3d91;
    color: white;
    border-radius: 7px;
    text-decoration: none;
}

.empty {
    background: white;
    padding: 30px;
    border-radius: 12px;
    text-align: center;
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

    <div class="logo">
        📚 School SMS
    </div>

    <div class="nav">

        <a href="../../parent.php">
            🏠 Dashboard
        </a>

        <a href="index.php">
            📚 Academics
        </a>

        <a href="children.php">
            👨‍👩‍👧 My Children
        </a>

        <a href="results.php">
            📊 Results
        </a>

        <a href="performance.php">
            📈 Performance
        </a>

        <a href="report_card.php">
            📄 Report Cards
        </a>

        <a href="assignments.php">
            📝 Assignments
        </a>

        <a href="timetable.php">
            🕐 Timetable
        </a>

        <a href="cbc.php">
            🎓 CBC
        </a>

        <a href="resources.php">
            📚 Resources
        </a>

        <a href="achievements.php" class="active">
            🏆 Achievements
        </a>

    </div>

</div>

<div class="main">

    <div class="topbar">

        <div>

            <h1>Achievements</h1>

            <p>
                Celebrate your child's academic and extracurricular achievements.
            </p>

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

    <?php if (!$selected_child): ?>

        <div class="empty">

            <h2>No Child Found</h2>

            <p>
                No student is currently linked to your parent account.
            </p>

        </div>

    <?php elseif (empty($achievements)): ?>

        <div class="empty">

            <div class="icon">🏆</div>

            <h2>No Achievements Yet</h2>

            <p>
                There are currently no achievements recorded
                for <?= htmlspecialchars($selected_child['full_name']) ?>.
            </p>

        </div>

    <?php else: ?>

        <div class="achievements">

            <?php foreach ($achievements as $achievement): ?>

                <div class="achievement">

                    <div class="icon">
                        🏆
                    </div>

                    <span class="type">

                        <?= htmlspecialchars(
                            $achievement['achievement_type']
                        ) ?>

                    </span>

                    <h2>
                        <?= htmlspecialchars(
                            $achievement['title']
                        ) ?>
                    </h2>

                    <?php if (!empty($achievement['description'])): ?>

                        <p>

                            <?= nl2br(
                                htmlspecialchars(
                                    $achievement['description']
                                )
                            ) ?>

                        </p>

                    <?php endif; ?>

                    <?php if (!empty($achievement['achievement_date'])): ?>

                        <div class="date">

                            📅
                            <?= date(
                                'd M Y',
                                strtotime(
                                    $achievement['achievement_date']
                                )
                            ) ?>

                        </div>

                    <?php endif; ?>

                    <?php if (!empty($achievement['certificate_url'])): ?>

                        <a
                            href="../../<?= htmlspecialchars(
                                ltrim(
                                    $achievement['certificate_url'],
                                    '/'
                                )
                            ) ?>"
                            target="_blank"
                            class="certificate">

                            📜 View Certificate

                        </a>

                    <?php endif; ?>

                </div>

            <?php endforeach; ?>

        </div>

    <?php endif; ?>

</div>

</body>
</html>