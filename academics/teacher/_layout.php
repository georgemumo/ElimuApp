<?php $pageTitle = $pageTitle ?? 'Teacher Portal'; ?>
<!doctype html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,
 initial-scale=1"><title><?= teacherEscape($pageTitle) ?> | Smart School</title><link rel="stylesheet" href="../../assets/css/school.css"><style>
*{box-sizing:border-box}body{margin:0;font-family:Arial,Helvetica,sans-serif;background:#f4f7fb;color:#1f2937}.teacher-shell{display:flex;min-height:100vh}.teacher-nav{width:250px;flex:none;padding:24px 14px;background:linear-gradient(180deg,#061b41,#0b2e6b 60%,#10458f);color:#fff}.teacher-nav h2{margin:0 10px 4px;font-size:20px}.teacher-nav p{margin:0 10px 26px;color:#bfdbfe;font-size:12px}.teacher-nav a{display:block;padding:11px 12px;margin:3px 0;border-radius:8px;color:#fff;text-decoration:none;font-size:14px}.teacher-nav a:hover,.teacher-nav a.active{background:rgba(255,255,255,.16)}.teacher-main{width:100%;max-width:1180px;padding:30px;margin:0 auto}.hero{display:flex;align-items:center;justify-content:space-between;gap:20px;padding:28px;border-radius:12px;background:linear-gradient(135deg,#061b41,#155a91);color:#fff}.hero h1{margin:4px 0 8px}.hero p{margin:0;color:#dbeafe}.eyebrow{font-size:11px;text-transform:uppercase;letter-spacing:1px;color:#bfdbfe!important}.button{display:inline-block;padding:10px 14px;border-radius:7px;background:#fff;color:#0b2e6b;text-decoration:none;font-weight:bold}.metrics{display:grid;grid-template-columns:repeat(4,1fr);gap:16px;margin:20px 0}.metrics article,.panel{background:#fff;border:1px solid #e5e7eb;border-radius:10px;padding:20px;box-shadow:0 3px 12px rgba(6,27,65,.06)}.metrics strong{display:block;font-size:26px;color:#061b41}.metrics span{font-size:12px;color:#64748b}.panel-heading{display:flex;justify-content:space-between;align-items:center}.panel-heading h2{margin:0;color:#061b41;font-size:19px}.panel-heading a{color:#0b2e6b;font-weight:bold;font-size:13px}.table-wrap{overflow-x:auto}table{width:100%;border-collapse:collapse;margin-top:14px}th,td{text-align:left;padding:12px;border-bottom:1px solid #edf0f4;font-size:13px}th{color:#64748b;font-size:11px;text-transform:uppercase}.empty{color:#64748b}@media(max-width:800px){.teacher-shell{display:block}.teacher-nav{width:100%}.teacher-nav a{display:inline-block}.teacher-main{padding:18px}.metrics{grid-template-columns:1fr 1fr}.hero{align-items:flex-start;flex-direction:column}}@media(max-width:480px){.metrics{grid-template-columns:1fr}}
</style>
</head><body><div class="teacher-shell">
    <aside class="teacher-nav"><h2>Smart School</h2>
    <p>Teacher Portal</p>
    <a class="active" href="index.php"><span class="nav-icon">🏠</span><span>Dashboard</span></a>
    <a href="my_classes.php"><span class="nav-icon">📚</span><span>My Classes</span></a>
    <a href="my_subjects.php"><span class="nav-icon">📖</span><span>My Subjects</span></a>
    <a href="assignments.php"><span class="nav-icon">📝</span><span>Assignments</span></a>
    <a href="exams.php"><span class="nav-icon">📋</span><span>Assessments</span></a>
    <a href="enter_marks.php"><span class="nav-icon">✍️</span><span>Enter Marks</span></a>
    <a href="manage_marks.php"><span class="nav-icon">📊</span><span>Manage Marks</span></a>
    <a href="attendance.php"><span class="nav-icon">✅</span><span>Attendance</span></a>
    <a href="timetable.php"><span class="nav-icon">🗓️</span><span>Timetable</span></a>
    <a href="resources.php"><span class="nav-icon">📚</span><span>Resources</span></a>
    <a href="reports.php"><span class="nav-icon">📈</span><span>Reports</span></a>
    <a href="at_risk.php"><span class="nav-icon">⚠️</span><span>At-risk Students</span></a>
    <a href="../../messages.php"><span class="nav-icon">💬</span><span>Messages</span></a>
    <a href="../../calender.php"><span class="nav-icon">📅</span><span>Calendar</span></a>
    <a href="../../profile.php"><span class="nav-icon">👤</span><span>Profile</span></a>
    <a href="../../logout.php"><span class="nav-icon">🚪</span><span>Logout</span></a>
    </aside><main class="teacher-main">