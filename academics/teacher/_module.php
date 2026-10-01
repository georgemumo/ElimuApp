<?php
require_once __DIR__ . '/_bootstrap.php';
$pageTitle = $pageTitle ?? ucfirst(str_replace('_', ' ', $moduleKey ?? 'Teacher module'));

$moduleKey = (string)($moduleKey ?? 'dashboard');
$pageTitle = ucfirst(str_replace('_', ' ', $moduleKey));

$assignmentRows = $conn->prepare(
	'SELECT tsa.assignment_id, tsa.class_id, tsa.subject_id, c.class_name, c.stream_name, s.subject_name
	 FROM teacher_subject_assignments tsa
	 INNER JOIN classes c ON c.class_id = tsa.class_id
	 INNER JOIN subjects s ON s.subject_id = tsa.subject_id
	 WHERE tsa.teacher_id = ? AND tsa.academic_year = ? AND tsa.is_active = 1
	 ORDER BY c.class_name, s.subject_name'
);
$assignmentRows->execute([$teacherId, $currentYear]);
$assignments = $assignmentRows->fetchAll();
$assignmentMap = [];
foreach ($assignments as $row) {
	$assignmentMap[(int)$row['class_id'] . ':' . (int)$row['subject_id']] = true;
}

$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	if (!teacherCsrfValid()) {
		$error = 'Your form expired. Please try again.';
	} else {
		try {
			if ($moduleKey === 'assignments' && ($_POST['action'] ?? '') === 'create') {
				$teacherAssignmentId = (int)($_POST['teacher_assignment_id'] ?? 0);
				$selectedAssignment = array_values(array_filter($assignments, static fn (array $row): bool => (int)$row['assignment_id'] === $teacherAssignmentId))[0] ?? null;
				if (!$selectedAssignment) throw new RuntimeException('You are not assigned to that class and subject.');
				$classId = (int)$selectedAssignment['class_id'];
				$subjectId = (int)$selectedAssignment['subject_id'];
				$statement = $conn->prepare('INSERT INTO assignments (teacher_id,class_id,subject_id,title,description,max_marks,assigned_date,due_date,status) VALUES (?,?,?,?,?,?,?,?,?)');
				$statement->execute([$teacherId, $classId, $subjectId, trim($_POST['title'] ?? ''), trim($_POST['description'] ?? ''), (float)($_POST['max_marks'] ?? 100), $_POST['assigned_date'] ?? date('Y-m-d'), $_POST['due_date'] ?? date('Y-m-d'), $_POST['status'] === 'DRAFT' ? 'DRAFT' : 'PUBLISHED']);
				teacherFlash('Assignment created.'); teacherRedirect('assignments.php');
			}
			if ($moduleKey === 'assignments' && ($_POST['action'] ?? '') === 'delete') {
				$statement = $conn->prepare('DELETE FROM assignments WHERE assignment_id = ? AND teacher_id = ?');
				$statement->execute([(int)$_POST['assignment_id'], $teacherId]);
				teacherFlash('Assignment deleted.'); teacherRedirect('assignments.php');
			}
			if ($moduleKey === 'enter_marks' && ($_POST['action'] ?? '') === 'save') {
				$assessmentId = (int)$_POST['assessment_id'];
				$check = $conn->prepare('SELECT a.class_id FROM assessments a INNER JOIN teacher_subject_assignments tsa ON tsa.class_id=a.class_id AND tsa.subject_id=a.subject_id WHERE a.assessment_id=? AND tsa.teacher_id=? AND tsa.academic_year=?');
				$check->execute([$assessmentId, $teacherId, $currentYear]);
				if (!$check->fetch()) throw new RuntimeException('Assessment is not assigned to you.');
				$studentIds = $_POST['student_id'] ?? [];
				$scores = $_POST['score'] ?? [];
				$grades = $_POST['grade'] ?? [];
				$remarks = $_POST['remarks'] ?? [];
				$statement = $conn->prepare('INSERT INTO assessment_marks (assessment_id,student_id,score,grade,remarks,entered_by) VALUES (?,?,?,?,?,?) ON DUPLICATE KEY UPDATE score=VALUES(score),grade=VALUES(grade),remarks=VALUES(remarks),entered_by=VALUES(entered_by)');
				foreach ($studentIds as $index => $studentId) if ($scores[$index] !== '') $statement->execute([$assessmentId, (int)$studentId, (float)$scores[$index], trim($grades[$index] ?? ''), trim($remarks[$index] ?? ''), (int)$_SESSION['user_id']]);
				$conn->prepare("UPDATE assessments SET status='SUBMITTED' WHERE assessment_id=? AND status IN ('DRAFT','OPEN','SUBMITTED')")->execute([$assessmentId]);
				teacherFlash('Marks saved.'); teacherRedirect('enter_marks.php?assessment_id=' . $assessmentId);
			}
			if ($moduleKey === 'resources' && ($_POST['action'] ?? '') === 'create') {
				$classId = (int)($_POST['class_id'] ?? 0); $subjectId = (int)($_POST['subject_id'] ?? 0);
				if ($classId && $subjectId && !isset($assignmentMap[$classId . ':' . $subjectId])) throw new RuntimeException('You are not assigned to that class and subject.');
				$statement = $conn->prepare('INSERT INTO learning_resources (teacher_id,class_id,subject_id,title,description,resource_type,external_url) VALUES (?,?,?,?,?,?,?)');
				$statement->execute([$teacherId, $classId ?: null, $subjectId ?: null, trim($_POST['title'] ?? ''), trim($_POST['description'] ?? ''), $_POST['resource_type'] ?? 'NOTE', trim($_POST['external_url'] ?? '') ?: null]);
				teacherFlash('Resource created.'); teacherRedirect('resources.php');
			}
			if ($moduleKey === 'resources' && ($_POST['action'] ?? '') === 'delete') {
				$statement = $conn->prepare('DELETE FROM learning_resources WHERE resource_id=? AND teacher_id=?'); $statement->execute([(int)$_POST['resource_id'], $teacherId]); teacherFlash('Resource deleted.'); teacherRedirect('resources.php');
			}
			if ($moduleKey === 'exams' && ($_POST['action'] ?? '') === 'create') {
				$teacherAssignmentId = (int)($_POST['teacher_assignment_id'] ?? 0);
				$selectedAssignment = array_values(array_filter($assignments, static fn (array $row): bool => (int)$row['assignment_id'] === $teacherAssignmentId))[0] ?? null;
				if (!$selectedAssignment) throw new RuntimeException('You are not assigned to that class and subject.');
				$statement = $conn->prepare('INSERT INTO assessments (assessment_name,assessment_type,term,academic_year,class_id,subject_id,max_score,assessment_date,status,created_by) VALUES (?,?,?,?,?,?,?,?,?,?)');
				$statement->execute([trim($_POST['assessment_name'] ?? ''), $_POST['assessment_type'] ?? 'CAT', $_POST['term'] ?? 'TERM_1', $currentYear, (int)$selectedAssignment['class_id'], (int)$selectedAssignment['subject_id'], (float)($_POST['max_score'] ?? 100), $_POST['assessment_date'] ?: null, 'DRAFT', (int)$_SESSION['user_id']]);
				teacherFlash('Assessment created.'); teacherRedirect('exams.php');
			}
			if ($moduleKey === 'attendance' && ($_POST['action'] ?? '') === 'save') {
				$classId = (int)($_POST['class_id'] ?? 0); $date = $_POST['attendance_date'] ?? date('Y-m-d');
				$validClass = $conn->prepare('SELECT 1 FROM teacher_subject_assignments WHERE teacher_id=? AND class_id=? AND academic_year=? AND is_active=1 LIMIT 1'); $validClass->execute([$teacherId,$classId,$currentYear]);
				if (!$validClass->fetch()) throw new RuntimeException('You are not assigned to this class.');
				$existing = $conn->prepare('SELECT attendance_id FROM attendance WHERE student_id=? AND class_id=? AND date=? LIMIT 1');
				$update = $conn->prepare('UPDATE attendance SET status=? WHERE attendance_id=?');
				$insert = $conn->prepare('INSERT INTO attendance (student_id,class_id,date,status) VALUES (?,?,?,?)');
				foreach (($_POST['student_id'] ?? []) as $index => $studentIdValue) {
					$existing->execute([(int)$studentIdValue,$classId,$date]); $attendanceId=$existing->fetchColumn(); $status=$_POST['status'][$index] ?? 'PRESENT';
					if ($attendanceId) $update->execute([$status,$attendanceId]); else $insert->execute([(int)$studentIdValue,$classId,$date,$status]);
				}
				teacherFlash('Attendance saved.'); teacherRedirect('attendance.php?class_id=' . $classId . '&date=' . urlencode($date));
			}
			if ($moduleKey === 'cbc' && ($_POST['action'] ?? '') === 'save') {
				$studentIdValue=(int)($_POST['student_id']??0); $subjectId=(int)($_POST['subject_id']??0); $competencyId=(int)($_POST['competency_id']??0);
				$valid = $conn->prepare('SELECT 1 FROM students st INNER JOIN teacher_subject_assignments tsa ON tsa.class_id=st.class_id AND tsa.subject_id=? WHERE st.student_id=? AND tsa.teacher_id=? AND tsa.academic_year=? LIMIT 1'); $valid->execute([$subjectId,$studentIdValue,$teacherId,$currentYear]);
				if (!$valid->fetch()) throw new RuntimeException('Student or subject is not assigned to you.');
				$statement=$conn->prepare('INSERT INTO cbc_assessments (student_id,subject_id,competency_id,term,academic_year,performance_level,teacher_comment,teacher_id) VALUES (?,?,?,?,?,?,?,?)');
				$statement->execute([$studentIdValue,$subjectId,$competencyId,$_POST['term']??'TERM_1',$currentYear,$_POST['performance_level']??'AE',trim($_POST['teacher_comment']??''),$teacherId]); teacherFlash('CBC assessment saved.'); teacherRedirect('cbc.php');
			}
		} catch (Throwable $exception) { $error = $exception->getMessage(); }
	}
}

