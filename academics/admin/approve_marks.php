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

$assessmentId = (int)($_GET['assessment_id'] ?? 0);
$userId = (int)$_SESSION['user_id'];
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $assessmentId = (int)($_POST['assessment_id'] ?? 0);
    $action = $_POST['action'] ?? '';

    if ($assessmentId > 0 && in_array($action, ['APPROVED', 'REJECTED'], true)) {
        try {
            $newStatus = $action === 'APPROVED' ? 'APPROVED' : 'DRAFT';
            $stmt = $conn->prepare("UPDATE assessments SET status=? WHERE assessment_id=? AND status='SUBMITTED'");
            $stmt->execute([$newStatus, $assessmentId]);

            if ($stmt->rowCount() !== 1) {
                throw new RuntimeException('Only submitted marks can be approved or rejected.');
            }

            $log = $conn->prepare("INSERT INTO assessment_approval_logs (assessment_id,user_id,action,comment) VALUES (?,?,?,?)");
            $log->execute([$assessmentId, $userId, $action, $action === 'APPROVED' ? 'Marks approved by administrator' : 'Marks returned to teacher for correction']);
            $message = $action === 'APPROVED' ? 'Marks approved successfully.' : 'Marks returned to the teacher.';
        } catch (Throwable $exception) {
            $error = $exception->getMessage();
        }
    }
}

$assessments = $conn->query("
    SELECT
        a.assessment_id,
        a.assessment_name,
        a.max_score,
        a.status,
        c.class_name,
        c.stream_name,
        s.subject_name
    FROM assessments a
    JOIN classes c ON c.class_id=a.class_id
    JOIN subjects s ON s.subject_id=a.subject_id
    WHERE a.status='SUBMITTED'
    ORDER BY a.academic_year DESC,a.assessment_id DESC
")->fetchAll();

$marks = [];

if ($assessmentId > 0) {

    $stmt = $conn->prepare("
        SELECT
            am.mark_id,
            st.adm_number,
            st.full_name,
            am.score,
            am.grade,
            am.remarks,
            u.email AS entered_by
        FROM assessment_marks am
        JOIN students st ON st.student_id=am.student_id
        JOIN users u ON u.user_id=am.entered_by
        WHERE am.assessment_id=?
        ORDER BY st.full_name
    ");

    $stmt->execute([$assessmentId]);

    $marks = $stmt->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<link rel="stylesheet" href="../../assets/css/school.css">
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Approve Marks</title>

<style>
body{margin:0;font-family:Arial;background:#f4f7fb}
.sidebar{position:fixed;width:240px;height:100vh;background:#09265c;color:#fff;padding:20px 15px;overflow-y:auto}
.sidebar a{display:block;color:white;text-decoration:none;padding:11px;border-radius:7px;margin:4px 0}
.sidebar a:hover,.active{background:#17458f}
.main{margin-left:240px;padding:25px}
.box{background:white;padding:25px;border-radius:12px;margin-bottom:20px}
select{padding:12px;width:100%;border:1px solid #ddd;border-radius:7px}
button{padding:11px 18px;background:#0d3478;color:white;border:0;border-radius:7px;margin-top:10px}
table{width:100%;border-collapse:collapse}
th,td{padding:11px;border-bottom:1px solid #eee;text-align:left}
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
<a href="approve_marks.php" class="active">✅ Approve Marks</a>
<a href="publish_results.php">📢 Publish Results</a>
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

<h1>Approve Marks</h1>

<?php if($message): ?><div class="success"><?=htmlspecialchars($message)?></div><?php endif; ?>
<?php if($error): ?><div class="error"><?=htmlspecialchars($error)?></div><?php endif; ?>

<form method="GET">

<select name="assessment_id" required>
<option value="">Select Assessment</option>

<?php foreach($assessments as $a): ?>

<option
value="<?=$a['assessment_id']?>"
<?=($assessmentId === (int)$a['assessment_id'])?'selected':''?>
>
<?=htmlspecialchars(
$a['assessment_name'].' | '.
$a['class_name'].' - '.
$a['stream_name'].' | '.
$a['subject_name']
)?>
</option>

<?php endforeach; ?>

</select>

<button type="submit">View Marks</button>

</form>

</div>

<?php if($assessmentId && $marks): ?>

<div class="box">

<h2>Entered Marks</h2>

<table>

<tr>
<th>Admission No.</th>
<th>Student</th>
<th>Score</th>
<th>Grade</th>
<th>Remarks</th>
<th>Entered By</th>
<th>Action</th>
</tr>

<?php foreach($marks as $mark): ?>

<tr>
<td><?=htmlspecialchars($mark['adm_number'])?></td>
<td><?=htmlspecialchars($mark['full_name'])?></td>
<td><?=htmlspecialchars($mark['score'])?></td>
<td><?=htmlspecialchars($mark['grade'] ?? '-')?></td>
<td><?=htmlspecialchars($mark['remarks'] ?? '-')?></td>
<td><?=htmlspecialchars($mark['entered_by'])?></td>
<td>Marks are approved per assessment below.</td>
</tr>

<?php endforeach; ?>

</table>

</div>

<?php elseif($assessmentId): ?>

<div class="box">
<p>No marks have been entered for this assessment.</p>
</div>

<?php endif; ?>

<?php if($assessmentId): ?>
<div class="box">
<form method="POST">
<input type="hidden" name="assessment_id" value="<?=$assessmentId?>">
<button name="action" value="APPROVED">Approve Marks</button>
<button name="action" value="REJECTED" style="background:#991b1b">Reject Marks</button>
</form>
</div>
<?php endif; ?>

</div>

</body>
</html>