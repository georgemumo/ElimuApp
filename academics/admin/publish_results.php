<?php
session_start();
require_once "../../db.php";

if (
    !isset($_SESSION['logged_in']) ||
    $_SESSION['logged_in'] !== true ||
    !isset($_SESSION['role'])
) {
    header("Location: ../../login.php");
    exit;
}

$role = strtoupper(trim((string)$_SESSION['role']));

if ($role !== 'SCHOOL_ADMIN' && $role !== 'SUPER_ADMIN') {
    header("Location: ../../login.php");
    exit;
}

$userId = (int)$_SESSION['user_id'];
$message = "";
$error = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $assessmentId = (int)($_POST['assessment_id'] ?? 0);

    if ($assessmentId > 0) {

        try {

            $stmt = $conn->prepare("
                UPDATE assessments
                SET status='PUBLISHED'
                WHERE assessment_id=?
                AND status='APPROVED'
            ");

            $stmt->execute([$assessmentId]);

            $log = $conn->prepare("
                INSERT INTO assessment_approval_logs
                (assessment_id,user_id,action,comment)
                VALUES (?,?, 'PUBLISHED',?)
            ");

            $log->execute([
                $assessmentId,
                $userId,
                "Results published by administrator"
            ]);

            $message = "Results published successfully.";

        } catch(PDOException $e) {
            $error = "Unable to publish results.";
        }
    }
}

$assessments = $conn->query("
    SELECT
        a.assessment_id,
        a.assessment_name,
        a.term,
        a.academic_year,
        a.status,
        c.class_name,
        c.stream_name,
        s.subject_name,
        COUNT(am.mark_id) AS marks
    FROM assessments a
    JOIN classes c ON c.class_id=a.class_id
    JOIN subjects s ON s.subject_id=a.subject_id
    LEFT JOIN assessment_marks am ON am.assessment_id=a.assessment_id
    WHERE a.status IN ('APPROVED','PUBLISHED')
    GROUP BY a.assessment_id
    ORDER BY a.academic_year DESC,a.assessment_id DESC
")->fetchAll();
?>
<!DOCTYPE html>
<html>
<head>
<link rel="stylesheet" href="../../assets/css/school.css">
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Publish Results</title>

<style>
body{margin:0;font-family:Arial;background:#f4f7fb}
.sidebar{position:fixed;width:240px;height:100vh;background:#09265c;color:#fff;padding:20px 15px;overflow-y:auto}
.sidebar a{display:block;color:#fff;text-decoration:none;padding:11px;border-radius:7px;margin:4px 0}
.sidebar a:hover,.active{background:#17458f}
.main{margin-left:240px;padding:25px}
.box{background:#fff;padding:25px;border-radius:12px}
table{width:100%;border-collapse:collapse}
th,td{padding:11px;border-bottom:1px solid #eee;text-align:left}
button{background:#0d3478;color:#fff;border:0;padding:9px 14px;border-radius:6px}
.success{background:#dcfce7;color:#166534;padding:12px;margin-bottom:15px}
.error{background:#fee2e2;color:#991b1b;padding:12px;margin-bottom:15px}
@media(max-width:700px){.sidebar{position:relative;width:auto;height:auto}.main{margin-left:0}}
</style>
</head>

<body>

<div class="sidebar">
<h2>🏫 School SMS</h2>
<a href="index.php">📊 Dashboard</a>
<a href="classes.php">🏫 Classes</a>
<a href="subjects.php">📚 Subjects</a>
<a href="teacher_assignments.php">👨‍🏫 Teacher Assignments</a>
<a href="approve_marks.php">✅ Approve Marks</a>
<a href="publish_results.php" class="active">📢 Publish Results</a>
<a href="cbc.php">🎯 CBC</a>
<a href="class_performance.php">📈 Class Performance</a>
<a href="student_performance.php">👨‍🎓 Student Performance</a>
<a href="report_cards.php">📄 Report Cards</a>
<a href="timetable.php">🕒 Timetable</a>
<a href="assignments.php">📘 Assignments</a>
<a href="reports.php">📑 Reports</a>
<a href="../../admin.php">⬅ Back</a>
</div>

<div class="main">

<div class="box">

<h1>Publish Results</h1>

<?php if($message): ?>
<div class="success"><?=htmlspecialchars($message)?></div>
<?php endif; ?>

<?php if($error): ?>
<div class="error"><?=htmlspecialchars($error)?></div>
<?php endif; ?>

<table>

<tr>
<th>Assessment</th>
<th>Class</th>
<th>Subject</th>
<th>Term</th>
<th>Marks</th>
<th>Status</th>
<th>Action</th>
</tr>

<?php foreach($assessments as $a): ?>

<tr>

<td><?=htmlspecialchars($a['assessment_name'])?></td>

<td>
<?=htmlspecialchars($a['class_name'].' - '.$a['stream_name'])?>
</td>

<td><?=htmlspecialchars($a['subject_name'])?></td>

<td><?=htmlspecialchars(str_replace('_',' ',$a['term']))?></td>

<td><?=htmlspecialchars($a['marks'])?></td>

<td><?=htmlspecialchars($a['status'])?></td>

<td>

<?php if($a['status']==='APPROVED'): ?>

<form method="POST">
<input type="hidden"
name="assessment_id"
value="<?=$a['assessment_id']?>">
<button name="publish">Publish</button>
</form>

<?php else: ?>

Published

<?php endif; ?>

</td>

</tr>

<?php endforeach; ?>

</table>

</div>

</div>
</body>
</html>