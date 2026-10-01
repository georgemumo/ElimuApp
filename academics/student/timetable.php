<?php

require_once __DIR__ . '/_bootstrap.php';

$stmt = $conn->prepare("
    SELECT
        t.timetable_id,
        t.day_of_week,
        t.start_time,
        t.end_time,
        s.subject_id,
        s.subject_code,
        s.subject_name,
        te.teacher_id,
        te.full_name AS teacher_name
    FROM timetables t
    INNER JOIN subjects s ON t.subject_id = s.subject_id
    INNER JOIN teachers te ON t.teacher_id = te.teacher_id
    WHERE t.class_id = ?
    ORDER BY
        CASE t.day_of_week
            WHEN 'MONDAY' THEN 1
            WHEN 'TUESDAY' THEN 2
            WHEN 'WEDNESDAY' THEN 3
            WHEN 'THURSDAY' THEN 4
            WHEN 'FRIDAY' THEN 5
            WHEN 'SATURDAY' THEN 6
        END,
        t.start_time
");

$stmt->execute([$classId]);

$timetableRows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$days = [
    'MONDAY' => [],
    'TUESDAY' => [],
    'WEDNESDAY' => [],
    'THURSDAY' => [],
    'FRIDAY' => [],
    'SATURDAY' => []
];

foreach ($timetableRows as $row) {
    if (isset($days[$row['day_of_week']])) {
        $days[$row['day_of_week']][] = $row;
    }
}

?>

<?php
$pageTitle = 'My Timetable';
require __DIR__ . '/_layout.php';
?>

<style>

    * {
        box-sizing: border-box;
    }

    body {
        margin: 0;
        font-family:
            "Segoe UI",
            Arial,
            sans-serif;
        background: #f4f7fb;
        color: #173b5d;
    }

    .timetable-topbar {
        background: white;
        border-radius: 14px;
        padding: 20px 24px;
        margin-bottom: 25px;
        box-shadow:
            0 4px 15px rgba(0,0,0,0.05);
    }

    .timetable-topbar h1 {
        margin: 0;
        font-size: 24px;
    }

    .timetable-student-info {
        margin-top: 6px;
        color: #71869a;
        font-size: 13px;
    }

    .timetable-student-info strong {
        color: #173b5d;
    }

    .day-card {
        background: white;
        border-radius: 14px;
        margin-bottom: 22px;
        overflow: hidden;
        box-shadow:
            0 4px 15px rgba(0,0,0,0.05);
    }

    .day-header {
        background:
            linear-gradient(
                100deg,
                #2869ee,
                #079bc5
            );
        color: white;
        padding: 15px 20px;
        font-weight: 800;
    }

    .lesson {
        display: grid;
        grid-template-columns: 130px 1fr 180px;
        gap: 20px;
        align-items: center;
        padding: 17px 20px;
        border-bottom: 1px solid #edf1f5;
    }

    .lesson:last-child {
        border-bottom: none;
    }

    .time {
        font-weight: 700;
        color: #2869ee;
        font-size: 13px;
    }

    .subject-name {
        font-weight: 800;
        font-size: 14px;
        color: #173b5d;
    }

    .subject-code {
        color: #8294a5;
        font-size: 11px;
        margin-top: 4px;
    }

    .teacher {
        color: #5f7488;
        font-size: 12px;
    }

    .empty {
        background: white;
        padding: 50px;
        border-radius: 14px;
        text-align: center;
        box-shadow:
            0 4px 15px rgba(0,0,0,0.05);
    }

    .empty h2 {
        margin-bottom: 8px;
    }

    .empty p {
        color: #71869a;
        font-size: 13px;
    }

    @media (max-width: 800px) {

        .lesson {
            grid-template-columns: 1fr;
            gap: 7px;
        }

    }

</style>

<div class="timetable-topbar">

    <h1>
        My Timetable
    </h1>

    <div class="timetable-student-info">

        Student:
        <strong>
            <?= htmlspecialchars($studentName) ?>
        </strong>

        &nbsp; | &nbsp;

        Admission No:
        <strong>
            <?= htmlspecialchars($student["adm_number"]) ?>
        </strong>

        &nbsp; | &nbsp;

        Class:
        <strong>
            <?= htmlspecialchars($student["class_name"]) ?>
            <?= htmlspecialchars($student["stream_name"]) ?>
        </strong>

    </div>

</div>


<?php if (empty($timetableRows)): ?>

    <div class="empty">

        <h2>
            No Timetable Available
        </h2>

        <p>
            There is currently no timetable assigned
            to your class.
        </p>

        <p>
            Your class is:
            <strong>
                <?= htmlspecialchars($student["class_name"]) ?>
                <?= htmlspecialchars($student["stream_name"]) ?>
            </strong>
        </p>

    </div>

<?php else: ?>


    <?php foreach ($days as $day => $lessons): ?>

        <?php if (!empty($lessons)): ?>

            <div class="day-card">

                <div class="day-header">

                    <?= htmlspecialchars($day) ?>

                </div>


                <?php foreach ($lessons as $lesson): ?>

                    <div class="lesson">


                        <div class="time">

                            <?= date(
                                "g:i A",
                                strtotime($lesson["start_time"])
                            ) ?>

                            -

                            <?= date(
                                "g:i A",
                                strtotime($lesson["end_time"])
                            ) ?>

                        </div>


                        <div>

                            <div class="subject-name">

                                <?= htmlspecialchars(
                                    $lesson["subject_name"]
                                ) ?>

                            </div>

                            <div class="subject-code">

                                <?= htmlspecialchars(
                                    $lesson["subject_code"]
                                ) ?>

                            </div>

                        </div>


                        <div class="teacher">

                            👨‍🏫

                            <?= htmlspecialchars(
                                $lesson["teacher_name"]
                            ) ?>

                        </div>


                    </div>

                <?php endforeach; ?>

            </div>

        <?php endif; ?>

    <?php endforeach; ?>


<?php endif; ?>

<?php require __DIR__ . '/_footer.php'; ?>
