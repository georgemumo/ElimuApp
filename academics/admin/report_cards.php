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

$studentId = (int)($_GET['student_id'] ?? 0);
$term = $_GET['term'] ?? 'TERM_1';
$year = (int)($_GET['year'] ?? date('Y'));

$students = $conn->query("
    SELECT student_id,adm_number,full_name
    FROM students
    WHERE status='ACTIVE'
    ORDER BY full_name
")->fetchAll();

$student = null;
$results = [];
$report = null;

if ($studentId > 0) {

    $stmt = $conn->prepare("
        SELECT
            st.*,
            c.class_name,
            c.stream_name
        FROM students st
        JOIN classes c ON c.class_id=st.class_id
        WHERE st.student_id=?
    ");

    $stmt->execute([$studentId]);

    $student = $stmt->fetch();

    if ($student) {

        $stmt = $conn->prepare("
            SELECT
                s.subject_name,
                s.subject_code,
                er.score,
                er.grade,
                er.remarks
            FROM exam_results er
            JOIN subjects s ON s.subject_id=er.subject_id
            WHERE er.student_id=?
            AND er.term=?
            AND er.academic_year=?
            ORDER BY s.subject_name
        ");

        $stmt->execute([$studentId,$term,$year]);

        $results = $stmt->fetchAll();

        $stmt = $conn->prepare("
            SELECT *
            FROM academic_reports
            WHERE student_id=?
            AND term=?
            AND academic_year=?
        ");

        $stmt->execute([$studentId,$term,$year]);

        $report = $stmt->fetch();
    }
}

$average = null;

if ($results) {

    $total = 0;

    foreach($results as $r) {
        $total += (float)$r['score'];
    }

    $average = $total / count($results);
}
?>
<!DOCTYPE html>
<html>
<head>
<link rel="stylesheet" href="../../assets/css/school.css">
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Report Cards</title>

<style>
body{margin:0;font-family:Arial;background:#f4f7fb}
.sidebar{position:fixed;width:240px;height:100vh;background:#09265c;color:#fff;padding:20px 15px;overflow-y:auto}
.sidebar a{display:block;color:white;text-decoration:none;padding:11px;border-radius:7px;margin:4px 0}
.sidebar a:hover,.active{background:#17458f}
.main{margin-left:240px;padding:25px}
.box{background:#fff;padding:25px;border-radius:12px;margin-bottom:20px}
form{display:flex;gap:10px}
select,input,button{padding:11px;border:1px solid #ddd;border-radius:7px}
button{background:#0d3478;color:white;border:0}
table{width:100%;border-collapse:collapse}
th,td{padding:11px;border-bottom:1px solid #eee;text-align:left}
.report-header{text-align:center;border-bottom:2px solid #ddd;padding-bottom:15px}
.comments{background:#f8fafc;padding:15px;border-radius:8px;margin-top:20px}
@media(max-width:700px){.sidebar{position:relative;width:auto;height:auto}.main{margin-left:0}form{flex-direction:column}}
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
<a href="publish_results.php">📢 Publish Results</a>
<a href="cbc.php">🎯 CBC</a>
<a href="class_performance.php">📈 Class Performance</a>
<a href="student_performance.php">👨‍🎓 Student Performance</a>
<a href="report_cards.php" class="active">📄 Report Cards</a>
<a href="timetable.php">🕒 Timetable</a>
<a href="assignments.php">📘 Assignments</a>
<a href="reports.php">📑 Reports</a>
<a href="../../admin.php">⬅ Back</a>
</div>

<div class="main">

<div class="box">

<h1>Report Cards</h1>

<form method="GET">

<select name="student_id" required>
<option value="">Select Student</option>

<?php foreach($students as $s): ?>

<option
value="<?=$s['student_id']?>"
<?=($studentId===$s['student_id'])?'selected':''?>
>
<?=htmlspecialchars($s['adm_number'].' - '.$s['full_name'])?>
</option>

<?php endforeach; ?>

</select>

<select name="term">
<option value="TERM_1" <?=$term==='TERM_1'?'selected':''?>>Term 1</option>
<option value="TERM_2" <?=$term==='TERM_2'?'selected':''?>>Term 2</option>
<option value="TERM_3" <?=$term==='TERM_3'?'selected':''?>>Term 3</option>
</select>

<input type="number" name="year" value="<?=$year?>">

<button>Generate Report</button>

</form>

</div>

<?php if($student): ?>

<div class="box">

<div class="report-header">

<h1>STUDENT REPORT CARD</h1>

<h2><?=htmlspecialchars($student['full_name'])?></h2>

<p>
Admission No:
<strong><?=htmlspecialchars($student['adm_number'])?></strong>
</p>

<p>
Class:
<strong>
<?=htmlspecialchars($student['class_name'].' - '.$student['stream_name'])?>
</strong>
</p>

<p>
<?=htmlspecialchars(str_replace('_',' ',$term))?>,
<?=htmlspecialchars($year)?>
</p>

</div>

<table>

<tr>
<th>Subject</th>
<th>Code</th>
<th>Score</th>
<th>Grade</th>
<th>Remarks</th>
</tr>

<?php foreach($results as $r): ?>

<tr>
<td><?=htmlspecialchars($r['subject_name'])?></td>
<td><?=htmlspecialchars($r['subject_code'])?></td>
<td><?=htmlspecialchars($r['score'])?></td>
<td><?=htmlspecialchars($r['grade'] ?? '-')?></td>
<td><?=htmlspecialchars($r['remarks'] ?? '-')?></td>
</tr>

<?php endforeach; ?>

</table>

<?php if($results): ?>

<h3>
Overall Average:
<?=number_format($average,2)?>
</h3>

<?php endif; ?>

<?php if($report): ?>

<div class="comments">

<p>
<strong>Overall Grade:</strong>
<?=htmlspecialchars($report['overall_grade'] ?? '-')?>
</p>

<p>
<strong>Class Position:</strong>
<?=htmlspecialchars($report['class_position'] ?? '-')?>
</p>

<p>
<strong>Stream Position:</strong>
<?=htmlspecialchars($report['stream_position'] ?? '-')?>
</p>

<p>
<strong>Teacher Comment:</strong><br>
<?=nl2br(htmlspecialchars($report['teacher_comment'] ?? ''))?>
</p>

<p>
<strong>Principal Comment:</strong><br>
<?=nl2br(htmlspecialchars($report['principal_comment'] ?? ''))?>
</p>

<p>
<strong>Status:</strong>
<?=htmlspecialchars($report['status'])?>
</p>

</div>

<?php endif; ?>

</div>

<?php endif; ?>

</div>

</body>
</html>