$flash = teacherTakeFlash();
$teacherLinks = ['my_classes.php'=>'My Classes','my_subjects.php'=>'My Subjects','assignments.php'=>'Assignments','exams.php'=>'Assessments','enter_marks.php'=>'Enter Marks','manage_marks.php'=>'Manage Marks','attendance.php'=>'Attendance','timetable.php'=>'Timetable','resources.php'=>'Resources','reports.php'=>'Reports','at_risk.php'=>'At-risk Students','cbc.php'=>'CBC'];
require __DIR__ . '/_layout.php';
?>
<?php if ($flash): ?><div class="panel" style="margin-bottom:16px;color:#166534;background:#dcfce7"><?= teacherEscape($flash['message']) ?></div><?php endif; ?>
<?php if ($error): ?><div class="panel" style="margin-bottom:16px;color:#991b1b;background:#fee2e2"><?= teacherEscape($error) ?></div><?php endif; ?>
<section class="panel"><div class="panel-heading"><h2><?= teacherEscape($pageTitle) ?></h2><a href="index.php">Dashboard</a></div>
<?php if ($moduleKey === 'assignments'): ?>
<form method="post"><input type="hidden" name="csrf_token" value="<?= teacherEscape($csrfToken) ?>"><input type="hidden" name="action" value="create"><div class="metrics"><article><label>Class and subject<select name="teacher_assignment_id" required><?php foreach($assignments as $a): ?><option value="<?= (int)$a['assignment_id'] ?>"><?= teacherEscape($a['class_name'].' '.$a['stream_name'].' / '.$a['subject_name']) ?></option><?php endforeach; ?></select></label></article><article><label>Title<input name="title" required></label></article><article><label>Due date<input type="date" name="due_date" required></label></article></div><textarea name="description" placeholder="Instructions" rows="3"></textarea><button type="submit">Create assignment</button></form>
<?php elseif ($moduleKey === 'enter_marks'): $assessmentStatement=$conn->prepare('SELECT DISTINCT a.assessment_id,a.assessment_name,a.class_id,a.subject_id,c.class_name,c.stream_name,s.subject_name FROM assessments a INNER JOIN teacher_subject_assignments tsa ON tsa.class_id=a.class_id AND tsa.subject_id=a.subject_id INNER JOIN classes c ON c.class_id=a.class_id INNER JOIN subjects s ON s.subject_id=a.subject_id WHERE tsa.teacher_id=? AND a.academic_year=? ORDER BY a.assessment_id DESC'); $assessmentStatement->execute([$teacherId,$currentYear]); $assessmentRows=$assessmentStatement->fetchAll(); $assessmentId=(int)($_GET['assessment_id']??0); ?>
<form method="get"><select name="assessment_id" required><option value="">Select assessment</option><?php foreach($assessmentRows as $a): ?><option value="<?= (int)$a['assessment_id'] ?>" <?= $assessmentId===(int)$a['assessment_id']?'selected':'' ?>><?= teacherEscape($a['assessment_name'].' / '.$a['class_name'].' '.$a['stream_name'].' / '.$a['subject_name']) ?></option><?php endforeach; ?></select><button>Load students</button></form>
<?php if ($assessmentId): $studentStatement=$conn->prepare('SELECT st.student_id,st.adm_number,st.full_name,am.score,am.grade,am.remarks FROM assessments a INNER JOIN students st ON st.class_id=a.class_id AND st.status=\'ACTIVE\' LEFT JOIN assessment_marks am ON am.assessment_id=a.assessment_id AND am.student_id=st.student_id WHERE a.assessment_id=? ORDER BY st.full_name'); $studentStatement->execute([$assessmentId]); $markRows=$studentStatement->fetchAll(); ?><form method="post"><input type="hidden" name="csrf_token" value="<?= teacherEscape($csrfToken) ?>"><input type="hidden" name="action" value="save"><input type="hidden" name="assessment_id" value="<?= $assessmentId ?>"><div class="table-wrap"><table><tr><th>Student</th><th>Score</th><th>Grade</th><th>Remarks</th></tr><?php foreach($markRows as $i=>$m): ?><tr><td><?= teacherEscape($m['adm_number'].' '.$m['full_name']) ?><input type="hidden" name="student_id[]" value="<?= (int)$m['student_id'] ?>"></td><td><input name="score[]" value="<?= teacherEscape($m['score']) ?>" type="number" min="0" step="0.01"></td><td><input name="grade[]" value="<?= teacherEscape($m['grade']) ?>"></td><td><input name="remarks[]" value="<?= teacherEscape($m['remarks']) ?>"></td></tr><?php endforeach; ?></table></div><button>Save marks</button></form><?php endif; ?>
<?php elseif ($moduleKey === 'resources'): ?><form method="post"><input type="hidden" name="csrf_token" value="<?= teacherEscape($csrfToken) ?>"><input type="hidden" name="action" value="create"><input name="title" placeholder="Resource title" required><select name="resource_type"><option>NOTE</option><option>PDF</option><option>WORKSHEET</option><option>LINK</option><option>VIDEO</option></select><select name="class_id"><option value="0">All classes</option><?php foreach($assignments as $a): ?><option value="<?= (int)$a['class_id'] ?>"><?= teacherEscape($a['class_name'].' '.$a['stream_name']) ?></option><?php endforeach; ?></select><input name="external_url" placeholder="Resource URL"><textarea name="description" placeholder="Description"></textarea><button>Create resource</button></form><hr><div class="table-wrap"><table><tr><th>Title</th><th>Type</th><th>Created</th><th></th></tr><?php $resourceStatement=$conn->prepare('SELECT resource_id,title,resource_type,created_at FROM learning_resources WHERE teacher_id=? ORDER BY created_at DESC LIMIT 100');$resourceStatement->execute([$teacherId]);foreach($resourceStatement as $r): ?><tr><td><?= teacherEscape($r['title']) ?></td><td><?= teacherEscape($r['resource_type']) ?></td><td><?= teacherEscape($r['created_at']) ?></td><td><form method="post"><input type="hidden" name="csrf_token" value="<?= teacherEscape($csrfToken) ?>"><input type="hidden" name="action" value="delete"><input type="hidden" name="resource_id" value="<?= (int)$r['resource_id'] ?>"><button>Delete</button></form></td></tr><?php endforeach; ?></table></div>
<?php elseif ($moduleKey === 'exams'): ?><form method="post"><input type="hidden" name="csrf_token" value="<?= teacherEscape($csrfToken) ?>"><input type="hidden" name="action" value="create"><select name="teacher_assignment_id" required><?php foreach($assignments as $a): ?><option value="<?= (int)$a['assignment_id'] ?>"><?= teacherEscape($a['class_name'].' '.$a['stream_name'].' / '.$a['subject_name']) ?></option><?php endforeach; ?></select><input name="assessment_name" placeholder="Assessment name" required><select name="assessment_type"><option>CAT</option><option>MIDTERM</option><option>END_TERM</option><option>MOCK</option><option>PROJECT</option></select><select name="term"><option>TERM_1</option><option>TERM_2</option><option>TERM_3</option></select><input type="number" name="max_score" value="100" min="1" step="0.01"><input type="date" name="assessment_date"><button>Create assessment</button></form>
<?php elseif ($moduleKey === 'attendance'): $selectedClass=(int)($_GET['class_id']??($assignments[0]['class_id']??0));$attendanceDate=$_GET['date']??date('Y-m-d');$studentStatement=$conn->prepare('SELECT student_id,adm_number,full_name FROM students WHERE class_id=? AND status=\'ACTIVE\' ORDER BY full_name LIMIT 500');$studentStatement->execute([$selectedClass]);$attendanceStudents=$studentStatement->fetchAll(); ?><form method="get"><select name="class_id"><?php foreach($assignments as $a): ?><option value="<?= (int)$a['class_id'] ?>" <?= $selectedClass===(int)$a['class_id']?'selected':'' ?>><?= teacherEscape($a['class_name'].' '.$a['stream_name']) ?></option><?php endforeach; ?></select><input type="date" name="date" value="<?= teacherEscape($attendanceDate) ?>"><button>Load class</button></form><form method="post"><input type="hidden" name="csrf_token" value="<?= teacherEscape($csrfToken) ?>"><input type="hidden" name="action" value="save"><input type="hidden" name="class_id" value="<?= $selectedClass ?>"><input type="hidden" name="attendance_date" value="<?= teacherEscape($attendanceDate) ?>"><div class="table-wrap"><table><tr><th>Student</th><th>Status</th></tr><?php foreach($attendanceStudents as $studentRow): ?><tr><td><?= teacherEscape($studentRow['adm_number'].' '.$studentRow['full_name']) ?><input type="hidden" name="student_id[]" value="<?= (int)$studentRow['student_id'] ?>"></td><td><select name="status[]"><option>PRESENT</option><option>ABSENT</option><option>LATE</option><option>EXCUSED</option></select></td></tr><?php endforeach; ?></table></div><button>Save attendance</button></form>
<?php elseif ($moduleKey === 'cbc'): $competencies=$conn->query('SELECT competency_id,competency_name FROM competencies WHERE is_active=1 ORDER BY competency_name')->fetchAll();$cbcStudents=$conn->prepare('SELECT DISTINCT st.student_id,st.adm_number,st.full_name FROM students st INNER JOIN teacher_subject_assignments tsa ON tsa.class_id=st.class_id WHERE tsa.teacher_id=? AND tsa.academic_year=? AND st.status=\'ACTIVE\' ORDER BY st.full_name LIMIT 1000');$cbcStudents->execute([$teacherId,$currentYear]); ?><form method="post"><input type="hidden" name="csrf_token" value="<?= teacherEscape($csrfToken) ?>"><input type="hidden" name="action" value="save"><select name="student_id" required><?php foreach($cbcStudents as $s): ?><option value="<?= (int)$s['student_id'] ?>"><?= teacherEscape($s['adm_number'].' '.$s['full_name']) ?></option><?php endforeach; ?></select><select name="subject_id" required><?php foreach($assignments as $a): ?><option value="<?= (int)$a['subject_id'] ?>"><?= teacherEscape($a['subject_name']) ?></option><?php endforeach; ?></select><select name="competency_id" required><?php foreach($competencies as $c): ?><option value="<?= (int)$c['competency_id'] ?>"><?= teacherEscape($c['competency_name']) ?></option><?php endforeach; ?></select><select name="term"><option>TERM_1</option><option>TERM_2</option><option>TERM_3</option></select><select name="performance_level"><option>EE</option><option>ME</option><option>AE</option><option>BE</option></select><textarea name="teacher_comment" placeholder="Comment"></textarea><button>Save CBC assessment</button></form>
<?php elseif ($moduleKey === 'my_classes' || $moduleKey === 'my_subjects'): ?><div class="table-wrap"><table><tr><th>Class</th><th>Subject</th><th>Academic year</th></tr><?php foreach($assignments as $a): ?><tr><td><?= teacherEscape($a['class_name'].' '.$a['stream_name']) ?></td><td><?= teacherEscape($a['subject_name']) ?></td><td><?= $currentYear ?></td></tr><?php endforeach; ?></table></div>
<?php elseif ($moduleKey === 'reports' || $moduleKey === 'class_performance' || $moduleKey === 'at_risk'): ?><div class="table-wrap"><table><tr><th>Class</th><th>Subject</th><th>Students</th><th>Average score</th></tr><?php foreach($assignments as $a): $summary=$conn->prepare('SELECT COUNT(DISTINCT st.student_id) students,COALESCE(AVG(am.score),0) average_score FROM students st LEFT JOIN assessment_marks am ON am.student_id=st.student_id INNER JOIN assessments ax ON ax.assessment_id=am.assessment_id AND ax.class_id=st.class_id AND ax.subject_id=? WHERE st.class_id=? AND st.status=\'ACTIVE\'');$summary->execute([(int)$a['subject_id'],(int)$a['class_id']]);$s=$summary->fetch(); ?><tr><td><?= teacherEscape($a['class_name'].' '.$a['stream_name']) ?></td><td><?= teacherEscape($a['subject_name']) ?></td><td><?= (int)($s['students']??0) ?></td><td><?= number_format((float)($s['average_score']??0),2) ?></td></tr><?php endforeach; ?></table></div>
<?php else: ?><p class="empty">Use the links above to manage your assigned classes, assessments, marks, attendance, resources, CBC records, and reports.</p><?php endif; ?></section>
<?php require __DIR__ . '/_footer.php'; ?>
<?php require __DIR__ . '/_footer.php'; ?>