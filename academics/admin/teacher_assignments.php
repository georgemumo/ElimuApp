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

$message = "";
$error = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['assign'])) {

    $teacher = (int)($_POST['teacher_id'] ?? 0);
    $class = (int)($_POST['class_id'] ?? 0);
    $subject = (int)($_POST['subject_id'] ?? 0);
    $year = (int)($_POST['academic_year'] ?? date('Y'));

    if (!$teacher || !$class || !$subject) {
        $error = "Please fill all fields.";
    } else {
        try {
            $stmt = $conn->prepare("
                INSERT INTO teacher_subject_assignments
                (teacher_id,class_id,subject_id,academic_year)
                VALUES (?,?,?,?)
            ");

            $stmt->execute([$teacher,$class,$subject,$year]);

            $message = "Teacher assigned successfully.";
        } catch(PDOException $e) {
            $error = "This teacher/class/subject assignment already exists.";
        }
    }
}

if(isset($_GET['delete'])) {
    $stmt=$conn->prepare("
        DELETE FROM teacher_subject_assignments
        WHERE assignment_id=?
    ");
    $stmt->execute([(int)$_GET['delete']]);
    $message="Assignment removed.";
}

$teachers=$conn->query("
    SELECT teacher_id,full_name
    FROM teachers
    ORDER BY full_name
")->fetchAll();

$classes=$conn->query("
    SELECT class_id,class_name,stream_name
    FROM classes
    ORDER BY class_name,stream_name
")->fetchAll();

$subjects=$conn->query("
    SELECT subject_id,subject_code,subject_name
    FROM subjects
    ORDER BY subject_name
")->fetchAll();

$assignments=$conn->query("
    SELECT
        tsa.assignment_id,
        t.full_name teacher_name,
        c.class_name,
        c.stream_name,
        s.subject_name,
        s.subject_code,
        tsa.academic_year
    FROM teacher_subject_assignments tsa
    JOIN teachers t ON t.teacher_id=tsa.teacher_id
    JOIN classes c ON c.class_id=tsa.class_id
    JOIN subjects s ON s.subject_id=tsa.subject_id
    ORDER BY tsa.academic_year DESC,t.full_name
")->fetchAll();
?>
<!DOCTYPE html>
<html>
<head>
<link rel="stylesheet" href="../../assets/css/school.css">
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Teacher Assignments</title>
<style>
body{margin:0;font-family:Arial;background:#f4f7fb}
.sidebar{position:fixed;width:240px;height:100vh;background:#09265c;color:white;padding:20px 15px}
.sidebar a{display:block;color:white;text-decoration:none;padding:11px;border-radius:7px;margin:4px 0}
.sidebar a:hover,.active{background:#17458f}
.main{margin-left:240px;padding:25px}
.box{background:#fff;padding:25px;border-radius:12px;margin-bottom:20px}
form{display:grid;grid-template-columns:repeat(4,1fr);gap:10px}
select,input,button{padding:11px;border:1px solid #ddd;border-radius:7px}
button{background:#0d3478;color:white;border:0}
table{width:100%;border-collapse:collapse}
th,td{padding:11px;border-bottom:1px solid #eee;text-align:left}
.delete{color:red}
.success{background:#dcfce7;padding:10px}
.error{background:#fee2e2;padding:10px}
@media(max-width:700px){.sidebar{position:relative;width:auto;height:auto}.main{margin-left:0}form{grid-template-columns:1fr}}
</style>
</head>

<body>

<div class="sidebar">
<h2>🏫 School SMS</h2>
<a href="index.php">📊 Dashboard</a>
<a href="classes.php">🏫 Classes</a>
<a href="subjects.php">📚 Subjects</a>
<a class="active" href="teacher_assignments.php">👨‍🏫 Teacher Assignments</a>
<a href="approve_marks.php">✅ Approve Marks</a>
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

<h1>Teacher Subject Assignments</h1>

<?php if($message): ?><div class="success"><?=htmlspecialchars($message)?></div><?php endif;?>
<?php if($error): ?><div class="error"><?=htmlspecialchars($error)?></div><?php endif;?>

<form method="POST">

<select name="teacher_id" required>
<option value="">Select Teacher</option>
<?php foreach($teachers as $t): ?>
<option value="<?=$t['teacher_id']?>"><?=htmlspecialchars($t['full_name'])?></option>
<?php endforeach;?>
</select>

<select name="class_id" required>
<option value="">Select Class</option>
<?php foreach($classes as $c): ?>
<option value="<?=$c['class_id']?>">
<?=htmlspecialchars($c['class_name'].' - '.$c['stream_name'])?>
</option>
<?php endforeach;?>
</select>

<select name="subject_id" required>
<option value="">Select Subject</option>
<?php foreach($subjects as $s): ?>
<option value="<?=$s['subject_id']?>">
<?=htmlspecialchars($s['subject_code'].' - '.$s['subject_name'])?>
</option>
<?php endforeach;?>
</select>

<input type="number" name="academic_year" value="<?=date('Y')?>" required>

<button name="assign">Assign Teacher</button>

</form>
</div>

<div class="box">

<h2>Current Assignments</h2>

<table>
<tr>
<th>Teacher</th>
<th>Class</th>
<th>Subject</th>
<th>Year</th>
<th>Action</th>
</tr>

<?php foreach($assignments as $a): ?>
<tr>
<td><?=htmlspecialchars($a['teacher_name'])?></td>
<td><?=htmlspecialchars($a['class_name'].' - '.$a['stream_name'])?></td>
<td><?=htmlspecialchars($a['subject_code'].' - '.$a['subject_name'])?></td>
<td><?=htmlspecialchars($a['academic_year'])?></td>
<td>
<a class="delete"
href="?delete=<?=$a['assignment_id']?>"
onclick="return confirm('Remove assignment?')">
Remove
</a>
</td>
</tr>
<?php endforeach;?>
</table>

</div>

</div>
</body>
</html>