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

/* TERM AND YEAR */
$term = $_GET['term'] ?? 'TERM_1';
$year = isset($_GET['year'])
    ? (int)$_GET['year']
    : (int)date('Y');

$valid_terms = ['TERM_1', 'TERM_2', 'TERM_3'];

if (!in_array($term, $valid_terms, true)) {
    $term = 'TERM_1';
}

/* RESULTS */
$results = [];

if ($selected_child) {

    $stmt = $conn->prepare("
        SELECT
            sub.subject_name,
            sub.subject_code,
            er.score,
            er.grade,
            er.remarks
        FROM exam_results er
        INNER JOIN subjects sub
            ON sub.subject_id = er.subject_id
        WHERE er.student_id = ?
          AND er.term = ?
          AND er.academic_year = ?
        ORDER BY sub.subject_name
    ");

    $stmt->execute([
        $student_id,
        $term,
        $year
    ]);

    $results = $stmt->fetchAll();
}

/* ACADEMIC REPORT */
$report = null;

if ($selected_child) {

    $stmt = $conn->prepare("
        SELECT *
        FROM academic_reports
        WHERE student_id = ?
          AND term = ?
          AND academic_year = ?
        LIMIT 1
    ");

    $stmt->execute([
        $student_id,
        $term,
        $year
    ]);

    $report = $stmt->fetch();
}

/* CALCULATE AVERAGE */
$total_score = 0;
$count = count($results);

foreach ($results as $result) {
    $total_score += (float)$result['score'];
}

$average = $count > 0
    ? $total_score / $count
    : 0;

?>
<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Report Card | Parent Academics</title>
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
    color: white;
    padding: 25px 15px;
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

.controls {
    background: white;
    padding: 20px;
    border-radius: 12px;
    margin-bottom: 25px;
}

.controls form {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
}

select,
button {
    padding: 11px;
    border: 1px solid #ddd;
    border-radius: 7px;
}

button {
    background: #0b3d91;
    color: white;
    border: none;
    cursor: pointer;
}

.report {
    background: white;
    padding: 30px;
    border-radius: 12px;
    box-shadow: 0 4px 15px rgba(0,0,0,.05);
}

.student-info {
    text-align: center;
    margin-bottom: 25px;
}

.student-info h2 {
    margin-bottom: 8px;
}

.summary {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 15px;
    margin-bottom: 25px;
}

.summary-card {
    padding: 20px;
    background: #f5f8ff;
    border-radius: 10px;
    text-align: center;
}

.summary-card strong {
    display: block;
    font-size: 24px;
    color: #0b3d91;
    margin-top: 8px;
}

table {
    width: 100%;
    border-collapse: collapse;
}

th,
td {
    padding: 13px;
    border-bottom: 1px solid #eee;
    text-align: left;
}

th {
    background: #0b3d91;
    color: white;
}

.comment {
    margin-top: 25px;
    padding: 20px;
    background: #f8fafc;
    border-radius: 10px;
}

@media(max-width: 800px) {

    .sidebar {
        position: static;
        width: 100%;
        height: auto;
    }

    .main {
        margin-left: 0;
        padding: 20px;
    }

    .summary {
        grid-template-columns: 1fr;
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

        <a href="report_card.php" class="active">📄 Report Cards</a>

        <a href="assignments.php">📝 Assignments</a>

        <a href="timetable.php">🕐 Timetable</a>

        <a href="cbc.php">🎓 CBC</a>

        <a href="resources.php">📚 Resources</a>

        <a href="achievements.php">🏆 Achievements</a>

        <a href="../../fees.php">💰 Fees</a>
        <a href="../../messages.php">✉️ Messages</a>
        <a href="../../calender.php">🗓️ Calendar</a>

    </div>

</div>

<div class="main">

    <div class="topbar">

        <div>
            <h1>Academic Report Card</h1>
            <p>View your child's academic report.</p>
        </div>

        <a href="index.php" class="back">
            ← Back
        </a>

    </div>

    <div class="controls">

        <form method="GET">

            <select name="student_id">

                <?php foreach ($children as $child): ?>

                    <option
                        value="<?= (int)$child['student_id'] ?>"
                        <?= $student_id == $child['student_id'] ? 'selected' : '' ?>>

                        <?= htmlspecialchars($child['full_name']) ?>

                    </option>

                <?php endforeach; ?>

            </select>

            <select name="term">

                <?php foreach ($valid_terms as $term_option): ?>

                    <option
                        value="<?= $term_option ?>"
                        <?= $term === $term_option ? 'selected' : '' ?>>

                        <?= str_replace('_', ' ', $term_option) ?>

                    </option>

                <?php endforeach; ?>

            </select>

            <select name="year">

                <?php for ($y = date('Y'); $y >= date('Y') - 5; $y--): ?>

                    <option
                        value="<?= $y ?>"
                        <?= $year == $y ? 'selected' : '' ?>>

                        <?= $y ?>

                    </option>

                <?php endfor; ?>

            </select>

            <button type="submit">
                View Report
            </button>

        </form>

    </div>

    <?php if (!$selected_child): ?>

        <div class="report">
            No child is linked to your account.
        </div>

    <?php else: ?>

        <div class="report">

            <div class="student-info">

                <h2>
                    <?= htmlspecialchars($selected_child['full_name']) ?>
                </h2>

                <div>
                    Admission No:
                    <strong>
                        <?= htmlspecialchars($selected_child['adm_number']) ?>
                    </strong>
                </div>

                <div>
                    Class:
                    <?= htmlspecialchars($selected_child['class_name']) ?>
                    -
                    <?= htmlspecialchars($selected_child['stream_name']) ?>
                </div>

                <p>
                    <?= str_replace('_', ' ', $term) ?>
                    -
                    <?= $year ?>
                </p>

            </div>

            <div class="summary">

                <div class="summary-card">
                    Subjects
                    <strong><?= $count ?></strong>
                </div>

                <div class="summary-card">
                    Average
                    <strong><?= number_format($average, 2) ?></strong>
                </div>

                <div class="summary-card">
                    Grade
                    <strong>
                        <?= htmlspecialchars($report['overall_grade'] ?? '-') ?>
                    </strong>
                </div>

            </div>

            <?php if (!empty($results)): ?>

                <table>

                    <thead>

                        <tr>
                            <th>Subject</th>
                            <th>Code</th>
                            <th>Score</th>
                            <th>Grade</th>
                            <th>Remarks</th>
                        </tr>

                    </thead>

                    <tbody>

                        <?php foreach ($results as $result): ?>

                            <tr>

                                <td>
                                    <?= htmlspecialchars($result['subject_name']) ?>
                                </td>

                                <td>
                                    <?= htmlspecialchars($result['subject_code']) ?>
                                </td>

                                <td>
                                    <?= htmlspecialchars($result['score']) ?>
                                </td>

                                <td>
                                    <?= htmlspecialchars($result['grade'] ?? '-') ?>
                                </td>

                                <td>
                                    <?= htmlspecialchars($result['remarks'] ?? '-') ?>
                                </td>

                            </tr>

                        <?php endforeach; ?>

                    </tbody>

                </table>

            <?php else: ?>

                <p>
                    No examination results are available for this term.
                </p>

            <?php endif; ?>

            <?php if ($report): ?>

                <div class="comment">

                    <strong>Teacher Comment</strong>

                    <p>
                        <?= nl2br(
                            htmlspecialchars(
                                $report['teacher_comment'] ?? 'No teacher comment.'
                            )
                        ) ?>
                    </p>

                    <strong>Principal Comment</strong>

                    <p>
                        <?= nl2br(
                            htmlspecialchars(
                                $report['principal_comment'] ?? 'No principal comment.'
                            )
                        ) ?>
                    </p>

                </div>

            <?php endif; ?>

        </div>

    <?php endif; ?>

</div>

</body>
</html>