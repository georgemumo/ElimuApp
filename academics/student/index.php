<?php
require_once __DIR__ . '/_bootstrap.php';

$resultCount = studentCount($conn, 'SELECT COUNT(*) FROM exam_results WHERE student_id = ?', [$studentId]);
$pendingAssignments = studentCount(
    $conn,
    'SELECT COUNT(*)
     FROM assignments a
     LEFT JOIN assignment_submissions sub
       ON sub.assignment_id = a.assignment_id AND sub.student_id = ?
    WHERE a.class_id = ? AND (sub.submission_id IS NULL OR sub.status IN (\'NOT_SUBMITTED\', \'LATE\'))',
    [$studentId, $classId]
);
$attendanceCount = studentCount($conn, 'SELECT COUNT(*) FROM attendance WHERE student_id = ?', [$studentId]);
$presentCount = studentCount($conn, "SELECT COUNT(*) FROM attendance WHERE student_id = ? AND status = 'PRESENT'", [$studentId]);
$attendanceRate = $attendanceCount > 0 ? round(($presentCount / $attendanceCount) * 100, 1) : 0;

$latestResults = $conn->prepare(
    'SELECT s.subject_name, er.score, er.grade, er.term, er.academic_year
     FROM exam_results er
     INNER JOIN subjects s ON s.subject_id = er.subject_id
     WHERE er.student_id = ?
     ORDER BY er.academic_year DESC, er.result_id DESC
     LIMIT 6'
);
$latestResults->execute([$studentId]);
$results = $latestResults->fetchAll();

$pageTitle = 'Student Dashboard';
require __DIR__ . '/_layout.php';
?>
<section class="hero">
    <div>
        <p class="eyebrow">Student portal</p>
        <h1><?= studentEscape($student['full_name']) ?></h1>
        <p><?= studentEscape($student['class_name'] . ' ' . $student['stream_name']) ?> · Admission <?= studentEscape($student['adm_number']) ?></p>
    </div><a class="button" href="../../profile.php">Profile</a></section>
<section class="metrics">
    <article><strong><?= $resultCount ?></strong><span>Results recorded</span></article>
    <article><strong><?= $pendingAssignments ?></strong><span>Assignments to review</span></article>
    <article><strong><?= $attendanceRate ?>%</strong><span>Attendance</span></article>
</section>
<section class="panel"><div class="panel-heading"><h2>Latest results</h2><a href="results.php">View all</a></div><?php if (!$results): ?><p class="empty">No results have been published yet.</p><?php else: ?><div class="table-wrap"><table><thead><tr><th>Subject</th><th>Score</th><th>Grade</th><th>Term</th></tr></thead><tbody><?php foreach ($results as $result): ?><tr><td><?= studentEscape($result['subject_name']) ?></td><td><?= studentEscape($result['score']) ?></td><td><?= studentEscape($result['grade'] ?: '-') ?></td><td><?= studentEscape($result['term']) ?></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?></section>
<?php require __DIR__ . '/_footer.php'; ?>