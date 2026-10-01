<?php

require_once __DIR__ . '/_bootstrap.php';

/*
|--------------------------------------------------------------------------
| PAGE SETTINGS
|--------------------------------------------------------------------------
*/

$moduleKey = (string) ($moduleKey ?? 'dashboard');

$pageTitle = ucfirst(
    str_replace('_', ' ', $moduleKey)
);

$error = null;
$uploadedAttachmentPath = null;


/*
|--------------------------------------------------------------------------
| HANDLE POST REQUESTS
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    /*
    |--------------------------------------------------------------------------
    | CHECK CSRF TOKEN
    |--------------------------------------------------------------------------
    */

    if (!studentCsrfValid()) {

        $error = 'Your form expired. Please try again.';

    } elseif ($moduleKey === 'submit_assignment') {

        $uploadedAttachmentPath = null;

        try {

            /*
            |--------------------------------------------------------------------------
            | GET ASSIGNMENT ID
            |--------------------------------------------------------------------------
            */

            $assignmentId = (int) (
                $_POST['assignment_id'] ?? 0
            );


            /*
            |--------------------------------------------------------------------------
            | CHECK IF ASSIGNMENT EXISTS
            |--------------------------------------------------------------------------
            */

            $assignmentCheck = $conn->prepare(
                "
                SELECT
                    assignment_id,
                    due_date
                FROM assignments
                WHERE assignment_id = ?
                    AND class_id = ?
                    AND status = 'PUBLISHED'
                "
            );

            $assignmentCheck->execute([
                $assignmentId,
                $classId
            ]);

            $assignment = $assignmentCheck->fetch();


            /*
            |--------------------------------------------------------------------------
            | MAKE SURE ASSIGNMENT IS AVAILABLE
            |--------------------------------------------------------------------------
            */

            if (!$assignment) {
                throw new RuntimeException(
                    'Assignment is not available to you.'
                );
            }


            /*
            |--------------------------------------------------------------------------
            | GET SUBMISSION DATE
            |--------------------------------------------------------------------------
            */

            $submittedAt = $_POST['submitted_at']
                ?: date('Y-m-d H:i:s');


            /*
            |--------------------------------------------------------------------------
            | DETERMINE SUBMISSION STATUS
            |--------------------------------------------------------------------------
            */

            $status = substr(
                $submittedAt,
                0,
                10
            ) > $assignment['due_date']
                ? 'LATE'
                : 'SUBMITTED';

            $attachmentPath = null;
            if (isset($_FILES['attachment']) && $_FILES['attachment']['error'] !== UPLOAD_ERR_NO_FILE) {
                if ($_FILES['attachment']['error'] !== UPLOAD_ERR_OK) {
                    throw new RuntimeException('The selected file could not be uploaded.');
                }

                if ((int)$_FILES['attachment']['size'] > 20 * 1024 * 1024) {
                    throw new RuntimeException('The attachment must be 20 MB or smaller.');
                }

                $allowedTypes = [
                    'pdf' => ['application/pdf'],
                    'doc' => ['application/msword'],
                    'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document'],
                    'txt' => ['text/plain'],
                    'rtf' => ['application/rtf', 'text/rtf'],
                    'xls' => ['application/vnd.ms-excel'],
                    'xlsx' => ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
                    'ppt' => ['application/vnd.ms-powerpoint'],
                    'pptx' => ['application/vnd.openxmlformats-officedocument.presentationml.presentation'],
                    'jpg' => ['image/jpeg'],
                    'jpeg' => ['image/jpeg'],
                    'png' => ['image/png']
                ];
                $extension = strtolower(pathinfo($_FILES['attachment']['name'], PATHINFO_EXTENSION));
                $mimeType = (new finfo(FILEINFO_MIME_TYPE))->file($_FILES['attachment']['tmp_name']);

                if (!isset($allowedTypes[$extension]) || !in_array($mimeType, $allowedTypes[$extension], true)) {
                    throw new RuntimeException('Unsupported attachment type. Use PDF, Word, Excel, PowerPoint, text, JPG, or PNG.');
                }

                $uploadDirectory = __DIR__ . '/../../uploads/assignments';
                if (!is_dir($uploadDirectory) && !mkdir($uploadDirectory, 0755, true) && !is_dir($uploadDirectory)) {
                    throw new RuntimeException('The attachment folder could not be created.');
                }

                $fileName = 'student_' . $studentId . '_assignment_' . $assignmentId . '_' . bin2hex(random_bytes(8)) . '.' . $extension;
                $destination = $uploadDirectory . DIRECTORY_SEPARATOR . $fileName;
                if (!move_uploaded_file($_FILES['attachment']['tmp_name'], $destination)) {
                    throw new RuntimeException('The attachment could not be saved.');
                }

                $attachmentPath = 'uploads/assignments/' . $fileName;
                $uploadedAttachmentPath = $destination;
            }


            /*
            |--------------------------------------------------------------------------
            | SAVE ASSIGNMENT SUBMISSION
            |--------------------------------------------------------------------------
            */

            $statement = $conn->prepare(
                "
                INSERT INTO assignment_submissions
                (
                    assignment_id,
                    student_id,
                    submission_text,
                    attachment_url,
                    submitted_at,
                    status
                )
                VALUES (?, ?, ?, ?, ?, ?)

                ON DUPLICATE KEY UPDATE
                    submission_text = VALUES(submission_text),
                    attachment_url = COALESCE(VALUES(attachment_url), attachment_url),
                    submitted_at = VALUES(submitted_at),
                    status = VALUES(status)
                "
            );

            $statement->execute([
                $assignmentId,
                $studentId,
                trim($_POST['submission_text'] ?? ''),
                $attachmentPath,
                $submittedAt,
                $status
            ]);


            /*
            |--------------------------------------------------------------------------
            | SUCCESS MESSAGE AND REDIRECT
            |--------------------------------------------------------------------------
            */

            studentFlash('Assignment submitted.');

            studentRedirect('assignments.php');

        } catch (Throwable $exception) {
            if (isset($uploadedAttachmentPath) && $uploadedAttachmentPath && is_file($uploadedAttachmentPath)) {
                unlink($uploadedAttachmentPath);
            }
            $error = $exception->getMessage();
        }
    }
}


