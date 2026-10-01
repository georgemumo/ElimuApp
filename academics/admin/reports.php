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

$year=(int)($_GET['year']??date('Y'));
$term=$_GET['term']??'TERM_1';

$studentCount=$conn->query("
    SELECT COUNT(*)
    FROM students
    WHERE status='ACTIVE'
")->fetchColumn();

$teacherCount=$conn->query("
    SELECT COUNT(*)
    FROM teachers
")->fetchColumn();

$classCount=$conn->query("
    SELECT COUNT(*)
    FROM classes
")->fetchColumn();

$subjectCount=$conn->query("
    SELECT COUNT(*)
    FROM subjects
")->fetchColumn();

$stmt=$conn->prepare("
    SELECT
        COUNT(*) AS result_count,
        ROUND(AVG(score),2) AS average_score,
        MAX(score) AS highest_score,
        MIN(score) AS lowest_score
    FROM exam_results
    WHERE term=?
    AND academic_year=?
");

$stmt->execute([$term,$year]);

$summary=$stmt->fetch();

$subjectPerformance=$conn->prepare("
    SELECT
        s.subject_name,
        s.subject_code,
        COUNT(er.result_id) AS result_count,
        ROUND(AVG(er.score),2) AS average_score,
        MAX(er.score) AS highest_score
    FROM exam_results er
    JOIN subjects s ON s.subject_id=er.subject_id
    WHERE er.term=?
    AND er.academic_year=?
    GROUP BY s.subject_id
    ORDER BY average_score DESC
");

$subjectPerformance->execute([$term,$year]);

$subjectRows=$subjectPerformance->fetchAll();
?>
<!DOCTYPE html>
<html>
<head>
<link rel="stylesheet" href="../../assets/css/school.css">
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Academic Reports</title>

<style>
body{margin:0;font-family:Arial;background:#f4f7fb;color:#222}
.sidebar{position:fixed;width:240px;height:100vh;background:#09265c;color:#fff;padding:20px 15px;overflow-y:auto}
.sidebar a{display:block;color:white;text-decoration:none;padding:11px;border-radius:7px;margin:4px 0}
.sidebar a:hover,.active{background:#17458f}
.main{margin-left:240px;padding:25px}
.box{background:#fff;padding:25px;border-radius:12px;margin-bottom:20px}
form{display:flex;gap:10px}
select,input,button{padding:11px;border:1px solid #ddd;border-radius:7px}
button{background:#0d3478;color:white;border:0}
.cards{display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:15px}
.card{background:#fff;padding:20px;border-radius:10px}
.card h3{color:#666;font-size:14px}
.number{font-size:28px;font-weight:bold;color:#0d3478}
table{width:100%;border-collapse:collapse}
th,td{padding:11px;border-bottom:1px solid #eee;text-align:left}
@media(max-width:700px){
.sidebar{position:relative;width:auto;height:auto}
.main{margin-left:0}
form{flex-direction:column}
}
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
<a href="report_cards.php">📄 Report Cards</a>
<a href="timetable.php">🕒 Timetable</a>
<a href="assignments.php">📘 Assignments</a>
<a href="reports.php" class="active">📑 Reports</a>
<a href="../../admin.php">⬅ Back</a>
</div>

<div class="main">

<div class="box">

<h1>Academic Reports</h1>

<form method="GET">

<select name="term">
<option value="TERM_1" <?=$term==='TERM_1'?'selected':''?>>Term 1</option>
<option value="TERM_2" <?=$term==='TERM_2'?'selected':''?>>Term 2</option>
<option value="TERM_3" <?=$term==='TERM_3'?'selected':''?>>Term 3</option>
</select>

<input
type="number"
name="year"
value="<?=$year?>"
>

<button>Generate Report</button>

</form>

</div>

<div class="cards">

<div class="card">
<h3>Active Students</h3>
<div class="number"><?=$studentCount?></div>
</div>

<div class="card">
<h3>Teachers</h3>
<div class="number"><?=$teacherCount?></div>
</div>

<div class="card">
<h3>Classes</h3>
<div class="number"><?=$classCount?></div>
</div>

<div class="card">
<h3>Subjects</h3>
<div class="number"><?=$subjectCount?></div>
</div>

<div class="card">
<h3>Results Recorded</h3>
<div class="number"><?=htmlspecialchars($summary['result_count']??0)?></div>
</div>

<div class="card">
<h3>Average Score</h3>
<div class="number"><?=htmlspecialchars($summary['average_score']??'-')?></div>
</div>

<div class="card">
<h3>Highest Score</h3>
<div class="number"><?=htmlspecialchars($summary['highest_score']??'-')?></div>
</div>

<div class="card">
<h3>Lowest Score</h3>
<div class="number"><?=htmlspecialchars($summary['lowest_score']??'-')?></div>
</div>

</div>

<div class="box">

<h2>Subject Performance</h2>

<table>

<tr>
<th>Subject</th>
<th>Code</th>
<th>Results</th>
<th>Average</th>
<th>Highest</th>
</tr>

<?php foreach($subjectRows as $row): ?>

<tr>

<td><?=htmlspecialchars($row['subject_name'])?></td>

<td><?=htmlspecialchars($row['subject_code'])?></td>

<td><?=htmlspecialchars($row['result_count'])?></td>

<td><?=htmlspecialchars($row['average_score'])?></td>

<td><?=htmlspecialchars($row['highest_score'])?></td>

</tr>

<?php endforeach; ?>

<?php if(!$subjectRows): ?>

<tr>
<td colspan="5">No academic results found for this term.</td>
</tr>

<?php endif; ?>

</table>

</div>

</div>

</body>
</html>