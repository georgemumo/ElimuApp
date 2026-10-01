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

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_subject'])) {

    $code = strtoupper(trim($_POST['subject_code'] ?? ''));
    $name = trim($_POST['subject_name'] ?? '');

    if ($code === '' || $name === '') {
        $error = "Subject code and subject name are required.";
    } else {
        try {
            $stmt = $conn->prepare("
                INSERT INTO subjects (subject_code, subject_name)
                VALUES (?, ?)
            ");

            $stmt->execute([$code, $name]);

            $message = "Subject added successfully.";
        } catch (PDOException $e) {
            $error = "Subject code already exists.";
        }
    }
}

if (isset($_GET['delete'])) {

    try {
        $stmt = $conn->prepare("DELETE FROM subjects WHERE subject_id = ?");
        $stmt->execute([(int)$_GET['delete']]);

        $message = "Subject deleted successfully.";
    } catch (PDOException $e) {
        $error = "This subject cannot be deleted because it is being used.";
    }
}

$subjects = $conn->query("
    SELECT subject_id, subject_code, subject_name
    FROM subjects
    ORDER BY subject_name
")->fetchAll();
?>
<!DOCTYPE html>
<html>
<head>
<link rel="stylesheet" href="../../assets/css/school.css">
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Subjects</title>

<style>
body{margin:0;font-family:Arial;background:#f4f7fb}
.sidebar{position:fixed;width:240px;height:100vh;background:#09265c;color:#fff;padding:20px 15px}
.sidebar a{display:block;color:white;text-decoration:none;padding:11px;border-radius:7px;margin:4px 0}
.sidebar a:hover,.active{background:#17458f}
.main{margin-left:240px;padding:25px}
.box{background:white;padding:25px;border-radius:12px;margin-bottom:20px}
form{display:flex;gap:10px}
input,button{padding:11px;border-radius:7px;border:1px solid #ddd}
button{background:#0d3478;color:white;border:0}
table{width:100%;border-collapse:collapse}
th,td{padding:12px;border-bottom:1px solid #eee;text-align:left}
.delete{color:red}
.success{background:#dcfce7;padding:10px;color:#166534}
.error{background:#fee2e2;padding:10px;color:#991b1b}
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
<a class="active" href="subjects.php">📚 Subjects</a>
<a href="teacher_assignments.php">👨‍🏫 Teacher Assignments</a>
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

<h1>Manage Subjects</h1>

<?php if($message): ?>
<div class="success"><?=htmlspecialchars($message)?></div>
<?php endif; ?>

<?php if($error): ?>
<div class="error"><?=htmlspecialchars($error)?></div>
<?php endif; ?>

<form method="POST">
<input name="subject_code" placeholder="Subject Code" required>
<input name="subject_name" placeholder="Subject Name" required>
<button name="add_subject">Add Subject</button>
</form>

</div>

<div class="box">

<h2>Subjects</h2>

<table>
<tr>
<th>Code</th>
<th>Subject</th>
<th>Action</th>
</tr>

<?php foreach($subjects as $subject): ?>

<tr>
<td><?=htmlspecialchars($subject['subject_code'])?></td>
<td><?=htmlspecialchars($subject['subject_name'])?></td>
<td>
<a class="delete"
href="?delete=<?=$subject['subject_id']?>"
onclick="return confirm('Delete subject?')">
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