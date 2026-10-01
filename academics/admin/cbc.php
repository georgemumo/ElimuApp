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

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_competency'])) {

    $name = trim($_POST['competency_name'] ?? '');
    $description = trim($_POST['description'] ?? '');

    if ($name === '') {
        $error = "Competency name is required.";
    } else {

        $stmt = $conn->prepare("
            INSERT INTO competencies
            (competency_name,description)
            VALUES (?,?)
        ");

        $stmt->execute([$name,$description ?: null]);

        $message = "Competency added successfully.";
    }
}

if (isset($_GET['delete'])) {

    try {

        $stmt = $conn->prepare("
            DELETE FROM competencies
            WHERE competency_id=?
        ");

        $stmt->execute([(int)$_GET['delete']]);

        $message = "Competency deleted.";

    } catch(PDOException $e) {
        $error = "Competency is currently being used.";
    }
}

$competencies = $conn->query("
    SELECT *
    FROM competencies
    ORDER BY competency_name
")->fetchAll();

$cbcCount = $conn->query("
    SELECT COUNT(*)
    FROM cbc_assessments
")->fetchColumn();
?>
<!DOCTYPE html>
<html>
<head>
<link rel="stylesheet" href="../../assets/css/school.css">
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>CBC Management</title>

<style>
body{margin:0;font-family:Arial;background:#f4f7fb}
.sidebar{position:fixed;width:240px;height:100vh;background:#09265c;color:#fff;padding:20px 15px;overflow-y:auto}
.sidebar a{display:block;color:#fff;text-decoration:none;padding:11px;border-radius:7px;margin:4px 0}
.sidebar a:hover,.active{background:#17458f}
.main{margin-left:240px;padding:25px}
.box{background:#fff;padding:25px;border-radius:12px;margin-bottom:20px}
form{display:grid;gap:10px}
input,textarea,button{padding:11px;border:1px solid #ddd;border-radius:7px}
button{background:#0d3478;color:white;border:0}
table{width:100%;border-collapse:collapse}
th,td{padding:11px;border-bottom:1px solid #eee;text-align:left}
.delete{color:red}
.success{background:#dcfce7;padding:10px;color:#166534}
.error{background:#fee2e2;padding:10px;color:#991b1b}
.stat{font-size:25px;font-weight:bold;color:#0d3478}
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
<a href="cbc.php" class="active">🎯 CBC</a>
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

<h1>CBC Management</h1>

<p>
Total CBC assessments recorded:
<strong class="stat"><?=htmlspecialchars($cbcCount)?></strong>
</p>

</div>

<div class="box">

<h2>Add Competency</h2>

<?php if($message): ?>
<div class="success"><?=htmlspecialchars($message)?></div>
<?php endif; ?>

<?php if($error): ?>
<div class="error"><?=htmlspecialchars($error)?></div>
<?php endif; ?>

<form method="POST">

<input
type="text"
name="competency_name"
placeholder="Competency name"
required
>

<textarea
name="description"
placeholder="Competency description"
></textarea>

<button name="add_competency">
Add Competency
</button>

</form>

</div>

<div class="box">

<h2>CBC Competencies</h2>

<table>

<tr>
<th>Competency</th>
<th>Description</th>
<th>Status</th>
<th>Action</th>
</tr>

<?php foreach($competencies as $c): ?>

<tr>

<td><?=htmlspecialchars($c['competency_name'])?></td>

<td><?=htmlspecialchars($c['description'] ?? '-')?></td>

<td>
<?=((int)$c['is_active']===1)?'Active':'Inactive'?>
</td>

<td>
<a
class="delete"
href="?delete=<?=$c['competency_id']?>"
onclick="return confirm('Delete competency?')"
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