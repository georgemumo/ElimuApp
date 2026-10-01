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

/* Add class */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_class'])) {

    $className = trim($_POST['class_name'] ?? '');
    $streamName = trim($_POST['stream_name'] ?? '');

    if ($className === '' || $streamName === '') {
        $error = "Class name and stream name are required.";
    } else {
        try {
            $stmt = $conn->prepare("
                INSERT INTO classes (class_name, stream_name)
                VALUES (?, ?)
            ");

            $stmt->execute([$className, $streamName]);

            $message = "Class added successfully.";
        } catch (PDOException $e) {
            $error = "Unable to add class. The class may already exist.";
        }
    }
}

/* Delete class */
if (isset($_GET['delete'])) {

    $id = (int)$_GET['delete'];

    try {
        $stmt = $conn->prepare("DELETE FROM classes WHERE class_id = ?");
        $stmt->execute([$id]);

        $message = "Class deleted successfully.";
    } catch (PDOException $e) {
        $error = "This class cannot be deleted because it is being used.";
    }
}

$classes = $conn->query("
    SELECT
        c.class_id,
        c.class_name,
        c.stream_name,
        COUNT(s.student_id) AS student_count
    FROM classes c
    LEFT JOIN students s ON s.class_id = c.class_id
    GROUP BY c.class_id
    ORDER BY c.class_name, c.stream_name
")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<link rel="stylesheet" href="../../assets/css/school.css">
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Manage Classes</title>

<style>
body{margin:0;font-family:Arial;background:#f4f7fb;color:#222}
.sidebar{position:fixed;left:0;top:0;width:240px;height:100vh;background:#09265c;color:white;padding:20px 15px}
.sidebar h2{text-align:center}
.sidebar a{display:block;color:white;text-decoration:none;padding:11px;margin:4px 0;border-radius:7px}
.sidebar a:hover,.sidebar a.active{background:#17458f}
.main{margin-left:240px;padding:25px}
.box{background:white;padding:22px;border-radius:12px;margin-bottom:20px}
form{display:grid;grid-template-columns:1fr 1fr auto;gap:10px}
input,button{padding:11px;border:1px solid #ddd;border-radius:7px}
button{background:#0d3478;color:white;border:0;cursor:pointer}
table{width:100%;border-collapse:collapse}
th,td{padding:12px;border-bottom:1px solid #eee;text-align:left}
.delete{color:#dc2626;text-decoration:none}
.success{background:#dcfce7;color:#166534;padding:12px;border-radius:7px;margin-bottom:15px}
.error{background:#fee2e2;color:#991b1b;padding:12px;border-radius:7px;margin-bottom:15px}
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
<a href="classes.php" class="active">🏫 Classes</a>
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
<a href="reports.php">📑 Reports</a>
<a href="../../admin.php">⬅ Back</a>
</div>

<div class="main">

<div class="box">
<h1>Manage Classes</h1>

<?php if ($message): ?>
<div class="success"><?= htmlspecialchars($message) ?></div>
<?php endif; ?>

<?php if ($error): ?>
<div class="error"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<form method="POST">
<input type="text" name="class_name" placeholder="Class e.g. Grade 6" required>
<input type="text" name="stream_name" placeholder="Stream e.g. East" required>
<button type="submit" name="add_class">Add Class</button>
</form>
</div>

<div class="box">

<h2>Existing Classes</h2>

<table>
<thead>
<tr>
<th>Class</th>
<th>Stream</th>
<th>Students</th>
<th>Action</th>
</tr>
</thead>

<tbody>

<?php foreach ($classes as $class): ?>

<tr>
<td><?= htmlspecialchars($class['class_name']) ?></td>
<td><?= htmlspecialchars($class['stream_name']) ?></td>
<td><?= (int)$class['student_count'] ?></td>
<td>
<a
class="delete"
href="?delete=<?= (int)$class['class_id'] ?>"
onclick="return confirm('Delete this class?')"
>
Delete
</a>
</td>
</tr>

<?php endforeach; ?>

<?php if (!$classes): ?>
<tr>
<td colspan="4">No classes found.</td>
</tr>
<?php endif; ?>

</tbody>
</table>

</div>

</div>

</body>
</html>