/*
|--------------------------------------------------------------------------
| GET FLASH MESSAGE
|--------------------------------------------------------------------------
*/

$flash = studentTakeFlash();


/*
|--------------------------------------------------------------------------
| LOAD PAGE LAYOUT
|--------------------------------------------------------------------------
*/

require __DIR__ . '/_layout.php';

?>


<?php if ($flash): ?>

    <div
        class="panel"
        style="margin-bottom:16px;color:#166534;background:#dcfce7"
    >

        <?= studentEscape($flash['message']) ?>

    </div>

<?php endif; ?>


<?php if ($error): ?>

    <div
        class="panel"
        style="margin-bottom:16px;color:#991b1b;background:#fee2e2"
    >

        <?= studentEscape($error) ?>

    </div>

<?php endif; ?>


<section class="panel">

    <div class="panel-heading">

        <h2>
            <?= studentEscape($pageTitle) ?>
        </h2>

        <a href="index.php">
            Dashboard
        </a>

    </div>


<?php
/*
|--------------------------------------------------------------------------
| ASSIGNMENTS
|--------------------------------------------------------------------------
*/

if (
    $moduleKey === 'assignments'
    || $moduleKey === 'submit_assignment'
):

    $assignmentStatement = $conn->prepare(
        "
        SELECT
            a.assignment_id,
            a.title,
            a.description,
            a.assigned_date,
            a.due_date,
            s.subject_name,
            sub.submission_id,
            sub.submission_text,
            sub.attachment_url,
            sub.status,
            sub.marks,
            sub.teacher_feedback

        FROM assignments a

        INNER JOIN subjects s
            ON s.subject_id = a.subject_id

        LEFT JOIN assignment_submissions sub
            ON sub.assignment_id = a.assignment_id
            AND sub.student_id = ?

        WHERE a.class_id = ?
            AND a.status = 'PUBLISHED'

        ORDER BY a.due_date DESC

        LIMIT 100
        "
    );


    $assignmentStatement->execute([
        $studentId,
        $classId
    ]);


    $assignmentRows = $assignmentStatement->fetchAll();

