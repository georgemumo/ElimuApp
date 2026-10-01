<?php
session_start();

require_once "../../db.php";

/*
|--------------------------------------------------------------------------
| PARENT ACCESS CHECK
|--------------------------------------------------------------------------
*/
if (
    !isset($_SESSION['logged_in']) ||
    $_SESSION['logged_in'] !== true
) {
    header("Location: ../../login.php");
    exit;
}

if (
    !isset($_SESSION['role']) ||
    strtoupper(trim((string) $_SESSION['role'])) !== 'PARENT'
) {
    header("Location: ../../login.php");
    exit;
}

if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
    header("Location: ../../login.php");
    exit;
}

$user_id = (int) $_SESSION['user_id'];

/*
|--------------------------------------------------------------------------
| FIND THE LOGGED-IN PARENT
|--------------------------------------------------------------------------
*/
$parentStmt = $conn->prepare("
    SELECT
        p.parent_id,
        p.full_name,
        p.phone,
        p.user_id
    FROM parents p
    WHERE p.user_id = ?
    LIMIT 1
");

$parentStmt->execute([$user_id]);
$parent = $parentStmt->fetch();

if (!$parent) {
    die("
        <div style='
            font-family: Arial, sans-serif;
            max-width: 700px;
            margin: 60px auto;
            padding: 25px;
            border-radius: 10px;
            background: #fff3cd;
            color: #856404;
            border: 1px solid #ffeeba;
        '>
            <h2>Parent Account Not Linked</h2>
            <p>
                Your login account is not linked to a parent record.
                Please contact the school administrator.
            </p>
        </div>
    ");
}

$parent_id = (int) $parent['parent_id'];

/*
|--------------------------------------------------------------------------
| GET CHILDREN LINKED TO THIS PARENT
|--------------------------------------------------------------------------
*/
$childrenStmt = $conn->prepare("
    SELECT
        s.student_id,
        s.adm_number,
        s.full_name,
        s.gender,
        s.class_id,
        s.status,
        c.class_name,
        c.stream_name
    FROM parent_student_map psm
    INNER JOIN students s
        ON s.student_id = psm.student_id
    INNER JOIN classes c
        ON c.class_id = s.class_id
    WHERE psm.parent_id = ?
    ORDER BY s.full_name ASC
");

$childrenStmt->execute([$parent_id]);
$children = $childrenStmt->fetchAll();

/*
|--------------------------------------------------------------------------
| SELECT CHILD
|--------------------------------------------------------------------------
| If ?student_id= is supplied, use it only if that child actually
| belongs to the logged-in parent.
|--------------------------------------------------------------------------
*/
$selectedStudentId = isset($_GET['student_id'])
    ? (int) $_GET['student_id']
    : 0;

$selectedChild = null;

/*
|--------------------------------------------------------------------------
| If no child was selected, automatically select the first child
|--------------------------------------------------------------------------
*/
if ($selectedStudentId === 0 && !empty($children)) {
    $selectedStudentId = (int) $children[0]['student_id'];
}

/*
|--------------------------------------------------------------------------
| Find the selected child from the parent's children
|--------------------------------------------------------------------------
*/
foreach ($children as $child) {
    if ((int) $child['student_id'] === $selectedStudentId) {
        $selectedChild = $child;
        break;
    }
}

/*
|--------------------------------------------------------------------------
| TIMETABLE DATA
|--------------------------------------------------------------------------
*/
$timetable = [];

if ($selectedChild) {

    $class_id = (int) $selectedChild['class_id'];

    /*
    |--------------------------------------------------------------------------
    | GET TIMETABLE USING THE CHILD'S ACTUAL CLASS_ID
    |--------------------------------------------------------------------------
    | This is the important relationship:
    |
    | Parent
    |   ↓
    | parent_student_map
    |   ↓
    | Student
    |   ↓
    | class_id
    |   ↓
    | timetables.class_id
    |   ↓
    | Subject + Teacher
    |--------------------------------------------------------------------------
    */
    $timetableStmt = $conn->prepare("
        SELECT
            t.timetable_id,
            t.class_id,
            t.subject_id,
            t.teacher_id,
            t.day_of_week,
            t.start_time,
            t.end_time,

            c.class_name,
            c.stream_name,

            sub.subject_code,
            sub.subject_name,

            te.full_name AS teacher_name

        FROM timetables t

        INNER JOIN classes c
            ON c.class_id = t.class_id

        INNER JOIN subjects sub
            ON sub.subject_id = t.subject_id

        INNER JOIN teachers te
            ON te.teacher_id = t.teacher_id

        WHERE t.class_id = ?

        ORDER BY
            FIELD(
                t.day_of_week,
                'MONDAY',
                'TUESDAY',
                'WEDNESDAY',
                'THURSDAY',
                'FRIDAY',
                'SATURDAY'
            ),
            t.start_time ASC
    ");

    $timetableStmt->execute([$class_id]);
    $timetable = $timetableStmt->fetchAll();
}

/*
|--------------------------------------------------------------------------
| GROUP TIMETABLE BY DAY
|--------------------------------------------------------------------------
*/
$days = [
    'MONDAY',
    'TUESDAY',
    'WEDNESDAY',
    'THURSDAY',
    'FRIDAY',
    'SATURDAY'
];

$groupedTimetable = [];

foreach ($days as $day) {
    $groupedTimetable[$day] = [];
}

foreach ($timetable as $row) {
    $day = strtoupper($row['day_of_week']);

    if (isset($groupedTimetable[$day])) {
        $groupedTimetable[$day][] = $row;
    }
}

/*
|--------------------------------------------------------------------------
| FORMAT TIME
|--------------------------------------------------------------------------
*/
function formatTime($time)
{
    if (empty($time)) {
        return '';
    }

    return date("g:i A", strtotime($time));
}

/*
|--------------------------------------------------------------------------
| ESCAPE OUTPUT
|--------------------------------------------------------------------------
*/
function e($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Child Timetable - Parent Portal</title>
    <link rel="stylesheet" href="../../assets/css/school.css">

    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: Arial, Helvetica, sans-serif;
            background: #f5f7fb;
            color: #1f2937;
        }

        .container {
            width: 95%;
            max-width: 1400px;
            margin: 30px auto;
        }

        .page-header {
            background: #ffffff;
            padding: 25px;
            border-radius: 12px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.06);
        }

        .page-header h1 {
            font-size: 28px;
            margin-bottom: 8px;
        }

        .page-header p {
            color: #6b7280;
        }

        .child-selector {
            background: #ffffff;
            padding: 20px;
            border-radius: 12px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.06);
        }

        .child-selector label {
            display: block;
            font-weight: bold;
            margin-bottom: 8px;
        }

        .child-selector select {
            width: 100%;
            max-width: 500px;
            padding: 12px;
            border: 1px solid #d1d5db;
            border-radius: 8px;
            font-size: 15px;
            background: white;
        }

        .student-info {
            background: #ffffff;
            padding: 20px;
            border-radius: 12px;
            margin-bottom: 25px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.06);
        }

        .student-info h2 {
            margin-bottom: 12px;
        }

        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 15px;
        }

        .info-item {
            background: #f8fafc;
            padding: 14px;
            border-radius: 8px;
        }

        .info-item span {
            display: block;
            font-size: 12px;
            color: #6b7280;
            margin-bottom: 5px;
            text-transform: uppercase;
        }

        .info-item strong {
            font-size: 15px;
        }

        .day-card {
            background: #ffffff;
            border-radius: 12px;
            margin-bottom: 20px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.06);
        }

        .day-header {
            padding: 16px 20px;
            background: #2563eb;
            color: white;
            font-size: 18px;
            font-weight: bold;
        }

        .table-wrapper {
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th {
            background: #f8fafc;
            padding: 14px;
            text-align: left;
            font-size: 13px;
            color: #4b5563;
            border-bottom: 1px solid #e5e7eb;
        }

        td {
            padding: 14px;
            border-bottom: 1px solid #e5e7eb;
            vertical-align: middle;
        }

        tr:last-child td {
            border-bottom: none;
        }

        .subject {
            font-weight: bold;
        }

        .subject-code {
            display: block;
            font-size: 12px;
            color: #6b7280;
            margin-top: 3px;
        }

        .time {
            font-weight: bold;
            white-space: nowrap;
        }

        .teacher {
            color: #374151;
        }

        .empty-day {
            padding: 20px;
            color: #6b7280;
            text-align: center;
        }

        .no-children {
            background: #ffffff;
            padding: 40px;
            text-align: center;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.06);
        }

        .no-children h2 {
            margin-bottom: 10px;
        }

        .no-children p {
            color: #6b7280;
        }

        .no-timetable {
            background: #fff3cd;
            color: #856404;
            border: 1px solid #ffeeba;
            padding: 25px;
            border-radius: 12px;
            margin-top: 20px;
        }

        .no-timetable h3 {
            margin-bottom: 8px;
        }

        .debug-info {
            margin-top: 15px;
            padding: 12px;
            background: rgba(255, 255, 255, 0.6);
            border-radius: 8px;
            font-size: 13px;
        }

        @media (max-width: 700px) {

            .container {
                width: 92%;
                margin: 20px auto;
            }

            .page-header h1 {
                font-size: 22px;
            }

            th,
            td {
                padding: 10px;
                font-size: 13px;
            }
        }
    </style>
