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

$classId = (int)($_GET['class_id'] ?? 0);
$term = $_GET['term'] ?? 'TERM_1';
$year = (int)($_GET['year'] ?? date('Y'));

$classes = $conn->query("
    SELECT class_id,class_name,stream_name
    FROM classes
    ORDER BY class_name,stream_name
")->fetchAll();

$students = [];

if ($classId > 0) {

    $stmt = $conn->prepare("
        SELECT
            st.student_id,
            st.adm_number,
            st.full_name,
            ROUND(AVG(er.score),2) AS average_score,
            COUNT(er.result_id) AS subject_count
        FROM students st
        LEFT JOIN exam_results er
            ON er.student_id=st.student_id
            AND er.term=?
            AND er.academic_year=?
        WHERE st.class_id=?
        AND st.status='ACTIVE'
        GROUP BY st.student_id
        ORDER BY average_score DESC
    ");

    $stmt->execute([$term,$year,$classId]);

    $students = $stmt->fetchAll();
}
?>
<!DOCTYPE html>
<html>
<head>
<link rel="stylesheet" href="../../assets/css/school.css">
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Class Performance</title>

<style>
body{margin:0;font-family:Arial;background:#f4f7fb}
.sidebar{position:fixed;width:240px;height:100vh;background:#09265c;color:#fff;padding:20px 15px;overflow-y:auto}
.sidebar a{display:block;color:#fff;text-decoration:none;padding:11px;border-radius:7px;margin:4px 0}
.sidebar a:hover,.active{background:#17458f}
.main{margin-left:240px;padding:25px}
.box{background:#fff;padding:25px;border-radius:12px;margin-bottom:20px}
form{display:flex;gap:10px}
select,input,button{padding:11px;border:1px solid #ddd;border-radius:7px}
button{background:#0d3478;color:white;border:0}
table{width:100%;border-collapse:collapse}
th,td{padding:11px;border-bottom:1px solid #eee;text-align:left}
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
<a href="class_performance.php" class="active">📈 Class Performance</a>
<a href="student_performance.php">👨‍🎓 Student Performance</a>
<a href="report_cards.php">📄 Report Cards</a>
<a href="timetable.php">🕒 Timetable</a>
<a href="assignments.php">📘 Assignments</a>
<a href="reports.php">📑 Reports</a>
<a href="../../admin.php">⬅ Back</a>
</div>

<div class="main">

<div class="box">

<h1>Class Performance</h1>

<form method="GET">

<select name="class_id" required>
<option value="">Select Class</option>

<?php foreach($classes as $c): ?>

<option
value="<?=$c['class_id']?>"
<?=($classId===$c['class_id'])?'selected':''?>
>
<?=htmlspecialchars($c['class_name'].' - '.$c['stream_name'])?>
</option>

<?php endforeach; ?>

</select>

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

<button>View Performance</button>

</form>

</div>

<?php if($classId): ?>

<div class="box">

<h2>Student Performance</h2>

<table>

<tr>
<th>Position</th>
<th>Admission No.</th>
<th>Student</th>
<th>Subjects</th>
<th>Average</th>
</tr>

<?php $position=1; foreach($students as $student): ?>

<tr>

<td><?=$position++?></td>

<td><?=htmlspecialchars($student['adm_number'])?></td>

<td><?=htmlspecialchars($student['full_name'])?></td>

<td><?=htmlspecialchars($student['subject_count'])?></td>

<td>
<?= $student['average_score'] !== null
? htmlspecialchars($student['average_score'])
: 'No results'
?>
</td>

</tr>

<?php endforeach; ?>

<?php if(!$students): ?>

<tr>
<td colspan="5">No students found.</td>
</tr>

<?php endif; ?>

</table>

</div>

<?php endif; ?>

</div>

</body>
</html>