?>


    <div class="table-wrap">

        <table>

            <tr>
                <th>Assignment</th>
                <th>Subject</th>
                <th>Due</th>
                <th>Status</th>
                <th>Attachment</th>
                <th>Action</th>
            </tr>


            <?php foreach ($assignmentRows as $a): ?>

                <tr>

                    <td>
                        <?= studentEscape($a['title']) ?>

                        <br>

                        <small>
                            <?= studentEscape($a['description']) ?>
                        </small>
                    </td>


                    <td>
                        <?= studentEscape($a['subject_name']) ?>
                    </td>


                    <td>
                        <?= studentEscape($a['due_date']) ?>
                    </td>


                    <td>
                        <?= studentEscape(
                            $a['status'] ?: 'NOT_SUBMITTED'
                        ) ?>
                    </td>

                    <td>
                        <?php if (!empty($a['attachment_url'])): ?>
                            <a href="../../<?= studentEscape($a['attachment_url']) ?>" target="_blank" rel="noopener">Open file</a>
                        <?php else: ?>
                            <span class="empty">None</span>
                        <?php endif; ?>
                    </td>


                    <td>
                        <a
                            href="submit_assignment.php?assignment_id=<?= (int) $a['assignment_id'] ?>"
                        >
                            Submit
                        </a>
                    </td>

                </tr>

            <?php endforeach; ?>

        </table>

    </div>


<?php
/*
|--------------------------------------------------------------------------
| SUBMIT ASSIGNMENT FORM
|--------------------------------------------------------------------------
*/

if ($moduleKey === 'submit_assignment'):

    $selectedId = (int) (
        $_GET['assignment_id'] ?? 0
    );

?>


    <hr>


    <form method="post" enctype="multipart/form-data">

        <input
            type="hidden"
            name="csrf_token"
            value="<?= studentEscape($csrfToken) ?>"
        >


        <select
            name="assignment_id"
            required
        >

            <?php foreach ($assignmentRows as $a): ?>

                <option
                    value="<?= (int) $a['assignment_id'] ?>"
                    <?= $selectedId === (int) $a['assignment_id']
                        ? 'selected'
                        : ''
                    ?>
                >

                    <?= studentEscape(
                        $a['title'] . ' / ' . $a['subject_name']
                    ) ?>

                </option>

            <?php endforeach; ?>

        </select>


        <textarea
            name="submission_text"
            rows="6"
            placeholder="Write your submission"
            required
        ></textarea>


        <input
            type="file"
            name="attachment"
            accept=".pdf,.doc,.docx,.txt,.rtf,.xls,.xlsx,.ppt,.pptx,.jpg,.jpeg,.png"
        >


        <small>Optional attachment. Maximum size: 10 MB.</small>


        <input
            type="datetime-local"
            name="submitted_at"
            value="<?= date('Y-m-d\TH:i') ?>"
        >


        <button>
            Submit assignment
        </button>

    </form>


<?php endif; ?>


<?php
/*
|--------------------------------------------------------------------------
| RESULTS / PERFORMANCE
|--------------------------------------------------------------------------
*/

elseif (
    $moduleKey === 'results'
    || $moduleKey === 'performance'
):

    $resultStatement = $conn->prepare(
        "
        SELECT
            a.assessment_name,
            a.assessment_type,
            a.term,
            a.academic_year,
            a.assessment_date,
            a.max_score,
            s.subject_name,
            am.score,
            am.grade
        FROM assessments a
        INNER JOIN subjects s ON s.subject_id = a.subject_id
        INNER JOIN assessment_marks am
            ON am.assessment_id = a.assessment_id
            AND am.student_id = ?
        WHERE a.class_id = ?
            AND a.status = 'PUBLISHED'
        ORDER BY COALESCE(a.assessment_date, CONCAT(a.academic_year, '-01-01')) DESC,
                 a.assessment_id DESC
        LIMIT 200
        "
    );
    $resultStatement->execute([$studentId, $classId]);
    $resultRows = $resultStatement->fetchAll();

    if ($moduleKey === 'performance'):
        $performanceValues = array_map(
            static fn (array $row): float => (float)$row['score'] / max(1, (float)$row['max_score']) * 100,
            $resultRows
        );
        $performanceAverage = $performanceValues ? array_sum($performanceValues) / count($performanceValues) : 0;
        $performancePoints = [];
        foreach (array_reverse($performanceValues) as $index => $value) {
            $x = count($performanceValues) === 1 ? 50 : 8 + ($index * 84 / (count($performanceValues) - 1));
            $y = 92 - (min(100, max(0, $value)) * 0.8);
            $performancePoints[] = round($x, 1) . ',' . round($y, 1);
        }