</head>

<body>

<div class="container">

    <nav class="school-links" aria-label="School navigation">
        <a href="../../parent.php">Dashboard</a>
        <a href="index.php">Academics</a>
        <a href="../../fees.php">Fees</a>
        <a href="../../messages.php">Messages</a>
        <a href="../../calender.php">Calendar</a>
    </nav>

    <!-- PAGE HEADER -->
    <div class="page-header">

        <h1>📅 Timetable</h1>

        <p>
            View your child's class timetable and daily lessons.
        </p>

    </div>


    <?php if (empty($children)): ?>

        <!-- NO CHILDREN -->
        <div class="no-children">

            <h2>No Child Linked</h2>

            <p>
                There is currently no student linked to your parent account.
                Please contact the school administrator.
            </p>

        </div>

    <?php else: ?>

        <!-- CHILD SELECTOR -->
        <div class="child-selector">

            <form method="GET">

                <label for="student_id">
                    Select Child
                </label>

                <select
                    name="student_id"
                    id="student_id"
                    onchange="this.form.submit()"
                >

                    <?php foreach ($children as $child): ?>

                        <option
                            value="<?= e($child['student_id']) ?>"
                            <?= (
                                $selectedChild &&
                                (int) $selectedChild['student_id'] === (int) $child['student_id']
                            ) ? 'selected' : '' ?>
                        >

                            <?= e($child['full_name']) ?>
                            -
                            <?= e($child['class_name']) ?>
                            <?= e($child['stream_name']) ?>

                        </option>

                    <?php endforeach; ?>

                </select>

            </form>

        </div>


        <?php if ($selectedChild): ?>

            <!-- STUDENT INFORMATION -->
            <div class="student-info">

                <h2>
                    <?= e($selectedChild['full_name']) ?>
                </h2>

                <div class="info-grid">

                    <div class="info-item">

                        <span>Admission Number</span>

                        <strong>
                            <?= e($selectedChild['adm_number']) ?>
                        </strong>

                    </div>

                    <div class="info-item">

                        <span>Class</span>

                        <strong>
                            <?= e($selectedChild['class_name']) ?>
                        </strong>

                    </div>

                    <div class="info-item">

                        <span>Stream</span>

                        <strong>
                            <?= e($selectedChild['stream_name']) ?>
                        </strong>

                    </div>

                    <div class="info-item">

                        <span>Status</span>

                        <strong>
                            <?= e($selectedChild['status']) ?>
                        </strong>

                    </div>

                </div>

            </div>


            <?php if (empty($timetable)): ?>

                <!-- NO TIMETABLE -->
                <div class="no-timetable">

                    <h3>Timetable Not Available</h3>

                    <p>
                        No timetable has been created for
                        <strong>
                            <?= e($selectedChild['class_name']) ?>
                            <?= e($selectedChild['stream_name']) ?>
                        </strong>
                        yet.
                    </p>

                    <div class="debug-info">

                        <strong>Class ID:</strong>
                        <?= e($selectedChild['class_id']) ?>

                        <br>

                        <strong>Student ID:</strong>
                        <?= e($selectedChild['student_id']) ?>

                    </div>

                </div>

            <?php else: ?>


                <!-- TIMETABLE BY DAY -->

                <?php foreach ($days as $day): ?>

                    <div class="day-card">

                        <div class="day-header">

                            <?= e($day) ?>

                        </div>


                        <?php if (empty($groupedTimetable[$day])): ?>

                            <div class="empty-day">

                                No lessons scheduled for this day.

                            </div>

                        <?php else: ?>

                            <div class="table-wrapper">

                                <table>

                                    <thead>

                                        <tr>

                                            <th>
                                                Time
                                            </th>

                                            <th>
                                                Subject
                                            </th>

                                            <th>
                                                Teacher
                                            </th>

                                        </tr>

                                    </thead>

                                    <tbody>

                                    <?php foreach ($groupedTimetable[$day] as $lesson): ?>

                                        <tr>

                                            <td class="time">

                                                <?= e(formatTime($lesson['start_time'])) ?>

                                                -

                                                <?= e(formatTime($lesson['end_time'])) ?>

                                            </td>


                                            <td>

                                                <div class="subject">

                                                    <?= e($lesson['subject_name']) ?>

                                                </div>

                                                <?php if (!empty($lesson['subject_code'])): ?>

                                                    <span class="subject-code">

                                                        <?= e($lesson['subject_code']) ?>

                                                    </span>

                                                <?php endif; ?>

                                            </td>


                                            <td class="teacher">

                                                <?= e($lesson['teacher_name']) ?>

                                            </td>

                                        </tr>

                                    <?php endforeach; ?>

                                    </tbody>

                                </table>

                            </div>

                        <?php endif; ?>

                    </div>

                <?php endforeach; ?>

            <?php endif; ?>

        <?php endif; ?>

    <?php endif; ?>

</div>

</body>

</html>