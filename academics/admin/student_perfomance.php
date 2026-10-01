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

$students = $conn->query("
    SELECT student_id,adm_number,full_name
    FROM students
    WHERE status='ACTIVE'
    ORDER BY full_name
")->fetchAll();

$results = [];
$student = null;

if ($studentId > 0) {

    $stmt = $conn->prepare("
        SELECT
            st.student_id,
            st.adm_number,
            st.full_name,
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
                er.term,
                er.academic_year,
                s.subject_name,
                s.subject_code,
                er.score,
                er.grade,
                er.remarks
            FROM exam_results er
            JOIN subjects s ON s.subject_id=er.subject_id
            WHERE er.student_id=?
            ORDER BY er.academic_year DESC,er.term,s.subject_name
        ");

        $stmt->execute([$studentId]);

        $results = $stmt->fetchAll();
    }
}
?>
<!DOCTYPE html>
<html>
<head>
<link rel="stylesheet" href="../../assets/css/school.css">
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Student Performance</title>

<style>
body{margin:0;font-family:Arial;background:#f4f7fb}
.sidebar{position:fixed;width:240px;height:100vh;background:#09265c;color:#fff;padding:20px 15px;overflow-y:auto}
.sidebar a{display:block;color:#fff;text-decoration:none;padding:11px;border-radius:7px;margin:4px 0}
.sidebar a:hover,.active{background:#17458f}
.main{margin-left:240px;padding:25px}
.box{background:#fff;padding:25px;border-radius:12px;margin-bottom:20px}
form{display:flex;gap:10px}
select,button{padding:11px;border:1px solid #ddd;border-radius:7px}
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
<a href="class_performance.php">📈 Class Performance</a>
<a href="student_performance.php" class="active">👨‍🎓 Student Performance</a>
<a href="report_cards.php">📄 Report Cards</a>
<a href="timetable.php">🕒 Timetable</a>
<a href="assignments.php">📘 Assignments</a>
<a href="reports.php">📑 Reports</a>
<a href="../../admin.php">⬅ Back</a>
</div>

<div class="main">

<div class="box">

<h1>Student Performance</h1>

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

<button>View Performance</button>

</form>

</div>

<?php if($student): ?>

<div class="box">

<h2><?=htmlspecialchars($student['full_name'])?></h2>

<p>
Admission Number:
<strong><?=htmlspecialchars($student['adm_number'])?></strong>
</p>

<p>
Class:
<strong>
<?=htmlspecialchars($student['class_name'].' - '.$student['stream_name'])?>
</strong>
</p>

</div>

<div class="box">

<h2>Academic Results</h2>

<table>

<tr>
<th>Year</th>
<th>Term</th>
<th>Subject</th>
<th>Score</th>
<th>Grade</th>
<th>Remarks</th>
</tr>

<?php foreach($results as $r): ?>

<tr>
<td><?=htmlspecialchars($r['academic_year'])?></td>
<td><?=htmlspecialchars(str_replace('_',' ',$r['term']))?></td>
<td><?=htmlspecialchars($r['subject_name'])?></td>
<td><?=htmlspecialchars($r['score'])?></td>
<td><?=htmlspecialchars($r['grade'] ?? '-')?></td>
<td><?=htmlspecialchars($r['remarks'] ?? '-')?></td>
</tr>

<?php endforeach; ?>

<?php if(!$results): ?>

<tr>
<td colspan="6">No academic results found.</td>
</tr>

<?php endif; ?>

</table>

</div>

<?php endif; ?>

</div>
</body>
</html>