?>
    <section class="performance-summary">
        <article><strong><?= count($resultRows) ?></strong><span>Published marks</span></article>
        <article><strong><?= number_format($performanceAverage, 1) ?>%</strong><span>Average score</span></article>
    </section>
    <section class="performance-chart">
        <div class="panel-heading"><h3>Marks trend</h3><span>Percentage by assessment</span></div>
        <?php if ($performancePoints): ?>
            <svg viewBox="0 0 100 100" role="img" aria-label="Student marks trend">
                <line x1="8" y1="12" x2="92" y2="12" stroke="#e5e7eb" />
                <line x1="8" y1="52" x2="92" y2="52" stroke="#e5e7eb" />
                <line x1="8" y1="92" x2="92" y2="92" stroke="#cbd5e1" />
                <polyline points="<?= studentEscape(implode(' ', $performancePoints)) ?>" fill="none" stroke="#0b5cad" stroke-width="2.5" vector-effect="non-scaling-stroke" />
                <?php foreach ($performancePoints as $point): [$pointX, $pointY] = explode(',', $point); ?><circle cx="<?= $pointX ?>" cy="<?= $pointY ?>" r="1.8" fill="#0b5cad" /><?php endforeach; ?>
            </svg>
            <div class="chart-labels"><span>0%</span><span>50%</span><span>100%</span></div>
        <?php else: ?>
            <p class="empty">No published marks are available yet.</p>
        <?php endif; ?>
    </section>
<?php endif; ?>

    <div class="table-wrap">
        <table>
            <tr><th>Assessment</th><th>Subject</th><th>Score</th><th>Grade</th><th>Term</th><th>Year</th></tr>
            <?php foreach ($resultRows as $r): ?><tr>
                <td><?= studentEscape($r['assessment_name']) ?></td>
                <td><?= studentEscape($r['subject_name']) ?></td>
                <td><?= studentEscape($r['score'] . ' / ' . $r['max_score']) ?></td>
                <td><?= studentEscape($r['grade'] ?? '-') ?></td>
                <td><?= studentEscape($r['term']) ?></td>
                <td><?= studentEscape($r['academic_year']) ?></td>
            </tr><?php endforeach; ?>
        </table>
    </div>


<?php
/*
|--------------------------------------------------------------------------
| EXAMS
|--------------------------------------------------------------------------
*/

elseif ($moduleKey === 'exams'):

    $examStatement = $conn->prepare(
        "
        SELECT
            a.assessment_name,
            a.assessment_type,
            a.term,
            a.assessment_date,
            a.max_score,
            am.score,
            am.grade

        FROM assessments a

        LEFT JOIN assessment_marks am
            ON am.assessment_id = a.assessment_id
            AND am.student_id = ?

        WHERE a.class_id = ?
            AND a.status = 'PUBLISHED'

        ORDER BY a.assessment_date DESC

        LIMIT 100
        "
    );


    $examStatement->execute([
        $studentId,
        $classId
    ]);

?>


    <div class="table-wrap">

        <table>

            <tr>
                <th>Assessment</th>
                <th>Type</th>
                <th>Date</th>
                <th>Score</th>
                <th>Grade</th>
            </tr>


            <?php foreach ($examStatement as $r): ?>

                <tr>

                    <td>
                        <?= studentEscape(
                            $r['assessment_name']
                        ) ?>
                    </td>

                    <td>
                        <?= studentEscape(
                            $r['assessment_type']
                        ) ?>
                    </td>

                    <td>
                        <?= studentEscape(
                            $r['assessment_date']
                        ) ?>
                    </td>

                    <td>

                        <?= studentEscape(
                            $r['score'] ?? 'Pending'
                        ) ?>

                        /

                        <?= studentEscape(
                            $r['max_score']
                        ) ?>

                    </td>

                    <td>
                        <?= studentEscape(
                            $r['grade'] ?? '-'
                        ) ?>
                    </td>

                </tr>

            <?php endforeach; ?>

        </table>

    </div>


<?php
/*
|--------------------------------------------------------------------------
| LEARNING RESOURCES
|--------------------------------------------------------------------------
*/

elseif ($moduleKey === 'resources'):

    $resourceStatement = $conn->prepare(
        "
        SELECT
            lr.title,
            lr.description,
            lr.resource_type,
            lr.external_url,
            lr.created_at,
            s.subject_name

        FROM learning_resources lr

        LEFT JOIN subjects s
            ON s.subject_id = lr.subject_id

        WHERE
            (
                lr.class_id = ?
                OR lr.class_id IS NULL
            )

        ORDER BY lr.created_at DESC

        LIMIT 100
        "
    );


    $resourceStatement->execute([
        $classId
    ]);

