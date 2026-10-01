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

$assignments=$conn->query("
    SELECT
        a.assignment_id,
        a.title,
        a.description,
        a.assigned_date,
        a.due_date,
        a.status,
        t.full_name AS teacher_name,
        c.class_name,
        c.stream_name,
        s.subject_name,
        s.subject_code,
        COUNT(asub.submission_id) AS submissions
    FROM assignments a
    JOIN teachers t ON t.teacher_id=a.teacher_id
    JOIN classes c ON c.class_id=a.class_id
    JOIN subjects s ON s.subject_id=a.subject_id
    LEFT JOIN assignment_submissions asub
        ON asub.assignment_id=a.assignment_id
    GROUP BY a.assignment_id
    ORDER BY a.created_at DESC
")->fetchAll();

if(isset($_GET['delete'])){

    try{

        $stmt=$conn->prepare("
            DELETE FROM assignments
            WHERE assignment_id=?
        ");

        $stmt->execute([(int)$_GET['delete']]);

        header("Location: assignments.php");
        exit;

    }catch(PDOException $e){}
}
?>
<!DOCTYPE html>
<html>
<head>
<link rel="stylesheet" href="../../assets/css/school.css">
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Assignments</title>

<style>
body{margin:0;font-family:Arial;background:#f4f7fb}
.sidebar{position:fixed;width:240px;height:100vh;background:#09265c;color:#fff;padding:20px 15px;overflow-y:auto}
.sidebar a{display:block;color:#fff;text-decoration:none;padding:11px;border-radius:7px;margin:4px 0}
.sidebar a:hover,.active{background:#17458f}
.main{margin-left:240px;padding:25px}
.box{background:white;padding:25px;border-radius:12px}
table{width:100%;border-collapse:collapse}
th,td{padding:11px;border-bottom:1px solid #eee;text-align:left}
.delete{color:red}
.badge{background:#eee;padding:5px 8px;border-radius:5px}
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
<a href="publish_results.php">📢 Publish Results</a>
<a href="cbc.php">🎯 CBC</a>
<a href="class_performance.php">📈 Class Performance</a>
<a href="student_performance.php">👨‍🎓 Student Performance</a>
<a href="report_cards.php">📄 Report Cards</a>
<a href="timetable.php">🕒 Timetable</a>
<a href="assignments.php" class="active">📘 Assignments</a>
<a href="reports.php">📑 Reports</a>
<a href="../../admin.php">⬅ Back</a>
</div>

<div class="main">

<div class="box">

<h1>Assignments</h1>

<table>

<tr>
<th>Title</th>
<th>Subject</th>
<th>Class</th>
<th>Teacher</th>
<th>Assigned</th>
<th>Due</th>
<th>Submissions</th>
<th>Status</th>
<th>Action</th>
</tr>

<?php foreach($assignments as $a): ?>

<tr>

<td><?=htmlspecialchars($a['title'])?></td>

<td>
<?=htmlspecialchars($a['subject_code'].' - '.$a['subject_name'])?>
</td>

<td>
<?=htmlspecialchars($a['class_name'].' - '.$a['stream_name'])?>
</td>

<td><?=htmlspecialchars($a['teacher_name'])?></td>

<td><?=htmlspecialchars($a['assigned_date'])?></td>

<td><?=htmlspecialchars($a['due_date'])?></td>

<td><?=htmlspecialchars($a['submissions'])?></td>

<td>
<span class="badge">
<?=htmlspecialchars($a['status'])?>
</span>
</td>

<td>

<a
class="delete"
href="?delete=<?=$a['assignment_id']?>"
onclick="return confirm('Delete this assignment?')"
>
Delete
</a>

</td>

</tr>

<?php endforeach; ?>

<?php if(!$assignments): ?>

<tr>
<td colspan="9">No assignments found.</td>
</tr>

<?php endif; ?>

</table>

</div>

</div>
</body>
</html>