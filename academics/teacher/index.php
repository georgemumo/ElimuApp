<?php
require_once __DIR__ . '/_bootstrap.php';

$classCount = teacherCount($conn, 'SELECT COUNT(DISTINCT class_id) FROM teacher_subject_assignments WHERE teacher_id = ? AND academic_year = ? AND is_active = 1', [$teacherId, $currentYear]);
$subjectCount = teacherCount($conn, 'SELECT COUNT(DISTINCT subject_id) FROM teacher_subject_assignments WHERE teacher_id = ? AND academic_year = ? AND is_active = 1', [$teacherId, $currentYear]);
$studentCount = teacherCount(
    $conn,
    'SELECT COUNT(DISTINCT st.student_id)
     FROM students st
     INNER JOIN teacher_subject_assignments tsa ON tsa.class_id = st.class_id
     WHERE tsa.teacher_id = ? AND tsa.academic_year = ? AND tsa.is_active = 1 AND st.status = \'ACTIVE\'',
    [$teacherId, $currentYear]
);
$pendingCount = teacherCount(
    $conn,
    'SELECT COUNT(*) FROM assessments a
     INNER JOIN teacher_subject_assignments tsa
       ON tsa.class_id = a.class_id AND tsa.subject_id = a.subject_id AND tsa.teacher_id = ?
     WHERE a.status = \'SUBMITTED\' AND a.academic_year = ?',
    [$teacherId, $currentYear]
);

$assignmentStatement = $conn->prepare(
    'SELECT a.assessment_name, a.assessment_type, a.term, a.status,
            c.class_name, c.stream_name, s.subject_name
     FROM assessments a
     INNER JOIN teacher_subject_assignments tsa
       ON tsa.class_id = a.class_id AND tsa.subject_id = a.subject_id AND tsa.teacher_id = ?
     INNER JOIN classes c ON c.class_id = a.class_id
     INNER JOIN subjects s ON s.subject_id = a.subject_id
     WHERE a.academic_year = ?
     ORDER BY a.created_at DESC
     LIMIT 8'
);
$assignmentStatement->execute([$teacherId, $currentYear]);
$assessments = $assignmentStatement->fetchAll();

$pageTitle = 'Teacher Dashboard';
require __DIR__ . '/_layout.php';
?>
<section class="hero"><div><p class="eyebrow">Teacher portal</p><h1><?= teacherEscape($teacher['full_name']) ?></h1><p><?= teacherEscape($teacher['email'] ?: $teacher['phone_number']) ?></p></div><a class="button" href="../../profile.php">Profile</a></section>
<section class="metrics"><article><strong><?= $classCount ?></strong><span>Assigned classes</span></article><article><strong><?= $subjectCount ?></strong><span>Assigned subjects</span></article><article><strong><?= $studentCount ?></strong><span>Students reached</span></article><article><strong><?= $pendingCount ?></strong><span>Assessments awaiting action</span></article></section>
<section class="panel"><div class="panel-heading"><h2>Recent assessments</h2><a href="exams.php">Manage assessments</a></div><?php if (!$assessments): ?><p class="empty">No assessments found for the current academic year.</p><?php else: ?><div class="table-wrap"><table><thead><tr><th>Assessment</th><th>Class</th><th>Subject</th><th>Status</th></tr></thead><tbody><?php foreach ($assessments as $assessment): ?><tr><td><?= teacherEscape($assessment['assessment_name']) ?></td><td><?= teacherEscape($assessment['class_name'] . ' ' . $assessment['stream_name']) ?></td><td><?= teacherEscape($assessment['subject_name']) ?></td><td><?= teacherEscape($assessment['status']) ?></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?></section>
<?php require __DIR__ . '/_footer.php'; ?>