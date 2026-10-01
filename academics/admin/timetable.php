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

$message="";
$error="";

if($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['add_timetable'])){

    $classId=(int)($_POST['class_id']??0);
    $subjectId=(int)($_POST['subject_id']??0);
    $teacherId=(int)($_POST['teacher_id']??0);
    $day=$_POST['day_of_week']??'MONDAY';
    $start=$_POST['start_time']??'';
    $end=$_POST['end_time']??'';

    if(!$classId || !$subjectId || !$teacherId || !$start || !$end){
        $error="Please fill all fields.";
    } elseif($start >= $end){
        $error="End time must be later than start time.";
    } else {

        try{

            $stmt=$conn->prepare("
                INSERT INTO timetables
                (class_id,subject_id,teacher_id,day_of_week,start_time,end_time)
                VALUES(?,?,?,?,?,?)
            ");

            $stmt->execute([
                $classId,
                $subjectId,
                $teacherId,
                $day,
                $start,
                $end
            ]);

            $message="Timetable entry added.";

        }catch(PDOException $e){
            $error="Unable to add timetable entry.";
        }
    }
}

if(isset($_GET['delete'])){

    $stmt=$conn->prepare("
        DELETE FROM timetables
        WHERE timetable_id=?
    ");
$stmt->execute([(int)$_GET['delete']]);
    

    $message="Timetable entry deleted.";
}

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

$teachers=$conn->query("
    SELECT teacher_id,full_name
    FROM teachers
    ORDER BY full_name
")->fetchAll();

$timetable=$conn->query("
    SELECT
        tt.*,
        c.class_name,
        c.stream_name,
        s.subject_name,
        s.subject_code,
        t.full_name AS teacher_name
    FROM timetables tt
    JOIN classes c ON c.class_id=tt.class_id
    JOIN subjects s ON s.subject_id=tt.subject_id
    JOIN teachers t ON t.teacher_id=tt.teacher_id
    ORDER BY
        FIELD(
            tt.day_of_week,
            'MONDAY',
            'TUESDAY',
            'WEDNESDAY',
            'THURSDAY',
            'FRIDAY',
            'SATURDAY'
        ),
        tt.start_time
")->fetchAll();
?>
<!DOCTYPE html>
<html>
<head>
<link rel="stylesheet" href="../../assets/css/school.css">
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Timetable</title>

<style>
body{margin:0;font-family:Arial;background:#f4f7fb}
.sidebar{position:fixed;width:240px;height:100vh;background:#09265c;color:#fff;padding:20px 15px;overflow-y:auto}
.sidebar a{display:block;color:#fff;text-decoration:none;padding:11px;border-radius:7px;margin:4px 0}
.sidebar a:hover,.active{background:#17458f}
.main{margin-left:240px;padding:25px}
.box{background:#fff;padding:25px;border-radius:12px;margin-bottom:20px}
form{display:grid;grid-template-columns:repeat(3,1fr);gap:10px}
select,input,button{padding:11px;border:1px solid #ddd;border-radius:7px}
button{background:#0d3478;color:#fff;border:0}
table{width:100%;border-collapse:collapse}
th,td{padding:11px;border-bottom:1px solid #eee;text-align:left}
.delete{color:red}
.success{background:#dcfce7;color:#166534;padding:10px}
.error{background:#fee2e2;color:#991b1b;padding:10px}
@media(max-width:700px){
.sidebar{position:relative;width:auto;height:auto}
.main{margin-left:0}
form{grid-template-columns:1fr}
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
<a href="timetable.php" class="active">🕒 Timetable</a>
<a href="assignments.php">📘 Assignments</a>
<a href="reports.php">📑 Reports</a>
<a href="../../admin.php">⬅ Back</a>
</div>

<div class="main">

<div class="box">

<h1>Timetable Management</h1>

<?php if($message): ?>
<div class="success"><?=htmlspecialchars($message)?></div>
<?php endif; ?>

<?php if($error): ?>
<div class="error"><?=htmlspecialchars($error)?></div>
<?php endif; ?>

<form method="POST">

<select name="class_id" required>
<option value="">Select Class</option>
<?php foreach($classes as $c): ?>
<option value="<?=$c['class_id']?>">
<?=htmlspecialchars($c['class_name'].' - '.$c['stream_name'])?>
</option>
<?php endforeach; ?>
</select>

<select name="subject_id" required>
<option value="">Select Subject</option>
<?php foreach($subjects as $s): ?>
<option value="<?=$s['subject_id']?>">
<?=htmlspecialchars($s['subject_code'].' - '.$s['subject_name'])?>
</option>
<?php endforeach; ?>
</select>

<select name="teacher_id" required>
<option value="">Select Teacher</option>
<?php foreach($teachers as $t): ?>
<option value="<?=$t['teacher_id']?>">
<?=htmlspecialchars($t['full_name'])?>
</option>
<?php endforeach; ?>
</select>

<select name="day_of_week">
<option>MONDAY</option>
<option>TUESDAY</option>
<option>WEDNESDAY</option>
<option>THURSDAY</option>
<option>FRIDAY</option>
<option>SATURDAY</option>
</select>

<input type="time" name="start_time" required>

<input type="time" name="end_time" required>

<button name="add_timetable">Add Timetable Entry</button>

</form>

</div>

<div class="box">

<h2>School Timetable</h2>

<table>

<tr>
<th>Day</th>
<th>Time</th>
<th>Class</th>
<th>Subject</th>
<th>Teacher</th>
<th>Action</th>
</tr>

<?php foreach($timetable as $tt): ?>

<tr>

<td><?=htmlspecialchars($tt['day_of_week'])?></td>

<td>
<?=htmlspecialchars(substr($tt['start_time'],0,5))?>
-
<?=htmlspecialchars(substr($tt['end_time'],0,5))?>
</td>

<td>
<?=htmlspecialchars($tt['class_name'].' - '.$tt['stream_name'])?>
</td>

<td>
<?=htmlspecialchars($tt['subject_code'].' - '.$tt['subject_name'])?>
</td>

<td><?=htmlspecialchars($tt['teacher_name'])?></td>

<td>
<a
class="delete"
href="?delete=<?=$tt['timetable_id']?>"
onclick="return confirm('Delete this timetable entry?')"
>
Delete
</a>
</td>

</tr>

<?php endforeach; ?>

</table>

</div>

</div>
</body>
</html>