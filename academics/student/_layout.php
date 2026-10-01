<?php
$pageTitle = $pageTitle ?? 'Student Portal';
$studentName = isset($student['full_name']) ? (string) $student['full_name'] : 'Student';
?>
<!doctype html>
<html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title><?= studentEscape($pageTitle) ?> | Smart School</title>
        <link rel="stylesheet" href="../../assets/css/school.css">
        <style>
            *{box-sizing:border-box}
            body{margin:0;font-family:Arial,Helvetica,sans-serif;background:#f4f7fb;color:#1f2937}
            .student-shell{display:flex;min-height:100vh}
            .student-nav{width:250px;flex:none;padding:20px 15px;background:linear-gradient(180deg,#103553 0%,#0b5578 100%);color:#fff;display:flex;flex-direction:column}
            .student-brand{display:flex;align-items:center;gap:13px;padding:0 10px 20px}
            .student-brand-icon{width:45px;height:45px;border-radius:12px;background:rgba(255,255,255,.14);display:flex;align-items:center;justify-content:center;font-size:24px}
            .student-brand-name{font-size:23px;font-weight:800}
            .student-menu-title{font-size:12px;font-weight:800;color:rgba(255,255,255,.55);letter-spacing:1px;padding:0 12px;margin-bottom:10px}
            .student-nav nav{display:flex;flex-direction:column;gap:5px}
            .student-nav a{display:flex;align-items:center;gap:12px;padding:12px 13px;margin:0;border-radius:11px;color:rgba(255,255,255,.82);text-decoration:none;font-size:14px;font-weight:600}
            .nav-icon{width:22px;flex:none;text-align:center;font-size:17px}
            .student-nav a:hover,.student-nav a.active{background:rgba(255,255,255,.14);color:#fff}
            .student-nav a.active{border-left:3px solid #24c6f5;padding-left:10px}
            .student-submenu{display:block;margin:-2px 0 5px 34px;padding-left:10px;border-left:1px solid rgba(255,255,255,.2)}
            .student-submenu a{padding:8px 10px;border-radius:7px;font-size:13px;font-weight:500}
            .student-nav-bottom{margin-top:auto;border-top:1px solid rgba(255,255,255,.1);padding-top:15px}
            .student-user{display:flex;align-items:center;gap:10px;padding:8px;margin-bottom:8px}
            .student-avatar{width:36px;height:36px;border-radius:8px;background:#24b9e8;display:flex;align-items:center;justify-content:center;font-size:14px;font-weight:800}
            .student-user-name{font-size:14px;font-weight:700}
            .student-user-role{font-size:11px;color:rgba(255,255,255,.65);margin-top:3px}
            .student-main{width:100%;max-width:1180px;padding:30px;margin:0 auto}
            .hero{display:flex;align-items:center;justify-content:space-between;gap:20px;padding:28px;border-radius:12px;background:linear-gradient(135deg,#061b41,#155a91);color:#fff}
            .hero h1{margin:4px 0 8px}
            .hero p{margin:0;color:#dbeafe}
            .eyebrow{font-size:11px;text-transform:uppercase;letter-spacing:1px;color:#bfdbfe!important}
            .button{display:inline-block;padding:10px 14px;border-radius:7px;background:#fff;color:#0b2e6b;text-decoration:none;font-weight:bold}
            .metrics{display:grid;grid-template-columns:repeat(3,1fr);gap:16px;margin:20px 0}
            .metrics article,.panel{background:#fff;border:1px solid #e5e7eb;border-radius:10px;padding:20px;box-shadow:0 3px 12px rgba(6,27,65,.06)}
            .metrics strong{display:block;font-size:26px;color:#061b41}
            .metrics span{font-size:12px;color:#64748b}
            .panel-heading{display:flex;justify-content:space-between;align-items:center}
            .panel-heading h2{margin:0;color:#061b41;font-size:19px}
            .panel-heading a{color:#0b2e6b;font-weight:bold;font-size:13px}
            .performance-summary{display:grid;grid-template-columns:repeat(2,1fr);gap:16px;margin:20px 0}.performance-summary article,.performance-chart{background:#fff;border:1px solid #e5e7eb;border-radius:10px;padding:20px;box-shadow:0 3px 12px rgba(6,27,65,.06)}.performance-summary strong{display:block;font-size:26px;color:#061b41}.performance-summary span,.panel-heading span{font-size:12px;color:#64748b}.performance-chart h3{margin:0;color:#061b41;font-size:18px}.performance-chart svg{display:block;width:100%;height:220px;margin-top:15px}.chart-labels{display:flex;justify-content:space-between;color:#64748b;font-size:11px}
            .table-wrap{overflow-x:auto}
            table{width:100%;border-collapse:collapse;margin-top:14px}
            th,td{text-align:left;padding:12px;border-bottom:1px solid #edf0f4;font-size:13px}
            th{color:#64748b;font-size:11px;text-transform:uppercase}
            .empty{color:#64748b}
            @media(max-width:700px){.student-shell{display:block}.student-nav{width:100%}.student-nav a{display:inline-flex}.student-main{padding:18px}.metrics,.performance-summary{grid-template-columns:1fr}.hero{align-items:flex-start;flex-direction:column}}
        </style>
    </head>
    <body>
        <div class="student-shell">
            <aside class="student-nav">
                <div class="student-brand">
                    <div class="student-brand-icon">🎓</div>
                    <div class="student-brand-name">MySchool</div>
                </div>
                <div class="student-menu-title">MAIN MENU</div>
                <nav>
                    <a href="../../student.php"><span class="nav-icon">🏠</span><span>Dashboard</span></a>
                    <a href="../../profile.php"><span class="nav-icon">👤</span><span>Profile</span></a>
                    <a class="active" href="index.php"><span class="nav-icon">📚</span><span>Academics</span></a>
                    <div class="student-submenu">
                        <a href="index.php">Academic Dashboard</a>
                        <a href="timetable.php">Timetable</a>
                        <a href="assignments.php">Assignments</a>
                        <a href="submit_assignment.php">Submit Assignment</a>
                        <a href="resources.php">Learning Resources</a>
                        <a href="results.php">Results</a>
                        <a href="performance.php">Performance</a>
                        <a href="exams.php">Exams</a>
                        <a href="cbc.php">CBC Progress</a>
                        <a href="achievements.php">Achievements</a>
                    </div>
                    <a href="../../fees.php"><span class="nav-icon">💰</span><span>Fees</span></a>
                    <a href="../../messages.php"><span class="nav-icon">💬</span><span>Messages</span></a>
                    <a href="../../calender.php"><span class="nav-icon">📅</span><span>Calendar</span></a>
                </nav>
                <div class="student-nav-bottom">
                    <div class="student-user">
                        <div class="student-avatar"><?= studentEscape(substr($studentName, 0, 2)) ?></div>
                        <div><div class="student-user-name"><?= studentEscape($studentName) ?></div><div class="student-user-role">STUDENT</div></div>
                    </div>
                    <a href="../../logout.php"><span class="nav-icon">🚪</span><span>Logout</span></a>
                </div>
            </aside>
        <main class="student-main">