?>


    <div class="table-wrap">

        <table>

            <tr>
                <th>Title</th>
                <th>Subject</th>
                <th>Type</th>
                <th>Resource</th>
            </tr>


            <?php foreach ($resourceStatement as $r): ?>

                <tr>

                    <td>

                        <?= studentEscape(
                            $r['title']
                        ) ?>

                        <br>

                        <small>
                            <?= studentEscape(
                                $r['description']
                            ) ?>
                        </small>

                    </td>


                    <td>

                        <?= studentEscape(
                            $r['subject_name'] ?? 'All'
                        ) ?>

                    </td>


                    <td>

                        <?= studentEscape(
                            $r['resource_type']
                        ) ?>

                    </td>


                    <td>

                        <?php if ($r['external_url']): ?>

                            <a
                                target="_blank"
                                rel="noopener"
                                href="<?= studentEscape(
                                    $r['external_url']
                                ) ?>"
                            >
                                Open
                            </a>

                        <?php endif; ?>

                    </td>

                </tr>

            <?php endforeach; ?>

        </table>

    </div>


<?php
/*
|--------------------------------------------------------------------------
| ACHIEVEMENTS
|--------------------------------------------------------------------------
*/

elseif ($moduleKey === 'achievements'):

    $achievementStatement = $conn->prepare(
        "
        SELECT
            title,
            description,
            achievement_type,
            achievement_date

        FROM student_achievements

        WHERE student_id = ?

        ORDER BY achievement_date DESC

        LIMIT 100
        "
    );


    $achievementStatement->execute([
        $studentId
    ]);

?>


    <div class="table-wrap">

        <table>

            <tr>
                <th>Achievement</th>
                <th>Type</th>
                <th>Date</th>
                <th>Description</th>
            </tr>


            <?php foreach ($achievementStatement as $a): ?>

                <tr>

                    <td>
                        <?= studentEscape(
                            $a['title']
                        ) ?>
                    </td>

                    <td>
                        <?= studentEscape(
                            $a['achievement_type']
                        ) ?>
                    </td>

                    <td>
                        <?= studentEscape(
                            $a['achievement_date']
                        ) ?>
                    </td>

                    <td>
                        <?= studentEscape(
                            $a['description']
                        ) ?>
                    </td>

                </tr>

            <?php endforeach; ?>

        </table>

    </div>


<?php
/*
|--------------------------------------------------------------------------
| CBC ASSESSMENTS
|--------------------------------------------------------------------------
*/

elseif ($moduleKey === 'cbc'):

    $cbcStatement = $conn->prepare(
        "
        SELECT
            s.subject_name,
            c.competency_name,
            ca.term,
            ca.academic_year,
            ca.performance_level,
            ca.teacher_comment

        FROM cbc_assessments ca

        INNER JOIN subjects s
            ON s.subject_id = ca.subject_id

        INNER JOIN competencies c
            ON c.competency_id = ca.competency_id

        WHERE ca.student_id = ?

        ORDER BY
            ca.academic_year DESC,
            ca.created_at DESC

        LIMIT 100
        "
    );


    $cbcStatement->execute([
        $studentId
    ]);

?>


    <div class="table-wrap">

        <table>

            <tr>
                <th>Subject</th>
                <th>Competency</th>
                <th>Level</th>
                <th>Term</th>
                <th>Comment</th>
            </tr>


            <?php foreach ($cbcStatement as $r): ?>

                <tr>

                    <td>
                        <?= studentEscape(
                            $r['subject_name']
                        ) ?>
                    </td>

                    <td>
                        <?= studentEscape(
                            $r['competency_name']
                        ) ?>
                    </td>

                    <td>
                        <?= studentEscape(
                            $r['performance_level']
                        ) ?>
                    </td>

                    <td>
                        <?= studentEscape(
                            $r['term']
                        ) ?>
                    </td>

                    <td>
                        <?= studentEscape(
                            $r['teacher_comment']
                        ) ?>
                    </td>

                </tr>

            <?php endforeach; ?>

        </table>

    </div>


<?php
/*
|--------------------------------------------------------------------------
| DEFAULT MODULE
|--------------------------------------------------------------------------
*/

else:
?>

    <p class="empty">

        This module is connected to your academic records.
        Use the navigation links to view current information.

    </p>

<?php endif; ?>


</section>


<?php

require __DIR__ . '/_footer.php';

?>